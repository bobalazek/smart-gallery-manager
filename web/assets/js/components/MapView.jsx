import React from 'react';
import L from 'leaflet';
import { withStyles } from '@material-ui/styles';

const styles = {
  root: {
    width: '100%',
    display: 'flex',
    flexDirection: 'column',
    height: '100vh',
  },
};

class MapView extends React.Component {
  constructor(props) {
    super(props);

    this.mapRef = React.createRef();
  }

  componentDidMount() {
    this.prepareMap();
  }

  prepareMap() {
    const position = [
      48.2082,
      16.3738
    ];

    if (
      this.mapRef &&
      this.mapRef.current
    ) {
      this.mapRef.current.style.height = window.innerHeight + 'px';

      const map = L.map(this.mapRef.current, {
        layers: [
          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors',
          }),
        ],
      }).setView(position, 8);
    }
  }

  render() {
    const {
      classes,
    } = this.props;

    return (
      <div className={classes.root}>
        <div ref={this.mapRef}></div>
      </div>
    );
  }
}

export default withStyles(styles)(MapView);
