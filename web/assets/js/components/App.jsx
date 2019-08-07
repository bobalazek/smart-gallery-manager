import React from 'react';
import { ThemeProvider } from '@material-ui/styles';
import { createMuiTheme } from '@material-ui/core/styles';
import AppContainer from './AppContainer';

const theme = createMuiTheme({
  // TODO
});

export default class App extends React.Component {
  render() {
    return (
      <div>
        <ThemeProvider theme={theme}>
          <AppContainer />
        </ThemeProvider>
      </div>
    );
  }
}
