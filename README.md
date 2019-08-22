# Smart Gallery Manager

## Development

* (optional) Create your own `.env` file - just copy the contents from `.env.example`
* Run: `docker-compose build`, and then `docker-compose up`
* Create a `settings.yml` file - just copy the contents from `settings.example.yml`
* Add your data sources inside `docker-compose.yml`
* Copy˛/duplicate `web/.env` into `web/.env.local` and set your custom variables there, like the `HERE_APP_ID` and `HERE_APP_CODE` constants
* Exec into the `php-fpm` container
  * Prepare the database by running `php bin/console doctrine:schema:update -f`
  * Scan and add files to the database by running `php bin/console app:files:scan`
* Visit: http://localhost:81 (or whichever port you set in `.env`)

### Frontend
https://symfony.com/doc/current/frontend/encore/simple-example.html

### Backend
https://symfony.com/doc/current/index.html

## Notes
* When using NFS mounts, follow this tutorial: https://www.digitalocean.com/community/tutorials/how-to-set-up-an-nfs-mount-on-ubuntu-16-04
  * Working config in `/etc/exports` on host server: `/srv 10.21.91.0/24(rw,sync,no_subtree_check,no_root_squash,insecure)`
  * Mounting via [Cockpit](https://cockpit-project.org/) -> storage:
    * Server address: `10.21.91.110`
    * Path on Server: `/srv`
    * Local Mount Point: `/nfs/srv`
    * (checked) Mount at boot
    * (unchecked) Mount read only
    * (checked) Custom mount options: `vers=3,nolock,rw`
  * Commands: `nfsstat -m`, `df -h`, ...
* The `.cr3` format is not yet supported by any open-source converter, so we need a workaround
  * Download [Adobe DNG](https://helpx.adobe.com/photoshop/digital-negative.html#downloads)
  * Create a separate folder for the converted files, like `Converted` (when your original folder is called `Raw`) in the same directory
  * Open `Adobe DNG`
    * Select your raw folder, for example `\\10.21.91.110\Server\Photography\Raw`
    * Tick `Include images contained within subfolders subdirectories` and `Skip source image if destination image already exists`
    * Select your destination folder, for example `\\10.21.91.110\Server\Photography\Converted`
    * Tick `Preserve subfolders`
    * Click `Convert`
