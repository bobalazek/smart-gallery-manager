import json
from http.server import BaseHTTPRequestHandler
from router import do_action

# https://medium.com/@andrewklatzke/creating-a-python3-webserver-from-the-ground-up-4ff8933ecb96

class Server(BaseHTTPRequestHandler):
    def do_HEAD(self):
        return

    def do_GET(self):
        self.respond()

    def do_POST(self):
        return

    def handle_http(self):
        status = 200
        content_type = "application/json"
        response_content = do_action(self.path)

        self.send_response(status)
        self.send_header("Content-type", content_type)
        self.end_headers()

        return bytes(json.dumps(response_content), "UTF-8")

    def respond(self):
        content = self.handle_http()
        self.wfile.write(content)
