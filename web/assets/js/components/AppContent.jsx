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
          let rowsTotalCount = 0;
          let rowsFilesCountMap = [];
          let filesPerDate = [];

          filesSummary.date.date.forEach((data) => {
            let rowsCountPerDate = 1;
            if (data.count > this.maxFilesPerRow) {
              rowsCountPerDate = Math.ceil(data.count / this.maxFilesPerRow);

              for (let i = 0; i < rowsCountPerDate-1; i++) {
                rowsFilesCountMap.push(this.maxFilesPerRow);
              }
              rowsFilesCountMap.push(
                data.count % this.maxFilesPerRow
              );
            } else {
              rowsFilesCountMap.push(data.count);
            }

            filesPerDate.push({
              date: data.date,
              count: data.count,
              rows: rowsCountPerDate,
            });

            rowsTotalCount += rowsCountPerDate;
          });

          if (res.data.meta.order_by_direction === 'ASC') {
            filesPerDate = filesPerDate.reverse();
          }

          this.props.setDataBatch({
            rows: [],
            rowsFilesCountMap,
            rowsTotalCount,
            files: [],
            filesIdMap: [],
            filesSummary,
            filesSummaryDatetime: moment(),
            filesPerDate,
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
      selectedLabel,
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

    if (selectedLabel !== null) {
      query += '&label=' + selectedLabel;
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
