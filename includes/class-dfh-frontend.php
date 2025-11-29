<?php
if (!defined('ABSPATH')) exit;

class DFH_Frontend {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'assets'));
        add_action('woocommerce_before_add_to_cart_button', array($this, 'render'), 15);
        add_action('woocommerce_after_add_to_cart_button', array($this, 'render_quote_button'), 10);
    }

    public function assets() {
        if (!is_product()) return;
        
        global $post;
        $rule = $this->get_rule($post->ID);
        if (!$rule) return;

        $product = wc_get_product($post->ID);
        if (!$product) return;

        wp_enqueue_style('dfh-front', DFH_URL . 'assets/css/frontend.css', array(), DFH_VERSION);
        wp_enqueue_script('dfh-front', DFH_URL . 'assets/js/frontend.js', array('jquery'), DFH_VERSION, true);

        $formula = get_post_meta($rule->ID, '_dfh_formula', true);

        wp_localize_script('dfh-front', 'dfhData', array(
            'formula' => $formula,
            'basePrice' => floatval($product->get_regular_price()),
            'ruleId' => $rule->ID,
            'currency' => get_woocommerce_currency_symbol(),
            'currencyPos' => get_option('woocommerce_currency_pos'),
            'thousandSep' => wc_get_price_thousand_separator(),
            'decimalSep' => wc_get_price_decimal_separator(),
            'decimals' => wc_get_price_decimals(),
        ));
    }

    public function render() {
        if (defined('DFH_FORM_RENDERED')) return;
        define('DFH_FORM_RENDERED', true);

        global $product;
        if (!$product) return;

        $this->output_form($product->get_id());
    }

    public function render_quote_button() {
        global $product;
        if (!$product) return;
        
        $rule = $this->get_rule($product->get_id());
        if (!$rule) return;
        
        echo '<a href="mailto:' . esc_attr(get_option('admin_email')) . '?subject=Teklif%20Talebi%20-%20' . urlencode($product->get_name()) . '" id="dfh-quote-btn" class="button alt dfh-quote-btn" style="display:none;">Teklif Al</a>';
    }

    public function output_form($product_id) {
        $rule = $this->get_rule($product_id);
        if (!$rule) return;

        $form_id = get_post_meta($rule->ID, '_dfh_form_id', true);
        if (!$form_id) return;

        $fields = get_post_meta($form_id, '_dfh_fields', true);
        if (empty($fields) || !is_array($fields)) return;

        $form_settings = get_post_meta($form_id, '_dfh_form_settings', true);
        if (!is_array($form_settings)) $form_settings = array();
        $default_label_pos = isset($form_settings['label_position']) ? $form_settings['label_position'] : 'top';

        // Form başlangıcı - WooCommerce form.cart içinde olmalı
        echo '<div id="dfh-form" class="dfh-form">';
        
        // Hidden input - ÖNEMLİ: Bu sepete eklerken gönderilecek
        echo '<input type="hidden" name="dfh_rule_id" value="' . esc_attr($rule->ID) . '">';

        // Fiyat kutusu
        echo '<div id="dfh-price-box" class="dfh-price-box">';
        echo '<span class="dfh-price-label">Toplam Fiyat:</span>';
        echo '<span id="dfh-price" class="dfh-price-value">-</span>';
        echo '</div>';

        echo '<div class="dfh-grid">';
        foreach ($fields as $f) {
            $this->render_field($f, $default_label_pos);
        }
        echo '</div>';
        echo '</div>';
    }

    public function render_field($f, $default_label_pos = 'top') {
        $type = isset($f['type']) ? $f['type'] : 'text';
        $label = isset($f['label']) ? $f['label'] : '';
        $name = isset($f['name']) ? $f['name'] : '';
        $required = !empty($f['required']);
        $tooltip = isset($f['tooltip']) ? $f['tooltip'] : '';
        $options = isset($f['options']) ? $f['options'] : '';
        $threshold = isset($f['threshold']) ? $f['threshold'] : '';
        $width = isset($f['width']) ? $f['width'] : '6/12';
        $field_label_pos = isset($f['label_position']) ? $f['label_position'] : 'default';
        $label_pos = ($field_label_pos === 'default') ? $default_label_pos : $field_label_pos;

        if (empty($name)) return;

        $class = 'dfh-field-wrap dfh-col-' . str_replace('/', '-', $width);
        $class .= ' dfh-label-' . $label_pos;
        $input_name = 'dfh_inputs[' . esc_attr($name) . ']';

        echo '<div class="' . esc_attr($class) . '">';
        
        // Label (checkbox ve hidden hariç)
        if ($label && $type !== 'checkbox' && $label_pos !== 'hidden') {
            echo '<label class="dfh-label">';
            echo '<span class="dfh-label-text">' . esc_html($label) . '</span>';
            if ($required) echo '<span class="dfh-req">*</span>';
            if ($tooltip) {
                echo '<span class="dfh-tip" data-tip="' . esc_attr($tooltip) . '">?</span>';
            }
            echo '</label>';
        }

        echo '<div class="dfh-input-wrap">';

        switch ($type) {
            case 'number':
                $attrs = $threshold ? ' data-threshold="' . esc_attr($threshold) . '"' : '';
                echo '<input type="number" name="' . esc_attr($input_name) . '" class="dfh-input" step="any"' . $attrs . ($required ? ' required' : '') . '>';
                break;

            case 'text':
                echo '<input type="text" name="' . esc_attr($input_name) . '" class="dfh-input"' . ($required ? ' required' : '') . '>';
                break;

            case 'textarea':
                echo '<textarea name="' . esc_attr($input_name) . '" class="dfh-input" rows="3"' . ($required ? ' required' : '') . '></textarea>';
                break;

            case 'select':
                echo '<select name="' . esc_attr($input_name) . '" class="dfh-input"' . ($required ? ' required' : '') . '>';
                echo '<option value="">Seçin...</option>';
                foreach (explode("\n", $options) as $line) {
                    $parts = explode('|', trim($line));
                    if (empty($parts[0])) continue;
                    $val = trim($parts[0]);
                    $txt = isset($parts[1]) ? trim($parts[1]) : $val;
                    echo '<option value="' . esc_attr($val) . '">' . esc_html($txt) . '</option>';
                }
                echo '</select>';
                break;

            case 'radio':
                echo '<div class="dfh-radio-group">';
                foreach (explode("\n", $options) as $line) {
                    $parts = explode('|', trim($line));
                    if (empty($parts[0])) continue;
                    $val = trim($parts[0]);
                    $txt = isset($parts[1]) ? trim($parts[1]) : $val;
                    $subtitle = isset($parts[2]) ? trim($parts[2]) : '';
                    echo '<label class="dfh-radio">';
                    echo '<input type="radio" name="' . esc_attr($input_name) . '" value="' . esc_attr($val) . '"' . ($required ? ' required' : '') . '>';
                    echo '<span class="dfh-radio-text">' . esc_html($txt) . '</span>';
                    if ($subtitle) echo '<span class="dfh-radio-subtitle">' . esc_html($subtitle) . '</span>';
                    echo '</label>';
                }
                echo '</div>';
                break;

            case 'radio_button':
                echo '<div class="dfh-button-radio-group">';
                foreach (explode("\n", $options) as $line) {
                    $parts = explode('|', trim($line));
                    if (empty($parts[0])) continue;
                    $val = trim($parts[0]);
                    $txt = isset($parts[1]) ? trim($parts[1]) : $val;
                    $subtitle = isset($parts[2]) ? trim($parts[2]) : '';
                    echo '<label class="dfh-button-radio">';
                    echo '<input type="radio" name="' . esc_attr($input_name) . '" value="' . esc_attr($val) . '"' . ($required ? ' required' : '') . '>';
                    echo '<span class="dfh-button-radio-inner">';
                    echo '<span class="dfh-button-radio-text">' . esc_html($txt) . '</span>';
                    if ($subtitle) echo '<span class="dfh-button-radio-subtitle">' . esc_html($subtitle) . '</span>';
                    echo '</span>';
                    echo '</label>';
                }
                echo '</div>';
                break;

            case 'image_radio':
                $img_options = !empty($options) ? json_decode($options, true) : array();
                if (!is_array($img_options)) $img_options = array();
                echo '<div class="dfh-image-radio-group">';
                foreach ($img_options as $opt) {
                    $val = isset($opt['value']) ? $opt['value'] : '';
                    $img = isset($opt['image']) ? $opt['image'] : '';
                    $txt = isset($opt['label']) ? $opt['label'] : '';
                    $subtitle = isset($opt['subtitle']) ? $opt['subtitle'] : '';
                    echo '<label class="dfh-image-radio">';
                    echo '<input type="radio" name="' . esc_attr($input_name) . '" value="' . esc_attr($val) . '"' . ($required ? ' required' : '') . '>';
                    echo '<span class="dfh-image-radio-inner">';
                    if ($img) echo '<img src="' . esc_url($img) . '" alt="' . esc_attr($txt) . '">';
                    echo '<span class="dfh-image-radio-text">' . esc_html($txt) . '</span>';
                    if ($subtitle) echo '<span class="dfh-image-radio-subtitle">' . esc_html($subtitle) . '</span>';
                    echo '</span>';
                    echo '</label>';
                }
                echo '</div>';
                break;

            case 'checkbox':
                echo '<label class="dfh-checkbox">';
                echo '<input type="checkbox" name="' . esc_attr($input_name) . '" value="1"' . ($required ? ' required' : '') . '>';
                echo '<span class="dfh-checkbox-text">' . esc_html($label) . '</span>';
                if ($tooltip) echo '<span class="dfh-tip" data-tip="' . esc_attr($tooltip) . '">?</span>';
                echo '</label>';
                break;
        }

        echo '</div>'; // .dfh-input-wrap
        echo '</div>'; // .dfh-field-wrap
    }

    public function get_rule($product_id) {
        static $cache = array();
        
        $product_id = intval($product_id);
        
        if (isset($cache[$product_id])) {
            return $cache[$product_id];
        }

        $rules = get_posts(array(
            'post_type' => 'dfh_rule', 
            'numberposts' => -1, 
            'post_status' => 'publish'
        ));
        
        foreach ($rules as $rule) {
            $ids_str = get_post_meta($rule->ID, '_dfh_product_ids', true);
            if (empty($ids_str)) continue;
            
            // ID'leri array'e çevir ve temizle
            $ids_array = array_map('intval', array_map('trim', explode(',', $ids_str)));
            
            if (in_array($product_id, $ids_array, true)) {
                $cache[$product_id] = $rule;
                return $rule;
            }
        }
        
        $cache[$product_id] = null;
        return null;
    }
}
