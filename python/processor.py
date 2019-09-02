import os, io, glob
import magic
import exifread
from PIL import Image
from PIL.ExifTags import TAGS

def get_file_info(filename):
    result = {}

    if not os.path.isfile(filename):
        result["error"] = "File does not exist"
        return result

    result["mime"] = magic.from_file(filename, mime=True)
    result["exif"] = get_exif(filename)

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
