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
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Image;

class ImagesCommand extends Command
{
    protected static $defaultName = 'app:images';

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Prepares all the image stuff')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $finder = new Finder();
        $filesystem = new Filesystem();
        $imagesRepository = $this->em->getRepository(Image::class);

        define('PROJECT_ROOT', dirname(__DIR__) . '/../..');

        // Get the settings
        $settings = Yaml::parseFile(PROJECT_ROOT . '/settings.yml');
        // TODO: error handling

        // Browse the paths
        $paths = $settings['paths'];
        foreach ($paths as $path) {
            $files = $finder->in($path)->files();
            $io->section(sprintf('Starting to process folder: %s', $path));

            foreach ($files as $file) {
                $io->text(sprintf('Starting to process file: %s', $file));

                $absoluteFilePath = $file->getRealPath();

                $fileHash = sha1($absoluteFilePath);

                // General data
                $data = [
                    'real_path' => $absoluteFilePath,
                    'relative_pathname' => $file->getRelativePathname(),
                    'extension' => $file->getExtension(),
                ];
                $image = $imagesRepository->findOneByHash($fileHash);
                if (!$image) {
                    $image = new Image();
                    $image->setHash($fileHash);
                }

                $image->setData($data);

                $this->em->persist($image);
            }

            $this->em->flush();
            $this->em->clear();
        }

        $io->success('Success!');
    }
}
