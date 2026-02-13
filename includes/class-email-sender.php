<?php
/**
 * Email Sender per Attestati
 */

if (!defined('ABSPATH')) {
    exit;
}

class Att_Webinar_Email_Sender {
    
    const MAX_RETRY = 5;

    /**
     * Invia un batch di attestati non ancora inviati per un template.
     * Ritorna il conteggio dei rimanenti per consentire chiamate successive.
     */
    public function send_batch_for_attestato($attestato_id, $batch_size = 25) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'attestati_generati';

        // Conta totale rimanenti (inclusi quelli oltre il batch corrente)
        $total_remaining = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name
             WHERE attestato_id = %d AND status = 'generato' AND tentativi_invio < %d",
            $attestato_id,
            self::MAX_RETRY
        ));

        // Prendi solo il batch corrente
        $attestati = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name
             WHERE attestato_id = %d AND status = 'generato' AND tentativi_invio < %d
             ORDER BY id ASC
             LIMIT %d",
            $attestato_id,
            self::MAX_RETRY,
            $batch_size
        ));

        $sent = 0;
        $errors = 0;

        foreach ($attestati as $att) {
            $result = $this->send_certificate($att, $attestato_id);
            if ($result) {
                $sent++;
            } else {
                $errors++;
                // Incrementa tentativi di invio
                $wpdb->query($wpdb->prepare(
                    "UPDATE $table_name SET tentativi_invio = tentativi_invio + 1 WHERE id = %d",
                    $att->id
                ));
            }
        }

        $remaining = $total_remaining - $sent;

        return array(
            'success'   => true,
            'sent'      => $sent,
            'errors'    => $errors,
            'remaining' => max(0, $remaining),
            'message'   => sprintf(
                __('Inviati %d attestati, %d errori, %d rimanenti', 'attestati-webinar'),
                $sent, $errors, max(0, $remaining)
            ),
        );
    }

    /**
     * Invia tutti gli attestati non ancora inviati per un template (retrocompatibilita).
     * Esegue batch in loop fino a esaurimento.
     */
    public function send_all_for_attestato($attestato_id) {
        $total_sent = 0;
        $total_errors = 0;

        do {
            $result = $this->send_batch_for_attestato($attestato_id, 25);
            $total_sent += $result['sent'];
            $total_errors += $result['errors'];
        } while ($result['remaining'] > 0 && $result['sent'] > 0);

        return array(
            'success' => true,
            'sent'    => $total_sent,
            'errors'  => $total_errors,
            'message' => sprintf(__('Inviati %d attestati, %d errori', 'attestati-webinar'), $total_sent, $total_errors),
        );
    }
    
    /**
     * Invia singolo attestato
     */
    public function send_certificate($certificate, $attestato_id) {
        global $wpdb;
        
        // Recupera impostazioni email
        $subject = get_post_meta($attestato_id, '_att_email_subject', true);
        $body = get_post_meta($attestato_id, '_att_email_body', true);
        $webinar_title = get_post_meta($attestato_id, '_att_webinar_title', true);
        $webinar_date = get_post_meta($attestato_id, '_att_webinar_date', true);
        
        // Link alla sezione My Account (i file non sono accessibili direttamente)
        $download_url = wc_get_account_endpoint_url('attestati');

        // Sostituisci variabili
        $replacements = array(
            '{nome_cognome}' => $certificate->nome_cognome,
            '{titolo_webinar}' => $webinar_title,
            '{data_webinar}' => date_i18n('j F Y', strtotime($webinar_date)),
            '{link_download}' => $download_url,
        );
        
        $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
        $body = str_replace(array_keys($replacements), array_values($replacements), $body);
        
        // Prepara email HTML
        $html_body = $this->get_email_template($body, $certificate, $webinar_title, $webinar_date);
        
        // Headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Dott.ssa Caterina Apruzzese <info@caterinaapruzzese.it>',
        );
        
        // Allegato
        $attachments = array();
        if (file_exists($certificate->pdf_path)) {
            $attachments[] = $certificate->pdf_path;
        }
        
        // Invia
        $result = wp_mail($certificate->email, $subject, $html_body, $headers, $attachments);
        
        if ($result) {
            // Aggiorna status
            $table_name = $wpdb->prefix . 'attestati_generati';
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'inviato',
                    'data_invio' => current_time('mysql'),
                ),
                array('id' => $certificate->id)
            );
        }
        
        return $result;
    }
    
    /**
     * Template HTML email
     */
    private function get_email_template($body, $certificate, $webinar_title, $webinar_date) {
        $body_html = nl2br(esc_html($body));
        $account_url = wc_get_account_endpoint_url('attestati');

        // Rendi il link cliccabile (punta alla sezione My Account)
        $body_html = str_replace(
            esc_html($account_url),
            '<a href="' . esc_url($account_url) . '" style="color: #477C80; text-decoration: underline;">I miei Attestati</a>',
            $body_html
        );

        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin: 0; padding: 0; background-color: #f5f5f5; font-family: Arial, sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 20px;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                            <!-- Header -->
                            <tr>
                                <td style="background: linear-gradient(135deg, #477C80 0%, #5a9a9e 100%); padding: 30px; text-align: center;">
                                    <h1 style="color: #ffffff; margin: 0; font-size: 24px;">🎓 Attestato di Partecipazione</h1>
                                </td>
                            </tr>

                            <!-- Content -->
                            <tr>
                                <td style="padding: 30px;">
                                    <div style="font-size: 16px; line-height: 1.6; color: #333333;">
                                        ' . $body_html . '
                                    </div>
                                </td>
                            </tr>

                            <!-- Button -->
                            <tr>
                                <td style="padding: 0 30px 30px; text-align: center;">
                                    <a href="' . esc_url($account_url) . '"
                                       style="display: inline-block; background-color: #C9A86C; color: #ffffff; padding: 15px 40px; border-radius: 30px; text-decoration: none; font-weight: bold; font-size: 16px;">
                                        📥 Scarica il tuo Attestato
                                    </a>
                                </td>
                            </tr>
                            
                            <!-- Info Box -->
                            <tr>
                                <td style="padding: 0 30px 30px;">
                                    <table width="100%" style="background-color: #f8f9fa; border-radius: 8px; padding: 20px;">
                                        <tr>
                                            <td>
                                                <p style="margin: 0 0 10px; color: #666;"><strong>📌 Webinar:</strong> ' . esc_html($webinar_title) . '</p>
                                                <p style="margin: 0 0 10px; color: #666;"><strong>📅 Data:</strong> ' . date_i18n('j F Y', strtotime($webinar_date)) . '</p>
                                                <p style="margin: 0; color: #666;"><strong>🔖 Codice:</strong> ' . esc_html($certificate->codice_univoco) . '</p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td style="background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #eeeeee;">
                                    <p style="margin: 0; color: #999999; font-size: 14px;">
                                        Dott.ssa Caterina Apruzzese<br>
                                        <a href="https://caterinaapruzzese.it" style="color: #477C80;">caterinaapruzzese.it</a>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
    }
}
