from bottle import Bottle, request, response, run
import simplejson as json
from processor import get_file_info

PORT = 8000

app = Bottle()

@app.route("/")
def index():
    response.content_type = "application/json"
    return json.dumps("Hello World!")

@app.route("/file-info")
def index():
    response.content_type = "application/json"
    return json.dumps({
        "data": get_file_info(request.query.file),
    })

if __name__ == "__main__":
    run(app, host="0.0.0.0", port=PORT, reloader=True, debug=True)
