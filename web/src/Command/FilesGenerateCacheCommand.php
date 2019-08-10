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

class FilesGenerateCacheCommand extends Command
{
    protected static $defaultName = 'app:files:generate-cache';

    public function __construct(ParameterBagInterface $params, EntityManagerInterface $em, FileManager $fileManager)
    {
        $this->params = $params;
        $this->em = $em;
        $this->fileManager = $fileManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Generates the cache for all the local files, inserted inside the database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $format = 'jpg';
        $imageTypes = array_keys($this->params->get('allowed_image_conversion_types'));

        // Do not cache originals. They are on-demand
        unset($imageTypes['original']);

        define('PROJECT_ROOT', dirname(__DIR__) . '/../..');

        $files = $this->em->createQueryBuilder()
            ->select('f')
            ->from(File::class, 'f')
            ->where('f.type = :type')
            ->orderBy('f.takenAt', 'DESC')
            ->setParameter('type', 'image')
            ->getQuery()
            ->getResult();
        $filesCount = count($files);

        $io->text(sprintf('Found %s files. Starting to generate the thumbnails ...', $filesCount));

        foreach ($files as $i => $file) {
            $io->text(
                sprintf(
                    'Generating thumbnail (#%s out of %s) for: %s',
                    $i + 1,
                    $filesCount,
                    $file->getPath()
                )
            );

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

        $io->success('Success!');

        return true;
    }
}
