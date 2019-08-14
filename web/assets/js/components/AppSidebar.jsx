import React from 'react';
import { withStyles } from '@material-ui/styles';

const styles = {
  root: {
    width: '100%',
    flexGrow: 1,
  },
};

class AppSidebar extends React.Component {
  render() {
    const {
      classes,
    } = this.props;

    return (
      <div className={classes.root}>
        SIDEBAR
      </div>
    );
  }
}

export default withStyles(styles)(AppSidebar);
