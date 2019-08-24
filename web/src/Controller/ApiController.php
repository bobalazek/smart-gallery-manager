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
        $countPerDay = [];
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

            $countPerDay[] = [
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

        /***** Tags *****/
        $tags = [];
        $tagsMap = [];

        // TODO: optimize
        $filesQueryBuilder = $this->em->createQueryBuilder()
            ->select('f')
            ->from(File::class, 'f');
        $this->_applyQueryFilters($filesQueryBuilder, $dateField);

        $files = $filesQueryBuilder->getQuery()->getResult();
        foreach ($files as $file) {
            $fileTags = $file->getTags();

            foreach ($fileTags as $fileTag) {
                if (!isset($tagsMap[$fileTag])) {
                    $tagsMap[$fileTag] = 0;
                }

                $tagsMap[$fileTag]++;
            }
        }

        foreach ($tagsMap as $tag => $count) {
            $tags[] = [
                'tag' => $tag,
                'count' => $count,
            ];
        }

        usort($tags, function($a, $b) {
            return $a['count'] <=> $b['count'];
        });
        $tags = array_reverse($tags);

        return $this->json([
            'data' => [
                'date' => [
                    'day' => $countPerDay,
                    'month' => $countPerMonth,
                    'year' => $countPerYear,
                ],
                'types' => $types,
                'tags' => $tags,
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
        $fileMeta = $file->getMeta();
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

            $width = $fileMeta['width'];
            $height = $fileMeta['height'];

            // If it's not the default orientation, we need to reflect that here!
            //   The meta is saved for the ORIGINAL (unprocessed & unoriented) image!
            //   The streamed one in the /file/{hash} endpoint, has already applied orientation.
            $isFinalImageOriented = !in_array($fileMeta['orientation'], [null, 1, 3]);
            if ($isFinalImageOriented) {
                $width = $fileMeta['height'];
                $height = $fileMeta['width'];
            }

            if ($width && $height) {
                $aspectRatio = $width / $height;

                if (
                    isset($imageTypeData['width']) &&
                    $width > $imageTypeData['width']
                ) {
                    $width = $imageTypeData['width'];
                    $height = $width / $aspectRatio;
                }

                if (
                    isset($imageTypeData['height']) &&
                    $height > $imageTypeData['height']
                ) {
                    $height = $imageTypeData['height'];
                    $width = $height * $aspectRatio;
                }
            } else {
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
        $day = $request->get('day');
        $createdBefore = $request->get('created_before');
        $search = $request->get('search');
        $tag = $request->get('tag');

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
        if ($day) {
            $queryBuilder
                ->andWhere('DATE(f.' . $dateField . ') = :day')
                ->setParameter('day', $day);
            ;
        }
        if ($createdBefore) {
            $queryBuilder
                ->andWhere('f.createdAt < :created_before')
                ->setParameter('created_before', new \DateTime($createdBefore));
            ;
        }
        if ($search) {
            $search = strtolower(rawurldecode($search));
            $queryBuilder
                ->andWhere($queryBuilder->expr()->orX(
                    $queryBuilder->expr()->like('f.path', ':search'),
                    $queryBuilder->expr()->like('f.type', ':search'),
                    $queryBuilder->expr()->like('f.mime', ':search'),
                    $queryBuilder->expr()->like('f.extension', ':search'),
                    $queryBuilder->expr()->like('LOWER(JSON_UNQUOTE(JSON_EXTRACT(
                        f.location,
                        :json_location_address_label
                    )))', ':search'),
                    $queryBuilder->expr()->like('LOWER(JSON_UNQUOTE(JSON_EXTRACT(
                        f.location,
                        :json_location_address_district
                    )))', ':search'),
                    $queryBuilder->expr()->eq('JSON_CONTAINS(
                        f.tags,
                        JSON_ARRAY(:search_json)
                    )', 1)
                ))
                ->setParameter('json_location_address_label', '$.address.label')
                ->setParameter('json_location_address_district', '$.address.district')
                ->setParameter('search', '%' . $search . '%')
                ->setParameter('search_json', ucwords($search))
            ;
        }
        if ($tag) {
            $search = strtolower(rawurldecode($search));
            $queryBuilder
                ->andWhere(
                    $queryBuilder->expr()->eq('JSON_CONTAINS(
                        f.tags,
                        JSON_ARRAY(:tag)
                    )', 1)
                )
                ->setParameter('tag', ucwords($tag))
            ;
        }

        return $queryBuilder;
    }
}
