import React from 'react';
import { connect } from 'react-redux';
import { BrowserRouter } from 'react-router-dom';
import { withStyles } from '@material-ui/styles';
import Grid from '@material-ui/core/Grid';
import FormControl from '@material-ui/core/FormControl';
import Select from '@material-ui/core/Select';
import TextField from '@material-ui/core/TextField';
import OutlinedInput from '@material-ui/core/OutlinedInput';
import InputLabel from '@material-ui/core/InputLabel';
import MenuItem from '@material-ui/core/MenuItem';
import { setData } from '../actions/index';

const styles = {
  root: {},
};

const mapStateToProps = state => {
  return {
    orderBy: state.orderBy,
    orderByDirection: state.orderByDirection,
    search: state.search,
  };
};

function mapDispatchToProps(dispatch) {
  return {
    setData: (type, data) => dispatch(setData(type, data)),
  };
}

class AppNavigation extends React.Component {
  constructor(props) {
    super(props);

    this.parent = this.props.parent;

    this.onChange = this.onChange.bind(this);
  }

  onChange(event) {
    const name = event.target.name;
    const value = event.target.value;

    this.props.setData(name, value);
  }

  render() {
    const {
      classes,
      orderBy,
      orderByDirection,
      search,
    } = this.props;

    return (
      <div className={classes.root}>
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
      </div>
    );
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(
  withStyles(styles)(AppNavigation)
);
