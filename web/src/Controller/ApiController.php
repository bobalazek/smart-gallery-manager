<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Mime\MimeTypes;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Image\ImageManager;
use App\Entity\File;

class ApiController extends AbstractController
{
    public function __construct(ParameterBagInterface $params, EntityManagerInterface $em)
    {
        $this->params = $params;
        $this->em = $em;
    }

    /**
     * @Route("/api", name="api")
     */
    public function index()
    {
        return $this->json('Hello World.');
    }

    /**
     * @Route("/api/files", name="api.files")
     */
    public function files(Request $request)
    {
        $mimeTypes = new MimeTypes();

        $format = $request->get('format', 'jpg');
        $offset = (int) $request->get('offset', 0);
        $limit = (int) $request->get('limit', 32);

        $allowedFormats = $this->params->get('allowed_formats');
        if (!in_array($format, $allowedFormats)) {
            throw new \Exception('Invalid format. Allowed: ' . implode(', ', $allowedFormats));
        }

        $files = $this->em->createQueryBuilder()
            ->select('f')
            ->from(File::class, 'f')
            // TODO: only allowed formats
            //->orderBy('f.takenAt', 'DESC')
            ->orderBy('f.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($files as $file) {
            $single = $file->toArray();
            $single['links'] = [
                'thumbnail' => $this->generateUrl(
                    'file',
                    [
                        'hash' => $file->getHash(),
                        'type' => 'thumbnail',
                        'format' => 'jpg',
                    ],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
                'small' => $this->generateUrl(
                    'file',
                    [
                        'hash' => $file->getHash(),
                        'type' => 'small',
                        'format' => 'jpg',
                    ],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
                'original' => $this->generateUrl(
                    'file',
                    [
                        'hash' => $file->getHash(),
                        'type' => 'original',
                        'format' => 'jpg',
                    ],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
            ];

            $data[] = $single;
        }

        return $this->json([
            'data' => $data,
            'meta' => [],
        ]);
    }
}
