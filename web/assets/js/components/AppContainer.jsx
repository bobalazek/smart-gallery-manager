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
import ImageGrid from './ImageGrid';

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

class AppContainer extends React.Component {
  constructor(props) {
    super(props);

    this.state = {
      rows: [],
      rowsIndexes: [],
      files: [],
      filesMap: [],
      filesSummary: {},
      isLoading: false,
      isLoaded: false,
      isModalOpen: false,
      modalData: {},
    };

    this.onImageClick = this.onImageClick.bind(this);
    this.onModalClose = this.onModalClose.bind(this);

    this.maxFilesPerRow = 40;

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
        const filesSummary = res.data.data;
        this.setState({
          filesSummary,
          isLoaded: true,
        });

        this._prepareRowsIndexes(filesSummary);
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
      files,
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
        {isLoaded && this.renderInfiniteLoader()}
        <ImageModal
          open={isModalOpen}
          onClose={this.onModalClose}
          data={modalData}
        />
      </div>
    );
  }

  /***** Infinite loader stuff *****/
  _prepareRowsIndexes(filesSummary) {
    let rowsIndexes = [];

    filesSummary.count_per_date.forEach((data) => {
      if (data.count <= this.maxFilesPerRow) {
        rowsIndexes.push(data.date);
      } else {
        const totalRows = Math.round(data.count / this.maxFilesPerRow);
        for(let i = 0; i < totalRows; i++) {
          rowsIndexes.push(data.date);
        }
      }
    });

    this.setState({
      rowsIndexes,
    });
  }

  _prepareRowsPerIndex(files) {
    const { filesSummary } = this.state;

    const now = moment();

    let rows = [];
    let lastDate = '';
    let row = {
      files: [],
    };

    let dateToIndex = {};
    filesSummary.count_per_date.forEach((data) => {
      dateToIndex[data.date] = Object.keys(dateToIndex).length;
    });

    files.forEach((file, index) => {
      let fileDate = file.date;
      // We shall remote the timezone from the string,
      //   else it won't find the date inside filesSummary,
      //   if it's before noon
      if (fileDate.indexOf('+') !== -1) {
        fileDate = fileDate.split('+')[0];
      }

      // hack, so it won't parse the timezone
      const dateMoment = moment.parseZone(file.date);
      const date = dateMoment.format('YYYY-MM-DD');
      const hasReachedMaxFilesPerRow = row.files.length >= this.maxFilesPerRow;

      if (
        (
          lastDate !== '' &&
          date !== lastDate
        ) ||
        hasReachedMaxFilesPerRow
      ) {
        rows.push(row);
        row = {
          files: [],
        };
      }

      row.files.push(file);

      if (date !== lastDate) {
        const count = filesSummary.count_per_date[dateToIndex[date]].count;

        const isTooLongAgo = now.diff(dateMoment, 'days', true) > 28;
        if (!isTooLongAgo) {
          row.heading = {
            relative_time: dateMoment.fromNow(),
            date: dateMoment.format('ddd, DD MMM YYYY'),
            items_count: count,
          };
        } else {
          row.heading = {
            date: dateMoment.format('ddd, DD MMM YYYY'),
            items_count: count,
          };
        }
      }

      lastDate = date;
    });

    this.setState({
      rows,
    });
  }

  _loadMoreRows({ startIndex, stopIndex }) {
    const { rowsIndexes } = this.state;

    const fromDate = rowsIndexes[stopIndex];
    const toDate = rowsIndexes[startIndex];

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
            } = this.state;

            requestFiles.forEach(file => {
              if (!filesMap.includes(file.id)) {
                files.push(file);
                filesMap.push(file.id);
              }
            });

            this.setState({
              files,
              filesMap,
              isLoading: false,
            });

            this._prepareRowsPerIndex(files);

            resolve();
          });
      }, 500);
    });
  }

  _isRowLoaded({ index }) {
    const { rows } = this.state;

    return typeof rows[index] !== 'undefined';
  }

  _getRowCount() {
    const { rowsIndexes } = this.state;

    return rowsIndexes.length;
  }

  _rowRenderer({ index, key, parent, style, isVisible, isScrolling }) {
    const { classes } = this.props;
    const { rows } = this.state;

    const row = rows[index];

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
            {row.heading &&
              <Typography
                variant="h4"
                component="h4"
                className={classes.gridDateSubHeading}
              >
                <React.Fragment>
                  {row.heading.relative_time &&
                    <span><b>{row.heading.relative_time}</b> -{' '}</span>
                  }
                  {row.heading.date} --{' '}
                  <small><i>{row.heading.items_count} items</i></small>
                </React.Fragment>
              </Typography>
            }
            <div>
              {row.files && (
                <Grid container>
                  {row.files.map((file) => {
                    // TODO: set debounce on measure() -- note: AwesomeDebouncePromise() works (did work),
                    //   but there's an issue when the image gets unmounted (out of viewport)
                    //   it triggers an error.
                    // TODO: implement "isVisible", to cancel image loading, when out of viewport
                    return (
                      <Grid item xs={2} key={file.id}>
                        <Image
                          src={file.images.thumbnail.src}
                          srcAfterLoad={file.images.preview.src}
                          onClick={this.onImageClick.bind(this, file)}
                          onLoad={measure}
                        />
                      </Grid>
                    )
                  })}
                </Grid>
              )}
            </div>
          </div>
        )}
      </CellMeasurer>
    );
  }
}

export default withStyles(styles)(AppContainer);
