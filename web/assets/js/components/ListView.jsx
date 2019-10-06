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
import AppNavigation from './AppNavigation';
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
    padding: 16,
  },
  infiniteLoaderContainer: {
    width: '100%',
    display: 'flex',
    flexGrow: 1,
    position: 'relative',
  },
  infiniteLoader: {
    position: 'relative',
    width: '100%',
    display: 'flex',
    flexGrow: 1,
    minHeight: 320,
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
    rowsFilesCountMap: state.rowsFilesCountMap,
    rowsTotalCount: state.rowsTotalCount,
    files: state.files,
    filesIdMap: state.filesIdMap,
    filesSummary: state.filesSummary,
    filesSummaryDatetime: state.filesSummaryDatetime,
    filesPerDate: state.filesPerDate,
    orderBy: state.orderBy,
    orderByDirection: state.orderByDirection,
    search: state.search,
    selectedType: state.selectedType,
    selectedYear: state.selectedYear,
    selectedYearMonth: state.selectedYearMonth,
    selectedDate: state.selectedDate,
    selectedCountry: state.selectedCountry,
    selectedCity: state.selectedCity,
    selectedLabel: state.selectedLabel,
  };
};

function mapDispatchToProps(dispatch) {
  return {
    setData: (type, data) => dispatch(setData(type, data)),
    setDataBatch: (data) => dispatch(setDataBatch(data)),
  };
}

class ListView extends React.Component {
  constructor(props) {
    super(props);

    this.parent = this.props.parent;

    this.rowsLoading = {};

    // Infinite loader
    this.infiniteLoaderContainerRef = React.createRef();
    this.infiniteLoaderRef = React.createRef();
    this.infiniteLoaderListRef = React.createRef();

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
    this.props.setData('view', 'list');
  }

  componentDidUpdate(prevProps) {
    if (prevProps.filesSummaryDatetime !== this.props.filesSummaryDatetime) {
      this.cache.clearAll();

      if (
        this.infiniteLoaderRef &&
        this.infiniteLoaderRef.current
      ) {
        this.infiniteLoaderRef.current.resetLoadMoreRowsCache(true);
      }

      if (
        this.infiniteLoaderListRef &&
        this.infiniteLoaderListRef.current
      ) {
        this.infiniteLoaderListRef.current.recomputeRowHeights();
      }

      this.lastQuery = null;
      this.lastOffset = null;
      this.rowsLoading = {};
    }
  }

  render() {
    const {
      classes,
      files,
      isLoading,
      isLoaded,
      orderBy,
      orderByDirection,
      search,
    } = this.props;

    return (
      <div className={classes.root}>
        <AppNavigation parent={this.parent} />
        {!isLoading && isLoaded && files.length === 0 &&
          <div>No files found.</div>
        }
        {isLoaded && (
          <div
            className={classes.infiniteLoaderContainer}
            ref={this.infiniteLoaderContainerRef}
          >
            <AutoSizer disableHeight>
              {({ width }) => (
                <InfiniteLoader
                  loadMoreRows={this._loadMoreRows}
                  isRowLoaded={this._isRowLoaded}
                  rowCount={this._getRowCount()}
                  minimumBatchSize={6}
                  threshold={8}
                  ref={this.infiniteLoaderRef}
                >
                  {({ onRowsRendered, registerChild }) => (
                    <WindowScroller>
                      {({ height, isScrolling, onChildScroll, scrollTop }) => (
                        <List
                          autoHeight
                          height={height}
                          width={width}
                          onRowsRendered={onRowsRendered}
                          rowCount={this._getRowCount()}
                          rowHeight={this.cache.rowHeight}
                          rowRenderer={this._rowRenderer}
                          deferredMeasurementCache={this.cache}
                          isScrolling={isScrolling}
                          onScroll={onChildScroll}
                          scrollTop={scrollTop}
                          overscanRowCount={4}
                          ref={el => {
                            this.infiniteLoaderListRef.current = el;
                            registerChild(el);
                          }}
                        />
                    )}
                    </WindowScroller>
                  )}
                </InfiniteLoader>
              )}
            </AutoSizer>
          </div>
        )}
      </div>
    );
  }

  /***** Infinite loader stuff *****/
  _prepareRowsPerIndex(files, startIndex, stopIndex) {
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
    filesSummary.date.date.forEach((data) => {
      dateMap[data.date] = Object.keys(dateMap).length;
    });

    files.forEach((file) => {
      let fileDate = file.date;

      // We shall remove the timezone from the string,
      //   else it won't find the date inside filesSummary,
      //   if it's before noon
      if (fileDate.indexOf('+') !== -1) {
        fileDate = fileDate.split('+')[0];
      }

      // hack, so it won't parse the timezone
      const dateMoment = moment.parseZone(file.date);

      const date = dateMoment.format('YYYY-MM-DD');
      const hasReachedMaxFilesPerRow = row.files.length >= this.parent.parent.maxFilesPerRow;

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
        const countOnDate = typeof filesSummary.date.date[dateMap[date]] !== 'undefined'
          ? filesSummary.date.date[dateMap[date]].count
          : '?'; // That just means, that new images were added since the we last fetched the summary
        // Partially we prevent this with created_before, but it's still possible,
        //   that 2 or more images were added the same second, and that would cause the issue.

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

    // Add the remaining row, if it has any files left in
    if (row.files.length > 0) {
      rows.push(row);
    }

    this.props.setData('rows', rows);
  }

  _loadMoreRows({ startIndex, stopIndex }) {
    const {
      rowsFilesCountMap,
      filesSummaryDatetime,
    } = this.props;

    for (let index = startIndex; index <= stopIndex; index++) {
      if (this._isRowLoaded({ index })) {
        startIndex++;
      }
    }

    const query = this.parent.getFiltersQuery();
    const offsetAndLimit = this.parent.getOffsetAndLimitByIndexes(startIndex, stopIndex);
    const offset = offsetAndLimit[0];
    const limit = offsetAndLimit[1];

    // Prevent doing a request if it's the same query and offset
    //   or if limit is 0, which basically means, that we are at the end of the page
    if (
      (
        this.lastQuery === query &&
        this.lastOffset === offset
      ) ||
      limit === 0
    ) {
      return;
    }

    this.lastQuery = query;
    this.lastOffset = offset;

    return new Promise((resolve, reject) => {
      this._setRowsLoading(startIndex, stopIndex);
      this.props.setData('isDataLoading', true);

      const url = rootUrl + '/api/files' + query +
        '&offset=' + offset +
        '&limit=' + limit +
        '&created_before=' + filesSummaryDatetime.format('YYYY-MM-DDTHH:mm:ss');

      return axios.get(url)
        .then(res => {
          const requestFiles = res.data.data;
          let {
            files,
            filesIdMap,
          } = this.props;

          requestFiles.forEach(file => {
            if (!filesIdMap.includes(file.id)) {
              files.push(file);
              filesIdMap.push(file.id);
            }
          });

          this.props.setDataBatch({
            files,
            filesIdMap,
            isDataLoading: false,
          });

          this._setRowsLoaded(startIndex, stopIndex);

          this._prepareRowsPerIndex(files, startIndex, stopIndex);

          resolve();
        })
        .catch((error) => {
          if (axios.isCancel(error)) {
            // Request was canceled
          } else {
            reject(error);
          }

          this.props.setData('isDataLoading', false);
        });
    });
  }

  _isRowLoaded({ index }) {
    const {
      rows,
    } = this.props;

    const isLoaded = !!rows[index];
    if (
      !isLoaded &&
      this.rowsLoading[index] === true
    ) {
      return true;
    }

    return isLoaded;
  }

  _getRowCount() {
    const {
      rowsTotalCount,
    } = this.props;

    return rowsTotalCount;
  }

  _rowRenderer({ index, key, parent, style, isVisible, isScrolling }) {
    const {
      classes,
      rows,
    } = this.props;

    const row = rows[index];

    if (!row) {
      return;
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
              isScrolling={isScrolling}
              container={container}
              onReady={measure}
              onClick={this.props.onImageClick}
            />
          </div>
        )}
      </CellMeasurer>
    );
  }

  _setRowsLoading(startIndex, stopIndex) {
    for (let i = startIndex; i <= stopIndex; i++) {
      this.rowsLoading[i] = true;
    }
  }

  _setRowsLoaded(startIndex, stopIndex) {
    for (let i = startIndex; i <= stopIndex; i++) {
      this.rowsLoading[i] = false;
    }
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(
  withStyles(styles)(ListView)
);
