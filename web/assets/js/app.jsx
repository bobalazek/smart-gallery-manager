import React from 'react';
import ReactDOM from 'react-dom';
import { Provider } from 'react-redux';
import L from 'leaflet';
import store from './store/index';
import App from './components/App';
import * as serviceWorker from './serviceWorker';

// Leaflet
import 'leaflet/dist/leaflet.css';
L.Marker.prototype.options.icon = L.icon({
  iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.5.1/images/marker-icon.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.5.1/images/marker-shadow.png',
});

import '../css/app.css';

ReactDOM.render(
  <Provider store={store}>
    <App />
  </Provider>,
  document.getElementById('root')
);

serviceWorker.unregister();
