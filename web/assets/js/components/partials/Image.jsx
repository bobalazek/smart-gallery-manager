import React from 'react';
import PropTypes from 'prop-types';
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
    this.setState({ isLoaded: true });
    if (this.props.onLoad) {
      this.props.onLoad();
    }
  }

  onImageError() {
    if (this.props.src) {
      this.setState({ isError: true });
      if (this.props.onError) {
        this.props.onError();
      }
    }
  }

  render() {
    const {
      classes,
      onClick,
      ...image
    } = this.props;

    const loadingIcon = <CircularProgress size={32} />;
    const errorIcon = <BrokenImage style={{ width: 32, height: 32 }} />;

    return (
      <div className={classes.root}
        onClick={onClick}>
        {image.src && <img
          {...image}
          className={classes.image}
          onLoad={this.onImageLoaded}
          onError={this.onImageError}
        />}
        <div className={classes.iconContainer}>
          {!this.state.isLoaded && !this.state.isError && loadingIcon}
          {this.state.isError && errorIcon}
        </div>
      </div>
    )
  }
}

export default withStyles(styles)(Image);
