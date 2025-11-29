/**
 * DFH Frontend v4.1
 */
(function($) {
    'use strict';

    // dfhData yoksa çık
    if (typeof dfhData === 'undefined') {
        console.log('DFH: dfhData bulunamadı');
        return;
    }

    var DFH = {
        
        init: function() {
            var $form = $('#dfh-form');
            if (!$form.length) {
                console.log('DFH: Form bulunamadı');
                return;
            }

            console.log('DFH: Başlatılıyor...');
            
            this.createTooltip();
            this.bindEvents();
            this.calculate();
        },

        // Tooltip oluştur
        createTooltip: function() {
            if ($('#dfh-tooltip').length) return;
            
            $('<div id="dfh-tooltip"></div>').css({
                position: 'absolute',
                display: 'none',
                background: '#222',
                color: '#fff',
                padding: '8px 12px',
                borderRadius: '5px',
                fontSize: '13px',
                maxWidth: '250px',
                zIndex: 999999,
                boxShadow: '0 3px 10px rgba(0,0,0,0.3)',
                lineHeight: '1.4'
            }).appendTo('body');
        },

        bindEvents: function() {
            var self = this;

            // Input değişiklikleri
            $('#dfh-form').on('input change', 'input, select, textarea', function() {
                self.calculate();
            });

            // WooCommerce miktar
            $(document).on('input change', 'input.qty, input[name="quantity"]', function() {
                self.calculate();
            });

            // +/- butonları
            $(document).on('click', '.plus, .minus, .quantity-btn', function() {
                setTimeout(function() { self.calculate(); }, 50);
            });

            // Tooltip - mouseenter
            $(document).on('mouseenter', '.dfh-tip', function(e) {
                var text = $(this).data('tip') || $(this).attr('title');
                if (!text) return;
                
                var $tip = $('#dfh-tooltip');
                var $el = $(this);
                var offset = $el.offset();
                
                $tip.text(text).show();
                
                // Pozisyon: elementin hemen altında, ortada
                var left = offset.left + ($el.outerWidth() / 2) - ($tip.outerWidth() / 2);
                var top = offset.top + $el.outerHeight() + 8;
                
                // Ekran dışına taşmasın
                if (left < 10) left = 10;
                if (left + $tip.outerWidth() > $(window).width() - 10) {
                    left = $(window).width() - $tip.outerWidth() - 10;
                }
                
                $tip.css({ left: left, top: top });
            });

            // Tooltip - mouseleave
            $(document).on('mouseleave', '.dfh-tip', function() {
                $('#dfh-tooltip').hide();
            });

            // Radio seçim stili
            $('#dfh-form').on('change', 'input[type="radio"]', function() {
                var $group = $(this).closest('.dfh-radio-group, .dfh-button-radio-group, .dfh-image-radio-group');
                $group.find('label').removeClass('is-selected');
                $(this).closest('label').addClass('is-selected');
            });
        },

        getFields: function() {
            var fields = {};
            
            $('#dfh-form').find('input, select, textarea').each(function() {
                var name = $(this).attr('name');
                if (!name) return;
                
                // dfh_inputs[xxx] formatını parse et
                var match = name.match(/dfh_inputs\[([^\]]+)\]/);
                if (!match) return;
                
                var key = match[1];
                var val = '';
                
                if (this.type === 'checkbox') {
                    val = this.checked ? 1 : 0;
                } else if (this.type === 'radio') {
                    if (this.checked) {
                        val = $(this).val();
                    } else {
                        return; // Seçili değilse atla
                    }
                } else {
                    val = $(this).val();
                }
                
                // Sayıya çevirmeyi dene
                if (val !== '' && !isNaN(parseFloat(val)) && isFinite(val)) {
                    val = parseFloat(val);
                }
                
                fields[key] = val;
            });

            return fields;
        },

        calculate: function() {
            var fields = this.getFields();
            var quantity = parseInt($('input.qty, input[name="quantity"]').val()) || 1;
            var product_price = parseFloat(dfhData.basePrice) || 0;

            console.log('DFH: Hesaplama', { fields: fields, quantity: quantity, product_price: product_price });

            // Threshold kontrolü
            var thresholdExceeded = false;
            $('#dfh-form input[data-threshold]').each(function() {
                var val = parseFloat($(this).val()) || 0;
                var threshold = parseFloat($(this).data('threshold'));
                if (threshold > 0 && val > threshold) {
                    thresholdExceeded = true;
                    return false;
                }
            });

            if (thresholdExceeded) {
                this.showQuoteMode();
                return;
            }

            this.hideQuoteMode();

            // Formül hesapla
            var price = product_price * quantity;
            
            if (dfhData.formula && dfhData.formula.trim() !== '') {
                try {
                    // Formülü çalıştır
                    var result = eval(dfhData.formula);
                    if (typeof result === 'number' && !isNaN(result)) {
                        price = result;
                    }
                } catch (e) {
                    console.error('DFH: Formül hatası', e);
                }
            }

            if (price < 0) price = 0;

            this.updatePrice(price);
        },

        updatePrice: function(price) {
            var formatted = this.formatPrice(price);
            
            $('#dfh-price').html(formatted);
            
            // WooCommerce fiyatını güncelle
            $('.summary .price, .product .price').not('#dfh-price').first().html(
                '<span class="woocommerce-Price-amount amount">' + formatted + '</span>'
            );
        },

        formatPrice: function(price) {
            var symbol = dfhData.currency || '₺';
            var decimals = parseInt(dfhData.decimals) || 2;
            var decSep = dfhData.decimalSep || ',';
            var thousandSep = dfhData.thousandSep || '.';
            var position = dfhData.currencyPos || 'right_space';

            // Sayıyı formatla
            var num = price.toFixed(decimals);
            var parts = num.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep);
            var formatted = parts.join(decSep);

            // Para birimi pozisyonu
            switch (position) {
                case 'left':
                    return symbol + formatted;
                case 'left_space':
                    return symbol + ' ' + formatted;
                case 'right':
                    return formatted + symbol;
                case 'right_space':
                default:
                    return formatted + ' ' + symbol;
            }
        },

        showQuoteMode: function() {
            console.log('DFH: Teklif modu aktif');
            
            // Fiyat alanına "Teklif Al!" yaz
            $('#dfh-price').html('<span class="dfh-quote-label">Teklif Al!</span>');
            
            // WooCommerce fiyatını güncelle
            $('.summary .price, .product .price').not('#dfh-price').first().html(
                '<span class="dfh-quote-label">Teklif Al!</span>'
            );
            
            // Sepete Ekle butonunu gizle
            $('.single_add_to_cart_button').hide();
            
            // Teklif Al butonunu göster
            $('#dfh-quote-btn').show();
            
            // Miktar alanını gizle (opsiyonel)
            // $('form.cart .quantity').hide();
        },

        hideQuoteMode: function() {
            // Sepete Ekle butonunu göster
            $('.single_add_to_cart_button').show();
            
            // Teklif Al butonunu gizle
            $('#dfh-quote-btn').hide();
            
            // Miktar alanını göster
            // $('form.cart .quantity').show();
        }
    };

    // DOM hazır olunca başlat
    $(document).ready(function() {
        DFH.init();
    });

    // Global erişim
    window.DFH = DFH;

})(jQuery);
