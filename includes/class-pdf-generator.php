<?php
/**
 * Generatore Attestati (output: immagine PNG)
 *
 * Il nome della classe e dei campi DB (pdf_path/pdf_url) contengono "pdf" per motivi legacy,
 * ma il formato effettivo generato e PNG tramite la libreria GD.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Att_Webinar_PDF_Generator {
    
    private $upload_dir;
    private $upload_url;
    
    public function __construct() {
        $upload = wp_upload_dir();
        $this->upload_dir = $upload['basedir'] . '/attestati-webinar/';
        $this->upload_url = $upload['baseurl'] . '/attestati-webinar/';
        
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
        }
    }
    
    /**
     * Genera anteprima con dati fittizi
     */
    public function generate_preview($attestato_id) {
        $data = array(
            'nome_cognome' => 'Mario Rossi',
            'titolo_webinar' => get_post_meta($attestato_id, '_att_webinar_title', true) ?: 'Titolo Webinar di Esempio',
            'data_webinar' => get_post_meta($attestato_id, '_att_webinar_date', true) ?: date('Y-m-d'),
            'testo_custom' => get_post_meta($attestato_id, '_att_testo_custom', true) ?: '',
        );
        
        return $this->generate_certificate($attestato_id, $data, 'preview');
    }
    
    /**
     * Genera tutti gli attestati per un attestato template
     */
    public function generate_all_for_attestato($attestato_id) {
        global $wpdb;
        
        $product_id = get_post_meta($attestato_id, '_att_product_id', true);
        
        if (!$product_id) {
            return array('success' => false, 'message' => __('Nessun prodotto collegato', 'attestati-webinar'));
        }
        
        // Trova solo gli ordini completati che contengono questo prodotto,
        // escludendo quelli per cui l'attestato e stato gia generato
        $table_name = $wpdb->prefix . 'attestati_generati';

        $order_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT oi.order_id
             FROM {$wpdb->prefix}woocommerce_order_items oi
             INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
                 ON oi.order_item_id = oim.order_item_id
                 AND oim.meta_key = '_product_id'
                 AND oim.meta_value = %d
             INNER JOIN {$wpdb->posts} p
                 ON oi.order_id = p.ID
                 AND p.post_status = 'wc-completed'
             WHERE oi.order_id NOT IN (
                 SELECT order_id FROM $table_name WHERE attestato_id = %d
             )",
            $product_id,
            $attestato_id
        ));

        $generated = 0;
        $errors = 0;

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                $errors++;
                continue;
            }

            $result = $this->generate_for_order($attestato_id, $order);
            if ($result) {
                $generated++;
            } else {
                $errors++;
            }
        }
        
        return array(
            'success' => true,
            'generated' => $generated,
            'errors' => $errors,
            'message' => sprintf(__('Generati %d attestati, %d errori', 'attestati-webinar'), $generated, $errors)
        );
    }
    
    /**
     * Genera attestato per un ordine specifico
     */
    public function generate_for_order($attestato_id, $order) {
        global $wpdb;
        
        $nome_cognome = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $email = $order->get_billing_email();
        $user_id = $order->get_user_id();
        $product_id = get_post_meta($attestato_id, '_att_product_id', true);
        
        $data = array(
            'nome_cognome' => $nome_cognome,
            'titolo_webinar' => get_post_meta($attestato_id, '_att_webinar_title', true),
            'data_webinar' => get_post_meta($attestato_id, '_att_webinar_date', true),
            'testo_custom' => get_post_meta($attestato_id, '_att_testo_custom', true) ?: '',
        );
        
        // Genera codice univoco
        $codice = 'ATT-' . $order->get_id() . '-' . strtoupper(wp_generate_password(6, false));
        
        // Genera PDF
        $filename = 'attestato-' . $order->get_id() . '-' . sanitize_title($nome_cognome) . '.png';
        $pdf_path = $this->generate_certificate($attestato_id, $data, $filename);
        
        if (!$pdf_path) {
            return false;
        }
        
        // Salva nel database
        $table_name = $wpdb->prefix . 'attestati_generati';
        $wpdb->insert($table_name, array(
            'attestato_id' => $attestato_id,
            'order_id' => $order->get_id(),
            'user_id' => $user_id,
            'product_id' => $product_id,
            'nome_cognome' => $nome_cognome,
            'email' => $email,
            'pdf_path' => $this->upload_dir . $filename,
            'pdf_url' => $this->upload_url . $filename,
            'codice_univoco' => $codice,
            'data_generazione' => current_time('mysql'),
            'status' => 'generato',
        ));
        
        return true;
    }
    
    /**
     * Genera il certificato come immagine
     */
    public function generate_certificate($attestato_id, $data, $filename = 'preview') {
        // Carica template
        $template_id = get_post_meta($attestato_id, '_att_template_id', true);
        if (!$template_id) {
            $this->last_error = __('Nessun template caricato. Carica un\'immagine template prima.', 'attestati-webinar');
            return false;
        }

        $template_path = get_attached_file($template_id);
        if (!$template_path || !file_exists($template_path)) {
            $this->last_error = __('File template non trovato sul server. Ricarica l\'immagine.', 'attestati-webinar');
            return false;
        }

        // Carica posizioni
        $positions = get_post_meta($attestato_id, '_att_field_positions', true);

        // Carica immagine template
        $extension = strtolower(pathinfo($template_path, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $image = imagecreatefromjpeg($template_path);
                break;
            case 'png':
                $image = imagecreatefrompng($template_path);
                break;
            default:
                $this->last_error = sprintf(__('Formato immagine "%s" non supportato. Usa JPG o PNG.', 'attestati-webinar'), $extension);
                return false;
        }

        if (!$image) {
            $this->last_error = __('Impossibile caricare l\'immagine template con GD.', 'attestati-webinar');
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // Font path - cerca in ordine: font custom, poi fallback multipiattaforma
        $font_path = $this->resolve_font_path();
        if (!$font_path) {
            imagedestroy($image);
            $this->last_error = __('Nessun font TTF trovato. Verifica che assets/fonts/OpenSans.ttf esista.', 'attestati-webinar');
            return false;
        }

        // Aggiungi testi (salta campi nascosti)
        foreach (['nome_cognome', 'titolo_webinar', 'data_webinar', 'testo_custom'] as $field) {
            if (isset($positions[$field]) && !empty($positions[$field]['hidden'])) {
                continue;
            }
            if (isset($positions[$field]) && isset($data[$field]) && $data[$field] !== '') {
                $pos = $positions[$field];
                $x = ($pos['x'] / 100) * $width;
                $y = ($pos['y'] / 100) * $height;
                $size = isset($pos['size']) ? $pos['size'] : 18;
                $color_hex = isset($pos['color']) ? $pos['color'] : '#333333';
                
                // Converti colore hex in RGB
                $color_rgb = $this->hex_to_rgb($color_hex);
                $color = imagecolorallocate($image, $color_rgb['r'], $color_rgb['g'], $color_rgb['b']);
                
                // Formatta data se necessario
                $text = $data[$field];
                if ($field === 'data_webinar' && strtotime($text)) {
                    $text = date_i18n('j F Y', strtotime($text));
                }
                
                // Calcola allineamento
                $align = isset($pos['align']) ? $pos['align'] : 'center';
                $bbox = imagettfbbox($size, 0, $font_path, $text);
                $text_width = $bbox[2] - $bbox[0];
                
                if ($align === 'center') {
                    $x = $x - ($text_width / 2);
                } elseif ($align === 'right') {
                    $x = $x - $text_width;
                }
                
                // Scrivi testo
                imagettftext($image, $size, 0, $x, $y, $color, $font_path, $text);
            }
        }
        
        // Aggiungi logo (se non nascosto)
        $logo_id = get_post_meta($attestato_id, '_att_logo_id', true);
        if ($logo_id && isset($positions['logo']) && empty($positions['logo']['hidden'])) {
            $logo_path = get_attached_file($logo_id);
            if (file_exists($logo_path)) {
                $this->add_image_to_canvas($image, $logo_path, $positions['logo'], $width, $height);
            }
        }

        // Aggiungi firma (se non nascosta)
        $firma_id = get_post_meta($attestato_id, '_att_firma_id', true);
        if ($firma_id && isset($positions['firma']) && empty($positions['firma']['hidden'])) {
            $firma_path = get_attached_file($firma_id);
            if (file_exists($firma_path)) {
                $this->add_image_to_canvas($image, $firma_path, $positions['firma'], $width, $height);
            }
        }
        
        // Salva immagine
        $output_path = $this->upload_dir . $filename;
        
        if ($filename === 'preview') {
            $output_path = $this->upload_dir . 'preview-' . $attestato_id . '.png';
        }
        
        imagepng($image, $output_path);
        imagedestroy($image);
        
        if ($filename === 'preview') {
            return $this->upload_url . 'preview-' . $attestato_id . '.png?t=' . time();
        }
        
        return $output_path;
    }
    
    /**
     * Aggiunge immagine (logo/firma) al canvas
     */
    private function add_image_to_canvas(&$canvas, $image_path, $position, $canvas_width, $canvas_height) {
        $extension = strtolower(pathinfo($image_path, PATHINFO_EXTENSION));
        
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $overlay = imagecreatefromjpeg($image_path);
                break;
            case 'png':
                $overlay = imagecreatefrompng($image_path);
                break;
            default:
                return;
        }
        
        if (!$overlay) {
            return;
        }
        
        $overlay_width = imagesx($overlay);
        $overlay_height = imagesy($overlay);
        
        // Calcola dimensione target
        $target_width = isset($position['width']) ? ($position['width'] / 100) * $canvas_width : $overlay_width;
        $ratio = $target_width / $overlay_width;
        $target_height = $overlay_height * $ratio;
        
        // Calcola posizione
        $x = ($position['x'] / 100) * $canvas_width;
        $y = ($position['y'] / 100) * $canvas_height;
        
        // Copia con ridimensionamento
        imagecopyresampled(
            $canvas,
            $overlay,
            $x, $y,
            0, 0,
            $target_width, $target_height,
            $overlay_width, $overlay_height
        );
        
        imagedestroy($overlay);
    }
    
    /**
     * Risolve il percorso del font TTF cercando in piu posizioni
     */
    private function resolve_font_path() {
        $candidates = array(
            // Font bundled con il plugin
            ATT_WEBINAR_PATH . 'assets/fonts/OpenSans.ttf',
            ATT_WEBINAR_PATH . 'assets/fonts/Helvetica.ttf',
            // Linux (Ubuntu/Debian)
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
            // Linux (CentOS/RHEL)
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
            // Windows
            'C:\\Windows\\Fonts\\arial.ttf',
        );

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Nessun font trovato: logga errore
        error_log('Attestati Webinar: nessun font TTF trovato. Posiziona un file .ttf in ' . ATT_WEBINAR_PATH . 'assets/fonts/');
        return false;
    }

    /**
     * Converte colore hex in RGB
     */
    private function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        
        return array(
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        );
    }
}
