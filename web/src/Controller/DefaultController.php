<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\File;

class DefaultController extends AbstractController
{
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @Route("/", name="index")
     */
    public function index()
    {
        return $this->redirect('gallery');
    }

    /**
     * @Route("/gallery", name="gallery")
     */
    public function gallery(Request $request)
    {
        return $this->render('default/gallery.html.twig');
    }

    /**
     * @Route("/gallery/{path}", name="gallery")
     */
    public function galleryWithPath(Request $request, $path = '')
    {
        return $this->render('default/gallery.html.twig');
    }
}
