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
use Doctrine\ORM\Query;
use Intervention\Image\ImageManager;
use App\Manager\FileManager;
use App\Entity\File;
use App\Entity\ImageLabel;
use App\Entity\ImageLocation;

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
     * This whole function needs some major optimizations!
     *
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

        $orderByDirection = $request->get('order_by_direction', 'DESC');
        if (!in_array($orderByDirection, ['ASC', 'DESC'])) {
            return $this->json([
                'error' => [
                    'message' => 'Invalid order_by_direction parameter.',
                ],
            ], 500);
        }

        $dateField = $orderBy === 'taken_at'
            ? 'takenAt'
            : 'createdAt';

        /***** Date *****/
        $datePerDate = [];
        $datePerYearMonth = [];
        $datePerYearMonthMap = [];
        $datePerYear = [];
        $datePerYearMap = [];

        $filesCountQueryBuilder = $this->em->createQueryBuilder()
            ->select('DATE_FORMAT(f.' . $dateField . ', \'%Y-%m-%d\') AS filesDate, COUNT(f.id) AS filesCount')
            ->from(File::class, 'f')
            ->leftJoin('f.imageLabels', 'il')
            ->leftJoin('f.imageLocation', 'ilo')
            ->groupBy('filesDate')
            ->orderBy('filesDate', 'DESC');
        $this->_applyQueryFilters($filesCountQueryBuilder, $dateField);

        $filesCount = $filesCountQueryBuilder->getQuery()->getResult();
        foreach ($filesCount as $filesCount) {
            $count = (int)$filesCount['filesCount'];
            $date = $filesCount['filesDate'];

            $datetime = new \DateTime($date);
            $yearMonth = $datetime->format('Y-m');
            $year = $datetime->format('Y');

            $datePerDate[] = [
                'date' => $date,
                'count' => $count,
            ];

            if (!isset($datePerYearMonthMap[$yearMonth])) {
                $datePerYearMonthMap[$yearMonth] = count($datePerYearMonth);
                $datePerYearMonth[$datePerYearMonthMap[$yearMonth]] = [
                    'date' => $yearMonth,
                    'count' => 0,
                ];
            }
            $datePerYearMonth[$datePerYearMonthMap[$yearMonth]]['count'] += $count;

            if (!isset($datePerYearMap[$year])) {
                $datePerYearMap[$year] = count($datePerYear);
                $datePerYear[$datePerYearMap[$year]] = [
                    'date' => $year,
                    'count' => 0,
                ];
            }
            $datePerYear[$datePerYearMap[$year]]['count'] += $count;
        }

        /***** Types *****/
        $types = [];

        $fileTypeCountQueryBuilder = $this->em->createQueryBuilder()
            ->select('f.type AS fileType, COUNT(f.id) AS fileTypeCount')
            ->from(File::class, 'f')
            ->leftJoin('f.imageLabels', 'il')
            ->leftJoin('f.imageLocation', 'ilo')
            ->groupBy('fileType');
        $this->_applyQueryFilters($fileTypeCountQueryBuilder, $dateField);

        $fileTypeCount = $fileTypeCountQueryBuilder->getQuery()->getResult();
        foreach ($fileTypeCount as $fileTypeCountSingle) {
            $types[] = [
                'type' => $fileTypeCountSingle['fileType'],
                'count' => $fileTypeCountSingle['fileTypeCount'],
            ];
        }

        /***** Tags & locations *****/
        $tags = [];
        $locationCityCountryMap = [];
        $locationPerCity = [];
        $locationPerCityMap = [];
        $locationPerCountry = [];
        $locationPerCountryMap = [];

        $tagsQueryBuilder = $this->em->createQueryBuilder()
            ->select('il.name AS tag, COUNT(il.id) AS count')
            ->from(ImageLabel::class, 'il')
            ->leftJoin('il.file', 'f')
            ->leftJoin('f.imageLocation', 'ilo')
            ->groupBy('tag');
        $this->_applyQueryFilters($tagsQueryBuilder, $dateField);
        $tags = $tagsQueryBuilder->getQuery()->getResult(Query::HYDRATE_ARRAY);

        usort($tags, function($a, $b) {
            return $a['count'] <=> $b['count'];
        });
        $tags = array_reverse($tags);

        $imageLocationsQueryBuilder = $this->em->createQueryBuilder()
            ->select('
                ilo.label AS label,
                COUNT(ilo.id) AS count,
                GROUP_CONCAT(DISTINCT ilo.town) AS town,
                GROUP_CONCAT(DISTINCT ilo.country) AS country
            ')
            ->from(ImageLocation::class, 'ilo')
            ->leftJoin('ilo.file', 'f')
            ->leftJoin('f.imageLabels', 'il')
            ->groupBy('label');
        $this->_applyQueryFilters($imageLocationsQueryBuilder, $dateField);
        $imageLocations = $imageLocationsQueryBuilder->getQuery()->getResult(Query::HYDRATE_ARRAY);
        foreach ($imageLocations as $location) {
            // Country
            $country = $location['country'] ?? '';
            if (!isset($locationPerCountryMap[$country])) {
                $locationPerCountryMap[$country] = 0;
            }
            $locationPerCountryMap[$country]++;

            // City
            $city = $location['town'] ?? '';
            if (!isset($locationPerCityMap[$city])) {
                $locationPerCityMap[$city] = 0;
            }
            $locationPerCityMap[$city]++;
            $locationCityCountryMap[$city] = $city === ''
                ? ''
                : $country;
        }

        /***** Locations - continued *****/
        // City
        foreach ($locationPerCityMap as $location => $count) {
            $locationPerCity[] = [
                'location' => $location,
                'count' => $count,
                'parent' => $locationCityCountryMap[$location],
            ];
        }

        usort($locationPerCity, function($a, $b) {
            return $a['count'] <=> $b['count'];
        });
        $locationPerCity = array_reverse($locationPerCity);

        // Country
        foreach ($locationPerCountryMap as $location => $count) {
            $locationPerCountry[] = [
                'location' => $location,
                'count' => $count,
            ];
        }

        usort($locationPerCountry, function($a, $b) {
            return $a['count'] <=> $b['count'];
        });
        $locationPerCountry = array_reverse($locationPerCountry);

        return $this->json([
            'data' => [
                'date' => [
                    'date' => $datePerDate,
                    'year_month' => $datePerYearMonth,
                    'year' => $datePerYear,
                ],
                'location' => [
                    'city' => $locationPerCity,
                    'country' => $locationPerCountry,
                ],
                'types' => $types,
                'tags' => $tags,
            ],
            'meta' => [
                'order_by' => $orderBy,
                'order_by_direction' => $orderByDirection,
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

        $orderByDirection = $request->get('order_by_direction', 'DESC');
        if (!in_array($orderByDirection, ['ASC', 'DESC'])) {
            return $this->json([
                'error' => [
                    'message' => 'Invalid order_by_direction parameter.',
                ],
            ], 500);
        }

        $dateField = $orderBy === 'taken_at'
            ? 'takenAt'
            : 'createdAt';

        $filesQueryBuilder = $this->em->createQueryBuilder()
            ->select('f')
            ->from(File::class, 'f')
            ->leftJoin('f.imageLabels', 'il')
            ->leftJoin('f.imageLocation', 'ilo')
            ->orderBy('f.' . $dateField, $orderByDirection)
        ;

        if ($offset !== null) {
            $filesQueryBuilder->setFirstResult((int)$offset);
        }

        if ($limit !== null) {
            $filesQueryBuilder->setMaxResults((int)$limit);
        }

        if ($dateFrom !== null) {
            $filesQueryBuilder
                ->andWhere('f.' . $dateField . ' >= :date_from')
                ->setParameter('date_from', new \DateTime($dateFrom . ' 00:00:00'))
            ;
        }

        if ($dateTo !== null) {
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
                'offset' => (int)$offset,
                'limit' => (int)$limit,
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

        // If it's not the default orientation, we need to reflect that here!
        //   The meta is saved for the ORIGINAL (unprocessed & unoriented) image!
        //   The streamed one in the /file/{hash} endpoint, has already applied orientation.
        // .dng images are already oriented, because when we set the meta,
        //   we get the .jpg version from the .dng file, because in .dng,
        //   the sizes are wrong. Means, with .dgn files we already have applied the orientation
        // See the _processFileMetaViaPython() method in FileManager.php
        $swapWidthHeight = $file->getExtension() !== 'dng' &&
            !in_array($fileMeta['orientation'], [null, 1, 2, 3, 4]);

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

            $width = $swapWidthHeight
                ? $fileMeta['height']
                : $fileMeta['width'];
            $height = $swapWidthHeight
                ? $fileMeta['width']
                : $fileMeta['height'];

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

                $aspectRatio = $width / $height;
            }

            $response[$imageType] = [
                'src' => $src,
                'width' => $width,
                'height' => $height,
                'aspect_ratio' => $aspectRatio,
            ];
        }

        return $response;
    }

    /**
     * Applies all the query filters
     *
     * @param QueryBuilder $query
     * @param string $dateField
     * @param array $ignoredFields
     */
    private function _applyQueryFilters(QueryBuilder $queryBuilder, $dateField, $ignoredFields = [])
    {
        $request = $this->requestStack->getCurrentRequest();

        $type = $request->get('type');
        if ($type !== null) {
            $queryBuilder
                ->andWhere('f.type = :type')
                ->setParameter('type', $type);
            ;
        }

        $year = $request->get('year');
        if ($year !== null) {
            $queryBuilder
                ->andWhere('YEAR(f.' . $dateField . ') = :year')
                ->setParameter('year', $year);
            ;
        }

        $month = $request->get('month');
        if ($month !== null) {
            $queryBuilder
                ->andWhere('MONTH(f.' . $dateField . ') = :month')
                ->setParameter('month', $month);
            ;
        }

        $day = $request->get('day');
        if ($day !== null) {
            $queryBuilder
                ->andWhere('DAY(f.' . $dateField . ') = :day')
                ->setParameter('day', $day);
            ;
        }

        $date = $request->get('date');
        if ($date !== null) {
            $queryBuilder
                ->andWhere('DATE(f.' . $dateField . ') = :date')
                ->setParameter('date', $date);
            ;
        }

        $country = $request->get('country');
        if ($country !== null) {
            $country = strtolower(rawurldecode($country));
            $queryBuilder
                ->andWhere('ilo.country = LOWER(:country)')
                ->setParameter('country', $country)
            ;
        }

        $city = $request->get('city');
        if ($city !== null) {
            $city = strtolower(rawurldecode($city));
            $queryBuilder
                ->andWhere('ilo.town = LOWER(:city)')
                ->setParameter('city', $city)
            ;
        }

        $createdBefore = $request->get('created_before');
        if ($createdBefore !== null) {
            $queryBuilder
                ->andWhere('f.createdAt < :created_before')
                ->setParameter('created_before', new \DateTime($createdBefore));
            ;
        }

        $search = $request->get('search');
        if ($search) {
            $search = strtolower(rawurldecode($search));
            $queryBuilder
                ->andWhere($queryBuilder->expr()->orX(
                    $queryBuilder->expr()->like('f.path', ':search'),
                    $queryBuilder->expr()->like('f.type', ':search'),
                    $queryBuilder->expr()->like('f.mime', ':search'),
                    $queryBuilder->expr()->like('f.extension', ':search')
                ))
                ->setParameter('search', '%' . $search . '%')
            ;
        }

        $tag = $request->get('tag');
        if ($tag !== null) {
            $tag = strtolower(rawurldecode($tag));
            $queryBuilder
                ->andWhere('il.name = LOWER(:tag)')
                ->setParameter('tag', $tag)
            ;
        }

        return $queryBuilder;
    }
}
