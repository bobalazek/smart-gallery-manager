<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ImageFaceRepository")
 * @ORM\Table(name="image_faces")
 */
class ImageFace
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
    private $source;

    /**
     * @ORM\Column(type="decimal", name="`left`", precision=16, scale=15)
     */
    private $left;

    /**
     * @ORM\Column(type="decimal", precision=16, scale=15)
     */
    private $top;

    /**
     * @ORM\Column(type="decimal", precision=16, scale=15)
     */
    private $width;

    /**
     * @ORM\Column(type="decimal", precision=16, scale=15)
     */
    private $height;

    /**
     * @ORM\Column(type="json_array")
     */
    private $meta = [];

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\File", inversedBy="imageFaces")
     * @ORM\JoinColumn(nullable=false)
     */
    private $file;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @ORM\Column(type="datetime")
     */
    private $modifiedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getLeft()
    {
        return $this->left;
    }

    public function setLeft($left): self
    {
        $this->left = $left;

        return $this;
    }

    public function getTop()
    {
        return $this->top;
    }

    public function setTop($top): self
    {
        $this->top = $top;

        return $this;
    }

    public function getWidth()
    {
        return $this->width;
    }

    public function setWidth($width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight()
    {
        return $this->height;
    }

    public function setHeight($height): self
    {
        $this->height = $height;

        return $this;
    }

    public function getMeta()
    {
        return $this->meta;
    }

    public function setMeta($meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): self
    {
        $this->file = $file;

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
}
