<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\Finder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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

        // Form
        $form = $this->createForm(FilesScanType::class, [
            'updateExistingEntries' => true,
            'actions' => [
                'meta',
                'cache',
                'geocode',
                'label',
            ],
            'folders' => $folders,
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $entry = [
                'command' => 'app:files:scan',
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
                    'app:files:scan'
                )
            );

            return $this->redirect('dashboard');
        }

        // Last result
        $logDir = $this->params->get('var_dir') . '/queue/logs';
        $lastResultLinesCount = 50;
        $lastResultFile = '';
        $lastResultContent = '';
        $finder = new Finder();
        $files = $finder
            ->files()
            ->followLinks()
            ->in($logDir)
            ->sortByName()
            ->reverseSorting()
        ;
        if ($finder->hasResults()) {
            foreach ($files as $file) {
                $lastResultFile = $file->getRelativePathname();
                $contents = $file->getContents();
                $contentsArray = explode("\n", $contents);
                $contentsArray = array_reverse($contentsArray);
                $contentsArray = array_slice($contentsArray, 0, $lastResultLinesCount);
                $lastResultContent = join('<br />', $contentsArray);

                break;
            }
        }

        return $this->render('dashboard/index.html.twig', [
            'files_count' => $filesCount,
            'last_result_file' => $lastResultFile,
            'last_result_content' => $lastResultContent,
            'last_result_lines_count' => $lastResultLinesCount,
            'form' => $form->createView(),
        ]);
    }
}
