import os, io, glob
import magic
import exifread
from PIL import Image
from PIL.ExifTags import TAGS
from urllib.parse import urlparse, parse_qs

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

    '''
    image = Image.open(filename)
    image.verify()
    image_exif = image._getexif()

    if image_exif:
        for (key, val) in image_exif.items():
            tag_key = TAGS.get(key)
            if tag_key:
                data[tag_key] = val
    '''

    f = open(filename, 'rb')
    tags = exifread.process_file(f)
    for tag in tags.keys():
        if tag not in ('JPEGThumbnail', 'TIFFThumbnail', 'Filename', 'EXIF MakerNote'):
            data[tag] = str(tags[tag])

    return data
