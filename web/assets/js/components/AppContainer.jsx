import React from 'react';
import axios from 'axios';
import moment from 'moment';
import { withStyles } from '@material-ui/styles';
import Typography from '@material-ui/core/Typography';
import Container from '@material-ui/core/Container';
import Grid from '@material-ui/core/Grid';
import CircularProgress from '@material-ui/core/CircularProgress';
import Image from './Image';
import ImageModal from './ImageModal';

const styles = {
  circularProgressWrapper: {
    textAlign: 'center',
    marginTop: 64,
  },
};

class AppContainer extends React.Component {
  constructor(props) {
    super(props);

    this.state = {
      files: [],
      isLoading: true,
      isLoaded: false,
      isModalOpen: false,
      modalData: {},
    };

    this.onImageClick = this.onImageClick.bind(this);
    this.onModalClose = this.onModalClose.bind(this);
  }

  componentDidMount() {
    axios.get(rootUrl + '/api/files')
      .then(res => {
        const files = res.data.data;

        this.setState({
          files,
          isLoading: false,
          isLoaded: true,
        });
      });
  }

  onImageClick(file) {
    this.setState({
      isModalOpen: true,
      modalData: file,
    });
  }

  onModalClose() {
    this.setState({
      isModalOpen: false,
      modalData: {},
    });
  }

  render() {
    const {
      classes,
    } = this.props;
    const {
      files,
      isLoading,
      isLoaded,
      isModalOpen,
      modalData,
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
          {isLoading && !isLoaded && (
            <div className={classes.circularProgressWrapper}>
              <CircularProgress size={80} />
            </div>
          )}
          {!isLoading && isLoaded && files.length === 0 &&
            <div>No files found.</div>
          }
          {isLoaded && files.length > 0 && Object.keys(filesPerDate).map(date => {
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
        <ImageModal
          open={isModalOpen}
          onClose={this.onModalClose}
          data={modalData}
        />
      </div>
    );
  }
}

export default withStyles(styles)(AppContainer);
