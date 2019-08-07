<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Image\ImageManager;
use App\Entity\File;

class DefaultController extends AbstractController
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @Route("/", name="index")
     */
    public function index()
    {
        return $this->render('default/index.html.twig');
    }

    /**
     * @Route("/files", name="files")
     */
    public function files()
    {
        $filesRepository = $this->em->getRepository(File::class);

        return $this->render('default/files.html.twig', [
            'files' => $filesRepository->findAll(),
        ]);
    }

    /**
     * The method should be called "file()",
     *   but that's already used by the AbstractController
     *
     * @Route("/file/{hash}.{type}.{format}", name="file")
     */
    public function fileDetail($hash, $type, $format)
    {
        ini_set('memory_limit', '512M');

        $maxAge = 300; // TODO: add to params

        if (!in_array($type, ['thumbnail', 'small', 'original'])) {
            throw new \Exception('Invalid type. Allowed: "thumbnail", "small" or "original"');
        }

        if (!in_array($format, ['jpg'])) {
            throw new \Exception('Invalid format. Allowed: "jpg"');
        }

        $cache = new TagAwareAdapter(
            new FilesystemAdapter(
                'files'
            ),
            new FilesystemAdapter(
                'file_tags'
            )
        );

        //$cache->invalidateTags(['file']);

        $filesRepository = $this->em->getRepository(File::class);
        $file = $filesRepository->findOneByHash($hash);
        if (!$file) {
            throw $this->createNotFoundException('The file does not exist');
        }

        $fileData = $file->getData();

        $cacheKey = $hash . '.' . $type . '.' . $format;
        $fileMimeAndContent = $cache->get(
            $cacheKey,
            function (ItemInterface $item) use ($fileData, $type, $format, $maxAge) {
                $item->tag('file');
                $item->expiresAfter($maxAge);

                // TODO: do only if image!
                $manager = new ImageManager(['driver' => 'imagick']);

                $fileInstance = $manager->make($fileData['real_path']);
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

                return [
                    'mime' => $fileInstance->mime(),
                    'content' => $fileInstance->encode($format),
                ];
            }
        );

        $response = new Response();

        $dispositionHeader = HeaderUtils::makeDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $fileData['relative_pathname']
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
