import React from 'react';
import axios from 'axios';
import moment from 'moment';
import InfiniteScroll from 'react-infinite-scroller';
import { InfiniteLoader, AutoSizer, List } from 'react-virtualized';
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
    zIndex: 9999,
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
      filesPerDate: {},
      isLoading: false,
      isLoaded: false,
      isModalOpen: false,
      modalData: {},
    };

    this.onImageClick = this.onImageClick.bind(this);
    this.onModalClose = this.onModalClose.bind(this);
    this.onLoadFiles = this.onLoadFiles.bind(this);

    this._loadMoreRows = this._loadMoreRows.bind(this);
    this._isRowLoaded = this._isRowLoaded.bind(this);
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

  onLoadFiles() {
    if (this.state.isLoading) {
      return false;
    }

    this.setState({
      isLoading: true,
    });

    return axios.get(rootUrl + '/api/files?offset=' + this.state.files.length)
      .then(res => {
        const files = this.state.files.concat(res.data.data);

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

        this.setState({
          files,
          filesPerDate,
          isLoading: false,
        });
      });
  }

  renderGrid() {
    const { classes } = this.props;
    const { filesPerDate } = this.state;
    const now = moment();

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
                  <span><b>{dateMoment.fromNow()}</b> -{' '}</span>
                }
                {dateMoment.format('ddd, DD MMM YYYY')} --{' '}
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

  renderInfiniteLoader() {
    return (
      <InfiniteLoader
        loadMoreRows={this._loadMoreRows}
        isRowLoaded={this._isRowLoaded}

        rowCount={this._getRowCount}
      >
        {({ onRowsRendered, registerChild }) => (
          <AutoSizer>
            {({ height, width }) => (
              <List
                height={height}
                width={width}
                onRowsRendered={onRowsRendered}
                ref={registerChild}
                rowCount={this._getRowCount}
                rowHeight={200}
                rowRenderer={this._rowRenderer}
                width={width}
              />
            )}
          </AutoSizer>
        )}
      </InfiniteLoader>
    );
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
      <div style={{ padding: '0 16px' }}>
        {isLoading && (
          <div className={classes.circularProgressWrapper}>
            <CircularProgress size={80} />
          </div>
        )}
        {!isLoading && isLoaded && files.length === 0 &&
          <div>No files found.</div>
        }
        <InfiniteScroll
          loadMore={this.onLoadFiles}
          hasMore={true}
        >
          {this.renderGrid()}
        </InfiniteScroll>
        <ImageModal
          open={isModalOpen}
          onClose={this.onModalClose}
          data={modalData}
        />
      </div>
    );
  }

  _loadMoreRows({ startIndex, stopIndex }) {
    // TODO
  }

  _isRowLoaded({ index }) {
    return false; // TODO
  }
}

export default withStyles(styles)(AppContainer);
