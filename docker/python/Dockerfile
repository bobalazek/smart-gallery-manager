FROM python:3

WORKDIR /usr/src/app

RUN apt-get install libmagickwand-dev

RUN pip install Wand
RUN pip install pyyaml
RUN pip install python-magic
RUN pip install exif
RUN pip install Pillow
RUN pip install simplejson

COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

EXPOSE 8000

CMD ["python", "-u", "python/main.py"]
