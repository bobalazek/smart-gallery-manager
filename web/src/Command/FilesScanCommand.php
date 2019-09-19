<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Mime\MimeTypes;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Manager\FileManager;
use App\Entity\File;

class FilesScanCommand extends Command
{
    protected static $defaultName = 'app:files:scan';

    public function __construct(
        ParameterBagInterface $params,
        EntityManagerInterface $em,
        FileManager $fileManager,
        LoggerInterface $logger
    )
    {
        $this->params = $params;
        $this->em = $em;
        $this->fileManager = $fileManager;
        $this->logger = $logger;

        $this->fileManager->setLogger($this->logger);

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Scans and enters/updates all the local files into the database.')
            ->addOption(
                'update-existing-entries',
                'u',
                InputOption::VALUE_OPTIONAL,
                'Should we update the existing entries in the database (when meta changes for example)?',
                false
            )
            ->addOption(
                'action',
                'a',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'What actions should be executed - must be a comma-separated value: Default: bin/console app:files:scan -a meta -a cache -a geocode -a label. ' .
                'You can also use "geocode:force" (instead of "geocode") and "label:force" (instead of "label") to force the API to get new data, ' .
                'instead of the cached one locally.',
                ['meta', 'cache', 'geocode', 'label' , 'faces']
            )
            ->addOption(
                'folder',
                'f',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Which folder do you want to scan? The value is an array, so you can chain it, like: ' .
                '"bin/console app:files:scan -f /one/directory -f /another/dir -f /yetanother/dir"'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $finder = new Finder();
        $mimeTypes = new MimeTypes();
        $filesRepository = $this->em->getRepository(File::class);

        $isGeocodingEnabled = $this->params->get('geocoding_enabled');
        $isLabellingEnabled = $this->params->get('labelling_enabled');

        $actions = $input->getOption('action');
        $updateExistingEntries = $input->getOption('update-existing-entries') !== false;

        $shouldGeocode = $isGeocodingEnabled && (
            in_array('geocode', $actions) ||
            in_array('geocode:force', $actions)
        );
        $shouldLabel = $isLabellingEnabled && (
            in_array('label', $actions) ||
            in_array('label:force', $actions)
        );
        $shouldFaces = (
            in_array('faces', $actions) ||
            in_array('faces:force', $actions)
        );

        $geocodeForce = in_array('geocode:force', $actions);
        $labelForce = in_array('label:force', $actions);
        $facesForce = in_array('faces:force', $actions);

        // Get the settings
        try {
            $settings = Yaml::parseFile(
                dirname(__DIR__) . '/../../settings.yml'
            );
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            return;
        }

        if (empty($actions)) {
            $this->logger->critical('You need to specify at least one action');
            return;
        }

        // General
        $this->logger->notice('======== General information ========');
        $this->logger->notice(sprintf(
            'Start time: %s',
            date(DATE_ATOM)
        ));
        $this->logger->notice(
            'Update existing entries: ' .
            ($updateExistingEntries ? 'yes' : 'no')
        );
        $this->logger->notice('Actions:');
        foreach ($actions as $action) {
            $isDisabled = (
                (
                    !$shouldLabel &&
                    ($action === 'label' || $action === 'label:force')
                ) ||
                (
                    !$shouldGeocode &&
                    ($action === 'geocode' || $action === 'geocode:force')
                )
            );
            $this->logger->notice(
                '* ' . $action .
                ($isDisabled
                    ? ' (disabled)'
                    : '')
            );
        }

        $this->logger->notice(sprintf(
            'Memory usage: %s',
            $this->_getMemoryUsageText()
        ));

        // Browse the folders
        $folders = $settings['folders'];
        $folderOption = $input->getOption('folder');

        if (count($folderOption) > 0) {
            $folders = $folderOption;
        }

        $files = $finder
            ->files()
            ->ignoreUnreadableDirs()
            ->followLinks()
            ->in($folders)
            ->sortByChangedTime()
            ->reverseSorting()
        ;

        $this->logger->debug('Calculating the total number of files in folders. This may take a while ...');

        $filesCount = iterator_count($files);
        $newFilesCount = $filesCount;

        $existingFilePathsMap = [];
        $existingFiles = $this->em->createQueryBuilder()
            ->select('f.path')
            ->from(File::class, 'f')
            ->getQuery()
            ->getScalarResult();
        foreach ($existingFiles as $existingFile) {
            $existingFilePathsMap[$existingFile['path']] = true;
        }

        foreach ($files as $fileObject) {
            if (isset($existingFilePathsMap[$fileObject->getRealPath()])) {
                $newFilesCount--;
            }
        }

        $this->logger->notice('-------- Files & folders --------');
        $this->logger->notice(sprintf(
            'Total files count: %s',
            $filesCount
        ));
        $this->logger->notice(sprintf(
            'New files count: %s',
            $newFilesCount
        ));

        $this->logger->notice('Folders:');
        foreach ($folders as $folder) {
            $this->logger->notice('* ' . $folder);
        }

        if ($shouldGeocode) {
            $this->logger->notice('-------- Geocoding --------');

            $filesToBeGeocoded = 0;
            if (!$updateExistingEntries) {
                $filesToBeGeocoded = $newFilesCount;
            } elseif (
                $geocodeForce &&
                $updateExistingEntries
            ) {
                $filesToBeGeocoded = $newFilesCount +
                    $this->_getExistingFilesWithGeolocationCount($folders);
            } elseif (
                !$geocodeForce &&
                $updateExistingEntries
            ) {
                $this->logger->debug('Calculating the number of files that still need to be geocoded. This may take a while ...');

                $filesToBeGeocoded = $newFilesCount +
                    $this->_getExistingFilesWithoutGeolocationCount($existingFilePathsMap) -
                    $this->_getExistingFilesWithGeolocationCount($folders);
            }

            $this->logger->notice(sprintf(
                'Maximum files to be geocoded: %s',
                $filesToBeGeocoded
            ));
            $this->logger->notice('Note: This is an upper estimate. It is assumed that all new files will contain geolocation data, which in reality it may not.');
        }

        if ($shouldLabel) {
            $this->logger->notice('-------- Labelling --------');

            $filesToBeLabeled = 0;
            if (!$updateExistingEntries) {
                $filesToBeLabeled = $newFilesCount;
            } elseif (
                $labelForce &&
                $updateExistingEntries
            ) {
                $filesToBeLabeled = $filesCount;
            } elseif (
                !$geocodeForce &&
                $updateExistingEntries
            ) {
                $this->logger->debug('Calculating the number of files that still need to be labeled. This may take a while ...');

                $filesToBeLabeled = $newFilesCount +
                    $this->_getExistingUnlabeledFilesCount($existingFilePathsMap);
            }

            $this->logger->notice(sprintf(
                'Files to be labeled: %s',
                $filesToBeLabeled
            ));
        }

        $this->logger->notice(sprintf(
            'Memory usage: %s',
            $this->_getMemoryUsageText()
        ));

        // File processing
        $this->logger->notice('======== File processing ... ========');

        $i = 0;
        foreach ($files as $fileObject) {
            $i++;

            $filePath = $fileObject->getRealPath();

            $this->logger->notice(sprintf(
                '%s/%s [%s] -- Starting to process file: %s',
                $i,
                $filesCount,
                $this->_getMemoryUsageText(),
                $filePath
            ));

            $fileHash = $this->fileManager->generateFilePathHash($filePath);

            $file = $filesRepository->findOneByHash($fileHash);
            $fileExists = $file !== null;

            if (
                $fileExists &&
                !$updateExistingEntries
            ) {
                $this->logger->debug('File already exists. Skipping ...');

                $this->em->detach($file);

                continue;
            }

            if (!$fileExists) {
                $file = new File();
                $file
                    ->setHash($fileHash)
                    ->setCreatedAt(new \DateTime())
                ;
            }

            $fileMime = $mimeTypes->guessMimeType($filePath);
            $fileExtension = $fileObject->getExtension();
            $fileType = strpos($fileMime, 'image/') !== false
                ? File::TYPE_IMAGE
                : (strpos($fileMime, 'video/') !== false
                    ? File::TYPE_VIDEO
                    : (strpos($fileMime, 'audio/') !== false
                        ? File::TYPE_AUDIO
                        : File::TYPE_OTHER
                    )
                );

            $file
                ->setType($fileType)
                ->setPath($filePath)
                ->setMime($fileMime)
                ->setExtension($fileExtension)
                ->setModifiedAt(new \DateTime())
            ;

            if (in_array('meta', $actions)) {
                $this->logger->info('Meta ...');

                $file
                    ->setMeta($this->fileManager->getFileMeta($file))
                    ->setTakenAt(new \DateTime($file->getMeta()['date'] ?? '1970-01-01'))
                ;
            } elseif (
                !$fileExists &&
                !in_array('meta', $actions)
            ) {
                $file->setTakenAt(new \DateTime('1970-01-01'));
            }

            /********** Prepare **********/
            $this->fileManager->prepare($file);

            /********** Cache **********/
            if (in_array('cache', $actions)) {
                $this->logger->info('Caching ...');

                try {
                    $this->fileManager->cache($file);
                } catch (\Exception $e) {
                    $this->logger->warning($e->getMessage());
                }
            }

            /********** Geocode  **********/
            if ($shouldGeocode) {
                $this->logger->info('Geocoding ...');

                try {
                    $this->fileManager->geodecode(
                        $file,
                        !$geocodeForce
                    );
                } catch (\Exception $e) {
                    $this->logger->warning($e->getMessage());
                }
            }

            /********** Label **********/
            if ($shouldLabel) {
                $this->logger->info('Labeling ...');

                try {
                    $this->fileManager->label(
                        $file,
                        !$labelForce
                    );
                } catch (\Exception $e) {
                    $this->logger->warning($e->getMessage());
                }
            }

            /********** Faces **********/
            if ($shouldFaces) {
                $this->logger->info('Faces ...');

                try {
                    $this->fileManager->faces(
                        $file,
                        !$facesForce
                    );
                } catch (\Exception $e) {
                    $this->logger->warning($e->getMessage());
                }
            }

            /********** Persist **********/
            // Save the entity
            $this->em->persist($file);

            if (($i % 25) === 0) {
                $this->em->flush();
                $this->em->clear();
                gc_collect_cycles();
            }
        }

        // Persist the remaining entities
        $this->em->flush();
        $this->em->clear();

        $this->logger->notice('======== File processing completed ========');

        $this->logger->notice(sprintf(
            'End time: %s',
            date(DATE_ATOM)
        ));
        $this->logger->notice('Success!');

        return true;
    }

    /**
     * Gets the memory data
     *
     * @return string
     */
    private function _getMemoryUsageText() {
        $startMemoryPeakUsage = (int)(memory_get_peak_usage() / 1024 / 1024) . 'MB';
        $startMemoryPeakUsageReal = (int)(memory_get_peak_usage(true) / 1024 / 1024) . 'MB';

        return sprintf(
            'current: %s, real: %s',
            $startMemoryPeakUsage,
            $startMemoryPeakUsageReal
        );
    }

    /**
     * Gets the number of existing files without geolocation
     *
     * @param array $existingFilePathsMap
     *
     * @return int
     */
    private function _getExistingFilesWithoutGeolocationCount($existingFilePathsMap) {
        $filesDataDir = $this->fileManager->getFilesDataDir();
        $geocodeFileName = $this->fileManager->getGeocodeFileName();
        $count = 0;

        foreach ($existingFilePathsMap as $filePath => $_) {
            $fileHash = $this->fileManager->generateFilePathHash($filePath);
            $geocodeFilePath = $this->fileManager->getFileDataDir($fileHash)
                . '/' . $geocodeFileName;

            if (!file_exists($geocodeFilePath)) {
                $count++;
            }
        }

        return $count;
    }
    /**
     * Gets the number of existing files with geolocation
     *
     * @param array $folders
     *
     * @return int
     */
    private function _getExistingFilesWithGeolocationCount($folders) {
        $count = 0;

        $qb = $this->em->createQueryBuilder()
            ->select('f.meta')
            ->from(File::class, 'f');
        foreach ($folders as $i => $folder) {
            $qb
                ->orWhere('f.path LIKE :path' . $i)
                ->setParameter('path' . $i, $folder . '%')
            ;
        }

        $existingFiles = $qb->getQuery()->getScalarResult();
        foreach ($existingFiles as $existingFile) {
            $meta = json_decode($existingFile['meta'], true);

            if (
                !empty($meta) &&
                !empty($meta['geolocation']) &&
                !empty($meta['geolocation']['latitude']) &&
                !empty($meta['geolocation']['longitude'])
            ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Gets the number of existing files, that have no labeling file yet
     *
     * @param array $existingFilePathsMap
     *
     * @return int
     */
    private function _getExistingUnlabeledFilesCount($existingFilePathsMap) {
        $labelFileName = $this->fileManager->getLabelFileName();
        $count = 0;

        foreach ($existingFilePathsMap as $filePath => $_) {
            $fileHash = $this->fileManager->generateFilePathHash($filePath);
            $labelFilePath = $this->fileManager->getFileDataDir($fileHash)
                . '/' . $labelFileName;

            if (!file_exists($labelFilePath)) {
                $count++;
            }
        }

        return $count;
    }
}
