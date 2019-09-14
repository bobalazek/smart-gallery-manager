<?php

namespace App\MessageHandler;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Psr\Log\LoggerInterface;
use App\Message\QueueEntry;

class QueueEntryHandler implements MessageHandlerInterface
{
    public function __construct(
        ParameterBagInterface $params,
        KernelInterface $kernel,
        LoggerInterface $logger
    )
    {
        $this->params = $params;
        $this->kernel = $kernel;
        $this->logger = $logger;

        $this->filesystem = new Filesystem();
    }

    public function __invoke(QueueEntry $queueEntry)
    {
        $phpBin = (new PhpExecutableFinder())->find();
        $projectDir = $this->kernel->getProjectDir();

        $queueEntryInput = $queueEntry->getInput();
        $command = $queueEntryInput['command'];
        $options = $queueEntryInput['options'];

        $commandArray = [
            $phpBin,
            $projectDir . '/bin/console',
            $command,
            '-vvv',
        ];

        if ($options) {
            $commandArray = array_merge($commandArray, $options);
        }

        // Logging
        $logDir = $this->params->get('var_dir') . '/queue/logs';
        if (!$this->filesystem->exists($logDir)) {
            $this->filesystem->mkdir($logDir);
        }

        $logFile = str_replace(
            ':',
            '_',
            $logDir . '/' . date('Ymd-His') . '-' . $command . '.log'
        );
        if (!$this->filesystem->exists($logFile)) {
            $this->filesystem->touch($logFile);
        }

        // TODO: cleanup, if we have too many logs in there?

        // Process
        $process = new Process($commandArray);

        $process->setTimeout(0);
        $process->setIdleTimeout(0);
        $process->start();

        $filesystem = $this->filesystem;
        $process->wait(function ($type, $buffer) use ($filesystem, $logFile) {
            $filesystem->appendToFile($logFile, $buffer);
        });
    }
}