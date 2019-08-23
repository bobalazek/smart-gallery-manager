<?php

namespace App\Manager;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Cache\ItemInterface;
use Intervention\Image\ImageManager;
use Intervention\Image\Image;
use Aws\Rekognition\RekognitionClient;
use App\Entity\File;

class FileManager {
    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
        $this->cache = new FilesystemAdapter(
            'files',
            0,
            $this->params->get('var_dir') . '/cache'
        );
        $this->filesystem = new Filesystem();
        $this->mimeTypes = new MimeTypes();
        $this->imageManager = new ImageManager([
            'driver' => 'imagick',
        ]);
        $this->httpClient = new CurlHttpClient();

        $this->allowedImageConversionTypes = $this->params->get('allowed_image_conversion_types');

        $this->awsRekognitionClient = new RekognitionClient([
            'credentials' => $this->params->get('aws_credentials'),
            'region' => $this->params->get('aws_rekognition_region'),
            'version' => $this->params->get('aws_rekognition_version'),
        ]);
        $this->awsRekognitionMinConfidence = $this->params->get('aws_rekognition_min_confidence');
        $this->labelingConfidence = $this->params->get('labeling_confidence');

        $this->hereApiCredentials = $this->params->get('here_api_credentials');
        $this->hereReverseGeocoderRadius = $this->params->get('here_reverse_geocoder_radius');
        $this->hereReverseGeocoderMaxResults = $this->params->get('here_reverse_geocoder_max_results');
    }

    private $_fileMeta = [];

    /**
     * Gets the file meta data
     *
     * @param File $file
     */
    public function getFileMeta(File $file): ?array
    {
        $this->_fileMeta['date'] = null;
        $this->_fileMeta['size'] = null;
        $this->_fileMeta['width'] = null;
        $this->_fileMeta['height'] = null;
        $this->_fileMeta['orientation'] = null;
        $this->_fileMeta['device'] = [
            'make' => null,
            'model' => null,
            'shutter_speed' => null,
            'aperture' => null,
            'iso' => null,
            'focal_length' => null,
            'lens_make' => null,
            'lens_model' => null,
        ];
        $this->_fileMeta['geolocation'] = [
            'altitude' => null,
            'latitude' => null,
            'longitude' => null,
        ];

        try {
            $this->_processFileMetaViaGd($file);
        } catch (\Exception $e) {
            try {
                if ($file->getExtension() === 'dng') {
                    $this->_processFileMetaViaPython($file);
                } else {
                    $this->_processFileMetaViaImagick($file);
                }
            } catch (\Exception $ee) {}
        }

        return [
            'name' => basename($file->getPath()),
            'date' => $this->_fileMeta['date'],
            'size' => $this->_fileMeta['size'],
            'width' => $this->_fileMeta['width'],
            'height' => $this->_fileMeta['height'],
            'pixels' => is_numeric($this->_fileMeta['width'])
                && is_numeric($this->_fileMeta['height'])
                ? $this->_fileMeta['width'] * $this->_fileMeta['height']
                : null,
            'orientation' => $this->_fileMeta['orientation'],
            'device' => $this->_fileMeta['device'],
            'geolocation' => $this->_fileMeta['geolocation'],
        ];
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
    public function getImage($file): Image
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
     * Gets the image instance for data or further manipulation.
     *
     * @param File $file
     *
     * @return string
     */
    public function getFileDataDir($file): string
    {
        $fileDataDir = $this->params->get('var_dir') .
            '/data/files/' . $file->getHash();
        if (!$this->filesystem->exists($fileDataDir)) {
            $this->filesystem->mkdir($fileDataDir);
        }

        return $fileDataDir;
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

    private $_geodecodeLocation = [];
    private $_geodecodeCache = [];

    /**
     * Geodecodes the location
     *
     * @param File $file
     * @param bool $skipFetchIfAlreadyExists
     */
    public function geodecode(File $file, $skipFetchIfAlreadyExists = true)
    {
        $fileMeta = $file->getMeta();

        if (
            $fileMeta['geolocation']['latitude'] === null ||
            $fileMeta['geolocation']['longitude'] === null
        ) {
            throw new \Exception('This file has no geolocation data.');

            return false;
        }

        $this->_geodecodeLocation['service'] = null;
        $this->_geodecodeLocation['address'] = [
            'label' => null,
            'street' => null,
            'house_number' => null,
            'postal_code' => null,
            'city' => null,
            'district' => null,
            'state' => null,
            'country' => null,
        ];

        $this->_geocodeHere($file, $skipFetchIfAlreadyExists);

        $file->setLocation($this->_geodecodeLocation);

        return true;
    }

    /**
     * Labels the image
     *
     * @param File $file
     * @param bool $skipFetchIfAlreadyExists
     */
    public function label(File $file, $skipFetchIfAlreadyExists = true)
    {
        // Check if it's a viable file first. If not, it will throw an exception,
        //   so it won't continue any execution.
        try {
            $image = $this->getImage($file);
        } catch (\Exception $e) {
            throw new \Exception(
                'Can not label, because it is not an image. Error: ' .
                $e->getMessage()
            );
        }


        $path = $this->getFileDataDir($file) . '/aws_rekognition_labels.json';

        $alreadyExists = $skipFetchIfAlreadyExists
            && file_exists($path);
        $result = [];

        if ($alreadyExists) {
            $result = json_decode(file_get_contents($path), true);
        } else {
            $image->widen(1024, function ($constraint) {
                $constraint->upsize();
            });
            $image->heighten(1024, function ($constraint) {
                $constraint->upsize();
            });

            $result = $this->awsRekognitionClient->detectLabels([
                'Image' => [
                    'Bytes' => $image->encode('jpg'),
                ],
                'MinConfidence' => $this->awsRekognitionMinConfidence,
            ]);

            file_put_contents($path, json_encode($result->toArray()));
        }

        $tags = [];
        foreach ($result['Labels'] as $label) {
            if ($label['Confidence'] >= $this->labelingConfidence) {
                $tags[] = $label['Name'];
            }
        }

        $file->setTags($tags);

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
                $return = (new \DateTime(
                    $matches[1] . '-' . $matches[2] . '-' . $matches[3] .
                        'T' . $matches[4] . ':' . $matches[5] . ':' . $matches[6]
                ))->format(DATE_ATOM);
            } else {
                $return = null;
            }
        }

        if (is_array($return)) {
            return json_encode($return);
        }

        return $return;
    }

    /**
     * Processed file meta via GD
     *
     * @param File $file
     */
    private function _processFileMetaViaGd($file)
    {
        $exif = exif_read_data($file->getPath(), 0, true);
        $this->_fileMeta['date'] = isset($exif['IFD0']['DateTime'])
            ? $this->_eval($exif['IFD0']['DateTime'], 'datetime')
            : (isset($exif['EXIF']['DateTimeOriginal'])
                ? $exif['EXIF']['DateTimeOriginal']
                : (isset($exif['EXIF']['DateTimeDigitized'])
                    ? $exif['EXIF']['DateTimeDigitized']
                    : (isset($exif['EXIF']['DateTime'])
                        ? $exif['EXIF']['DateTime']
                        : null
                    )
                )
            );
        $this->_fileMeta['size'] = isset($exif['FILE']['FileSize'])
            ? (int)$exif['FILE']['FileSize']
            : filesize($file->getPath());
        $this->_fileMeta['width'] = isset($exif['EXIF']['ExifImageWidth'])
            ? (int)$exif['EXIF']['ExifImageWidth']
            : (isset($exif['COMPUTED']['Width'])
                ? (int)$exif['COMPUTED']['Width']
                : null
            );
        $this->_fileMeta['height'] = isset($exif['EXIF']['ExifImageLength'])
            ? (int)$exif['EXIF']['ExifImageLength']
            : (isset($exif['COMPUTED']['Height'])
                ? (int)$exif['COMPUTED']['Height']
                : null
            );
        $this->_fileMeta['orientation'] = isset($exif['IFD0']['Orientation'])
            ? $exif['IFD0']['Orientation']
            : (isset($exif['COMPUTED']['Orientation'])
                ? (int)$exif['COMPUTED']['Orientation']
                : null
            );

        // Device
        $this->_fileMeta['device']['make'] = $exif['IFD0']['Make'] ?? null;
        $this->_fileMeta['device']['model'] = $exif['IFD0']['Model'] ?? null;
        $this->_fileMeta['device']['shutter_speed'] = isset($exif['EXIF']['ExposureTime'])
            ? $this->_eval($exif['EXIF']['ExposureTime'], 'shutter_speed')
            : (isset($exif['EXIF']['ShutterSpeedValue'])
                ? $this->_eval($exif['EXIF']['ShutterSpeedValue'], 'shutter_speed')
                : null
            );
        $this->_fileMeta['device']['aperture'] = isset($exif['EXIF']['FNumber'])
            ? $this->_eval($exif['EXIF']['FNumber'], 'aperture')
            : (isset($exif['EXIF']['ApertureValue'])
                ? $this->_eval($exif['EXIF']['ApertureValue'], 'aperture')
                : null
            );
        $this->_fileMeta['device']['iso'] = isset($exif['EXIF']['ISOSpeedRatings'])
            ? $this->_eval($exif['EXIF']['ISOSpeedRatings'], 'iso')
            : null;
        $this->_fileMeta['device']['focal_length'] = isset($exif['EXIF']['FocalLength'])
            ? $this->_eval($exif['EXIF']['FocalLength'], 'focal_length')
            : null;
        $this->_fileMeta['device']['lens_make'] = isset($exif['EXIF']['LensInfo'])
            ? $exif['EXIF']['LensInfo']
            : (isset($exif['EXIF']['UndefinedTag:0xA434'])
                ? $exif['EXIF']['UndefinedTag:0xA434']
                : null
            );
        $this->_fileMeta['device']['lens_model'] = isset($exif['EXIF']['LensModel'])
            ? $exif['EXIF']['LensModel']
            : (isset($exif['EXIF']['UndefinedTag:0xA500'])
                ? $exif['EXIF']['UndefinedTag:0xA500']
                : null
            );

        // Geolocation
        $this->_fileMeta['geolocation']['altitude'] = isset($exif['GPS']['GPSAltitude'])
            ? $this->_eval($exif['GPS']['GPSAltitude'], 'altitude')
            : null;
        $this->_fileMeta['geolocation']['latitude'] = isset($exif['GPS']['GPSLatitude'])
            ? $this->_eval($exif['GPS']['GPSLatitude'], 'latitude')
            : null;
        $this->_fileMeta['geolocation']['longitude'] = isset($exif['GPS']['GPSLongitude'])
            ? $this->_eval($exif['GPS']['GPSLongitude'], 'longitude')
            : null;
    }

    /**
     * Processed file meta via Imagick
     *
     * @param File $file
     */
    private function _processFileMetaViaImagick($file)
    {
        $imageMagick = new \imagick($file->getPath());
        $imageMagickProperties = $imageMagick->getImageProperties();

        $this->_fileMeta['date'] = isset($imageMagickProperties['exif:DateTimeOriginal'])
            ? $this->_eval($imageMagickProperties['exif:DateTimeOriginal'], 'datetime')
            : (isset($imageMagickProperties['exif:DateTimeDigitized'])
                ? $this->_eval($imageMagickProperties['exif:DateTimeDigitized'], 'datetime')
                : (isset($imageMagickProperties['exif:DateTime'])
                    ? $this->_eval($imageMagickProperties['exif:DateTime'], 'datetime')
                    : null
                )
            );
        $this->_fileMeta['size'] = $imageMagick->getImageLength();
        $this->_fileMeta['width'] = $imageMagick->getImageWidth();
        $this->_fileMeta['height'] = $imageMagick->getImageHeight();
        $this->_fileMeta['orientation'] = $imageMagick->getImageOrientation();

        // Device
        $this->_fileMeta['device']['make'] = $imageMagickProperties['exif:Make'] ?? null;
        $this->_fileMeta['device']['model'] = $imageMagickProperties['exif:Model'] ?? null;
        $this->_fileMeta['device']['shutter_speed'] = isset($imageMagickProperties['exif:ExposureTime'])
            ? $this->_eval($imageMagickProperties['exif:ExposureTime'], 'shutter_speed')
            : (isset($imageMagickProperties['exif:ShutterSpeedValue'])
                ? $this->_eval($imageMagickProperties['exif:ShutterSpeedValue'], 'shutter_speed')
                : null
            );
        $this->_fileMeta['device']['aperture'] = isset($imageMagickProperties['exif:FNumber'])
            ? $this->_eval($imageMagickProperties['exif:FNumber'], 'aperture')
            : (isset($imageMagickProperties['exif:ApertureValue'])
                ? $this->_eval($imageMagickProperties['exif:ApertureValue'], 'aperture')
                : null
            );
        $this->_fileMeta['device']['iso'] = isset($imageMagickProperties['exif:ISOSpeedRatings'])
            ? $imageMagickProperties['exif:ISOSpeedRatings']
            : null;
        $this->_fileMeta['device']['focal_length'] = isset($imageMagickProperties['exif:FocalLength'])
            ? $this->_eval($imageMagickProperties['exif:FocalLength'], 'focal_length')
            : null;
        $this->_fileMeta['device']['lens_make'] = $imageMagickProperties['exif:LensMake'] ?? null;
        $this->_fileMeta['device']['lens_model'] = $imageMagickProperties['exif:LensModel'] ?? null;

        // Geolocation
        $this->_fileMeta['geolocation']['altitude'] = isset($imageMagickProperties['exif:GPSAltitude'])
            ? $this->_eval($imageMagickProperties['exif:GPSAltitude'], 'altitude')
            : null;
        $this->_fileMeta['geolocation']['latitude'] = isset($imageMagickProperties['exif:GPSLatitude'])
            ? $this->_eval($imageMagickProperties['exif:GPSLatitude'], 'latitude')
            : null;
        $this->_fileMeta['geolocation']['longitude'] = isset($imageMagickProperties['exif:GPSLongitude'])
            ? $this->_eval($imageMagickProperties['exif:GPSLongitude'], 'longitude')
            : null;
    }

    /**
     * Processed file meta via Python
     *
     * @param File $file
     */
    private function _processFileMetaViaPython($file)
    {
        $url = 'http://python:8000/file-info';
        $response = $this->httpClient->request('GET', $url, [
            'query' => [
                'file' => $file->getPath(),
            ],
        ]);

        $content = json_decode($response->getContent(), true);
        if (isset($content['data']['error'])) {
            throw new \Exception($content['data']['error']);
        }

        $exif = $content['data']['exif'];

        $this->_fileMeta['date'] = isset($exif['EXIF DateTimeOriginal'])
            ? $this->_eval($exif['EXIF DateTimeOriginal'], 'datetime')
            : (isset($exif['EXIF DateTimeDigitized'])
                ? $this->_eval($exif['EXIF DateTimeDigitized'], 'datetime')
                : (isset($exif['Image DateTime'])
                    ? $this->_eval($exif['Image DateTime'], 'datetime')
                    : null
                )
            );
        $this->_fileMeta['size'] = filesize($file->getPath());
        // TODO: width & height are wrong. Not sure why.
        $this->_fileMeta['width'] = isset($exif['Image ImageWidth'])
            ? (int)$exif['Image ImageWidth']
            : null;
        $this->_fileMeta['height'] = isset($exif['Image ImageLength'])
            ? (int)$exif['Image ImageLength']
            : null;
        $this->_fileMeta['orientation'] = isset($exif['Image Orientation'])
            ? (int)$exif['Image Orientation']
            : null;

        // Device
        $this->_fileMeta['device']['make'] = isset($exif['Image Make'])
            ? $exif['Image Make']
            : null;
        $this->_fileMeta['device']['model'] = isset($exif['Image Model'])
            ? $exif['Image Model']
            : null;
        $this->_fileMeta['device']['shutter_speed'] = isset($exif['EXIF ExposureTime'])
            ? $this->_eval($exif['EXIF ExposureTime'], 'shutter_speed')
            : (isset($exif['EXIF ShutterSpeedValue'])
                ? $this->_eval($exif['EXIF ShutterSpeedValue'], 'shutter_speed')
                : null
            );
        $this->_fileMeta['device']['aperture'] = isset($exif['EXIF FNumber'])
            ? $this->_eval($exif['EXIF FNumber'], 'aperture')
            : (isset($exif['EXIF ApertureValue'])
                ? $this->_eval($exif['EXIF ApertureValue'], 'aperture')
                : null
            );
        $this->_fileMeta['device']['iso'] = isset($exif['EXIF ISOSpeedRatings'])
            ? $exif['EXIF ISOSpeedRatings']
            : null;
        $this->_fileMeta['device']['focal_length'] = isset($exif['EXIF FocalLength'])
            ? $exif['EXIF FocalLength']
            : null;
        $this->_fileMeta['device']['lens_make'] = isset($exif['EXIF LensMake'])
            ? $exif['EXIF LensMake']
            : null;
        $this->_fileMeta['device']['lens_model'] = isset($exif['EXIF LensModel'])
            ? $exif['EXIF LensModel']
            : null;

        // Geolocation
        $this->_fileMeta['geolocation']['altitude'] = isset($exif['EXIF GPSAltitude'])
            ? $this->_eval($exif['EXIF GPSAltitude'], 'altitude')
            : null;
        $this->_fileMeta['geolocation']['latitude'] = isset($exif['EXIF GPSLatitude'])
            ? $this->_eval($exif['EXIF GPSLatitude'], 'latitude')
            : null;
        $this->_fileMeta['geolocation']['longitude'] = isset($exif['EXIF GPSLongitude'])
            ? $this->_eval($exif['EXIF GPSLongitude'], 'longitude')
            : null;
    }

    /**
     * Geodecodes the location via OSM
     * Note: At the moment, I can't really get it working. After the first request,
     *   I always get "Failed sending data to peer ..."
     *
     * @param File $file
     * @param bool $skipFetchIfAlreadyExists
     */
    private function _geocodeOsm(File $file, $skipFetchIfAlreadyExists)
    {
        $fileMeta = $file->getMeta();

        $latitude_and_longitude = [
            'lat' => $fileMeta['geolocation']['latitude'],
            'lon' => $fileMeta['geolocation']['longitude'],
        ];

        $cacheHash = 'osm.' . sha1(json_encode($latitude_and_longitude));

        $path = $this->getFileDataDir($file) . '/osm_geocode.json';

        $alreadyExists = $skipFetchIfAlreadyExists
            && file_exists($path);

        if ($alreadyExists) {
            $this->_geodecodeCache[$cacheHash] = json_decode(file_get_contents($path), true);
        }

        if (!isset($this->_geodecodeCache[$cacheHash])) {
            $url = 'https://nominatim.openstreetmap.org/reverse';
            $response = $this->httpClient->request('GET', $url, [
                'query' => array_merge([
                    'format' => 'geocodejson',
                ], $latitude_and_longitude),
            ]);
            $content = json_decode($response->getContent(), true);
            if (isset($content['error'])) {
                throw new \Exception($content['error']['message']);

                return false;
            }

            $this->_geodecodeCache[$cacheHash] = $content;
        }

        $geocodeData = $this->_geodecodeCache[$cacheHash];

        if (!$alreadyExists) {
            file_put_contents($path, json_encode($geocodeData));
        }

        $locationData = $geocodeData['features'][0]['properties']['geocoding'];

        $this->_geodecodeLocation['service'] = 'osm';
        $this->_geodecodeLocation['address']['label'] = $locationData['label'] ?? null;
        $this->_geodecodeLocation['address']['street'] = $locationData['street'] ?? null;
        $this->_geodecodeLocation['address']['house_number'] = $locationData['housenumber'] ?? null;
        $this->_geodecodeLocation['address']['postal_code'] = $locationData['postcode'] ?? null;
        $this->_geodecodeLocation['address']['city'] = $locationData['city'] ?? null;
        $this->_geodecodeLocation['address']['state'] = $locationData['state'] ?? null;
        $this->_geodecodeLocation['address']['country'] = $locationData['country'] ?? null;
    }

    /**
     * Geodecodes the location via HERE
     *
     * @param File $file
     * @param bool $skipFetchIfAlreadyExists
     */
    private function _geocodeHere(File $file, $skipFetchIfAlreadyExists)
    {
        $fileMeta = $file->getMeta();

        $latitude_and_longitude = [
            'lat' => $fileMeta['geolocation']['latitude'],
            'lon' => $fileMeta['geolocation']['longitude'],
        ];

        $cacheHash = 'here.' . sha1(json_encode($latitude_and_longitude));

        $path = $this->getFileDataDir($file) . '/here_geocode.json';

        $alreadyExists = $skipFetchIfAlreadyExists
            && file_exists($path);

        if ($alreadyExists) {
            $this->_geodecodeCache[$cacheHash] = json_decode(file_get_contents($path), true);
        }

        if (!isset($this->_geodecodeCache[$cacheHash])) {
            $url = 'https://reverse.geocoder.api.here.com/6.2/reversegeocode.json';
            $response = $this->httpClient->request('GET', $url, [
                'query' => [
                    'app_id' => $this->hereApiCredentials['app_id'],
                    'app_code' => $this->hereApiCredentials['app_code'],
                    'mode' => 'retrieveAll',
                    'maxresults' => $this->hereReverseGeocoderMaxResults,
                    'gen' => '9',
                    'prox' => $fileMeta['geolocation']['latitude'] . ',' .
                        $fileMeta['geolocation']['longitude'] . ',' .
                        $this->hereReverseGeocoderRadius,
                ],
            ]);
            $content = json_decode($response->getContent(), true);
            if (isset($content['error'])) {
                throw new \Exception($content['error']['message']);

                return false;
            }

            $this->_geodecodeCache[$cacheHash] = $content;
        }

        $geocodeData = $this->_geodecodeCache[$cacheHash];

        if (!$alreadyExists) {
            file_put_contents($path, json_encode($geocodeData));
        }

        $view = $geocodeData['Response']['View'];

        if (count($view) === 0) {
            throw new \Exception('Could not find any geolocation data for those coordinates.');
        }

        $results = $view[0]['Result'];
        $locationData = $results[0]['Location']['Address'];

        foreach ($results as $result) {
            // The first one is usually "district", but we may want to set a more detailed location.
            if ($result['MatchLevel'] === 'houseNumber') {
                $locationData = $result['Location']['Address'];
                break;
            }
        }

        $this->_geodecodeLocation['service'] = 'here';
        $this->_geodecodeLocation['address']['label'] = $locationData['Label'] ?? null;
        $this->_geodecodeLocation['address']['street'] = $locationData['Street'] ?? null;
        $this->_geodecodeLocation['address']['house_number'] = $locationData['HouseNumber'] ?? null;
        $this->_geodecodeLocation['address']['postal_code'] = $locationData['PostalCode'] ?? null;
        $this->_geodecodeLocation['address']['city'] = $locationData['City'] ?? null;
        $this->_geodecodeLocation['address']['district'] = $locationData['District'] ?? null;
        $this->_geodecodeLocation['address']['state'] = $locationData['State'] ?? null;
        $this->_geodecodeLocation['address']['country'] = $locationData['Country'] ?? null;
    }
}
