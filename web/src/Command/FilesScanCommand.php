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
                'conversion-format',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Into which format should it convert the found files?',
                'jpg'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $finder = new Finder();
        $filesystem = new Filesystem();
        $mimeTypes = new MimeTypes();

        $allowedImageConversionFormats = $this->params->get('allowed_image_conversion_formats');
        $conversionFormat = $input->getOption('conversion-format');
        if (!in_array($conversionFormat, $allowedImageConversionFormats)) {
            $io->error(
                'Invalid conversion format. Allowed: ' .
                implode(', ', $allowedImageConversionFormats)
            );

            return;
        }

        $allowedImageConversionTypes = $this->params->get('allowed_image_conversion_types');
        // Do not cache originals. They are on-demand
        unset($allowedImageConversionTypes['original']);

        $filesRepository = $this->em->getRepository(File::class);

        define('PROJECT_ROOT', dirname(__DIR__) . '/../..');

        // Get the settings
        try {
            $settings = Yaml::parseFile(PROJECT_ROOT . '/settings.yml');
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
                ->in($folder)
                ->sortByChangedTime();
            $filesCount = iterator_count($files);

            $io->section(
                sprintf(
                    'Starting to process folder (%s files found): %s',
                    $filesCount,
                    $folder
                )
            );

            $i = 0;
            foreach ($files as $fileObject) {
                $filePath = $fileObject->getRealPath();

                $io->text(
                    sprintf(
                        'Starting to process file #%s out of %s: %s',
                        $i + 1,
                        $filesCount,
                        $filePath
                    )
                );

                $i++;

                $fileHash = sha1($filePath);
                $fileMime = $mimeTypes->guessMimeType($filePath);
                $fileExtension = $fileObject->getExtension();
                $fileType = strpos($fileMime, 'image/') !== false
                    ? File::TYPE_IMAGE
                    : (strpos($fileMime, 'video/') !== false
                        ? File::TYPE_VIDEO
                        : File::TYPE_OTHER
                    );

                if ($fileType === File::TYPE_OTHER) {
                    $io->text(
                        sprintf(
                            'File %s is not an image, nor a video. Skipping ...',
                            $filePath
                        )
                    );

                    continue;
                }

                $file = $filesRepository->findOneByHash($fileHash);

                // TODO: instead of just skipping, maybe check if it's dirty?
                if ($file) {
                    $io->text(
                        sprintf(
                            'File %s already exists. Skipping ...',
                            $filePath
                        )
                    );

                    continue;
                }

                $fileData = [];
                if ($fileType === File::TYPE_IMAGE) {
                    $image = $this->fileManager->getImage($filePath);
                    $fileData['image'] = [
                        'width' => $image->width(),
                        'height' => $image->height(),
                    ];
                }

                $file = new File();
                $file
                    ->setHash($fileHash)
                    ->setType($fileType)
                    ->setPath($filePath)
                    ->setMime($fileMime)
                    ->setExtension($fileExtension)
                    ->setData($fileData)
                ;

                // Get the meta AFTER we've set the data, because it depends
                //   on some data there.
                $fileMeta = $file->getProcessedMeta();

                $takenAt = new \DateTime($fileMeta['date'] ?? '1970-01-01');

                $file
                    ->setCreatedAt(new \DateTime())
                    ->setModifiedAt(new \DateTime())
                    ->setTakenAt($takenAt)
                ;

                $this->em->persist($file);
                $this->em->flush();
                $this->em->clear();

                $io->text(sprintf('File %s saved.', $filePath));

                /********** Cache **********/
                $io->text(sprintf('Generating cache for %s ...', $filePath));

                try {
                    foreach ($allowedImageConversionTypes as $imageType => $imaageTypeData) {
                        $this->fileManager->generateImageCache(
                            $file,
                            $imageType,
                            $conversionFormat
                        );
                    }
                } catch (\Exception $e) {
                    $io->error(
                        sprintf(
                            'Generating cache for: %s FAILED. Message: %s',
                            $file->getPath(),
                            $e->getMessage()
                        )
                    );
                }
            }
        }

        $io->success('Success!');

        return true;
    }
}
