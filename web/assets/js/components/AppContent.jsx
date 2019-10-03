import React from 'react';
import axios from 'axios';
import moment from 'moment';
import qs from 'qs';
import { withRouter, Switch, Route } from 'react-router-dom';
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

    this.parent = this.props.parent;

    this.lastUrl = null;
  }

  componentDidUpdate(prevProps) {
    clearTimeout(this.timer);
    this.timer = setTimeout(() => {
      const {
        view,
      } = this.props;

      const pathWithoutQuery = this.parent.views[view].url;
      const path = pathWithoutQuery + this.getFiltersQuery();
      const currentQuery = window.location.search;
      const currentPath = window.location.pathname + currentQuery;
      const isFirstVisit = !this.lastPath;

      if (isFirstVisit) {
        const keyValues = {
          order_by: 'orderBy',
          order_by_direction: 'orderByDirection',
          search: 'search',
          type: 'selectedType',
          year: 'selectedYear',
          month: 'selectedYearMonth',
          date: 'selectedDate',
          country: 'selectedCountry',
          city: 'selectedCity',
          label: 'selectedLabel',
        };
        const parsedQuery = qs.parse(currentQuery, { ignoreQueryPrefix: true });
        let newData = {};

        for (const key in keyValues) {
          const propsKey = keyValues[key];
          if (
            !parsedQuery[key] ||
            parsedQuery[key] === this.props[propsKey]
          ) {
            continue;
          }

          let newValue = parsedQuery[key];
          if (key === 'month') {
            // We can only set the selectedMonth, if there is also the year
            if (parsedQuery.year) {
              newValue = parsedQuery.year + '-' + newValue;
            } else {
              continue;
            }
          }

          newData[propsKey] = newValue;
        }

        if (Object.keys(newData).length > 0) {
          this.props.setDataBatch(newData);
        }
      }

      if (
        !isFirstVisit &&
        path !== currentPath &&
        this.lastPath !== path
      ) {
        this.props.history.push(path);
      }

      this.lastPath = path;
    }, 500);
  }

  fetchFilesSummary(orderBy, orderByDirection) {
    const {
      selectedYear,
      selectedYearMonth,
      selectedDate,
    } = this.props;
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
            // Now that we get ALL the dates, because we are ignoring all the
            //   date related parameters in the API, we will need to process
            //   them here on the client-side.
            if (
              (
                selectedYear &&
                !data.date.startsWith(selectedYear + '-')
              ) ||
              (
                selectedYearMonth &&
                !data.date.startsWith(selectedYearMonth + '-')
              ) ||
              (
                selectedDate &&
                data.date !== selectedDate
              )
            ) {
              return;
            }

            let rowsCountPerDate = 1;
            if (data.count > this.parent.maxFilesPerRow) {
              rowsCountPerDate = Math.ceil(data.count / this.parent.maxFilesPerRow);

              for (let i = 0; i < rowsCountPerDate-1; i++) {
                rowsFilesCountMap.push(this.parent.maxFilesPerRow);
              }
              rowsFilesCountMap.push(
                data.count % this.parent.maxFilesPerRow
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
      view,
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

    if (view === 'map') {
      query += '&only_with_location=true';
    }

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
    } = this.props;

    return (
      <div className={classes.root}>
        <Switch>
          <Route exact path={basePath}>
            <ListView onImageClick={onImageClick} parent={this} />
          </Route>
          <Route exact path={`${basePath}/map`}>
            <MapView onImageClick={onImageClick} parent={this} />
          </Route>
        </Switch>
      </div>
    );
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(
  withRouter(withStyles(styles)(AppContent))
);
