import React from 'react';
import { connect } from 'react-redux';
import L from 'leaflet';
import { withStyles } from '@material-ui/styles';
import {
  setData,
  setDataBatch,
} from '../actions/index';

const styles = {
  root: {
    width: '100%',
    display: 'flex',
    flexDirection: 'column',
    height: '100vh',
  },
};

function mapDispatchToProps(dispatch) {
  return {
    setData: (type, data) => dispatch(setData(type, data)),
  };
}

class MapView extends React.Component {
  constructor(props) {
    super(props);

    this.parent = this.props.parent;

    this.mapRef = React.createRef();
  }

  componentDidMount() {
    this.props.setData('view', 'map');

    this.prepareMap();
    this.parent.fetchFilesSummary();
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

export default connect(null, mapDispatchToProps)(
  withStyles(styles)(MapView)
);
