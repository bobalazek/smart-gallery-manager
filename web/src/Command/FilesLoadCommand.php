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
use App\Entity\File;

class FilesLoadCommand extends Command
{
    protected static $defaultName = 'app:files:load';

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Loads (inserts or updates) all the local files into the database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $finder = new Finder();
        $filesystem = new Filesystem();
        $filesRepository = $this->em->getRepository(File::class);

        define('PROJECT_ROOT', dirname(__DIR__) . '/../..');

        // Get the settings
        try {
            $settings = Yaml::parseFile(PROJECT_ROOT . '/settings.yml');
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return false;
        }

        // Browse the paths
        $paths = $settings['paths'];
        foreach ($paths as $path) {
            $files = $finder->in($path)->files();
            $io->section(sprintf('Starting to process folder: %s', $path));

            $i = 0;
            foreach ($files as $file) {
                $io->text(sprintf('Starting to process file: %s', $file));

                $absoluteFilePath = $file->getRealPath();

                $fileHash = sha1($absoluteFilePath);

                // General data
                $data = [
                    'real_path' => $absoluteFilePath,
                    'relative_pathname' => $file->getRelativePathname(),
                    'extension' => strtolower($file->getExtension()),
                    'mime' => mime_content_type($absoluteFilePath),
                ];
                $file = $filesRepository->findOneByHash($fileHash);
                if (!$file) {
                    $file = new File();
                    $file->setHash($fileHash);
                }

                $file->setData($data);

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

                $i++;
                if (($i % 100) === 0) {
                    $this->em->flush();
                    $this->em->clear();
                }
            }

            $this->em->flush();
            $this->em->clear();
        }

        $io->success('Success!');

        return true;
    }
}
