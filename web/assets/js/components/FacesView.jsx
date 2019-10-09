import React from 'react';
import axios from 'axios';
import moment from 'moment';
import { connect } from 'react-redux';
import L from 'leaflet';
import { withStyles } from '@material-ui/styles';
import AppNavigation from './AppNavigation';
import {
  setData,
  setDataBatch,
} from '../actions/index';

const styles = {
  root: {
    width: '100%',
    flexGrow: 1,
    padding: 16,
  },
};

const mapStateToProps = state => {
  return {
    filesSummaryDatetime: state.filesSummaryDatetime,
  };
};

function mapDispatchToProps(dispatch) {
  return {
    setData: (type, data) => dispatch(setData(type, data)),
  };
}

class FacesView extends React.Component {
  constructor(props) {
    super(props);

    this.parent = this.props.parent;

    this.state = {
      data: [],
    };

    this.mapRef = React.createRef();
  }

  componentDidMount() {
    this.props.setData('view', 'faces');
  }

  componentDidUpdate(prevProps) {
    if (prevProps.filesSummaryDatetime !== this.props.filesSummaryDatetime) {
      return new Promise((resolve, reject) => {
        this.props.setData('isDataLoading', true);

        const url = rootUrl + '/api/files/faces' +
          this.parent.getFiltersQuery();

        return axios.get(url)
          .then(res => {
            this.setState({
              data: res.data.data,
            }, () => {
              this.prepareFaces();
            });
          })
          .finally(() => {
            this.props.setData('isDataLoading', false);
          });
      });
    }
  }

  prepareFaces() {
    // TODO
  }

  render() {
    const {
      classes,
    } = this.props;

    return (
      <div className={classes.root}>
        <AppNavigation parent={this.parent} />
        <div>
          TODO
        </div>
      </div>
    );
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(
  withStyles(styles)(FacesView)
);
