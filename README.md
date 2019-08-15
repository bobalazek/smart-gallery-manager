# Smart Gallery Manager

## Development

* Run: `docker-compose build`, and then `docker-compose up`
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
