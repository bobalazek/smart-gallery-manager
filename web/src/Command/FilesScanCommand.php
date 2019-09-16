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
                ['meta', 'cache', 'geocode', 'label']
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

        // Get the settings
        try {
            $settings = Yaml::parseFile(
                dirname(__DIR__) . '/../../settings.yml'
            );
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());

            return;
        }

        // Log
        $this->logger->notice('Starting to process files ...');
        $this->logger->notice(sprintf(
            'Start time: %s',
            date(DATE_ATOM)
        ));
        $this->logger->notice(sprintf(
            'Actions: %s',
            implode(', ', $actions)
        ));
        $this->logger->notice(sprintf(
            'Memory usage: %s',
            $this->getMemoryUsageText()
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

        $filesCount = iterator_count($files);

        $this->logger->notice(sprintf(
            '%s files found in folders:',
            $filesCount
        ));

        foreach ($folders as $folder) {
            $this->logger->notice('* ' . $folder);
        }

        $i = 0;
        foreach ($files as $fileObject) {
            $i++;

            $filePath = $fileObject->getRealPath();

            $this->logger->notice(sprintf(
                '%s/%s [%s] -- Starting to process file: %s',
                $i,
                $filesCount,
                $this->getMemoryUsageText(),
                $filePath
            ));

            $fileHash = sha1($filePath);

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
            if (
                $isGeocodingEnabled &&
                (
                    in_array('geocode', $actions) ||
                    in_array('geocode:force', $actions)
                )
            ) {
                $this->logger->info('Geocoding ...');

                try {
                    $this->fileManager->geodecode(
                        $file,
                        !in_array('geocode:force', $actions)
                    );
                } catch (\Exception $e) {
                    $this->logger->warning($e->getMessage());
                }
            }

            /********** Label **********/
            if (
                $isLabellingEnabled &&
                (
                    in_array('label', $actions) ||
                    in_array('label:force', $actions)
                )
            ) {
                $this->logger->info('Labeling ...');

                try {
                    $this->fileManager->label(
                        $file,
                        !in_array('label:force', $actions)
                    );
                } catch (\Exception $e) {
                    $this->logger->warning($e->getMessage());
                }
            }

            // Save the entity
            $this->em->persist($file);

            if (($i % 50) === 0) {
                $this->em->flush();
                $this->em->clear();
                gc_collect_cycles();
            }
        }

        // Persist the remaining entities
        $this->em->flush();
        $this->em->clear();

        $this->logger->notice(sprintf(
            'End time: %s',
            date(DATE_ATOM)
        ));
        $this->logger->notice('Success!');

        return true;
    }

    /**
     * Gets the memory data & stuff
     */
    protected function getMemoryUsageText() {
        $startMemoryPeakUsage = (int)(memory_get_peak_usage() / 1024 / 1024) . 'MB';
        $startMemoryPeakUsageReal = (int)(memory_get_peak_usage(true) / 1024 / 1024) . 'MB';

        return sprintf(
            'current: %s, real: %s',
            $startMemoryPeakUsage,
            $startMemoryPeakUsageReal
        );
    }
}
