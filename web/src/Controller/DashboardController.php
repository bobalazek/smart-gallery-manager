<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\Finder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\File;
use App\Form\Type\FilesScanType;
use App\Message\QueueEntry;

class DashboardController extends AbstractController
{
    public function __construct(ParameterBagInterface $params, EntityManagerInterface $em)
    {
        $this->params = $params;
        $this->em = $em;
        $this->logDir = $this->params->get('var_dir') . '/queue/logs';
    }

    /**
     * @Route("/dashboard", name="dashboard")
     */
    public function index(Request $request)
    {
        $filesCount = $this->em->createQueryBuilder()
            ->select('COUNT(f)')
            ->from(File::class, 'f')
            ->getQuery()
            ->getSingleScalarResult();

        $folders = [];
        try {
            $settings = Yaml::parseFile(
                dirname(__DIR__) . '/../../settings.yml'
            );
            if (is_array($settings['folders'])) {
                $folders = $settings['folders'];
            }
        } catch (\Exception $e) {}

        // Files scan
        $filesScanForm = $this->createForm(FilesScanType::class, [
            'updateExistingEntries' => false,
            'actions' => [
                'meta',
                'cache',
                'geocode',
                'label',
            ],
            'folders' => $folders,
        ]);
        $filesScanForm->handleRequest($request);
        if ($filesScanForm->isSubmitted() && $filesScanForm->isValid()) {
            $command = 'app:files:scan';
            $data = $filesScanForm->getData();

            $entry = [
                'command' => $command,
                'options' => [],
            ];

            if ($data['updateExistingEntries']) {
                $entry['options'][] = '--update-existing-entries';
            }

            if ($data['actions']) {
                foreach ($data['actions'] as $action) {
                    $entry['options'][] = '--action';
                    $entry['options'][] = $action;
                }
            }

            if ($data['folders']) {
                foreach ($data['folders'] as $folder) {
                    $entry['options'][] = '--folder';
                    $entry['options'][] = $folder;
                }
            }

            $this->dispatchMessage(new QueueEntry($entry));
            $this->addFlash(
                'success',
                sprintf(
                    'You have successfully triggered the "%s" command',
                    $command
                )
            );

            return $this->redirect('dashboard');
        }

        // Queue stop workers
        $queueStopWorkersForm = $this->createFormBuilder()
            ->add('Execute', SubmitType::class)
            ->getForm();
        $queueStopWorkersForm->handleRequest($request);
        if ($queueStopWorkersForm->isSubmitted() && $queueStopWorkersForm->isValid()) {
            $command = 'messenger:stop-workers';
            $entry = [
                'command' => $command,
                'options' => [],
            ];

            $this->dispatchMessage(new QueueEntry($entry));
            $this->addFlash(
                'success',
                sprintf(
                    'You have successfully triggered the "%s" command',
                    $command
                )
            );

            return $this->redirect('dashboard');
        }

        // Last result
        $logFiles = [];
        if (file_exists($this->logDir)) {
            $finder = new Finder();
            $files = $finder
                ->files()
                ->followLinks()
                ->in($this->logDir)
                ->sortByName()
                ->reverseSorting()
            ;
            if ($finder->hasResults()) {
                foreach ($files as $file) {
                    $logFile = [
                        'name' => $file->getRelativePathname(),
                    ];
                    $logFiles[] = $logFile;
                }
            }
        }

        return $this->render('dashboard/index.html.twig', [
            'files_count' => $filesCount,
            'log_files' => $logFiles,
            'files_scan_form' => $filesScanForm->createView(),
            'queue_stop_workers_form' => $queueStopWorkersForm->createView(),
        ]);
    }

    /**
     * @Route("/dashboard/log/{name}", name="dashboard.log")
     */
    public function log(Request $request, $name)
    {
        $file = $this->logDir . '/' . $name;

        if (!file_exists($file)) {
            throw $this->createNotFoundException('The product does not exist');
        }

        $contents = file_get_contents($file);

        return $this->render('dashboard/log.html.twig', [
            'name' => $name,
            'contents' => explode("\n", $contents),
        ]);
    }
}
