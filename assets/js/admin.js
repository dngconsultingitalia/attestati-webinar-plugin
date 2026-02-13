/**
 * Admin JS - Attestati Webinar
 */

(function($) {
    'use strict';

    var selectedField = null;
    var positions = {};

    $(document).ready(function() {
        initUploadButtons();
        loadPositions();
        initPreview();
        initActions();

        // Inizializza editor dopo un breve delay per assicurare il rendering del canvas
        setTimeout(function() {
            placeFieldsOnCanvas();
            initDraggableFields();
            initFieldProperties();
        }, 100);
    });

    /**
     * Ottiene le dimensioni reali del canvas (padding-bottom crea altezza virtuale)
     */
    function getCanvasRect() {
        var canvas = document.getElementById('att-editor-canvas');
        if (!canvas) return { width: 0, height: 0 };
        var rect = canvas.getBoundingClientRect();
        return { width: rect.width, height: rect.height };
    }

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

                if (targetInput === 'att_template_id') {
                    $('#att-editor-canvas').css('background-image', 'url(' + attachment.url + ')');
                    $('#att-editor-canvas .att-no-template').hide();
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
     * Posiziona i campi sul canvas convertendo da percentuale (centro) a pixel (top-left)
     */
    function placeFieldsOnCanvas() {
        var size = getCanvasRect();
        if (size.width === 0 || size.height === 0) return;

        $('.att-field').each(function() {
            var $field = $(this);
            var fieldName = $field.data('field');
            var pos = positions[fieldName];
            if (!pos) return;

            var centerX = (pos.x / 100) * size.width;
            var centerY = (pos.y / 100) * size.height;
            var left = centerX - ($field.outerWidth() / 2);
            var top = centerY - ($field.outerHeight() / 2);

            // Clamp
            left = Math.max(0, Math.min(left, size.width - $field.outerWidth()));
            top = Math.max(0, Math.min(top, size.height - $field.outerHeight()));

            $field.css({ left: Math.round(left) + 'px', top: Math.round(top) + 'px' });
        });
    }

    /**
     * Draggable Fields
     */
    function initDraggableFields() {
        var $canvas = $('#att-editor-canvas');

        $('.att-field').draggable({
            containment: 'parent',
            stop: function(event, ui) {
                var $field = $(this);
                var fieldName = $field.data('field');
                var size = getCanvasRect();
                if (size.width === 0 || size.height === 0) return;

                // ui.position = posizione top-left in pixel relativa al parent
                var posX = (ui.position.left + ($field.outerWidth() / 2)) / size.width * 100;
                var posY = (ui.position.top + ($field.outerHeight() / 2)) / size.height * 100;

                posX = Math.max(0, Math.min(100, posX));
                posY = Math.max(0, Math.min(100, posY));

                if (!positions[fieldName]) {
                    positions[fieldName] = {};
                }
                positions[fieldName].x = Math.round(posX * 10) / 10;
                positions[fieldName].y = Math.round(posY * 10) / 10;

                savePositions();
            }
        });

        // Click per selezionare
        $('.att-field').on('click', function(e) {
            e.stopPropagation();
            selectField($(this));
        });

        // Click fuori per deselezionare
        $canvas.on('click', function(e) {
            if ($(e.target).is('#att-editor-canvas') || $(e.target).hasClass('att-no-template')) {
                deselectField();
            }
        });
    }

    function selectField($field) {
        deselectField();
        $field.addClass('selected');
        selectedField = $field.data('field');

        var pos = positions[selectedField] || {};

        if ($field.hasClass('att-field-text')) {
            $('.att-text-prop').show();
            $('.att-image-prop').hide();
            $('#prop-font-size').val(pos.size || 18);
            $('#prop-color').val(pos.color || '#333333');
            $('#prop-align').val(pos.align || 'center');
        } else {
            $('.att-text-prop').hide();
            $('.att-image-prop').show();
            $('#prop-width').val(pos.width || 15);
        }

        $('.att-select-field-msg').hide();
    }

    function deselectField() {
        $('.att-field').removeClass('selected');
        selectedField = null;
        $('.att-text-prop, .att-image-prop').hide();
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

    function loadPositions() {
        var saved = $('#att_field_positions').val();
        if (saved) {
            try { positions = JSON.parse(saved); } catch(e) { positions = {}; }
        }
    }

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
