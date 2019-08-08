<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Image\ImageManager;
use App\Entity\File;

class FileController extends AbstractController
{
    public function __construct(ParameterBagInterface $params, EntityManagerInterface $em)
    {
        $this->params = $params;
        $this->em = $em;
    }

    /**
     * @Route("/file/{hash}.json", name="file.info")
     */
    public function info($hash)
    {
        $file = $this->em->getRepository(File::class)->findOneByHash($hash);
        if (!$file) {
            throw $this->createNotFoundException('The file does not exist');
        }

        return $this->json(
            $file->toArray()
        );
    }

    /**
     * @Route("/file/{hash}.{type}.{format}", name="file.view")
     */
    public function view($hash, $type, $format, Request $request)
    {
        ini_set('memory_limit', '512M');

        $allowedFormats = $this->params->get('allowed_formats');
        $maxAge = $this->params->get('max_age');

        if (!in_array($type, ['thumbnail', 'small', 'original'])) {
            throw new \Exception('Invalid type. Allowed: "thumbnail", "small" or "original"');
        }

        if (!in_array($format, $allowedFormats)) {
            throw new \Exception('Invalid format. Allowed: ' . implode(', ', $allowedFormats));
        }

        $cache = new TagAwareAdapter(
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

        $file = $this->em->getRepository(File::class)->findOneByHash($hash);
        if (!$file) {
            throw $this->createNotFoundException('The file does not exist');
        }

        $fileMeta = $file->getMeta();

        $cacheKey = $hash . '.' . $type . '.' . $format;
        $fileMimeAndContent = $cache->get(
            $cacheKey,
            function (ItemInterface $item) use ($file, $type, $format, $maxAge) {
                $item->tag('file');
                $item->expiresAfter($maxAge);

                $mime = $file->getMime();
                $content = '';

                $isImage = strpos('image/', $mime) !== false
                    || $file->getExtension() === 'heic';

                if ($isImage) {
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
                } else {
                    $content = file_get_contents($file->getPath());
                }

                return [
                    'format' => $format,
                    'mime' => $mime,
                    'content' => $content,
                ];
            }
        );

        $response = new Response();

        $fileName = str_replace(
            '.' . $file->getExtension(),
            '.' . $fileMimeAndContent['format'],
            $fileMeta['name']
        );

        $dispositionHeader = HeaderUtils::makeDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $fileName
        );
        $response->headers->set('Content-Disposition', $dispositionHeader);
        $response->headers->set('Content-Type', $fileMimeAndContent['mime']);
        $response->headers->set('Content-Length', strlen($fileMimeAndContent['content']));

        $response->setContent($fileMimeAndContent['content']);
        $response->setSharedMaxAge($maxAge);
        $response->setCache([
            'etag' => sha1($cacheKey),
            'last_modified' => $file->getModifiedAt(),
            'max_age' => $maxAge,
            's_maxage' => $maxAge,
            'private' => true,
        ]);

        return $response;
    }
}
