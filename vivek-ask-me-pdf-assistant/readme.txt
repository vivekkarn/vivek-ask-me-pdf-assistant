=== Vivek Ask Me PDF Assistant ===
Contributors: vivekkarn
Tags: assistant, ai, chatbot, pdf, openrouter
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a floating Ask Me AI document assistant widget that answers visitor questions from uploaded PDFs.

== Description ==

Vivek Ask Me PDF Assistant adds a polished floating support-style widget to WordPress websites. Site owners can upload PDF documents, index their content, and let visitors Ask Me AI questions from those documents.

The visitor-facing experience is branded as Ask Me AI rather than as a generic AI chatbot. The assistant retrieves relevant PDF chunks and sends only that context to the configured AI provider.

Features include:

* Floating Ask Me AI launcher.
* Compact support-chat style widget.
* Custom assistant name, welcome message, color, position, placeholder, and suggested questions.
* PDF upload and document management from the WordPress dashboard.
* Local custom tables for documents, chunks, embeddings, and optional chat logs.
* OpenRouter chat completion support.
* Configurable embedding model and embedding endpoint.
* Source PDF names and short snippets where available.
* Nonce-protected REST endpoint with rate limiting.
* Optional shortcode mode with [ask_me_ai_widget].

The plugin is designed to answer only from uploaded documents. If the retrieved document context does not contain the answer, Ask Me AI is instructed to say it does not know based on the available documents.

= Third-party service disclosure =

This plugin connects to OpenRouter only after the site administrator enters an API key and model configuration. OpenRouter is used to generate embeddings for uploaded document chunks and to generate answers from retrieved document context.

Data sent to OpenRouter can include extracted text snippets from uploaded PDFs, visitor questions, configured model names, and normal API request metadata. The OpenRouter API key is stored on the WordPress server and is not exposed to visitors.

OpenRouter service links:

* Website: https://openrouter.ai/
* Terms: https://openrouter.ai/terms
* Privacy: https://openrouter.ai/privacy

== Installation ==

1. Upload the plugin zip from Plugins > Add New > Upload Plugin.
2. Activate Vivek Ask Me PDF Assistant.
3. Open Vivek Ask Me PDF Assistant from the WordPress admin menu.
4. Enter your OpenRouter API key, chat model, and embedding model or endpoint.
5. Upload one or more PDF files.
6. Wait until each document shows Ready.
7. Enable the widget globally or use shortcode-only mode with [ask_me_ai_widget].

== Frequently Asked Questions ==

= Does this plugin require an external AI service? =

Yes. The site admin must provide an OpenRouter API key or a compatible configured endpoint. The API key is stored server-side and is not exposed to visitors.

= Does Ask Me AI train on my documents? =

The plugin stores extracted chunks and embeddings locally in your WordPress database. It sends only the visitor question and retrieved context chunks to the configured AI provider at answer time.

= Can I show the widget only on selected pages? =

Yes. Enable shortcode-only mode and place [ask_me_ai_widget] on the pages where you want the assistant to appear.

= Does this plugin support scanned PDFs? =

Not directly. Scanned image-only PDFs need OCR before upload. Text-based PDFs work best.

== Screenshots ==

1. Floating Ask Me AI widget.
2. Vivek Ask Me PDF Assistant admin settings.
3. PDF upload and indexing dashboard.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
