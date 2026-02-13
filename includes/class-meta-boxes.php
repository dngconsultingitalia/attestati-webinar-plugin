<?php
/**
 * Meta Boxes per Attestato Webinar
 */

if (!defined('ABSPATH')) {
    exit;
}

class Att_Webinar_Meta_Boxes {
    
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_attestato_webinar', array($this, 'save_meta_boxes'), 10, 2);
        add_action('wp_ajax_att_webinar_preview', array($this, 'ajax_preview'));
        add_action('wp_ajax_att_webinar_generate_manual', array($this, 'ajax_generate_manual'));
        add_action('wp_ajax_att_webinar_send_manual', array($this, 'ajax_send_manual'));
    }
    
    public function add_meta_boxes() {
        // Template Upload
        add_meta_box(
            'att_webinar_template',
            __('Template Attestato', 'attestati-webinar'),
            array($this, 'render_template_box'),
            'attestato_webinar',
            'normal',
            'high'
        );
        
        // Editor Posizione Campi
        add_meta_box(
            'att_webinar_editor',
            __('Posizione Campi', 'attestati-webinar'),
            array($this, 'render_editor_box'),
            'attestato_webinar',
            'normal',
            'high'
        );
        
        // Collegamento Prodotto WooCommerce
        add_meta_box(
            'att_webinar_product',
            __('Collegamento Webinar', 'attestati-webinar'),
            array($this, 'render_product_box'),
            'attestato_webinar',
            'side',
            'high'
        );
        
        // Impostazioni Invio
        add_meta_box(
            'att_webinar_send_settings',
            __('Impostazioni Invio', 'attestati-webinar'),
            array($this, 'render_send_settings_box'),
            'attestato_webinar',
            'side',
            'default'
        );
        
        // Azioni Manuali
        add_meta_box(
            'att_webinar_actions',
            __('Azioni', 'attestati-webinar'),
            array($this, 'render_actions_box'),
            'attestato_webinar',
            'side',
            'default'
        );
    }
    
    /**
     * Render Template Upload Box
     */
    public function render_template_box($post) {
        wp_nonce_field('att_webinar_meta', 'att_webinar_nonce');
        
        $template_id = get_post_meta($post->ID, '_att_template_id', true);
        $template_url = $template_id ? wp_get_attachment_url($template_id) : '';
        $logo_id = get_post_meta($post->ID, '_att_logo_id', true);
        $logo_url = $logo_id ? wp_get_attachment_url($logo_id) : '';
        $firma_id = get_post_meta($post->ID, '_att_firma_id', true);
        $firma_url = $firma_id ? wp_get_attachment_url($firma_id) : '';
        ?>
        <div class="att-template-upload">
            <div class="att-upload-row">
                <label><?php _e('Template Base (immagine)', 'attestati-webinar'); ?></label>
                <div class="att-upload-field">
                    <input type="hidden" name="att_template_id" id="att_template_id" value="<?php echo esc_attr($template_id); ?>">
                    <div class="att-preview-image" id="att_template_preview">
                        <?php if ($template_url): ?>
                            <img src="<?php echo esc_url($template_url); ?>" alt="Template">
                        <?php endif; ?>
                    </div>
                    <button type="button" class="button att-upload-btn" data-target="att_template_id" data-preview="att_template_preview">
                        <?php _e('Carica Template', 'attestati-webinar'); ?>
                    </button>
                    <button type="button" class="button att-remove-btn" data-target="att_template_id" data-preview="att_template_preview" <?php echo !$template_id ? 'style="display:none;"' : ''; ?>>
                        <?php _e('Rimuovi', 'attestati-webinar'); ?>
                    </button>
                </div>
            </div>
            
            <div class="att-upload-row">
                <label><?php _e('Logo', 'attestati-webinar'); ?></label>
                <div class="att-upload-field">
                    <input type="hidden" name="att_logo_id" id="att_logo_id" value="<?php echo esc_attr($logo_id); ?>">
                    <div class="att-preview-image small" id="att_logo_preview">
                        <?php if ($logo_url): ?>
                            <img src="<?php echo esc_url($logo_url); ?>" alt="Logo">
                        <?php endif; ?>
                    </div>
                    <button type="button" class="button att-upload-btn" data-target="att_logo_id" data-preview="att_logo_preview">
                        <?php _e('Carica Logo', 'attestati-webinar'); ?>
                    </button>
                    <button type="button" class="button att-remove-btn" data-target="att_logo_id" data-preview="att_logo_preview" <?php echo !$logo_id ? 'style="display:none;"' : ''; ?>>
                        <?php _e('Rimuovi', 'attestati-webinar'); ?>
                    </button>
                </div>
            </div>
            
            <div class="att-upload-row">
                <label><?php _e('Firma Digitale', 'attestati-webinar'); ?></label>
                <div class="att-upload-field">
                    <input type="hidden" name="att_firma_id" id="att_firma_id" value="<?php echo esc_attr($firma_id); ?>">
                    <div class="att-preview-image small" id="att_firma_preview">
                        <?php if ($firma_url): ?>
                            <img src="<?php echo esc_url($firma_url); ?>" alt="Firma">
                        <?php endif; ?>
                    </div>
                    <button type="button" class="button att-upload-btn" data-target="att_firma_id" data-preview="att_firma_preview">
                        <?php _e('Carica Firma', 'attestati-webinar'); ?>
                    </button>
                    <button type="button" class="button att-remove-btn" data-target="att_firma_id" data-preview="att_firma_preview" <?php echo !$firma_id ? 'style="display:none;"' : ''; ?>>
                        <?php _e('Rimuovi', 'attestati-webinar'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Editor Posizione Campi
     */
    public function render_editor_box($post) {
        // Posizioni salvate
        $positions = get_post_meta($post->ID, '_att_field_positions', true);
        if (!$positions) {
            $positions = array(
                'nome_cognome' => array('x' => 50, 'y' => 40, 'size' => 24, 'color' => '#333333', 'align' => 'center'),
                'titolo_webinar' => array('x' => 50, 'y' => 30, 'size' => 18, 'color' => '#477C80', 'align' => 'center'),
                'data_webinar' => array('x' => 50, 'y' => 55, 'size' => 14, 'color' => '#666666', 'align' => 'center'),
                'testo_custom' => array('x' => 50, 'y' => 65, 'size' => 14, 'color' => '#333333', 'align' => 'center'),
                'logo' => array('x' => 10, 'y' => 10, 'width' => 15),
                'firma' => array('x' => 70, 'y' => 75, 'width' => 20),
            );
        }
        if (!isset($positions['testo_custom'])) {
            $positions['testo_custom'] = array('x' => 50, 'y' => 65, 'size' => 14, 'color' => '#333333', 'align' => 'center');
        }

        // Definizione campi
        $text_fields = array(
            'nome_cognome'   => __('Nome e Cognome', 'attestati-webinar'),
            'titolo_webinar' => __('Titolo Webinar', 'attestati-webinar'),
            'data_webinar'   => __('Data Webinar', 'attestati-webinar'),
            'testo_custom'   => __('Testo Personalizzato', 'attestati-webinar'),
        );
        $image_fields = array(
            'logo'  => __('Logo', 'attestati-webinar'),
            'firma' => __('Firma', 'attestati-webinar'),
        );
        ?>
        <p class="description" style="margin-bottom: 15px;">
            <?php _e('Imposta le coordinate X e Y in percentuale (0-100). X=50, Y=50 corrisponde al centro dell\'immagine. Usa "Anteprima" per verificare il risultato.', 'attestati-webinar'); ?>
        </p>

        <table class="widefat att-positions-table">
            <thead>
                <tr>
                    <th><?php _e('Campo', 'attestati-webinar'); ?></th>
                    <th><?php _e('Dato', 'attestati-webinar'); ?></th>
                    <th style="width:70px;">X %</th>
                    <th style="width:70px;">Y %</th>
                    <th style="width:70px;"><?php _e('Font', 'attestati-webinar'); ?></th>
                    <th style="width:80px;"><?php _e('Colore', 'attestati-webinar'); ?></th>
                    <th style="width:100px;"><?php _e('Allineamento', 'attestati-webinar'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($text_fields as $key => $label):
                    $pos = isset($positions[$key]) ? $positions[$key] : array();
                ?>
                <tr>
                    <td><strong><?php echo esc_html($label); ?></strong></td>
                    <td class="att-field-source">
                        <?php
                        switch ($key) {
                            case 'nome_cognome':
                                _e('da ordine WooCommerce', 'attestati-webinar');
                                break;
                            case 'titolo_webinar':
                                _e('da impostazioni', 'attestati-webinar');
                                break;
                            case 'data_webinar':
                                _e('da impostazioni', 'attestati-webinar');
                                break;
                            case 'testo_custom':
                                _e('da impostazioni', 'attestati-webinar');
                                break;
                        }
                        ?>
                    </td>
                    <td><input type="number" class="att-pos-input" data-field="<?php echo esc_attr($key); ?>" data-prop="x" min="0" max="100" step="1" value="<?php echo esc_attr(isset($pos['x']) ? $pos['x'] : 50); ?>"></td>
                    <td><input type="number" class="att-pos-input" data-field="<?php echo esc_attr($key); ?>" data-prop="y" min="0" max="100" step="1" value="<?php echo esc_attr(isset($pos['y']) ? $pos['y'] : 50); ?>"></td>
                    <td><input type="number" class="att-pos-input" data-field="<?php echo esc_attr($key); ?>" data-prop="size" min="8" max="72" value="<?php echo esc_attr(isset($pos['size']) ? $pos['size'] : 18); ?>"></td>
                    <td><input type="color" class="att-pos-input" data-field="<?php echo esc_attr($key); ?>" data-prop="color" value="<?php echo esc_attr(isset($pos['color']) ? $pos['color'] : '#333333'); ?>"></td>
                    <td>
                        <select class="att-pos-input" data-field="<?php echo esc_attr($key); ?>" data-prop="align">
                            <option value="left" <?php selected(isset($pos['align']) ? $pos['align'] : 'center', 'left'); ?>><?php _e('Sx', 'attestati-webinar'); ?></option>
                            <option value="center" <?php selected(isset($pos['align']) ? $pos['align'] : 'center', 'center'); ?>><?php _e('Centro', 'attestati-webinar'); ?></option>
                            <option value="right" <?php selected(isset($pos['align']) ? $pos['align'] : 'center', 'right'); ?>><?php _e('Dx', 'attestati-webinar'); ?></option>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <table class="widefat att-positions-table" style="margin-top: 15px;">
            <thead>
                <tr>
                    <th><?php _e('Immagine', 'attestati-webinar'); ?></th>
                    <th style="width:70px;">X %</th>
                    <th style="width:70px;">Y %</th>
                    <th style="width:90px;"><?php _e('Larghezza %', 'attestati-webinar'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($image_fields as $key => $label):
                    $pos = isset($positions[$key]) ? $positions[$key] : array();
                ?>
                <tr>
                    <td><strong><?php echo esc_html($label); ?></strong></td>
                    <td><input type="number" class="att-pos-input" data-field="<?php echo esc_attr($key); ?>" data-prop="x" min="0" max="100" step="1" value="<?php echo esc_attr(isset($pos['x']) ? $pos['x'] : 10); ?>"></td>
                    <td><input type="number" class="att-pos-input" data-field="<?php echo esc_attr($key); ?>" data-prop="y" min="0" max="100" step="1" value="<?php echo esc_attr(isset($pos['y']) ? $pos['y'] : 10); ?>"></td>
                    <td><input type="number" class="att-pos-input" data-field="<?php echo esc_attr($key); ?>" data-prop="width" min="5" max="80" step="1" value="<?php echo esc_attr(isset($pos['width']) ? $pos['width'] : 15); ?>"></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <input type="hidden" name="att_field_positions" id="att_field_positions" value='<?php echo esc_attr(json_encode($positions)); ?>'>

        <div class="att-preview-section">
            <button type="button" class="button button-primary" id="att-preview-btn">
                <?php _e('👁️ Anteprima Attestato', 'attestati-webinar'); ?>
            </button>
            <p class="description"><?php _e('Salva prima il post, poi clicca Anteprima per vedere il risultato.', 'attestati-webinar'); ?></p>
            <div id="att-preview-result"></div>
        </div>
        <?php
    }
    
    /**
     * Render Product Connection Box
     */
    public function render_product_box($post) {
        $product_id = get_post_meta($post->ID, '_att_product_id', true);
        $webinar_title = get_post_meta($post->ID, '_att_webinar_title', true);
        $webinar_date = get_post_meta($post->ID, '_att_webinar_date', true);
        
        // Get WooCommerce products
        $products = wc_get_products(array(
            'limit' => -1,
            'status' => 'publish',
        ));
        ?>
        <div class="att-product-connection">
            <p>
                <label for="att_product_id"><?php _e('Prodotto WooCommerce', 'attestati-webinar'); ?></label>
                <select name="att_product_id" id="att_product_id" class="widefat">
                    <option value=""><?php _e('— Seleziona Prodotto —', 'attestati-webinar'); ?></option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo esc_attr($product->get_id()); ?>" <?php selected($product_id, $product->get_id()); ?>>
                            <?php echo esc_html($product->get_name()); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            
            <p>
                <label for="att_webinar_title"><?php _e('Titolo Webinar (per attestato)', 'attestati-webinar'); ?></label>
                <input type="text" name="att_webinar_title" id="att_webinar_title" class="widefat" 
                       value="<?php echo esc_attr($webinar_title); ?>" 
                       placeholder="<?php _e('Es: Speech is Motor - Pensare le parole', 'attestati-webinar'); ?>">
            </p>
            
            <p>
                <label for="att_webinar_date"><?php _e('Data Webinar', 'attestati-webinar'); ?></label>
                <input type="date" name="att_webinar_date" id="att_webinar_date" class="widefat"
                       value="<?php echo esc_attr($webinar_date); ?>">
            </p>

            <p>
                <label for="att_testo_custom"><?php _e('Testo Personalizzato', 'attestati-webinar'); ?></label>
                <input type="text" name="att_testo_custom" id="att_testo_custom" class="widefat"
                       value="<?php echo esc_attr(get_post_meta($post->ID, '_att_testo_custom', true)); ?>"
                       placeholder="<?php _e('Es: ECM n. 12345 - 5 crediti formativi', 'attestati-webinar'); ?>">
                <span class="description"><?php _e('Testo aggiuntivo da mostrare sull\'attestato', 'attestati-webinar'); ?></span>
            </p>
        </div>
        <?php
    }
    
    /**
     * Render Send Settings Box
     */
    public function render_send_settings_box($post) {
        $send_delay = get_post_meta($post->ID, '_att_send_delay', true);
        $send_auto = get_post_meta($post->ID, '_att_send_auto', true);
        $email_subject = get_post_meta($post->ID, '_att_email_subject', true);
        $email_body = get_post_meta($post->ID, '_att_email_body', true);
        
        if (!$email_subject) {
            $email_subject = 'Il tuo Attestato di Partecipazione - {titolo_webinar}';
        }
        if (!$email_body) {
            $email_body = "Gentile {nome_cognome},\n\ngrazie per aver partecipato al webinar \"{titolo_webinar}\" del {data_webinar}.\n\nIn allegato trovi il tuo Attestato di Partecipazione.\n\nPuoi anche scaricarlo dalla tua area riservata:\n{link_download}\n\nGrazie e a presto!\n\nDott.ssa Caterina Apruzzese";
        }
        ?>
        <div class="att-send-settings">
            <p>
                <label>
                    <input type="checkbox" name="att_send_auto" value="1" <?php checked($send_auto, '1'); ?>>
                    <?php _e('Invio Automatico', 'attestati-webinar'); ?>
                </label>
            </p>
            
            <p>
                <label for="att_send_delay"><?php _e('Ore dopo il webinar', 'attestati-webinar'); ?></label>
                <input type="number" name="att_send_delay" id="att_send_delay" class="widefat" 
                       value="<?php echo esc_attr($send_delay ?: 24); ?>" min="1" max="720">
                <span class="description"><?php _e('Quando inviare dopo la data del webinar', 'attestati-webinar'); ?></span>
            </p>
            
            <p>
                <label for="att_email_subject"><?php _e('Oggetto Email', 'attestati-webinar'); ?></label>
                <input type="text" name="att_email_subject" id="att_email_subject" class="widefat" 
                       value="<?php echo esc_attr($email_subject); ?>">
            </p>
            
            <p>
                <label for="att_email_body"><?php _e('Testo Email', 'attestati-webinar'); ?></label>
                <textarea name="att_email_body" id="att_email_body" class="widefat" rows="8"><?php echo esc_textarea($email_body); ?></textarea>
                <span class="description">
                    <?php _e('Variabili: {nome_cognome}, {titolo_webinar}, {data_webinar}, {link_download}', 'attestati-webinar'); ?>
                </span>
            </p>
        </div>
        <?php
    }
    
    /**
     * Render Actions Box
     */
    public function render_actions_box($post) {
        ?>
        <div class="att-actions">
            <p>
                <button type="button" class="button button-primary widefat" id="att-generate-all-btn">
                    <?php _e('🔄 Genera Tutti gli Attestati', 'attestati-webinar'); ?>
                </button>
            </p>
            <p>
                <button type="button" class="button widefat" id="att-send-all-btn">
                    <?php _e('📧 Invia Tutti gli Attestati', 'attestati-webinar'); ?>
                </button>
            </p>
            <div id="att-action-result"></div>
        </div>
        <?php
    }
    
    /**
     * Save Meta Boxes
     */
    public function save_meta_boxes($post_id, $post) {
        // Verify nonce
        if (!isset($_POST['att_webinar_nonce']) || !wp_verify_nonce($_POST['att_webinar_nonce'], 'att_webinar_meta')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save template
        if (isset($_POST['att_template_id'])) {
            update_post_meta($post_id, '_att_template_id', sanitize_text_field($_POST['att_template_id']));
        }
        
        // Save logo
        if (isset($_POST['att_logo_id'])) {
            update_post_meta($post_id, '_att_logo_id', sanitize_text_field($_POST['att_logo_id']));
        }
        
        // Save firma
        if (isset($_POST['att_firma_id'])) {
            update_post_meta($post_id, '_att_firma_id', sanitize_text_field($_POST['att_firma_id']));
        }
        
        // Save field positions
        if (isset($_POST['att_field_positions'])) {
            $positions = json_decode(stripslashes($_POST['att_field_positions']), true);
            update_post_meta($post_id, '_att_field_positions', $positions);
        }
        
        // Save product connection
        if (isset($_POST['att_product_id'])) {
            update_post_meta($post_id, '_att_product_id', sanitize_text_field($_POST['att_product_id']));
        }
        
        if (isset($_POST['att_webinar_title'])) {
            update_post_meta($post_id, '_att_webinar_title', sanitize_text_field($_POST['att_webinar_title']));
        }
        
        if (isset($_POST['att_webinar_date'])) {
            update_post_meta($post_id, '_att_webinar_date', sanitize_text_field($_POST['att_webinar_date']));
        }

        if (isset($_POST['att_testo_custom'])) {
            update_post_meta($post_id, '_att_testo_custom', sanitize_text_field($_POST['att_testo_custom']));
        }

        // Save send settings
        $send_auto = isset($_POST['att_send_auto']) ? '1' : '0';
        update_post_meta($post_id, '_att_send_auto', $send_auto);
        
        if (isset($_POST['att_send_delay'])) {
            update_post_meta($post_id, '_att_send_delay', intval($_POST['att_send_delay']));
        }
        
        if (isset($_POST['att_email_subject'])) {
            update_post_meta($post_id, '_att_email_subject', sanitize_text_field($_POST['att_email_subject']));
        }
        
        if (isset($_POST['att_email_body'])) {
            update_post_meta($post_id, '_att_email_body', sanitize_textarea_field($_POST['att_email_body']));
        }
    }
    
    /**
     * AJAX Preview
     */
    public function ajax_preview() {
        check_ajax_referer('att_webinar_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Permessi insufficienti', 'attestati-webinar'));
        }

        $attestato_id = intval($_POST['attestato_id']);
        
        $generator = new Att_Webinar_PDF_Generator();
        $result = $generator->generate_preview($attestato_id);
        
        if ($result) {
            wp_send_json_success(array('url' => $result));
        } else {
            $error = isset($generator->last_error) ? $generator->last_error : __('Errore nella generazione dell\'anteprima', 'attestati-webinar');
            wp_send_json_error($error);
        }
    }
    
    /**
     * AJAX Generate Manual
     */
    public function ajax_generate_manual() {
        check_ajax_referer('att_webinar_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permessi insufficienti', 'attestati-webinar'));
        }

        $attestato_id = intval($_POST['attestato_id']);
        
        $generator = new Att_Webinar_PDF_Generator();
        $result = $generator->generate_all_for_attestato($attestato_id);
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX Send Manual
     */
    public function ajax_send_manual() {
        check_ajax_referer('att_webinar_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permessi insufficienti', 'attestati-webinar'));
        }

        $attestato_id = intval($_POST['attestato_id']);

        // Invio a batch: il JS richiamera fino a remaining === 0
        $sender = new Att_Webinar_Email_Sender();
        $result = $sender->send_batch_for_attestato($attestato_id, 25);

        wp_send_json_success($result);
    }
}
