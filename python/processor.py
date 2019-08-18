import os, io, glob
import magic
from PIL import Image
from PIL.ExifTags import TAGS
from urllib.parse import urlparse, parse_qs

def do_action(path):
    response = {
        "status": 200,
        "type": "application/json",
        "content": {
            "data": {},
            "meta": {},
        },
    }

    parsed_path = urlparse(path)
    parsed_query = parse_qs(parsed_path.query, keep_blank_values=True)

    response["content"]["meta"] = {
        "query": parsed_query,
        "path": parsed_path.path,
    }

    if response["content"]["meta"]["path"] == "/info" and "file" in parsed_query:
        file = next(iter(parsed_query["file"]))
        response["content"]["data"] = get_file_info(file)

    return response

def get_file_info(filename):
    result = {}

    if not os.path.isfile(filename):
        result["error"] = "File does not exist"
        return result

    image = Image.open(filename)
    image.verify()
    image_exif = image._getexif()

    result["mime"] = magic.from_file(filename, mime=True)
    result["exif"] = get_exif(image_exif)

    return result

def get_exif(exif):
    data = {}

    if exif:
        for (key, val) in exif.items():
            tag_key = TAGS.get(key)
            if tag_key:
                data[tag_key] = val

    return data
