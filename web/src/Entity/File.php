<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
     * @ORM\Column(type="string", length=255, unique=true)
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
     * @ORM\Column(type="json_array")
     */
    private $data = [];

    /**
     * @ORM\Column(type="json_array")
     */
    private $meta = [];

    /**
     * @ORM\Column(type="json_array")
     */
    private $location = [];

    /**
     * @ORM\Column(type="json_array")
     */
    private $tags = [];

    /**
     * @ORM\Column(type="json_array")
     */
    private $faces = [];

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\ImageLocation", mappedBy="file", cascade={"persist", "remove"})
     */
    private $imageLocation;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ImageLabel", mappedBy="file", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    private $imageLabels;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ImageFace", mappedBy="file", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    private $imageFaces;

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

    public function __construct()
    {
        $this->imageLabels = new ArrayCollection();
        $this->imageFaces = new ArrayCollection();
    }

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

    public function getMeta(): ?array
    {
        return $this->meta;
    }

    public function setMeta(array $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    public function getLocation(): ?array
    {
        return $this->location;
    }

    public function setLocation(array $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function setTags(array $tags): self
    {
        $this->tags = $tags;

        return $this;
    }

    public function getFaces(): ?array
    {
        return $this->faces;
    }

    public function setFaces(array $faces): self
    {
        $this->faces = $faces;

        return $this;
    }

    public function getImageLocation(): ?ImageLocation
    {
        return $this->imageLocation;
    }

    public function setImageLocation(?ImageLocation $imageLocation): self
    {
        $this->imageLocation = $imageLocation;

        // set (or unset) the owning side of the relation if necessary
        $newFile = $imageLocation === null ? null : $this;
        if ($newFile !== $imageLocation->getFile()) {
            $imageLocation->setFile($newFile);
        }

        return $this;
    }

    /**
     * @return Collection|ImageLabel[]
     */
    public function getImageLabels(): Collection
    {
        return $this->imageLabels;
    }

    public function getImageLabel($source, $name)
    {
        $imageLabels = $this->getImageLabels();
        foreach ($imageLabels as $imageLabel) {
            if (
                $imageLabel->getSource() === $source &&
                $imageLabel->getName() === $name
            ) {
                return $imageLabel;
            }
        }

        return null;
    }

    public function addImageLabel(ImageLabel $imageLabel): self
    {
        if (!$this->imageLabels->contains($imageLabel)) {
            $this->imageLabels[] = $imageLabel;
            $imageLabel->setFile($this);
        }

        return $this;
    }

    public function removeImageLabel(ImageLabel $imageLabel): self
    {
        if ($this->imageLabels->contains($imageLabel)) {
            $this->imageLabels->removeElement($imageLabel);
            // set the owning side to null (unless already changed)
            if ($imageLabel->getFile() === $this) {
                $imageLabel->setFile(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ImageFace[]
     */
    public function getImageFaces(): Collection
    {
        return $this->imageFaces;
    }

    public function getImageFace($source, $left, $top, $width, $height)
    {
        $tolerance = 0.01;
        $imageFaces = $this->getImageFaces();
        foreach ($imageFaces as $imageFace) {
            if (
                $imageFace->getSource() === $source &&
                abs($imageFace->getLeft() - $left) < $tolerance &&
                abs($imageFace->getTop() - $top) < $tolerance &&
                abs($imageFace->getWidth() - $width) < $tolerance &&
                abs($imageFace->getHeight() - $height) < $tolerance
            ) {
                return $imageFace;
            }
        }

        return null;
    }

    public function addImageFace(ImageFace $imageFace): self
    {
        if (!$this->imageFaces->contains($imageFace)) {
            $this->imageFaces[] = $imageFace;
            $imageFace->setFile($this);
        }

        return $this;
    }

    public function removeImageFace(ImageFace $imageFace): self
    {
        if ($this->imageFaces->contains($imageFace)) {
            $this->imageFaces->removeElement($imageFace);
            // set the owning side to null (unless already changed)
            if ($imageFace->getFile() === $this) {
                $imageFace->setFile(null);
            }
        }

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
            'meta' => $this->getMeta(),
            'location' => $this->getLocation(),
            'tags' => $this->getTags(),
            'faces' => $this->getFaces(),
            'created_at' => $this->getCreatedAt()->format(DATE_ATOM),
            'modified_at' => $this->getModifiedAt()->format(DATE_ATOM),
            'taken_at' => $this->getTakenAt()->format(DATE_ATOM),
        ];
    }
}
