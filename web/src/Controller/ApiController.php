<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Mime\MimeTypes;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Intervention\Image\ImageManager;
use App\Manager\FileManager;
use App\Entity\File;

class ApiController extends AbstractController
{
    public function __construct(
        RequestStack $requestStack,
        ParameterBagInterface $params,
        EntityManagerInterface $em,
        FileManager $fileManager
    )
    {
        $this->requestStack = $requestStack;
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
        $dateField = $orderBy === 'taken_at'
            ? 'takenAt'
            : 'createdAt';

        /***** Count *****/
        $countPerDate = [];
        $countPerMonth = [];
        $countPerMonthMap = [];
        $countPerYear = [];
        $countPerYearMap = [];

        $filesCountQueryBuilder = $this->em->createQueryBuilder()
            ->select('DATE_FORMAT(f.' . $dateField . ', \'%Y-%m-%d\') as filesDate, COUNT(f.id) as filesCount')
            ->from(File::class, 'f')
            ->groupBy('filesDate')
            ->orderBy('filesDate', 'DESC');
        $this->_applyQueryFilters($filesCountQueryBuilder, $dateField);

        $filesCount = $filesCountQueryBuilder->getQuery()->getResult();
        foreach ($filesCount as $filesCount) {
            $count = (int)$filesCount['filesCount'];
            $date = $filesCount['filesDate'];

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

        /***** Types *****/
        $types = [];

        $fileTypeCountQueryBuilder = $this->em->createQueryBuilder()
            ->select('f.type as fileType, COUNT(f.id) as fileTypeCount')
            ->from(File::class, 'f')
            ->groupBy('f.type');
        $this->_applyQueryFilters($fileTypeCountQueryBuilder, $dateField);

        $fileTypeCount = $fileTypeCountQueryBuilder->getQuery()->getResult();
        foreach ($fileTypeCount as $fileTypeCountSingle) {
            $types[] = [
                'type' => $fileTypeCountSingle['fileType'],
                'count' => $fileTypeCountSingle['fileTypeCount'],
            ];
        }

        return $this->json([
            'data' => [
                'count' => [
                    'date' => $countPerDate,
                    'month' => $countPerMonth,
                    'year' => $countPerYear,
                ],
                'types' => $types,
            ],
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
        $dateField = $orderBy === 'taken_at'
            ? 'takenAt'
            : 'createdAt';

        $filesQueryBuilder = $this->em->createQueryBuilder()
            ->select('f')
            ->from(File::class, 'f')
            ->orderBy('f.' . $dateField, 'DESC')
        ;

        if ($offset) {
            $filesQueryBuilder->setFirstResult((int)$offset);
        }

        if ($limit) {
            $filesQueryBuilder->setMaxResults((int)$limit);
        }

        if ($dateFrom) {
            $filesQueryBuilder
                ->andWhere('f.' . $dateField . ' >= :date_from')
                ->setParameter('date_from', new \DateTime($dateFrom . ' 00:00:00'))
            ;
        }

        if ($dateTo) {
            $filesQueryBuilder
                ->andWhere('f.' . $dateField . ' <= :date_to')
                ->setParameter('date_to', new \DateTime($dateTo . ' 23:59:59'))
            ;
        }

        $this->_applyQueryFilters($filesQueryBuilder, $dateField);

        $files = $filesQueryBuilder->getQuery()->getResult();

        $data = [];
        foreach ($files as $file) {
            $method = 'get' . ucfirst($dateField);
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
     *
     * @param File $file
     * @param string $format
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

            if ($width === 0 || $height === 0) {
                // It seems to be an invalid image. Simply return a "404 not found" image
                $width = 800;
                $height = 600;
                $src = $src = $this->generateUrl(
                    'index',
                    [],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ) . 'img/404.jpg';
            }

            $response[$imageType] = [
                'src' => $src,
                'width' => $width,
                'height' => $height,
            ];
        }

        return $response;
    }

    /**
     * Applies all the query filters
     *
     * @param QueryBuilder $query
     * @param string $dateField
     */
    private function _applyQueryFilters(QueryBuilder $queryBuilder, $dateField)
    {
        $request = $this->requestStack->getCurrentRequest();

        $type = $request->get('type');
        $year = $request->get('year');
        $month = $request->get('month');
        $date = $request->get('date');
        $createdBefore = $request->get('created_before');
        $search = $request->get('search');

        if ($type) {
            $queryBuilder
                ->andWhere('f.type = :type')
                ->setParameter('type', $type);
            ;
        }
        if ($year) {
            $queryBuilder
                ->andWhere('YEAR(f.' . $dateField . ') = :year')
                ->setParameter('year', $year);
            ;
        }
        if ($month) {
            if (strpos($month, '-') !== false) {
                $monthExploded = explode('-', $month);
                $month = $monthExploded[1];
            }

            $queryBuilder
                ->andWhere('MONTH(f.' . $dateField . ') = :month')
                ->setParameter('month', $month);
            ;
        }
        if ($date) {
            $queryBuilder
                ->andWhere('DATE(f.' . $dateField . ') = :date')
                ->setParameter('date', $date);
            ;
        }
        if ($createdBefore) {
            $queryBuilder
                ->andWhere('f.createdAt < :created_before')
                ->setParameter('created_before', new \DateTime($createdBefore));
            ;
        }
        if ($search) {
            $search = rawurldecode($search);
            $queryBuilder
                ->andWhere('
                    f.path LIKE :search OR
                    f.type LIKE :search OR
                    f.mime LIKE :search OR
                    f.extension LIKE :search
                ')
                ->setParameter('search', '%' . $search . '%');
            ;
        }

        return $queryBuilder;
    }
}
