<?php
if (!defined('ABSPATH')) exit;

class DFH_Cart {
    
    public function __construct() {
        // Validasyon - öncelik düşük
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate'), 10, 3);
        
        // Sepete veri ekle - öncelik yüksek
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_data'), 10, 3);
        
        // Fiyat hesapla
        add_action('woocommerce_before_calculate_totals', array($this, 'set_price'), 99, 1);
        
        // Sepette göster
        add_filter('woocommerce_get_item_data', array($this, 'display_data'), 10, 2);
        
        // Siparişe kaydet
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_to_order'), 10, 4);
    }

    public function validate($passed, $product_id, $qty) {
        // dfh_rule_id yoksa normal devam
        if (!isset($_POST['dfh_rule_id']) || empty($_POST['dfh_rule_id'])) {
            return $passed;
        }

        $rule_id = absint($_POST['dfh_rule_id']);
        $form_id = get_post_meta($rule_id, '_dfh_form_id', true);
        
        if (!$form_id) return $passed;
        
        $fields = get_post_meta($form_id, '_dfh_fields', true);
        $inputs = isset($_POST['dfh_inputs']) ? $_POST['dfh_inputs'] : array();

        if (!is_array($fields)) return $passed;

        foreach ($fields as $f) {
            $name = isset($f['name']) ? $f['name'] : '';
            if (empty($name)) continue;

            $required = !empty($f['required']);
            $threshold = !empty($f['threshold']) ? floatval($f['threshold']) : 0;
            $value = isset($inputs[$name]) ? $inputs[$name] : '';
            $label = isset($f['label']) ? $f['label'] : $name;

            // Zorunlu alan kontrolü
            if ($required && $value === '' && $value !== '0') {
                wc_add_notice(sprintf('%s alanı zorunludur.', $label), 'error');
                $passed = false;
            }

            // Threshold kontrolü
            if (isset($f['type']) && $f['type'] === 'number' && $threshold > 0 && floatval($value) > $threshold) {
                wc_add_notice('Girdiğiniz miktar baremi aşıyor. Lütfen teklif talep edin.', 'error');
                $passed = false;
            }
        }

        return $passed;
    }

    public function add_data($cart_data, $product_id, $variation_id) {
        // dfh_rule_id kontrolü
        if (!isset($_POST['dfh_rule_id']) || empty($_POST['dfh_rule_id'])) {
            return $cart_data;
        }

        $rule_id = absint($_POST['dfh_rule_id']);
        $form_id = get_post_meta($rule_id, '_dfh_form_id', true);
        
        if (!$form_id) return $cart_data;
        
        $fields = get_post_meta($form_id, '_dfh_fields', true);
        $inputs = isset($_POST['dfh_inputs']) ? $_POST['dfh_inputs'] : array();

        if (!is_array($fields)) return $cart_data;

        $clean = array();
        $labels = array();

        foreach ($fields as $f) {
            $name = isset($f['name']) ? $f['name'] : '';
            if (empty($name)) continue;

            $labels[$name] = isset($f['label']) ? $f['label'] : $name;
            $val = isset($inputs[$name]) ? $inputs[$name] : '';
            $type = isset($f['type']) ? $f['type'] : 'text';

            if ($type === 'number') {
                $clean[$name] = floatval($val);
            } elseif ($type === 'checkbox') {
                $clean[$name] = !empty($val) ? 1 : 0;
            } else {
                $clean[$name] = sanitize_text_field($val);
            }
        }

        // Veri varsa ekle
        $cart_data['dfh_inputs'] = $clean;
        $cart_data['dfh_labels'] = $labels;
        $cart_data['dfh_rule_id'] = $rule_id;
        $cart_data['dfh_unique_key'] = md5(serialize($clean) . time() . rand(1000, 9999));

        return $cart_data;
    }

    public function set_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        if (did_action('woocommerce_before_calculate_totals') >= 2) return;

        foreach ($cart->get_cart() as $cart_key => $cart_item) {
            if (!isset($cart_item['dfh_inputs']) || !isset($cart_item['dfh_rule_id'])) {
                continue;
            }

            $rule_id = $cart_item['dfh_rule_id'];
            $fields = $cart_item['dfh_inputs'];
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];
            $base_price = floatval($product->get_regular_price());

            // Formülü al ve PHP'ye çevir
            $formula = get_post_meta($rule_id, '_dfh_formula', true);
            
            if (empty($formula)) {
                continue;
            }

            $php_code = DFH_Converter::js_to_php($formula);

            // Hesapla
            $total = DFH_Converter::execute_php($php_code, $fields, $base_price, $quantity);

            // Birim fiyat ayarla (WooCommerce quantity ile çarpar)
            if ($total > 0 && $quantity > 0) {
                $unit_price = floatval($total) / intval($quantity);
                $product->set_price($unit_price);
            }
        }
    }

    public function display_data($item_data, $cart_item) {
        if (!isset($cart_item['dfh_inputs'])) {
            return $item_data;
        }

        $labels = isset($cart_item['dfh_labels']) ? $cart_item['dfh_labels'] : array();

        foreach ($cart_item['dfh_inputs'] as $key => $val) {
            // Boş değerleri atla
            if ($val === '' || $val === null) continue;
            
            $label = isset($labels[$key]) ? $labels[$key] : $key;
            $display_val = $val;
            
            // Checkbox için
            if ($val === 1 || $val === '1') {
                $display_val = 'Evet';
            } elseif ($val === 0 || $val === '0') {
                continue; // Hayır olanları gösterme
            }

            $item_data[] = array(
                'name' => esc_html($label),
                'value' => esc_html($display_val)
            );
        }

        return $item_data;
    }

    public function save_to_order($item, $cart_item_key, $values, $order) {
        if (!isset($values['dfh_inputs'])) {
            return;
        }

        $labels = isset($values['dfh_labels']) ? $values['dfh_labels'] : array();

        foreach ($values['dfh_inputs'] as $key => $val) {
            if ($val === '' || $val === null) continue;
            
            $label = isset($labels[$key]) ? $labels[$key] : $key;
            $display_val = $val;
            
            if ($val === 1 || $val === '1') {
                $display_val = 'Evet';
            } elseif ($val === 0 || $val === '0') {
                $display_val = 'Hayır';
            }

            $item->add_meta_data($label, $display_val, true);
        }
    }
}
