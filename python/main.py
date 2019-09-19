import io, os, gzip
import rawpy
import simplejson as json
from PIL import Image
from bottle import Bottle, request, response, run
from helpers import get_file_info, get_file_faces

PORT = 8000

app = Bottle()

@app.route("/")
def index():
    response.content_type = "application/json"
    return json.dumps("Hello World!")

@app.route("/file-view")
def file_view():
    file = request.query.file
    response.content_type = "application/json"
    if not os.path.isfile(file):
        json.dumps({
            "data": {
                "error": "File does not exist",
            },
        })

    response.content_type = "image/jpeg"

    image_buffer = io.BytesIO()

    with rawpy.imread(file) as raw:
        rgb = raw.postprocess(use_camera_wb=True)
        im = Image.fromarray(rgb)
        im.save(image_buffer, format="jpeg")
        raw.close()

    bytes = image_buffer.getvalue()
    return bytes

@app.route("/file-info")
def file_info():
    response.content_type = "application/json"
    return json.dumps(
        get_file_info(request.query.file)
    )

@app.route("/file-faces")
def file_info():
    response.content_type = "application/json"
    return json.dumps(
        get_file_faces(request.query.file)
    )

if __name__ == "__main__":
    run(app, host="0.0.0.0", port=PORT, reloader=True, debug=True)
