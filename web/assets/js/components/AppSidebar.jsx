import React from 'react';
import moment from 'moment';
import { withRouter } from 'react-router-dom';
import { connect } from 'react-redux';
import { withStyles } from '@material-ui/styles';
import CircularProgress from '@material-ui/core/CircularProgress';
import Collapse from '@material-ui/core/Collapse';
import Grid from '@material-ui/core/Grid';
import Button from '@material-ui/core/Button';
import List from '@material-ui/core/List';
import ListSubheader from '@material-ui/core/ListSubheader';
import ListItem from '@material-ui/core/ListItem';
import ListItemText from '@material-ui/core/ListItemText';
import {
  setData,
  setDataBatch,
} from '../actions/index';

const styles = {
  root: {
    width: 240,
    top: 0,
    flex: '1 0 auto',
    height: '100%',
    display: 'flex',
    position: 'fixed',
    overflowY: 'auto',
    flexDirection: 'column',
  },
  listItemCount: {
    fontSize: 12,
    paddingLeft: 10,
    color: '#999999',
  },
  circularProgress: {
    margin: '0 auto',
    margin: '8px 16px',
  },
};

const mapStateToProps = state => {
  return {
    view: state.view,
    isLoading: state.isLoading,
    isLoaded: state.isLoaded,
    filesSummary: state.filesSummary,
    orderBy: state.orderBy,
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

class AppSidebar extends React.Component {
  constructor(props) {
    super(props);

    this.state = {
      labelsShownCount: 16,
    };

    this.parent = this.props.parent;

    this.onViewClick = this.onViewClick.bind(this);
    this.onTypeClick = this.onTypeClick.bind(this);
    this.onDateClick = this.onDateClick.bind(this);
    this.onLocationClick = this.onLocationClick.bind(this);
    this.onLabelClick = this.onLabelClick.bind(this);
  }

  onViewClick(view, url) {
    this.props.setData('view', view);

    this.props.history.push(url);
  }

  onTypeClick(type) {
    this.props.setData(
      'selectedType',
      this.props.selectedType === type
        ? null
        : type
    );
  }

  onDateClick(date, type) {
    if (type === 'year') {
      const isAlreadySet = this.props.selectedYear === date;
      if (isAlreadySet) {
        this.props.setDataBatch({
          selectedYear: null,
          selectedYearMonth: null,
          selectedDate: null,
        });
      } else {
        this.props.setData(
          'selectedYear',
          date
        );
      }
    } else if (type === 'year_month') {
      const isAlreadySet = this.props.selectedYearMonth === date;
      if (isAlreadySet) {
        this.props.setDataBatch({
          selectedYearMonth: null,
          selectedDate: null,
        });
      } else {
        this.props.setData(
          'selectedYearMonth',
          date
        );
      }
    } else if (type === 'date') {
      this.props.setData(
        'selectedDate',
        this.props.selectedDate === date
          ? null
          : date
      );
    }
  }

  onLocationClick(location, type) {
    if (type === 'country') {
      const isAlreadySet = this.props.selectedCountry === location;
      if (isAlreadySet) {
        this.props.setDataBatch({
          selectedCountry: null,
          selectedCity: null,
        });
      } else {
        this.props.setData(
          'selectedCountry',
          location
        );
      }
    } else if (type === 'city') {
      this.props.setData(
        'selectedCity',
        this.props.selectedCity === location
          ? null
          : location
      );
    }
  }

  onLabelClick(label) {
    this.props.setData(
      'selectedLabel',
      this.props.selectedLabel === label
        ? null
        : label
    );
  }

  renderViewList() {
    const {
      classes,
      view,
    } = this.props;

    return (
      <List
        component="nav"
        dense
        subheader={
          <ListSubheader component="div">
            View
          </ListSubheader>
        }
      >
        {Object.keys(this.parent.views).map((viewKey) => {
          const thisView = this.parent.views[viewKey];
          return (
            <ListItem
              key={thisView.key}
              button
              onClick={this.onViewClick.bind(this, thisView.key, thisView.url)}
              selected={view === thisView.key}
            >
              <ListItemText
                primary={thisView.label}
              />
            </ListItem>
          )
        })}
      </List>
    );
  }

  renderTypeList() {
    const {
      classes,
      filesSummary,
      selectedType,
    } = this.props;

    const types = filesSummary && filesSummary.types
      ? filesSummary.types
      : null;

    return (
      <List
        component="nav"
        dense
        subheader={
          <ListSubheader component="div">
            <Grid container justify="space-between">
              <Grid item>
                Type
              </Grid>
              <Grid item>
                {selectedType !== null &&
                  <Button
                    size="small"
                    onClick={this.onTypeClick.bind(this, selectedType)}
                  >
                    Clear
                  </Button>
                }
              </Grid>
            </Grid>
          </ListSubheader>
        }
      >
        {!types &&
          <CircularProgress className={classes.circularProgress} />
        }
        {types && types.length === 0 &&
          <ListItem>
            <ListItemText
              primary={'No types found'}
            />
          </ListItem>
        }
        {types && types.map((entry) => {
          return (
            <ListItem
              key={entry.type}
              button
              onClick={this.onTypeClick.bind(this, entry.type)}
              selected={selectedType === entry.type}
            >
              <ListItemText
                primary={(
                  <React.Fragment>
                    {entry.type}
                    <span className={classes.listItemCount}>{entry.count}</span>
                  </React.Fragment>
                )}
              />
            </ListItem>
          )
        })}
      </List>
    );
  }

  renderDateList() {
    const {
      classes,
      filesSummary,
      selectedYear,
      selectedYearMonth,
      selectedDate,
      orderBy,
    } = this.props;

    const yearsCount = filesSummary
      && filesSummary.date
      && filesSummary.date.year
      ? filesSummary.date.year
      : null;
    const yearMonthsCount = filesSummary
      && filesSummary.date
      && filesSummary.date.year_month
      ? filesSummary.date.year_month
      : null;
    const dateCount = filesSummary
      && filesSummary.date
      && filesSummary.date.date
      ? filesSummary.date.date
      : null;

    return (
      <List
        dense
        component="nav"
        subheader={
          <ListSubheader component="div">
            <Grid container justify="space-between">
              <Grid item>
                {orderBy === 'taken_at' && 'Date taken'}
                {orderBy === 'created_at' && 'Date created'}
              </Grid>
              <Grid item>
                {(selectedYear !== null || selectedYearMonth !== null || selectedDate !== null) &&
                  <Button
                    size="small"
                    onClick={this.onDateClick.bind(this, selectedYear, 'year')}
                  >
                    Clear
                  </Button>
                }
              </Grid>
            </Grid>
          </ListSubheader>
        }
      >
        {!yearsCount &&
          <CircularProgress className={classes.circularProgress} />
        }
        {yearsCount && yearsCount.length === 0 &&
          <ListItem>
            <ListItemText
              primary={'No dates found'}
            />
          </ListItem>
        }
        {yearsCount && yearsCount.map((entry) => {
          const subList = (
            <Collapse
              in={entry.date === selectedYear}
              timeout="auto"
              unmountOnExit
            >
              <List
                dense
                component="div"
                disablePadding
                style={{ paddingLeft: 8 }}
              >
                {yearMonthsCount && yearMonthsCount.map((subEntry) => {
                  if (subEntry.date.indexOf(entry.date + '-') === -1) {
                    return;
                  }

                  const subSubList = (
                    <Collapse
                      in={subEntry.date === selectedYearMonth}
                      timeout="auto"
                      unmountOnExit
                    >
                      <List
                        dense
                        component="div"
                        disablePadding
                        style={{ paddingLeft: 8 }}
                      >
                        {dateCount && dateCount.map((subSubEntry) => {
                          if (subSubEntry.date.indexOf(subEntry.date + '-') === -1) {
                            return;
                          }

                          return (
                            <div key={subSubEntry.date}>
                              <ListItem
                                button
                                onClick={this.onDateClick.bind(this, subSubEntry.date, 'date')}
                                selected={subSubEntry.date === selectedDate}
                              >
                                <ListItemText
                                  primary={(
                                    <React.Fragment>
                                      {moment.parseZone(subSubEntry.date).format('Do')}
                                      <span className={classes.listItemCount}>{subSubEntry.count}</span>
                                    </React.Fragment>
                                  )}
                                />
                              </ListItem>
                            </div>
                          )
                        })}
                      </List>
                    </Collapse>
                  );

                  return (
                    <div key={subEntry.date}>
                      <ListItem
                        button
                        onClick={this.onDateClick.bind(this, subEntry.date, 'year_month')}
                        selected={subEntry.date === selectedYearMonth}
                      >
                        <ListItemText
                          primary={(
                            <React.Fragment>
                              {moment.parseZone(subEntry.date).format('MMMM')}
                              <span className={classes.listItemCount}>{subEntry.count}</span>
                            </React.Fragment>
                          )}
                        />
                      </ListItem>
                      {subSubList}
                    </div>
                  )
                })}
              </List>
            </Collapse>
          );

          return (
            <div key={entry.date}>
              <ListItem
                button
                onClick={this.onDateClick.bind(this, entry.date, 'year')}
                selected={entry.date === selectedYear}
              >
                <ListItemText
                  primary={(
                    <React.Fragment>
                      {entry.date}
                      <span className={classes.listItemCount}>{entry.count}</span>
                    </React.Fragment>
                  )}
                />
              </ListItem>
              {subList}
            </div>
          )
        })}
      </List>
    );
  }

  renderLocationList() {
    const {
      classes,
      filesSummary,
      selectedCountry,
      selectedCity,
      orderBy,
    } = this.props;

    const countries = filesSummary
      && filesSummary.location
      && filesSummary.location.country
      ? filesSummary.location.country
      : null;
    const cities = filesSummary
      && filesSummary.location
      && filesSummary.location.city
      ? filesSummary.location.city
      : null;

    return (
      <List
        dense
        component="nav"
        subheader={
          <ListSubheader component="div">
            <Grid container justify="space-between">
              <Grid item>
                Location
              </Grid>
              <Grid item>
                {(selectedCountry !== null || selectedCity !== null) &&
                  <Button
                    size="small"
                    onClick={this.onLocationClick.bind(this, selectedCountry, 'country')}
                  >
                    Clear
                  </Button>
                }
              </Grid>
            </Grid>
          </ListSubheader>
        }
      >
        {!countries &&
          <CircularProgress className={classes.circularProgress} />
        }
        {countries && countries.length === 0 &&
          <ListItem>
            <ListItemText
              primary={'No locations found'}
            />
          </ListItem>
        }
        {countries && countries.map((entry) => {
          const subList = (
            <Collapse
              in={entry.location === selectedCountry}
              timeout="auto"
              unmountOnExit
            >
              <List
                dense
                component="div"
                disablePadding
                style={{ paddingLeft: 8 }}
              >
                {cities && cities.map((subEntry) => {
                  if (entry.location !== subEntry.parent) {
                    return;
                  }

                  if (subEntry.location === '') {
                    // TODO: not yet working correctly on the backend
                    return;
                  }

                  return (
                    <div key={subEntry.location}>
                      <ListItem
                        button
                        onClick={this.onLocationClick.bind(this, subEntry.location, 'city')}
                        selected={subEntry.location === selectedCity}
                      >
                        <ListItemText
                          primary={(
                            <React.Fragment>
                              {subEntry.location ? subEntry.location : 'Unknown'}
                              <span className={classes.listItemCount}>{subEntry.count}</span>
                            </React.Fragment>
                          )}
                        />
                      </ListItem>
                    </div>
                  )
                })}
              </List>
            </Collapse>
          );

          if (entry.location === '') {
            // TODO: not yet working correctly on the backend
            return;
          }

          return (
            <div key={entry.location}>
              <ListItem
                button
                onClick={this.onLocationClick.bind(this, entry.location, 'country')}
                selected={entry.location === selectedCountry}
              >
                <ListItemText
                  primary={(
                    <React.Fragment>
                      {entry.location ? entry.location : 'Unknown'}
                      <span className={classes.listItemCount}>{entry.count}</span>
                    </React.Fragment>
                  )}
                />
              </ListItem>
              {subList}
            </div>
          )
        })}
      </List>
    );
  }

  renderLabelList() {
    const {
      classes,
      filesSummary,
      selectedLabel,
    } = this.props;
    const {
      labelsShownCount,
    } = this.state;

    let labels = filesSummary && filesSummary.labels
      ? filesSummary.labels
      : null;
    let allLabelsShown = true;

    if (labels) {
      allLabelsShown = labelsShownCount >= labels.length;

      labels = labels.slice(0, labelsShownCount);
    }

    return (
      <List
        component="nav"
        dense
        subheader={
          <ListSubheader component="div">
            <Grid container justify="space-between">
              <Grid item>
                Label
              </Grid>
              <Grid item>
                {selectedLabel !== null &&
                  <Button
                    size="small"
                    onClick={this.onLabelClick.bind(this, selectedLabel)}
                  >
                    Clear
                  </Button>
                }
              </Grid>
            </Grid>
          </ListSubheader>
        }
      >
        {!labels &&
          <CircularProgress className={classes.circularProgress} />
        }
        {labels && labels.length === 0 &&
          <ListItem>
            <ListItemText
              primary={'No labels found'}
            />
          </ListItem>
        }
        {labels && labels.map((entry) => {
          return (
            <ListItem
              key={entry.label}
              button
              onClick={this.onLabelClick.bind(this, entry.label)}
              selected={selectedLabel === entry.label}
            >
              <ListItemText
                primary={(
                  <React.Fragment>
                    {entry.label}
                    <span className={classes.listItemCount}>{entry.count}</span>
                  </React.Fragment>
                )}
              />
            </ListItem>
          )
        })}
        {!allLabelsShown &&
          <ListItem
            button
            onClick={() => {
              this.setState({
                labelsShownCount: labelsShownCount + this.parent.sidebarLabelsShownStep,
              });
            }}
          >
            <ListItemText
              primary={'Show more ...'}
            />
          </ListItem>
        }
      </List>
    );
  }

  render() {
    const {
      classes,
    } = this.props;

    return (
      <div className={classes.root}>
        {this.renderViewList()}
        {this.renderTypeList()}
        {this.renderDateList()}
        {this.renderLocationList()}
        {this.renderLabelList()}
      </div>
    );
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(
  withRouter(withStyles(styles)(AppSidebar))
);
