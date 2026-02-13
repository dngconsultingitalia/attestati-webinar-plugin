<?php
/**
 * Cron Handler per invio automatico attestati
 */

if (!defined('ABSPATH')) {
    exit;
}

class Att_Webinar_Cron_Handler {
    
    public function __construct() {
        add_action('att_webinar_check_send_certificates', array($this, 'check_and_send'));
    }
    
    /**
     * Controlla e invia attestati automaticamente
     * Eseguito ogni ora dal cron
     */
    public function check_and_send() {
        // Trova tutti gli attestati con invio automatico attivo
        $attestati = get_posts(array(
            'post_type' => 'attestato_webinar',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_att_send_auto',
                    'value' => '1',
                ),
            ),
        ));
        
        foreach ($attestati as $attestato) {
            $this->process_attestato($attestato->ID);
        }
    }
    
    /**
     * Processa un singolo attestato template
     */
    private function process_attestato($attestato_id) {
        $webinar_date = get_post_meta($attestato_id, '_att_webinar_date', true);
        $send_delay = get_post_meta($attestato_id, '_att_send_delay', true) ?: 24;
        $product_id = get_post_meta($attestato_id, '_att_product_id', true);
        
        if (!$webinar_date || !$product_id) {
            return;
        }
        
        // Calcola quando inviare
        $send_time = strtotime($webinar_date) + ($send_delay * 3600);
        
        // Se e passato il momento di invio
        if (time() >= $send_time) {
            // Genera attestati mancanti
            $generator = new Att_Webinar_PDF_Generator();
            $generator->generate_all_for_attestato($attestato_id);

            // Invia un batch (il cron rieseguira ogni 5 min per i rimanenti)
            $sender = new Att_Webinar_Email_Sender();
            $sender->send_batch_for_attestato($attestato_id, 25);
        }
    }
}
