# Smart Gallery Manager

A locally hosted smart gallery manager.


## Summary
* It runs on Nginx with PHP-FPM
* The main app (API & admin) is written in Symfony
* It runs MySQL as the database
* The frontend is written in React
* It uses webpack as the bundler, via [Symfony Webpack Encore](https://symfony.com/doc/current/frontend/encore/installation.html)
* There is also a python micro service, that is, for now, only used for `.dng` files. It reads the EXIF data and converts it to `.jpg`


## Setup & development

* (optional) Create your own `.env` file, if you want to override the docker ports - just copy the contents from `.env.example`
* Create a `settings.yml` file and add your image folders in - just copy the contents from `settings.example.yml`
* Create a `docker-compose.override.yml` file and set your custom volumes there - just copy the contents from `docker-compose.override.example.yml`
* Duplicate the `web/.env` into `web/.env.local` and set your the values for your custom variables there - particularly those, inside the `### Project` block
* Run `docker-compose up`
* Exec into the `php-fpm` container
  * Prepare the database by running `php bin/console doctrine:schema:update -f`
  * Scan and add files to the database by running `php bin/console app:files:scan [-u|--update-existing-entries][-a|--actions "meta,cache,geocode,label"][-f|--folder "/path/to/the/folder"]`
* Visit: http://localhost:81 (or whichever port you set in `.env`)
* You are ready. Start developing!


## TODO

* [Backend] Write more tests
* [Backend] Add a dashboard
* [Backend] Add a job queue to trigger actions from there, instead of a CLI
* [Frontend] Start tackling performance issues - mostly happens when there are tens of thousands of files added & you are scrolling down too fast
* [Frontend] More intuitive sidebar
* [Python] There is a bug, where the `.dng` formats return the wrong sizes
