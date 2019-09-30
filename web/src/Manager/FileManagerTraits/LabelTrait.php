<?php

namespace App\Manager\FileManagerTraits;

use App\Entity\File;
use App\Entity\ImageLabel;

trait LabelTrait {
    private $_labels = [];

    /**
     * Labels the image
     *
     * @param File $file
     * @param bool $skipFetchIfAlreadyExists
     */
    public function label(File $file, $skipFetchIfAlreadyExists = true)
    {
        $this->_labels = [];

        if (!$this->labellingEnabled) {
            throw new \Exception(
                'The labelling is disabled.'
            );
        }

        if ($this->labellingService === 'amazon_rekognition') {
            $this->_labelAmazonRekognition($file, $skipFetchIfAlreadyExists);
        } else {
            throw new \Exception(
                sprintf(
                    'The specified labelling service "%s" does not exist.',
                    $this->labellingService
                )
            );
        }

        foreach ($this->_labels as $label) {
            $imageLabel = $file->getImageLabel(
                $this->labellingService,
                $label['name']
            );
            if (!$imageLabel) {
                $imageLabel = new ImageLabel();
                $imageLabel->setCreatedAt(new \DateTime());

                $file->addImageLabel($imageLabel);
            }
            $imageLabel
                ->setSource($this->labellingService)
                ->setName($label['name'])
                ->setConfidence($label['confidence'])
                ->setMeta($label['meta'])
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
    public function getLabelFileName($service = null)
    {
        if ($service === null) {
            $service = $this->labellingService;
        }

        if ($service === 'amazon_rekognition') {
            return 'amazon_rekognition_labels.json';
        }

        throw new \Exception(sprintf(
            'The labelling service "%s" does not exist.',
            $service
        ));
    }

    /**
     * Labels via amazon rekognition
     *
     * @param File $file
     * @param bool $skipFetchIfAlreadyExists
     */
    private function _labelAmazonRekognition(File $file, $skipFetchIfAlreadyExists)
    {
        if (
            $this->awsCredentials['key'] === '' ||
            $this->awsCredentials['secret'] === ''
        ) {
            throw new \Exception('AWS credentials are not set. Could not label the file.');
        }

        // Check if it's a viable file first. If not, it will throw an exception,
        //   so it won't continue any execution.
        try {
            $image = $this->getImage($file);
        } catch (\Exception $e) {
            throw new \Exception(
                'Can not label, because it is not an image. Error: ' .
                $e->getMessage()
            );
        }

        $path = $this->getFileDataDir($file) . '/' . $this->getLabelFileName('amazon_rekognition');

        $alreadyExists = $skipFetchIfAlreadyExists && file_exists($path);
        $result = [];

        if ($alreadyExists) {
            $result = json_decode(file_get_contents($path), true);
        } else {
            if ($this->logger) {
                $this->logger->debug('Labelling data does not exist. Feching from service ...');
            }

            $image->widen(1024, function ($constraint) {
                $constraint->upsize();
            });
            $image->heighten(1024, function ($constraint) {
                $constraint->upsize();
            });

            $result = $this->awsRekognitionClient->detectLabels([
                'Image' => [
                    'Bytes' => $image->encode('jpg'),
                ],
                'MinConfidence' => $this->awsRekognitionMinConfidence,
            ]);

            file_put_contents($path, json_encode($result->toArray()));
        }

        $tags = [];
        foreach ($result['Labels'] as $label) {
            $this->_labels[] = [
                'name' => $label['Name'],
                'confidence' => $label['Confidence'],
                'meta' => $label,
            ];

            if ($label['Confidence'] >= $this->labellingConfidence) {
                $tags[] = $label['Name'];
            }
        }
    }
}
