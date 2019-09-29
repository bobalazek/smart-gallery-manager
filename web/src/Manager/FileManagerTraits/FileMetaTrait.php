<?php

namespace App\Manager\FileManagerTraits;

use App\Entity\File;

trait FileMetaTrait {
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
            'latitude_ref' => null,
            'longitude' => null,
            'longitude_ref' => null,
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
            'pixels' => is_numeric($this->_fileMeta['width']) && is_numeric($this->_fileMeta['height'])
                ? $this->_fileMeta['width'] * $this->_fileMeta['height']
                : null,
            'orientation' => $this->_fileMeta['orientation'],
            'device' => $this->_fileMeta['device'],
            'geolocation' => $this->_fileMeta['geolocation'],
        ];
    }

    /**
     * Processed file meta via GD
     *
     * @param File $file
     */
    private function _processFileMetaViaGd($file)
    {
        $exif = @exif_read_data($file->getPath(), 0, true);
        if (!$exif && !is_array($exif)) {
            throw new \Exception(sprintf(
                'Could not read the file on path "%s".',
                $file->getPath()
            ));
        }

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
        if ($this->_fileMeta['date'] !== null) {
            try {
                $this->_fileMeta['date'] = (new \DateTime($this->_fileMeta['date']))->format(DATE_ATOM);
            } catch (\Exception $e) {
                $this->_fileMeta['date'] = null;
            }
        }
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
        $this->_fileMeta['geolocation']['latitude_ref'] = isset($exif['GPS']['GPSLatitudeRef'])
            ? strtolower($this->_eval($exif['GPS']['GPSLatitudeRef']))
            : null;
        $this->_fileMeta['geolocation']['longitude_ref'] = isset($exif['GPS']['GPSLongitudeRef'])
            ? strtolower($this->_eval($exif['GPS']['GPSLongitudeRef']))
            : null;
        $negateLatitude = in_array($this->_fileMeta['geolocation']['latitude_ref'], ['s', 'south']);
        $negateLongitude = in_array($this->_fileMeta['geolocation']['longitude_ref'], ['w', 'west']);

        $this->_fileMeta['geolocation']['altitude'] = isset($exif['GPS']['GPSAltitude'])
            ? (float) $this->_eval($exif['GPS']['GPSAltitude'], 'altitude')
            : null;
        $this->_fileMeta['geolocation']['latitude'] = isset($exif['GPS']['GPSLatitude'])
            ? (float) $this->_eval($exif['GPS']['GPSLatitude'], 'latitude') * ($negateLatitude ? -1 : 1)
            : null;
        $this->_fileMeta['geolocation']['longitude'] = isset($exif['GPS']['GPSLongitude'])
            ? (float) $this->_eval($exif['GPS']['GPSLongitude'], 'longitude') * ($negateLongitude ? -1 : 1)
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
        if ($this->_fileMeta['date'] !== null) {
            try {
                $this->_fileMeta['date'] = (new \DateTime($this->_fileMeta['date']))->format(DATE_ATOM);
            } catch (\Exception $e) {
                $this->_fileMeta['date'] = null;
            }
        }
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
        $this->_fileMeta['geolocation']['latitude_ref'] = isset($imageMagickProperties['exif:GPSLatitudeRef'])
            ? strtolower($this->_eval($imageMagickProperties['exif:GPSLatitudeRef']))
            : null;
        $this->_fileMeta['geolocation']['longitude_ref'] = isset($imageMagickProperties['exif:GPSLongitudeRef'])
            ? strtolower($this->_eval($imageMagickProperties['exif:GPSLongitudeRef']))
            : null;
        $negateLatitude = in_array($this->_fileMeta['geolocation']['latitude_ref'], ['s', 'south']);
        $negateLongitude = in_array($this->_fileMeta['geolocation']['longitude_ref'], ['w', 'west']);

        $this->_fileMeta['geolocation']['altitude'] = isset($imageMagickProperties['exif:GPSAltitude'])
            ? (float) $this->_eval($imageMagickProperties['exif:GPSAltitude'], 'altitude')
            : null;
        $this->_fileMeta['geolocation']['latitude'] = isset($imageMagickProperties['exif:GPSLatitude'])
            ? (float) $this->_eval($imageMagickProperties['exif:GPSLatitude'], 'latitude') * ($negateLatitude ? -1 : 1)
            : null;
        $this->_fileMeta['geolocation']['longitude'] = isset($imageMagickProperties['exif:GPSLongitude'])
            ? (float) $this->_eval($imageMagickProperties['exif:GPSLongitude'], 'longitude') * ($negateLongitude ? -1 : 1)
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
        if ($content === null) {
            throw new \Exception('Invalid JSON returned from service.');
        }

        if (isset($content['error'])) {
            throw new \Exception($content['error']);
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
        if ($this->_fileMeta['date'] !== null) {
            try {
                $this->_fileMeta['date'] = (new \DateTime($this->_fileMeta['date']))->format(DATE_ATOM);
            } catch (\Exception $e) {
                $this->_fileMeta['date'] = null;
            }
        }
        $this->_fileMeta['size'] = filesize($file->getPath());
        $this->_fileMeta['width'] = isset($exif['Image ImageWidth'])
            ? (int)$exif['Image ImageWidth']
            : null;
        $this->_fileMeta['height'] = isset($exif['Image ImageLength'])
            ? (int)$exif['Image ImageLength']
            : null;
        $this->_fileMeta['orientation'] = isset($exif['Image Orientation'])
            ? (int)$exif['Image Orientation']
            : null;

        // Width & height hack
        // It's unlikely that a .dng image will be that small,
        //   so let's just get the converted version (into .jpg),
        //   and get the correct size.
        if (
            $this->_fileMeta['width'] < 800 ||
            $this->_fileMeta['height'] < 600
        ) {
            $image = $this->getImage($file);
            $this->_fileMeta['width'] = $image->getWidth();
            $this->_fileMeta['height'] = $image->getHeight();
        }

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
        $this->_fileMeta['geolocation']['latitude_ref'] = isset($exif['EXIF GPSLatitudeRef'])
            ? strtolower($this->_eval($exif['EXIF GPSLatitudeRef']))
            : null;
        $this->_fileMeta['geolocation']['longitude_ref'] = isset($exif['EXIF GPSLongitudeRef'])
            ? strtolower($this->_eval($exif['EXIF GPSLongitudeRef']))
            : null;
        $negateLatitude = in_array($this->_fileMeta['geolocation']['latitude_ref'], ['s', 'south']);
        $negateLongitude = in_array($this->_fileMeta['geolocation']['longitude_ref'], ['w', 'west']);

        $this->_fileMeta['geolocation']['altitude'] = isset($exif['EXIF GPSAltitude'])
            ? (float) $this->_eval($exif['EXIF GPSAltitude'], 'altitude')
            : null;
        $this->_fileMeta['geolocation']['latitude'] = isset($exif['EXIF GPSLatitude'])
            ? (float) $this->_eval($exif['EXIF GPSLatitude'], 'latitude') * ($negateLatitude ? -1 : 1)
            : null;
        $this->_fileMeta['geolocation']['longitude'] = isset($exif['EXIF GPSLongitude'])
            ? (float) $this->_eval($exif['EXIF GPSLongitude'], 'longitude') * ($negateLongitude ? -1 : 1)
            : null;
    }
}
