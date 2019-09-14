# Smart Gallery Manager

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
* It uses webpack as the bundler, via [Symfony Webpack Encore](https://symfony.com/doc/current/frontend/encore/installation.html)
* There is also a python micro service that is, for now, only used for `.dng` files. It reads the EXIF data and converts it to `.jpg`


## Setup & development

* (optional) Create your own `.env` file, if you want to override the docker ports - just copy the contents from `.env.example`
* Create a `settings.yml` file and add your file folders in - just copy the contents from `settings.example.yml`
* Create a `docker-compose.override.yml` file and set your custom volumes there - just copy the contents from `docker-compose.override.example.yml`
* Duplicate the `web/.env` into `web/.env.local` and set your the values for your custom variables there - particularly those, inside the `Project` block
* Run: `docker-compose build`
* (temporary fix) The `sgm_node` container doesn't yet correctly work, so after building the containers, you'll need to manually install the dependencies via the host device with: `cd web && yarn install && cd ..`
* Run: `docker-compose up`
* Run: `docker exec -i sgm_php_fpm composer install`
* Run: `docker exec -i sgm_php_fpm php bin/console doctrine:schema:update -f`
* Go to http://localhost:81/dashboard (or whichever port you set), to start scanning for files

### Notes

* (temporary fix) If in development mode, when adding new dependencies to package.json (via yarn), stop the `sgm_node` container first, run the `yarn add ...` command and restart the container again


## Credits

* Icon - https://pixabay.com/vectors/image-pictures-icon-photo-1271454/


## License

Smart Gallery Manager is licensed under the MIT license.
