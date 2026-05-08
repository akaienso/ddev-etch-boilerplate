<?php
/**
 * Automatic.css Activity Log file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\UI\Settings_Page;

use Automatic_CSS\Plugin;
use Automatic_CSS\Traits\ContainerAwareSingleton;

/**
 * Automatic.css Activity Log class.
 */
class Activity_Log {

	use ContainerAwareSingleton;

	/**
	 * Activity log filename.
	 */
	private const ACTIVITY_LOG = 'activity.log';

	/**
	 * Default number of events to display.
	 */
	private const DEFAULT_EVENTS = 50;

	/**
	 * Initialize the Activity_Log class.
	 *
	 * @return void
	 */
	public function init() {
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public static function settings_page() {
		$log_path = self::get_log_path();
		$entries  = self::get_log_entries( $log_path, self::DEFAULT_EVENTS );
		?>
		<h2>Activity Log</h2>
		<p style="max-width: 80ch;">This page shows recent activity from the Automatic.css plugin. Events are sampled (1% of successful requests, 100% of errors) to minimize performance impact.</p>

		<?php if ( ! file_exists( $log_path ) ) : ?>
			<p><strong>No activity log file found.</strong></p>
			<p>The activity log will be created once the plugin records its first event. Make sure the <code>ENABLE_ACTIVITY_LOG</code> flag is enabled.</p>
		<?php elseif ( empty( $entries ) ) : ?>
			<p><strong>The activity log is empty.</strong></p>
		<?php else : ?>
			<p>Showing the last <?php echo esc_html( count( $entries ) ); ?> events. Click a row to view details. <small>Log file: <code><?php echo esc_html( $log_path ); ?></code></small></p>

			<table class="widefat striped acss-activity-log-table" style="margin-top: 1em;">
				<thead>
					<tr>
						<th style="width: 180px;">Timestamp</th>
						<th style="width: 80px;">Method</th>
						<th style="width: 100px;">Trigger</th>
						<th style="width: 80px;">Status</th>
						<th>URI</th>
						<th style="width: 100px;">Duration</th>
					</tr>
				</thead>
				<tbody>
					<?php
					$reversed_entries = array_reverse( $entries );
					foreach ( $reversed_entries as $index => $entry ) :
						// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
						$entry_json = json_encode( $entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
						?>
						<tr class="acss-activity-log-row" data-entry-index="<?php echo esc_attr( $index ); ?>" style="cursor: pointer;">
							<td><?php echo esc_html( self::format_timestamp( $entry['request']['timestamp'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( $entry['request']['method'] ?? '-' ); ?></td>
							<td><?php echo esc_html( $entry['request']['trigger'] ?? '-' ); ?></td>
							<td>
								<?php
								$status       = $entry['result']['status'] ?? '-';
								$status_class = 'failure' === $status ? 'color: #dc3232; font-weight: bold;' : '';
								?>
								<span style="<?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status ); ?></span>
							</td>
							<td style="word-break: break-all; max-width: 400px;"><?php echo esc_html( $entry['request']['uri'] ?? '-' ); ?></td>
							<td><?php echo esc_html( self::format_duration( $entry['result']['duration_ms'] ?? null ) ); ?></td>
						</tr>
						<script type="application/json" id="acss-entry-<?php echo esc_attr( $index ); ?>"><?php echo $entry_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is safe here. ?></script>
					<?php endforeach; ?>
				</tbody>
			</table>

			<!-- Modal for displaying entry details -->
			<div id="acss-activity-log-modal" class="acss-modal" style="display: none;">
				<div class="acss-modal-backdrop"></div>
				<div class="acss-modal-content">
					<div class="acss-modal-header">
						<h3>Event Details</h3>
						<button type="button" class="acss-modal-close" aria-label="Close">&times;</button>
					</div>
					<div class="acss-modal-body">
						<pre id="acss-modal-json"></pre>
					</div>
				</div>
			</div>

			<style>
				.acss-activity-log-table tbody tr:hover {
					background-color: #f0f0f1;
				}
				.acss-modal {
					position: fixed;
					top: 0;
					left: 0;
					right: 0;
					bottom: 0;
					z-index: 100000;
					display: flex;
					align-items: center;
					justify-content: center;
				}
				.acss-modal-backdrop {
					position: absolute;
					top: 0;
					left: 0;
					right: 0;
					bottom: 0;
					background: rgba(0, 0, 0, 0.6);
				}
				.acss-modal-content {
					position: relative;
					background: #fff;
					border-radius: 4px;
					box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
					max-width: 800px;
					width: 90%;
					max-height: 80vh;
					display: flex;
					flex-direction: column;
				}
				.acss-modal-header {
					display: flex;
					justify-content: space-between;
					align-items: center;
					padding: 12px 16px;
					border-bottom: 1px solid #ddd;
					cursor: move;
					user-select: none;
				}
				.acss-modal-content.dragging {
					opacity: 0.9;
				}
				.acss-modal-header h3 {
					margin: 0;
					font-size: 16px;
				}
				.acss-modal-close {
					background: none;
					border: none;
					font-size: 24px;
					cursor: pointer;
					padding: 0;
					line-height: 1;
					color: #666;
				}
				.acss-modal-close:hover {
					color: #000;
				}
				.acss-modal-body {
					padding: 16px;
					overflow: auto;
					flex: 1;
				}
				#acss-modal-json {
					background: #1e1e1e;
					color: #d4d4d4;
					padding: 1em;
					margin: 0;
					overflow-x: auto;
					font-size: 13px;
					line-height: 1.4;
					border-radius: 4px;
					font-family: Consolas, Monaco, 'Courier New', monospace;
				}
				/* JSON syntax highlighting */
				.json-key { color: #9cdcfe; }
				.json-string { color: #ce9178; }
				.json-number { color: #b5cea8; }
				.json-boolean { color: #569cd6; }
				.json-null { color: #569cd6; }
				.json-punctuation { color: #d4d4d4; }
				/* Folding styles */
				.json-folder {
					display: inline-block;
					width: 1em;
					cursor: pointer;
					user-select: none;
					color: #888;
					text-align: center;
				}
				.json-folder:hover { color: #fff; }
				.json-collapsible { display: inline; }
				.json-collapsible.collapsed { display: none; }
				.json-ellipsis {
					display: none;
					color: #888;
					cursor: pointer;
				}
				.json-ellipsis:hover { color: #fff; }
				.json-collapsible.collapsed + .json-ellipsis { display: inline; }
				.json-collapsible.collapsed + .json-ellipsis + .json-bracket { display: inline; }
				.json-collapsible:not(.collapsed) + .json-ellipsis + .json-bracket { display: none; }
				.json-line { display: block; padding-left: 0; }
				.json-bracket { display: none; }
			</style>

			<script>
				(function() {
					var modal = document.getElementById('acss-activity-log-modal');
					var modalJson = document.getElementById('acss-modal-json');
					var rows = document.querySelectorAll('.acss-activity-log-row');
					var folderId = 0;

					/**
					 * Escape HTML characters.
					 */
					function escapeHtml(str) {
						return str
							.replace(/&/g, '&amp;')
							.replace(/</g, '&lt;')
							.replace(/>/g, '&gt;');
					}

					/**
					 * Render JSON value with syntax highlighting and folding.
					 * @param {*} value - The value to render
					 * @param {number} indent - Current indentation level
					 * @param {boolean} needsComma - Whether to append a comma after the value
					 */
					function renderValue(value, indent, needsComma) {
						var comma = needsComma ? '<span class="json-punctuation">,</span>' : '';
						if (value === null) {
							return '<span class="json-null">null</span>' + comma;
						}
						if (typeof value === 'boolean') {
							return '<span class="json-boolean">' + value + '</span>' + comma;
						}
						if (typeof value === 'number') {
							return '<span class="json-number">' + value + '</span>' + comma;
						}
						if (typeof value === 'string') {
							return '<span class="json-string">"' + escapeHtml(value) + '"</span>' + comma;
						}
						if (Array.isArray(value)) {
							return renderArray(value, indent, needsComma);
						}
						if (typeof value === 'object') {
							return renderObject(value, indent, needsComma);
						}
						return escapeHtml(String(value)) + comma;
					}

					/**
					 * Render a JSON object with folding.
					 */
					function renderObject(obj, indent, needsComma) {
						var keys = Object.keys(obj);
						var comma = needsComma ? '<span class="json-punctuation">,</span>' : '';
						if (keys.length === 0) {
							return '<span class="json-punctuation">{}</span>' + comma;
						}

						var id = 'fold-' + (folderId++);
						var spaces = '  '.repeat(indent);
						var innerSpaces = '  '.repeat(indent + 1);
						var html = '<span class="json-folder" data-target="' + id + '">▼</span>';
						html += '<span class="json-punctuation">{</span>';
						html += '<span class="json-collapsible" id="' + id + '">';

						keys.forEach(function(key, i) {
							var isLast = i === keys.length - 1;
							html += '<span class="json-line">' + innerSpaces;
							html += '<span class="json-key">"' + escapeHtml(key) + '"</span>';
							html += '<span class="json-punctuation">:</span> ';
							html += renderValue(obj[key], indent + 1, !isLast);
							html += '</span>';
						});

						html += '<span class="json-line">' + spaces + '<span class="json-punctuation">}</span>' + comma + '</span>';
						html += '</span>';
						html += '<span class="json-ellipsis" data-target="' + id + '">...</span>';
						html += '<span class="json-bracket json-punctuation">}' + (needsComma ? ',' : '') + '</span>';
						return html;
					}

					/**
					 * Render a JSON array with folding.
					 */
					function renderArray(arr, indent, needsComma) {
						var comma = needsComma ? '<span class="json-punctuation">,</span>' : '';
						if (arr.length === 0) {
							return '<span class="json-punctuation">[]</span>' + comma;
						}

						var id = 'fold-' + (folderId++);
						var spaces = '  '.repeat(indent);
						var innerSpaces = '  '.repeat(indent + 1);
						var html = '<span class="json-folder" data-target="' + id + '">▼</span>';
						html += '<span class="json-punctuation">[</span>';
						html += '<span class="json-collapsible" id="' + id + '">';

						arr.forEach(function(item, i) {
							var isLast = i === arr.length - 1;
							html += '<span class="json-line">' + innerSpaces;
							html += renderValue(item, indent + 1, !isLast);
							html += '</span>';
						});

						html += '<span class="json-line">' + spaces + '<span class="json-punctuation">]</span>' + comma + '</span>';
						html += '</span>';
						html += '<span class="json-ellipsis" data-target="' + id + '">' + arr.length + ' items</span>';
						html += '<span class="json-bracket json-punctuation">]' + (needsComma ? ',' : '') + '</span>';
						return html;
					}

					/**
					 * Toggle fold state.
					 */
					function toggleFold(target) {
						var collapsible = document.getElementById(target);
						if (!collapsible) return;

						var folder = modalJson.querySelector('.json-folder[data-target="' + target + '"]');
						if (collapsible.classList.contains('collapsed')) {
							collapsible.classList.remove('collapsed');
							if (folder) folder.textContent = '▼';
						} else {
							collapsible.classList.add('collapsed');
							if (folder) folder.textContent = '▶';
						}
					}

					function openModal(index) {
						var dataScript = document.getElementById('acss-entry-' + index);
						if (dataScript) {
							folderId = 0;
							var data = JSON.parse(dataScript.textContent);
							modalJson.innerHTML = renderValue(data, 0, false);
							// Reset position when opening
							modalContent.style.transform = '';
							modal.style.display = 'flex';
						}
					}

					function closeModal() {
						modal.style.display = 'none';
					}

					rows.forEach(function(row) {
						row.addEventListener('click', function() {
							var index = this.getAttribute('data-entry-index');
							openModal(index);
						});
					});

					modalJson.addEventListener('click', function(e) {
						var target = e.target.getAttribute('data-target');
						if (target) {
							toggleFold(target);
						}
					});

					modal.querySelector('.acss-modal-backdrop').addEventListener('click', closeModal);
					modal.querySelector('.acss-modal-close').addEventListener('click', closeModal);

					document.addEventListener('keydown', function(e) {
						if (e.key === 'Escape' && modal.style.display === 'flex') {
							closeModal();
						}
					});

					// Drag functionality
					var modalContent = modal.querySelector('.acss-modal-content');
					var modalHeader = modal.querySelector('.acss-modal-header');
					var isDragging = false;
					var dragStartX, dragStartY, initialX = 0, initialY = 0;

					modalHeader.addEventListener('mousedown', function(e) {
						if (e.target.classList.contains('acss-modal-close')) return;
						isDragging = true;
						modalContent.classList.add('dragging');
						dragStartX = e.clientX;
						dragStartY = e.clientY;
						// Parse current transform
						var transform = modalContent.style.transform;
						if (transform) {
							var match = transform.match(/translate\((-?\d+)px,\s*(-?\d+)px\)/);
							if (match) {
								initialX = parseInt(match[1], 10);
								initialY = parseInt(match[2], 10);
							}
						}
						e.preventDefault();
					});

					document.addEventListener('mousemove', function(e) {
						if (!isDragging) return;
						var dx = e.clientX - dragStartX;
						var dy = e.clientY - dragStartY;
						modalContent.style.transform = 'translate(' + (initialX + dx) + 'px, ' + (initialY + dy) + 'px)';
					});

					document.addEventListener('mouseup', function() {
						if (isDragging) {
							isDragging = false;
							modalContent.classList.remove('dragging');
							// Update initial position for next drag
							var transform = modalContent.style.transform;
							if (transform) {
								var match = transform.match(/translate\((-?\d+)px,\s*(-?\d+)px\)/);
								if (match) {
									initialX = parseInt(match[1], 10);
									initialY = parseInt(match[2], 10);
								}
							}
						}
					});
				})();
			</script>
		<?php endif; ?>
		<?php
	}

	/**
	 * Get the path to the activity log file.
	 *
	 * @return string
	 */
	public static function get_log_path(): string {
		return Plugin::get_dynamic_css_dir() . '/' . self::ACTIVITY_LOG;
	}

	/**
	 * Get log entries from the activity log file.
	 *
	 * @param string $file_path Path to the log file.
	 * @param int    $count     Number of events to return.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_log_entries( string $file_path, int $count ): array {
		if ( ! file_exists( $file_path ) ) {
			return array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $file_path );

		if ( empty( $content ) ) {
			return array();
		}

		// Split by lines that start with '{' to identify event boundaries.
		// Each JSON event in the activity log starts with '{' on its own line.
		$raw_events = preg_split( '/(?=^\{)/m', $content, -1, PREG_SPLIT_NO_EMPTY );

		if ( empty( $raw_events ) ) {
			return array();
		}

		// Get the last N events.
		$tail_events = array_slice( $raw_events, -$count );

		// Parse each event from JSON.
		$entries = array();
		foreach ( $tail_events as $raw_event ) {
			$decoded = json_decode( trim( $raw_event ), true );
			if ( is_array( $decoded ) ) {
				$entries[] = $decoded;
			}
		}

		return $entries;
	}

	/**
	 * Format a timestamp for display.
	 *
	 * @param string $timestamp ISO 8601 timestamp.
	 * @return string
	 */
	private static function format_timestamp( string $timestamp ): string {
		if ( empty( $timestamp ) ) {
			return '-';
		}

		try {
			$date = new \DateTime( $timestamp );
			return $date->format( 'Y-m-d H:i:s' );
		} catch ( \Exception $e ) {
			return $timestamp;
		}
	}

	/**
	 * Format a duration for display.
	 *
	 * @param float|int|null $duration_ms Duration in milliseconds.
	 * @return string
	 */
	private static function format_duration( $duration_ms ): string {
		if ( null === $duration_ms ) {
			return '-';
		}

		if ( $duration_ms < 1000 ) {
			return round( $duration_ms, 1 ) . ' ms';
		}

		return round( $duration_ms / 1000, 2 ) . ' s';
	}
}
