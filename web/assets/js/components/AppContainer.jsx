import React from 'react';
import axios from 'axios';
import moment from 'moment';
import {
  InfiniteLoader,
  AutoSizer,
  CellMeasurer,
  CellMeasurerCache,
  List,
} from 'react-virtualized';
import { withStyles } from '@material-ui/styles';
import Typography from '@material-ui/core/Typography';
import Container from '@material-ui/core/Container';
import Grid from '@material-ui/core/Grid';
import CircularProgress from '@material-ui/core/CircularProgress';
import Image from './Image';
import ImageModal from './ImageModal';

import 'react-virtualized/styles.css';

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

// Get some inspirations from here?
//   https://medium.com/@danrschlosser/building-the-image-grid-from-google-photos-6a09e193c74a
//   or
//   https://medium.com/@albertjuhe/an-easy-to-use-performant-solution-to-lazy-load-images-in-react-e6752071020c

class AppContainer extends React.Component {
  constructor(props) {
    super(props);

    this.state = {
      files: [],
      filesMap: [],
      filesPerDate: {},
      filesSummary: {},
      isLoading: false,
      isLoaded: false,
      isModalOpen: false,
      modalData: {},
    };

    this.onImageClick = this.onImageClick.bind(this);
    this.onModalClose = this.onModalClose.bind(this);

    this.cache = new CellMeasurerCache({
      fixedWidth: true,
      defaultHeight: 220,
      keyMapper: () => 1,
    });
    this._loadMoreRows = this._loadMoreRows.bind(this);
    this._isRowLoaded = this._isRowLoaded.bind(this);
    this._getRowCount = this._getRowCount.bind(this);
    this._rowRenderer = this._rowRenderer.bind(this);
  }

  componentDidMount() {
    axios.get(rootUrl + '/api/files/summary')
      .then(res => {
        this.setState({
          filesSummary: res.data.data,
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

  renderInfiniteLoader() {
    return (
      <InfiniteLoader
        loadMoreRows={this._loadMoreRows}
        isRowLoaded={this._isRowLoaded}
        rowCount={this._getRowCount()}
      >
        {({ onRowsRendered, registerChild }) => (
          <div style={{ display: 'flex' }}>
            <div style={{ flex: '1 1 auto', height: '100vh' }}>
              <AutoSizer>
                {({ height, width }) => (
                  <div>
                    <List
                      height={height}
                      width={width}
                      onRowsRendered={onRowsRendered}
                      ref={registerChild}
                      rowCount={this._getRowCount()}
                      overscanRowCount={3}
                      deferredMeasurementCache={this.cache}
                      rowHeight={this.cache.rowHeight}
                      rowRenderer={this._rowRenderer}
                      width={width}
                    />
                  </div>
                )}
              </AutoSizer>
            </div>
          </div>
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
      <div>
        {isLoading && (
          <div className={classes.circularProgressWrapper}>
            <CircularProgress size={80} />
          </div>
        )}
        {!isLoading && isLoaded && files.length === 0 &&
          <div>No files found.</div>
        }
        {this.renderInfiniteLoader()}
        <ImageModal
          open={isModalOpen}
          onClose={this.onModalClose}
          data={modalData}
        />
      </div>
    );
  }

  /***** Infinite loader stuff *****/
  _loadMoreRows({ startIndex, stopIndex }) {
    const { filesSummary } = this.state;

    const fromDate = filesSummary.count_per_date[stopIndex].date;
    const toDate = filesSummary.count_per_date[startIndex].date;

    clearTimeout(this.loadRowsTimeout);
    return new Promise((resolve, reject) => {
      this.loadRowsTimeout = setTimeout(() => {
        this.setState({
          isLoading: true,
        });

        return axios.get(rootUrl + '/api/files?from_date=' + fromDate + '&to_date=' + toDate)
          .then(res => {
            const requestFiles = res.data.data;
            let {
              files,
              filesMap,
              filesPerDate
            } = this.state;

            requestFiles.forEach(file => {
              if (!filesMap.includes(file.id)) {
                const date = moment(file.date).format('YYYY-MM-DD');

                if (typeof filesPerDate[date] === 'undefined') {
                  filesPerDate[date] = [];
                }

                files.push(file);
                filesMap.push(file.id);
                filesPerDate[date].push(file);
              }
            });

            this.setState({
              files,
              filesMap,
              filesPerDate,
              isLoading: false,
            });

            resolve();
          });
      }, 500);
    });
  }

  _isRowLoaded({ index }) {
    const {
      filesSummary,
      filesPerDate,
    } = this.state;

    if (!filesSummary.count_per_date) {
      return false;
    }

    const date = filesSummary.count_per_date[index].date;

    return typeof filesPerDate[date] !== 'undefined';
  }

  _getRowCount() {
    const { filesSummary } = this.state;

    if (!filesSummary.count_per_date) {
      return 0;
    }

    return filesSummary.count_per_date.length;
  }

  _rowRenderer({ index, key, parent, style, isVisible, isScrolling }) {
    const { classes } = this.props;
    const { filesSummary, filesPerDate } = this.state;

    const date = filesSummary.count_per_date[index].date;
    const now = moment();
    const dateMoment = moment(date);
    const isTooLongAgo = now.diff(dateMoment, 'days', true) > 28;

    function content(measure, onImageClick) {
      const files = filesPerDate[date]
        ? filesPerDate[date]
        : null;

      // TODO: probably also need to split into multiple rows,
      //    if too much images?

      return (
        <div>
          {files && (
            <Grid container>
              {files.map((file) => {
                // TODO: set debounce on measure() -- note: AwesomeDebouncePromise() works (did work),
                //   but there's an issue when the image gets unmounted (out of viewport)
                //   it triggers an error.
                // TODO: implement "isVisible", to cancel image loading, when out of viewport
                return (
                  <Grid item xs={2} key={file.id}>
                    <Image
                      src={file.images.thumbnail.src}
                      srcAfterLoad={file.images.preview.src}
                      onClick={onImageClick.bind(this, file)}
                      onLoad={measure}
                    />
                  </Grid>
                )
              })}
            </Grid>
          )}
        </div>
      );
    }

    return (
      <CellMeasurer
        key={key}
        cache={this.cache}
        parent={parent}
        columnIndex={0}
        rowIndex={index}
      >
        {({ measure }) => (
          <div style={style}>
            <Typography
              variant="h4"
              component="h4"
              className={classes.gridDateSubHeading}
            >
              {!isTooLongAgo &&
                <span><b>{dateMoment.fromNow()}</b> -{' '}</span>
              }
              {dateMoment.format('ddd, DD MMM YYYY')} --{' '}
              <small><i>{filesSummary.count_per_date[index].count} items</i></small>
            </Typography>
            {content(measure, this.onImageClick)}
          </div>
        )}
      </CellMeasurer>
    );
  }
}

export default withStyles(styles)(AppContainer);
