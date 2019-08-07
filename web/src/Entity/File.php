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
     * @ORM\Column(type="string", length=32)
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

    public function getMeta(): ?array
    {
        $exif = null;
        try {
            $exif = exif_read_data($this->getPath(), 0, true);
        } catch (\Exception $e) {}

        $device = [
            'make' => $exif['IFD0']['Make'] ?? null,
            'model' => $exif['IFD0']['Model'] ?? null,
            'shutter_speed' => $exif['EXIF']['ExposureTime'] ?? null,
            'aperature' => $exif['EXIF']['FNumber'] ?? null,
            'iso' => $exif['EXIF']['ISOSpeedRatings'] ?? null,
            'focal_length' => $exif['EXIF']['FocalLength'] ?? null,
            'orientation' => $exif['IFD0']['Orientation'] ?? null,
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

        return [
            'name' => basename($this->getPath()),
            'date' => $date,
            'device' => $device,
        ];
    }

    public function toArray(): ?array
    {
        return [
            'id' => $this->getId(),
            'hash' => $this->getHash(),
            'path' => $this->getPath(),
            'type' => $this->getType(),
            'mime' => $this->getMime(),
            'data' => $this->getData(),
            'created_at' => $this->getCreatedAt()->format(DATE_ATOM),
            'modified_at' => $this->getModifiedAt()->format(DATE_ATOM),
            'taken_at' => $this->getTakenAt()->format(DATE_ATOM),
            'meta' => $this->getMeta(),
        ];
    }
}
