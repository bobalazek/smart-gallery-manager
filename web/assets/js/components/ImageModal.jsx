import React from 'react';
import axios from 'axios';
import moment from 'moment';
import { withStyles } from '@material-ui/styles';
import Grid from '@material-ui/core/Grid';
import Modal from '@material-ui/core/Modal';
import IconButton from '@material-ui/core/IconButton';
import CloseIcon from '@material-ui/icons/Close';
import CircularProgress from '@material-ui/core/CircularProgress';

const styles = {
  root: {
    backgroundColor: '#000',
    width: '100%',
    height: '100%',
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
    };

    this.imageRef = React.createRef();

    this.onImageLoad = this.onImageLoad.bind(this);
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

    return (
      <Modal
        open={open}
        onClose={onClose}
      >
        <div>
          <IconButton
            className={classes.closeButton}
            onClick={onClose}
          >
            <CloseIcon />
          </IconButton>
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
      </Modal>
    );
  }
}

export default withStyles(styles)(ImageModal);
