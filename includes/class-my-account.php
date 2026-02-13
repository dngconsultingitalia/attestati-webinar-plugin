<?php
/**
 * My Account - Sezione Attestati
 */

if (!defined('ABSPATH')) {
    exit;
}

class Att_Webinar_My_Account {
    
    public function __construct() {
        // Aggiungi endpoint
        add_action('init', array($this, 'add_endpoint'));
        
        // Aggiungi voce menu
        add_filter('woocommerce_account_menu_items', array($this, 'add_menu_item'));
        
        // Contenuto pagina
        add_action('woocommerce_account_attestati_endpoint', array($this, 'render_content'));
        
        // Gestisci download
        add_action('init', array($this, 'handle_download'));
    }
    
    public function add_endpoint() {
        add_rewrite_endpoint('attestati', EP_ROOT | EP_PAGES);
    }
    
    public function add_menu_item($items) {
        // Inserisci dopo "downloads"
        $new_items = array();
        
        foreach ($items as $key => $value) {
            $new_items[$key] = $value;
            
            if ($key === 'downloads') {
                $new_items['attestati'] = __('I miei Attestati', 'attestati-webinar');
            }
        }
        
        // Se non c'è downloads, aggiungi prima di logout
        if (!isset($new_items['attestati'])) {
            $logout = isset($new_items['customer-logout']) ? $new_items['customer-logout'] : null;
            unset($new_items['customer-logout']);
            $new_items['attestati'] = __('I miei Attestati', 'attestati-webinar');
            if ($logout) {
                $new_items['customer-logout'] = $logout;
            }
        }
        
        return $new_items;
    }
    
    public function render_content() {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $table_name = $wpdb->prefix . 'attestati_generati';
        
        // Recupera attestati dell'utente
        $attestati = $wpdb->get_results($wpdb->prepare(
            "SELECT ag.*, p.post_title as attestato_nome 
             FROM $table_name ag
             LEFT JOIN {$wpdb->posts} p ON ag.attestato_id = p.ID
             WHERE ag.user_id = %d 
             ORDER BY ag.data_generazione DESC",
            $user_id
        ));
        
        ?>
        <style>
            .att-my-certificates {
                margin-top: 20px;
            }
            .att-certificate-card {
                background: #f9f9f9;
                border-radius: 10px;
                padding: 20px;
                margin-bottom: 15px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 15px;
            }
            .att-certificate-info h4 {
                margin: 0 0 8px 0;
                color: #333;
            }
            .att-certificate-info p {
                margin: 0;
                color: #666;
                font-size: 14px;
            }
            .att-certificate-info .att-code {
                font-family: monospace;
                background: #e9e9e9;
                padding: 2px 8px;
                border-radius: 4px;
                font-size: 12px;
            }
            .att-certificate-actions .button {
                background: #477C80;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                text-decoration: none;
                display: inline-block;
            }
            .att-certificate-actions .button:hover {
                background: #3a6568;
                color: white;
            }
            .att-certificate-status {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: bold;
            }
            .att-status-inviato {
                background: #d4edda;
                color: #155724;
            }
            .att-status-generato {
                background: #fff3cd;
                color: #856404;
            }
            .att-no-certificates {
                text-align: center;
                padding: 40px;
                background: #f9f9f9;
                border-radius: 10px;
                color: #666;
            }
            .att-no-certificates .dashicons {
                font-size: 48px;
                width: 48px;
                height: 48px;
                margin-bottom: 15px;
                color: #ccc;
            }
        </style>
        
        <div class="att-my-certificates">
            <h2><?php _e('I miei Attestati di Partecipazione', 'attestati-webinar'); ?></h2>
            
            <?php if (empty($attestati)): ?>
                <div class="att-no-certificates">
                    <span class="dashicons dashicons-awards"></span>
                    <p><?php _e('Non hai ancora attestati disponibili.', 'attestati-webinar'); ?></p>
                    <p><?php _e('Gli attestati verranno generati automaticamente dopo la partecipazione ai webinar.', 'attestati-webinar'); ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($attestati as $att): 
                    $webinar_title = get_post_meta($att->attestato_id, '_att_webinar_title', true);
                    $webinar_date = get_post_meta($att->attestato_id, '_att_webinar_date', true);
                ?>
                    <div class="att-certificate-card">
                        <div class="att-certificate-info">
                            <h4><?php echo esc_html($webinar_title ?: $att->attestato_nome); ?></h4>
                            <p>
                                📅 <?php echo date_i18n('j F Y', strtotime($webinar_date)); ?> &nbsp;|&nbsp;
                                <span class="att-code"><?php echo esc_html($att->codice_univoco); ?></span>
                            </p>
                            <p>
                                <span class="att-certificate-status att-status-<?php echo esc_attr($att->status); ?>">
                                    <?php echo $att->status === 'inviato' ? '✅ Inviato' : '⏳ In attesa'; ?>
                                </span>
                                <?php if ($att->data_invio): ?>
                                    &nbsp; Inviato il <?php echo date_i18n('j/m/Y H:i', strtotime($att->data_invio)); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="att-certificate-actions">
                            <?php if ($att->pdf_url): ?>
                                <a href="<?php echo esc_url(add_query_arg(array('download_attestato' => $att->id, 'nonce' => wp_create_nonce('download_att_' . $att->id)), wc_get_account_endpoint_url('attestati'))); ?>" class="button">
                                    📥 <?php _e('Scarica', 'attestati-webinar'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Gestisce il download dell'attestato
     */
    public function handle_download() {
        if (!isset($_GET['download_attestato']) || !isset($_GET['nonce'])) {
            return;
        }
        
        $att_id = intval($_GET['download_attestato']);
        
        if (!wp_verify_nonce($_GET['nonce'], 'download_att_' . $att_id)) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'attestati_generati';
        
        $attestato = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
            $att_id,
            get_current_user_id()
        ));
        
        if (!$attestato || !file_exists($attestato->pdf_path)) {
            return;
        }
        
        // Download file
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="' . basename($attestato->pdf_path) . '"');
        header('Content-Length: ' . filesize($attestato->pdf_path));
        readfile($attestato->pdf_path);
        exit;
    }
}
