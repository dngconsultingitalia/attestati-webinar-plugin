<?php
/**
 * Admin - Archivio Attestati e integrazione ordini
 */

if (!defined('ABSPATH')) {
    exit;
}

class Att_Webinar_Admin {
    
    public function __construct() {
        // Sottomenu archivio
        add_action('admin_menu', array($this, 'add_submenu'));
        
        // Meta box nell'ordine WooCommerce
        add_action('add_meta_boxes', array($this, 'add_order_meta_box'));
        
        // AJAX per azioni singole
        add_action('wp_ajax_att_webinar_generate_single', array($this, 'ajax_generate_single'));
        add_action('wp_ajax_att_webinar_send_single', array($this, 'ajax_send_single'));
    }
    
    public function add_submenu() {
        add_submenu_page(
            'edit.php?post_type=attestato_webinar',
            __('Archivio Attestati', 'attestati-webinar'),
            __('📋 Archivio', 'attestati-webinar'),
            'manage_options',
            'attestati-archivio',
            array($this, 'render_archive_page')
        );
    }
    
    /**
     * Pagina Archivio Attestati
     */
    public function render_archive_page() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'attestati_generati';
        
        // Filtri
        $attestato_filter = isset($_GET['attestato_id']) ? intval($_GET['attestato_id']) : 0;
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

        // Paginazione
        $per_page = 30;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        // Query
        $where = "WHERE 1=1";
        if ($attestato_filter) {
            $where .= $wpdb->prepare(" AND ag.attestato_id = %d", $attestato_filter);
        }
        if ($status_filter) {
            $where .= $wpdb->prepare(" AND ag.status = %s", $status_filter);
        }

        $total_items = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name ag $where"
        );
        $total_pages = ceil($total_items / $per_page);

        $attestati = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ag.*, p.post_title as attestato_nome
                 FROM $table_name ag
                 LEFT JOIN {$wpdb->posts} p ON ag.attestato_id = p.ID
                 $where
                 ORDER BY ag.data_generazione DESC
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );
        
        // Lista attestati template per filtro
        $templates = get_posts(array(
            'post_type' => 'attestato_webinar',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Archivio Attestati Generati', 'attestati-webinar'); ?></h1>
            
            <!-- Filtri -->
            <div class="tablenav top">
                <form method="get" class="alignleft actions">
                    <input type="hidden" name="post_type" value="attestato_webinar">
                    <input type="hidden" name="page" value="attestati-archivio">
                    
                    <select name="attestato_id">
                        <option value=""><?php _e('Tutti gli attestati', 'attestati-webinar'); ?></option>
                        <?php foreach ($templates as $t): ?>
                            <option value="<?php echo esc_attr($t->ID); ?>" <?php selected($attestato_filter, $t->ID); ?>>
                                <?php echo esc_html($t->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="status">
                        <option value=""><?php _e('Tutti gli stati', 'attestati-webinar'); ?></option>
                        <option value="generato" <?php selected($status_filter, 'generato'); ?>><?php _e('Generato', 'attestati-webinar'); ?></option>
                        <option value="inviato" <?php selected($status_filter, 'inviato'); ?>><?php _e('Inviato', 'attestati-webinar'); ?></option>
                    </select>
                    
                    <input type="submit" class="button" value="<?php _e('Filtra', 'attestati-webinar'); ?>">
                </form>
            </div>
            
            <!-- Tabella -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Attestato', 'attestati-webinar'); ?></th>
                        <th><?php _e('Nome Cognome', 'attestati-webinar'); ?></th>
                        <th><?php _e('Email', 'attestati-webinar'); ?></th>
                        <th><?php _e('Ordine', 'attestati-webinar'); ?></th>
                        <th><?php _e('Codice', 'attestati-webinar'); ?></th>
                        <th><?php _e('Generato', 'attestati-webinar'); ?></th>
                        <th><?php _e('Stato', 'attestati-webinar'); ?></th>
                        <th><?php _e('Azioni', 'attestati-webinar'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attestati)): ?>
                        <tr>
                            <td colspan="8"><?php _e('Nessun attestato trovato.', 'attestati-webinar'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($attestati as $att): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo get_edit_post_link($att->attestato_id); ?>">
                                        <?php echo esc_html($att->attestato_nome); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($att->nome_cognome); ?></td>
                                <td><?php echo esc_html($att->email); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('post.php?post=' . $att->order_id . '&action=edit'); ?>">
                                        #<?php echo esc_html($att->order_id); ?>
                                    </a>
                                </td>
                                <td><code><?php echo esc_html($att->codice_univoco); ?></code></td>
                                <td><?php echo date_i18n('d/m/Y H:i', strtotime($att->data_generazione)); ?></td>
                                <td>
                                    <?php if ($att->status === 'inviato'): ?>
                                        <span style="color: green;">✅ <?php _e('Inviato', 'attestati-webinar'); ?></span>
                                        <br><small><?php echo date_i18n('d/m/Y H:i', strtotime($att->data_invio)); ?></small>
                                    <?php else: ?>
                                        <span style="color: orange;">⏳ <?php _e('Generato', 'attestati-webinar'); ?></span>
                                        <?php if (!empty($att->tentativi_invio) && $att->tentativi_invio > 0): ?>
                                            <br><small style="color: red;">
                                                <?php printf(__('%d tentativi falliti', 'attestati-webinar'), $att->tentativi_invio); ?>
                                                <?php if ($att->tentativi_invio >= 5): ?>
                                                    — <?php _e('sospeso', 'attestati-webinar'); ?>
                                                <?php endif; ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($att->pdf_url): ?>
                                        <a href="<?php echo esc_url($att->pdf_url); ?>" target="_blank" class="button button-small">
                                            👁️
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($att->status !== 'inviato'): ?>
                                        <button type="button" class="button button-small att-send-single" data-id="<?php echo esc_attr($att->id); ?>">
                                            📧
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php printf(_n('%s elemento', '%s elementi', $total_items, 'attestati-webinar'), number_format_i18n($total_items)); ?>
                    </span>
                    <?php
                    $page_links = paginate_links(array(
                        'base'      => add_query_arg('paged', '%#%'),
                        'format'    => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total'     => $total_pages,
                        'current'   => $current_page,
                    ));
                    if ($page_links) {
                        echo '<span class="pagination-links">' . $page_links . '</span>';
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(function($) {
            $('.att-send-single').on('click', function() {
                var $btn = $(this);
                var id = $btn.data('id');
                
                if (!confirm('<?php _e('Inviare questo attestato?', 'attestati-webinar'); ?>')) {
                    return;
                }
                
                $btn.prop('disabled', true).text('...');
                
                $.post(ajaxurl, {
                    action: 'att_webinar_send_single',
                    nonce: '<?php echo wp_create_nonce('att_webinar_nonce'); ?>',
                    certificate_id: id
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data);
                        $btn.prop('disabled', false).text('📧');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Meta box nell'ordine WooCommerce
     */
    public function add_order_meta_box() {
        $screen = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';
        
        add_meta_box(
            'att_webinar_order_box',
            __('🎓 Attestati', 'attestati-webinar'),
            array($this, 'render_order_meta_box'),
            $screen,
            'side',
            'default'
        );
    }
    
    public function render_order_meta_box($post_or_order) {
        global $wpdb;
        
        // Compatibilità HPOS
        $order_id = $post_or_order instanceof WC_Order ? $post_or_order->get_id() : $post_or_order->ID;
        
        $table_name = $wpdb->prefix . 'attestati_generati';
        
        $attestati = $wpdb->get_results($wpdb->prepare(
            "SELECT ag.*, p.post_title as attestato_nome 
             FROM $table_name ag
             LEFT JOIN {$wpdb->posts} p ON ag.attestato_id = p.ID
             WHERE ag.order_id = %d",
            $order_id
        ));
        
        if (empty($attestati)) {
            echo '<p>' . __('Nessun attestato generato per questo ordine.', 'attestati-webinar') . '</p>';
            return;
        }
        
        echo '<ul style="margin: 0;">';
        foreach ($attestati as $att) {
            echo '<li style="padding: 8px 0; border-bottom: 1px solid #eee;">';
            echo '<strong>' . esc_html($att->attestato_nome) . '</strong><br>';
            echo '<small>' . esc_html($att->nome_cognome) . '</small><br>';
            echo '<span style="color: ' . ($att->status === 'inviato' ? 'green' : 'orange') . ';">';
            echo $att->status === 'inviato' ? '✅ Inviato' : '⏳ Generato';
            echo '</span>';
            if ($att->pdf_url) {
                echo ' <a href="' . esc_url($att->pdf_url) . '" target="_blank">👁️</a>';
            }
            echo '</li>';
        }
        echo '</ul>';
    }
    
    /**
     * AJAX: Invia singolo attestato
     */
    public function ajax_send_single() {
        check_ajax_referer('att_webinar_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permessi insufficienti', 'attestati-webinar'));
        }

        global $wpdb;
        
        $cert_id = intval($_POST['certificate_id']);
        $table_name = $wpdb->prefix . 'attestati_generati';
        
        $certificate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $cert_id
        ));
        
        if (!$certificate) {
            wp_send_json_error(__('Attestato non trovato', 'attestati-webinar'));
        }
        
        $sender = new Att_Webinar_Email_Sender();
        $result = $sender->send_certificate($certificate, $certificate->attestato_id);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Errore nell\'invio', 'attestati-webinar'));
        }
    }
}
