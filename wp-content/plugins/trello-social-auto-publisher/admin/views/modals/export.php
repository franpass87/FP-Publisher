<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="tts-modal-content">
    <h2><?php esc_html_e( 'Export Data', 'fp-publisher' ); ?></h2>
    <form id="tts-export-form" class="tts-ajax-form" data-ajax-action="tts_export_data">
        <input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">
        <div class="tts-export-options">
            <p class="description">
                <?php esc_html_e( 'Sensitive credentials are excluded unless you explicitly include them below.', 'fp-publisher' ); ?>
            </p>
            <label>
                <input type="checkbox" name="export_settings" checked>
                <?php esc_html_e( 'Plugin Settings', 'fp-publisher' ); ?>
            </label>
            <label>
                <input type="checkbox" name="export_social_apps" checked>
                <?php esc_html_e( 'Social Media Configurations', 'fp-publisher' ); ?>
            </label>
            <label>
                <input type="checkbox" name="export_clients" checked>
                <?php esc_html_e( 'Clients', 'fp-publisher' ); ?>
            </label>
            <label>
                <input type="checkbox" name="export_posts">
                <?php esc_html_e( 'Social Posts (last 100)', 'fp-publisher' ); ?>
            </label>
            <label>
                <input type="checkbox" name="export_logs">
                <?php esc_html_e( 'Recent Logs (last 30 days)', 'fp-publisher' ); ?>
            </label>
            <label>
                <input type="checkbox" name="export_analytics">
                <?php esc_html_e( 'Analytics Data', 'fp-publisher' ); ?>
            </label>
            <label class="tts-export-include-secrets">
                <input type="checkbox" name="export_include_secrets">
                <?php esc_html_e( 'Include secrets (app/client secrets, tokens)', 'fp-publisher' ); ?>
                <span class="description"><?php esc_html_e( 'Only enable this on secure systems. Without this option the export will mark secrets as [REDACTED].', 'fp-publisher' ); ?></span>
            </label>
        </div>
        <div class="tts-modal-actions">
            <button type="submit" class="tts-btn primary">
                <?php esc_html_e( 'Export', 'fp-publisher' ); ?>
            </button>
            <button type="button" class="tts-btn secondary tts-modal-close">
                <?php esc_html_e( 'Cancel', 'fp-publisher' ); ?>
            </button>
        </div>
    </form>
</div>
