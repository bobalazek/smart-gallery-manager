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
        return $this->redirect('files');
    }

    /**
     * @Route("/phpinfo", name="phpinfo")
     */
    public function phpinfo()
    {
        // TODO: remove in production!
        phpinfo();
    }

    /**
     * @Route("/files", name="files")
     */
    public function files(Request $request)
    {
        $offset = (int) $request->get('offset', 0);
        $limit = 16;

        $files = $this->em->createQueryBuilder()
            ->select('f')
            ->from(File::class, 'f')
            ->orderBy('f.takenAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->render('default/files.html.twig', [
            'files' => $files,
            'offset' => $offset,
            'limit' => $limit,
        ]);
    }
}
