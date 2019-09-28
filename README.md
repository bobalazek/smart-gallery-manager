# Smart Gallery Manager &middot; [![GitHub license](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/bobalazek/smart-gallery-manager/blob/master/LICENSE) [![Build Status](https://travis-ci.org/bobalazek/smart-gallery-manager.svg?branch=master)](https://travis-ci.org/bobalazek/smart-gallery-manager)

Your personal locally hosted smart gallery manager.

View this [Trello Board](https://trello.com/b/WLSAoeAg/smart-gallery-manager) for the TODO items.

> Please note that this is a personal project, that is still in the pre-alpha stage, so it should only be used for development purposes.


## Features

* Gallery of images [Note: Currently it will scan and add ALL files inside the folders you specify, so add folders that only contain images]
* Filter the images by type, date taken or created & tags (if labelling via [Amazon Rekognition](https://aws.amazon.com/rekognition) is enabled)
* Search images by path, location (if reverse geolocation via [HERE Geocoding](https://www.here.com/products/location-based-services/geocoding-tools) is enabled), tags, extension and more
* Detailed modal view with information about the image


## Requirements

* Processor: dual core or higher
* Memory: 1 GB RAM or more

> May work on lower specs, but it has to be tested first.


## Screenshots

![Preview 1](/docs/images/preview-1.jpg)
![Preview 2](/docs/images/preview-2.jpg)
![Preview 3](/docs/images/preview-3.jpg)


## Summary

* It runs on Nginx with PHP-FPM
* The main app (API & admin) is written in Symfony with MySQL as the database
* Frontend is written in React
* It uses webpack as the bundler via [Symfony Webpack Encore](https://symfony.com/doc/current/frontend/encore/installation.html)
* There is also a python micro service for converting & reading exif data from `.dng` files and for face detection on images


## Setup

* Prepare the environment
  * Create your own `.env` file (copy the contents from `.env.example`)
    * All the variables in `.env`, will automatically be forwarded to the `sgm_php_fpm`,  `sgm_node` & the `sgm_python` container.
    * This is the most convenient way to set the web app variables all in one place. Alternatively you can duplicate the `web/.env` into `web/.env.local` and set your the values for your custom variables there - particularly those, inside the `Project` block.
  * Create a `docker-compose.override.yml` file and set your custom volumes there - just copy the contents from `docker-compose.override.example.yml`
  * *(optional)* Create a `settings.yml` file and add your file folders in - just copy the contents from `settings.example.yml`
    * Those will be your default folders that will be used when manually triggering the files scan with `docker exec -i sgm_php_fpm php bin/console app:files:scan` and the files that will per default always show up in the dashboard, when triggering the scan via the web UI
* Build the app
  * Run: `docker-compose build`
  * Run: `docker-compose up`
    * This may take a while, especially for the first time, as the `sgm_node` container will install all the dependencies.
  * Run: `docker exec -i sgm_php_fpm composer install`
  * Run: `docker exec -i sgm_php_fpm php bin/console doctrine:schema:update -f`
* Start scanning for new files
  * Go to http://localhost:81/dashboard (or whichever port you set in `.env`) and start scanning for files


## Tests

* To run the tests, you simply execute: `docker exec -i sgm_php_fpm php bin/phpunit`


## Credits

* Icon - https://pixabay.com/vectors/image-pictures-icon-photo-1271454/


## License

Smart Gallery Manager is licensed under the MIT license.
