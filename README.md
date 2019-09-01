# Smart Gallery Manager

Your personal locally hosted smart gallery manager.

> Please note that this is a personal project, that is still in the pre-alpha stage, so it should only be used for development purposes.


## Features

* Gallery of images [Note: Currently it will scan and add ALL files inside the folders you specify, so add folders that only contain images]
* Filter the images by type, date taken or created & tags (if labelling via [Amazon Rekognition](https://aws.amazon.com/rekognition) is enabled)
* Search images by path, location (if reverse geolocation via [HERE Geocoding](https://www.here.com/products/location-based-services/geocoding-tools) is enabled), tags, extension and more
* Detailed modal view with information about the image

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
* Create a `settings.yml` file and add your image folders in - just copy the contents from `settings.example.yml`
* Create a `docker-compose.override.yml` file and set your custom volumes there - just copy the contents from `docker-compose.override.example.yml`
* Duplicate the `web/.env` into `web/.env.local` and set your the values for your custom variables there - particularly those, inside the `Project` block
* Run `docker-compose up`
* Exec into the `php-fpm` container
  * Prepare the database by running `php bin/console doctrine:schema:update -f`
  * Scan and add files to the database by running `php bin/console app:files:scan [-u|--update-existing-entries][-a|--actions "meta,cache,geocode,label"][-f|--folder "/path/to/the/folder"]`
* Visit: http://localhost:81 (or whichever port you have set in `.env`)
* You are ready. Start developing!


## TODO

* [Backend] Support for videos, audio & maybe also PDFs OR ignore files that are not images, videos or audio clips
* [Backend] Write more tests
* [Backend] Add a dashboard
* [Backend] Add a job queue to trigger actions from there, instead of a CLI
* [Backend] Ability to choose the geocoding service - OSM is implemented, but does not work correctly yet
* [Frontend] Start tackling performance issues - mostly happens when there are tens of thousands of files added & you are scrolling down too fast
* [Frontend] More intuitive sidebar
* [Frontend] Add a "Map view", where we could use the geolocation data from existing images and show it on the map
* [Frontend] Add a separate view, where you can see "What happened on day XY"
* [Frontend] Search autocomplete
* [Python] There is a bug, where the `.dng` formats return the wrong sizes & orientation
* [Design] Make a nicer 404 image


## License

Smart Gallery Manager is licensed under the MIT license.
