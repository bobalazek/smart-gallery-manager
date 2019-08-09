import React from 'react';
import axios from 'axios';
import moment from 'moment';
import InfiniteLoader from 'react-infinite-loader';
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
  gridDateHeading: {
    marginTop: 40,
    marginBottom: 0,
    fontSize: 32,
  },
  gridDateSubHeading: {
    marginTop: 30,
    marginBottom: 10,
    fontSize: 24,
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
    const { classes } = this.props;
    const { files } = this.state;
    const now = moment();

    let filesPerDate = {};
    files.forEach(file => {
      const month = moment(file.date).format('YYYY-MM');
      const date = moment(file.date).format('YYYY-MM-DD');

      if (typeof filesPerDate[month] === 'undefined') {
        filesPerDate[month] = {};
      }

      if (typeof filesPerDate[month][date] === 'undefined') {
        filesPerDate[month][date] = [];
      }

      filesPerDate[month][date].push(file);
    });

    return Object.keys(filesPerDate).map(month => {
      const filesPerDates = Object.keys(filesPerDate[month]).map(date => {
        const dateMoment = moment(date);
        const isTooLongAgo = now.diff(dateMoment, 'days', true) > 28;

        return (
          <Grid container key={date}>
            <Grid item xs={12}>
              <Typography
                variant="h4"
                component="h4"
                className={classes.gridDateSubHeading}
              >
                {!isTooLongAgo &&
                  <span><b>{dateMoment.fromNow()}</b>,{' '}</span>
                }
                {dateMoment.format('DD MMM YYYY')} --{' '}
                <small><i>{filesPerDate[month][date].length} items</i></small>
              </Typography>
            </Grid>
            {filesPerDate[month][date].map((file) => {
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

      return (
        <Grid container key={month}>
          <Grid item xs={12}>
            <Typography
              variant="h4"
              component="h4"
              className={classes.gridDateHeading}
            >
              {moment(month).format('MMM YYYY')}
            </Typography>
          </Grid>
          <Grid item xs={12}>
            {filesPerDates}
          </Grid>
        </Grid>
      );
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
