<?php
if (!defined('ABSPATH')) exit;

class DFH_Shortcode {
    
    public function __construct() {
        add_shortcode('dfh_form', array($this, 'render'));
    }

    public function render($atts) {
        $atts = shortcode_atts(array(
            'product_id' => 0,
            'id' => 0
        ), $atts);

        // Product ID belirle
        $product_id = absint($atts['product_id']);
        if (!$product_id) {
            $product_id = absint($atts['id']);
        }
        
        // Otomatik algılama
        if (!$product_id) {
            global $product, $post;
            if ($product && is_a($product, 'WC_Product')) {
                $product_id = $product->get_id();
            } elseif ($post && get_post_type($post) === 'product') {
                $product_id = $post->ID;
            }
        }

        if (!$product_id) {
            if (current_user_can('manage_options')) {
                return '<p style="color:red;padding:10px;background:#fee;border-radius:4px;">[dfh_form] Hata: Ürün ID bulunamadı. Kullanım: [dfh_form product_id="123"]</p>';
            }
            return '';
        }

        // Ürün var mı kontrol
        $product = wc_get_product($product_id);
        if (!$product) {
            if (current_user_can('manage_options')) {
                return '<p style="color:red;padding:10px;background:#fee;border-radius:4px;">[dfh_form] Hata: Ürün bulunamadı (ID: ' . $product_id . ')</p>';
            }
            return '';
        }

        // Rule var mı kontrol
        $frontend = new DFH_Frontend();
        $rule = $frontend->get_rule($product_id);
        
        if (!$rule) {
            if (current_user_can('manage_options')) {
                return '<p style="color:orange;padding:10px;background:#fff3cd;border-radius:4px;">[dfh_form] Bu ürün için hesaplama kuralı tanımlanmamış (Ürün ID: ' . $product_id . ')</p>';
            }
            return '';
        }

        // Assets yükle
        $this->enqueue_assets($product_id, $rule);

        // Form render
        ob_start();
        echo '<div class="dfh-shortcode-wrapper">';
        $frontend->output_form($product_id);
        
        // Quote butonu
        echo '<div class="dfh-shortcode-buttons" style="margin-top:15px;">';
        echo '<a href="mailto:' . esc_attr(get_option('admin_email')) . '?subject=Teklif%20Talebi%20-%20' . urlencode($product->get_name()) . '" id="dfh-quote-btn" class="button alt dfh-quote-btn" style="display:none;">Teklif Al</a>';
        echo '</div>';
        
        echo '</div>';
        return ob_get_clean();
    }

    private function enqueue_assets($product_id, $rule) {
        $product = wc_get_product($product_id);
        if (!$product) return;

        wp_enqueue_style('dfh-front', DFH_URL . 'assets/css/frontend.css', array(), DFH_VERSION);
        wp_enqueue_script('dfh-front', DFH_URL . 'assets/js/frontend.js', array('jquery'), DFH_VERSION, true);

        $formula = get_post_meta($rule->ID, '_dfh_formula', true);

        // Sadece bir kere localize et
        if (!wp_scripts()->get_data('dfh-front', 'data')) {
            wp_localize_script('dfh-front', 'dfhData', array(
                'formula' => $formula,
                'basePrice' => floatval($product->get_regular_price()),
                'ruleId' => $rule->ID,
                'productId' => $product_id,
                'currency' => get_woocommerce_currency_symbol(),
                'currencyPos' => get_option('woocommerce_currency_pos'),
                'thousandSep' => wc_get_price_thousand_separator(),
                'decimalSep' => wc_get_price_decimal_separator(),
                'decimals' => wc_get_price_decimals(),
            ));
        }
    }
}
