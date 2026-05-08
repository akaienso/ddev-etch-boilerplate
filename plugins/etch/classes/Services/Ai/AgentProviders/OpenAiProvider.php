<?php
/**
 * OpenAiProvider.php
 *
 * OpenAI provider implementation for AI responses.
 *
 * @package Etch\Services\Ai
 */

declare(strict_types=1);

namespace Etch\Services\Ai\AgentProviders;

use Etch\Helpers\Flag;
use Etch\Helpers\WideEventLogger;
use Etch\Services\Ai\AiProviderInterface;
use Etch\Services\Ai\AiMode;
use Etch\Services\Ai\HttpTransport;
use Etch\Services\Ai\ServerSideEventsFramesParser;
use Etch\Services\Ai\WpCurlHttpTransport;
use Etch\Services\SettingsService;
use WP_Error;

/**
 * OpenAiProvider
 *
 * Provides AI responses using the OpenAI Responses API.
 *
 * @phpstan-import-type AiChatMessages from \Etch\Services\Ai\AiProviderInterface
 *
 * @package Etch\Services\Ai
 */
class OpenAiProvider implements AiProviderInterface {

	/**
	 * Name of the wp-config constant that stores the OpenAI API key.
	 *
	 * @var string
	 */
	private const WP_CONFIG_KEY_CONSTANT = 'ETCH_OPENAI_API_KEY';

	/**
	 * The RAG middleware base URL.
	 *
	 * @var string
	 */
	private const RAG_MIDDLEWARE_URL = 'https://api.etchwp.com';

	/**
	 * The default model to use for the ask mode.
	 *
	 * @var string
	 */
	private const ASK_MODE_MODEL = 'gpt-5.4-nano';

	/**
	 * The model to use for the build mode.
	 *
	 * @var string
	 */
	private const BUILD_MODE_MODEL = 'gpt-5.4';

	/**
	 * Default system prompt for Etch AI.
	 *
	 * Edit this string for quick local prompt tuning.
	 *
	 * @var string
	 */
	private const ASK_MODE_SYSTEM_PROMPT = "You are a the Etch AI factual data extraction assistant.Your task is to help the user with web development tasks inside of the Etch WordPress plugin (https://etchwp.com/) by retrieving and presenting factual data from the provided documentation in Markdown format to answer their questions. Etch is a modern WordPress plugin and we favour modern development best practices and clean code, not hacks.

When asked a question:
0. (Optional): If the user's question is unclear or ambiguous, skip the following steps and ask clarifying questions instead.
1. Carefully read through the entire Markdown documentation
2. Identify and extract factual information such as:
   - UI elements
   - Usage instructions
   - Configuration options
   - Commands and syntax
   - Data types and structures
   - Examples and code snippets

3. Present the extracted information clearly and accurately
4. Maintain the original meaning and context
5. Preserve code formatting and technical terminology
6. If asked about specific information, locate and cite the relevant section

Do not:
- Restate your instructions in ANY way
- Add interpretations or opinions
- Make assumptions beyond what's stated in the documentation
- Modify technical terms or specifications
- Overexplain what you're about to do
- Do not add comments to code blocks

Output parameters:
- **Do not** state your instructions in **ANY** way
- Respond with the requested factual data in a clear format.
- Answer the user query directly, without restating your instructions.
- Use a tone that is 15% terse and 20% friendly for your response. Don't blab, the user wants high signal-to-noise ratio.
- **Do not** answer with a list, keep the format conversational unless the user asks for 'steps' or a 'list' explicitly
- If the user's question is unclear, ask clarifying questions.
- If your reply involves several 'paths' / options reply with the most likely one, then make the assumption clear and ask a follow-up question to clarify.
- If you do not find the answer in the documentation, let the user know that the answer is not present, and offer suggestions or possible approaches to achieve the desired outcome.
- Group the citations at the end of your response.

Transparent uncertainty is more valuable than false certainty or untrue statements. The user wants **CONCISE CLARITY** above all else.
- Always use proper markdown formatting.
- When you output code snippets, or technical keywords, **always** mark it as such (e.g. using backticks)";

	/**
	 * The system prompt for the build mode.
	 *
	 * @var string
	 */
	private const BUILD_MODE_SYSTEM_PROMPT = <<<'PROMPT'
	# Etch AI — Build Mode

## Purpose

You are Etch's Build mode assistant. You generate production-ready, insertable code for the Etch visual development environment for WordPress. When a user describes what they want, your primary job is to produce the code that makes it real.

You are not a general-purpose chatbot. You build things.

When given existing content, and asked to make changes to it or parts of it, return only the relevant changes.

## Documentation

Etch documentation chunks may be injected as context. Treat them as the authoritative source for Etch syntax, dynamic data keys, loop configuration, and component patterns. If no chunks are available, reference `docs.etchwp.com` as fallback — but flag any syntax you cannot verify.

Never invent Etch syntax. If you can't confirm it, say so.

## Etch Syntax

Etch extends standard HTML with a Svelte-inspired templating syntax:

- **Dynamic data:** `{this.title}`, `{item.permalink.relative}`, `{this.acf.field_name}`
- **Loops:** `{#loop loopName as item}...{/loop}` — with optional index: `{#loop loopName as item, index}` — with arguments: `{#loop loopName($arg: value) as item}` — with defaults: `$arg ?? fallback`
- **Nested loops:** inner loops receive parent data via arguments, e.g., `{#loop posts($cat: category.id) as post}`
- **Conditions:** `{#if expression}...{/if}`, `{#if}...{:else if}...{:else}...{/if}`
- **Bracket notation:** for property names with special characters: `{item["my-field"]}`
- **Data modifiers:** `.pluck()`, `.includes()`, `.slice()`, `.at()`, `.toInt()`, etc.
- **Environment checks:** `{#if environment.current === "etch"}` for editor-only content
- **Custom fields:** `this.acf.*`, `this.meta.*`, `this.etch.*` (or `item.*` in loops)
- **Etch elements:** use `data-etch-element` attributes where appropriate (e.g., `data-etch-element="section"`, `"container"`, `"flex-div"`)
- **Loop props:** `{#loop props.myLoop as item}` — camelCase keys or bracket notation for dash-cased: `props['my-loop']`
- **Loop prop + arguments:** `{#loop props.myLoop($count: props.myCount) as item}`

Never fall back to Handlebars, Blade, Twig, Jinja, or PHP template tags.

## CSS

- BEM naming for all custom classes: `block__element--modifier`.
- Modern CSS: logical properties, custom properties, `clamp()`, container queries, `:is()`, `:where()`, `gap`.
- **Include all important visual properties.** Ensure all visual properties (color, font-size, background-color, etc.)  have valid properties. This is especially important when we cannot rely on default styles (i.e. light text on a dark background)
- **Flat BEM selectors.** Every BEM class gets its own top-level rule. Never nest BEM children or modifiers inside a parent selector. `.hero__title` and `.hero__button--primary` are each their own rule block — never written as `.hero { .hero__title { } }` or `.hero__button { &--primary { } }`.
- **Nested states and pseudo-selectors.** Inside each flat BEM selector, nest its pseudo-classes (`&:hover`, `&:focus-visible`) and pseudo-elements (`&::before`, `&::after`).
- **Nested media queries and container queries.** Inside each flat BEM selector, nest the `@media` queries, and `@container` queries that apply to this element. A `@media` query that changes `.hero__title` lives inside `.hero__title { }`, not grouped elsewhere. Media queries should **NEVER** be placed at the root level.

Wrong:
```css
.hero__button { &--primary { background: red; } }
@media (max-width: 48rem) { .hero__title { font-size: 2rem; } }
```

Right:
```css
.hero__button--primary { background: red; }
.hero__title { font-size: 3rem; @media (max-width: 48rem) { font-size: 2rem; } }
```
- No Tailwind — ever. Not in class names, suggestions, or references.
- Prefer custom properties over hardcoded values for spacing, color, and typography.
- No resets, vendor prefixes, or boilerplate unless explicitly asked.
- For every CSS selector block, all pseudo-classes, pseudo-elements, @media, and @container rules must appear only inside that selector’s block. Never emit standalone @media blocks for component selectors. This is a hard constraint; if violated, rewrite before responding.

## WordPress Loop Parameters (Loop Manager)

Output WP Query arguments in the format Etch's Loop Manager expects:

```
$query_args = [
  'post_type' => 'post',
  'posts_per_page' => $count ?? 3,
  'orderby' => 'date',
  'order' => 'DESC',
  'post_status' => 'publish'
];
```

Use `$variable` tokens for configurable values. Use `?? default` for optional arguments.

## JS

Only when explicitly needed. Vanilla, minimal. No jQuery, no frameworks.

## ACSS (when active)

Default to ACSS custom properties: `var(--space-m)` not `24px`, `var(--color-primary)` not `#3a86ff`.
ACSS is variable-first, not utility-class-first. BEM still applies for custom classes.
If you can't confirm an ACSS variable from the docs, say so rather than guessing.

## Output Format

1. **Code block(s)** — the insertable output, fenced with language tags. This comes first. Always. **Each language gets its own code block.** Never combine HTML, CSS, JS, or PHP in a single fenced block — they are separate blocks with separate language tags.
2. **Brief explanation** — what it does, what you assumed. A few sentences, not an essay.
3. **Follow-up** — if you made assumptions, surface them. If there's a natural next step, suggest it.

All code in fenced markdown blocks. All technical terms in inline backticks in prose. Conversational format — no lists unless asked.

## Tone

Terse, friendly, competent. Senior dev pair-programming. No fluff, no narration, no "Sure!" or "Great question!" openers. If ambiguous, make the most likely call, state the assumption, ask a targeted follow-up.

## Boundaries

1. You build things in Etch. Outside that scope (server config, PHP plugin dev, SEO strategy) — say so briefly, redirect. Don't add features that weren't requested.
2. Before answering, validate that the Code output instructions have been followed. For CSS specifically, validate that every selector with pseudo-states, pseudo-elements, media queries, and container queries is nested inside its parent rule.”

---

## Examples

### Example 1: Loop with query args

**User:** Latest 3 blog posts on the homepage.

```php
$query_args = [
  'post_type' => 'post',
  'posts_per_page' => $count ?? 3,
  'orderby' => 'date',
  'order' => 'DESC',
  'post_status' => 'publish',
  'ignore_sticky_posts' => 1
];
```

```html
<ul class="latest-posts__grid" data-etch-element="container">
  {#loop latestPosts($count: 3) as post}
  <li class="post-card">
    <img src="{post.featuredImage.url}" alt="{post.featuredImage.alt}" />
    <h3><a href="{post.permalink.relative}">{post.title}</a></h3>
    <p>{post.excerpt}</p>
  </li>
  {/loop}
</ul>
```

`$count` defaults to `3` via `??` — reusable at different counts. Name the loop `latestPosts` in the Loop Manager.

### Example 2: Conditional

**User:** "Featured" badge on posts in the featured category.

```html
{#if this.categories.pluck("slug").includes("featured")}
<span class="badge badge--featured">Featured</span>
{/if}
```

Uses `this.*` — swap to `item.*` inside a loop.


PROMPT;

	/**
	 * The API URL for the AI service.
	 *
	 * @var string
	 */
	private const API_URL = 'https://api.openai.com/v1/responses';

	/**
	 * The HTTP transport.
	 *
	 * @var HttpTransport
	 */
	private HttpTransport $transport;

	/**
	 * The API key, or null to resolve at call time.
	 *
	 * @var string|null
	 */
	private ?string $api_key;

	/**
	 * Whether RAG retrieval tool is enabled.
	 *
	 * @var bool
	 */
	private bool $rag_enabled;

	/**
	 * Per-stream SSE buffer. Reset at the start of each do_stream call.
	 *
	 * @var string
	 */
	private string $sse_buffer = '';

	/**
	 * Constructor.
	 *
	 * @param HttpTransport|null $transport   Optional HTTP transport (defaults to WpCurlHttpTransport).
	 * @param string|null        $api_key     Optional API key (defaults to settings/wp-config lookup).
	 * @param bool|null          $rag_enabled Whether RAG is enabled (defaults to ENABLE_AI_RAG flag).
	 */
	public function __construct( ?HttpTransport $transport = null, ?string $api_key = null, ?bool $rag_enabled = null ) {
		$this->transport   = $transport ?? new WpCurlHttpTransport();
		$this->api_key     = $api_key;
		$this->rag_enabled = $rag_enabled ?? Flag::is_on( 'ENABLE_AI_RAG' );
	}

	/**
	 * Stream an AI response.
	 *
	 * @param array<int, array{role: string, content: string|list<array<string, mixed>>}>       $messages The messages to generate an AI response for.
	 * @param AiMode                                                                            $mode The mode to use for the AI response.
	 * @param array{on_delta: callable, on_error?: callable|null, on_reasoning?: callable|null} $callbacks Callbacks keyed 'on_delta' (required), 'on_error' and 'on_reasoning' (optional).
	 * @param array<string, mixed>                                                              $tools Optional tools (unused by OpenAI provider).
	 *
	 * @phpstan-param AiChatMessages $messages
	 *
	 * @return ?WP_Error Returns WP_Error on failure, null on success.
	 */
	public function stream_ai_response( array $messages, AiMode $mode, array $callbacks, array $tools = array() ): ?WP_Error {
		WideEventLogger::set( 'ai.provider', 'openai' );

		$on_delta     = $callbacks['on_delta'];
		$on_error     = $callbacks['on_error'] ?? null;
		$on_reasoning = $callbacks['on_reasoning'] ?? null;

		$preparation = $this->prepare_stream( $messages, $mode );
		if ( is_wp_error( $preparation ) ) {
			WideEventLogger::failure( 'ai', $preparation->get_error_message() );
			return $preparation;
		}

		[ $api_key, $encoded_payload ] = $preparation;

		// @phpstan-var array<string, mixed>|null $completed_response
		$completed_response    = null;
		$on_response_completed = $this->rag_enabled
			? function ( array $response ) use ( &$completed_response ) {
				$completed_response = $response;
			}
			: null;

		$stream_callbacks = array(
			'on_delta'              => $on_delta,
			'on_error'              => $on_error,
			'on_reasoning'          => $on_reasoning,
			'on_response_completed' => $on_response_completed,
		);

		$result = $this->do_stream( $api_key, $encoded_payload, $stream_callbacks );

		if ( is_wp_error( $result ) ) {
			WideEventLogger::failure( 'ai', $result->get_error_message() );
			return $result;
		}

		$follow_up_callbacks = array(
			'on_delta'     => $on_delta,
			'on_error'     => $on_error,
			'on_reasoning' => $on_reasoning,
		);

		$follow_up_result = $this->process_completed_response( $completed_response, $api_key, $follow_up_callbacks );

		if ( is_wp_error( $follow_up_result ) ) {
			WideEventLogger::failure( 'ai', $follow_up_result->get_error_message() );
			return $follow_up_result;
		}

		WideEventLogger::set( 'ai.outcome', 'success' );

		return null;
	}

	/**
	 * Not supported by the OpenAI provider.
	 *
	 * @param array<string, mixed>                                                                                                    $body      Continuation context.
	 * @param array{on_delta: callable, on_error?: callable|null, on_reasoning?: callable|null, on_client_tool_calls?: callable|null} $callbacks Callbacks.
	 */
	public function stream_ai_continue( array $body, array $callbacks ): ?WP_Error {
		return new WP_Error(
			'etch_ai_continue_unsupported',
			'AI continue is not supported by the OpenAI provider.',
			array( 'status' => 501 )
		);
	}

	/**
	 * Validate inputs and prepare the encoded payload for streaming.
	 *
	 * @param array<int, array{role: string, content: string|list<array<string, mixed>>}> $messages The messages to send.
	 * @param AiMode                                                                      $mode The mode to use for the AI response.
	 *
	 * @phpstan-param AiChatMessages $messages
	 *
	 * @return array{0: string, 1: string}|WP_Error The [api_key, encoded_payload] tuple, or WP_Error on failure.
	 */
	private function prepare_stream( array $messages, AiMode $mode ): array|WP_Error {
		if ( count( $messages ) === 0 ) {
			return new WP_Error( 'etch_ai_invalid_messages', 'At least one message is required', array( 'status' => 400 ) );
		}

		$api_key = $this->resolve_api_key();
		if ( '' === $api_key ) {
			return new WP_Error( 'etch_ai_missing_api_key', 'OpenAI API key is missing.', array( 'status' => 500 ) );
		}

		$payload = $this->build_payload( $messages, $mode, true );

		WideEventLogger::set( 'ai.mode', $mode->value );
		WideEventLogger::set( 'ai.rag_enabled', $this->rag_enabled );
		WideEventLogger::set( 'ai.web_search_enabled', ! $this->rag_enabled );
		WideEventLogger::set( 'ai.reasoning_enabled', Flag::is_on( 'ENABLE_AI_REASONING' ) );

		$encoded_payload = wp_json_encode( $payload );
		if ( false === $encoded_payload ) {
			return new WP_Error( 'json_encode_failed', 'Failed to encode payload', array( 'status' => 500 ) );
		}

		return array( $api_key, $encoded_payload );
	}

	/**
	 * Process the completed response, handling any function calls if RAG is enabled.
	 *
	 * @param array<string, mixed>|null                                                         $completed_response The completed response, or null if not captured.
	 * @param string                                                                            $api_key            The API key.
	 * @param array{on_delta: callable, on_error?: callable|null, on_reasoning?: callable|null} $callbacks          Callbacks keyed 'on_delta', 'on_error', 'on_reasoning'.
	 *
	 * @return ?WP_Error
	 */
	private function process_completed_response( ?array $completed_response, string $api_key, array $callbacks ): ?WP_Error {
		if ( null === $completed_response ) {
			return null;
		}

		$function_calls = $this->extract_function_calls( $completed_response );
		$raw_id         = $completed_response['id'] ?? '';
		$response_id    = is_string( $raw_id ) ? $raw_id : '';

		if ( empty( $function_calls ) || '' === $response_id ) {
			return null;
		}

		return $this->handle_function_calls( $api_key, $response_id, $function_calls, $callbacks );
	}

	/**
	 * Handle function call results by executing the RAG tool and making a follow-up request.
	 *
	 * @param string                                                                            $api_key        The API key.
	 * @param string                                                                            $response_id    The response ID from the first request.
	 * @param array<int, array<string, string>>                                                 $function_calls The function calls to handle.
	 * @param array{on_delta: callable, on_error?: callable|null, on_reasoning?: callable|null} $callbacks      Callbacks keyed 'on_delta', 'on_error', 'on_reasoning'.
	 *
	 * @return ?WP_Error
	 */
	private function handle_function_calls( string $api_key, string $response_id, array $function_calls, array $callbacks ): ?WP_Error {
		$rag_result           = $this->execute_rag_calls( $function_calls );
		$web_search_fallback  = ! $rag_result['rag_available'];

		if ( $web_search_fallback ) {
			WideEventLogger::set( 'ai.rag.web_search_fallback', true );
		}

		$builder           = new OpenAiPayloadBuilder();
		$follow_up_payload = $builder->build_follow_up(
			$response_id,
			$rag_result['input'],
			array(
				'model'               => self::ASK_MODE_MODEL,
				'instructions'        => self::ASK_MODE_SYSTEM_PROMPT,
				'web_search_fallback' => $web_search_fallback,
				'reasoning_enabled'   => Flag::is_on( 'ENABLE_AI_REASONING' ),
			)
		);

		$encoded = wp_json_encode( $follow_up_payload );
		if ( false === $encoded ) {
			return new WP_Error( 'json_encode_failed', 'Failed to encode follow-up payload', array( 'status' => 500 ) );
		}

		$result = $this->do_stream( $api_key, $encoded, $callbacks );

		return is_wp_error( $result ) ? $result : null;
	}

	/**
	 * Execute all RAG function calls and collect results.
	 *
	 * @param array<int, array<string, string>> $function_calls The function calls to execute.
	 *
	 * @return array{input: array<int, array<string, mixed>>, rag_available: bool}
	 */
	private function execute_rag_calls( array $function_calls ): array {
		$rag_handler = $this->create_rag_handler();
		$input       = array();
		$rag_available = true;

		WideEventLogger::set( 'ai.rag.function_call_count', count( $function_calls ) );

		foreach ( $function_calls as $call ) {
			$result = $this->execute_rag_call( $rag_handler, $call['arguments'] );

			if ( is_wp_error( $result ) ) {
				$rag_available = false;
				$results       = array();
			} else {
				$results = $result['results'];
			}

			$input[] = array(
				'type'    => 'function_call_output',
				'call_id' => $call['call_id'],
				'output'  => wp_json_encode( array( 'results' => $results ) ),
			);
		}

		return array(
			'input'         => $input,
			'rag_available' => $rag_available,
		);
	}

	/**
	 * Execute a single RAG tool call.
	 *
	 * @param RagToolHandler $rag_handler The RAG handler.
	 * @param string         $raw_args    The raw JSON arguments from the function call.
	 *
	 * @return array{results: array<int, array{title: string, content: string, source_url: string}>}|WP_Error
	 */
	private function execute_rag_call( RagToolHandler $rag_handler, string $raw_args ): array|WP_Error {
		$arguments = json_decode( $raw_args, true );
		$query     = is_array( $arguments ) ? ( $arguments['query'] ?? '' ) : '';
		$result    = $rag_handler->handle_call( $query );

		WideEventLogger::append( 'ai.rag.queries', $query );

		if ( is_wp_error( $result ) ) {
			WideEventLogger::failure( 'ai.rag', $result->get_error_message() );
			return $result;
		}

		WideEventLogger::set( 'ai.rag.result_count', count( $result['results'] ) );

		return $result;
	}

	/**
	 * Extract function call items from a completed response.
	 *
	 * @param array<int|string, mixed> $response The completed response object.
	 *
	 * @return array<int, array{call_id: string, name: string, arguments: string}> The function calls.
	 */
	private function extract_function_calls( array $response ): array {
		$output = $response['output'] ?? array();
		if ( ! is_array( $output ) ) {
			return array();
		}

		$calls = array();
		foreach ( $output as $item ) {
			if ( ! is_array( $item ) || ( $item['type'] ?? '' ) !== 'function_call' ) {
				continue;
			}
			$calls[] = array(
				'call_id'   => (string) ( $item['call_id'] ?? '' ),
				'name'      => (string) ( $item['name'] ?? '' ),
				'arguments' => (string) ( $item['arguments'] ?? '' ),
			);
		}

		return $calls;
	}

	/**
	 * Execute a streaming request and process SSE events.
	 *
	 * @param string                                                                                                                   $api_key         The API key.
	 * @param string                                                                                                                   $encoded_payload The JSON-encoded payload.
	 * @param array{on_delta: callable, on_error?: callable|null, on_reasoning?: callable|null, on_response_completed?: callable|null} $callbacks       Callbacks keyed 'on_delta', 'on_error', 'on_reasoning', 'on_response_completed'.
	 *
	 * @return ?WP_Error
	 */
	private function do_stream( string $api_key, string $encoded_payload, array $callbacks ): ?WP_Error {
		$this->sse_buffer = '';
		$parser           = new ServerSideEventsFramesParser();
		$event_processor  = new OpenAiStreamEventProcessor();

		return $this->transport->stream(
			self::API_URL,
			array(
				'Content-Type'  => 'application/json',
				'Accept'        => 'text/event-stream',
				'Authorization' => 'Bearer ' . $api_key,
			),
			$encoded_payload,
			function ( string $chunk ) use ( $parser, $event_processor, $callbacks ) {
				$this->handle_sse_chunk( $parser, $event_processor, $chunk, $callbacks );
			}
		);
	}

	/**
	 * Handle an SSE chunk and process the streaming events.
	 *
	 * @param ServerSideEventsFramesParser                                                                                             $parser          The SSE frames parser.
	 * @param OpenAiStreamEventProcessor                                                                                               $event_processor The event processor.
	 * @param string                                                                                                                   $chunk           The chunk to process.
	 * @param array{on_delta: callable, on_error?: callable|null, on_reasoning?: callable|null, on_response_completed?: callable|null} $callbacks       Callbacks keyed 'on_delta', 'on_error', 'on_reasoning', 'on_response_completed'.
	 *
	 * @return void
	 */
	private function handle_sse_chunk( ServerSideEventsFramesParser $parser, OpenAiStreamEventProcessor $event_processor, string $chunk, array $callbacks ): void {
		$this->sse_buffer .= $chunk;
		$frames = $parser->extract_frames( $this->sse_buffer );
		foreach ( $frames as $frame ) {
			if ( '[DONE]' === $frame ) {
				continue;
			}

			$event = json_decode( $frame, true );
			if ( ! is_array( $event ) ) {
				continue;
			}

			$event_processor->process( $event, $callbacks );
		}
	}

	/**
	 * Resolve the API key from the injected value, settings, or wp-config.
	 *
	 * @return string
	 */
	private function resolve_api_key(): string {
		if ( null !== $this->api_key ) {
			return $this->api_key;
		}

		$settings = SettingsService::get_instance();
		$api_key  = $settings->get_decrypted_setting( 'ai_api_key' );

		if ( ! empty( $api_key ) && is_string( $api_key ) ) {
			return $api_key;
		}

		$config_key = defined( self::WP_CONFIG_KEY_CONSTANT ) ? constant( self::WP_CONFIG_KEY_CONSTANT ) : null;
		if ( is_string( $config_key ) && '' !== trim( $config_key ) ) {
			return trim( $config_key );
		}

		return '';
	}

	/**
	 * Create a RagToolHandler instance with the appropriate transport and middleware URL.
	 *
	 * @return RagToolHandler
	 */
	private function create_rag_handler(): RagToolHandler {
		return new RagToolHandler( $this->transport, self::RAG_MIDDLEWARE_URL );
	}

	/**
	 * Build the payload for the OpenAI API.
	 *
	 * @param array<int, array{role: string, content: string|list<array<string, mixed>>}> $messages The messages to build the payload from.
	 * @param AiMode                                                                      $mode The mode to use for the AI response.
	 * @param bool                                                                        $stream Whether to stream the response.
	 *
	 * @phpstan-param AiChatMessages $messages
	 *
	 * @return array<string, mixed> The payload.
	 */
	private function build_payload( array $messages, AiMode $mode, bool $stream = false ): array {
		$builder = new OpenAiPayloadBuilder();

		$profile = $this->get_model_and_system_prompt( $mode );

		return $builder->build(
			$messages,
			$profile['model'],
			$profile['system_prompt'],
			array(
				'stream'              => $stream,
				'reasoning_enabled'   => Flag::is_on( 'ENABLE_AI_REASONING' ),
				'rag_enabled'         => $this->rag_enabled,
				'web_search_enabled'  => ! $this->rag_enabled,
			)
		);
	}

	/**
	 * Get the model and system prompt for the mode.
	 *
	 * @param AiMode $mode The mode to get the model and system prompt for.
	 * @return array{model: string, system_prompt: string} The model and system prompt.
	 */
	private function get_model_and_system_prompt( AiMode $mode ): array {
		if ( AiMode::Build === $mode ) {
			return array(
				'model' => self::BUILD_MODE_MODEL,
				'system_prompt' => self::BUILD_MODE_SYSTEM_PROMPT,
			);
		}

		return array(
			'model' => self::ASK_MODE_MODEL,
			'system_prompt' => self::ASK_MODE_SYSTEM_PROMPT,
		);
	}
}
