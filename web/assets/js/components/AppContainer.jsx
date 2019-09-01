import React from 'react';
import { withStyles } from '@material-ui/styles';
import ImageModal from './ImageModal';
import AppSidebar from './AppSidebar';
import AppContent from './AppContent';

const styles = {
  root: {
    width: '100%',
    padding: 16,
    display: 'flex',
    flexDirection: 'column',
    height: '100vh',
  },
  containerInner: {
    width: '100%',
    padding: 16,
    display: 'flex',
    flexGrow: 1,
  },
  sidebarWrapper: {
    flexShrink: 0,
    width: 200,
    position: 'static',
    paddingRight: 20,
  },
  contentWrapper: {
    flexGrow: 1,
    position: 'relative',
  },
};

class AppContainer extends React.Component {
  constructor(props) {
    super(props);

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
      <div className={classes.root}>
        <div className={classes.containerInner}>
          <div className={classes.sidebarWrapper}>
            <AppSidebar />
          </div>
          <div className={classes.contentWrapper}>
            <AppContent
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
    );
  }
}

export default withStyles(styles)(AppContainer);
