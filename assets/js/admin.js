jQuery(function($) {
    var idx = 0;
    var imgOptIdx = 0;

    $('#dfh-fields .dfh-field').each(function() {
        var i = parseInt($(this).data('index')) || 0;
        if (i > idx) idx = i;
    });

    // Sortable
    if ($.fn.sortable) {
        $('#dfh-fields').sortable({
            handle: '.dfh-handle',
            placeholder: 'dfh-placeholder'
        });
    }

    // Alan ekleme
    $('.dfh-add').on('click', function() {
        var type = $(this).data('type');
        idx++;

        var typeLabels = {
            'number': '🔢 Sayı',
            'text': '📝 Text',
            'select': '📋 Select',
            'radio': '⚪ Radio',
            'radio_button': '🔘 Button Radio',
            'image_radio': '🖼️ Image Radio',
            'checkbox': '☑️ Checkbox',
            'textarea': '📄 Textarea'
        };

        var optionsHtml = '';
        if (type === 'select' || type === 'radio' || type === 'radio_button') {
            optionsHtml = '<div class="dfh-options-section"><label><strong>Seçenekler</strong></label><p class="description">Her satıra: değer|Etiket|Subtitle (opsiyonel)</p><textarea name="dfh_fields['+idx+'][options]" rows="5" class="widefat"></textarea></div>';
        }

        if (type === 'image_radio') {
            optionsHtml = '<div class="dfh-options-section"><label><strong>Görsel Seçenekler</strong></label><div class="dfh-image-options" data-index="'+idx+'"></div><button type="button" class="button dfh-add-image-option" data-index="'+idx+'">+ Seçenek Ekle</button></div>';
        }

        var thresholdHtml = '';
        if (type === 'number') {
            thresholdHtml = '<div class="dfh-threshold-section"><label><strong>⚠️ Teklif Baremi</strong></label><input type="number" name="dfh_fields['+idx+'][threshold]" class="widefat"><p class="description">Bu değer aşıldığında teklif modu aktif.</p></div>';
        }

        var html = '<div class="dfh-field" data-index="'+idx+'">' +
            '<div class="dfh-field-header">' +
                '<span class="dashicons dashicons-move dfh-handle"></span>' +
                '<span class="dfh-title">Yeni Alan</span>' +
                '<code>fields.field_'+idx+'</code>' +
                '<span class="dfh-type">'+(typeLabels[type] || type)+'</span>' +
                '<button type="button" class="dfh-toggle">▼</button>' +
                '<button type="button" class="dfh-duplicate" title="Kopyala">⧉</button>' +
                '<button type="button" class="dfh-remove">✕</button>' +
            '</div>' +
            '<div class="dfh-field-body" style="display:block;">' +
                '<input type="hidden" name="dfh_fields['+idx+'][type]" value="'+type+'">' +
                '<div class="dfh-row">' +
                    '<div class="dfh-col"><label>Etiket</label><input type="text" name="dfh_fields['+idx+'][label]" class="widefat dfh-label-input"></div>' +
                    '<div class="dfh-col"><label>Alan Adı</label><input type="text" name="dfh_fields['+idx+'][name]" value="field_'+idx+'" class="widefat dfh-name-input"><small>Formülde: <code>fields.field_'+idx+'</code></small></div>' +
                '</div>' +
                '<div class="dfh-row">' +
                    '<div class="dfh-col"><label>Genişlik</label><select name="dfh_fields['+idx+'][width]" class="widefat"><option value="12/12">Tam</option><option value="6/12" selected>Yarım</option><option value="4/12">1/3</option><option value="3/12">1/4</option></select></div>' +
                    '<div class="dfh-col"><label>Label Pozisyonu</label><select name="dfh_fields['+idx+'][label_position]" class="widefat"><option value="default">Varsayılan</option><option value="top">Üstte</option><option value="left">Solda</option><option value="hidden">Gizli</option></select></div>' +
                '</div>' +
                '<div class="dfh-row">' +
                    '<div class="dfh-col"><label><input type="checkbox" name="dfh_fields['+idx+'][required]" value="1"> Zorunlu</label></div>' +
                    '<div class="dfh-col"><label>Tooltip</label><input type="text" name="dfh_fields['+idx+'][tooltip]" class="widefat"></div>' +
                '</div>' +
                optionsHtml +
                thresholdHtml +
            '</div>' +
        '</div>';

        $('#dfh-fields').append(html);
        $('#dfh-empty').hide();
    });

    // Image option ekle
    $(document).on('click', '.dfh-add-image-option', function() {
        var fieldIdx = $(this).data('index');
        imgOptIdx++;
        
        var html = '<div class="dfh-image-option">' +
            '<input type="hidden" name="dfh_fields['+fieldIdx+'][image_options]['+imgOptIdx+'][value]" value="opt_'+imgOptIdx+'" class="opt-value-input">' +
            '<input type="hidden" name="dfh_fields['+fieldIdx+'][image_options]['+imgOptIdx+'][image]" value="" class="img-url-input">' +
            '<img src="" class="img-preview" style="display:none;">' +
            '<button type="button" class="button dfh-select-image">Görsel Seç</button>' +
            '<input type="text" name="dfh_fields['+fieldIdx+'][image_options]['+imgOptIdx+'][label]" placeholder="Etiket" class="widefat">' +
            '<input type="text" name="dfh_fields['+fieldIdx+'][image_options]['+imgOptIdx+'][subtitle]" placeholder="Subtitle (opsiyonel)" class="widefat">' +
            '<button type="button" class="dfh-remove-option">✕</button>' +
        '</div>';

        $(this).siblings('.dfh-image-options').append(html);
    });

    // Görsel seç (Media Library)
    $(document).on('click', '.dfh-select-image', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $wrap = $btn.closest('.dfh-image-option');

        var frame = wp.media({
            title: 'Görsel Seç',
            button: { text: 'Seç' },
            multiple: false
        });

        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
            
            $wrap.find('.img-url-input').val(url);
            $wrap.find('.img-preview').attr('src', url).show();
        });

        frame.open();
    });

    // Option sil
    $(document).on('click', '.dfh-remove-option', function() {
        $(this).closest('.dfh-image-option').remove();
    });

    // Aç/Kapat
    $(document).on('click', '.dfh-toggle', function() {
        $(this).closest('.dfh-field').find('.dfh-field-body').slideToggle(200);
    });

    // Sil
    $(document).on('click', '.dfh-remove', function() {
        if (confirm('Bu alanı silmek istediğinize emin misiniz?')) {
            $(this).closest('.dfh-field').fadeOut(200, function() {
                $(this).remove();
                if ($('#dfh-fields .dfh-field').length === 0) {
                    $('#dfh-empty').show();
                }
            });
        }
    });

    // Duplicate (Kopyala)
    $(document).on('click', '.dfh-duplicate', function() {
        var $field = $(this).closest('.dfh-field');
        var $clone = $field.clone();
        
        idx++;
        
        // Index güncelle
        $clone.attr('data-index', idx);
        
        // Tüm input name'lerini güncelle
        $clone.find('input, select, textarea').each(function() {
            var name = $(this).attr('name');
            if (name) {
                $(this).attr('name', name.replace(/\[\d+\]/, '[' + idx + ']'));
            }
        });
        
        // Alan adını değiştir (duplicate olduğu belli olsun)
        var $nameInput = $clone.find('.dfh-name-input');
        var newName = $nameInput.val() + '_copy';
        $nameInput.val(newName);
        
        // Header'daki code'u güncelle
        $clone.find('.dfh-field-header code').text('fields.' + newName);
        
        // Title'a (Kopya) ekle
        var $title = $clone.find('.dfh-title');
        $title.text($title.text() + ' (Kopya)');
        
        // Body'yi kapat
        $clone.find('.dfh-field-body').hide();
        
        // Ekle
        $field.after($clone);
    });

    // Label input değişince title güncelle
    $(document).on('input', '.dfh-label-input', function() {
        $(this).closest('.dfh-field').find('.dfh-title').text($(this).val() || 'Yeni Alan');
    });

    // Name input değişince code güncelle
    $(document).on('input', '.dfh-name-input', function() {
        var name = $(this).val() || 'field';
        var $field = $(this).closest('.dfh-field');
        $field.find('.dfh-field-header code').text('fields.' + name);
        $(this).siblings('small').find('code').text('fields.' + name);
    });
});
