<?php

namespace App\Manager\FileManagerTraits;

use App\Entity\File;
use App\Entity\ImageLocation;

trait GeocodeTrait {
    private $_geocodeLocation = [];
    private $_geocodeCache = [];

    /**
     * Geocodes the location
     *
     * @param File $file
     * @param bool $skipFetchIfAlreadyExists
     */
    public function geocode(File $file, $skipFetchIfAlreadyExists = true)
    {
        if (!$this->geocodingEnabled) {
            throw new \Exception(
                'The geocoding is disabled.'
            );
        }

        $this->_geocodeLocation = [
            'address' => [
                'label' => null,
                'street' => null,
                'house_number' => null,
                'postal_code' => null,
                'city' => null,
                'district' => null,
                'state' => null,
                'country' => null,
            ],
            'coordinates' => [
                'latitude' => null,
                'longitude' => null,
            ],
        ];

        if ($this->geocodingService === 'here') {
            $this->_geocodeHere($file, $skipFetchIfAlreadyExists);
        } elseif ($this->geocodingService === 'osm') {
            $this->_geocodeOsm($file, $skipFetchIfAlreadyExists);
        } else {
            throw new \Exception(
                sprintf(
                    'The specified geocoding service "%s" does not exist.',
                    $this->geocodingService
                )
            );
        }

        $file->setLocation($this->_geocodeLocation);

        if (
            !empty($this->_geocodeLocation['coordinates']['latitude']) &&
            !empty($this->_geocodeLocation['coordinates']['longitude'])
        ) {
            $imageLocation = $file->getImageLocation();
            if (!$imageLocation) {
                $imageLocation = new ImageLocation();
                $imageLocation->setCreatedAt(new \DateTime());
            }
            $imageLocation
                ->setSource($this->geocodingService)
                ->setLabel($this->_geocodeLocation['address']['label'])
                ->setStreet($this->_geocodeLocation['address']['street'])
                ->setHouseNumber($this->_geocodeLocation['address']['house_number'])
                ->setPostalCode($this->_geocodeLocation['address']['postal_code'])
                ->setTown($this->_geocodeLocation['address']['city'])
                ->setRegion($this->_geocodeLocation['address']['district'])
                ->setState($this->_geocodeLocation['address']['state'])
                ->setCountry($this->_geocodeLocation['address']['country'])
                ->setLatitude($this->_geocodeLocation['coordinates']['latitude'])
                ->setLongitude($this->_geocodeLocation['coordinates']['longitude'])
                ->setModifiedAt(new \DateTime())
            ;
            $file->setImageLocation($imageLocation);
        }

        return true;
    }

    /**
     * @param string|null $service
     *
     * @return string
     */
    public function getGeocodeFileName($service = null)
    {
        if ($service === null) {
            $service = $this->geocodingService;
        }

        if ($service === 'osm') {
            return 'osm_geocode.json';
        } elseif ($service === 'here') {
            return 'here_geocode.json';
        }

        throw new \Exception(sprintf(
            'The geocoding service "%s" does not exist.',
            $service
        ));
    }

    /**
     * Geocodes the location via OSM
     * Note: At the moment, I can't really get it working. After the first request,
     *   I always get "Failed sending data to peer ...". Figure out what the issue is.
     *
     * @param File $file
     * @param bool $skipFetchIfAlreadyExists
     */
    private function _geocodeOsm(File $file, $skipFetchIfAlreadyExists)
    {
        $fileMeta = $file->getMeta();

        $coordinates = [
            'lat' => $fileMeta['geolocation']['latitude'],
            'lon' => $fileMeta['geolocation']['longitude'],
        ];

        $cacheHash = 'osm.' . sha1(json_encode($coordinates));

        $path = $this->getFileDataDir($file) . '/' . $this->getGeocodeFileName('osm');

        $alreadyExists = $skipFetchIfAlreadyExists && file_exists($path);

        if ($alreadyExists) {
            $this->_geocodeCache[$cacheHash] = json_decode(
                file_get_contents($path),
                true
            );
        }

        if (!isset($this->_geocodeCache[$cacheHash])) {
            if ($this->logger) {
                $this->logger->debug('Geocoding data does not exist. Feching from service ...');
            }

            $url = 'https://nominatim.openstreetmap.org/reverse';
            $response = $this->httpClient->request('GET', $url, [
                'query' => array_merge([
                    'format' => 'geocodejson',
                ], $coordinates),
            ]);
            $content = json_decode($response->getContent(), true);
            if (isset($content['error'])) {
                throw new \Exception($content['error']['message']);

                return false;
            }

            $this->_geocodeCache[$cacheHash] = $content;
        }

        $geocodeData = $this->_geocodeCache[$cacheHash];

        if (!$alreadyExists) {
            file_put_contents($path, json_encode($geocodeData));
        }

        $features = $geocodeData['features'];
        if (count($features) === 0) {
            throw new \Exception('Could not find any geolocation data for those coordinates.');
        }

        $locationData = $features[0]['properties']['geocoding'];

        $this->_geocodeLocation['address']['label'] = $locationData['label'] ?? null;
        $this->_geocodeLocation['address']['street'] = $locationData['street'] ?? null;
        $this->_geocodeLocation['address']['house_number'] = $locationData['housenumber'] ?? null;
        $this->_geocodeLocation['address']['postal_code'] = $locationData['postcode'] ?? null;
        $this->_geocodeLocation['address']['city'] = $locationData['city'] ?? null;
        $this->_geocodeLocation['address']['state'] = $locationData['state'] ?? null;
        $this->_geocodeLocation['address']['country'] = $locationData['country'] ?? null;
        $this->_geocodeLocation['coordinates']['latitude'] = $fileMeta['geolocation']['latitude'];
        $this->_geocodeLocation['coordinates']['longitude'] = $fileMeta['geolocation']['longitude'];
    }

    /**
     * geocodes the location via HERE
     *
     * @param File $file
     * @param bool $skipFetchIfAlreadyExists
     */
    private function _geocodeHere(File $file, $skipFetchIfAlreadyExists)
    {
        if (
            $this->hereApiCredentials['app_id'] === '' ||
            $this->hereApiCredentials['app_code'] === ''
        ) {
            throw new \Exception('HERE credentials are not set. Could not geocode the file.');
        }

        $fileMeta = $file->getMeta();

        if (
            empty($fileMeta['geolocation']['latitude']) ||
            empty($fileMeta['geolocation']['longitude'])
        ) {
            throw new \Exception('This file has no geolocation (latitude & longitude) data.');
        }

        $coordinates = [
            'lat' => $fileMeta['geolocation']['latitude'],
            'lon' => $fileMeta['geolocation']['longitude'],
        ];

        $cacheHash = 'here.' . sha1(json_encode($coordinates));

        $path = $this->getFileDataDir($file) . '/' . $this->getGeocodeFileName('here');

        $alreadyExists = $skipFetchIfAlreadyExists && file_exists($path);

        if ($alreadyExists) {
            $this->_geocodeCache[$cacheHash] = json_decode(
                file_get_contents($path),
                true
            );
        }

        if (!isset($this->_geocodeCache[$cacheHash])) {
            if ($this->logger) {
                $this->logger->debug('Geocoding data does not exist. Feching from service ...');
            }

            $url = 'https://reverse.geocoder.api.here.com/6.2/reversegeocode.json';
            $response = $this->httpClient->request('GET', $url, [
                'query' => [
                    'app_id' => $this->hereApiCredentials['app_id'],
                    'app_code' => $this->hereApiCredentials['app_code'],
                    'mode' => 'retrieveAll',
                    'maxresults' => $this->hereReverseGeocoderMaxResults,
                    'gen' => '9',
                    'prox' => (
                        $fileMeta['geolocation']['latitude'] . ',' .
                        $fileMeta['geolocation']['longitude'] . ',' .
                        $this->hereReverseGeocoderRadius
                    ),
                ],
            ]);
            $content = json_decode($response->getContent(), true);
            if (isset($content['error'])) {
                throw new \Exception($content['error']['message']);

                return false;
            }

            $this->_geocodeCache[$cacheHash] = $content;
        }

        $geocodeData = $this->_geocodeCache[$cacheHash];

        if (!$alreadyExists) {
            file_put_contents($path, json_encode($geocodeData));
        }

        $view = $geocodeData['Response']['View'];

        if (count($view) === 0) {
            throw new \Exception('Could not find any geolocation data for those coordinates.');
        }

        $results = $view[0]['Result'];
        $locationData = $results[0]['Location']['Address'];

        foreach ($results as $result) {
            // The first one is usually "district", but we may want to set a more detailed location.
            if ($result['MatchLevel'] === 'houseNumber') {
                $locationData = $result['Location']['Address'];
                break;
            }
        }

        $country = $locationData['Country'] ?? null;
        if (isset($locationData['AdditionalData'])) {
            foreach ($locationData['AdditionalData'] as $additionalData) {
                if ($additionalData['key'] === 'CountryName') {
                    $country = $additionalData['value'];
                    break;
                }
            }
        }

        $this->_geocodeLocation['address']['label'] = $locationData['Label'] ?? null;
        $this->_geocodeLocation['address']['street'] = $locationData['Street'] ?? null;
        $this->_geocodeLocation['address']['house_number'] = $locationData['HouseNumber'] ?? null;
        $this->_geocodeLocation['address']['postal_code'] = $locationData['PostalCode'] ?? null;
        $this->_geocodeLocation['address']['city'] = $locationData['City'] ?? null;
        $this->_geocodeLocation['address']['district'] = $locationData['District'] ?? null;
        $this->_geocodeLocation['address']['state'] = $locationData['State'] ?? null;
        $this->_geocodeLocation['address']['country'] = $country;
        $this->_geocodeLocation['coordinates']['latitude'] = $fileMeta['geolocation']['latitude'];
        $this->_geocodeLocation['coordinates']['longitude'] = $fileMeta['geolocation']['longitude'];
    }
}
