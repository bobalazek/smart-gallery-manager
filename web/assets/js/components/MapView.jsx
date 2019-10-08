import React from 'react';
import axios from 'axios';
import moment from 'moment';
import { connect } from 'react-redux';
import L from 'leaflet';
import { withStyles } from '@material-ui/styles';
import AppNavigation from './AppNavigation';
import {
  setData,
  setDataBatch,
} from '../actions/index';

const styles = {
  root: {
    width: '100%',
    flexGrow: 1,
    padding: 16,
  },
  mapContainer: {
    width: '100%',
    display: 'flex',
    flexDirection: 'column',
    height: 'calc(100vh - 108px)',
  },
};

const mapStateToProps = state => {
  return {
    filesSummaryDatetime: state.filesSummaryDatetime,
  };
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

    this.state = {
      zoom: 8,
      position: [
        48.2082,
        16.3738
      ],
      data: [],
      meta: [],
    };

    this.mapRef = React.createRef();
  }

  componentDidMount() {
    this.props.setData('view', 'map');

    this.prepareMap();
  }

  componentDidUpdate(prevProps) {
    if (prevProps.filesSummaryDatetime !== this.props.filesSummaryDatetime) {
      const createdBefore = moment();

      return new Promise((resolve, reject) => {
        this.props.setData('isDataLoading', true);

        const query = this.parent.getFiltersQuery();
        const url = rootUrl + '/api/files/map' + query +
          '&created_before=' + createdBefore.format('YYYY-MM-DDTHH:mm:ss');

        return axios.get(url)
          .then(res => {
            this.setState({
              data: res.data.data,
              meta: res.data.data,
            }, () => {
              this.prepareMap();
            });
          })
          .finally(() => {
            this.props.setData('isDataLoading', false);
          });
      });
    }
  }

  prepareMap() {
    const {
      position,
      zoom,
      data,
      meta,
    } = this.state;

    if (
      this.mapRef &&
      this.mapRef.current
    ) {
      this.mapRef.current.style.height = window.innerHeight + 'px';

      if (!this.mapMarkersLayer) {
        this.mapMarkersLayer = L.featureGroup();
      }
      this.mapMarkersLayer.clearLayers();

      for (let i = 0; i < data.length; i++) {
        const coordinates = data[i].location.coordinates;
        const url = this.parent.parent.views.list.url +
          '?search=' + data[i].location.label;

        L.marker(coordinates)
          .bindPopup(
            '<a href="' + url + '">' +
              data[i].location.label + ' (count: ' + data[i].count + ')' +
            '</a><br />' +
            '(Lat: ' +  coordinates[0] + ', Lon: ' + coordinates[1] + ')'
          )
          .addTo(this.mapMarkersLayer);
      }

      if (!this.map) {
        this.map = L.map(this.mapRef.current, {
          layers: [
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
              attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors',
            }),
            this.mapMarkersLayer,
          ],
        }).setView(position, zoom);
      }

      if (data.length > 0) {
        this.map.fitBounds(this.mapMarkersLayer.getBounds());
      }
    }
  }

  render() {
    const {
      classes,
    } = this.props;

    return (
      <div className={classes.root}>
        <AppNavigation parent={this.parent} />
        <div className={classes.mapContainer}>
          <div ref={this.mapRef}></div>
        </div>
      </div>
    );
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(
  withStyles(styles)(MapView)
);
