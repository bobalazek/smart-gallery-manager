<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Manager\FileManager;
use App\Entity\File;

class FilesScanCommand extends Command
{
    protected static $defaultName = 'app:files:scan';

    public function __construct(ParameterBagInterface $params, EntityManagerInterface $em, FileManager $fileManager)
    {
        $this->params = $params;
        $this->em = $em;
        $this->fileManager = $fileManager;

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
                'actions',
                'a',
                InputOption::VALUE_OPTIONAL,
                'What actions should be executed - must be a comma-separated value: Default: "meta,cache,geocode,label". ' .
                'You can also use "geocode:force" (instead of "geocode") and "label:force" (instead of "label") to force the API to get new data, ' .
                'instead of the cached one locally.',
                'meta,cache,geocode,label'
            )
            ->addOption(
                'folder',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Which folder do you want to scan? Note: that will override the existing folders from settings.yml',
                null
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $finder = new Finder();
        $mimeTypes = new MimeTypes();
        $isGeocodingEnabled = $this->params->get('geocoding_enabled');
        $isLabellingEnabled = $this->params->get('labelling_enabled');
        $actions = explode(',', $input->getOption('actions'));
        $updateExistingEntries = $input->getOption('update-existing-entries') !== false;

        $filesRepository = $this->em->getRepository(File::class);

        // Get the settings
        try {
            $settings = Yaml::parseFile(
                dirname(__DIR__) . '/../../settings.yml'
            );
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return;
        }

        // Browse the folders
        $folders = $settings['folders'];

        if ($input->getOption('folder')) {
            $folders = [$input->getOption('folder')];
        }

        $files = $finder->files()
            ->ignoreUnreadableDirs()
            ->followLinks()
            ->in($folders);

        $filesCount = iterator_count($files);

        $io->newLine();
        $io->text(sprintf(
            '%s files found in folders:',
            $filesCount
        ));
        $io->newLine();
        foreach ($folders as $folder) {
            $io->text('* ' . $folder);
        }
        $io->newLine();

        // TODO: optimize. Maybe fetch all the files here from the DB,
        //   create a map ([path] => file) and compare it then,
        //   if we've set to skip existing entries

        $i = 0;
        foreach ($files as $fileObject) {
            $i++;

            $filePath = $fileObject->getRealPath();

            $io->text(
                sprintf(
                    '%s/%s [%sMB] -- Starting to process file: %s',
                    $i,
                    $filesCount,
                    (int)(memory_get_peak_usage() / 1024 / 1024),
                    $filePath
                )
            );

            $fileHash = sha1($filePath);

            $file = $filesRepository->findOneByHash($fileHash);
            $fileExists = $file !== null;

            if (
                $fileExists &&
                !$updateExistingEntries
            ) {
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
                try {
                    $this->fileManager->cache($file);
                } catch (\Exception $e) {
                    $io->newLine();
                    $io->error($e->getMessage());
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
                try {
                    $this->fileManager->geodecode(
                        $file,
                        !in_array('geocode:force', $actions)
                    );
                } catch (\Exception $e) {
                    $io->newLine();
                    $io->error($e->getMessage());
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
                try {
                    $this->fileManager->label(
                        $file,
                        !in_array('label:force', $actions)
                    );
                } catch (\Exception $e) {
                    $io->newLine();
                    $io->error($e->getMessage());
                }
            }

            // Finally, save the file into the DB
            $this->em->persist($file);
            $this->em->flush();
            $this->em->clear();
        }

        $io->success('Success!');

        return true;
    }
}
