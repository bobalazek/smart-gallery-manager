<?php

namespace App\Manager;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
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
                $this->params->get('cache_dir')
            ),
            new FilesystemAdapter(
                'file_tags',
                0,
                $this->params->get('cache_dir')
            )
        );
        $this->mimeTypes = new MimeTypes();
        $this->imageManager = new ImageManager([
            'driver' => 'imagick',
        ]);
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
     * @param string $type
     * @param string $format
     */
    public function generateImageCache(File $file, $type = 'thumbnail', $format = 'jpg')
    {
        $cacheKey = $file->getHash() . '.' . $type . '.' . $format;

        $this->cache->delete($cacheKey);
        $this->getImageData($file, $type, $format);

        return true;
    }

    /**
     * Gets the image instance for data or further manipulation.
     *
     * @param string $format
     */
    public function getImage($path): Image
    {
        $image = $this->imageManager->make($path);
        $image->orientate();

        return $image;
    }

    /**
     * Processes the image
     *
     * @param File $file
     * @param string $type
     * @param string $format
     */
    private function _processImage(File $file, $type = 'thumbnail', $format = 'jpg'): Image
    {
        $imageTypes = $this->params->get('allowed_image_conversion_types');

        if (!array_key_exists($type, $imageTypes)) {
            throw new \Exception(sprintf('The type "%s" does not exist.', $type));
        }

        $image = $this->getImage($file->getPath());

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
}
