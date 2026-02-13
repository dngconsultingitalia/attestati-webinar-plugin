<?php
/**
 * Uninstall - Attestati Webinar
 *
 * Eseguito alla disinstallazione del plugin.
 * Rimuove tabella custom, post meta, cron e file generati.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Rimuovi tabella custom
$table_name = $wpdb->prefix . 'attestati_generati';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// Rimuovi tutti i post del CPT e relativi meta
$attestati = get_posts(array(
    'post_type'      => 'attestato_webinar',
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'fields'         => 'ids',
));

foreach ($attestati as $id) {
    wp_delete_post($id, true);
}

// Rimuovi cron
wp_clear_scheduled_hook('att_webinar_check_send_certificates');

// Rimuovi opzione versione DB
delete_option('att_webinar_db_version');

// Rimuovi directory upload attestati
$upload_dir = wp_upload_dir();
$att_dir = $upload_dir['basedir'] . '/attestati-webinar';
if (is_dir($att_dir)) {
    $files = glob($att_dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    // Rimuovi .htaccess e index.php
    if (file_exists($att_dir . '/.htaccess')) {
        unlink($att_dir . '/.htaccess');
    }
    if (file_exists($att_dir . '/index.php')) {
        unlink($att_dir . '/index.php');
    }
    rmdir($att_dir);
}

// Flush rewrite rules
flush_rewrite_rules();
