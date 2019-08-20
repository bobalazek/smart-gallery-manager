<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
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
                'folder',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Which folder do you want to scan? Note: that will override the existing folders from settings.yml',
                null
            )
            ->addOption(
                'skip-generate-cache',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Should we skip the caching of the images?',
                false
            )
            ->addOption(
                'update-existing-entries',
                'u',
                InputOption::VALUE_OPTIONAL,
                'Should we update the existing entries in the database (when meta changes for example)?',
                false
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        ProgressBar::setFormatDefinition('custom', ' %current%/%max% -- %message% (%filename%)');
        $io = new SymfonyStyle($input, $output);
        $finder = new Finder();
        $filesystem = new Filesystem();
        $mimeTypes = new MimeTypes();
        $skipGenerateCache = $input->getOption('skip-generate-cache') !== false;
        $updateExistingEntries = $input->getOption('update-existing-entries') !== false;

        $allowedImageConversionTypes = $this->params->get('allowed_image_conversion_types');
        // Do not cache originals. They are on-demand
        unset($allowedImageConversionTypes['original']);

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

        foreach ($folders as $folder) {
            $files = $finder->files()
                ->ignoreUnreadableDirs()
                ->followLinks()
                ->in($folder)
                ->sortByChangedTime();

            $io->newLine();
            $io->section(
                sprintf(
                    'Starting to process folder: %s',
                    $folder
                )
            );

            // TODO: optimize. Maybe fetch all the files in this folder here,
            //   create a map ([path] => file) and compare it then,
            //   if we've set to skip existing entries

            $progressBar = new ProgressBar($output);
            $progressBar->setFormat('custom');

            $progressBar->setMessage('Processing files...');
            $progressBar->setMessage('...', 'filename');

            foreach ($progressBar->iterate($files) as $fileObject) {
                $filePath = $fileObject->getRealPath();

                $progressBar->setMessage('Processing files...');
                $progressBar->setMessage($filePath, 'filename');

                $fileHash = sha1($filePath);
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

                $file
                    ->setType($fileType)
                    ->setPath($filePath)
                    ->setMime($fileMime)
                    ->setExtension($fileExtension)
                    ->setMeta($this->fileManager->getFileMeta($file))
                    ->setModifiedAt(new \DateTime())
                    ->setTakenAt(new \DateTime($file->getMeta()['date'] ?? '1970-01-01'))
                ;

                $this->em->persist($file);
                $this->em->flush();
                $this->em->clear();

                if (!$skipGenerateCache) {
                    /********** Cache **********/
                    try {
                        foreach ($allowedImageConversionTypes as $imageType => $imageTypeData) {
                            $this->fileManager->generateImageCache(
                                $file,
                                $imageType
                            );
                        }
                    } catch (\Exception $e) {
                        $io->error($e->getMessage());
                    }
                }
            }
        }

        $io->success('Success!');

        return true;
    }
}
