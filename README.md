# Smart Gallery Manager

## Development

* (optional) Set stuff in `.env` (nginx ports only for now)
* Run: `docker-compose build`, and then `docker-compose up`
* Add your data sources inside `docker-compose.yml`, and then in `settings.yml`
* Prepare the database by running `php bin/console doctrine:schema:update -f` inside the `sgm_phpfpm` container
* (optional) To add the images via PHP, also run `php bin/console app:files:scan` in the same container
* Visit: http://localhost:81 (or whichever port you set in `.env`)

### Frontend
* https://symfony.com/doc/current/frontend/encore/simple-example.html

```
# compile assets once
$ yarn encore dev

# or, recompile assets automatically when files change
$ yarn encore dev --watch

# on deploy, create production build
$ yarn encore production
```

### Backend
* https://symfony.com/doc/current/index.html

## Notes
* For .heic/.heif, the [libheif](https://github.com/strukturag/libheif) library is required
* When using NFS mounts, follow this tutorial: https://www.digitalocean.com/community/tutorials/how-to-set-up-an-nfs-mount-on-ubuntu-16-04
  * Working config in `/etc/exports` on host server: `/srv 10.21.91.0/24(rw,sync,no_subtree_check,no_root_squash,insecure)`
  * Mounting via cockpit -> storage:
    * Server address: `10.21.91.110`
    * Path on Server: `/srv`
    * Local Mount Point: `/nfs/srv`
    * (checked) Mount at boot
    * (unchecked) Mount read only
    * (checked) Custom mount options: `vers=3,nolock,rw`
  * Commands: `nfsstat -m`, `df -h`, ...
