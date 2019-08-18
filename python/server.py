import simplejson as json
from http.server import BaseHTTPRequestHandler
from processor import do_action

# https://medium.com/@andrewklatzke/creating-a-python3-webserver-from-the-ground-up-4ff8933ecb96

class Server(BaseHTTPRequestHandler):
    def do_HEAD(self):
        return

    def do_GET(self):
        self.respond()

    def do_POST(self):
        return

    def handle_http(self):
        action_response = do_action(self.path)

        self.send_response(action_response["status"])
        self.send_header("Content-type", action_response["type"])
        self.end_headers()

        return bytes(json.dumps(action_response["content"]), "UTF-8")

    def respond(self):
        content = self.handle_http()
        self.wfile.write(content)
