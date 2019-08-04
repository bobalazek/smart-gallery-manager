<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\File\File;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Image\ImageManager;
use App\Entity\Image;

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
     * @Route("/images", name="images")
     */
    public function images()
    {
        $imagesRepository = $this->em->getRepository(Image::class);

        return $this->render('default/images.html.twig', [
            'images' => $imagesRepository->findAll(),
        ]);
    }

    /**
     * @Route("/image/{hash}/{image_name}", name="image")
     */
    public function image($hash, $image_name)
    {
        ini_set('memory_limit', '256M');

        $imagesRepository = $this->em->getRepository(Image::class);
        $image = $imagesRepository->findOneByHash($hash);
        if (!$image) {
            // TODO: return a blank image or something
        }

        $manager = new ImageManager();

        $imageData = $image->getData();

        $image = $manager->make($imageData['real_path']);

        $image->orientate();

        $response = new StreamedResponse();
        $response->setCallback(function() use ($image) {
            echo $image->stream();
            flush();
        });

        $dispositionHeader = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $imageData['relative_pathname'],
            'image.jpg'
        );
        $response->headers->set('Content-Disposition', $dispositionHeader);

        return $response;
    }
}
