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
        $this->setDescription('Scans and enters/updates all the local files into the database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $finder = new Finder();
        $filesystem = new Filesystem();
        $mimeTypes = new MimeTypes();
        $format = 'jpg';
        $imageTypes = array_keys($this->params->get('allowed_image_conversion_types'));
        $filesRepository = $this->em->getRepository(File::class);

        define('PROJECT_ROOT', dirname(__DIR__) . '/../..');

        // Get the settings
        try {
            $settings = Yaml::parseFile(PROJECT_ROOT . '/settings.yml');
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return false;
        }

        // Browse the folders
        $folders = $settings['folders'];
        foreach ($folders as $folder) {
            $files = $finder->in($folder)->files();
            $io->section(sprintf('Starting to process folder: %s', $folder));

            foreach ($files as $fileObject) {
                $filePath = $fileObject->getRealPath();

                $io->text(sprintf('Starting to process file: %s', $filePath));

                $fileHash = sha1($filePath);
                $fileMime = $mimeTypes->guessMimeType($filePath);
                $fileExtension = $fileObject->getExtension();
                $fileType = strpos($fileMime, 'image/') !== false
                    ? File::TYPE_IMAGE
                    : (strpos($fileMime, 'video/') !== false
                        ? File::TYPE_VIDEO
                        : File::TYPE_OTHER
                    );

                $file = $filesRepository->findOneByHash($fileHash);
                if ($file) {
                    $io->text(sprintf('File %s already exists. Skipping ...', $filePath));
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
                $fileMeta = $file->getMeta();

                $takenAt = new \DateTime('1970-01-01');
                if ($fileMeta['date']) {
                    $takenAt = new \DateTime($fileMeta['date']);
                }

                $file
                    ->setCreatedAt(new \DateTime())
                    ->setModifiedAt(new \DateTime())
                    ->setTakenAt($takenAt)
                ;

                $this->em->persist($file);
                $this->em->flush();
                $this->em->clear();

                $io->text(sprintf('File %s saved.', $filePath));

                $io->text(sprintf('Generating cache for %s ...', $filePath));

                try {
                    foreach ($imageTypes as $imageType) {
                        $this->fileManager->generateImageCache(
                            $file,
                            $imageType,
                            $format
                        );
                    }
                } catch (\Exception $e) {
                    $io->error(
                        sprintf(
                            'Generating thumbnail for: %s FAILED. Message: %s',
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
