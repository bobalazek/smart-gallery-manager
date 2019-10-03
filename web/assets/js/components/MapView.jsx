import React from 'react';
import axios from 'axios';
import moment from 'moment';
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

const mapStateToProps = state => {
  return {
    isLoading: state.isLoading,
    isLoaded: state.isLoaded,
    orderBy: state.orderBy,
    orderByDirection: state.orderByDirection,
    search: state.search,
    selectedType: state.selectedType,
    selectedYear: state.selectedYear,
    selectedYearMonth: state.selectedYearMonth,
    selectedDate: state.selectedDate,
    selectedCountry: state.selectedCountry,
    selectedCity: state.selectedCity,
    selectedLabel: state.selectedLabel,
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
    this.fetchFilesSummary();
  }

  componentDidUpdate(prevProps) {
    if (
      prevProps.selectedType !== this.props.selectedType ||
      prevProps.selectedYear !== this.props.selectedYear ||
      prevProps.selectedYearMonth !== this.props.selectedYearMonth ||
      prevProps.selectedDate !== this.props.selectedDate ||
      prevProps.selectedCountry !== this.props.selectedCountry ||
      prevProps.selectedCity !== this.props.selectedCity ||
      prevProps.selectedLabel !== this.props.selectedLabel
    ) {
      this.fetchFilesSummary();
    }
  }

  fetchFilesSummary() {
    this.parent.fetchFilesSummary()
      .then(() => {
        const createdBefore = moment();

        return new Promise((resolve, reject) => {
          this.props.setData('isLoading', true);

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
            });
        });
      });
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
        L.marker(coordinates)
          .bindPopup(
            'Location: ' + data[i].location.label + '<br />' +
            'Latitude: ' +  coordinates[0] + ', ' +
            'Longitude: ' +  coordinates[1] + '<br />' +
            'Count: '  + data[i].count
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
        <div ref={this.mapRef}></div>
      </div>
    );
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(
  withStyles(styles)(MapView)
);
