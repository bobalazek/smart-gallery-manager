import React from 'react';
import axios from 'axios';
import moment from 'moment';
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
import CircularProgress from '@material-ui/core/CircularProgress';

const styles = {
  list: {
    width: '100%',
    maxWidth: 360,
  },
};

class ImageModalSidebar extends React.Component {
  constructor(props) {
    super(props);

    this.state = {
      fileInformation: {},
      isFileInformationLoaded: false,
    };
  }

  componentDidMount() {
    axios.get(rootUrl + '/api/file/' + this.props.data.hash)
      .then(res => {
        this.setState({
          fileInformation: res.data,
          isFileInformationLoaded: true,
        });
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
        ? moment(fileInformation.taken_at).format('LL')
        : '',
      dateSecondary: fileInformation
        ? moment(fileInformation.taken_at).format('HH:mm:ss')
        : '',
      filePrimary: fileInformation && fileInformation.meta
        ? fileInformation.meta.name
        : '',
      fileSecondary: fileInformation && fileInformation.meta
        ? (
          <React.Fragment>
            Megapixels: {(fileInformation.meta.dimensions.total / 1000000).toFixed(1)}MP <br />
            Size: {fileInformation.meta.dimensions.width + 'x' + fileInformation.meta.dimensions.height} <br />
            File size: {(fileInformation.meta.size / 1024 / 1024).toFixed(1)}MB <br />
          </React.Fragment>
        )
        : '',
      devicePrimary: fileInformation && fileInformation.meta && fileInformation.meta.device
        ? fileInformation.meta.device.make + ' ' + fileInformation.meta.device.model
        : '',
      deviceSecondary: fileInformation && fileInformation.meta && fileInformation.meta.device
        ? (
          <React.Fragment>
            Aperature: f/{fileInformation.meta.device.aperature} <br />
            Shutter speed: {fileInformation.meta.device.shutter_speed} <br />
            Focal length: {fileInformation.meta.device.focal_length} <br />
            ISO: {fileInformation.meta.device.iso}
          </React.Fragment>
        )
        : '',
      hasLocationData: fileInformation
        && fileInformation.meta
        && fileInformation.meta.location &&
        (
          fileInformation.meta.location.name ||
          fileInformation.meta.location.altitude ||
          fileInformation.meta.location.latitude ||
          fileInformation.meta.location.longitude
        ),
      locationPrimary: 'Location',
      locationSecondary: fileInformation && fileInformation.meta && fileInformation.meta.location
        ? (
          <React.Fragment>
            <span dangerouslySetInnerHTML={{ __html: fileInformation.meta.location.name
              ? 'Name:' + fileInformation.meta.location.name + '<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.meta.location.altitude
              ? 'Altitude:' + fileInformation.meta.location.altitude + '<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.meta.location.latitude
              ? 'Latitude:' + fileInformation.meta.location.latitude + '<br />'
              : '' }} />
            <span dangerouslySetInnerHTML={{ __html: fileInformation.meta.location.longitude
              ? 'Longitude:' + fileInformation.meta.location.longitude + '<br />'
              : '' }} />
          </React.Fragment>
        )
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
              />
            </ListItem>
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
        )}
      </div>
    );
  }
}

export default withStyles(styles)(ImageModalSidebar);
