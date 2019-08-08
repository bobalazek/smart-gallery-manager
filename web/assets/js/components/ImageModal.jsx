import React from 'react';
import axios from 'axios';
import moment from 'moment';
import { withStyles } from '@material-ui/styles';
import Grid from '@material-ui/core/Grid';
import Modal from '@material-ui/core/Modal';
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
    margin: '0 auto',
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
    };

    this.imageRef = React.createRef();

    this.onImageLoad = this.onImageLoad.bind(this);
  }

  componentDidUpdate(previousProps) {
    if (previousProps.data.id !== this.props.data.id) {
      this.setState({
        isImageLoaded: false,
      });
    }
  }

  onImageLoad() {
    this.setState({
      isImageLoaded: true,
    });
  }

  render() {
    const {
      isImageLoaded,
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
    let imageStyle = {};
    if (!isImageLoaded) {
      imageStyle.display = 'none';
    }

    return (
      <Modal
        open={open}
        onClose={onClose}
      >
        <div>
          {!isImageLoaded && (
            <div className={classes.circularProgressWrapper}>
              <CircularProgress size={80} />
            </div>
          )}
          {imageSrc &&
            <img
              src={imageSrc}
              onLoad={this.onImageLoad}
              ref={this.imageRef}
              className={classes.image}
              style={imageStyle}
            />
          }
        </div>
      </Modal>
    );
  }
}

export default withStyles(styles)(ImageModal);
