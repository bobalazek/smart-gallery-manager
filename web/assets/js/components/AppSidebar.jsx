import React from 'react';
import moment from 'moment';
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
    width: '100%',
    flexGrow: 1,
  },
  listItemCount: {
    fontSize: 12,
    paddingLeft: 10,
    color: '#999999',
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
    selectedMonth: state.selectedMonth,
    selectedDate: state.selectedDate,
    selectedTag: state.selectedTag,
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

    this.tagsShownStep = 16;
    this.state = {
      tagsShownCount: 16,
    };

    this.onViewClick = this.onViewClick.bind(this);
    this.onTypeClick = this.onTypeClick.bind(this);
    this.onDateClick = this.onDateClick.bind(this);
    this.onTagClick = this.onTagClick.bind(this);
  }

  onViewClick(view) {
    this.props.setData(
      'view',
      view
    );
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
          selectedMonth: null,
          selectedDate: null,
        });
      } else {
        this.props.setData(
          'selectedYear',
          date
        );
      }
    } else if (type === 'month') {
      const isAlreadySet = this.props.selectedMonth === date;
      if (isAlreadySet) {
        this.props.setDataBatch({
          selectedMonth: null,
          selectedDate: null,
        });
      } else {
        this.props.setData(
          'selectedMonth',
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

  onTagClick(tag) {
    this.props.setData(
      'selectedTag',
      this.props.selectedTag === tag
        ? null
        : tag
    );
  }

  renderViewList() {
    const {
      classes,
      view,
    } = this.props;

    const views = {
      list: 'List',
      map: 'Map',
    };

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
        {Object.keys(views).map((viewKey) => {
          return (
            <ListItem
              key={viewKey}
              button
              onClick={this.onViewClick.bind(this, viewKey)}
              selected={view === viewKey}
            >
              <ListItemText
                primary={views[viewKey]}
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
          <CircularProgress />
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
      selectedMonth,
      selectedDate,
      orderBy,
    } = this.props;

    const yearsCount = filesSummary
      && filesSummary.date
      && filesSummary.date.year
      ? filesSummary.date.year
      : null;
    const monthsCount = filesSummary
      && filesSummary.date
      && filesSummary.date.month
      ? filesSummary.date.month
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
                {(selectedYear !== null || selectedMonth !== null || selectedDate !== null) &&
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
          <CircularProgress />
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
                {monthsCount && monthsCount.map((subEntry) => {
                  if (subEntry.date.indexOf(entry.date + '-') === -1) {
                    return;
                  }

                  const subSubList = (
                    <Collapse
                      in={subEntry.date === selectedMonth}
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
                        onClick={this.onDateClick.bind(this, subEntry.date, 'month')}
                        selected={subEntry.date === selectedMonth}
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

  renderTagList() {
    const {
      classes,
      filesSummary,
      selectedTag,
    } = this.props;
    const {
      tagsShownCount,
    } = this.state;

    let tags = filesSummary && filesSummary.tags
      ? filesSummary.tags
      : null;
    let allTagsShown = true;

    if (tags) {
      allTagsShown = tagsShownCount >= tags.length;

      tags = tags.slice(0, tagsShownCount);
    }

    return (
      <List
        component="nav"
        dense
        subheader={
          <ListSubheader component="div">
            <Grid container justify="space-between">
              <Grid item>
                Tag
              </Grid>
              <Grid item>
                {selectedTag !== null &&
                  <Button
                    size="small"
                    onClick={this.onTagClick.bind(this, selectedTag)}
                  >
                    Clear
                  </Button>
                }
              </Grid>
            </Grid>
          </ListSubheader>
        }
      >
        {!tags &&
          <CircularProgress />
        }
        {tags && tags.map((entry) => {
          return (
            <ListItem
              key={entry.tag}
              button
              onClick={this.onTagClick.bind(this, entry.tag)}
              selected={selectedTag === entry.tag}
            >
              <ListItemText
                primary={(
                  <React.Fragment>
                    {entry.tag}
                    <span className={classes.listItemCount}>{entry.count}</span>
                  </React.Fragment>
                )}
              />
            </ListItem>
          )
        })}
        {!allTagsShown &&
          <ListItem
            button
            onClick={() => {
              this.setState({
                tagsShownCount: tagsShownCount + this.tagsShownStep,
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
        {this.renderTagList()}
      </div>
    );
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(
  withStyles(styles)(AppSidebar)
);
