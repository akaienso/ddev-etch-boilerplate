<?php
/**
 * SamplerInterface - Contract for sampling decisions.
 *
 * @package Automatic_CSS\Lib\WideEvents\Sampler
 */

namespace Automatic_CSS\Lib\WideEvents\Sampler;

/**
 * SamplerInterface.
 *
 * Determines whether an event should be emitted.
 * Used to reduce log volume while ensuring important events are captured.
 */
interface SamplerInterface {

	/**
	 * Determine if the event should be sampled (emitted).
	 *
	 * @param bool $has_error Whether the event contains an error.
	 * @return bool True if the event should be emitted.
	 */
	public function should_sample( bool $has_error ): bool;
}
