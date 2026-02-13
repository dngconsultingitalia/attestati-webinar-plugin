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
        initCanvas();
    });

    /**
     * Imposta altezza esplicita del canvas in pixel (A4 landscape = 70.7%)
     * jQuery UI draggable ha bisogno di un'altezza reale, non padding-bottom.
     */
    function setCanvasHeight() {
        var $canvas = $('#att-editor-canvas');
        if (!$canvas.length) return;
        var w = $canvas.width();
        if (w > 0) {
            $canvas.css('height', Math.round(w * 0.707) + 'px');
        }
    }

    /**
     * Inizializza il canvas: imposta altezza, posiziona i campi, attiva il drag
     */
    function initCanvas() {
        var $canvas = $('#att-editor-canvas');
        if (!$canvas.length) return;

        // Imposta altezza esplicita
        setCanvasHeight();

        // Ricalcola al resize
        $(window).on('resize', function() {
            setCanvasHeight();
            placeFieldsOnCanvas();
        });

        // Posiziona i campi e attiva il drag dopo un breve delay
        setTimeout(function() {
            setCanvasHeight();
            applyFieldVisibility();
            placeFieldsOnCanvas();
            initDraggableFields();
            initFieldProperties();
        }, 200);
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
        var $canvas = $('#att-editor-canvas');
        if (!$canvas.length) return;
        var cw = $canvas.width();
        var ch = $canvas.height();
        if (cw === 0 || ch === 0) return;

        $('.att-field').each(function() {
            var $field = $(this);
            var fieldName = $field.data('field');
            var pos = positions[fieldName];
            if (!pos) return;

            var fw = $field.outerWidth();
            var fh = $field.outerHeight();
            var centerX = (pos.x / 100) * cw;
            var centerY = (pos.y / 100) * ch;
            var left = centerX - (fw / 2);
            var top = centerY - (fh / 2);

            // Clamp dentro il canvas
            left = Math.max(0, Math.min(left, cw - fw));
            top = Math.max(0, Math.min(top, ch - fh));

            $field.css({ left: Math.round(left) + 'px', top: Math.round(top) + 'px' });
        });
    }

    /**
     * Draggable Fields con jQuery UI
     */
    function initDraggableFields() {
        var $canvas = $('#att-editor-canvas');
        if (!$canvas.length) return;

        // Calcola il bounding box reale per il containment
        var offset = $canvas.offset();
        var cw = $canvas.width();
        var ch = $canvas.height();

        $('.att-field').each(function() {
            var $field = $(this);

            // Salta campi gia resi draggable
            if ($field.hasClass('ui-draggable')) return;

            $field.draggable({
                containment: [offset.left, offset.top, offset.left + cw - $field.outerWidth(), offset.top + ch - $field.outerHeight()],
                scroll: false,
                stop: function(event, ui) {
                    var fieldName = $field.data('field');
                    var canvasOff = $canvas.offset();
                    var canvasW = $canvas.width();
                    var canvasH = $canvas.height();
                    if (canvasW === 0 || canvasH === 0) return;

                    // ui.offset = posizione assoluta nella pagina
                    var fieldLeft = ui.offset.left - canvasOff.left;
                    var fieldTop = ui.offset.top - canvasOff.top;

                    var posX = (fieldLeft + ($field.outerWidth() / 2)) / canvasW * 100;
                    var posY = (fieldTop + ($field.outerHeight() / 2)) / canvasH * 100;

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

        // Mostra bottone nascondi
        $('.att-hide-field-btn').show();

        $('.att-select-field-msg').hide();
    }

    function deselectField() {
        $('.att-field').removeClass('selected');
        selectedField = null;
        $('.att-text-prop, .att-image-prop').hide();
        $('.att-hide-field-btn').hide();
        $('.att-select-field-msg').show();
    }

    /**
     * Proprieta campo
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

        // Bottone "Nascondi campo" nella sidebar
        $('#att-hide-selected-field').on('click', function() {
            if (!selectedField) return;
            toggleFieldVisibility(selectedField);
            deselectField();
        });

        // Checkbox nella lista campi (sidebar)
        $(document).on('change', '.att-field-toggle', function() {
            var fieldName = $(this).data('field');
            var visible = $(this).is(':checked');

            if (!positions[fieldName]) {
                positions[fieldName] = {};
            }
            positions[fieldName].hidden = !visible;
            savePositions();

            applyFieldVisibility();
        });
    }

    /**
     * Toggle visibilita di un campo
     */
    function toggleFieldVisibility(fieldName) {
        if (!positions[fieldName]) {
            positions[fieldName] = {};
        }
        positions[fieldName].hidden = !positions[fieldName].hidden;
        savePositions();
        applyFieldVisibility();
    }

    /**
     * Applica la visibilita ai campi sul canvas e aggiorna le checkbox
     */
    function applyFieldVisibility() {
        $('.att-field').each(function() {
            var $field = $(this);
            var fieldName = $field.data('field');
            var pos = positions[fieldName] || {};

            if (pos.hidden) {
                $field.addClass('att-field-hidden');
            } else {
                $field.removeClass('att-field-hidden');
            }
        });

        // Aggiorna checkbox nella lista
        $('.att-field-toggle').each(function() {
            var fieldName = $(this).data('field');
            var pos = positions[fieldName] || {};
            $(this).prop('checked', !pos.hidden);
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
                attestato_id: postId,
                positions: JSON.stringify(positions)
            }, function(response) {
                $btn.prop('disabled', false).text('Anteprima Attestato');

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
                $btn.prop('disabled', false).text('Genera Tutti gli Attestati');

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
                            $btn.prop('disabled', false).text('Invia Tutti gli Attestati');
                            $result.html(
                                'Completato! Inviati ' + totalSent + ' attestati' +
                                (totalErrors > 0 ? ', ' + totalErrors + ' errori' : '') +
                                (remaining > 0 ? ', ' + remaining + ' non inviabili (troppi tentativi)' : '')
                            );
                        }
                    } else {
                        $btn.prop('disabled', false).text('Invia Tutti gli Attestati');
                        $result.removeClass('success').addClass('error').html(response.data);
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('Invia Tutti gli Attestati');
                    $result.removeClass('success').addClass('error').html('Errore di connessione. Riprova.');
                });
            }

            sendBatch();
        });
    }

})(jQuery);
