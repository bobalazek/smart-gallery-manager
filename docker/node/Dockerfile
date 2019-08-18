FROM node:12

WORKDIR /var/www/web

RUN npm install yarn -g

RUN yarn install

CMD ["yarn", "encore", "dev", "--watch"]
