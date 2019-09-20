FROM python:3

WORKDIR /usr/src/app

RUN apt-get update
RUN apt-get install libmagickwand-dev ufraw -y

RUN pip install --upgrade pip
RUN pip install bottle
RUN pip install Wand
RUN pip install pyyaml
RUN pip install python-magic
RUN pip install exif
RUN pip install exifread
RUN pip install rawpy
RUN pip install Pillow
RUN pip install simplejson
RUN pip install numpy
RUN pip install mtcnn
RUN pip install tensorflow==1.13.2
RUN pip install opencv-contrib-python==4.1.1.26

COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

EXPOSE 8000

CMD ["python", "-u", "python/main.py"]
