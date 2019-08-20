# Smart Gallery Manager

## Development

* (optional) Create your own `.env` - just copy the contents from `.env.example`
* Run: `docker-compose build`, and then `docker-compose up`
* Add your data sources inside `docker-compose.yml`, and then in `settings.yml`
* Prepare the database by running `php bin/console doctrine:schema:update -f` inside the `sgm_phpfpm` container
* (optional) To add the images via PHP, also run `php bin/console app:files:scan` in the same container
* Visit: http://localhost:81 (or whichever port you set in `.env`)

### Frontend
https://symfony.com/doc/current/frontend/encore/simple-example.html

```
# compile assets once
$ yarn encore dev

# or, recompile assets automatically when files change
$ yarn encore dev --watch

# on deploy, create production build
$ yarn encore production
```

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
