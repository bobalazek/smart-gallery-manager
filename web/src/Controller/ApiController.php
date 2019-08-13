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
use App\Manager\FileManager;
use App\Entity\File;

class ApiController extends AbstractController
{
    public function __construct(ParameterBagInterface $params, EntityManagerInterface $em, FileManager $fileManager)
    {
        $this->params = $params;
        $this->em = $em;
        $this->fileManager = $fileManager;
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
        $orderBy = $request->get('order_by', 'taken_at');
        if (!in_array($orderBy, ['taken_at', 'created_at'])) {
            return $this->json([
                'error' => [
                    'message' => 'Invalid order_by parameter.',
                ],
            ], 500);
        }
        $orderByParameter = $orderBy === 'taken_at'
            ? 'takenAt'
            : 'createdAt';

        $countPerDate = [];
        $countPerMonth = [];
        $countPerMonthMap = [];
        $countPerYear = [];
        $countPerYearMap = [];

        $files = $this->em->createQueryBuilder()
            ->select('DATE_FORMAT(f.' . $orderByParameter . ', \'%Y-%m-%d\') as filesDate, COUNT(f.id) as filesCount')
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

        $offset = $request->get('offset');
        $limit = $request->get('limit');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $orderBy = $request->get('order_by', 'taken_at');
        if (!in_array($orderBy, ['taken_at', 'created_at'])) {
            return $this->json([
                'error' => [
                    'message' => 'Invalid order_by parameter.',
                ],
            ], 500);
        }
        $orderByParameter = $orderBy === 'taken_at'
            ? 'takenAt'
            : 'createdAt';

        $files = $this->em->createQueryBuilder()
            ->select('f')
            ->from(File::class, 'f')
            ->where('f.type = :type')
            ->orderBy('f.' . $orderByParameter, 'DESC')
            ->setParameter('type', 'image')
        ;

        if ($offset) {
            $files->setFirstResult((int)$offset);
        }

        if ($limit) {
            $files->setMaxResults((int)$limit);
        }

        if ($dateFrom) {
            $files
                ->andWhere('f.' . $orderByParameter . ' >= :date_from')
                ->setParameter('date_from', new \DateTime($dateFrom . ' 00:00:00'))
            ;
        }

        if ($dateTo) {
            $files
                ->andWhere('f.' . $orderByParameter . ' <= :date_to')
                ->setParameter('date_to', new \DateTime($dateTo . ' 23:59:59'))
            ;
        }

        $files = $files->getQuery()->getResult();

        $data = [];
        foreach ($files as $file) {
            $method = 'get' . ucfirst($orderByParameter);
            $datetime = $file->$method();
            $data[] = [
                'id' => $file->getId(),
                'hash' => $file->getHash(),
                'date' => $datetime->format(DATE_ATOM),
                'images' => $this->_getFileImages($file),
            ];
        }

        return $this->json([
            'data' => $data,
            'meta' => [
                'offset' => $offset,
                'limit' => $limit,
                'date_from' => $dateTo,
                'date_to' => $dateTo,
                'order_by' => $orderBy,
            ],
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
                'images' => $this->_getFileImages($file),
            ]
        ));
    }

    /**
     * Get's the file urls
     */
    private function _getFileImages(File $file, $format = 'jpg')
    {
        $fileData = $file->getData();
        $response = [];

        $imageTypes = $this->params->get('allowed_image_conversion_types');
        foreach ($imageTypes as $imageType => $imageTypeData) {
            $src = $this->generateUrl(
                'file.view',
                [
                    'hash' => $file->getHash(),
                    'type' => $imageType,
                    'format' => $format,
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $width = 0;
            $height = 0;

            if (isset($fileData['image'])) {
                $width = $fileData['image']['width'];
                $height = $fileData['image']['height'];
                $aspectRatio = $width / $height;

                // TODO: take upsizing constraints into account

                if (isset($imageTypeData['width'])) {
                    $width = $imageTypeData['width'];
                    $height = $width / $aspectRatio;
                }

                if (isset($imageTypeData['height'])) {
                    $height = $imageTypeData['height'];
                    $width = $height * $aspectRatio;
                }
            }

            $response[$imageType] = [
                'src' => $src,
                'width' => $width,
                'height' => $height,
            ];
        }

        return $response;
    }
}
