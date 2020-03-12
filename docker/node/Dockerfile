FROM node:12

WORKDIR /var/www/web

COPY start.sh /usr/local/bin/
RUN sed -i 's/\r$//' /usr/local/bin/start.sh
RUN chmod 755 /usr/local/bin/start.sh

ARG NODE_ENV
ENV NODE_ENV ${NODE_ENV}

# TODO: not yet fully working - at least not the environment
CMD ["/bin/bash", "-c", "/usr/local/bin/start.sh $NODE_ENV"]
