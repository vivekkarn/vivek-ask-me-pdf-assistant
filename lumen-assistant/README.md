# Lumen Assistant

Lumen Assistant is a WordPress plugin that adds a polished, Intercom-style floating document assistant to your website. Site owners upload PDF files, Lumen indexes the content locally, and visitors can ask questions through a compact support-chat widget.

The visitor experience is intentionally branded as **Ask Lumen**, not as a generic AI chatbot. Answers are generated from the site owner's uploaded documents using retrieval-augmented generation, with source snippets shown where possible.

## What It Does

- Adds a floating **Ask Lumen** chat bubble to the bottom-left or bottom-right of your site.
- Opens a compact support-chat panel inspired by Intercom and modern help widgets.
- Lets admins upload PDF documents from the WordPress dashboard.
- Extracts PDF text, splits it into searchable chunks, and stores embeddings locally.
- Retrieves the most relevant document chunks for each visitor question.
- Uses OpenRouter for chat completion and configurable embedding generation.
- Instructs the assistant to answer only from uploaded PDF context.
- Shows source PDF names, page numbers when available, and short snippets.

## Features

- Floating launcher button.
- Expandable chat panel.
- Header with assistant name and availability text.
- Welcome message.
- Suggested starter questions.
- User and Lumen message bubbles.
- Typing indicator.
- Mobile responsive full-screen mode.
- Smooth open and close animation.
- Optional `Powered by Lumen Assistant` branding.
- Admin-controlled widget color, position, placeholder, and copy.
- Global widget mode or shortcode-only mode.
- Public REST API endpoint with nonce verification and rate limiting.
- Local custom database tables for documents, chunks, embeddings, and optional chat logs.
- Donate link in the admin screen and plugin action links.

## Requirements

- WordPress 6.2 or newer.
- PHP 7.4 or newer.
- An OpenRouter API key.
- A chat model available through OpenRouter.
- An embedding model or compatible embedding endpoint.

## Installation From Zip

1. Download or create the plugin zip file.
2. In WordPress, go to **Plugins > Add New > Upload Plugin**.
3. Upload `lumen-assistant-wordpress-plugin.zip`.
4. Activate **Lumen Assistant**.
5. Open **Lumen Assistant** from the WordPress admin menu.
6. Add your OpenRouter API key and model settings.
7. Upload PDFs and wait until each document shows `Ready`.

## Manual Installation

1. Copy the `lumen-assistant` folder into `wp-content/plugins/`.
2. Activate **Lumen Assistant** from the WordPress Plugins screen.
3. Configure the plugin in **Lumen Assistant**.

The folder name should be `lumen-assistant` for WordPress.org packaging.

## Shortcode

Use this shortcode if you only want Lumen on selected pages:

```text
[ask_me_ai_widget]
```

In the admin settings, enable **Only show where shortcode is used**.

## Recommended OpenRouter Configuration

- Chat model: `openai/gpt-4o-mini`
- Embedding model: `openai/text-embedding-3-small`
- Embedding endpoint: `https://openrouter.ai/api/v1/embeddings`
- Assistant name: `Ask Lumen`
- Placeholder: `Ask Lumen...`
- Context chunks: `5`
- Rate limit: `20` questions per `300` seconds per visitor IP

You can use other OpenRouter-compatible chat and embedding models if your account has access to them.

## Third-Party Service Disclosure

Lumen Assistant connects to OpenRouter only after the site administrator enters an API key and model configuration. OpenRouter is used to generate embeddings for uploaded document chunks and to generate answers from retrieved document context.

Data sent to OpenRouter can include extracted text snippets from uploaded PDFs, visitor questions, configured model names, and normal API request metadata. The OpenRouter API key is stored on the WordPress server and is not exposed to visitors.

OpenRouter links:

- Website: https://openrouter.ai/
- Terms: https://openrouter.ai/terms
- Privacy: https://openrouter.ai/privacy

## How RAG Works

1. Admin uploads a PDF.
2. Lumen extracts text from the PDF.
3. Text is split into overlapping chunks.
4. Each chunk is sent to the configured embedding endpoint.
5. Embedding vectors and chunk metadata are stored in WordPress tables.
6. A visitor asks a question.
7. The question is embedded.
8. Lumen compares the question vector against stored chunk vectors.
9. The most relevant chunks are sent to OpenRouter as context.
10. The response is instructed to use only that retrieved context.

If the answer is not found in the uploaded documents, Lumen should say it does not know based on the available documents.

## Database Tables

The plugin creates these custom tables on activation:

- `{prefix}_ask_me_ai_documents`
- `{prefix}_ask_me_ai_chunks`
- `{prefix}_ask_me_ai_embeddings`
- `{prefix}_ask_me_ai_chat_logs`

Stored data includes:

- Source filename.
- Stored file path.
- Document status.
- Page number when available.
- Chunk text.
- Embedding vector.
- Embedding model.
- Created and updated dates.
- Optional chat logs.

## PDF Extraction Notes

PDF extraction depends heavily on the PDF file itself.

Text-based PDFs usually work best. Scanned image-only PDFs need OCR before upload. Complex layouts, custom encodings, and protected PDFs may produce incomplete text.

If `smalot/pdfparser` is installed through Composer, Lumen will use it. Otherwise, it falls back to a lightweight built-in extractor for common text PDFs.

Developers can override extracted PDF text with:

```php
add_filter( 'ask_me_ai_pdf_text', function ( $pages, $file_path ) {
	return $pages;
}, 10, 2 );
```

## Security

- Admin actions require WordPress permissions and nonces.
- PDF uploads are validated before storage.
- Uploaded documents are stored under the WordPress uploads directory.
- The OpenRouter API key is stored server-side in WordPress options.
- The API key is never exposed to frontend JavaScript.
- Public chat requests require a WordPress REST nonce.
- The public chat endpoint is rate-limited per visitor IP hash.
- Responses expose only answer text and source snippets.

## Limitations

- This plugin does not perform OCR.
- Very large PDFs can take time to index because each chunk needs an embedding request.
- Vector search is performed in PHP over locally stored JSON vectors, which is suitable for modest document libraries on normal WordPress hosting.
- For very large document collections, a dedicated vector database would be more scalable.
- PHP linting and WordPress coding standards checks should be run in a full WordPress development environment before public directory submission.

## Development

Main files:

- `lumen-assistant.php` - plugin bootstrap and plugin metadata.
- `includes/Admin/Admin_Page.php` - WordPress admin dashboard.
- `includes/Core/Database.php` - custom table creation.
- `includes/Core/Rest_Controller.php` - public REST endpoints.
- `includes/Core/Widget.php` - frontend asset loading and shortcode.
- `includes/Services/Document_Indexer.php` - PDF upload and indexing.
- `includes/Services/Retriever.php` - local vector retrieval.
- `includes/Services/Chat_Service.php` - RAG answer orchestration.
- `assets/js/widget.js` - visitor chat widget.
- `assets/css/widget.css` - widget styling.

## Uninstall

Deleting the plugin through WordPress removes:

- Lumen Assistant options.
- Custom database tables.
- Uploaded plugin PDF files.

## Donate

If this plugin helps you, you can support development here:

https://buymemomo.com/vivek

## License

GPL-2.0-or-later is recommended for WordPress plugins. Add a `LICENSE` file before submitting this plugin to the official WordPress.org plugin directory.
