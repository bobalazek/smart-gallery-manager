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
     * @Route("/file/{hash}.{type}.{format}", name="file.view")
     */
    public function view($hash, $type, $format, Request $request)
    {
        $allowedTypes = array_keys($this->params->get('allowed_image_conversion_types'));
        if (!in_array($type, $allowedTypes)) {
            throw new \Exception('Invalid type. Allowed: ' . implode(', ', $allowedTypes));
        }

        if (!in_array($format, ['jpg'])) {
            throw new \Exception('Invalid format. Allowed: jpg');
        }

        $file = $this->em->getRepository(File::class)->findOneByHash($hash);
        if (!$file) {
            throw $this->createNotFoundException('The file does not exist');
        }

        $imageData = $this->fileManager->getImageData(
            $file,
            $type,
            $format
        );

        $response = new Response();

        $fileName = mb_convert_encoding(
            str_replace(
                '.' . $file->getExtension(),
                '.' . $imageData['format'],
                basename($file->getPath())
            ),
            'ASCII'
        );

        $dispositionHeader = HeaderUtils::makeDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $fileName
        );
        $response->headers->set('Content-Disposition', $dispositionHeader);
        $response->headers->set('Content-Type', $imageData['mime']);
        $response->headers->set('Content-Length', strlen($imageData['content']));

        $response->setContent($imageData['content']);
        $response->setSharedMaxAge(600);
        $response->setCache([
            'etag' => sha1($imageData['key']),
            'last_modified' => $file->getModifiedAt(),
            'max_age' => 600,
            's_maxage' => 600,
            'private' => true,
        ]);

        return $response;
    }
}
