# Smart Gallery Manager

## Development

* (optional) Create your own `.env` file - just copy the contents from `.env.example`
* Run: `docker-compose build`, and then `docker-compose up`
* Create a `settings.yml` file - just copy the contents from `settings.example.yml`
* Add your data sources inside `docker-compose.yml`
* Copy/duplicate `web/.env` into `web/.env.local` and set your custom variables there
  * If you want to use geolocation, you'll probably want to set the `HERE_APP_ID` and `HERE_APP_CODE` constants
  * If you want to use labeling, you'll probably want to set the `AWS_KEY` and `AWS_SECRET` constants
* Exec into the `php-fpm` container
  * Prepare the database by running `php bin/console doctrine:schema:update -f`
  * Scan and add files to the database by running `php bin/console app:files:scan [-u|--update-existing-entries][-a|--actions "meta,cache,geocode,label"][-f|--folder "/path/to/the/folder"]`
* Visit: http://localhost:81 (or whichever port you set in `.env`)

### Frontend
https://symfony.com/doc/current/frontend/encore/simple-example.html

### Backend
https://symfony.com/doc/current/index.html
