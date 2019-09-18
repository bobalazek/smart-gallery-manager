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
import Grid from '@material-ui/core/Grid';
import CircularProgress from '@material-ui/core/CircularProgress';
import FormControl from '@material-ui/core/FormControl';
import InputLabel from '@material-ui/core/InputLabel';
import MenuItem from '@material-ui/core/MenuItem';
import Select from '@material-ui/core/Select';
import TextField from '@material-ui/core/TextField';
import OutlinedInput from '@material-ui/core/OutlinedInput';
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
  circularProgressWrapper: {
    position: 'absolute',
    top: 32,
    right: '50%',
    marginLeft: -40,
    zIndex: 9999,
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
    rowsDateMap: state.rowsDateMap,
    files: state.files,
    filesIdMap: state.filesIdMap,
    filesSummary: state.filesSummary,
    filesSummaryDatetime: state.filesSummaryDatetime,
    orderBy: state.orderBy,
    orderByDirection: state.orderByDirection,
    search: state.search,
    selectedType: state.selectedType,
    selectedYear: state.selectedYear,
    selectedYearMonth: state.selectedYearMonth,
    selectedDate: state.selectedDate,
    selectedCountry: state.selectedCountry,
    selectedCity: state.selectedCity,
    selectedTag: state.selectedTag,
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

    this.onChange = this.onChange.bind(this);

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
    this.fetchFilesSummary();
  }

  componentDidUpdate(prevProps) {
    if (
      prevProps.selectedType !== this.props.selectedType ||
      prevProps.selectedYear !== this.props.selectedYear ||
      prevProps.selectedYearMonth !== this.props.selectedYearMonth ||
      prevProps.selectedDate !== this.props.selectedDate ||
      prevProps.selectedCountry !== this.props.selectedCountry ||
      prevProps.selectedCity !== this.props.selectedCity ||
      prevProps.selectedTag !== this.props.selectedTag
    ) {
      this.fetchFilesSummary();
    }
  }

  onChange(event) {
    const name = event.target.name;
    const value = event.target.value;

    this.props.setData(name, value);

    if (name === 'orderBy') {
      this.fetchFilesSummary(value, this.props.orderByDirection);
    } else {
      clearTimeout(this.searchTimer)
      this.searchTimer = setTimeout(() => {
        this.fetchFilesSummary();
      }, 500);
    }
  }

  fetchFilesSummary(orderBy, orderByDirection) {
    this.parent.fetchFilesSummary(orderBy, orderByDirection)
      .then(() => {
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
      });
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
        {isLoading && (
          <div className={classes.circularProgressWrapper}>
            <CircularProgress size={80} />
          </div>
        )}
        <Grid
          container
          justify="space-between"
          style={{ marginBottom: 20 }}
        >
          <Grid item>
            <FormControl variant="outlined">
              <Select
                name="orderBy"
                value={orderBy}
                onChange={this.onChange}
                input={<OutlinedInput name="orderBy" />}
              >
                <MenuItem value="taken_at">Date taken</MenuItem>
                <MenuItem value="created_at">Date created</MenuItem>
              </Select>
            </FormControl>
            <FormControl variant="outlined">
              <Select
                name="orderByDirection"
                value={orderByDirection}
                onChange={this.onChange}
                input={<OutlinedInput name="age" />}
              >
                <MenuItem value="DESC">Descending</MenuItem>
                <MenuItem value="ASC">Ascending</MenuItem>
              </Select>
            </FormControl>
          </Grid>
          <Grid item>
            <TextField
              name="search"
              label="Search"
              type="search"
              variant="outlined"
              fullWidth
              value={search}
              onChange={this.onChange}
            />
          </Grid>
        </Grid>
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
                  threshold={4}
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
  _prepareRowsPerIndex(files) {
    const {
      filesSummary,
    } = this.props;

    const now = moment.parseZone();

    let rows = [];
    let lastDate = '';
    let row = {
      files: [],
    };

    let dateMap = {};
    filesSummary.date.date.forEach((data) => {
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
      const hasReachedMaxFilesPerRow = row.files.length >= this.parent.maxFilesPerRow;

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

    // It's very likely that there will be remaining files
    // in the last row after the loop, so add it to the rows.
    if (row.files.length > 0) {
      rows.push(row);
    }

    this.props.setData('rows', rows);
  }

  _loadMoreRows({ startIndex, stopIndex }) {
    const {
      filesSummaryDatetime,
    } = this.props;

    return new Promise((resolve, reject) => {
      const query = this.parent.getFiltersQuery();
      const limit = this.parent.maxFilesPerRow * ((stopIndex - startIndex) + 1);
      const offset = this.parent.maxFilesPerRow * startIndex;

      // Prevent doing a request if it's the same query and offset
      if (
        this.lastQuery === query &&
        this.lastOffset === offset
      ) {
        resolve();

        return;
      }

      this.lastQuery = query;
      this.lastOffset = offset;

      this.props.setData('isLoading', true);

      const url = rootUrl + '/api/files' + query +
        '&limit=' + limit +
        '&offset=' + offset +
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
            isLoading: false,
          });

          this._prepareRowsPerIndex(files);

          resolve();
        });
    });
  }

  _isRowLoaded({ index }) {
    const {
      rows,
    } = this.props;

    return !!rows[index];
  }

  _getRowCount() {
    const {
      rowsDateMap,
    } = this.props;

    return rowsDateMap.length;
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
}

export default connect(mapStateToProps, mapDispatchToProps)(
  withStyles(styles)(ListView)
);
