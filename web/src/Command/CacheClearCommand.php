<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;

class CacheClearCommand extends Command
{
    protected static $defaultName = 'app:cache:clear';

    public function __construct(
        ParameterBagInterface $params,
        LoggerInterface $logger
    )
    {
        $this->params = $params;
        $this->logger = $logger;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Clears the cache.')
            ->addOption(
                'include-converted-images',
                'c',
                InputOption::VALUE_OPTIONAL,
                'It will also remove the images, converted by the python service.',
                false
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filesystem = new Filesystem();
        $finder = new Finder();

        $cacheDir = $this->params->get('var_dir') . '/cache';
        $filesDataDir = $this->params->get('var_dir') . '/data/files';

        $includeConvertedImages = $input->getOption('include-converted-images') !== false;

        $this->logger->notice('Starting to remove the cache directory ...');

        $filesystem->remove($cacheDir);

        $this->logger->notice('Cache directory removed');

        if ($includeConvertedImages) {
            $this->logger->notice('Finding converted images ...');

            $files = $finder
                ->files()
                ->name('converted_from_*.jpg')
                ->ignoreUnreadableDirs()
                ->followLinks()
                ->in($filesDataDir)
            ;

            $filesCount = iterator_count($files);

            $this->logger->notice(sprintf(
                'Converted image files found: %s',
                $filesCount
            ));

            $this->logger->notice('Starting to remove the converted images ...');

            foreach ($files as $file) {
                $filePath = $file->getRealPath();

                $this->logger->debug(sprintf(
                    'Removing %s ...',
                    $filePath
                ));

                $filesystem->remove($filePath);
            }
        }

        $this->logger->notice('Success!');

        return true;
    }
}
