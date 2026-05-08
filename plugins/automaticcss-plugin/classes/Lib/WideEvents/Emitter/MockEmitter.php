<?php
/**
 * MockEmitter - Test emitter that stores events in memory.
 *
 * @package Automatic_CSS\Lib\WideEvents\Emitter
 */

namespace Automatic_CSS\Lib\WideEvents\Emitter;

/**
 * MockEmitter class.
 *
 * Captures emitted events in memory for testing purposes.
 * Provides methods to inspect emitted data.
 */
class MockEmitter implements EmitterInterface {

	/**
	 * Captured events.
	 *
	 * @var array<int, string>
	 */
	private array $events = array();

	/**
	 * Emit the formatted event data.
	 *
	 * Stores the data in memory instead of writing to file.
	 *
	 * @param string $formatted_data The formatted event data to emit.
	 * @return bool Always returns true.
	 */
	public function emit( string $formatted_data ): bool {
		$this->events[] = $formatted_data;
		return true;
	}

	/**
	 * Get the count of emitted events.
	 *
	 * @return int The number of events emitted.
	 */
	public function get_count(): int {
		return count( $this->events );
	}

	/**
	 * Get all emitted events as raw strings.
	 *
	 * @return array<int, string> Array of formatted event strings.
	 */
	public function get_events(): array {
		return $this->events;
	}

	/**
	 * Get the last emitted event as decoded array.
	 *
	 * @return array<string, mixed>|null The decoded event data or null if no events.
	 */
	public function get_last_event(): ?array {
		if ( empty( $this->events ) ) {
			return null;
		}

		$last = $this->events[ count( $this->events ) - 1 ];
		$decoded = json_decode( $last, true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Clear all captured events.
	 *
	 * @return void
	 */
	public function clear(): void {
		$this->events = array();
	}
}
