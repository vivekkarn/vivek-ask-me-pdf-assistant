# AskMe AI Docs

AskMe AI Docs is a WordPress plugin that adds a polished Intercom-inspired floating website assistant. Site owners upload PDF documents, the plugin indexes their text into local database chunks with embeddings, and visitors can ask questions through a compact support-chat widget.

The assistant is designed to answer only from uploaded documents. If retrieved PDF context does not contain the answer, it is instructed to say it does not know based on the available documents.

## Features

- Branded bottom-left or bottom-right chat launcher.
- Compact support chat panel with header, online status, welcome message, suggestions, message bubbles, typing indicator, and mobile full-screen mode.
- Admin customization for assistant name, welcome message, color, position, placeholder, suggested questions, and branding text.
- Donate button in the admin screen and plugin action links.
- PDF upload, document list, delete, and rebuild index actions.
- Local custom tables for documents, chunks, embeddings, and optional chat logs.
- OpenRouter chat completions.
- Configurable embedding model and embedding endpoint.
- Public REST chat endpoint protected by a WordPress nonce and IP-based rate limiting.
- Shortcode: `[ask_me_ai_widget]`.

## Installation

1. Copy the `ask-me-ai` folder into `wp-content/plugins/`.
2. Activate **Ask Me AI** in the WordPress Plugins screen.
3. Open **Ask Me AI** in the WordPress admin menu.
4. Enter your OpenRouter API key.
5. Upload one or more PDF files and wait for each document to show `Ready`.
6. Enable the widget globally or enable shortcode-only mode and place `[ask_me_ai_widget]` on selected pages.

## Example Configuration

- OpenRouter API key: your OpenRouter key.
- Chat model: `openai/gpt-4o-mini`
- Embedding model: `openai/text-embedding-3-small`
- Embedding endpoint: `https://openrouter.ai/api/v1/embeddings`
- Assistant name: `AskMe AI Docs`
- Context chunks: `5`
- Rate limit: `20` questions per `300` seconds per visitor IP.

## Support Development

If this plugin helps you, you can donate here:

https://buymemomo.com/vivek

## Architecture

1. **Upload**: Admin uploads a PDF through the dashboard.
2. **Extraction**: `Pdf_Text_Extractor` extracts text. If `smalot/pdfparser` is available it is used; otherwise the plugin uses a best-effort built-in parser for common text PDFs.
3. **Chunking**: `Text_Chunker` splits extracted text into overlapping chunks.
4. **Embedding**: `OpenRouter_Client` calls the configured embedding endpoint for each chunk.
5. **Storage**: Document metadata, text chunks, and JSON embedding vectors are stored in WordPress custom tables.
6. **Retrieval**: Visitor questions are embedded and compared locally with cosine similarity.
7. **Generation**: The most relevant chunks are sent to OpenRouter with strict instructions to answer only from retrieved context.
8. **Citations**: The REST response includes source PDF names, page numbers when available, and short snippets.

## Database Tables

- `{prefix}_ask_me_ai_documents`
- `{prefix}_ask_me_ai_chunks`
- `{prefix}_ask_me_ai_embeddings`
- `{prefix}_ask_me_ai_chat_logs`

## PDF Extraction Notes

PDF text extraction varies widely across hosting environments and PDF encodings. For production sites with scanned PDFs or complex layouts, install a stronger extractor such as `smalot/pdfparser` through Composer or provide custom extraction with:

```php
add_filter( 'ask_me_ai_pdf_text', function ( $pages, $file_path ) {
    return $pages;
}, 10, 2 );
```

Scanned image-only PDFs need OCR before indexing.

## Security Notes

- Admin actions require `manage_options` and WordPress nonces.
- Uploaded files are validated as PDFs.
- Public chat requests require a REST nonce.
- Public chat requests are rate-limited per visitor IP hash.
- API key is stored in WordPress options and is never exposed to the browser.
- Public responses expose only answer text and source snippets, not full stored document data.

## Uninstall

Deleting the plugin through WordPress removes plugin options, custom tables, and uploaded plugin PDFs.
