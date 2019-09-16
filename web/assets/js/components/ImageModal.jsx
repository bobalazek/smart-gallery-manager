import React from 'react';
import { connect } from 'react-redux';
import { withStyles } from '@material-ui/styles';
import Grid from '@material-ui/core/Grid';
import Modal from '@material-ui/core/Modal';
import Typography from '@material-ui/core/Typography';
import IconButton from '@material-ui/core/IconButton';
import CloseIcon from '@material-ui/icons/Close';
import InfoIcon from '@material-ui/icons/Info';
import ArrowBackIcon from '@material-ui/icons/ArrowBack';
import ArrowForwardIcon from '@material-ui/icons/ArrowForward';
import CircularProgress from '@material-ui/core/CircularProgress';
import ImageModalSidebar from './ImageModalSidebar';

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
    position: 'absolute',
    top: 0,
    bottom: 0,
    left: 0,
    width: '100%',
  },
  contentWithSidebar: {
    width: 'calc(100% - 360px)',
  },
  sidebar: {
    position: 'absolute',
    backgroundColor: '#fff',
    top: 0,
    bottom: 0,
    right: 0,
    width: '100%',
    maxWidth: 0,
    overflow: 'auto',
  },
  sidebarOpen: {
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
    zIndex: 9999,
  },
  infoButton: {
    position: 'absolute',
    top: 16,
    right: 16,
    color: '#fff',
    zIndex: 9999,
  },
  prevButton: {
    position: 'absolute',
    left: 16,
    top: '50%',
    color: '#fff',
    zIndex: 9999,
  },
  nextButton: {
    position: 'absolute',
    right: 16,
    top: '50%',
    color: '#fff',
    zIndex: 9999,
  },
  circularProgressWrapper: {
    textAlign: 'center',
    marginTop: 64,
    zIndex: 9999,
  },
};

const mapStateToProps = state => {
  return {
    files: state.files,
  };
};

class ImageModal extends React.Component {
  constructor(props) {
    super(props);

    this.state = {
      data: {},
      fileIndex: -1, // what's the index of the currently open image in the modal?
      isImageLoading: false,
      isImageLoaded: false,
      imageSrc: '',
      imageWrapperStyle: {},
      imageStyle: {},
      isSidebarOpen: false,
      showPrevButton: false,
      showNextButton: false,
    };

    // We always set the "preview" image in the modal first,
    //   so that one is used to preload the original image.
    // Once it's loaded, set the url of that original one, as the main src.
    this.originalImage = new Image();

    this.imageRef = React.createRef();
    this.imageContentRef = React.createRef();

    this.onImageLoad = this.onImageLoad.bind(this);
    this.onInfoButtonClick = this.onInfoButtonClick.bind(this);
    this.onPrevButtonClick = this.onPrevButtonClick.bind(this);
    this.onNextButtonClick = this.onNextButtonClick.bind(this);
    this.prepareImageStyles = this.prepareImageStyles.bind(this);
    this.prepareEvents = this.prepareEvents.bind(this);
    this.prepareData = this.prepareData.bind(this);
  }

  componentDidMount() {
    window.addEventListener('resize', this.prepareImageStyles);
    window.addEventListener('keydown', this.prepareEvents);
  }

  componentWillUnmount() {
    window.removeEventListener('resize', this.prepareImageStyles);
    window.removeEventListener('keydown', this.prepareEvents);
  }

  componentDidUpdate(previousProps) {
    if (previousProps.fileId !== this.props.fileId) {
      this.prepareData(this.props.fileId);
    }
  }

  onImageLoad() {
    const isPreviewImage = this.state.imageSrc === this.state.data.images.preview.src;
    this.setState({
      isImageLoading: false,
      isImageLoaded: true,
    });

    if (isPreviewImage) {
      const originalImageData = this.state.data.images.original;

      this.setState({
        isImageLoading: true,
      });

      this.originalImage.src = originalImageData.src;
      this.originalImage.onload = () => {
        this.setState({
          isImageLoading: false,
          imageSrc: this.originalImage.src,
        });
      };

      this.prepareImageStyles(
        originalImageData.width,
        originalImageData.height
      );
    } else {
      this.prepareImageStyles();
    }
  }

  onInfoButtonClick() {
    this.setState({
      isSidebarOpen: !this.state.isSidebarOpen,
    }, () => {
      this.prepareImageStyles();
    });
  }

  onPrevButtonClick() {
    const file = this.props.files[this.state.fileIndex - 1];
    if (file) {
      this.prepareData(file.id);
    }
  }

  onNextButtonClick() {
    const file = this.props.files[this.state.fileIndex + 1];
    if (file) {
      this.prepareData(file.id);
    }
  }

  prepareImageStyles(width, height) {
    if (
      !this.imageRef.current ||
      !this.imageContentRef.current
    ) {
      return;
    }

    const containerWidth = this.imageContentRef.current.clientWidth;
    const containerHeight = this.imageContentRef.current.clientHeight;
    const imageWidth = width || this.imageRef.current.naturalWidth;
    const imageHeight = height || this.imageRef.current.naturalHeight;
    const imageAspectRatio = imageWidth / imageHeight;

    let finalImageWidth = imageWidth;
    let finalImageHeight = imageHeight;

    if (finalImageWidth > containerWidth) {
      finalImageWidth = containerWidth;
      finalImageHeight = finalImageWidth / imageAspectRatio;
    }

    if (finalImageHeight > containerHeight) {
      finalImageHeight = containerHeight;
      finalImageWidth = finalImageHeight * imageAspectRatio;
    }

    const wrapperLeft = (containerWidth - finalImageWidth) / 2;
    const wrapperTop = (containerHeight - finalImageHeight) / 2;

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
  }

  prepareEvents(e) {
    if (e.keyCode === 37) { // Left
      this.onPrevButtonClick();
    } else if (e.keyCode === 39) { // Right
      this.onNextButtonClick();
    }
  }

  prepareData(fileId) {
    let data = {};
    let fileIndex = -1;
    let showPrevButton = false;
    let showNextButton = false;
    for (let i = 0; i < this.props.files.length; i++) {
      if (this.props.files[i].id === fileId) {
        data = this.props.files[i];
        fileIndex = i;
        break;
      }
    }
    if (fileIndex !== -1) {
      showPrevButton = fileIndex !== 0;
      showNextButton = fileIndex < this.props.files.length;
    }

    const imageSrc = data &&
      data.images &&
      data.images.preview &&
      data.images.preview.src
      ? data.images.preview.src
      : '';

    // Cancel original image loading - just in case there is any
    this.originalImage.src = '';

    this.setState({
      data,
      fileIndex,
      isImageLoaded: false,
      isImageLoading: true,
      imageSrc,
      imageWrapperStyle: {},
      imageStyle: {},
      showPrevButton,
      showNextButton,
    });
  }

  render() {
    const {
      data,
      isImageLoading,
      isImageLoaded,
      imageWrapperStyle,
      imageSrc,
      imageStyle,
      isSidebarOpen,
      showPrevButton,
      showNextButton,
    } = this.state;
    const {
      classes,
      open,
      onClose,
    } = this.props;

    let finalImageStyle = {...imageStyle};
    if (!isImageLoaded) {
      finalImageStyle.display = 'none';
    }

    const contentClassName = isSidebarOpen
      ? `${classes.content} ${classes.contentWithSidebar}`
      : classes.content;
    const sidebarClassName = isSidebarOpen
      ? `${classes.sidebar} ${classes.sidebarOpen}`
      : classes.sidebar;

    return (
      <Modal
        open={open}
        onClose={onClose}
      >
        <div className={classes.inner}>
          <div
            className={contentClassName}
            ref={this.imageContentRef}
          >
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
              {showPrevButton &&
                <IconButton
                  className={classes.prevButton}
                  onClick={this.onPrevButtonClick}
                >
                  <ArrowBackIcon />
                </IconButton>
              }
              {showNextButton &&
                <IconButton
                  className={classes.nextButton}
                  onClick={this.onNextButtonClick}
                >
                  <ArrowForwardIcon />
                </IconButton>
              }
            </div>
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
            {isImageLoading && (
              <div className={classes.circularProgressWrapper}>
                <CircularProgress size={80} />
              </div>
            )}
          </div>
          <div className={sidebarClassName}>
            <ImageModalSidebar
              data={data}
            />
          </div>
        </div>
      </Modal>
    );
  }
}

export default connect(mapStateToProps)(
  withStyles(styles)(ImageModal)
);
