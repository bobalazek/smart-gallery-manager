import time
from http.server import HTTPServer
from server import Server

PORT_NUMBER = 8000

if __name__ == '__main__':
    httpd = HTTPServer(('', PORT_NUMBER), Server)
    print(time.asctime(), 'Server UP - Port:%s' % (PORT_NUMBER))
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        pass
    httpd.server_close()
    print(time.asctime(), 'Server DOWN - Port:%s' % (PORT_NUMBER))
