import React from 'react';
import { withStyles } from '@material-ui/styles';

const styles = {
  root: {
    position: 'relative',
  },
};

// Get some inspirations from here?
//   https://medium.com/@danrschlosser/building-the-image-grid-from-google-photos-6a09e193c74a
//   or
//   https://medium.com/@albertjuhe/an-easy-to-use-performant-solution-to-lazy-load-images-in-react-e6752071020c

class ImageGrid extends React.Component {
  constructor(props) {
    super(props);

    this.wrapperWidth = 200;
    this.imageSpacing = 8;
  }

  render() {
    const {
      classes,
      row,
    } = this.props;

    const imagesCount = row.files.length;
    const minAspectRatio = this._getMinAspectRatio();

    let finalImages = [];

    let totalHeight = 0;
    let row = [];
    let translateX = 0;
    let translateY = 0;
    let rowAspectRatio = 0;

    for (let index = 0; index < imagesCount; i++) {
      const image = images[i];
      const imageSrc = image.images.preview.src;
      const imageWidth = image.images.preview.width;
      const imageHeight = image.images.preview.height;
      const imageAspectRatio = imageWidth / imageHeight;

      rowAspectRatio += parseFloat(image.aspectRatio);
      row.push({
        id: image.id,
        hash: image.hash,
        src: imageSrc,
        aspectRatio: imageAspectRatio,
      });

      if (rowAspectRatio >= minAspectRatio || index + 1 === imagesCount) {
        rowAspectRatio = Math.max(rowAspectRatio, minAspectRatio);

        let totalDesiredWidthOfImages = this.wrapperWidth - this.imageSpacing * (row.length - 1);
        let rowHeight = totalDesiredWidthOfImages / rowAspectRatio;

        row.forEach((rowImg) => {
          let imageWidth = rowHeight * img.aspectRatio;

          rowImg.style = {
            width: parseInt(imageWidth),
            height: parseInt(rowHeight),
            translateX: translateX,
            translateY: translateY,
          };

          finalImages.push(rowImg);

          translateX += imageWidth + this.imageSpacing;
        });

        row = [];
        translateX = 0;
        translateY += parseInt(rowHeight) + this.settings.spaceBetweenImages;
        rowAspectRatio = 0;
      }
    }

    totalHeight = translateY - this.imageSpacing;

    console.log(finalImages)
    console.log(totalHeight)

    return (
      <div className={classes.root}>
        GRID
      </div>
    );
  }

  _getMinAspectRatio() {
    const windowHeight = window.innerHeight;

    if (windowHeight <= 640) {
      return 2;
    } else if (windowHeight <= 1280) {
      return 4;
    } else if (windowHeight <= 1920) {
      return 4;
    }

    return 6;
  }
}

export default withStyles(styles)(ImageGrid);
