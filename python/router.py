import os, io, glob
import magic
from PIL import Image
from PIL.ExifTags import TAGS
from urllib.parse import urlparse, parse_qs

def do_action(path):
    result = dict()

    parsed_path = urlparse(path)
    parsed_query = parse_qs(parsed_path.query, keep_blank_values=True)

    result['meta'] = dict()
    result['meta']['query'] = parsed_query # TODO: convert them to key => val. Now it's key => [val]
    result['meta']['path'] = parsed_path.path

    result['data'] = dict()

    if result['meta']['path'] == '/info' and 'file' in parsed_query:
        file = next(iter(parsed_query['file']))
        result['data'] = get_file_info(file)

    return result

def get_file_info(filename):
    result = dict()

    if not os.path.isfile(filename):
        result['error'] = 'File does not exist'
        return result

    image = Image.open(filename)
    image.verify()
    image_exif = image._getexif()

    result['mime'] = magic.from_file(filename, mime=True)
    result['exif'] = get_exif(image_exif)

    return result

def get_exif(exif):
    data = {}
# TODO
#    for (key, val) in exif.items():
#        data[TAGS.get(key)] = val

    return data
