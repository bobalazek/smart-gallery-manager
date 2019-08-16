import React from 'react';
import { connect } from 'react-redux';
import { withStyles } from '@material-ui/styles';
import CircularProgress from '@material-ui/core/CircularProgress';
import Collapse from '@material-ui/core/Collapse';
import List from '@material-ui/core/List';
import ListSubheader from '@material-ui/core/ListSubheader';
import ListItem from '@material-ui/core/ListItem';
import ListItemText from '@material-ui/core/ListItemText';
import { setData } from '../actions/index';

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
    isLoading: state.isLoading,
    isLoaded: state.isLoaded,
    filesSummary: state.filesSummary,
    orderBy: state.orderBy,
    selectedType: state.selectedType,
    selectedYear: state.selectedYear,
    selectedMonth: state.selectedMonth,
    selectedDate: state.selectedDate,
  };
};

function mapDispatchToProps(dispatch) {
  return {
    setData: (type, data) => dispatch(setData(type, data)),
  };
}

class AppSidebar extends React.Component {
  constructor(props) {
    super(props);

    this.onClickDate = this.onClickDate.bind(this);
  }

  onClickDate(date, type) {
    if (type === 'year') {
      this.props.setData(
        'selectedYear',
        this.props.selectedYear === date
          ? null
          : date
      );
    } else if (type === 'month') {
      this.props.setData(
        'selectedMonth',
        this.props.selectedMonth === date
          ? null
          : date
      );
    } else if (type === 'date') {
      this.props.setData(
        'selectedDate',
        this.props.selectedDate === date
          ? null
          : date
      );
    }
  }

  render() {
    const {
      classes,
    } = this.props;

    return (
      <div className={classes.root}>
        {this.renderTypeList()}
        {this.renderDateList()}
      </div>
    );
  }

  renderTypeList() {
    const {
      classes,
      filesSummary,
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
            Type
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
      selectedType,
      selectedYear,
      selectedMonth,
      selectedDate,
      orderBy,
    } = this.props;

    const yearsCount = filesSummary && filesSummary.count && filesSummary.count.year
      ? filesSummary.count.year
      : null;
    const monthsCount = filesSummary && filesSummary.count && filesSummary.count.month
      ? filesSummary.count.month
      : null;
    const datesCount = filesSummary && filesSummary.count && filesSummary.count.date
      ? filesSummary.count.date
      : null;

    return (
      <List
        dense
        component="nav"
        subheader={
          <ListSubheader component="div">
            {orderBy === 'taken_at' && 'Date taken'}
            {orderBy === 'created_at' && 'Date created'}
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
                        {datesCount && datesCount.map((subSubEntry) => {
                          if (subSubEntry.date.indexOf(subEntry.date + '-') === -1) {
                            return;
                          }

                          return (
                            <div key={subSubEntry.date}>
                              <ListItem
                                button
                                onClick={this.onClickDate.bind(this, entry.date, 'date')}
                                selected={subSubEntry.date === selectedDate}
                              >
                                <ListItemText
                                  primary={(
                                    <React.Fragment>
                                      {subSubEntry.date}
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
                        onClick={this.onClickDate.bind(this, subEntry.date, 'month')}
                        selected={subEntry.date === selectedMonth}
                      >
                        <ListItemText
                          primary={(
                            <React.Fragment>
                              {subEntry.date}
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
                onClick={this.onClickDate.bind(this, entry.date, 'year')}
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
}

export default connect(mapStateToProps, mapDispatchToProps)(
  withStyles(styles)(AppSidebar)
);
