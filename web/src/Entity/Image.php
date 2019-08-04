<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ImageRepository")
 */
class Image
{
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
     * @ORM\Column(type="array")
     */
    private $data = [];

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

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getProcessedData(): ?array
    {
        $data = $this->getData();
        $exif = exif_read_data($data['real_path'], 0, true);

        return [
            'make' => $exif['IFD0']['Make'] ?? null,
            'model' => $exif['IFD0']['Model'] ?? null,
            'shutter_speed' => $exif['EXIF']['ExposureTime'] ?? null,
            'aperature' => $exif['EXIF']['FNumber'] ?? null,
            'iso' => $exif['EXIF']['ISOSpeedRatings'] ?? null,
            'focal_length' => $exif['EXIF']['FocalLength'] ?? null,
            'date' => $exif['IFD0']['DateTime'] ?? null,
            'orientation' => $exif['IFD0']['Orientation'] ?? null,
        ];
    }
}
