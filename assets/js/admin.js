/**
 * Admin JS - Attestati Webinar
 */

(function($) {
    'use strict';
    
    var selectedField = null;
    var positions = {};
    
    $(document).ready(function() {
        initUploadButtons();
        initDraggableFields();
        initFieldProperties();
        initPreview();
        initActions();
        loadPositions();
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
                
                // Se è il template, aggiorna lo sfondo dell'editor
                if (targetInput === 'att_template_id') {
                    $('#att-editor-canvas').css('background-image', 'url(' + attachment.url + ')');
                    $('#att-editor-canvas .att-no-template').hide();
                    $('#att-editor-canvas .att-field').show();
                }
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
     * Draggable Fields
     */
    function initDraggableFields() {
        $('.att-field').draggable({
            containment: '#att-editor-canvas',
            stop: function(event, ui) {
                var $field = $(this);
                var $canvas = $('#att-editor-canvas');
                var fieldName = $field.data('field');
                
                // Calcola posizione percentuale
                var canvasWidth = $canvas.width();
                var canvasHeight = $canvas.height();
                
                var posX = (ui.position.left + ($field.outerWidth() / 2)) / canvasWidth * 100;
                var posY = (ui.position.top + ($field.outerHeight() / 2)) / canvasHeight * 100;
                
                // Aggiorna posizioni
                if (!positions[fieldName]) {
                    positions[fieldName] = {};
                }
                positions[fieldName].x = posX;
                positions[fieldName].y = posY;
                
                savePositions();
            }
        });
        
        // Click per selezionare
        $('.att-field').on('click', function(e) {
            e.stopPropagation();
            selectField($(this));
        });
        
        // Click fuori per deselezionare
        $('#att-editor-canvas').on('click', function(e) {
            if ($(e.target).is('#att-editor-canvas')) {
                deselectField();
            }
        });
    }
    
    /**
     * Seleziona campo
     */
    function selectField($field) {
        deselectField();
        
        $field.addClass('selected');
        selectedField = $field.data('field');
        
        // Mostra proprietà
        var pos = positions[selectedField] || {};
        
        if ($field.hasClass('att-field-text')) {
            $('.att-property-group').not('.att-image-prop').show();
            $('.att-image-prop').hide();
            
            $('#prop-font-size').val(pos.size || 18);
            $('#prop-color').val(pos.color || '#333333');
            $('#prop-align').val(pos.align || 'center');
        } else {
            $('.att-property-group').hide();
            $('.att-image-prop').show();
            
            $('#prop-width').val(pos.width || 15);
        }
        
        $('.att-select-field-msg').hide();
    }
    
    /**
     * Deseleziona campo
     */
    function deselectField() {
        $('.att-field').removeClass('selected');
        selectedField = null;
        $('.att-property-group').hide();
        $('.att-select-field-msg').show();
    }
    
    /**
     * Proprietà campo
     */
    function initFieldProperties() {
        $('#prop-font-size, #prop-color, #prop-align, #prop-width').on('change', function() {
            if (!selectedField) return;
            
            if (!positions[selectedField]) {
                positions[selectedField] = {};
            }
            
            positions[selectedField].size = parseInt($('#prop-font-size').val());
            positions[selectedField].color = $('#prop-color').val();
            positions[selectedField].align = $('#prop-align').val();
            positions[selectedField].width = parseInt($('#prop-width').val());
            
            savePositions();
        });
    }
    
    /**
     * Carica posizioni salvate
     */
    function loadPositions() {
        var saved = $('#att_field_positions').val();
        if (saved) {
            try {
                positions = JSON.parse(saved);
            } catch(e) {
                positions = {};
            }
        }
    }
    
    /**
     * Salva posizioni
     */
    function savePositions() {
        $('#att_field_positions').val(JSON.stringify(positions));
    }
    
    /**
     * Preview
     */
    function initPreview() {
        $('#att-preview-btn').on('click', function() {
            var $btn = $(this);
            var postId = $('#post_ID').val();
            
            $btn.prop('disabled', true).text('Generazione...');
            
            // Salva prima il post via AJAX
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
                            // Prossimo batch dopo breve pausa
                            setTimeout(sendBatch, 1000);
                        } else {
                            // Completato
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
