FROM node:12

WORKDIR /var/www/web

RUN npm install yarn -g

# TODO: fix dependencies install
# RUN yarn install --pure-lockfile

# TODO: prepare production ready yarn build, if APP_ENV=prod
# CMD ["yarn", "encore", "production"]

CMD ["yarn", "encore", "dev", "--watch"]
