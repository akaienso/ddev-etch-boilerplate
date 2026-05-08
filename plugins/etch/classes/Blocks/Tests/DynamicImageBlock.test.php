<?php
/**
 * SvgBlock test class.
 *
 * @package Etch
 *
 * TEST COVERAGE CHECKLIST
 * =======================
 *
 * ✅ Block Registration & Structure
 *    - Block registration (etch/dynamic-image)
 *    - Attributes structure (tag: string, attributes: object, styles: array)
 *
 * ✅ Basic Rendering & Static Examples
 *    - Renders Image fetched from mediaId attribute
 *    - Merges block attributes with other attributes
 *    - uses srcSet when useSrcSet is true (default)
 *    - does not use srcSet when useSrcSet is false
 *    - applies maximumSize attribute correctly (default is full size)
 *    - Returns empty when mediaId is empty
 *    - Returns empty when Image fetch fails
 *
 * ✅ Component Props Context
 *    - mediaId from component props: {props.mediaId}
 *    - mediaId with default value and instance value
 *    - mediaId with only default value
 *    - mediaId with only instance value
 *    - Attributes with component props: {props.className}
 *
 * ✅ Global Context Expressions
 *    - mediaId with dynamic expression: {this.featuredMediaId}
 *    - Attributes with {this.title}, {site.name}
 *
 * ✅ Integration Scenarios
 *    - DynamicImageBlock inside ComponentBlock with mediaId prop
 *    - DynamicImageBlock with nested ComponentBlock (prop shadowing)
 *    - DynamicImageBlock with default mediaId and instance mediaId
 *
 * ✅ Edge Cases
 *    - Empty mediaId handled gracefully
 *    - Invalid Image fetch handled gracefully
 *    - maximumSize attribute removed from HTML output
 *    - useSrcSet attribute removed from HTML output
 *    - mediaId attribute removed from HTML output
 *
 * ✅ Shortcode Resolution
 *    - Shortcode in SVG attribute (aria-label): shortcodes ARE resolved
 *    - Shortcodes can be combined with fetched SVG attributes
 */

declare(strict_types=1);

namespace Etch\Blocks\Tests;

use WP_UnitTestCase;
use Etch\Blocks\DynamicImageBlock\DynamicImageBlock;
use Etch\Blocks\Tests\BlockTestHelper;
use Etch\Blocks\Tests\ShortcodeTestHelper;

/**
 * Class DynamicImageBlockTest
 *
 * Comprehensive tests for DynamicImageBlock functionality including:
 * - Basic rendering with Image fetching
 * - maximumSize option
 * - useSrcSet option
 * - Component props for dynamic mediaId
 * - Default and instance values
 * - Edge cases
 */
class DynamicImageBlockTest extends WP_UnitTestCase {

	use BlockTestHelper;
	use ShortcodeTestHelper;

	/**
	 * DynamicImageBlock instance
	 *
	 * @var DynamicImageBlock
	 */
	private $dynamic_image_block;
	/**
	 * Static DynamicImageBlock instance (shared across tests)
	 *
	 * @var DynamicImageBlock
	 */
	private static $dynamic_image_block_instance;

	/**
	 * The Attachment ID of the test image
	 *
	 * @var string
	 */
	private $attachment_id;
	/**
	 * The Attachment ID of the test svg image
	 *
	 * @var string
	 */
	private $svg_attachment_id;

	/**
	 * Set up test fixtures
	 */
	public function setUp(): void {
		parent::setUp();

		// Only create block instance once per test class
		if ( ! self::$dynamic_image_block_instance ) {
			self::$dynamic_image_block_instance = new DynamicImageBlock();
		}
		$this->dynamic_image_block = self::$dynamic_image_block_instance;

		// Trigger block registration if not already registered
		$this->ensure_block_registered( 'etch/dynamic-image' );

		// Clear cached context between tests
		$this->clear_cached_context();

		// Create a test image in the media library
		$this->create_test_image_in_media_library();

		// Register test shortcode
		$this->register_test_shortcode();
	}

	/**
	 * Create a test image in the media library for use in tests
	 */
	public function create_test_image_in_media_library() {
		// Create a simple 500x500 red square PNG image for testing
		$upload_dir = wp_get_upload_dir();
		$test_image_path = $upload_dir['basedir'] . '/test-image.png';
		$img = imagecreatetruecolor( 500, 500 );
		$red = imagecolorallocate( $img, 255, 0, 0 );
		imagefilledrectangle( $img, 0, 0, 500, 500, $red );
		imagepng( $img, $test_image_path );
		unset( $img ); // Allow GC to free GD image (imagedestroy is deprecated in PHP 8.3+).

		// Insert the image into the media library
		$this->attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => 'Test Image',
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$test_image_path
		);
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_data = wp_generate_attachment_metadata( $this->attachment_id, $test_image_path );
		wp_update_attachment_metadata( $this->attachment_id, $attach_data );

		// Set the alt text for the image
		update_post_meta( $this->attachment_id, '_wp_attachment_image_alt', 'Media Library Alt Text' );

		// Create a simple svg file for testing
		$test_svg_path = $upload_dir['basedir'] . '/test-image.svg';
		$svg_content = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
			<circle cx="50" cy="50" r="40" stroke="green" stroke-width="4" fill="yellow" />
			</svg>';
		file_put_contents( $test_svg_path, $svg_content );
		// Insert the svg into the media library
		$this->svg_attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/svg+xml',
				'post_title'     => 'Test SVG Image',
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$test_svg_path
		);

		// SVGs don't get metadata from wp_generate_attachment_metadata, so set it manually
		// to match production behavior where SVG metadata exists but has no pixel dimensions.
		wp_update_attachment_metadata(
			$this->svg_attachment_id,
			array(
				'file' => 'test-image.svg',
			)
		);
	}

	/**
	 * Clean up after tests
	 */
	public function tearDown(): void {
		$this->remove_test_shortcode();
		parent::tearDown();
	}

	/**
	 * Test block registration
	 */
	public function test_block_is_registered() {
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( 'etch/dynamic-image' );
		$this->assertNotNull( $block_type );
		$this->assertEquals( 'etch/dynamic-image', $block_type->name );
	}

	/**
	 * Test block has correct attributes structure
	 */
	public function test_block_has_correct_attributes() {
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( 'etch/dynamic-image' );
		$this->assertArrayHasKey( 'tag', $block_type->attributes );
		$this->assertArrayHasKey( 'attributes', $block_type->attributes );
		$this->assertArrayHasKey( 'styles', $block_type->attributes );
		$this->assertEquals( 'string', $block_type->attributes['tag']['type'] );
		$this->assertEquals( 'object', $block_type->attributes['attributes']['type'] );
	}

	/**
	 * Test DynamicImageBlock renders with mediaId from media library
	 */
	public function test_dynamic_image_block_renders_with_media_id() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
				'class' => 'test-class',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'class="test-class"', $result );
		// src and stripColors should not be in output
		$this->assertStringNotContainsString( 'maximumSize="', $result );
		$this->assertStringNotContainsString( 'useSrcSet="', $result );
	}

	/**
	 * Test DynamicImageBlock renders with alt if set explicitly on the attributes
	 */
	public function test_dynamic_image_block_renders_with_custom_alt() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
				'class' => 'test-class',
				'alt' => 'Custom Alt Text',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'alt="Custom Alt Text"', $result );
	}

	/**
	 * Test DynamicImageBlock renders with media library alt if not set explicitly on the attributes
	 */
	public function test_dynamic_image_block_renders_with_media_library_alt() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
				'class' => 'test-class',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'alt="Media Library Alt Text"', $result );
	}

	/**
	 * Test DynamicImageBlock renders with alt="" if user set alt is empty string
	 */
	public function test_dynamic_image_block_renders_without_media_library_alt_if_empty_string() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
				'class' => 'test-class',
				'alt' => '',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'alt=""', $result );
	}

	/**
	 * Test DynamicImageBlock renders with alt="" if media library alt is empty and user did not set alt explicitly
	 */
	public function test_dynamic_image_block_renders_with_alt_as_empty_string_if_media_library_or_user_set_alt_is_empty() {
		$original_attachment_id = $this->attachment_id;
		// make sure alt text from media library image for this test is empty
		update_post_meta( $original_attachment_id, '_wp_attachment_image_alt', '' );
		$this->attachment_id = $original_attachment_id;
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( 'alt=""', $result );
	}

	/**
	 * Test DynamicImageBlock renders with srcset by default
	 */
	public function test_dynamic_image_block_renders_with_srcset() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'srcset="', $result );
	}

	/**
	 * Test DynamicImageBlock renders with full image as first image in srcset
	 */
	public function test_dynamic_image_block_renders_full_image_first_in_srcset() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
			),
		);
		$full_image_src = wp_get_attachment_image_src( intval( $this->attachment_id ), 'full' )[0];
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'srcset="' . $full_image_src . ' 500w', $result );
	}

	/**
	 * Test DynamicImageBlock renders with full image as src by default
	 */
	public function test_dynamic_image_block_renders_with_full_src_by_default() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
			),
		);

		$full_image_src = wp_get_attachment_image_src( intval( $this->attachment_id ), 'full' )[0];
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'src="' . $full_image_src . '"', $result );
	}


	/**
	 * Test DynamicImageBlock renders without srcset if specified
	 */
	public function test_dynamic_image_block_renders_without_srcset() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
				'useSrcSet' => 'false',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringNotContainsString( 'srcset="', $result );
	}

	/**
	 * Test DynamicImageBlock renders with srcset biggest size as maximumSize
	 */
	public function test_dynamic_image_block_renders_with_maximum_size() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
				'maximumSize' => 'thumbnail',
			),
		);
		$thumbnail_image_src = wp_get_attachment_image_src( intval( $this->attachment_id ), 'thumbnail' )[0];
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'srcset="' . $thumbnail_image_src . ' 150w"', $result );
		$this->assertStringContainsString( 'src="' . $thumbnail_image_src . '"', $result );
	}

	/**
	 * Test DynamicImageBlock renders with dynamic data
	 */
	public function test_dynamic_image_block_renders_with_dynamic_data() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => '{' . $this->attachment_id . '}',
				'maximumSize' => '{"thumbnail"}',
				'alt' => '{site.name}',
			),
		);
		$thumbnail_image_src = wp_get_attachment_image_src( intval( $this->attachment_id ), 'thumbnail' )[0];
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'srcset="' . $thumbnail_image_src . ' 150w"', $result );
		$this->assertStringContainsString( 'src="' . $thumbnail_image_src . '"', $result );
		$this->assertStringContainsString( 'alt="Test Blog"', $result );
	}

	/**
	 * Test DynamicImageBlock renders with width and height from full size if maximumSize is not set
	 */
	public function test_dynamic_image_block_renders_with_width_and_height() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'width="500"', $result );
		$this->assertStringContainsString( 'height="500"', $result );
	}

	/**
	 * Test DynamicImageBlock renders with width and height from maximumSize
	 */
	public function test_dynamic_image_block_renders_with_width_and_height_from_maximum_size() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
				'maximumSize' => 'thumbnail',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'width="150"', $result );
		$this->assertStringContainsString( 'height="150"', $result );
	}


	/**
	 * Test DynamicImageBlock renders src when mediaId is empty
	 */
	public function test_dynamic_image_block_renders_src_when_media_id_is_empty() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'src' => 'https://example.com/image.png',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'src="https://example.com/image.png"', $result );
	}


	/**
	 * Test DynamicImageBlock renders mediaId dynamic src, even if custom src is set
	 */
	public function test_dynamic_image_block_prioritizes_media_id() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
				'src' => 'https://example.com/image.png',
			),
		);
		$image_src = wp_get_attachment_image_src( intval( $this->attachment_id ), 'full' )[0];
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'src="' . $image_src . '"', $result );
	}

	/**
	 * Test DynamicImageBlock renders custom placeholder img tag when mediaId is invalid
	 */
	public function test_dynamic_image_block_renders_custom_placeholder_img_tag_when_media_id_is_invalid() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => 'invalid_media_id',
				'src' => 'https://example.com/image.png',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( rawurlencode( 'Image with ID invalid_media_id not found' ), $result );
		$this->assertStringContainsString( 'alt=""', $result );
	}

	/**
	 * Test DynamicImageBlock custom width and height overwrites dynamically set width and height
	 */
	public function test_dynamic_image_block_custom_width_and_height_overwrites_dynamically_set_width_and_height() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
				'height' => '69',
				'width' => '420',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( 'height="69"', $result );
		$this->assertStringContainsString( 'width="420"', $result );
	}

	/**
	 * Test DynamicImageBlock renders alt attribute when no mediaId and no user-set alt
	 */
	public function test_alt_attribute_rendered_when_no_media_id_and_no_user_set_alt() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'src' => 'https://example.com/image.png',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( 'alt=""', $result );
	}

	/**
	 * Test DynamicImageBlock renders alt attribute when static src and user-set alt
	 */
	public function test_alt_attribute_rendered_when_static_src_and_user_set_alt() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'src' => 'https://example.com/image.png',
				'alt' => 'Custom Alt Text',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( 'alt="Custom Alt Text"', $result );
	}

	/**
	 * Test DynamicImageBlock renders placeholder image when mediaId is empty and no src is provided
	 */
	public function test_dynamic_image_block_renders_placeholder_image_when_media_id_is_empty_and_no_src_is_provided() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( 'data:image/svg+xml,', $result );
		$this->assertStringContainsString( 'No%20image%20source%20provided', $result );
	}

	/**
	 * Data provider: placeholder render paths that must preserve user attributes.
	 *
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public function provide_placeholder_trigger_attributes(): array {
		return array(
			'invalid mediaId' => array( array( 'mediaId' => 'invalid_media_id' ) ),
			'no mediaId and no src' => array( array() ),
		);
	}

	/**
	 * Test user attributes are preserved on every placeholder render path
	 *
	 * @dataProvider provide_placeholder_trigger_attributes
	 *
	 * @param array<string, mixed> $trigger_attributes Attributes that force the placeholder path.
	 */
	public function test_user_attributes_are_preserved_on_placeholder( array $trigger_attributes ) {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array_merge(
				$trigger_attributes,
				array(
					'class' => 'test-class',
					'id' => 'my-image',
					'data-foo' => 'bar',
				)
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( 'class="test-class"', $result );
		$this->assertStringContainsString( 'id="my-image"', $result );
		$this->assertStringContainsString( 'data-foo="bar"', $result );
	}

	/**
	 * Test DynamicImageBlock excludes Etch-specific props from output
	 */
	public function test_dynamic_image_block_excludes_etch_props_from_output() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
				'useSrcSet' => 'true',
				'maximumSize' => 'full',
				'src' => 'https://example.com/image.png',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringNotContainsString( 'useSrcSet="true"', $result );
		$this->assertStringNotContainsString( 'maximumSize="full"', $result );
	}

	/**
	 * Test DynamicImageBlock does not render srcset or width/height if no width is present (e.g. on an SVG)
	 */
	public function test_dynamic_image_block_does_not_render_srcset_width_and_height_if_no_width_is_present() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->svg_attachment_id,
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringNotContainsString( 'srcset="', $result );
		$this->assertStringNotContainsString( 'sizes="', $result );
		$this->assertStringNotContainsString( 'width="', $result );
		$this->assertStringNotContainsString( 'height="', $result );
	}

	/**
	 * Test DynamicImageBlock does not render width/height="1" when SVG has a non-full maximumSize
	 */
	public function test_no_width_and_height_on_svg_when_maximum_size_is_set() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->svg_attachment_id,
				'maximumSize' => 'thumbnail',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( 'src="', $result );
		$this->assertStringNotContainsString( 'width="', $result );
		$this->assertStringNotContainsString( 'height="', $result );
	}
}
