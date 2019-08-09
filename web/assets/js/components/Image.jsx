import React from 'react';
import PropTypes from 'prop-types';
import handleViewport from 'react-in-viewport';
import { withStyles } from '@material-ui/styles';
import CircularProgress from '@material-ui/core/CircularProgress';
import BrokenImage from '@material-ui/icons/BrokenImage';

const styles = {
  root: {
    position: 'relative',
    minHeight: 64,
    cursor: 'pointer',
  },
  image: {
    width: '100%',
    height: '100%',
    filter: 'brightness(100%)',
    transition: 'all 0.2s ease',
    '&:hover': {
      filter: 'brightness(80%)',
    },
  },
  iconContainer: {
    width: '100%',
    height: '100%',
    position: 'absolute',
    top: 0,
    left: 0,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    pointerEvents: 'none',
  },
};

class Image extends React.Component {
  constructor (props) {
    super(props);

    this.state = {
      isError: false,
      isLoaded: false,
      src: this.props.src,
    };

    this.onImageLoaded = this.onImageLoaded.bind(this);
    this.onImageError = this.onImageError.bind(this);
  }

  componentWillReceiveProps(nextProps) {
    if (this.props.src !== nextProps.src) {
      this.setState({
        isError: false,
        isLoaded: false,
      });
    }
  }

  onImageLoaded() {
    this.setState({
      isLoaded: true,
    });

    if (this.props.srcAfterLoad) {
      this.setState({
        src: this.props.srcAfterLoad,
      });
    }

    if (this.props.onLoad) {
      this.props.onLoad();
    }
  }

  onImageError() {
    this.setState({
      isError: true,
    });
    if (this.props.onError) {
      this.props.onError();
    }
  }

  render() {
    const {
      classes,
      inViewport,
      onClick,
    } = this.props;
    const {
      isLoaded,
      isError,
      src,
    } = this.state;

    const iconSize = 32;
    const loadingIcon = <CircularProgress size={iconSize} />;
    const errorIcon = <BrokenImage style={{ width: iconSize, height: iconSize }} />;

    //const finalSrc = inViewport ? src : '';
    // TODO: should actually stop the image from loading when it's out of the viewport,
    //   but it does not. Figure out why.

    const finalSrc = src;

    return (
      <div
        className={classes.root}
        onClick={onClick}
      >
        {finalSrc && !isError && <img
          src={finalSrc}
          className={classes.image}
          onLoad={this.onImageLoaded}
          onError={this.onImageError}
        />}
        <div className={classes.iconContainer}>
          {!isLoaded && !isError && loadingIcon}
          {isError && errorIcon}
        </div>
      </div>
    )
  }
}

export default handleViewport(withStyles(styles)(Image), { rootMargin: '-1.0px' });
