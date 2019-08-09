import React from 'react';
import ReactDOM from 'react-dom';
import App from './components/App';
import * as serviceWorker from './serviceWorker';

import '../css/app.css';
import 'react-virtualized/styles.css';

ReactDOM.render(<App />, document.getElementById('root'));

serviceWorker.unregister();
