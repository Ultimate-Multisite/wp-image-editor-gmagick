<?php
/**
 * WordPress Gmagick Image Editor
 *
 * Image manipulation via the Gmagick (GraphicsMagick) PHP extension.
 * API-compatible drop-in for WP_Image_Editor_Imagick.
 *
 * @package WP_Image_Editor_Gmagick
 */

defined( 'ABSPATH' ) || exit;

class WP_Image_Editor_Gmagick extends WP_Image_Editor {

	/**
	 * @var Gmagick
	 */
	protected $image;

	public function __destruct() {
		if ( $this->image instanceof Gmagick ) {
			$this->image->clear();
			$this->image->destroy();
		}
	}

	/**
	 * Check if the environment supports Gmagick.
	 *
	 * @param array $args
	 * @return bool
	 */
	public static function test( $args = array() ) {
		if ( ! extension_loaded( 'gmagick' ) || ! class_exists( 'Gmagick', false ) ) {
			return false;
		}

		$required_methods = array(
			'clear',
			'destroy',
			'getimageblob',
			'getimagegeometry',
			'getimageformat',
			'setimageformat',
			'setcompressionquality',
			'setimagecompression',
			'setimagepage',
			'scaleimage',
			'cropimage',
			'rotateimage',
			'flipimage',
			'flopimage',
			'readimage',
			'readimageblob',
			'writeimage',
		);

		$class_methods = array_map( 'strtolower', get_class_methods( 'Gmagick' ) );
		if ( array_diff( $required_methods, $class_methods ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if the editor supports a given mime type.
	 *
	 * @param string $mime_type
	 * @return bool
	 */
	public static function supports_mime_type( $mime_type ) {
		$extension = strtoupper( self::get_extension( $mime_type ) );

		if ( ! $extension ) {
			return false;
		}

		try {
			$gmagick = new Gmagick();
			$formats = $gmagick->queryformats();

			return in_array( $extension, $formats, true );
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Load image from file.
	 *
	 * @return true|WP_Error
	 */
	public function load() {
		if ( $this->image instanceof Gmagick ) {
			return true;
		}

		if ( ! is_file( $this->file ) && ! wp_is_stream( $this->file ) ) {
			return new WP_Error( 'error_loading_image', __( 'File does not exist?' ), $this->file );
		}

		wp_raise_memory_limit( 'image' );

		try {
			$this->image = new Gmagick();

			if ( wp_is_stream( $this->file ) ) {
				$this->image->readimageblob( file_get_contents( $this->file ), $this->file );
			} else {
				$this->image->readimage( $this->file );
			}

			if ( is_callable( array( $this->image, 'setimageindex' ) ) ) {
				$this->image->setimageindex( 0 );
			}

			$this->mime_type = $this->get_mime_type( $this->image->getimageformat() );
		} catch ( Exception $e ) {
			return new WP_Error( 'invalid_image', $e->getMessage(), $this->file );
		}

		$updated_size = $this->update_size();

		if ( is_wp_error( $updated_size ) ) {
			return $updated_size;
		}

		return $this->set_quality();
	}

	/**
	 * Set image compression quality.
	 *
	 * @param int   $quality
	 * @param array $dims
	 * @return true|WP_Error
	 */
	public function set_quality( $quality = null, $dims = array() ) {
		$quality_result = parent::set_quality( $quality, $dims );
		if ( is_wp_error( $quality_result ) ) {
			return $quality_result;
		} else {
			$quality = $this->get_quality();
		}

		try {
			if ( 'image/jpeg' === $this->mime_type ) {
				$this->image->setcompressionquality( $quality );
				$this->image->setimagecompression( Gmagick::COMPRESSION_JPEG );
			} else {
				$this->image->setcompressionquality( $quality );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'image_quality_error', $e->getMessage() );
		}

		return true;
	}

	/**
	 * Update current image size.
	 *
	 * @param int $width
	 * @param int $height
	 * @return true|WP_Error
	 */
	protected function update_size( $width = null, $height = null ) {
		$size = null;
		if ( ! $width || ! $height ) {
			try {
				$size = $this->image->getimagegeometry();
			} catch ( Exception $e ) {
				return new WP_Error( 'invalid_image', __( 'Could not read image size.' ), $this->file );
			}
		}

		if ( ! $width ) {
			$width = $size['width'];
		}

		if ( ! $height ) {
			$height = $size['height'];
		}

		return parent::update_size( $width, $height );
	}

	/**
	 * Resize the image.
	 *
	 * @param int|null   $max_w
	 * @param int|null   $max_h
	 * @param bool|array $crop
	 * @return true|WP_Error
	 */
	public function resize( $max_w, $max_h, $crop = false ) {
		if ( ( $this->size['width'] === $max_w ) && ( $this->size['height'] === $max_h ) ) {
			return true;
		}

		$dims = image_resize_dimensions( $this->size['width'], $this->size['height'], $max_w, $max_h, $crop );
		if ( ! $dims ) {
			return new WP_Error( 'error_getting_dimensions', __( 'Could not calculate resized image dimensions' ) );
		}

		list( $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h ) = $dims;

		if ( $crop ) {
			return $this->crop( $src_x, $src_y, $src_w, $src_h, $dst_w, $dst_h );
		}

		$this->set_quality(
			null,
			array(
				'width'  => $dst_w,
				'height' => $dst_h,
			)
		);

		try {
			// Strip metadata before resize for efficiency.
			if ( is_callable( array( $this->image, 'stripimage' ) ) ) {
				/** This filter is documented in wp-includes/class-wp-image-editor-imagick.php */
				if ( apply_filters( 'image_strip_meta', true ) ) {
					$this->image->stripimage();
				}
			}

			// Pre-sample large images for efficiency.
			if ( is_callable( array( $this->image, 'sampleimage' ) ) ) {
				$resize_ratio  = ( $dst_w / $this->size['width'] ) * ( $dst_h / $this->size['height'] );
				$sample_factor = 5;

				if ( $resize_ratio < .111 && ( $dst_w * $sample_factor > 128 && $dst_h * $sample_factor > 128 ) ) {
					$this->image->sampleimage( $dst_w * $sample_factor, $dst_h * $sample_factor );
				}
			}

			if ( is_callable( array( $this->image, 'resizeimage' ) ) && defined( 'Gmagick::FILTER_TRIANGLE' ) ) {
				$this->image->resizeimage( $dst_w, $dst_h, Gmagick::FILTER_TRIANGLE, 1 );
			} else {
				$this->image->scaleimage( $dst_w, $dst_h );
			}

			// Sharpen JPEGs after resize.
			if ( 'image/jpeg' === $this->mime_type && is_callable( array( $this->image, 'unsharpmaskimage' ) ) ) {
				$this->image->unsharpmaskimage( 0.25, 0.25, 8, 0.065 );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'image_resize_error', $e->getMessage() );
		}

		return $this->update_size( $dst_w, $dst_h );
	}

	/**
	 * Create multiple sub-sizes.
	 *
	 * @param array $sizes
	 * @return array
	 */
	public function multi_resize( $sizes ) {
		$metadata = array();

		foreach ( $sizes as $size => $size_data ) {
			$meta = $this->make_subsize( $size_data );

			if ( ! is_wp_error( $meta ) ) {
				$metadata[ $size ] = $meta;
			}
		}

		return $metadata;
	}

	/**
	 * Create a single sub-size.
	 *
	 * @param array $size_data
	 * @return array|WP_Error
	 */
	public function make_subsize( $size_data ) {
		if ( ! isset( $size_data['width'] ) && ! isset( $size_data['height'] ) ) {
			return new WP_Error( 'image_subsize_create_error', __( 'Cannot resize the image. Both width and height are not set.' ) );
		}

		$orig_size  = $this->size;
		$orig_image = $this->image->getimage();

		if ( ! isset( $size_data['width'] ) ) {
			$size_data['width'] = null;
		}

		if ( ! isset( $size_data['height'] ) ) {
			$size_data['height'] = null;
		}

		if ( ! isset( $size_data['crop'] ) ) {
			$size_data['crop'] = false;
		}

		if ( ( $this->size['width'] === $size_data['width'] ) && ( $this->size['height'] === $size_data['height'] ) ) {
			return new WP_Error( 'image_subsize_create_error', __( 'The image already has the requested size.' ) );
		}

		$resized = $this->resize( $size_data['width'], $size_data['height'], $size_data['crop'] );

		if ( is_wp_error( $resized ) ) {
			$saved = $resized;
		} else {
			$saved = $this->_save( $this->image );

			$this->image->clear();
			$this->image->destroy();
			$this->image = null;
		}

		$this->size  = $orig_size;
		$this->image = $orig_image;

		if ( ! is_wp_error( $saved ) ) {
			unset( $saved['path'] );
		}

		return $saved;
	}

	/**
	 * Crop the image.
	 *
	 * @param int  $src_x
	 * @param int  $src_y
	 * @param int  $src_w
	 * @param int  $src_h
	 * @param int  $dst_w
	 * @param int  $dst_h
	 * @param bool $src_abs
	 * @return true|WP_Error
	 */
	public function crop( $src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false ) {
		if ( $src_abs ) {
			$src_w -= $src_x;
			$src_h -= $src_y;
		}

		try {
			$this->image->cropimage( $src_w, $src_h, $src_x, $src_y );
			$this->image->setimagepage( $src_w, $src_h, 0, 0 );

			if ( $dst_w || $dst_h ) {
				if ( ! $dst_w ) {
					$dst_w = $src_w;
				}
				if ( ! $dst_h ) {
					$dst_h = $src_h;
				}

				$this->image->scaleimage( $dst_w, $dst_h );

				return $this->update_size();
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'image_crop_error', $e->getMessage() );
		}

		return $this->update_size();
	}

	/**
	 * Rotate the image counter-clockwise.
	 *
	 * @param float $angle
	 * @return true|WP_Error
	 */
	public function rotate( $angle ) {
		try {
			$this->image->rotateimage( new GmagickPixel( 'none' ), 360 - $angle );

			$result = $this->update_size();
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$this->image->setimagepage( $this->size['width'], $this->size['height'], 0, 0 );
		} catch ( Exception $e ) {
			return new WP_Error( 'image_rotate_error', $e->getMessage() );
		}

		return true;
	}

	/**
	 * Flip the image.
	 *
	 * @param bool $horz
	 * @param bool $vert
	 * @return true|WP_Error
	 */
	public function flip( $horz, $vert ) {
		try {
			if ( $horz ) {
				$this->image->flipimage();
			}

			if ( $vert ) {
				$this->image->flopimage();
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'image_flip_error', $e->getMessage() );
		}

		return true;
	}

	/**
	 * Save the image to a file.
	 *
	 * @param string $destfilename
	 * @param string $mime_type
	 * @return array|WP_Error
	 */
	public function save( $destfilename = null, $mime_type = null ) {
		$saved = $this->_save( $this->image, $destfilename, $mime_type );

		if ( ! is_wp_error( $saved ) ) {
			$this->file      = $saved['path'];
			$this->mime_type = $saved['mime-type'];

			try {
				$this->image->setimageformat( strtoupper( $this->get_extension( $this->mime_type ) ) );
			} catch ( Exception $e ) {
				return new WP_Error( 'image_save_error', $e->getMessage(), $this->file );
			}
		}

		return $saved;
	}

	/**
	 * Internal save implementation.
	 *
	 * @param Gmagick $image
	 * @param string  $filename
	 * @param string  $mime_type
	 * @return array|WP_Error
	 */
	protected function _save( $image, $filename = null, $mime_type = null ) {
		list( $filename, $extension, $mime_type ) = $this->get_output_format( $filename, $mime_type );

		if ( ! $filename ) {
			$filename = $this->generate_filename( null, null, $extension );
		}

		try {
			$image->setimageformat( strtoupper( $extension ) );

			if ( is_callable( array( $image, 'setinterlacescheme' ) ) ) {
				/** This filter is documented in wp-includes/class-wp-image-editor-gd.php */
				if ( apply_filters( 'image_save_progressive', false, $mime_type ) ) {
					$image->setinterlacescheme( Gmagick::INTERLACE_PLANE );
				} else {
					$image->setinterlacescheme( Gmagick::INTERLACE_NO );
				}
			}

			$dirname = dirname( $filename );
			if ( ! wp_mkdir_p( $dirname ) ) {
				return new WP_Error(
					'image_save_error',
					sprintf( __( 'Unable to create directory %s. Is its parent directory writable by the server?' ), esc_html( $dirname ) )
				);
			}

			if ( wp_is_stream( $filename ) ) {
				if ( file_put_contents( $filename, $image->getimageblob() ) === false ) {
					return new WP_Error( 'image_save_error', __( 'Failed while writing image to stream.' ), $filename );
				}
			} else {
				$image->writeimage( $filename );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'image_save_error', $e->getMessage(), $filename );
		}

		// Set correct file permissions.
		$stat  = stat( dirname( $filename ) );
		$perms = $stat['mode'] & 0000666;
		chmod( $filename, $perms );

		return array(
			'path'      => $filename,
			/** This filter is documented in wp-includes/class-wp-image-editor-gd.php */
			'file'      => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
			'width'     => $this->size['width'],
			'height'    => $this->size['height'],
			'mime-type' => $mime_type,
			'filesize'  => wp_filesize( $filename ),
		);
	}

	/**
	 * Stream the image to the browser.
	 *
	 * @param string $mime_type
	 * @return true|WP_Error
	 */
	public function stream( $mime_type = null ) {
		list( $filename, $extension, $mime_type ) = $this->get_output_format( null, $mime_type );

		try {
			$this->image->setimageformat( strtoupper( $extension ) );

			header( "Content-Type: $mime_type" );
			print $this->image->getimageblob();

			$this->image->setimageformat( $this->get_extension( $this->mime_type ) );
		} catch ( Exception $e ) {
			return new WP_Error( 'image_stream_error', $e->getMessage() );
		}

		return true;
	}
}
