import React from 'react';
import { connect } from 'react-redux';
import axios from 'axios';
import moment from 'moment';
import {
  InfiniteLoader,
  WindowScroller,
  AutoSizer,
  CellMeasurer,
  CellMeasurerCache,
  List,
} from 'react-virtualized';
import { withStyles } from '@material-ui/styles';
import CircularProgress from '@material-ui/core/CircularProgress';
import FormControl from '@material-ui/core/FormControl';
import InputLabel from '@material-ui/core/InputLabel';
import MenuItem from '@material-ui/core/MenuItem';
import Select from '@material-ui/core/Select';
import Image from './Image';
import ImageGrid from './ImageGrid';
import {
  setData,
  setDataBatch,
} from '../actions/index';

import 'react-virtualized/styles.css';

const styles = {
  root: {
    width: '100%',
    flexGrow: 1,
  },
  circularProgressWrapper: {
    position: 'fixed',
    top: 32,
    right: '50%',
    marginLeft: -40,
    zIndex: 9999,
  },
  infiniteLoader: {
    position: 'relative',
    width: '100%',
    display: 'flex',
    flexGrow: 1,
    minHeight: 64,
  },
  infiniteLoaderInner: {
    height: '100%',
    width: '100%',
  },
};

const mapStateToProps = state => {
  return {
    isLoading: state.isLoading,
    isLoaded: state.isLoaded,
    rows: state.rows,
    rowsIndexes: state.rowsIndexes,
    files: state.files,
    filesMap: state.filesMap,
    filesSummary: state.filesSummary,
    orderBy: state.orderBy,
  };
};

function mapDispatchToProps(dispatch) {
  return {
    setData: (type, data) => dispatch(setData(type, data)),
    setDataBatch: (data) => dispatch(setDataBatch(data)),
  };
}

class AppContent extends React.Component {
  constructor(props) {
    super(props);

    this.onOrderChange = this.onOrderChange.bind(this);
    this.fetchFilesSummary = this.fetchFilesSummary.bind(this);

    // Infinite loader
    this.infiniteLoaderContainerRef = React.createRef();

    this.maxFilesPerRow = 40;

    this.cache = new CellMeasurerCache({
      fixedWidth: true,
      defaultHeight: 310,
    });
    this._loadMoreRows = this._loadMoreRows.bind(this);
    this._isRowLoaded = this._isRowLoaded.bind(this);
    this._getRowCount = this._getRowCount.bind(this);
    this._rowRenderer = this._rowRenderer.bind(this);
  }

  componentDidMount() {
    this.fetchFilesSummary();
  }

  onOrderChange(event) {
    const orderBy = event.target.value;

    this.props.setData('orderBy', orderBy);

    this.fetchFilesSummary(orderBy);
  }

  fetchFilesSummary(orderBy) {
    if (!orderBy) {
      orderBy = this.props.orderBy;
    }

    this.props.setData('isLoading', true);

    axios.get(rootUrl + '/api/files/summary?order_by=' + orderBy)
      .then(res => {
        const filesSummary = res.data.data;
        let rowsIndexes = [];

        filesSummary.count.date.forEach((data) => {
          if (data.count <= this.maxFilesPerRow) {
            rowsIndexes.push(data.date);
          } else {
            const totalRows = Math.round(data.count / this.maxFilesPerRow);
            for(let i = 0; i < totalRows; i++) {
              rowsIndexes.push(data.date);
            }
          }
        });

        this.props.setDataBatch({
          rows: [],
          rowsIndexes: rowsIndexes,
          files: [],
          filesMap: [],
          filesSummary: filesSummary,
          isLoaded: true,
          isLoading: false,
        });

        this.cache.clearAll();
      });
  }

  render() {
    const {
      classes,
      files,
      isLoading,
      isLoaded,
      orderBy,
    } = this.props;

    return (
      <div className={classes.root}>
        {isLoading && (
          <div className={classes.circularProgressWrapper}>
            <CircularProgress size={80} />
          </div>
        )}
        <div style={{ marginBottom: 20 }}>
          <FormControl>
            <Select
              value={orderBy}
              onChange={this.onOrderChange}
              name="orderBy"
            >
              <MenuItem value={'taken_at'}>Date taken</MenuItem>
              <MenuItem value={'created_at'}>Date created</MenuItem>
            </Select>
          </FormControl>
        </div>
        {!isLoading && isLoaded && files.length === 0 &&
          <div>No files found.</div>
        }
        {isLoaded && (
          <div
            className={classes.infiniteLoaderContainer}
            ref={this.infiniteLoaderContainerRef}
          >
            <InfiniteLoader
              loadMoreRows={this._loadMoreRows}
              isRowLoaded={this._isRowLoaded}
              rowCount={this._getRowCount()}
            >
              {({ onRowsRendered, registerChild }) => (
                <div className={classes.infiniteLoader}>
                  <div className={classes.infiniteLoaderInner}>
                    <AutoSizer>
                      {({ height, width }) => (
                        <WindowScroller>
                          {({ height, isScrolling, onChildScroll, scrollTop }) => (
                            <List
                              autoHeight
                              height={height}
                              width={width}
                              overscanRowCount={3}
                              onRowsRendered={onRowsRendered}
                              rowCount={this._getRowCount()}
                              rowHeight={this.cache.rowHeight}
                              rowRenderer={this._rowRenderer}
                              deferredMeasurementCache={this.cache}
                              isScrolling={isScrolling}
                              onScroll={onChildScroll}
                              scrollTop={scrollTop}
                              ref={registerChild}
                            />
                        )}
                        </WindowScroller>
                      )}
                    </AutoSizer>
                  </div>
                </div>
              )}
            </InfiniteLoader>
          </div>
        )}
      </div>
    );
  }

  /***** Infinite loader stuff *****/
  _prepareRowsPerIndex(files) {
    const {
      filesSummary,
    } = this.props;

    const now = moment();

    let rows = [];
    let lastDate = '';
    let row = {
      files: [],
    };

    let dateMap = {};
    filesSummary.count.date.forEach((data) => {
      dateMap[data.date] = Object.keys(dateMap).length;
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
        const countOnDate = filesSummary.count.date[dateMap[date]].count;
        // TODO: if that fails, it means, that it's very likely, that we are doing
        //   scanning in the background. It finds an image on newer date,
        //   but that date does not yet exist in the summary,
        //   so we'll need to reload the filesSummary.
        // Alternativly, we could maybe pass a created_before parameter,
        //   where you'd get only images, that were loader before (same time),
        //   as we fetched our filesSummary data.

        const isTooLongAgo = now.diff(dateMoment, 'days', true) > 28;
        if (!isTooLongAgo) {
          row.heading = {
            relative_time: dateMoment.fromNow(),
            date: dateMoment.format('ddd, DD MMM YYYY'),
            items_count: countOnDate,
          };
        } else {
          row.heading = {
            date: dateMoment.format('ddd, DD MMM YYYY'),
            items_count: countOnDate,
          };
        }
      }

      lastDate = date;
    });

    this.props.setData('rows', rows);
  }

  _loadMoreRows({ startIndex, stopIndex }) {
    const {
      rowsIndexes,
      orderBy,
    } = this.props;

    const dateFrom = rowsIndexes[stopIndex];
    const dateTo = rowsIndexes[startIndex];

    clearTimeout(this.loadRowsTimeout);
    return new Promise((resolve, reject) => {
      this.loadRowsTimeout = setTimeout(() => {
        this.props.setData('isLoading', true);

        const url = rootUrl + '/api/files?order_by=' + orderBy +
          '&date_from=' + dateFrom +
          '&date_to=' + dateTo;

        return axios.get(url)
          .then(res => {
            const requestFiles = res.data.data;
            let {
              files,
              filesMap,
            } = this.props;

            requestFiles.forEach(file => {
              if (!filesMap.includes(file.id)) {
                files.push(file);
                filesMap.push(file.id);
              }
            });

            this.props.setDataBatch({
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
    const { rows } = this.props;

    return typeof rows[index] !== 'undefined';
  }

  _getRowCount() {
    const { rowsIndexes } = this.props;

    return rowsIndexes.length;
  }

  _rowRenderer({ index, key, parent, style, isVisible, isScrolling }) {
    const {
      classes,
      rows,
    } = this.props;

    const row = rows[index];

    if (!row) {
      return '';
    }

    const container = this.infiniteLoaderContainerRef.current;

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
            <ImageGrid
              heading={row.heading}
              files={row.files}
              isVisible={isVisible}
              container={container}
              onReady={measure}
              onClick={this.props.onImageClick}
            />
          </div>
        )}
      </CellMeasurer>
    );
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(
  withStyles(styles)(AppContent)
);
