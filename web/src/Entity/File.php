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
        $exif = null;
        try {
            $exif = exif_read_data($this->getPath(), 0, true);
        } catch (\Exception $e) {}

        $device = [
            'make' => $exif['IFD0']['Make'] ?? null,
            'model' => $exif['IFD0']['Model'] ?? null,
            'shutter_speed' => isset($exif['EXIF']['ExposureTime'])
                ? $this->_eval($exif['EXIF']['ExposureTime'], 'shutter_speed')
                : null,
            'aperature' => isset($exif['EXIF']['FNumber'])
                ? $this->_eval($exif['EXIF']['FNumber'], 'aperature')
                : null,
            'iso' => isset($exif['EXIF']['ISOSpeedRatings'])
                ? $this->_eval($exif['EXIF']['ISOSpeedRatings'], 'iso')
                : null,
            'focal_length' => isset($exif['EXIF']['FocalLength'])
                ? $this->_eval($exif['EXIF']['FocalLength'], 'focal_length')
                : null,
        ];
        $date = null;
        if (isset($exif['IFD0']['DateTime'])) {
            if (preg_match(
                '|^([0-9]{4}):([0-9]{2}):([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2})$|',
                $exif['IFD0']['DateTime'],
                $matches
            )) {
                $date = (new \DateTime(
                    $matches[1] . '-' . $matches[2] . '-' . $matches[3] .
                        'T' . $matches[4] . ':' . $matches[5] . ':' . $matches[6]
                ))->format(DATE_ATOM);
            }
        }

        // TODO: if date not present, maybe also look in the name of the file?

        // Dimensions
        $dimensionsWidth = isset($exif['EXIF']['ExifImageWidth'])
            ? $exif['EXIF']['ExifImageWidth']
            : (isset($exif['COMPUTED']['Width'])
                ? $exif['COMPUTED']['Width']
                : null);
        $dimensionsHeight = isset($exif['EXIF']['ExifImageLength'])
            ? $exif['EXIF']['ExifImageLength']
            : (isset($exif['COMPUTED']['Height'])
                ? $exif['COMPUTED']['Height']
                : null);
        $dimensions = [
            'width' => $dimensionsWidth,
            'height' => $dimensionsHeight,
            'total' => is_numeric($dimensionsWidth) && is_numeric($dimensionsHeight)
                ? $dimensionsWidth * $dimensionsHeight
                : null,
        ];
        $size = isset($exif['FILE']['FileSize'])
            ? $exif['FILE']['FileSize']
            : null;

        // Orientation
        $orientation = isset($exif['IFD0']['Orientation'])
            ? $exif['IFD0']['Orientation']
            : null;

        // Location
        $altitude = isset($exif['GPS']['ExifImageLength'])
            ? $this->_eval($exif['GPS']['GPSAltitude'], 'altitude')
            : null;
        $latitude = isset($exif['GPS']['GPSLatitude'])
            ? $this->_eval($exif['GPS']['GPSLatitude'], 'latitude')
            : null;
        $longitude = isset($exif['GPS']['GPSLongitude'])
            ? $this->_eval($exif['GPS']['GPSLongitude'], 'longitude')
            : null;
        $location = [
            'name' => null, // TODO
            'altitude' => $altitude,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];

        return [
            'name' => basename($this->getPath()),
            'date' => $date,
            'size' => $size,
            'dimensions' => $dimensions,
            'device' => $device,
            'orientation' => $orientation,
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
        }

        if (is_array($return)) {
            return json_encode($return);
        }

        return $return;
    }
}
