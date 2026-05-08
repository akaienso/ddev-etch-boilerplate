(function () {
	document.addEventListener("DOMContentLoaded", () => {
		if (!automatic_css_block_editor_options) {
			console.error(
				"automatic_css_block_editor_options is not defined. Please check if the plugin is activated."
			);
			return;
		}

		let root_font_size =
			automatic_css_block_editor_options.root_font_size + "%";

		whenEditorIsReady().then((_) => {
			console.log("Editor is ready");
			let iframe_container = document.querySelector(
				"iframe[name=editor-canvas]"
			);
			let post_container = document.querySelector(".editor-styles-wrapper");
			if (iframe_container) {
				console.log("Site editor detected");
				fix_site_editor_rfs(root_font_size);
			} else if (post_container) {
				console.log("Post editor detected");
				fix_post_editor_rfs(root_font_size);
			}
		});
	});

	/**
	 * Fix the root font size in the post editor.
	 *
	 * @param {string} root_font_size The root font size to be applied.
	 */
	function fix_post_editor_rfs(root_font_size) {
		let html = document.querySelector("html");
		html.style.fontSize = root_font_size;
	}

	/**
	 * Fix the root font size in the site editor.
	 * Based on: https://gist.github.com/KevinBatdorf/fca19e1f3b749b5c57db8158f4850eff?permalink_comment_id=4469942#gistcomment-4469942
	 *
	 * @param {string} root_font_size The root font size to be applied.
	 */
	function fix_site_editor_rfs(root_font_size) {
		getEditorIframe().then((iframe) => {
			let iframe_html =
				iframe.contentDocument.querySelector("html") ||
				iframe.contentWindow.document.querySelector("html");
			iframe_html.style.fontSize = root_font_size;

			let load_gutenberg_styles_using_enqueue_stylesheet =
				automatic_css_block_editor_options.load_gutenberg_styles_using_enqueue_stylesheet;
			console.log(
				"load_gutenberg_styles_using_enqueue_stylesheet",
				load_gutenberg_styles_using_enqueue_stylesheet
			);

			if (load_gutenberg_styles_using_enqueue_stylesheet === "on") {
				// Find the 'automaticcss-core-for-block-editor-css' link tag and replace the href with the iframed content.
				const link_tag = iframe.contentDocument.querySelector(
					"#automaticcss-core-for-block-editor-css"
				);
				link_tag.href =
					automatic_css_block_editor_options.iframed_stylesheet_url;
			} else {
				// Find style tag with the specific CSS comment
				const iframe_document =
					iframe.contentDocument || iframe.contentWindow.document;
				const style_tags = iframe_document.querySelectorAll("style");

				const core_block_editor_comment =
					"/* File: automatic-core-for-block-editor.css";
				for (let style_tag of style_tags) {
					if (!style_tag.textContent.includes(core_block_editor_comment)) {
						continue;
					}
					style_tag.textContent =
						automatic_css_block_editor_options.iframed_content;
					break;
				}
			}
		});
	}

	/**
	 * Wait for the editor to be ready.
	 *
	 * @returns {Promise} A promise that resolves when the editor is ready.
	 */
	function whenEditorIsReady() {
		const select = wp.data.select,
			subscribe = wp.data.subscribe;
		return new Promise((resolve) => {
			const unsubscribe = subscribe(() => {
				// This will trigger after the initial render blocking, before the window load event
				// This seems currently more reliable than using __unstableIsEditorReady
				if (
					select("core/editor").isCleanNewPost() ||
					select("core/block-editor").getBlockCount() > 0
				) {
					unsubscribe();
					resolve();
				}
			});
		});
	}

	/**
	 * Get the editor iframe.
	 *
	 * @returns {Promise} A promise that resolves when the editor iframe is loaded.
	 */
	async function getEditorIframe() {
		await whenEditorIsReady();

		const editorCanvasIframeElement = document.querySelector(
			'[name="editor-canvas"]'
		);

		return new Promise((resolve) => {
			if (!editorCanvasIframeElement.loading) {
				// somehow the iframe has already loaded,
				// skip waiting for onload event (won't be triggered)
				resolve(editorCanvasIframeElement);
			}

			editorCanvasIframeElement.onload = () => {
				resolve(editorCanvasIframeElement);
			};
		});
	}
})();
