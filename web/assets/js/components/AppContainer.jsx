import React from 'react';
import axios from 'axios';
import moment from 'moment';
import InfiniteLoader from 'react-infinite-loader';
import handleViewport from 'react-in-viewport';
import { withStyles } from '@material-ui/styles';
import Typography from '@material-ui/core/Typography';
import Container from '@material-ui/core/Container';
import Grid from '@material-ui/core/Grid';
import CircularProgress from '@material-ui/core/CircularProgress';
import Image from './Image';
import ImageModal from './ImageModal';

const styles = {
  circularProgressWrapper: {
    position: 'fixed',
    top: 16,
    right: 16,
  },
};

class AppContainer extends React.Component {
  constructor(props) {
    super(props);

    this.state = {
      files: [],
      isLoading: false,
      isLoaded: false,
      isModalOpen: false,
      modalData: {},
    };

    this.onImageClick = this.onImageClick.bind(this);
    this.onModalClose = this.onModalClose.bind(this);
    this.onDocumentBottom = this.onDocumentBottom.bind(this);
  }

  componentDidMount() {
    this.loadFiles();
  }

  loadFiles() {
    if (this.state.isLoading) {
      return false;
    }

    this.setState({
      isLoading: true,
    });

    // TODO: implement "after_id"
    axios.get(rootUrl + '/api/files?offset=' + this.state.files.length)
      .then(res => {
        const files = res.data.data;

        this.setState({
          files: this.state.files.concat(files),
          isLoading: false,
        });
      });
  }

  onImageClick(file) {
    this.setState({
      isModalOpen: true,
      modalData: file,
    });
  }

  onModalClose() {
    this.setState({
      isModalOpen: false,
      modalData: {},
    });
  }

  onDocumentBottom() {
    this.loadFiles();
  }

  renderGrid() {
    const { files } = this.state;

    let filesPerDate = {};
    files.forEach(file => {
      const date = moment(file.date).format('YYYY-MM-DD');

      if (typeof filesPerDate[date] === 'undefined') {
        filesPerDate[date] = [];
      }

      filesPerDate[date].push(file);
    });

    return Object.keys(filesPerDate).map(date => {
      return (
        <Grid container key={date} style={{ marginBottom: 20 }}>
          <Grid item xs={12}>
            <Typography variant="h3" component="h3">
              {date}
            </Typography>
          </Grid>
          {filesPerDate[date].map((file) => {
            return (
              <Grid item key={file.id} xs={3}>
                <Image
                  src={file.urls.thumbnail}
                  srcAfterLoad={file.urls.small}
                  onClick={this.onImageClick.bind(this, file)}
                />
              </Grid>
            )
          })}
        </Grid>
      )
    });
  }

  render() {
    const {
      classes,
    } = this.props;
    const {
      isLoading,
      isLoaded,
      isModalOpen,
      modalData,
    } = this.state;

    return (
      <div>
        {isLoading && (
          <div className={classes.circularProgressWrapper}>
            <CircularProgress size={80} />
          </div>
        )}
        <Container>
          {!isLoading && isLoaded && files.length === 0 &&
            <div>No files found.</div>
          }
          {this.renderGrid()}
        </Container>
        <ImageModal
          open={isModalOpen}
          onClose={this.onModalClose}
          data={modalData}
        />
        <InfiniteLoader onVisited={this.onDocumentBottom} />
      </div>
    );
  }
}

export default withStyles(styles)(AppContainer);
