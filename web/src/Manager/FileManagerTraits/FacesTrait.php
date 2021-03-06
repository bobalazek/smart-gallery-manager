<?php

namespace App\Manager\FileManagerTraits;

use App\Entity\File;
use App\Entity\ImageFace;

trait FacesTrait {
    private $_faces = [];

    /**
     * Finds the faces on the image
     *
     * @param File $file
     * @param bool $skipFetchIfAlreadyExists
     */
    public function faces(File $file, $skipFetchIfAlreadyExists = true)
    {
        if (!$this->facesEnabled) {
            throw new \Exception(
                'The faces are disabled.'
            );
        }

        $this->_faces = [];

        $this->_facesMtcnn($file, $skipFetchIfAlreadyExists);

        foreach ($this->_faces as $face) {
            $imageFace = $file->getImageFace(
                $this->facesService,
                $face['box'][0], // left
                $face['box'][1], // top
                $face['box'][2], // width
                $face['box'][3] // height
            );
            if (!$imageFace) {
                $imageFace = new ImageFace();
                $imageFace->setCreatedAt(new \DateTime());

                $file->addImageFace($imageFace);
            }
            $imageFace
                ->setSource($this->facesService)
                ->setLeft($face['box'][0])
                ->setTop($face['box'][1])
                ->setWidth($face['box'][2])
                ->setHeight($face['box'][3])
                ->setMeta($face)
                ->setModifiedAt(new \DateTime())
            ;
        }

        return true;
    }

    /**
     * @param string|null $service
     *
     * @return string
     */
    public function getFacesFileName($service = null)
    {
        if ($service === null) {
            $service = $this->facesService;
        }

        if ($service === 'mtcnn') {
            return 'mtcnn.json';
        }

        throw new \Exception(sprintf(
            'The faces service "%s" does not exist.',
            $service
        ));
    }

    /**
     * Detects faces via mtcnn
     *
     * @param File $file
     * @param bool $skipFetchIfAlreadyExists
     */
    private function _facesMtcnn($file, $skipFetchIfAlreadyExists)
    {
        // Check if it's a viable file first. If not, it will throw an exception,
        //   so it won't continue any execution.
        try {
            $image = $this->getImage($file);
        } catch (\Exception $e) {
            throw new \Exception(
                'Can not detect faces, because it is not an image. Error: ' .
                $e->getMessage()
            );
        }

        $path = $this->getFileDataDir($file) . '/' . $this->getFacesFileName();

        $alreadyExists = $skipFetchIfAlreadyExists && file_exists($path);
        $result = [];

        if ($alreadyExists) {
            $result = json_decode(file_get_contents($path), true);
        } else {
            if ($this->logger) {
                $this->logger->debug('Faces data does not exist. Feching from service ...');
            }

            $image->widen(1024, function ($constraint) {
                $constraint->upsize();
            });
            $image->heighten(1024, function ($constraint) {
                $constraint->upsize();
            });

            $url = 'http://python:8000/file-faces';
            $response = $this->httpClient->request('POST', $url, [
                'body' => (string) $image->encode('jpg'),
            ]);

            $result = json_decode($response->getContent(), true);
            if ($result === null) {
                throw new \Exception('Invalid JSON returned from service.');
            }

            if (isset($result['error'])) {
                throw new \Exception($result['error']);
            }

            file_put_contents($path, json_encode($result));
        }

        $faces = [];
        foreach ($result['data'] as $face) {
            $faces[] = $face;
        }

        $this->_faces = $faces;
    }
}
