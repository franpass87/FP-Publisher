<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="tts-modal-content">
	<h2><?php esc_html_e( 'Import Data', 'fp-publisher' ); ?></h2>
	<form id="tts-import-form" class="tts-ajax-form" data-ajax-action="tts_import_data" enctype="multipart/form-data">
		<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">
		<div class="tts-import-file">
			<label for="import_file">
				<?php esc_html_e( 'Select Export File:', 'fp-publisher' ); ?>
			</label>
			<input type="file" id="import_file" name="import_file" accept=".json" required>
		</div>

		<div class="tts-import-options">
			<h4><?php esc_html_e( 'Import Options:', 'fp-publisher' ); ?></h4>
			<label>
				<input type="checkbox" name="overwrite_settings">
				<?php esc_html_e( 'Overwrite existing settings', 'fp-publisher' ); ?>
			</label>
			<label>
				<input type="checkbox" name="overwrite_social_apps">
				<?php esc_html_e( 'Overwrite social media configurations', 'fp-publisher' ); ?>
			</label>
			<label>
				<input type="checkbox" name="import_clients" checked>
				<?php esc_html_e( 'Import clients', 'fp-publisher' ); ?>
			</label>
			<label>
				<input type="checkbox" name="import_posts">
				<?php esc_html_e( 'Import social posts (as drafts)', 'fp-publisher' ); ?>
			</label>
		</div>

		<div class="tts-modal-actions">
			<button type="submit" class="tts-btn primary">
				<?php esc_html_e( 'Import', 'fp-publisher' ); ?>
			</button>
			<button type="button" class="tts-btn secondary tts-modal-close">
				<?php esc_html_e( 'Cancel', 'fp-publisher' ); ?>
			</button>
		</div>
	</form>
</div>
