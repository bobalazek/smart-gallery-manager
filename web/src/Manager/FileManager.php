<?php

namespace App\Manager;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Cache\ItemInterface;
use Intervention\Image\ImageManager;
use Intervention\Image\Image;
use App\Entity\File;

class FileManager {
    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;

        $this->cache = new TagAwareAdapter(
            new FilesystemAdapter(
                'files',
                0,
                $this->params->get('var_dir') . '/cache'
            ),
            new FilesystemAdapter(
                'file_tags',
                0,
                $this->params->get('var_dir') . '/cache'
            )
        );
        $this->mimeTypes = new MimeTypes();
        $this->imageManager = new ImageManager([
            'driver' => 'imagick',
        ]);
        $this->httpClient = new CurlHttpClient();
        $this->filesystem = new Filesystem();
    }

    /**
     * Gets the file meta data
     *
     * @param File $file
     */
    public function getFileMeta(File $file): ?array
    {
        $date = null;
        $size = null;
        $width = null;
        $height = null;
        $orientation = null;
        $device = [
            'make' => null,
            'model' => null,
            'shutter_speed' => null,
            'aperture' => null,
            'iso' => null,
            'focal_length' => null,
            'lens_make' => null,
            'lens_model' => null,
        ];
        $location = [
            'name' => null,
            'altitude' => null,
            'latitude' => null,
            'longitude' => null,
        ];

        try {
            $exif = exif_read_data($file->getPath(), 0, true);
            $date = isset($exif['IFD0']['DateTime'])
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
            $size = isset($exif['FILE']['FileSize'])
                ? (int)$exif['FILE']['FileSize']
                : filesize($file->getPath());
            $width = isset($exif['EXIF']['ExifImageWidth'])
                ? (int)$exif['EXIF']['ExifImageWidth']
                : (isset($exif['COMPUTED']['Width'])
                    ? (int)$exif['COMPUTED']['Width']
                    : null
                );
            $height = isset($exif['EXIF']['ExifImageLength'])
                ? (int)$exif['EXIF']['ExifImageLength']
                : (isset($exif['COMPUTED']['Height'])
                    ? (int)$exif['COMPUTED']['Height']
                    : null
                );
            $orientation = isset($exif['IFD0']['Orientation'])
                ? $exif['IFD0']['Orientation']
                : (isset($exif['COMPUTED']['Orientation'])
                    ? $exif['COMPUTED']['Orientation']
                    : null
                );

            // Device
            $device['make'] = $exif['IFD0']['Make'] ?? null;
            $device['model'] = $exif['IFD0']['Model'] ?? null;
            $device['shutter_speed'] = isset($exif['EXIF']['ExposureTime'])
                ? $this->_eval($exif['EXIF']['ExposureTime'], 'shutter_speed')
                : (isset($exif['EXIF']['ShutterSpeedValue'])
                    ? $this->_eval($exif['EXIF']['ShutterSpeedValue'], 'shutter_speed')
                    : null
                );
            $device['aperture'] = isset($exif['EXIF']['FNumber'])
                ? $this->_eval($exif['EXIF']['FNumber'], 'aperture')
                : (isset($exif['EXIF']['ApertureValue'])
                    ? $this->_eval($exif['EXIF']['ApertureValue'], 'aperture')
                    : null
                );
            $device['iso'] = isset($exif['EXIF']['ISOSpeedRatings'])
                ? $this->_eval($exif['EXIF']['ISOSpeedRatings'], 'iso')
                : null;
            $device['focal_length'] = isset($exif['EXIF']['FocalLength'])
                ? $this->_eval($exif['EXIF']['FocalLength'], 'focal_length')
                : null;
            $device['lens_make'] = isset($exif['EXIF']['LensInfo'])
                ? $exif['EXIF']['LensInfo']
                : (isset($exif['EXIF']['UndefinedTag:0xA434'])
                    ? $exif['EXIF']['UndefinedTag:0xA434']
                    : null
                );
            $device['lens_model'] = isset($exif['EXIF']['LensModel'])
                ? $exif['EXIF']['LensModel']
                : (isset($exif['EXIF']['UndefinedTag:0xA500'])
                    ? $exif['EXIF']['UndefinedTag:0xA500']
                    : null
                );

            // Location
            $location['altitude'] = isset($exif['GPS']['GPSAltitude'])
                ? $this->_eval($exif['GPS']['GPSAltitude'], 'altitude')
                : null;
            $location['latitude'] = isset($exif['GPS']['GPSLatitude'])
                ? $this->_eval($exif['GPS']['GPSLatitude'], 'latitude')
                : null;
            $location['longitude'] = isset($exif['GPS']['GPSLongitude'])
                ? $this->_eval($exif['GPS']['GPSLongitude'], 'longitude')
                : null;
        } catch (\Exception $e) {
            try {
                if ($file->getExtension() === 'dng') {
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

                    $date = isset($exif['EXIF DateTimeOriginal'])
                        ? $this->_eval($exif['EXIF DateTimeOriginal'], 'datetime')
                        : (isset($exif['EXIF DateTimeDigitized'])
                            ? $this->_eval($exif['EXIF DateTimeDigitized'], 'datetime')
                            : (isset($exif['Image DateTime'])
                                ? $this->_eval($exif['Image DateTime'], 'datetime')
                                : null
                            )
                        );
                    $size = filesize($file->getPath());
                    // TODO: width & height are wrong. Not sure why.
                    $width = isset($exif['Image ImageWidth'])
                        ? (int)$exif['Image ImageWidth']
                        : null;
                    $height = isset($exif['Image ImageLength'])
                        ? (int)$exif['Image ImageLength']
                        : null;
                    $orientation = isset($exif['Image Orientation']) // TODO
                        ? ($exif['Image Orientation'] === 'Horizontal (normal)'
                            ? 1 // OR 3?
                            : 6 // OR 8?
                        )
                        : null;

                    // Device
                    $device['make'] = isset($exif['Image Make'])
                        ? $exif['Image Make']
                        : null;
                    $device['model'] = isset($exif['Image Model'])
                        ? $exif['Image Model']
                        : null;
                    $device['shutter_speed'] = isset($exif['EXIF ExposureTime'])
                        ? $this->_eval($exif['EXIF ExposureTime'], 'shutter_speed')
                        : (isset($exif['EXIF ShutterSpeedValue'])
                            ? $this->_eval($exif['EXIF ShutterSpeedValue'], 'shutter_speed')
                            : null
                        );
                    $device['aperture'] = isset($exif['EXIF FNumber'])
                        ? $this->_eval($exif['EXIF FNumber'], 'aperture')
                        : (isset($exif['EXIF ApertureValue'])
                            ? $this->_eval($exif['EXIF ApertureValue'], 'aperture')
                            : null
                        );
                    $device['iso'] = isset($exif['EXIF ISOSpeedRatings'])
                        ? $exif['EXIF ISOSpeedRatings']
                        : null;
                    $device['focal_length'] = isset($exif['EXIF FocalLength'])
                        ? $exif['EXIF FocalLength']
                        : null;
                    $device['lens_make'] = isset($exif['EXIF LensMake'])
                        ? $exif['EXIF LensMake']
                        : null;
                    $device['lens_model'] = isset($exif['EXIF LensModel'])
                        ? $exif['EXIF LensModel']
                        : null;

                    // Location
                    $location['altitude'] = isset($exif['EXIF GPSAltitude'])
                        ? $this->_eval($exif['EXIF GPSAltitude'], 'altitude')
                        : null;
                    $location['latitude'] = isset($exif['EXIF GPSLatitude'])
                        ? $this->_eval($exif['EXIF GPSLatitude'], 'latitude')
                        : null;
                    $location['longitude'] = isset($exif['EXIF GPSLongitude'])
                        ? $this->_eval($exif['EXIF GPSLongitude'], 'longitude')
                        : null;
                } else {
                    $imageMagick = new \imagick($file->getPath());
                    $imageMagickProperties = $imageMagick->getImageProperties();

                    $date = isset($imageMagickProperties['exif:DateTimeOriginal'])
                        ? $this->_eval($imageMagickProperties['exif:DateTimeOriginal'], 'datetime')
                        : (isset($imageMagickProperties['exif:DateTimeDigitized'])
                            ? $this->_eval($imageMagickProperties['exif:DateTimeDigitized'], 'datetime')
                            : (isset($imageMagickProperties['exif:DateTime'])
                                ? $this->_eval($imageMagickProperties['exif:DateTime'], 'datetime')
                                : null
                            )
                        );
                    $size = $imageMagick->getImageLength();
                    $width = $imageMagick->getImageWidth();
                    $height = $imageMagick->getImageHeight();
                    $orientation = $imageMagick->getImageOrientation();

                    // Device
                    $device['make'] = $imageMagickProperties['exif:Make'] ?? null;
                    $device['model'] = $imageMagickProperties['exif:Model'] ?? null;
                    $device['shutter_speed'] = isset($imageMagickProperties['exif:ExposureTime'])
                        ? $this->_eval($imageMagickProperties['exif:ExposureTime'], 'shutter_speed')
                        : (isset($imageMagickProperties['exif:ShutterSpeedValue'])
                            ? $this->_eval($imageMagickProperties['exif:ShutterSpeedValue'], 'shutter_speed')
                            : null
                        );
                    $device['aperture'] = isset($imageMagickProperties['exif:FNumber'])
                        ? $this->_eval($imageMagickProperties['exif:FNumber'], 'aperture')
                        : (isset($imageMagickProperties['exif:ApertureValue'])
                            ? $this->_eval($imageMagickProperties['exif:ApertureValue'], 'aperture')
                            : null
                        );
                    $device['iso'] = isset($imageMagickProperties['exif:ISOSpeedRatings'])
                        ? $imageMagickProperties['exif:ISOSpeedRatings']
                        : null;
                    $device['focal_length'] = isset($imageMagickProperties['exif:FocalLength'])
                        ? $this->_eval($imageMagickProperties['exif:FocalLength'], 'focal_length')
                        : null;
                    $device['lens_make'] = $imageMagickProperties['exif:LensMake'] ?? null;
                    $device['lens_model'] = $imageMagickProperties['exif:LensModel'] ?? null;

                    // Location
                    $location['altitude'] = isset($imageMagickProperties['exif:GPSAltitude'])
                        ? $this->_eval($imageMagickProperties['exif:GPSAltitude'], 'altitude')
                        : null;
                    $location['latitude'] = isset($imageMagickProperties['exif:GPSLatitude'])
                        ? $this->_eval($imageMagickProperties['exif:GPSLatitude'], 'latitude')
                        : null;
                    $location['longitude'] = isset($imageMagickProperties['exif:GPSLongitude'])
                        ? $this->_eval($imageMagickProperties['exif:GPSLongitude'], 'longitude')
                        : null;
                }
            } catch (\Exception $ee) {}
        }

        return [
            'name' => basename($file->getPath()),
            'date' => $date,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'pixels' => is_numeric($width) && is_numeric($height)
                ? $width * $height
                : null,
            'orientation' => $orientation,
            'device' => $device,
            'location' => $location,
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
                $item->tag('image');

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
     * Generates the cache for the the specified file
     *
     * @param File $file
     * @param array $types
     * @param string $format
     */
    public function generateImageCache(File $file, $types = ['thumbnail', 'preview'], $format = 'jpg')
    {
        $allowedImageConversionTypes = $this->params->get('allowed_image_conversion_types');

        foreach ($types as $type) {
            if (!isset($allowedImageConversionTypes)) {
                throw new \Exception('The type "' . $type . '" does not exist.');
            }

            $cacheKey = $file->getHash() . '.' . $type . '.' . $format;

            $this->cache->delete($cacheKey);
            $this->getImageData($file, $type, $format);
        }

        return true;
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
            $fileDataDir = $this->getFileDataDir($file);

            $path = $fileDataDir . '/converted_from_dng.jpg';
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
            $image->widen($imageTypes[$type]['height'], function ($constraint) {
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
}
