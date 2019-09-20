import os, io, glob
import magic
import exifread
import cv2
import numpy as np
from PIL import Image
from PIL.ExifTags import TAGS
from mtcnn.mtcnn import MTCNN
from yaml import load, dump
try:
    from yaml import CLoader as Loader, CDumper as Dumper
except ImportError:
    from yaml import Loader, Dumper

def get_file_info(filename):
    result = dict()

    if not os.path.isfile(filename):
        result['error'] = 'File does not exist'
        return result

    result['data'] = dict()
    result['data']['mime'] = magic.from_file(filename, mime=True)
    result['data']['exif'] = get_exif(filename)

    return result

def get_file_faces(filename=None, bytes=None):
    result = dict()

    if filename is None and bytes is None:
        result['error'] = 'You need to either set the filename or the bytes'
        return result

    if filename is not None and not os.path.isfile(filename):
        result['error'] = 'File does not exist'
        return result

    try:
        if filename is not None:
            img = cv2.imread(filename)
            img = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
        else:
            # https://stackoverflow.com/questions/17170752/python-opencv-load-image-from-byte-string
            img_array = np.fromstring(bytes, np.uint8)
            img = cv2.imdecode(img_array, cv2.IMREAD_COLOR)

        height, width, channels = img.shape

        detector = MTCNN()
        faces = detector.detect_faces(img)

        data = []

        for face in faces:
            item = face
            print(item)
            item['box'] = [
                item['box'][0] / width,
                item['box'][1] / height,
                item['box'][2] / width,
                item['box'][3] / height,
            ]
            item['keypoints'] = {
                'left_eye': [
                    item['keypoints']['left_eye'][0] / width,
                    item['keypoints']['left_eye'][1] / height,
                ],
                'right_eye': [
                    item['keypoints']['right_eye'][0] / width,
                    item['keypoints']['right_eye'][1] / height,
                ],
                'nose': [
                    item['keypoints']['nose'][0] / width,
                    item['keypoints']['nose'][1] / height,
                ],
                'mouth_left': [
                    item['keypoints']['mouth_left'][0] / width,
                    item['keypoints']['mouth_left'][1] / height,
                ],
                'mouth_right': [
                    item['keypoints']['mouth_right'][0] / width,
                    item['keypoints']['mouth_right'][1] / height,
                ],
            }
            data.append(item)

        result['data'] = data
    except:
        result['error'] = 'Something went wrong'

    return result

def get_exif(filename):
    data = {}

    # https://github.com/ianare/exif-py/blob/ea5af101ff0a4c478cc05b1a193372d4b33218d8/exifread/tags/exif.py#L123
    orientation = {
        'Horizontal (normal)': 1,
        'Mirrored horizontal': 2,
        'Rotated 180': 3,
        'Mirrored vertical': 4,
        'Mirrored horizontal then rotated 90 CCW': 5,
        'Rotated 90 CW': 6,
        'Mirrored horizontal then rotated 90 CW': 7,
        'Rotated 90 CCW': 8,
    }

    file = open(filename, 'rb')
    tags = exifread.process_file(file)
    for tag in tags.keys():
        if tag not in ('JPEGThumbnail', 'TIFFThumbnail', 'Filename', 'EXIF MakerNote'):
            if tag == 'Image Orientation':
                try:
                    data[tag] = orientation[str(tags[tag])]
                except KeyError:
                    data[tag] = 1
            else:
                data[tag] = str(tags[tag])

    file.close()

    return data

def get_folders():
    data = []

    project_root = os.path.join(os.path.dirname(os.path.realpath(__file__)), '..')
    settings_file = io.open(os.path.join(project_root, 'settings.yml'), 'r')

    try:
        settings = load(settings_file, Loader=Loader)
        if settings['folders']:
            data = settings['folders']
    except:
        data = []

    return data
