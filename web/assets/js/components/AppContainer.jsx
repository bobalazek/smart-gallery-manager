import React from 'react';
import { BrowserRouter } from 'react-router-dom';
import { withStyles } from '@material-ui/styles';
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
    } = this.props;
    const {
      isModalOpen,
      modalFileId,
    } = this.state;

    return (
      <BrowserRouter>
        <div className={classes.root}>
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

export default withStyles(styles)(AppContainer);
