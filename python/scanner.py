import os, io, glob
import magic
from yaml import load, dump
try:
    from yaml import CLoader as Loader, CDumper as Dumper
except ImportError:
    from yaml import Loader, Dumper

settings_file = io.open(os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', 'settings.yml'), 'r')
settings = load(settings_file, Loader=Loader)

# TODO: check if exists & is valid

for folder in settings['folders']:
    print('Started scanning folder: %(folder)s' % {'folder': folder})
    folder_files = [f for f in glob.glob(folder + '/**/*', recursive=True) if os.path.isfile(f)]
    print('Found %(count)s files' % {'count': len(folder_files)})
    for file in folder_files:
        mime = magic.from_file(file, mime=True)
        print('File: %(file)s. Mime: %(mime)s' % {'file': file, 'mime': mime})
