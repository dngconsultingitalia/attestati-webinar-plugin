<?php
/**
 * Plugin Name: Attestati Webinar
 * Plugin URI: https://caterinaapruzzese.it
 * Description: Genera e invia attestati di partecipazione personalizzati per i webinar WooCommerce
 * Version: 1.0.0
 * Author: Caterina Apruzzese
 * Text Domain: attestati-webinar
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ATT_WEBINAR_VERSION', '1.0.0');
define('ATT_WEBINAR_DB_VERSION', '1.1.0');
define('ATT_WEBINAR_PATH', plugin_dir_path(__FILE__));
define('ATT_WEBINAR_URL', plugin_dir_url(__FILE__));
define('ATT_WEBINAR_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
class Attestati_Webinar {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        require_once ATT_WEBINAR_PATH . 'includes/class-post-type.php';
        require_once ATT_WEBINAR_PATH . 'includes/class-meta-boxes.php';
        require_once ATT_WEBINAR_PATH . 'includes/class-pdf-generator.php';
        require_once ATT_WEBINAR_PATH . 'includes/class-email-sender.php';
        require_once ATT_WEBINAR_PATH . 'includes/class-cron-handler.php';
        require_once ATT_WEBINAR_PATH . 'includes/class-my-account.php';
        require_once ATT_WEBINAR_PATH . 'admin/class-admin.php';
    }
    
    private function init_hooks() {
        // Activation/Deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Custom cron interval (ogni 5 minuti)
        add_filter('cron_schedules', array($this, 'add_cron_interval'));

        // Init
        add_action('init', array($this, 'init'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
    }

    public function add_cron_interval($schedules) {
        $schedules['att_every_five_minutes'] = array(
            'interval' => 300,
            'display'  => __('Ogni 5 minuti', 'attestati-webinar'),
        );
        return $schedules;
    }
    
    public function init() {
        // Load text domain
        load_plugin_textdomain('attestati-webinar', false, dirname(ATT_WEBINAR_BASENAME) . '/languages');

        // Verifica se serve aggiornamento DB (aggiornamento plugin senza riattivazione)
        if (get_option('att_webinar_db_version') !== ATT_WEBINAR_DB_VERSION) {
            $this->activate();
        }

        // Initialize components
        new Att_Webinar_Post_Type();
        new Att_Webinar_Meta_Boxes();
        new Att_Webinar_Cron_Handler();
        new Att_Webinar_My_Account();
        
        if (is_admin()) {
            new Att_Webinar_Admin();
        }
    }
    
    public function activate() {
        // Create database table for generated certificates
        // Nota: i campi pdf_path/pdf_url contengono in realta file PNG (naming legacy mantenuto per compatibilita)
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'attestati_generati';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            attestato_id bigint(20) NOT NULL,
            order_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            nome_cognome varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            pdf_path varchar(500) DEFAULT NULL,
            pdf_url varchar(500) DEFAULT NULL,
            codice_univoco varchar(50) NOT NULL,
            data_generazione datetime NOT NULL,
            data_invio datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'generato',
            tentativi_invio int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY attestato_id (attestato_id),
            KEY order_id (order_id),
            KEY user_id (user_id),
            KEY codice_univoco (codice_univoco)
        ) $charset_collate;";

        // Migrazione: aggiungi colonna se non esiste (dbDelta non aggiunge sempre nuove colonne)
        $row = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'tentativi_invio'");
        if (empty($row)) {
            $wpdb->query("ALTER TABLE $table_name ADD tentativi_invio int(11) NOT NULL DEFAULT 0");
        }
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Salva versione DB per future migrazioni
        update_option('att_webinar_db_version', ATT_WEBINAR_DB_VERSION);

        // Schedule cron ogni 5 minuti (rischedula se era hourly)
        $next = wp_next_scheduled('att_webinar_check_send_certificates');
        if ($next) {
            wp_clear_scheduled_hook('att_webinar_check_send_certificates');
        }
        wp_schedule_event(time(), 'att_every_five_minutes', 'att_webinar_check_send_certificates');
        
        // Create upload directory with protection
        $upload_dir = wp_upload_dir();
        $att_dir = $upload_dir['basedir'] . '/attestati-webinar';
        if (!file_exists($att_dir)) {
            wp_mkdir_p($att_dir);
        }

        // Protect directory from direct access
        $htaccess = $att_dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Options -Indexes\nDeny from all\n");
        }

        $index = $att_dir . '/index.php';
        if (!file_exists($index)) {
            file_put_contents($index, "<?php\n// Silence is golden.\n");
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('att_webinar_check_send_certificates');
        flush_rewrite_rules();
    }
    
    public function admin_scripts($hook) {
        global $post_type;
        
        if ($post_type === 'attestato_webinar' || $hook === 'attestato_webinar_page_attestati-archivio') {
            wp_enqueue_media();
            
            wp_enqueue_style(
                'att-webinar-admin',
                ATT_WEBINAR_URL . 'assets/css/admin.css',
                array(),
                ATT_WEBINAR_VERSION
            );
            
            wp_enqueue_script(
                'att-webinar-admin',
                ATT_WEBINAR_URL . 'assets/js/admin.js',
                array('jquery', 'jquery-ui-draggable', 'jquery-ui-droppable'),
                ATT_WEBINAR_VERSION,
                true
            );
            
            wp_localize_script('att-webinar-admin', 'attWebinar', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('att_webinar_nonce'),
            ));
        }
    }
    
    public function frontend_scripts() {
        if (is_account_page()) {
            wp_enqueue_style(
                'att-webinar-frontend',
                ATT_WEBINAR_URL . 'assets/css/frontend.css',
                array(),
                ATT_WEBINAR_VERSION
            );
        }
    }
}

// Check dependencies before initializing
function att_webinar() {
    // Check WooCommerce
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('Attestati Webinar richiede WooCommerce attivo per funzionare.', 'attestati-webinar');
            echo '</p></div>';
        });
        return null;
    }

    // Check GD extension
    if (!extension_loaded('gd')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('Attestati Webinar richiede l\'estensione PHP GD per generare i certificati.', 'attestati-webinar');
            echo '</p></div>';
        });
        return null;
    }

    return Attestati_Webinar::get_instance();
}
add_action('plugins_loaded', 'att_webinar');
