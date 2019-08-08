import React from 'react';
import axios from 'axios';
import moment from 'moment';
import { withStyles } from '@material-ui/styles';
import Grid from '@material-ui/core/Grid';
import Modal from '@material-ui/core/Modal';
import Divider from '@material-ui/core/Divider';
import Typography from '@material-ui/core/Typography';
import IconButton from '@material-ui/core/IconButton';
import List from '@material-ui/core/List';
import ListItem from '@material-ui/core/ListItem';
import ListItemText from '@material-ui/core/ListItemText';
import ListItemAvatar from '@material-ui/core/ListItemAvatar';
import Avatar from '@material-ui/core/Avatar';
import CloseIcon from '@material-ui/icons/Close';
import InsertPhotoIcon from '@material-ui/icons/InsertPhoto';
import InfoIcon from '@material-ui/icons/Info';
import CameraIcon from '@material-ui/icons/Camera';
import LocationOnIcon from '@material-ui/icons/LocationOn';
import CalendarTodayIcon from '@material-ui/icons/CalendarToday';
import CircularProgress from '@material-ui/core/CircularProgress';

const styles = {
  root: {
    backgroundColor: '#000',
    width: '100%',
    height: '100%',
  },
  inner: {
    position: 'relative',
    height: '100%',
    width: '100%',
  },
  content: {

  },
  sidebar: {
    position: 'absolute',
    backgroundColor: '#fff',
    top: 0,
    bottom: 0,
    right: 0,
    width: '100%',
    maxWidth: 360,
  },
  sidebarList: {
    width: '100%',
    maxWidth: 360,
  },
  image: {
    display: 'block',
    maxWidth: '100%',
    maxHeight: '100%',
  },
  closeButton: {
    position: 'absolute',
    top: 16,
    left: 16,
    color: '#fff',
  },
  infoButton: {
    position: 'absolute',
    top: 16,
    right: 16,
    color: '#fff',
  },
  circularProgressWrapper: {
    textAlign: 'center',
    marginTop: 64,
  },
};

class ImageModal extends React.Component {
  constructor(props) {
    super(props);

    this.state = {
      isImageLoaded: false,
      imageWrapperStyle: {},
      imageStyle: {},
      isSidebarOpen: false,
    };

    this.imageRef = React.createRef();

    this.onImageLoad = this.onImageLoad.bind(this);
    this.onInfoButtonClick = this.onInfoButtonClick.bind(this);
    this.prepareImageStyles = this.prepareImageStyles.bind(this);
  }

  componentDidMount() {
    window.addEventListener('resize', this.prepareImageStyles);
  }

  componentWillUnmount() {
    window.removeEventListener('resize', this.prepareImageStyles);
  }

  componentDidUpdate(previousProps) {
    if (previousProps.data.id !== this.props.data.id) {
      this.setState({
        isImageLoaded: false,
        imageWrapperStyle: {},
        imageStyle: {},
      });
    }
  }

  onImageLoad() {
    this.setState({
      isImageLoaded: true,
    });

    this.prepareImageStyles();
  }

  onInfoButtonClick() {
    this.setState({
      isSidebarOpen: !this.state.isSidebarOpen,
    });
  }

  prepareImageStyles() {
    // Make sure the image is really ready.
    // Seems that the onLoad event of the image triggers too soon.
    setTimeout(() => {
      const windowWidth = window.innerWidth;
      const windowHeight = window.innerHeight;
      const imageWidth = this.imageRef.current.clientWidth;
      const imageHeight = this.imageRef.current.clientHeight;
      const imageAspectRatio = imageWidth / imageHeight;

      let finalImageWidth = imageWidth;
      let finalImageHeight = imageHeight;

      if (finalImageHeight > windowHeight) {
        const sizingRatio = finalImageHeight / windowHeight;
        finalImageHeight = windowHeight;
        finalImageWidth = finalImageWidth / sizingRatio;
      }

      const wrapperLeft = (windowWidth - finalImageWidth) / 2;
      const wrapperTop = (windowHeight - finalImageHeight) / 2;

      this.setState({
        imageStyle: {
          width: finalImageWidth,
          height: finalImageHeight,
        },
        imageWrapperStyle: {
          position: 'absolute',
          left: wrapperLeft,
          top: wrapperTop,
        },
      });
    });
  }

  render() {
    const {
      isImageLoaded,
      imageWrapperStyle,
      imageStyle,
    } = this.state;
    const {
      classes,
      open,
      onClose,
      data,
    } = this.props;

    const imageSrc = data && data.links
      ? data.links.original
      : null;

    let finalImageStyle = {...imageStyle};
    if (!isImageLoaded) {
      finalImageStyle.display = 'none';
    }

    const infoData = {
      datePrimary: data
        ? moment(data.taken_at).format('LL')
        : '',
      dateSecondary: data
        ? moment(data.taken_at).format('HH:mm:ss')
        : '',
      filePrimary: data && data.meta
        ? data.meta.name
        : '',
      fileSecondary: data && data.meta
        ? (
          <React.Fragment>
            Megapixels: {(data.meta.dimensions.total / 1000000).toFixed(1)}MP <br />
            Size: {data.meta.dimensions.width + 'x' + data.meta.dimensions.height} <br />
            File size: {(data.meta.size / 1024 / 1024).toFixed(1)}MB <br />
          </React.Fragment>
        )
        : '',
      devicePrimary: data && data.meta && data.meta.device
        ? data.meta.device.make + ' ' + data.meta.device.model
        : '',
      deviceSecondary: data && data.meta && data.meta.device
        ? (
          <React.Fragment>
            Aperature: f/{data.meta.device.aperature} <br />
            Shutter speed: {data.meta.device.shutter_speed} <br />
            Focal length: {data.meta.device.focal_length} <br />
            ISO: {data.meta.device.iso}
          </React.Fragment>
        )
        : '',
      locationPrimary: 'Location',
      locationSecondary: data && data.meta && data.meta.location
        ? (
          <React.Fragment>
            Name: {data.meta.location.name} <br />
            Altitude: {data.meta.location.altitude} <br />
            Latitude: {data.meta.location.latitude} <br />
            Longitude: {data.meta.location.longitude} <br />
          </React.Fragment>
        )
        : '',
    };

    return (
      <Modal
        open={open}
        onClose={onClose}
      >
        <div className={classes.inner}>
          <div className={classes.content}>
            <div>
              <IconButton
                className={classes.closeButton}
                onClick={onClose}
              >
                <CloseIcon />
              </IconButton>
              <IconButton
                className={classes.infoButton}
                onClick={this.onInfoButtonClick}
              >
                <InfoIcon />
              </IconButton>
            </div>
            {!isImageLoaded && (
              <div className={classes.circularProgressWrapper}>
                <CircularProgress size={80} />
              </div>
            )}
            {imageSrc &&
              <div style={imageWrapperStyle}>
                <img
                  src={imageSrc}
                  onLoad={this.onImageLoad}
                  ref={this.imageRef}
                  className={classes.image}
                  style={finalImageStyle}
                />
              </div>
            }
          </div>
          <div className={classes.sidebar}>
            <Typography variant="h4" component="h4">
              Info
            </Typography>
            <Divider />
            <List className={classes.sidebarList}>
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
              <ListItem>
                <ListItemAvatar>
                  <Avatar>
                    <CameraIcon />
                  </Avatar>
                </ListItemAvatar>
                <ListItemText
                  primary={infoData.locationPrimary}
                  secondary={infoData.locationSecondary}
                />
              </ListItem>
            </List>
          </div>
        </div>
      </Modal>
    );
  }
}

export default withStyles(styles)(ImageModal);
