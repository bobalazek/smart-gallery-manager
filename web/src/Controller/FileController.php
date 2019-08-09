<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Manager\FileManager;
use App\Entity\File;

class FileController extends AbstractController
{
    public function __construct(ParameterBagInterface $params, EntityManagerInterface $em, FileManager $fileManager)
    {
        $this->params = $params;
        $this->em = $em;
        $this->fileManager = $fileManager;
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

        $file = $this->em->getRepository(File::class)->findOneByHash($hash);
        if (!$file) {
            throw $this->createNotFoundException('The file does not exist');
        }

        $fileMeta = $file->getMeta();

        $fileCache = $this->fileManager->getCache(
            $file,
            $type,
            $format,
            $maxAge
        );

        $response = new Response();

        $fileName = str_replace(
            '.' . $file->getExtension(),
            '.' . $fileCache['format'],
            $fileMeta['name']
        );

        $dispositionHeader = HeaderUtils::makeDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $fileName
        );
        $response->headers->set('Content-Disposition', $dispositionHeader);
        $response->headers->set('Content-Type', $fileCache['mime']);
        $response->headers->set('Content-Length', strlen($fileCache['content']));

        $response->setContent($fileCache['content']);
        $response->setSharedMaxAge($maxAge);
        $response->setCache([
            'etag' => sha1($fileCache['key']),
            'last_modified' => $file->getModifiedAt(),
            'max_age' => $maxAge,
            's_maxage' => $maxAge,
            'private' => true,
        ]);

        return $response;
    }
}
