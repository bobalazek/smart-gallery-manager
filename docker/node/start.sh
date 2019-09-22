#!/bin/bash -x

cd /var/www/web

if [ ! -d "node_modules" ];
then
  yarn install
fi

# TODO: not yet fully working - for some reason the argument isn't set
if [ "$0" == "prod" ]
then
  yarn encore production --progress
  while true; do sleep 3600; done
else
  yarn encore dev --watch
fi
