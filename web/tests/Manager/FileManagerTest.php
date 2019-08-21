<?php

namespace App\Tests\Manager;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Manager\FileManager;
use App\Entity\File;

class FileManagerTest extends KernelTestCase
{
    protected $fileManager;
    protected $fileJpg;
    protected $fileDng;

    /**
     * {@inheritDoc}
     */
    protected function setUp()
    {
        self::bootKernel();

        $this->fileManager = self::$container->get(FileManager::class);

        // JPG
        $fileJpgPath = dirname(dirname(__FILE__)) . '/resources/DSC_0101.JPG';
        $this->fileJpg = new File();
        $this->fileJpg
            ->setHash(sha1($fileJpgPath))
            ->setType('image')
            ->setPath($fileJpgPath)
            ->setMime('image/jpeg')
            ->setExtension('jpg')
            ->setMeta([])
            ->setModifiedAt(new \DateTime())
            ->setTakenAt(new \DateTime())
            ->setCreatedAt(new \DateTime())
        ;

        // DNG
        $fileDngPath = dirname(dirname(__FILE__)) . '/resources/IMG_2464.dng';
        $this->fileDng = new File();
        $this->fileDng
            ->setHash(sha1($fileDngPath))
            ->setType('image')
            ->setPath($fileDngPath)
            ->setMime('image/tiff')
            ->setExtension('dng')
            ->setMeta([])
            ->setModifiedAt(new \DateTime())
            ->setTakenAt(new \DateTime())
            ->setCreatedAt(new \DateTime())
        ;
    }

    public function testGetFileMeta()
    {
        // Test the JPG file
        $fileMeta = $this->fileManager->getFileMeta($this->fileJpg);
        $this->assertTrue($fileMeta['date'] === '2018-02-24T15:02:35+00:00');
        $this->assertTrue($fileMeta['size'] === 7279431);
        $this->assertTrue($fileMeta['width'] === 5504);
        $this->assertTrue($fileMeta['height'] === 3096);
        $this->assertTrue($fileMeta['pixels'] === 17040384);
        $this->assertTrue($fileMeta['orientation'] === 1);
        $this->assertTrue($fileMeta['device']['make'] === 'Sony');
        $this->assertTrue($fileMeta['device']['model'] === 'G8141');
        $this->assertTrue($fileMeta['device']['shutter_speed'] === '1/1000');
        $this->assertTrue($fileMeta['device']['aperture'] === '2');
        $this->assertTrue($fileMeta['device']['iso'] === '40');
        $this->assertTrue($fileMeta['device']['focal_length'] === '4.4');
        $this->assertTrue($fileMeta['device']['lens_make'] === null);
        $this->assertTrue($fileMeta['device']['lens_model'] === null);

        // Test the DNG file
        $fileMeta = $this->fileManager->getFileMeta($this->fileDng);
        $this->assertTrue($fileMeta['date'] === '2019-08-19T18:18:25+00:00');
        $this->assertTrue($fileMeta['size'] === 27750426);
        $this->assertTrue($fileMeta['width'] === 256);
        $this->assertTrue($fileMeta['height'] === 171);
        $this->assertTrue($fileMeta['pixels'] === 43776);
        $this->assertTrue($fileMeta['orientation'] === 6);
        $this->assertTrue($fileMeta['device']['make'] === 'Canon');
        $this->assertTrue($fileMeta['device']['model'] === 'Canon EOS M50');
        $this->assertTrue($fileMeta['device']['shutter_speed'] === '1/250');
        $this->assertTrue($fileMeta['device']['aperture'] === '5.6');
        $this->assertTrue($fileMeta['device']['iso'] === '100');
        $this->assertTrue($fileMeta['device']['focal_length'] === '22');
        $this->assertTrue($fileMeta['device']['lens_make'] === null);
        $this->assertTrue($fileMeta['device']['lens_model'] === 'EF-M22mm f/2 STM');
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown()
    {
        parent::tearDown();
    }
}
