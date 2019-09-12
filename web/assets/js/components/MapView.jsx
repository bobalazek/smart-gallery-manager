import React from 'react';
import { withStyles } from '@material-ui/styles';

const styles = {
  root: {
    width: '100%',
    display: 'flex',
    flexDirection: 'column',
    height: '100vh',
  },
};

class MapView extends React.Component {
  constructor(props) {
    super(props);
  }

  render() {
    const {
      classes,
    } = this.props;

    return (
      <div className={classes.root}>
        MAP VIEW
      </div>
    );
  }
}

export default withStyles(styles)(MapView);
