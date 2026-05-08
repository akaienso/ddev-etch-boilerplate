<?php
/**
 * String Property
 *
 * Represents a string component property definition.
 * Supports specializations: color, url, image, select, wpMediaId.
 *
 * @package Etch\Blocks\Types\ComponentProperty
 */

namespace Etch\Blocks\Types\ComponentProperty;

use Etch\Blocks\Utilities\EtchTypeAsserter;

/**
 * StringProperty class
 */
class StringProperty extends ComponentProperty {

	/**
	 * Default value
	 *
	 * @var string|null
	 */
	public $default = null;

	/**
	 * Options array (for select properties)
	 *
	 * @var array<string>|null
	 */
	public ?array $options = null;

	/**
	 * Select options as string
	 *
	 * @var string|null
	 */
	public ?string $selectOptionsString = null;

	/**
	 * Check if this is a string property with a specialized type
	 *
	 * @return bool
	 */
	public function is_specialized_string(): bool {
		return '' !== $this->specialized;
	}

	/**
	 * Create from property data array.
	 *
	 * @param array<string, mixed> $data Property data array.
	 * @return self
	 */
	public static function from_property_array( array $data ): self {
		$instance = new self();
		self::extract_base( $data, $instance );

		if ( isset( $data['options'] ) && is_array( $data['options'] ) ) {
			$instance->options = $data['options'];
		}

		if ( isset( $data['selectOptionsString'] ) && is_string( $data['selectOptionsString'] ) ) {
			$instance->selectOptionsString = $data['selectOptionsString'];
		}

		return $instance;
	}

	/**
	 * Resolve the value for this property.
	 *
	 * @param mixed                                         $value   The value to resolve.
	 * @param array<int, array{key: string, source: mixed}> $sources Sources for dynamic expression evaluation.
	 * @return string
	 */
	public function resolve_value( $value, array $sources ): mixed {
		if ( null === $value || '' === $value ) {
			return '';
		}

		return EtchTypeAsserter::to_string( $value );
	}

	/**
	 * Convert to array
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$result = parent::to_array();

		if ( null !== $this->options ) {
			$result['options'] = $this->options;
		}

		if ( null !== $this->selectOptionsString ) {
			$result['selectOptionsString'] = $this->selectOptionsString;
		}

		return $result;
	}
}
