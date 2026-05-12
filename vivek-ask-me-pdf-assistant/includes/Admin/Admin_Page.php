<?php
/**
 * Admin settings page.
 *
 * @package AskMeAI\Admin
 */

namespace AskMeAI\Admin;

use AskMeAI\Core\Database;
use AskMeAI\Core\Settings;
use AskMeAI\Services\Document_Indexer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The admin document list reads from custom plugin tables.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

class Admin_Page {
	/**
	 * Register admin hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_ask_me_ai_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_ask_me_ai_upload_pdf', array( $this, 'upload_pdf' ) );
		add_action( 'admin_post_ask_me_ai_delete_document', array( $this, 'delete_document' ) );
		add_action( 'admin_post_ask_me_ai_rebuild_document', array( $this, 'rebuild_document' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
	}

	/**
	 * Add menu item.
	 */
	public function menu() {
		add_menu_page(
			__( 'Vivek Ask Me PDF Assistant', 'vivek-ask-me-pdf-assistant' ),
			__( 'Vivek Ask Me PDF Assistant', 'vivek-ask-me-pdf-assistant' ),
			'manage_options',
			'vivek-ask-me-pdf-assistant',
			array( $this, 'render' ),
			'dashicons-format-chat',
			58
		);
	}

	/**
	 * Add small admin-only styling.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function admin_styles( $hook ) {
		if ( 'toplevel_page_vivek-ask-me-pdf-assistant' !== $hook ) {
			return;
		}

		wp_add_inline_style(
			'common',
			'.ask-me-ai-admin__hero{background:#0f172a;color:#fff;border-radius:8px;padding:22px 24px;margin:16px 0 22px;display:flex;align-items:center;justify-content:space-between;gap:20px}.ask-me-ai-admin__hero h1{color:#fff;margin:0 0 6px;font-size:26px}.ask-me-ai-admin__hero p{color:#cbd5e1;margin:0;max-width:760px}.ask-me-ai-admin__donate{background:#f59e0b;color:#111827!important;border-radius:6px;display:inline-block;font-weight:700;padding:10px 14px;text-decoration:none;white-space:nowrap}.ask-me-ai-admin__donate:hover{background:#fbbf24;color:#111827}.ask-me-ai-admin__badge{background:#ecfdf5;border:1px solid #a7f3d0;border-radius:999px;color:#047857;display:inline-block;font-size:12px;font-weight:700;margin-left:8px;padding:3px 8px}'
		);
	}

	/**
	 * Save settings action.
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage this plugin.', 'vivek-ask-me-pdf-assistant' ) );
		}

		check_admin_referer( 'ask_me_ai_save_settings' );
		Settings::update( wp_unslash( $_POST['ask_me_ai'] ?? array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$this->redirect( 'settings_saved' );
	}

	/**
	 * Upload PDF action.
	 */
	public function upload_pdf() {
		if ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to upload documents.', 'vivek-ask-me-pdf-assistant' ) );
		}

		check_admin_referer( 'ask_me_ai_upload_pdf' );
		$result = ( new Document_Indexer() )->upload_and_index( $_FILES['ask_me_ai_pdf'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$this->redirect( is_wp_error( $result ) ? 'upload_failed' : 'document_indexed', is_wp_error( $result ) ? $result->get_error_message() : '' );
	}

	/**
	 * Delete document action.
	 */
	public function delete_document() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete documents.', 'vivek-ask-me-pdf-assistant' ) );
		}

		$document_id = absint( $_GET['document_id'] ?? 0 );
		check_admin_referer( 'ask_me_ai_delete_document_' . $document_id );

		$result = ( new Document_Indexer() )->delete_document( $document_id );
		$this->redirect( is_wp_error( $result ) ? 'delete_failed' : 'document_deleted', is_wp_error( $result ) ? $result->get_error_message() : '' );
	}

	/**
	 * Rebuild index action.
	 */
	public function rebuild_document() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to rebuild documents.', 'vivek-ask-me-pdf-assistant' ) );
		}

		$document_id = absint( $_GET['document_id'] ?? 0 );
		check_admin_referer( 'ask_me_ai_rebuild_document_' . $document_id );

		$result = ( new Document_Indexer() )->index_document( $document_id );
		$this->redirect( is_wp_error( $result ) ? 'rebuild_failed' : 'document_rebuilt', is_wp_error( $result ) ? $result->get_error_message() : '' );
	}

	/**
	 * Render page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings  = Settings::get();
		$documents = $this->documents();
		?>
		<div class="wrap ask-me-ai-admin">
			<div class="ask-me-ai-admin__hero">
				<div>
					<h1><?php esc_html_e( 'Vivek Ask Me PDF Assistant', 'vivek-ask-me-pdf-assistant' ); ?></h1>
					<p><?php esc_html_e( 'A polished document support assistant for WordPress. Upload PDFs, index them locally, and let visitors Ask Me AI grounded questions from a floating support widget.', 'vivek-ask-me-pdf-assistant' ); ?></p>
				</div>
			</div>
			<?php $this->notice(); ?>

			<div style="display:grid;grid-template-columns:minmax(0,1.15fr) minmax(320px,.85fr);gap:24px;align-items:start;">
				<div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="ask_me_ai_save_settings">
						<?php wp_nonce_field( 'ask_me_ai_save_settings' ); ?>

						<div class="postbox">
							<h2 class="hndle" style="padding:12px 16px;margin:0;"><?php esc_html_e( 'AI Provider', 'vivek-ask-me-pdf-assistant' ); ?></h2>
							<div class="inside">
								<table class="form-table" role="presentation">
									<tr>
										<th scope="row"><label for="openrouter_api_key"><?php esc_html_e( 'OpenRouter API key', 'vivek-ask-me-pdf-assistant' ); ?></label></th>
										<td>
											<input class="regular-text" type="password" id="openrouter_api_key" name="ask_me_ai[openrouter_api_key]" value="" placeholder="<?php echo esc_attr( empty( $settings['openrouter_api_key'] ) ? __( 'Enter API key', 'vivek-ask-me-pdf-assistant' ) : __( 'Saved. Enter a new key to replace it.', 'vivek-ask-me-pdf-assistant' ) ); ?>" autocomplete="off">
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="chat_model"><?php esc_html_e( 'Chat model', 'vivek-ask-me-pdf-assistant' ); ?></label></th>
										<td><input class="regular-text" type="text" id="chat_model" name="ask_me_ai[chat_model]" value="<?php echo esc_attr( $settings['chat_model'] ); ?>"></td>
									</tr>
									<tr>
										<th scope="row"><label for="embedding_model"><?php esc_html_e( 'Embedding model', 'vivek-ask-me-pdf-assistant' ); ?></label></th>
										<td><input class="regular-text" type="text" id="embedding_model" name="ask_me_ai[embedding_model]" value="<?php echo esc_attr( $settings['embedding_model'] ); ?>"></td>
									</tr>
									<tr>
										<th scope="row"><label for="embedding_endpoint"><?php esc_html_e( 'Embedding endpoint', 'vivek-ask-me-pdf-assistant' ); ?></label></th>
										<td><input class="large-text" type="url" id="embedding_endpoint" name="ask_me_ai[embedding_endpoint]" value="<?php echo esc_attr( $settings['embedding_endpoint'] ); ?>"></td>
									</tr>
									<tr>
										<th scope="row"><label for="assistant_instructions"><?php esc_html_e( 'Assistant instructions', 'vivek-ask-me-pdf-assistant' ); ?></label></th>
										<td><textarea class="large-text" rows="4" id="assistant_instructions" name="ask_me_ai[assistant_instructions]"><?php echo esc_textarea( $settings['assistant_instructions'] ); ?></textarea></td>
									</tr>
								</table>
							</div>
						</div>

						<div class="postbox">
							<h2 class="hndle" style="padding:12px 16px;margin:0;"><?php esc_html_e( 'Widget Appearance', 'vivek-ask-me-pdf-assistant' ); ?></h2>
							<div class="inside">
								<table class="form-table" role="presentation">
									<tr>
										<th scope="row"><?php esc_html_e( 'Display', 'vivek-ask-me-pdf-assistant' ); ?></th>
										<td>
											<label><input type="checkbox" name="ask_me_ai[enabled]" value="1" <?php checked( '1', $settings['enabled'] ); ?>> <?php esc_html_e( 'Enable widget globally', 'vivek-ask-me-pdf-assistant' ); ?></label><br>
											<label><input type="checkbox" name="ask_me_ai[shortcode_only]" value="1" <?php checked( '1', $settings['shortcode_only'] ); ?>> <?php esc_html_e( 'Only show where shortcode is used', 'vivek-ask-me-pdf-assistant' ); ?></label>
											<p class="description"><?php esc_html_e( 'Shortcode: [ask_me_ai_widget]', 'vivek-ask-me-pdf-assistant' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="assistant_name"><?php esc_html_e( 'Assistant name', 'vivek-ask-me-pdf-assistant' ); ?></label></th>
										<td><input class="regular-text" type="text" id="assistant_name" name="ask_me_ai[assistant_name]" value="<?php echo esc_attr( $settings['assistant_name'] ); ?>"></td>
									</tr>
									<tr>
										<th scope="row"><label for="welcome_message"><?php esc_html_e( 'Welcome message', 'vivek-ask-me-pdf-assistant' ); ?></label></th>
										<td><textarea class="large-text" rows="3" id="welcome_message" name="ask_me_ai[welcome_message]"><?php echo esc_textarea( $settings['welcome_message'] ); ?></textarea></td>
									</tr>
									<tr>
										<th scope="row"><label for="widget_color"><?php esc_html_e( 'Widget color', 'vivek-ask-me-pdf-assistant' ); ?></label></th>
										<td><input type="color" id="widget_color" name="ask_me_ai[widget_color]" value="<?php echo esc_attr( $settings['widget_color'] ); ?>"></td>
									</tr>
									<tr>
										<th scope="row"><label for="position"><?php esc_html_e( 'Position', 'vivek-ask-me-pdf-assistant' ); ?></label></th>
										<td>
											<select id="position" name="ask_me_ai[position]">
												<option value="bottom-right" <?php selected( 'bottom-right', $settings['position'] ); ?>><?php esc_html_e( 'Bottom right', 'vivek-ask-me-pdf-assistant' ); ?></option>
												<option value="bottom-left" <?php selected( 'bottom-left', $settings['position'] ); ?>><?php esc_html_e( 'Bottom left', 'vivek-ask-me-pdf-assistant' ); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="placeholder"><?php esc_html_e( 'Placeholder', 'vivek-ask-me-pdf-assistant' ); ?></label></th>
										<td><input class="regular-text" type="text" id="placeholder" name="ask_me_ai[placeholder]" value="<?php echo esc_attr( $settings['placeholder'] ); ?>"></td>
									</tr>
									<tr>
										<th scope="row"><label for="suggested_questions"><?php esc_html_e( 'Suggested questions', 'vivek-ask-me-pdf-assistant' ); ?></label></th>
										<td><textarea class="large-text" rows="5" id="suggested_questions" name="ask_me_ai[suggested_questions]"><?php echo esc_textarea( $settings['suggested_questions'] ); ?></textarea><p class="description"><?php esc_html_e( 'One question per line.', 'vivek-ask-me-pdf-assistant' ); ?></p></td>
									</tr>
								</table>
							</div>
						</div>

						<div class="postbox">
							<h2 class="hndle" style="padding:12px 16px;margin:0;"><?php esc_html_e( 'Limits', 'vivek-ask-me-pdf-assistant' ); ?></h2>
							<div class="inside">
								<table class="form-table" role="presentation">
									<tr>
										<th scope="row"><label for="max_context_chunks"><?php esc_html_e( 'Context chunks', 'vivek-ask-me-pdf-assistant' ); ?></label></th>
										<td><input type="number" min="1" max="12" id="max_context_chunks" name="ask_me_ai[max_context_chunks]" value="<?php echo esc_attr( $settings['max_context_chunks'] ); ?>"></td>
									</tr>
									<tr>
										<th scope="row"><label for="rate_limit_count"><?php esc_html_e( 'Rate limit', 'vivek-ask-me-pdf-assistant' ); ?></label></th>
										<td>
											<input type="number" min="1" max="200" id="rate_limit_count" name="ask_me_ai[rate_limit_count]" value="<?php echo esc_attr( $settings['rate_limit_count'] ); ?>">
											<?php esc_html_e( 'questions per', 'vivek-ask-me-pdf-assistant' ); ?>
											<input type="number" min="60" max="3600" name="ask_me_ai[rate_limit_window]" value="<?php echo esc_attr( $settings['rate_limit_window'] ); ?>">
											<?php esc_html_e( 'seconds per visitor IP.', 'vivek-ask-me-pdf-assistant' ); ?>
										</td>
									</tr>
								</table>
							</div>
						</div>

						<?php submit_button( __( 'Save Settings', 'vivek-ask-me-pdf-assistant' ) ); ?>
					</form>
				</div>

				<div>
					<div class="postbox">
						<h2 class="hndle" style="padding:12px 16px;margin:0;"><?php esc_html_e( 'Upload PDF', 'vivek-ask-me-pdf-assistant' ); ?></h2>
						<div class="inside">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
								<input type="hidden" name="action" value="ask_me_ai_upload_pdf">
								<?php wp_nonce_field( 'ask_me_ai_upload_pdf' ); ?>
								<p><input type="file" name="ask_me_ai_pdf" accept="application/pdf" required></p>
								<?php submit_button( __( 'Upload and Index', 'vivek-ask-me-pdf-assistant' ), 'primary', 'submit', false ); ?>
								<p class="description"><?php esc_html_e( 'Indexing calls the embedding endpoint once per text chunk, so large files can take time.', 'vivek-ask-me-pdf-assistant' ); ?></p>
							</form>
						</div>
					</div>

					<div class="postbox">
						<h2 class="hndle" style="padding:12px 16px;margin:0;"><?php esc_html_e( 'Documents', 'vivek-ask-me-pdf-assistant' ); ?><span class="ask-me-ai-admin__badge"><?php esc_html_e( 'RAG Library', 'vivek-ask-me-pdf-assistant' ); ?></span></h2>
						<div class="inside">
							<?php if ( empty( $documents ) ) : ?>
								<p><?php esc_html_e( 'No PDFs uploaded yet.', 'vivek-ask-me-pdf-assistant' ); ?></p>
							<?php else : ?>
								<table class="widefat striped">
									<thead>
										<tr>
											<th><?php esc_html_e( 'File', 'vivek-ask-me-pdf-assistant' ); ?></th>
											<th><?php esc_html_e( 'Status', 'vivek-ask-me-pdf-assistant' ); ?></th>
											<th><?php esc_html_e( 'Chunks', 'vivek-ask-me-pdf-assistant' ); ?></th>
											<th><?php esc_html_e( 'Actions', 'vivek-ask-me-pdf-assistant' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $documents as $document ) : ?>
											<tr>
												<td>
													<strong><?php echo esc_html( $document['filename'] ); ?></strong>
													<?php if ( ! empty( $document['error_message'] ) ) : ?>
														<br><span style="color:#b32d2e;"><?php echo esc_html( $document['error_message'] ); ?></span>
													<?php endif; ?>
												</td>
												<td><?php echo esc_html( ucfirst( $document['status'] ) ); ?></td>
												<td><?php echo esc_html( $document['chunk_count'] ); ?></td>
												<td>
													<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ask_me_ai_rebuild_document&document_id=' . absint( $document['id'] ) ), 'ask_me_ai_rebuild_document_' . absint( $document['id'] ) ) ); ?>"><?php esc_html_e( 'Rebuild', 'vivek-ask-me-pdf-assistant' ); ?></a>
													|
													<a style="color:#b32d2e;" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ask_me_ai_delete_document&document_id=' . absint( $document['id'] ) ), 'ask_me_ai_delete_document_' . absint( $document['id'] ) ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this document and all of its chunks?', 'vivek-ask-me-pdf-assistant' ) ); ?>');"><?php esc_html_e( 'Delete', 'vivek-ask-me-pdf-assistant' ); ?></a>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							<?php endif; ?>
						</div>
					</div>

					<div class="postbox">
						<h2 class="hndle" style="padding:12px 16px;margin:0;"><?php esc_html_e( 'Setup Notes', 'vivek-ask-me-pdf-assistant' ); ?></h2>
						<div class="inside">
							<ol>
								<li><?php esc_html_e( 'Enter an OpenRouter API key and model names.', 'vivek-ask-me-pdf-assistant' ); ?></li>
								<li><?php esc_html_e( 'Upload one or more PDFs and wait for Ready status.', 'vivek-ask-me-pdf-assistant' ); ?></li>
								<li><?php esc_html_e( 'Enable the widget globally or place [ask_me_ai_widget] on selected pages.', 'vivek-ask-me-pdf-assistant' ); ?></li>
							</ol>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Fetch document rows.
	 *
	 * @return array
	 */
	private function documents() {
		global $wpdb;

		$documents_table = Database::table( 'documents' );

		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i ORDER BY created_at DESC', $documents_table ),
			ARRAY_A
		);
	}

	/**
	 * Render admin notice.
	 */
	private function notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status  = sanitize_key( $_GET['ask_me_ai_status'] ?? '' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = sanitize_text_field( wp_unslash( $_GET['ask_me_ai_message'] ?? '' ) );

		if ( empty( $status ) ) {
			return;
		}

		$map = array(
			'settings_saved'   => __( 'Settings saved.', 'vivek-ask-me-pdf-assistant' ),
			'document_indexed' => __( 'PDF uploaded and indexed.', 'vivek-ask-me-pdf-assistant' ),
			'document_deleted' => __( 'Document deleted.', 'vivek-ask-me-pdf-assistant' ),
			'document_rebuilt' => __( 'Document index rebuilt.', 'vivek-ask-me-pdf-assistant' ),
		);

		$is_error = false !== strpos( $status, 'failed' );
		$text     = $message ?: ( $map[ $status ] ?? __( 'Action complete.', 'vivek-ask-me-pdf-assistant' ) );
		$class    = $is_error ? 'notice notice-error' : 'notice notice-success';

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $text ) );
	}

	/**
	 * Redirect back to settings page.
	 *
	 * @param string $status Status key.
	 * @param string $message Optional message.
	 */
	private function redirect( $status, $message = '' ) {
		$args = array(
			'page'             => 'vivek-ask-me-pdf-assistant',
			'ask_me_ai_status' => $status,
		);

		if ( '' !== $message ) {
			$args['ask_me_ai_message'] = $message;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
