FROM python:3

WORKDIR /usr/src/app

RUN apt-get install libmagickwand-dev

COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

CMD ["python"]
