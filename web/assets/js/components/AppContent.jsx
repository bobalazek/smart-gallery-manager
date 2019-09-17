import React from 'react';
import axios from 'axios';
import moment from 'moment';
import { connect } from 'react-redux';
import { withStyles } from '@material-ui/styles';
import ListView from './ListView';
import MapView from './MapView';
import {
  setData,
  setDataBatch,
} from '../actions/index';

const styles = {
  root: {
    width: '100%',
    flexGrow: 1,
  },
};

const mapStateToProps = state => {
  return {
    view: state.view,
    isLoading: state.isLoading,
    isLoaded: state.isLoaded,
    rows: state.rows,
    rowsIndexes: state.rowsIndexes,
    files: state.files,
    filesMap: state.filesMap,
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

class AppContent extends React.Component {
  constructor(props) {
    super(props);

    this.maxFilesPerRow = 50;
  }

  fetchFilesSummary(orderBy, orderByDirection) {
    this.props.setData('isLoading', true);

    return new Promise((resolve, reject) => {
      let url = rootUrl + '/api/files/summary' + this.getFiltersQuery(orderBy, orderByDirection);

      this.requestCancelToken && this.requestCancelToken();

      axios.get(url, {
        cancelToken: new axios.CancelToken((cancelToken) => {
          this.requestCancelToken = cancelToken;
        }),
      })
        .then(res => {
          const filesSummary = res.data.data;
          let rowsIndexes = [];

          filesSummary.date.date.forEach((data) => {
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
            rowsIndexes,
            files: [],
            filesMap: [],
            filesSummary,
            filesSummaryDatetime: moment.parseZone(),
            isLoaded: true,
            isLoading: false,
          });

          resolve();
        })
        .catch((error) => {
          if (axios.isCancel(error)) {
            // Request was canceled
          } else {
            reject(error);
          }
        });
    });
  }

  getFiltersQuery(forcedOrderBy, forcedOrderByDirection) {
    const {
      selectedType,
      selectedYear,
      selectedYearMonth,
      selectedDate,
      selectedCountry,
      selectedCity,
      selectedTag,
      orderBy,
      orderByDirection,
      search,
    } = this.props;

    let query = '?order_by=' + (forcedOrderBy ? forcedOrderBy : orderBy);

    query += '&order_by_direction=' + (forcedOrderByDirection ? forcedOrderByDirection : orderByDirection);

    if (selectedType !== null) {
      query += '&type=' + selectedType;
    }

    if (selectedYear !== null) {
      query += '&year=' + selectedYear;
    }

    if (selectedYearMonth !== null) {
      const month = selectedYearMonth.split('-')[1];
      query += '&month=' + month;
    }

    if (selectedDate !== null) {
      query += '&date=' + selectedDate;
    }

    if (selectedCountry !== null) {
      query += '&country=' + selectedCountry;
    }

    if (selectedCity !== null) {
      query += '&city=' + selectedCity;
    }

    if (selectedTag !== null) {
      query += '&tag=' + selectedTag;
    }

    if (search) {
      query += '&search=' + encodeURIComponent(search);
    }

    return query;
  }

  render() {
    const {
      classes,
      onImageClick,
      view,
    } = this.props;

    return (
      <div className={classes.root}>
        {view === 'list' && <ListView onImageClick={onImageClick} parent={this} />}
        {view === 'map' && <MapView onImageClick={onImageClick} parent={this} />}
      </div>
    );
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(
  withStyles(styles)(AppContent)
);
