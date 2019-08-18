<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FileRepository")
 * @ORM\Table(name="files",indexes={
 *   @ORM\Index(name="hash_idx", columns={"hash"})
 * })
 */
class File
{
    /***** Types *****/
    const TYPE_IMAGE = 'image';
    const TYPE_VIDEO = 'video';
    const TYPE_AUDIO = 'audio';
    const TYPE_OTHER = 'other';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $hash;

    /**
     * @ORM\Column(type="string", length=16)
     */
    private $type;

    /**
     * @ORM\Column(type="text")
     */
    private $path;

    /**
     * @ORM\Column(type="string", length=128)
     */
    private $mime;

    /**
     * @ORM\Column(type="string", length=16)
     */
    private $extension;

    /**
     * @ORM\Column(type="array")
     */
    private $data = [];

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @ORM\Column(type="datetime")
     */
    private $modifiedAt;

    /**
     * @ORM\Column(type="datetime")
     */
    private $takenAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(string $hash): self
    {
        $this->hash = $hash;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getMime(): ?string
    {
        return $this->mime;
    }

    public function setMime(string $mime): self
    {
        $this->mime = $mime;

        return $this;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): self
    {
        $this->extension = $extension;

        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getModifiedAt(): ?\DateTimeInterface
    {
        return $this->modifiedAt;
    }

    public function setModifiedAt(\DateTimeInterface $modifiedAt): self
    {
        $this->modifiedAt = $modifiedAt;

        return $this;
    }

    public function getTakenAt(): ?\DateTimeInterface
    {
        return $this->takenAt;
    }

    public function setTakenAt(\DateTimeInterface $takenAt): self
    {
        $this->takenAt = $takenAt;

        return $this;
    }

    public function getProcessedMeta(): ?array
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
            'aperature' => null,
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
            $exif = exif_read_data($this->getPath(), 0, true);

            $date = isset($exif['IFD0']['DateTime'])
                ? $this->_eval($exif['IFD0']['DateTime'], 'datetime')
                : null;
            $size = isset($exif['FILE']['FileSize'])
                ? $exif['FILE']['FileSize']
                : null;
            $width = isset($exif['EXIF']['ExifImageWidth'])
                ? $exif['EXIF']['ExifImageWidth']
                : (isset($exif['COMPUTED']['Width'])
                    ? $exif['COMPUTED']['Width']
                    : null);
            $height = isset($exif['EXIF']['ExifImageLength'])
                ? $exif['EXIF']['ExifImageLength']
                : (isset($exif['COMPUTED']['Height'])
                    ? $exif['COMPUTED']['Height']
                    : null);
            $orientation = isset($exif['IFD0']['Orientation'])
                ? $exif['IFD0']['Orientation']
                : null;

            // Device
            $device['make'] = $exif['IFD0']['Make'] ?? null;
            $device['model'] = $exif['IFD0']['Model'] ?? null;
            $device['shutter_speed'] = isset($exif['EXIF']['ExposureTime'])
                ? $this->_eval($exif['EXIF']['ExposureTime'], 'shutter_speed')
                : null;
            $device['aperature'] = isset($exif['EXIF']['FNumber'])
                ? $this->_eval($exif['EXIF']['FNumber'], 'aperature')
                : null;
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
                    : null);
            $device['lens_model'] = isset($exif['EXIF']['LensModel'])
                ? $exif['EXIF']['LensModel']
                : (isset($exif['EXIF']['UndefinedTag:0xA500'])
                    ? $exif['EXIF']['UndefinedTag:0xA500']
                    : null);

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
                $imageMagick = new \imagick($this->getPath());
                $imageMagickProperties = $imageMagick->getImageProperties();

                $date = isset($imageMagickProperties['exif:DateTime'])
                    ? $this->_eval($imageMagickProperties['exif:DateTime'], 'datetime')
                    : null;
                $size = $imageMagick->getImageSize();
                $width = $imageMagick->getImageWidth();
                $height = $imageMagick->getImageHeight();
                $orientation = $imageMagick->getImageOrientation();

                // Device
                $device['make'] = $imageMagickProperties['exif:Make'] ?? null;
                $device['model'] = $imageMagickProperties['exif:Model'] ?? null;
                $device['shutter_speed'] = isset($imageMagickProperties['ShutterSpeedValue'])
                    ? $this->_eval($imageMagickProperties['ShutterSpeedValue'], 'shutter_speed')
                    : null;
                $device['aperature'] = isset($imageMagickProperties['exif:ApertureValue'])
                    ? $this->_eval($imageMagickProperties['exif:ApertureValue'], 'aperature')
                    : null;
                $device['iso'] = isset($imageMagickProperties['exif:ExposureTime'])
                    ? $this->_eval($imageMagickProperties['exif:ExposureTime'], 'iso')
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
            } catch (\Exception $ee) {}
        }

        return [
            'name' => basename($this->getPath()),
            'date' => $date,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'megapixels' => is_numeric($width) && is_numeric($height)
                ? $width * $height
                : null,
            'orientation' => $orientation,
            'device' => $device,
            'location' => $location,
        ];
    }

    public function toArray(): ?array
    {
        return [
            'id' => $this->getId(),
            'hash' => $this->getHash(),
            'type' => $this->getType(),
            'path' => $this->getPath(),
            'mime' => $this->getMime(),
            'extension' => $this->getExtension(),
            'data' => $this->getData(),
            'created_at' => $this->getCreatedAt()->format(DATE_ATOM),
            'modified_at' => $this->getModifiedAt()->format(DATE_ATOM),
            'taken_at' => $this->getTakenAt()->format(DATE_ATOM),
            'processed_meta' => $this->getProcessedMeta(),
        ];
    }

    /**
     * @param mixed $data
     * @param string $type
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
