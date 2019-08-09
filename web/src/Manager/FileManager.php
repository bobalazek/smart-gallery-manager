<?php

namespace App\Manager;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;
use Intervention\Image\ImageManager;
use App\Entity\File;

class FileManager {
    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
        $this->cache = new TagAwareAdapter(
            new FilesystemAdapter(
                'files',
                0,
                realpath($this->params->get('kernel.root_dir') . '/../../var/cache')
            ),
            new FilesystemAdapter(
                'file_tags',
                0,
                realpath($this->params->get('kernel.root_dir') . '/../../var/cache')
            )
        );
    }

    /**
     * Gets the cache for the the specified file
     *
     * @param File $file
     * @param string $type
     * @param string $format
     * @param int $maxAge
     */
    public function getCache(File $file, $type = 'thumbnail', $format = 'jpg', $maxAge = 0) {
        if ($file->getType() !== File::TYPE_IMAGE) {
            throw new \Exception('Only images can be cached right now.');
        }

        $cacheKey = $file->getHash() . '.' . $type . '.' . $format;

        return $this->cache->get(
            $cacheKey,
            function (ItemInterface $item) use ($file, $cacheKey, $type, $format, $maxAge) {
                $item->tag('file');
                $item->expiresAfter($maxAge);

                $content = '';

                $mimeTypes = new MimeTypes();
                $manager = new ImageManager([
                    'driver' => 'imagick',
                ]);

                $fileInstance = $manager->make($file->getPath());
                $fileInstance->orientate();

                if ($type === 'thumbnail') {
                    $fileInstance->widen(64, function ($constraint) {
                        $constraint->upsize();
                    });
                } elseif ($type === 'small') {
                    $fileInstance->widen(640, function ($constraint) {
                        $constraint->upsize();
                    });
                }

                $formatMimeTypes = $mimeTypes->getMimeTypes($format);
                $mime = $formatMimeTypes[0];
                $content = $fileInstance->encode($format);

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
     * @param int $maxAge
     */
    public function generateCache(File $file, $type = 'thumbnail', $format = 'jpg', $maxAge = 0) {
        $cacheKey = $file->getHash() . '.' . $type . '.' . $format;

        $this->cache->delete($cacheKey);
        $this->getCache($file, $type, $format, $maxAge);

        return true;
    }
}
