<?php
/**
 * Dynamic Image Block
 *
 * Renders image elements with dynamic attributes and context support.
 * Specialized version of ElementBlock for image rendering with tag fixed to 'img'.
 * Resolves dynamic expressions in image attributes.
 *
 * @package Etch
 */

namespace Etch\Blocks\DynamicImageBlock;

use Etch\Blocks\Types\ElementAttributes;
use Etch\Blocks\Global\StylesRegister;
use Etch\Blocks\Global\ScriptRegister;
use Etch\Blocks\Global\DynamicContent\DynamicContextProvider;
use Etch\Blocks\Global\Utilities\DynamicContentProcessor;
use Etch\Blocks\Utilities\EtchTypeAsserter;
use Etch\Blocks\Utilities\ShortcodeProcessor;
use Etch\Helpers\SvgLoader;

/**
 * DynamicImageBlock class
 *
 * Handles rendering of etch/dynamic-image blocks with image-specific functionality.
 * Supports dynamic expression resolution in image attributes (e.g., {this.title}, {props.value}).
 *
 * @phpstan-type AttachmentMetadata array{width: int, height: int, file: string, sizes: array<string, array{file: string, width: int, height: int, mime-type: string}>, image_meta: array<string, mixed>, filesize: int}
 */
class DynamicImageBlock {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register the block
	 *
	 * @return void
	 */
	public function register_block() {
		register_block_type(
			'etch/dynamic-image',
			array(
				'api_version' => '3',
				'attributes' => array(
					'tag' => array(
						'type' => 'string',
						'default' => 'img', // Tag is always 'img' for DynamicImage blocks
					),
					'attributes' => array(
						'type' => 'object',
						'default' => array(),
					),
					'styles' => array(
						'type' => 'array',
						'default' => array(),
						'items' => array(
							'type' => 'string',
						),
					),
				),
				'supports' => array(
					'html' => false,
					'className' => false,
					'customClassName' => false,
					// '__experimentalNoWrapper' => true,
					'innerBlocks' => true,
				),
				'render_callback' => array( $this, 'render_block' ),
				'skip_inner_blocks' => true,
			)
		);
	}

	/**
	 * Render the block
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content Block content (not used for SVG blocks).
	 * @param \WP_Block|null       $block WP_Block instance (contains context).
	 * @return string Rendered block HTML.
	 */
	public function render_block( $attributes, $content, $block = null ) {
		$attrs = ElementAttributes::from_array( $attributes );

		ScriptRegister::register_script( $attrs );

		$resolved_attributes = $this->resolve_dynamic_attributes( $attrs, $block );

		// Register styles (original + dynamic) after EtchParser processing
		StylesRegister::register_block_styles( $attrs->styles ?? array(), $attrs->attributes, $resolved_attributes );

		$resolved_attributes = $this->process_shortcodes( $resolved_attributes );

		// Extract Etch props and clean image attributes
		$media_id = $resolved_attributes['mediaId'] ?? null;
		$media_id = is_string( $media_id ) ? $media_id : '';
		$use_srcset = $this->should_use_srcset( $resolved_attributes );
		$maximum_size = $this->get_maximum_size( $resolved_attributes );

		$image_attributes = $this->remove_etch_related_props( $resolved_attributes );

		// No mediaId provided, return <img> with attributes as is
		if ( empty( $media_id ) ) {
			return $this->render_static_image( $image_attributes );
		}

		return $this->render_media_library_image(
			$media_id,
			$image_attributes,
			$use_srcset,
			$maximum_size
		);
	}

	/**
	 * Build HTML attribute string from an array of attributes
	 *
	 * @param array<string, mixed> $attributes Attributes array.
	 * @return string Attribute string.
	 */
	private function build_attribute_string( array $attributes ): string {
		$attribute_string = '';
		foreach ( $attributes as $name => $value ) {
			$attribute_string .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( EtchTypeAsserter::to_string( $value ) ) );
		}
		return $attribute_string;
	}

	/**
	 * Render a static image tag when no mediaId is provided
	 *
	 * @param array<string, mixed> $image_attributes Cleaned image attributes (Etch props removed).
	 * @return string Rendered img tag.
	 */
	private function render_static_image( array $image_attributes ): string {
		if ( ! array_key_exists( 'src', $image_attributes ) ) {
			return $this->render_placeholder_image( 'No image source provided', $image_attributes );
		}
		if ( ! array_key_exists( 'alt', $image_attributes ) ) {
			$image_attributes['alt'] = '';
		}
		return '<img' . $this->build_attribute_string( $image_attributes ) . ' />';
	}

	/**
	 * Render an image from the WordPress media library
	 *
	 * @param string               $media_id Media attachment ID as string.
	 * @param array<string, mixed> $image_attributes Cleaned image attributes (Etch props removed).
	 * @param bool                 $use_srcset Whether to generate srcset attributes.
	 * @param string               $maximum_size Maximum image size name.
	 * @return string Rendered img tag.
	 */
	private function render_media_library_image( string $media_id, array $image_attributes, bool $use_srcset, string $maximum_size ): string {
		$attachment = wp_get_attachment_metadata( intval( $media_id ) );

		// If no attachment found, return a placeholder with error message
		if ( empty( $attachment ) ) {
			return $this->render_placeholder_image( 'Image with ID ' . $media_id . ' not found', $image_attributes );
		}

		// Remove src from user attributes to avoid conflicts with mediaId
		unset( $image_attributes['src'] );

		$maximum_image_src = '';
		if ( false !== wp_get_attachment_image_src( intval( $media_id ), $maximum_size ) ) {
			$maximum_image_src = wp_get_attachment_image_src( intval( $media_id ), $maximum_size )[0];
		}

		$special_attribute_string = '';

		// Skip srcset generation if useSrcSet is false or full size has no width (e.g. on an svg)
		$has_dimensions = isset( $attachment['width'] ) && $attachment['width'] > 1; // @phpstan-ignore isset.offset (WordPress type claims width always exists, but it may not for all media types)
		if ( $use_srcset && $has_dimensions ) {
			$special_attribute_string .= $this->build_srcset_attributes( $attachment, $media_id, $maximum_size );
		}

		// Always set src to the selected maximum_size
		$special_attribute_string .= sprintf( ' src="%s"', esc_attr( $maximum_image_src ) );

		if ( ! $this->has_user_set_alt_attribute( $image_attributes ) ) {
			$special_attribute_string .= $this->get_alt_text_from_media_library( (int) $media_id );
		}

		$special_attribute_string .= $this->maybe_add_dimensions( (int) $media_id, $image_attributes, $maximum_size );

		return '<img ' . $special_attribute_string . $this->build_attribute_string( $image_attributes ) . ' />';
	}

	/**
	 * Determine whether srcset should be used
	 *
	 * @param array<string, mixed> $resolved_attributes Resolved attributes.
	 * @return bool True if srcset should be generated.
	 */
	private function should_use_srcset( array $resolved_attributes ): bool {
		if ( ! empty( $resolved_attributes['useSrcSet'] ) ) {
			return in_array( $resolved_attributes['useSrcSet'], array( 'true', '1', 'yes', 'on' ), true );
		}
		return true;
	}

	/**
	 * Get the maximum image size name
	 *
	 * @param array<string, mixed> $resolved_attributes Resolved attributes.
	 * @return string Image size name (defaults to 'full').
	 */
	private function get_maximum_size( array $resolved_attributes ): string {
		if ( isset( $resolved_attributes['maximumSize'] ) && is_string( $resolved_attributes['maximumSize'] ) ) {
			return $resolved_attributes['maximumSize'];
		}
		return 'full';
	}

	/**
	 * Build srcset and sizes attribute string for responsive images
	 *
	 * @param AttachmentMetadata $attachment Attachment metadata.
	 * @param string             $media_id Media attachment ID.
	 * @param string             $maximum_size Maximum image size name.
	 * @return string Attribute string containing srcset and sizes.
	 */
	private function build_srcset_attributes( $attachment, $media_id, $maximum_size ) {
		$image_sizes = $attachment['sizes'];
		$max_width = isset( $image_sizes[ $maximum_size ] )
			? $image_sizes[ $maximum_size ]['width']
			: $attachment['width'];

		$srcset = $this->build_srcset_value( $attachment, $media_id, $image_sizes, $max_width );
		$sizes = sprintf( '(max-width: %dpx) 100vw, %dpx', esc_attr( (string) $max_width ), esc_attr( (string) $max_width ) );

		return sprintf( ' sizes="%s" srcset="%s"', esc_attr( $sizes ), esc_attr( $srcset ) );
	}

	/**
	 * Build the srcset value string from available image sizes.
	 *
	 * @param AttachmentMetadata                                                             $attachment Attachment metadata.
	 * @param string                                                                         $media_id Media attachment ID.
	 * @param array<string, array{file: string, width: int, height: int, mime-type: string}> $image_sizes Available image sizes.
	 * @param int                                                                            $max_width Maximum width to include.
	 * @return string Comma-separated srcset entries.
	 */
	private function build_srcset_value( $attachment, $media_id, $image_sizes, $max_width ) {
		$entries = array();

		$full_entry = $this->build_srcset_entry( $media_id, 'full', $attachment['width'], $max_width );
		if ( '' !== $full_entry ) {
			$entries[] = $full_entry;
		}

		foreach ( $image_sizes as $size_name => $size_data ) {
			$entry = $this->build_srcset_entry( $media_id, $size_name, $size_data['width'], $max_width );
			if ( '' !== $entry ) {
				$entries[] = $entry;
			}
		}

		return implode( ', ', $entries );
	}

	/**
	 * Build a single srcset entry if the size fits within max_width.
	 *
	 * @param string $media_id  Media attachment ID.
	 * @param string $size_name Image size name.
	 * @param int    $width     Width of this size.
	 * @param int    $max_width Maximum width to include.
	 * @return string Srcset entry (e.g. "url 300w") or empty string.
	 */
	private function build_srcset_entry( $media_id, $size_name, $width, $max_width ) {
		if ( $width > $max_width ) {
			return '';
		}

		$url = $this->get_image_src_url( $media_id, $size_name );
		if ( '' === $url ) {
			return '';
		}

		return $url . ' ' . $width . 'w';
	}

	/**
	 * Get the URL for an attachment at a given size, or empty string if unavailable.
	 *
	 * @param string $media_id Media attachment ID.
	 * @param string $size     Image size name.
	 * @return string Image URL or empty string.
	 */
	private function get_image_src_url( $media_id, $size ) {
		$src = wp_get_attachment_image_src( intval( $media_id ), $size );
		if ( false === $src ) {
			return '';
		}
		return $src[0];
	}

	/**
	 * Resolve dynamic attributes from context
	 *
	 * @param ElementAttributes $attrs Block attributes.
	 * @param \WP_Block|null    $block WP_Block instance.
	 * @return array<string, mixed> Resolved attributes.
	 */
	private function resolve_dynamic_attributes( ElementAttributes $attrs, $block ) {
		$resolved_attributes = $attrs->attributes;
		$sources = DynamicContextProvider::get_sources_for_wp_block( $block );

		if ( empty( $sources ) ) {
			return $resolved_attributes;
		}
		$resolved_attributes = DynamicContentProcessor::resolve_attributes( $resolved_attributes, array( 'sources' => $sources ) );

		return $resolved_attributes;
	}

	/**
	 * Process shortcodes in attribute values
	 *
	 * @param array<string, mixed> $attributes Attributes to process.
	 * @return array<string, mixed> Processed attributes.
	 */
	private function process_shortcodes( array $attributes ) {
		foreach ( $attributes as $name => $value ) {
			$string_value = EtchTypeAsserter::to_string( $value );
			$attributes[ $name ] = ShortcodeProcessor::process( $string_value, 'etch/dynamic-image' );
		}
		return $attributes;
	}

	/**
	 * Maybe add width and height attributes
	 *
	 * @param int                  $media_id Media attachment ID.
	 * @param array<string, mixed> $attributes Image attributes.
	 * @param string               $size Image size name.
	 * @return string Attribute string.
	 */
	private function maybe_add_dimensions( int $media_id, array $attributes, string $size ) {
		$attrs = '';

		// SVGs have no intrinsic pixel dimensions — skip width/height entirely.
		if ( 'image/svg+xml' === get_post_mime_type( $media_id ) ) {
			return '';
		}

		$src_data = wp_get_attachment_image_src( $media_id, $size );

		if ( ! $src_data ) {
			return '';
		}

		// Wordpress sometimes returns width and height as 1 for files without dimensions, so we check for that here as well
		if ( ! array_key_exists( 'width', $attributes ) && $src_data[1] > 1 ) {
			$attrs .= sprintf( ' width="%d"', (int) $src_data[1] );
		}

		if ( ! array_key_exists( 'height', $attributes ) && $src_data[2] > 1 ) {
			$attrs .= sprintf( ' height="%d"', (int) $src_data[2] );
		}

		return $attrs;
	}

	/**
	 * Check if the user has set the alt attribute
	 *
	 * @param array<string, mixed> $attributes Attributes array.
	 * @return bool True if the user has set the alt attribute.
	 */
	private function has_user_set_alt_attribute( array $attributes ): bool {
		return array_key_exists( 'alt', $attributes );
	}

	/**
	 * Get alt text from media library
	 *
	 * @param int $media_id Media attachment ID.
	 * @return string Alt text or empty string if no alt.
	 */
	private function get_alt_text_from_media_library( int $media_id ): string {
		$alt_text = get_post_meta( $media_id, '_wp_attachment_image_alt', true );

		// Always output an alt attribute for accessibility — empty string if no alt text is set
		if ( ! is_string( $alt_text ) ) {
			$alt_text = '';
		}

		return sprintf( ' alt="%s"', esc_attr( $alt_text ) );
	}

	/**
	 * Render a placeholder image with a message as an inline SVG data URI
	 *
	 * @param string               $message Message to display in the placeholder.
	 * @param array<string, mixed> $image_attributes User-set attributes to preserve on the placeholder img.
	 * @return string Rendered img tag with SVG placeholder.
	 */
	private function render_placeholder_image( string $message, array $image_attributes = array() ): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="200">'
			. '<rect width="100%" height="100%" fill="#f0f0f0"/>'
			. '<text x="50%" y="50%" font-family="sans-serif" font-size="16" fill="#999" text-anchor="middle" dominant-baseline="middle">'
			. esc_html( $message )
			. '</text></svg>';

		// The placeholder owns src; any user-set src would duplicate the attribute.
		unset( $image_attributes['src'] );

		if ( ! array_key_exists( 'alt', $image_attributes ) ) {
			$image_attributes['alt'] = '';
		}

		$src_attr = ' src="' . esc_attr( 'data:image/svg+xml,' . rawurlencode( $svg ) ) . '"';

		return '<img' . $src_attr . $this->build_attribute_string( $image_attributes ) . '/>';
	}

	/**
	 * Remove Etch-related props from attributes
	 *
	 * @param array<string, mixed> $attributes Attributes array.
	 * @return array<string, mixed> Cleaned attributes.
	 */
	private function remove_etch_related_props( array $attributes ) {
		unset( $attributes['mediaId'], $attributes['useSrcSet'], $attributes['maximumSize'] );
		return $attributes;
	}
}
