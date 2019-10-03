<?php

namespace App\Manager;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Cache\ItemInterface;
use Intervention\Image\ImageManager;
use Intervention\Image\Image;
use Psr\Log\LoggerInterface;
use Aws\Rekognition\RekognitionClient;
use App\Entity\File;

class FileManager {
    use FileManagerTraits\FileMetaTrait,
        FileManagerTraits\GeocodeTrait,
        FileManagerTraits\LabelTrait,
        FileManagerTraits\FacesTrait;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
        $this->filesystem = new Filesystem();
        $this->mimeTypes = new MimeTypes();
        $this->imageManager = new ImageManager([
            'driver' => 'imagick',
        ]);
        $this->httpClient = new CurlHttpClient();

        $this->allowedImageConversionTypes = $this->params->get('allowed_image_conversion_types');
        $filesCacheAdapter = $this->params->get('files_cache_adapter');

        // Cache
        if ($filesCacheAdapter === 'filesystem') {
            $this->cache = new FilesystemAdapter(
                'files',
                0,
                $this->params->get('var_dir') . '/cache'
            );
        } elseif ($filesCacheAdapter === 'memcached') {
            $this->cache = new MemcachedAdapter(
                MemcachedAdapter::createConnection(
                    'memcached://memcached:11211'
                ),
                'files',
                0
            );
        } elseif ($filesCacheAdapter === 'redis') {
            $this->cache = new RedisAdapter(
                RedisAdapter::createConnection(
                    'redis://redis'
                ),
                'files',
                0
            );
        } else {
            throw new \Exception(sprintf(
                'The file cache adapter "%s" does not exist.',
                $filesCacheAdapter
            ));
        }

        // Label
        $this->labellingEnabled = $this->params->get('labelling_enabled');
        $this->labellingService = $this->params->get('labelling_service');
        $this->labellingConfidence = $this->params->get('labelling_confidence');
        $this->awsCredentials = [
            'key' => $this->params->get('aws_key'),
            'secret' => $this->params->get('aws_secret'),
        ];
        $this->awsRekognitionClient = new RekognitionClient([
            'credentials' => $this->awsCredentials,
            'region' => $this->params->get('amazon_rekognition_region'),
            'version' => $this->params->get('amazon_rekognition_version'),
        ]);
        $this->awsRekognitionMinConfidence = $this->params->get('amazon_rekognition_min_confidence');

        // Geocode
        $this->geocodingEnabled = $this->params->get('geocoding_enabled');
        $this->geocodingService = $this->params->get('geocoding_service');
        $this->hereApiCredentials = [
            'app_id' => $this->params->get('here_app_id'),
            'app_code' => $this->params->get('here_app_code'),
        ];
        $this->hereReverseGeocoderRadius = $this->params->get('here_reverse_geocoder_radius');
        $this->hereReverseGeocoderMaxResults = $this->params->get('here_reverse_geocoder_max_results');

        // Faces
        $this->facesEnabled = $this->params->get('faces_enabled');
        $this->facesService = $this->params->get('faces_service');
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Gets the image cache for the the specified file
     *
     * @param File $file
     * @param string $type
     * @param string $format
     */
    public function getImageData(File $file, $type = 'thumbnail', $format = 'jpg')
    {
        $cacheKey = $file->getHash() . '.' . $type . '.' . $format;

        return $this->cache->get(
            $cacheKey,
            function (ItemInterface $item) use ($cacheKey, $file, $type, $format) {
                $image = $this->_processImage($file, $type, $format);

                $formatMimeTypes = $this->mimeTypes->getMimeTypes($format);
                $mime = $formatMimeTypes[0];
                $content = $image->encode($format);

                return [
                    'key' => $cacheKey,
                    'format' => $format,
                    'mime' => $mime,
                    'content' => $content,
                ];
            }
        );
    }

    /**
     * Gets the image instance for data or further manipulation.
     *
     * @param File $file
     *
     * @return Image
     */
    public function getImage(File $file): Image
    {
        $image = $this->imageManager->make(
            $this->getImagePath($file)
        );
        $image->orientate();

        return $image;
    }

    /**
     * Gets the image path
     *
     * @param File $file
     *
     * @return string
     */
    public function getImagePath($file): string
    {
        $path = $file->getPath();

        if ($file->getExtension() === 'dng') {
            $path = $this->getFileDataDir($file) . '/converted_from_dng.jpg';
            if (!$this->filesystem->exists($path)) {
                $url = 'http://python:8000/file-view';
                $response = $this->httpClient->request('GET', $url, [
                    'query' => [
                        'file' => $file->getPath(),
                    ],
                ]);
                file_put_contents($path, $response->getContent());
            }
        }

        return $path;
    }

    /**
     * Gets the path where files data is cached
     *
     * @return string
     */
    public function getFilesDataDir(): string
    {
        return $this->params->get('var_dir'). '/data/files';
    }

    /**
     * Gets the path for the file dir
     *
     * @param File|string $fileOrFileHash
     *
     * @return string
     */
    public function getFileDataDir($fileOrFileHash): string
    {
        $hash = is_string($fileOrFileHash)
            ? $fileOrFileHash
            : $fileOrFileHash->getHash();

        $fileDataDir = $this->getFilesDataDir() . '/' . $hash;
        if (!$this->filesystem->exists($fileDataDir)) {
            $this->filesystem->mkdir($fileDataDir);
        }

        return $fileDataDir;
    }

    /**
     * Generates the file path hash
     *
     * @param string $filePath
     *
     * @return string
     */
    public function generateFilePathHash($filePath): string
    {
        return sha1($filePath);
    }

    /**
     * Generates the cache for the the specified file
     *
     * @param File $file
     * @param array $types
     * @param string $format
     *
     * @throws \Exception if conversion type not found
     */
    public function cache(File $file, $types = ['thumbnail', 'preview'], $format = 'jpg')
    {
        foreach ($types as $type) {
            if (!isset($this->allowedImageConversionTypes[$type])) {
                throw new \Exception('The type "' . $type . '" does not exist.');

                return false;
            }

            $cacheKey = $file->getHash() . '.' . $type . '.' . $format;

            $this->cache->delete($cacheKey);
            $this->getImageData($file, $type, $format);
        }

        return true;
    }

    /**
     * Prepares a general.json file in the file data root, with some basic information.
     *
     * @param File $file
     * @param bool $skipFetchIfAlreadyExists
     */
    public function prepare(File $file, $skipFetchIfAlreadyExists = true)
    {
        $path = $this->getFileDataDir($file) . '/general.json';

        $alreadyExists = $skipFetchIfAlreadyExists && file_exists($path);

        if (!$alreadyExists) {
            file_put_contents($path, json_encode([
                'path' => $file->getPath(),
            ]));
        }

        return true;
    }

    /**
     * Processes the image
     *
     * @param File $file
     * @param string $type
     * @param string $format
     *
     * @return Image
     */
    private function _processImage(File $file, $type = 'thumbnail', $format = 'jpg'): Image
    {
        $imageTypes = $this->params->get('allowed_image_conversion_types');

        if (!array_key_exists($type, $imageTypes)) {
            throw new \Exception(sprintf('The type "%s" does not exist.', $type));
        }

        $image = $this->getImage($file);

        if (isset($imageTypes[$type]['width'])) {
            $image->widen($imageTypes[$type]['width'], function ($constraint) {
                $constraint->upsize();
            });
        }

        if (isset($imageTypes[$type]['height'])) {
            $image->heighten($imageTypes[$type]['height'], function ($constraint) {
                $constraint->upsize();
            });
        }

        return $image;
    }

    /**
     * @param mixed $data
     * @param string $type
     *
     * @return string
     */
    private function _eval($data, $type = ''): ?string
    {
        $return = $data;

        if (in_array($type, ['latitude', 'longitude'])) {
            if (is_string($data)) {
                $data = explode(', ', $data);
            }

            $degrees = $this->_eval($data[0]);
            $minutes = $this->_eval($data[1]);
            $seconds = $this->_eval($data[2]);

            $return = $degrees + ($minutes / 60) + ($seconds / 3600);
        } elseif (
            is_string($return) &&
            strpos($return, '/') !== false
        ) {
            $explode = explode('/', $return);
            $n1 = (float) $explode[0];
            $n2 = (float) $explode[1];

            if ($type === 'shutter_speed') {
                if (
                    $n1 !== (float)0 &&
                    $n2 !== (float)0 &&
                    $n1 !== (float)1
                ) {
                    $return = ($n1 / $n1) . '/' . (int)($n2 / $n1);
                }
            } else {
                $return = $n1 / $n2;
            }
        } elseif ($type === 'datetime') {
            if (preg_match(
                '|^([0-9]{4}):([0-9]{2}):([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2})$|',
                $return,
                $matches
            )) {
                $return = date(
                    'Y-m-d H:i:s',
                    strtotime(
                        $matches[1] . '-' . $matches[2] . '-' . $matches[3] . ' ' .
                        $matches[4] . ':' . $matches[5] . ':' . $matches[6]
                    )
                );
            } else {
                $return = null;
            }
        }

        if (is_array($return)) {
            return json_encode($return);
        }

        return $return;
    }
}
