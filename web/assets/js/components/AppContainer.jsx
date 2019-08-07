import React from 'react';
import axios from 'axios';
import moment from 'moment';
import { withStyles } from '@material-ui/styles';
import Typography from '@material-ui/core/Typography';
import Container from '@material-ui/core/Container';
import Grid from '@material-ui/core/Grid';
import Modal from '@material-ui/core/Modal';
import Image from './partials/Image';

const styles = {
  // TODO
};

class AppContainer extends React.Component {
  constructor(props) {
    super(props);

    this.state = {
      files: [],
      isModalOpen: false,
    };

    this.onImageClick = this.onImageClick.bind(this);
  }

  componentDidMount() {
    axios.get(rootUrl + '/api/files')
      .then(res => {
        const files = res.data.data;

        this.setState({ files });
      })
  }

  onImageClick(file, event) {
    console.log(event)
    console.log(file)
  }

  render() {
    const {
      files,
      isModalOpen,
    } = this.state;

    let filesPerDate = {};
    files.forEach(file => {
      const date = moment(file.taken_at).format('YYYY-MM-DD');

      if (typeof filesPerDate[date] === 'undefined') {
        filesPerDate[date] = [];
      }

      filesPerDate[date].push(file);
    });

    return (
      <div>
        <Container>
          {files.length === 0 &&
            <div>No files found.</div>
          }
          {files.length > 0 && Object.keys(filesPerDate).map(date => {
            return (
              <Grid container key={date} style={{ marginBottom: 20 }}>
                <Grid item xs={12}>
                  <Typography variant="h3" component="h3">
                    {date}
                  </Typography>
                </Grid>
                {filesPerDate[date].map((file) => {
                  return (
                    <Grid item key={file.id} xs={3}>
                      <Image
                        src={file.links.thumbnail}
                        onClick={this.onImageClick.bind(this, file)}
                      />
                    </Grid>
                  )
                })}
              </Grid>
            )
          })}
        </Container>
        <Modal open={isModalOpen}>
          <div>TODO</div>
        </Modal>
      </div>
    );
  }
}

export default withStyles(styles)(AppContainer);
