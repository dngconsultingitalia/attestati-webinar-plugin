/**
 * Admin JS - Attestati Webinar
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        initUploadButtons();
        initPositionInputs();
        initPreview();
        initActions();
    });

    /**
     * Upload Buttons
     */
    function initUploadButtons() {
        $('.att-upload-btn').on('click', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var targetInput = $btn.data('target');
            var previewDiv = $btn.data('preview');

            var frame = wp.media({
                title: 'Seleziona Immagine',
                button: { text: 'Usa questa immagine' },
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();

                $('#' + targetInput).val(attachment.id);
                $('#' + previewDiv).html('<img src="' + attachment.url + '" alt="">');
                $btn.siblings('.att-remove-btn').show();
            });

            frame.open();
        });

        $('.att-remove-btn').on('click', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var targetInput = $btn.data('target');
            var previewDiv = $btn.data('preview');

            $('#' + targetInput).val('');
            $('#' + previewDiv).empty();
            $btn.hide();
        });
    }

    /**
     * Position Inputs - aggiorna il campo hidden JSON quando cambiano i valori
     */
    function initPositionInputs() {
        $('.att-pos-input').on('change input', function() {
            var positions = {};

            // Leggi il JSON attuale come base
            var saved = $('#att_field_positions').val();
            if (saved) {
                try { positions = JSON.parse(saved); } catch(e) { positions = {}; }
            }

            // Aggiorna solo il campo modificato
            var field = $(this).data('field');
            var prop = $(this).data('prop');
            var val = $(this).val();

            if (!positions[field]) {
                positions[field] = {};
            }

            // Converti numeri
            if (prop === 'color') {
                positions[field][prop] = val;
            } else if (prop === 'align') {
                positions[field][prop] = val;
            } else {
                positions[field][prop] = parseFloat(val) || 0;
            }

            $('#att_field_positions').val(JSON.stringify(positions));
        });
    }

    /**
     * Preview
     */
    function initPreview() {
        $('#att-preview-btn').on('click', function() {
            var $btn = $(this);
            var postId = $('#post_ID').val();

            $btn.prop('disabled', true).text('Generazione...');

            $.post(attWebinar.ajaxurl, {
                action: 'att_webinar_preview',
                nonce: attWebinar.nonce,
                attestato_id: postId
            }, function(response) {
                $btn.prop('disabled', false).text('👁️ Anteprima Attestato');

                if (response.success) {
                    $('#att-preview-result').html('<img src="' + response.data.url + '" alt="Anteprima">');
                } else {
                    $('#att-preview-result').html('<p style="color: red;">' + response.data + '</p>');
                }
            });
        });
    }

    /**
     * Azioni manuali
     */
    function initActions() {
        $('#att-generate-all-btn').on('click', function() {
            var $btn = $(this);
            var postId = $('#post_ID').val();

            if (!confirm('Generare tutti gli attestati per gli ordini completati?')) {
                return;
            }

            $btn.prop('disabled', true).text('Generazione in corso...');

            $.post(attWebinar.ajaxurl, {
                action: 'att_webinar_generate_manual',
                nonce: attWebinar.nonce,
                attestato_id: postId
            }, function(response) {
                $btn.prop('disabled', false).text('🔄 Genera Tutti gli Attestati');

                if (response.success) {
                    $('#att-action-result')
                        .removeClass('error')
                        .addClass('success')
                        .html(response.data.message)
                        .show();
                } else {
                    $('#att-action-result')
                        .removeClass('success')
                        .addClass('error')
                        .html(response.data)
                        .show();
                }
            });
        });

        $('#att-send-all-btn').on('click', function() {
            var $btn = $(this);
            var postId = $('#post_ID').val();
            var $result = $('#att-action-result');

            if (!confirm('Inviare tutti gli attestati generati ma non ancora inviati?')) {
                return;
            }

            var totalSent = 0;
            var totalErrors = 0;

            $btn.prop('disabled', true);
            $result.removeClass('error').addClass('success').html('Avvio invio...').show();

            function sendBatch() {
                $btn.text('Invio in corso... (' + totalSent + ' inviati)');

                $.post(attWebinar.ajaxurl, {
                    action: 'att_webinar_send_manual',
                    nonce: attWebinar.nonce,
                    attestato_id: postId
                }, function(response) {
                    if (response.success) {
                        totalSent += response.data.sent;
                        totalErrors += response.data.errors;
                        var remaining = response.data.remaining;

                        $result.html(
                            'Inviati ' + totalSent + ' attestati' +
                            (totalErrors > 0 ? ', ' + totalErrors + ' errori' : '') +
                            (remaining > 0 ? ' — ' + remaining + ' rimanenti...' : '')
                        );

                        if (remaining > 0 && response.data.sent > 0) {
                            setTimeout(sendBatch, 1000);
                        } else {
                            $btn.prop('disabled', false).text('📧 Invia Tutti gli Attestati');
                            $result.html(
                                'Completato! Inviati ' + totalSent + ' attestati' +
                                (totalErrors > 0 ? ', ' + totalErrors + ' errori' : '') +
                                (remaining > 0 ? ', ' + remaining + ' non inviabili (troppi tentativi)' : '')
                            );
                        }
                    } else {
                        $btn.prop('disabled', false).text('📧 Invia Tutti gli Attestati');
                        $result.removeClass('success').addClass('error').html(response.data);
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('📧 Invia Tutti gli Attestati');
                    $result.removeClass('success').addClass('error').html('Errore di connessione. Riprova.');
                });
            }

            sendBatch();
        });
    }

})(jQuery);
