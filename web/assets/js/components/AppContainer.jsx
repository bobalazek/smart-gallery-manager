import React from 'react';
import { withStyles } from '@material-ui/styles';
import ImageModal from './ImageModal';
import AppContent from './AppContent';

const styles = {
  root: {
    width: '100%',
  },
};

class AppContainer extends React.Component {
  constructor(props) {
    super(props);

    this.state = {
      isModalOpen: false,
      modalData: {},
    };

    this.onImageClick = this.onImageClick.bind(this);
    this.onModalClose = this.onModalClose.bind(this);
  }

  onImageClick(image) {
    this.setState({
      isModalOpen: true,
      modalData: image,
    });
  }

  onModalClose() {
    this.setState({
      isModalOpen: false,
      modalData: {},
    });
  }

  render() {
    const {
      classes,
    } = this.props;
    const {
      isModalOpen,
      modalData,
    } = this.state;

    return (
      <div className={classes.root}>
        <AppContent
          onImageClick={this.onImageClick}
        />
        <ImageModal
          open={isModalOpen}
          onClose={this.onModalClose}
          data={modalData}
        />
      </div>
    );
  }
}

export default withStyles(styles)(AppContainer);
