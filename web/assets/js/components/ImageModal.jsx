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
  },
};

const mapStateToProps = state => {
  return {
    isLoading: state.isLoading,
    isLoaded: state.isLoaded,
    rows: state.rows,
    rowsIndexes: state.rowsIndexes,
    files: state.files,
    filesMap: state.filesMap,
    filesSummary: state.filesSummary,
    filesSummaryDatetime: state.filesSummaryDatetime,
    orderBy: state.orderBy,
    search: state.search,
    selectedType: state.selectedType,
    selectedYear: state.selectedYear,
    selectedMonth: state.selectedMonth,
    selectedDay: state.selectedDay,
    selectedTag: state.selectedTag,
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
      imageSrc: {},
      imageWrapperStyle: {},
      imageStyle: {},
      isSidebarOpen: false,
      showPrevButton: false,
      showNextButton: false,
    };

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
    this.setState({
      isImageLoading: false,
      isImageLoaded: true,
    });

    this.prepareImageStyles();

    if (this.state.imageSrc === this.state.data.images.preview.src) {
      this.setState({
        isImageLoading: true,
      });

      let originalImage = new Image();
      originalImage.src = this.state.data.images.original.src;
      originalImage.onload = () => {
        // TODO: check if we haven't yet switched to the next image
        this.setState({
          isImageLoading: false,
          imageSrc: originalImage.src,
        });
      };
    }
  }

  onInfoButtonClick() {
    this.setState({
      isSidebarOpen: !this.state.isSidebarOpen,
    });

    this.prepareImageStyles();
  }

  onPrevButtonClick() {
    this.prepareData(
      this.props.files[this.state.fileIndex - 1].id
    );
  }

  onNextButtonClick() {
    this.prepareData(
      this.props.files[this.state.fileIndex + 1].id
    );
  }

  prepareImageStyles() {
    if (
      !this.imageRef.current ||
      !this.imageContentRef.current
    ) {
      return;
    }

    const containerWidth = this.imageContentRef.current.clientWidth;
    const containerHeight = this.imageContentRef.current.clientHeight;
    const imageWidth = this.imageRef.current.naturalWidth;
    const imageHeight = this.imageRef.current.naturalHeight;
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
      : null;

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
            {isImageLoading && (
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
