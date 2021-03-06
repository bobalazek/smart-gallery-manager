import React from 'react';
import axios from 'axios';
import moment from 'moment';
import L from 'leaflet';
import { connect } from 'react-redux';
import { withStyles } from '@material-ui/styles';
import Divider from '@material-ui/core/Divider';
import Typography from '@material-ui/core/Typography';
import List from '@material-ui/core/List';
import ListItem from '@material-ui/core/ListItem';
import ListItemText from '@material-ui/core/ListItemText';
import ListItemAvatar from '@material-ui/core/ListItemAvatar';
import Avatar from '@material-ui/core/Avatar';
import InsertPhotoIcon from '@material-ui/icons/InsertPhoto';
import CameraIcon from '@material-ui/icons/Camera';
import LocationOnIcon from '@material-ui/icons/LocationOn';
import CalendarTodayIcon from '@material-ui/icons/CalendarToday';
import LabelIcon from '@material-ui/icons/Label';
import CircularProgress from '@material-ui/core/CircularProgress';
import {
  setData,
} from '../actions/index';

const styles = {
  list: {
    width: '100%',
    maxWidth: 360,
  },
};

function mapDispatchToProps(dispatch) {
  return {
    setData: (type, data) => dispatch(setData(type, data)),
  };
}

class ImageModalSidebar extends React.Component {
  constructor(props) {
    super(props);

    this.state = {
      fileInformation: {},
      isFileInformationLoaded: false,
    };

    this.mapRef = React.createRef();
  }

  componentDidMount() {
    this.prepareInformation();
  }

  componentWillUnmount() {
    this.requestCancelToken && this.requestCancelToken();
  }

  componentDidUpdate(previousProps) {
    if (previousProps.data.hash !== this.props.data.hash) {
      this.prepareInformation();
    }
  }

  prepareInformation() {
    this.props.setData('imageModalData', {});
    this.setState({
      isFileInformationLoaded: false,
    });

    this.requestCancelToken && this.requestCancelToken();

    axios.get(rootUrl + '/api/file/' + this.props.data.hash, {
      cancelToken: new axios.CancelToken((cancelToken) => {
        this.requestCancelToken = cancelToken;
      }),
    })
      .then(res => {
        this.setState({
          fileInformation: res.data,
          isFileInformationLoaded: true,
        }, () => {
          const { fileInformation } = this.state;
          const geolocation = fileInformation.meta.geolocation;
          const location = fileInformation.location
            && fileInformation.location.label
            ? fileInformation.location.label
            : '';
          const position = [
            geolocation.latitude ? geolocation.latitude : 0,
            geolocation.longitude ? geolocation.longitude : 0,
          ];

          if (
            this.mapRef &&
            this.mapRef.current
          ) {
            const mapMarkersLayer = L.layerGroup();
            const mapMarker = L.marker(position)
              .bindPopup(location)
              .addTo(mapMarkersLayer);
            const map = L.map(this.mapRef.current, {
              layers: [
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                  attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors',
                }),
                mapMarkersLayer,
              ],
            }).setView(position, 13);
          }

          this.props.setData('imageModalData', fileInformation);
        });
      })
      .catch((error) => {
        if (axios.isCancel(error)) {
          // Request was canceled
        }
      });
  }

  render() {
    const {
      fileInformation,
      isFileInformationLoaded,
    } = this.state;
    const {
      classes,
    } = this.props;

    const infoData = {
      datePrimary: fileInformation
        ? moment.parseZone(fileInformation.taken_at).format('LL')
        : '',
      dateSecondary: fileInformation
        ? moment.parseZone(fileInformation.taken_at).format('HH:mm:ss')
        : '',
      filePrimary: fileInformation &&
        fileInformation.meta
        ? fileInformation.meta.name
        : '',
      fileSecondary: fileInformation &&
        fileInformation.meta
        ? (
          <React.Fragment>
            <span dangerouslySetInnerHTML={{ __html: fileInformation.path
              ? 'Path: ' + fileInformation.path + '<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.mime
              ? 'Mime: ' + fileInformation.mime + '<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.meta.size
              ? 'File size: ' + (fileInformation.meta.size / 1024 / 1024).toFixed(1) + 'MB <br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.meta.pixels
              ? 'Megapixels: ' + (fileInformation.meta.pixels / 1000000).toFixed(1) + 'MP <br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.meta.width
              && fileInformation.meta.height
              ? 'Size: ' + (fileInformation.meta.width + 'x' + fileInformation.meta.height) + '<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.meta.orientation
              ? 'Orientation: ' + fileInformation.meta.orientation + '<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.taken_at
              ? 'Taken at: ' + moment.parseZone(fileInformation.taken_at).format('LL HH:mm:ss') + '<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.created_at
              ? 'Created at: ' + moment.parseZone(fileInformation.created_at).format('LL HH:mm:ss') + '<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.modified_at
              ? 'Modified at: ' + moment.parseZone(fileInformation.modified_at).format('LL HH:mm:ss') + '<br />'
              : '' }} />
          </React.Fragment>
        )
        : '',
      hasDeviceData: fileInformation &&
        fileInformation.meta &&
        fileInformation.meta.device &&
        (
          fileInformation.meta.device.make ||
          fileInformation.meta.device.model ||
          fileInformation.meta.device.shutter_speed ||
          fileInformation.meta.device.aperture ||
          fileInformation.meta.device.iso ||
          fileInformation.meta.device.focal_length ||
          fileInformation.meta.device.lens_make ||
          fileInformation.meta.device.lens_model
        ),
      devicePrimary: fileInformation &&
        fileInformation.meta &&
        fileInformation.meta.device &&
        (
          fileInformation.meta.device.make ||
          fileInformation.meta.device.model
        )
        ? fileInformation.meta.device.make + ' ' + fileInformation.meta.device.model
        : '',
      deviceSecondary: fileInformation &&
        fileInformation.meta &&
        fileInformation.meta.device
        ? (
          <React.Fragment>
            <span dangerouslySetInnerHTML={{ __html: fileInformation.meta.device.aperture
              ? 'Aperture: f/' + fileInformation.meta.device.aperture + '<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.meta.device.shutter_speed
              ? 'Shutter speed: ' + fileInformation.meta.device.shutter_speed + ' sec<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.meta.device.focal_length
              ? 'Focal length: ' + fileInformation.meta.device.focal_length + ' mm<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.meta.device.iso
              ? 'ISO: ' + fileInformation.meta.device.iso + '<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.meta.device.lens_make
              ? 'Lens make: ' + fileInformation.meta.device.lens_make + '<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.meta.device.lens_model
              ? 'Lens model: ' + fileInformation.meta.device.lens_model + '<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.meta.device.orientation
              ? 'Orientation: ' + fileInformation.meta.device.orientation + '<br />'
              : '' }} />
          </React.Fragment>
        )
        : '',
      hasLocationData: fileInformation &&
        fileInformation.meta &&
        fileInformation.meta.geolocation &&
        (
          fileInformation.meta.geolocation.altitude ||
          fileInformation.meta.geolocation.latitude ||
          fileInformation.meta.geolocation.longitude
        ),
      locationPrimary: 'Location',
      locationSecondary: fileInformation &&
        fileInformation.meta &&
        fileInformation.meta.geolocation
        ? (
          <React.Fragment>
            <span dangerouslySetInnerHTML={{ __html: fileInformation.location
              && fileInformation.location.label
              ? 'Label: ' + fileInformation.location.label + '<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.location
              && fileInformation.location.street
              ? 'Street: ' + fileInformation.location.street + '<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.location
              && fileInformation.location.house_number
              ? 'House number: ' + fileInformation.location.house_number + '<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.location
              && fileInformation.location.postal_code
              ? 'Postal code: ' + fileInformation.location.postal_code + '<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.location
              && fileInformation.location.city
              ? 'City: ' + fileInformation.location.city + '<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.location
              && fileInformation.location.district
              ? 'District: ' + fileInformation.location.district + '<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.location
              && fileInformation.location.state
              ? 'State: ' + fileInformation.location.state + '<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.location
              && fileInformation.location.country
              ? 'Country: ' + fileInformation.location.country + '<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.meta.geolocation.altitude
              ? 'Altitude: ' + fileInformation.meta.geolocation.altitude + '<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.meta.geolocation.latitude
              ? 'Latitude: ' + fileInformation.meta.geolocation.latitude + '<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.meta.geolocation.longitude
              ? 'Longitude: ' + fileInformation.meta.geolocation.longitude + '<br />'
              : '' }} />
          </React.Fragment>
        )
        : '',
      hasLabelsData: fileInformation.labels && fileInformation.labels.length > 0,
      labelsPrimary: 'Labels',
      labelsSecondary: fileInformation.labels
        ? fileInformation.labels.map((label) => {
          return label.name + ' (' + parseInt(label.confidence) + '%)';
        }).join(', ')
        : '',
    };
    return (
      <div>
        <Typography
          variant="h4"
          component="h4"
          style={{ padding: 16 }}
        >
          Information
        </Typography>
        <Divider />
        {!isFileInformationLoaded && (
          <div style={{ padding: 16, textAlign: 'center' }}>
            <CircularProgress size={40} />
          </div>
        )}
        {isFileInformationLoaded && (
          <div>
            <List className={classes.list}>
              <ListItem>
                <ListItemAvatar>
                  <Avatar>
                    <CalendarTodayIcon />
                  </Avatar>
                </ListItemAvatar>
                <ListItemText
                  primary={infoData.datePrimary}
                  secondary={infoData.dateSecondary}
                />
              </ListItem>
              <ListItem>
                <ListItemAvatar>
                  <Avatar>
                    <InsertPhotoIcon />
                  </Avatar>
                </ListItemAvatar>
                <ListItemText
                  primary={infoData.filePrimary}
                  secondary={infoData.fileSecondary}
                  style={{
                    overflow: 'hidden',
                    textOverflow: 'ellipsis',
                    wordBreak: 'break-word'
                  }}
                />
              </ListItem>
              {infoData.hasDeviceData &&
                <ListItem>
                  <ListItemAvatar>
                    <Avatar>
                      <CameraIcon />
                    </Avatar>
                  </ListItemAvatar>
                  <ListItemText
                    primary={infoData.devicePrimary}
                    secondary={infoData.deviceSecondary}
                  />
                </ListItem>
              }
              {infoData.hasLabelsData &&
                <ListItem>
                  <ListItemAvatar>
                    <Avatar>
                      <LabelIcon />
                    </Avatar>
                  </ListItemAvatar>
                  <ListItemText
                    primary={infoData.labelsPrimary}
                    secondary={infoData.labelsSecondary}
                  />
                </ListItem>
              }
              {infoData.hasLocationData &&
                <ListItem>
                  <ListItemAvatar>
                    <Avatar>
                      <LocationOnIcon />
                    </Avatar>
                  </ListItemAvatar>
                  <ListItemText
                    primary={infoData.locationPrimary}
                    secondary={infoData.locationSecondary}
                  />
                </ListItem>
              }
            </List>
            <div
              ref={this.mapRef}
              style={{
                height: 240,
                display: infoData.hasLocationData
                  ? 'block'
                  : 'none',
              }}
            ></div>
          </div>
        )}
      </div>
    );
  }
}

export default connect(null, mapDispatchToProps)(
  withStyles(styles)(ImageModalSidebar)
);
