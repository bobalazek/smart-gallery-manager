<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Mime\MimeTypes;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Image\ImageManager;
use App\Entity\File;

class ApiController extends AbstractController
{
    public function __construct(ParameterBagInterface $params, EntityManagerInterface $em)
    {
        $this->params = $params;
        $this->em = $em;
    }

    /**
     * @Route("/api", name="api")
     */
    public function index()
    {
        return $this->json('Hello World.');
    }

    /**
     * @Route("/api/files/summary", name="api.files.summary")
     */
    public function filesSummary(Request $request)
    {
        $countPerDate = [];
        $countPerMonth = [];
        $countPerMonthMap = [];
        $countPerYear = [];
        $countPerYearMap = [];

        $files = $this->em->createQueryBuilder()
            ->select('DATE_FORMAT(f.takenAt, \'%Y-%m-%d\') as filesDate, COUNT(f.id) as filesCount')
            ->from(File::class, 'f')
            ->where('f.type = :type')
            ->groupBy('filesDate')
            ->orderBy('filesDate', 'DESC')
            ->setParameter('type', 'image')
            ->getQuery()
            ->getResult();
        foreach ($files as $file) {
            $count = (int)$file['filesCount'];
            $date = $file['filesDate'];

            $datetime = new \DateTime($date);
            $month = $datetime->format('Y-m');
            $year = $datetime->format('Y');

            $countPerDate[] = [
                'date' => $date,
                'count' => $count,
            ];

            if (!isset($countPerMonthMap[$month])) {
                $countPerMonthMap[$month] = count($countPerMonth);
                $countPerMonth[$countPerMonthMap[$month]] = [
                    'date' => $month,
                    'count' => 0,
                ];
            }
            $countPerMonth[$countPerMonthMap[$month]]['count'] += $count;

            if (!isset($countPerYearMap[$year])) {
                $countPerYearMap[$year] = count($countPerYear);
                $countPerYear[$countPerYearMap[$year]] = [
                    'date' => $year,
                    'count' => 0,
                ];
            }
            $countPerYear[$countPerYearMap[$year]]['count'] += $count;
        }

        return $this->json([
            'data' => [
                'count_per_date' => $countPerDate,
                'count_per_month' => $countPerMonth,
                'count_per_year' => $countPerYear,
            ],
            'meta' => [],
        ]);
    }

    /**
     * @Route("/api/files", name="api.files")
     */
    public function files(Request $request)
    {
        $mimeTypes = new MimeTypes();

        $format = $request->get('format', 'jpg');
        $offset = $request->get('offset');
        $limit = $request->get('limit');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        $allowedFormats = $this->params->get('allowed_formats');
        if (!in_array($format, $allowedFormats)) {
            throw new \Exception('Invalid format. Allowed: ' . implode(', ', $allowedFormats));
        }

        $files = $this->em->createQueryBuilder()
            ->select('f')
            ->from(File::class, 'f')
            ->where('f.type = :type')
            ->orderBy('f.takenAt', 'DESC')
            ->setParameter('type', 'image')
        ;

        if ($offset) {
            $files->setFirstResult((int)$offset);
        }

        if ($limit) {
            $files->setMaxResults((int)$limit);
        }

        if ($fromDate) {
            $files
                ->andWhere('f.takenAt >= :from_date')
                ->setParameter('from_date', new \DateTime($fromDate . ' 00:00:00'))
            ;
        }

        if ($toDate) {
            $files
                ->andWhere('f.takenAt <= :to_date')
                ->setParameter('to_date', new \DateTime($toDate . ' 23:59:59'))
            ;
        }

        $files = $files->getQuery()->getResult();

        $data = [];
        foreach ($files as $file) {
            $data[] = [
                'id' => $file->getId(),
                'hash' => $file->getHash(),
                'date' => $file->getTakenAt()->format(DATE_ATOM),
                'urls' => $this->_getFileUrls($file),
            ];
        }

        return $this->json([
            'data' => $data,
            'meta' => [],
        ]);
    }

    /**
     * @Route("/api/file/{hash}", name="api.file.detail")
     */
    public function fileDetail($hash, Request $request)
    {
        $file = $this->em->getRepository(File::class)->findOneByHash($hash);
        if (!$file) {
            return $this->json([
                'error' => [
                    'message' => 'File does not exist',
                ],
            ], 404);
        }

        return $this->json(array_merge(
            $file->toArray(),
            [
                'urls' => $this->_getFileUrls($file),
            ]
        ));
    }

    /**
     * Get's the file urls
     */
    private function _getFileUrls(File $file)
    {
        return [
            'thumbnail' => $this->generateUrl(
                'file.view',
                [
                    'hash' => $file->getHash(),
                    'type' => 'thumbnail',
                    'format' => 'jpg',
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'small' => $this->generateUrl(
                'file.view',
                [
                    'hash' => $file->getHash(),
                    'type' => 'small',
                    'format' => 'jpg',
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'original' => $this->generateUrl(
                'file.view',
                [
                    'hash' => $file->getHash(),
                    'type' => 'original',
                    'format' => 'jpg',
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
        ];
    }
}
