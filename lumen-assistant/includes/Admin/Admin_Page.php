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
			__( 'Lumen Assistant', 'lumen-assistant' ),
			__( 'Lumen Assistant', 'lumen-assistant' ),
			'manage_options',
			'lumen-assistant',
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
		if ( 'toplevel_page_lumen-assistant' !== $hook ) {
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
			wp_die( esc_html__( 'You do not have permission to manage this plugin.', 'lumen-assistant' ) );
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
			wp_die( esc_html__( 'You do not have permission to upload documents.', 'lumen-assistant' ) );
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
			wp_die( esc_html__( 'You do not have permission to delete documents.', 'lumen-assistant' ) );
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
			wp_die( esc_html__( 'You do not have permission to rebuild documents.', 'lumen-assistant' ) );
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
					<h1><?php esc_html_e( 'Lumen Assistant', 'lumen-assistant' ); ?></h1>
					<p><?php esc_html_e( 'A polished document support assistant for WordPress. Upload PDFs, index them locally, and let visitors ask Lumen grounded questions from a floating support widget.', 'lumen-assistant' ); ?></p>
				</div>
				<a class="ask-me-ai-admin__donate" href="https://buymemomo.com/vivek" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Donate', 'lumen-assistant' ); ?></a>
			</div>
			<?php $this->notice(); ?>

			<div style="display:grid;grid-template-columns:minmax(0,1.15fr) minmax(320px,.85fr);gap:24px;align-items:start;">
				<div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="ask_me_ai_save_settings">
						<?php wp_nonce_field( 'ask_me_ai_save_settings' ); ?>

						<div class="postbox">
							<h2 class="hndle" style="padding:12px 16px;margin:0;"><?php esc_html_e( 'AI Provider', 'lumen-assistant' ); ?></h2>
							<div class="inside">
								<table class="form-table" role="presentation">
									<tr>
										<th scope="row"><label for="openrouter_api_key"><?php esc_html_e( 'OpenRouter API key', 'lumen-assistant' ); ?></label></th>
										<td>
											<input class="regular-text" type="password" id="openrouter_api_key" name="ask_me_ai[openrouter_api_key]" value="" placeholder="<?php echo esc_attr( empty( $settings['openrouter_api_key'] ) ? __( 'Enter API key', 'lumen-assistant' ) : __( 'Saved. Enter a new key to replace it.', 'lumen-assistant' ) ); ?>" autocomplete="off">
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="chat_model"><?php esc_html_e( 'Chat model', 'lumen-assistant' ); ?></label></th>
										<td><input class="regular-text" type="text" id="chat_model" name="ask_me_ai[chat_model]" value="<?php echo esc_attr( $settings['chat_model'] ); ?>"></td>
									</tr>
									<tr>
										<th scope="row"><label for="embedding_model"><?php esc_html_e( 'Embedding model', 'lumen-assistant' ); ?></label></th>
										<td><input class="regular-text" type="text" id="embedding_model" name="ask_me_ai[embedding_model]" value="<?php echo esc_attr( $settings['embedding_model'] ); ?>"></td>
									</tr>
									<tr>
										<th scope="row"><label for="embedding_endpoint"><?php esc_html_e( 'Embedding endpoint', 'lumen-assistant' ); ?></label></th>
										<td><input class="large-text" type="url" id="embedding_endpoint" name="ask_me_ai[embedding_endpoint]" value="<?php echo esc_attr( $settings['embedding_endpoint'] ); ?>"></td>
									</tr>
									<tr>
										<th scope="row"><label for="assistant_instructions"><?php esc_html_e( 'Assistant instructions', 'lumen-assistant' ); ?></label></th>
										<td><textarea class="large-text" rows="4" id="assistant_instructions" name="ask_me_ai[assistant_instructions]"><?php echo esc_textarea( $settings['assistant_instructions'] ); ?></textarea></td>
									</tr>
								</table>
							</div>
						</div>

						<div class="postbox">
							<h2 class="hndle" style="padding:12px 16px;margin:0;"><?php esc_html_e( 'Widget Appearance', 'lumen-assistant' ); ?></h2>
							<div class="inside">
								<table class="form-table" role="presentation">
									<tr>
										<th scope="row"><?php esc_html_e( 'Display', 'lumen-assistant' ); ?></th>
										<td>
											<label><input type="checkbox" name="ask_me_ai[enabled]" value="1" <?php checked( '1', $settings['enabled'] ); ?>> <?php esc_html_e( 'Enable widget globally', 'lumen-assistant' ); ?></label><br>
											<label><input type="checkbox" name="ask_me_ai[shortcode_only]" value="1" <?php checked( '1', $settings['shortcode_only'] ); ?>> <?php esc_html_e( 'Only show where shortcode is used', 'lumen-assistant' ); ?></label>
											<p class="description"><?php esc_html_e( 'Shortcode: [ask_me_ai_widget]', 'lumen-assistant' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="assistant_name"><?php esc_html_e( 'Assistant name', 'lumen-assistant' ); ?></label></th>
										<td><input class="regular-text" type="text" id="assistant_name" name="ask_me_ai[assistant_name]" value="<?php echo esc_attr( $settings['assistant_name'] ); ?>"></td>
									</tr>
									<tr>
										<th scope="row"><label for="welcome_message"><?php esc_html_e( 'Welcome message', 'lumen-assistant' ); ?></label></th>
										<td><textarea class="large-text" rows="3" id="welcome_message" name="ask_me_ai[welcome_message]"><?php echo esc_textarea( $settings['welcome_message'] ); ?></textarea></td>
									</tr>
									<tr>
										<th scope="row"><label for="widget_color"><?php esc_html_e( 'Widget color', 'lumen-assistant' ); ?></label></th>
										<td><input type="color" id="widget_color" name="ask_me_ai[widget_color]" value="<?php echo esc_attr( $settings['widget_color'] ); ?>"></td>
									</tr>
									<tr>
										<th scope="row"><label for="position"><?php esc_html_e( 'Position', 'lumen-assistant' ); ?></label></th>
										<td>
											<select id="position" name="ask_me_ai[position]">
												<option value="bottom-right" <?php selected( 'bottom-right', $settings['position'] ); ?>><?php esc_html_e( 'Bottom right', 'lumen-assistant' ); ?></option>
												<option value="bottom-left" <?php selected( 'bottom-left', $settings['position'] ); ?>><?php esc_html_e( 'Bottom left', 'lumen-assistant' ); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="placeholder"><?php esc_html_e( 'Placeholder', 'lumen-assistant' ); ?></label></th>
										<td><input class="regular-text" type="text" id="placeholder" name="ask_me_ai[placeholder]" value="<?php echo esc_attr( $settings['placeholder'] ); ?>"></td>
									</tr>
									<tr>
										<th scope="row"><label for="suggested_questions"><?php esc_html_e( 'Suggested questions', 'lumen-assistant' ); ?></label></th>
										<td><textarea class="large-text" rows="5" id="suggested_questions" name="ask_me_ai[suggested_questions]"><?php echo esc_textarea( $settings['suggested_questions'] ); ?></textarea><p class="description"><?php esc_html_e( 'One question per line.', 'lumen-assistant' ); ?></p></td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Branding', 'lumen-assistant' ); ?></th>
										<td><label><input type="checkbox" name="ask_me_ai[powered_by]" value="1" <?php checked( '1', $settings['powered_by'] ); ?>> <?php esc_html_e( 'Show Powered by Lumen Assistant', 'lumen-assistant' ); ?></label></td>
									</tr>
								</table>
							</div>
						</div>

						<div class="postbox">
							<h2 class="hndle" style="padding:12px 16px;margin:0;"><?php esc_html_e( 'Limits', 'lumen-assistant' ); ?></h2>
							<div class="inside">
								<table class="form-table" role="presentation">
									<tr>
										<th scope="row"><label for="max_context_chunks"><?php esc_html_e( 'Context chunks', 'lumen-assistant' ); ?></label></th>
										<td><input type="number" min="1" max="12" id="max_context_chunks" name="ask_me_ai[max_context_chunks]" value="<?php echo esc_attr( $settings['max_context_chunks'] ); ?>"></td>
									</tr>
									<tr>
										<th scope="row"><label for="rate_limit_count"><?php esc_html_e( 'Rate limit', 'lumen-assistant' ); ?></label></th>
										<td>
											<input type="number" min="1" max="200" id="rate_limit_count" name="ask_me_ai[rate_limit_count]" value="<?php echo esc_attr( $settings['rate_limit_count'] ); ?>">
											<?php esc_html_e( 'questions per', 'lumen-assistant' ); ?>
											<input type="number" min="60" max="3600" name="ask_me_ai[rate_limit_window]" value="<?php echo esc_attr( $settings['rate_limit_window'] ); ?>">
											<?php esc_html_e( 'seconds per visitor IP.', 'lumen-assistant' ); ?>
										</td>
									</tr>
								</table>
							</div>
						</div>

						<?php submit_button( __( 'Save Settings', 'lumen-assistant' ) ); ?>
					</form>
				</div>

				<div>
					<div class="postbox">
						<h2 class="hndle" style="padding:12px 16px;margin:0;"><?php esc_html_e( 'Upload PDF', 'lumen-assistant' ); ?></h2>
						<div class="inside">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
								<input type="hidden" name="action" value="ask_me_ai_upload_pdf">
								<?php wp_nonce_field( 'ask_me_ai_upload_pdf' ); ?>
								<p><input type="file" name="ask_me_ai_pdf" accept="application/pdf" required></p>
								<?php submit_button( __( 'Upload and Index', 'lumen-assistant' ), 'primary', 'submit', false ); ?>
								<p class="description"><?php esc_html_e( 'Indexing calls the embedding endpoint once per text chunk, so large files can take time.', 'lumen-assistant' ); ?></p>
							</form>
						</div>
					</div>

					<div class="postbox">
						<h2 class="hndle" style="padding:12px 16px;margin:0;"><?php esc_html_e( 'Documents', 'lumen-assistant' ); ?><span class="ask-me-ai-admin__badge"><?php esc_html_e( 'RAG Library', 'lumen-assistant' ); ?></span></h2>
						<div class="inside">
							<?php if ( empty( $documents ) ) : ?>
								<p><?php esc_html_e( 'No PDFs uploaded yet.', 'lumen-assistant' ); ?></p>
							<?php else : ?>
								<table class="widefat striped">
									<thead>
										<tr>
											<th><?php esc_html_e( 'File', 'lumen-assistant' ); ?></th>
											<th><?php esc_html_e( 'Status', 'lumen-assistant' ); ?></th>
											<th><?php esc_html_e( 'Chunks', 'lumen-assistant' ); ?></th>
											<th><?php esc_html_e( 'Actions', 'lumen-assistant' ); ?></th>
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
													<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ask_me_ai_rebuild_document&document_id=' . absint( $document['id'] ) ), 'ask_me_ai_rebuild_document_' . absint( $document['id'] ) ) ); ?>"><?php esc_html_e( 'Rebuild', 'lumen-assistant' ); ?></a>
													|
													<a style="color:#b32d2e;" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ask_me_ai_delete_document&document_id=' . absint( $document['id'] ) ), 'ask_me_ai_delete_document_' . absint( $document['id'] ) ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this document and all of its chunks?', 'lumen-assistant' ) ); ?>');"><?php esc_html_e( 'Delete', 'lumen-assistant' ); ?></a>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							<?php endif; ?>
						</div>
					</div>

					<div class="postbox">
						<h2 class="hndle" style="padding:12px 16px;margin:0;"><?php esc_html_e( 'Setup Notes', 'lumen-assistant' ); ?></h2>
						<div class="inside">
							<ol>
								<li><?php esc_html_e( 'Enter an OpenRouter API key and model names.', 'lumen-assistant' ); ?></li>
								<li><?php esc_html_e( 'Upload one or more PDFs and wait for Ready status.', 'lumen-assistant' ); ?></li>
								<li><?php esc_html_e( 'Enable the widget globally or place [ask_me_ai_widget] on selected pages.', 'lumen-assistant' ); ?></li>
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

		return $wpdb->get_results( 'SELECT * FROM ' . Database::table( 'documents' ) . ' ORDER BY created_at DESC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Render admin notice.
	 */
	private function notice() {
		$status  = sanitize_key( $_GET['ask_me_ai_status'] ?? '' );
		$message = sanitize_text_field( wp_unslash( $_GET['ask_me_ai_message'] ?? '' ) );

		if ( empty( $status ) ) {
			return;
		}

		$map = array(
			'settings_saved'   => __( 'Settings saved.', 'lumen-assistant' ),
			'document_indexed' => __( 'PDF uploaded and indexed.', 'lumen-assistant' ),
			'document_deleted' => __( 'Document deleted.', 'lumen-assistant' ),
			'document_rebuilt' => __( 'Document index rebuilt.', 'lumen-assistant' ),
		);

		$is_error = false !== strpos( $status, 'failed' );
		$text     = $message ?: ( $map[ $status ] ?? __( 'Action complete.', 'lumen-assistant' ) );
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
			'page'             => 'lumen-assistant',
			'ask_me_ai_status' => $status,
		);

		if ( '' !== $message ) {
			$args['ask_me_ai_message'] = $message;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
