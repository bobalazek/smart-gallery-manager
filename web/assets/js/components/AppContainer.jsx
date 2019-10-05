import React from 'react';
import { connect } from 'react-redux';
import { BrowserRouter } from 'react-router-dom';
import { withStyles } from '@material-ui/styles';
import CircularProgress from '@material-ui/core/CircularProgress';
import ImageModal from './ImageModal';
import AppSidebar from './AppSidebar';
import AppContent from './AppContent';

const styles = {
  root: {
    width: '100%',
    display: 'flex',
    flexDirection: 'column',
    height: '100vh',
  },
  circularProgressWrapper: {
    position: 'fixed',
    top: 32,
    right: 32,
    zIndex: 9999,
  },
  containerInner: {
    width: '100%',
    display: 'flex',
    flexGrow: 1,
  },
  sidebarWrapper: {
    flexShrink: 0,
    width: 240,
  },
  contentWrapper: {
    flexGrow: 1,
    position: 'relative',
  },
};

const mapStateToProps = state => {
  return {
    isLoading: state.isLoading,
  };
};

class AppContainer extends React.Component {
  constructor(props) {
    super(props);

    this.maxFilesPerRow = 50;
    this.sidebarLabelsShownStep = 16;
    this.views = {
      list: {
        key: 'list',
        label: 'List',
        url: basePath,
      },
      map: {
        key: 'map',
        label: 'Map',
        url: basePath + '/map',
      },
    };

    this.state = {
      isModalOpen: false,
      modalFileId: {},
    };

    this.onImageClick = this.onImageClick.bind(this);
    this.onModalClose = this.onModalClose.bind(this);
  }

  onImageClick(file) {
    this.setState({
      isModalOpen: true,
      modalFileId: file.id,
    });
  }

  onModalClose() {
    this.setState({
      isModalOpen: false,
      modalFileId: 0,
    });
  }

  render() {
    const {
      classes,
      isLoading,
    } = this.props;
    const {
      isModalOpen,
      modalFileId,
    } = this.state;

    return (
      <BrowserRouter>
        <div className={classes.root}>
          {isLoading && (
            <div className={classes.circularProgressWrapper}>
              <CircularProgress size={80} />
            </div>
          )}
          <div className={classes.containerInner}>
            <div className={classes.sidebarWrapper}>
              <AppSidebar parent={this} />
            </div>
            <div className={classes.contentWrapper}>
              <AppContent
                parent={this}
                onImageClick={this.onImageClick}
              />
            </div>
          </div>
          <ImageModal
            open={isModalOpen}
            onClose={this.onModalClose}
            fileId={modalFileId}
          />
        </div>
      </BrowserRouter>
    );
  }
}

export default connect(mapStateToProps)(
  withStyles(styles)(AppContainer)
);
