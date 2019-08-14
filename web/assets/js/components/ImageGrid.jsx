import React from 'react';
import { withStyles } from '@material-ui/styles';
import Typography from '@material-ui/core/Typography';

const styles = {
  root: {
    position: 'relative',
  },
  imagesWrapper: {
    position: 'relative',
  },
  imageWrapper: {
    position: 'absolute',
    cursor: 'pointer',
    backgroundColor: '#f6f6f6',
  },
  heading: {
    padding: 8,
    fontSize: 24,
  },
};

class ImageGrid extends React.Component {
  constructor(props) {
    super(props);

    this.state = {
      imageSpacing: this.props.imageSpacing || 4,
      containerWidth: this.props.container.innerWidth
        || this.props.container.clientWidth,
    };

    this.update = this.update.bind(this);
  }

  componentDidMount() {
    window.addEventListener('resize', this.update);

    if (this.props.onReady) {
      this.props.onReady();
    }
  }

  componentWillUnmount() {
    window.removeEventListener('resize', this.update);
  }

  update() {
    this.setState({
      containerWidth: this.props.container.innerWidth
        || this.props.container.clientWidth,
    });

    if (this.props.onReady) {
      this.props.onReady();
    }
  }

  render() {
    const {
      classes,
      heading,
      files,
      isVisible,
      onClick,
    } = this.props;
    const {
      imageSpacing,
      containerWidth,
    } = this.state;

    const images = files;
    const imagesCount = images.length;
    const minAspectRatio = this._getMinAspectRatio();

    let finalImages = [];

    let totalHeight = 0;
    let row = [];
    let translateX = 0;
    let translateY = 0;
    let rowAspectRatio = 0;

    for (let index = 0; index < imagesCount; index++) {
      const image = images[index];
      const imageWidth = image.images.preview.width;
      const imageHeight = image.images.preview.height;
      const imageAspectRatio = imageWidth / imageHeight;

      rowAspectRatio += parseFloat(imageAspectRatio);

      row.push({
        id: image.id,
        hash: image.hash,
        src: image.images.preview.src,
        srcPreview: image.images.preview.src,
        srcOriginal: image.images.original.src,
        aspectRatio: imageAspectRatio,
      });

      if (rowAspectRatio >= minAspectRatio || index + 1 === imagesCount) {
        rowAspectRatio = Math.max(rowAspectRatio, minAspectRatio);

        let totalDesiredWidthOfImages = containerWidth - imageSpacing * (row.length - 1);
        let rowHeight = totalDesiredWidthOfImages / rowAspectRatio;

        row.forEach((rowImg) => {
          let imageWidth = rowHeight * rowImg.aspectRatio;

          rowImg.style = {
            width: parseInt(imageWidth),
            height: parseInt(rowHeight),
            left: translateX,
            top: translateY,
          };

          finalImages.push(rowImg);

          translateX += imageWidth + imageSpacing;
        });

        row = [];
        translateX = 0;
        translateY += parseInt(rowHeight) + imageSpacing;
        rowAspectRatio = 0;
      }
    }

    totalHeight = translateY - imageSpacing;

    let rootStyle = {};
    if (!heading) {
      rootStyle.paddingTop = imageSpacing;
    }

    return (
      <div
        className={classes.root}
        style={rootStyle}
      >
        {heading &&
          <Typography
            variant="h4"
            component="h4"
            className={classes.heading}
          >
            <React.Fragment>
              {heading.relative_time &&
                <span><b>{heading.relative_time}</b> -{' '}</span>
              }
              {heading.date} --{' '}
              <small><i>{heading.items_count} items</i></small>
            </React.Fragment>
          </Typography>
        }
        <div
          className={classes.imagesWrapper}
          style={{ height: totalHeight }}
        >
          {finalImages.map((image) => {
            return (
              <div
                key={image.id}
                className={classes.imageWrapper}
                style={image.style}
                onClick={onClick.bind(this, image)}
              >
                <img
                  src={isVisible ? image.src : ''}
                  onLoad={() => {
                    if (
                      isVisible &&
                      image.srcPreview
                    ) {
                      image.src = image.srcPreview;
                    }
                  }}
                  style={image.style}
                />
              </div>
            )
          })}
        </div>
      </div>
    );
  }

  _getMinAspectRatio() {
    const containerWidth = this.state.containerWidth;

    if (containerWidth <= 640) {
      return 2;
    } else if (containerWidth <= 1280) {
      return 4;
    } else if (containerWidth <= 1920) {
      return 4;
    }

    return 6;
  }
}

export default withStyles(styles)(ImageGrid);
