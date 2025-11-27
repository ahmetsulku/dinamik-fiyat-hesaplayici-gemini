<?php
/**
 * Fiyat hesaplama ve sepet işlemleri
 */

if (!defined('ABSPATH')) {
    exit;
}

class DFH_Calculation {
    
    public function __construct() {
        // Sepete ekleme validasyonu
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_add_to_cart'), 10, 3);
        
        // Sepete veri ekleme
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 3);
        
        // Fiyat hesaplama
        add_action('woocommerce_before_calculate_totals', array($this, 'calculate_dynamic_price'), 20, 1);
        
        // Sepette verileri gösterme
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_data'), 10, 2);
        
        // Siparişe kaydetme
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_order_line_item_meta'), 10, 4);
        
        // AJAX: Canlı fiyat hesaplama
        add_action('wp_ajax_dfh_calculate_price', array($this, 'ajax_calculate_price'));
        add_action('wp_ajax_nopriv_dfh_calculate_price', array($this, 'ajax_calculate_price'));
        
        // Dosya upload için
        add_action('init', array($this, 'handle_file_uploads'));
    }

    public function validate_add_to_cart($passed, $product_id, $quantity) {
        if (empty($_POST['dfh_rule_id'])) {
            return $passed;
        }

        $rule_id = absint($_POST['dfh_rule_id']);
        $form_id = get_post_meta($rule_id, '_dfh_selected_form', true);
        $form_fields = $form_id ? get_post_meta($form_id, '_dfh_form_fields', true) : array();

        if (!is_array($form_fields)) {
            return $passed;
        }

        // Zorunlu alanları kontrol et
        $raw_inputs = isset($_POST['dfh_inputs']) ? (array) $_POST['dfh_inputs'] : array();

        foreach ($form_fields as $field) {
            if (!isset($field['required']) || $field['required'] !== 'yes') {
                continue;
            }

            $field_name = isset($field['name']) ? sanitize_key($field['name']) : '';
            
            if (empty($field_name)) {
                continue;
            }

            $value = isset($raw_inputs[$field_name]) ? $raw_inputs[$field_name] : '';

            if ($field['type'] === 'checkbox') {
                if (empty($value)) {
                    wc_add_notice(sprintf(__('%s alanı zorunludur.', 'dinamik-fiyat'), $field['label']), 'error');
                    $passed = false;
                }
            } elseif (empty($value) && $value !== '0') {
                wc_add_notice(sprintf(__('%s alanı zorunludur.', 'dinamik-fiyat'), $field['label']), 'error');
                $passed = false;
            }
        }

        return $passed;
    }

    public function handle_file_uploads() {
        if (!isset($_POST['dfh_rule_id']) || empty($_FILES)) {
            return;
        }

        foreach ($_FILES as $key => $file) {
            if (strpos($key, 'dfh_file_') !== 0) {
                continue;
            }

            if ($file['error'] !== UPLOAD_ERR_OK) {
                continue;
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $upload = wp_handle_upload($file, array('test_form' => false));

            if (isset($upload['url'])) {
                $field_name = str_replace('dfh_file_', '', $key);
                $_POST['dfh_inputs'][$field_name] = $upload['url'];
            }
        }
    }

    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        if (empty($_POST['dfh_rule_id'])) {
            return $cart_item_data;
        }

        $rule_id = absint($_POST['dfh_rule_id']);
        
        if (!$rule_id) {
            return $cart_item_data;
        }

        $form_id = get_post_meta($rule_id, '_dfh_selected_form', true);
        $form_fields = $form_id ? get_post_meta($form_id, '_dfh_form_fields', true) : array();

        $raw_inputs = isset($_POST['dfh_inputs']) ? (array) $_POST['dfh_inputs'] : array();
        $clean_inputs = array();
        $field_labels = array();

        if (is_array($form_fields)) {
            foreach ($form_fields as $field) {
                $field_name = isset($field['name']) ? sanitize_key($field['name']) : '';
                
                if (empty($field_name)) {
                    continue;
                }

                $type = isset($field['type']) ? $field['type'] : 'text';
                $field_labels[$field_name] = isset($field['label']) && $field['label'] ? $field['label'] : $field_name;
                $raw_value = isset($raw_inputs[$field_name]) ? $raw_inputs[$field_name] : '';

                switch ($type) {
                    case 'number':
                        $clean_inputs[$field_name] = is_numeric($raw_value) ? floatval($raw_value) : 0;
                        break;

                    case 'textarea':
                        $clean_inputs[$field_name] = sanitize_textarea_field($raw_value);
                        break;

                    case 'checkbox':
                        $clean_inputs[$field_name] = !empty($raw_value) ? __('Evet', 'dinamik-fiyat') : __('Hayır', 'dinamik-fiyat');
                        break;

                    case 'file':
                        $clean_inputs[$field_name] = esc_url_raw($raw_value);
                        break;

                    default:
                        $clean_inputs[$field_name] = is_array($raw_value)
                            ? array_map('sanitize_text_field', $raw_value)
                            : sanitize_text_field($raw_value);
                        break;
                }
            }
        }

        if (empty($clean_inputs)) {
            return $cart_item_data;
        }

        $cart_item_data['dfh_data'] = $clean_inputs;
        $cart_item_data['dfh_rule_id'] = $rule_id;
        $cart_item_data['dfh_field_labels'] = $field_labels;
        $cart_item_data['dfh_unique_key'] = md5(wp_json_encode($clean_inputs) . microtime());

        return $cart_item_data;
    }

    public function calculate_dynamic_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (!isset($cart_item['dfh_data']) || !isset($cart_item['dfh_rule_id'])) {
                continue;
            }

            $rule_id = $cart_item['dfh_rule_id'];
            $fields = $cart_item['dfh_data'];
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];
            $product_price = floatval($product->get_regular_price());

            $php_code = get_post_meta($rule_id, '_dfh_php_code', true);

            if (empty($php_code)) {
                continue;
            }

            $calculated_price = $this->execute_user_code($php_code, $fields, $product_price, $quantity);

            if ($calculated_price !== false && is_numeric($calculated_price) && $calculated_price > 0) {
                $cart_item['data']->set_price($calculated_price);
            }
        }
    }

    public function ajax_calculate_price() {
        check_ajax_referer('dfh_calc', 'nonce');

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $rule_id = isset($_POST['rule_id']) ? absint($_POST['rule_id']) : 0;
        $raw_inputs = isset($_POST['dfh_inputs']) ? (array) $_POST['dfh_inputs'] : array();
        $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;

        if (!$product_id || !$rule_id) {
            wp_send_json_error(array('message' => __('Geçersiz istek.', 'dinamik-fiyat')), 400);
        }

        $form_id = get_post_meta($rule_id, '_dfh_selected_form', true);
        $form_fields = $form_id ? get_post_meta($form_id, '_dfh_form_fields', true) : array();

        $clean_inputs = array();

        if (is_array($form_fields)) {
            foreach ($form_fields as $field) {
                $field_name = isset($field['name']) ? sanitize_key($field['name']) : '';
                
                if (empty($field_name)) {
                    continue;
                }

                $type = isset($field['type']) ? $field['type'] : 'text';
                $raw_value = isset($raw_inputs[$field_name]) ? $raw_inputs[$field_name] : '';

                switch ($type) {
                    case 'number':
                        $clean_inputs[$field_name] = is_numeric($raw_value) ? floatval($raw_value) : 0;
                        break;

                    case 'textarea':
                        $clean_inputs[$field_name] = sanitize_textarea_field($raw_value);
                        break;

                    case 'checkbox':
                        $clean_inputs[$field_name] = !empty($raw_value) ? 'Evet' : 'Hayır';
                        break;

                    default:
                        $clean_inputs[$field_name] = is_array($raw_value)
                            ? array_map('sanitize_text_field', $raw_value)
                            : sanitize_text_field($raw_value);
                        break;
                }
            }
        }

        $product = wc_get_product($product_id);
        
        if (!$product) {
            wp_send_json_error(array('message' => __('Ürün bulunamadı.', 'dinamik-fiyat')), 404);
        }

        $product_price = floatval($product->get_regular_price());
        $php_code = get_post_meta($rule_id, '_dfh_php_code', true);

        if (empty($php_code)) {
            wp_send_json_error(array('message' => __('Hesaplama kodu tanımlı değil.', 'dinamik-fiyat')), 400);
        }

        $calculated_price = $this->execute_user_code($php_code, $clean_inputs, $product_price, $quantity);

        if ($calculated_price === false || !is_numeric($calculated_price)) {
            wp_send_json_error(array('message' => __('Hesaplama hatası.', 'dinamik-fiyat')), 500);
        }

        $calculated_price = floatval($calculated_price);

        wp_send_json_success(array(
            'price' => $calculated_price,
            'formatted' => wc_price($calculated_price),
        ));
    }

    private function execute_user_code($code, $fields, $product_price, $quantity = 1) {
        if (empty($code)) {
            return false;
        }

        try {
            ob_start();
            
            // Kod içinde kullanılabilecek değişkenler
            $result = eval($code);
            
            ob_end_clean();
            
            return $result;
        } catch (Exception $e) {
            error_log('DFH Calculation Error: ' . $e->getMessage());
            return false;
        } catch (ParseError $e) {
            error_log('DFH Parse Error: ' . $e->getMessage());
            return false;
        }
    }

    public function display_cart_item_data($item_data, $cart_item) {
        if (!isset($cart_item['dfh_data'])) {
            return $item_data;
        }

        foreach ($cart_item['dfh_data'] as $key => $value) {
            $label = isset($cart_item['dfh_field_labels'][$key]) ? $cart_item['dfh_field_labels'][$key] : ucfirst($key);

            // Dosya URL'si mi kontrol et
            if (filter_var($value, FILTER_VALIDATE_URL)) {
                $display_value = '<a href="' . esc_url($value) . '" target="_blank">' . __('Dosyayı Görüntüle', 'dinamik-fiyat') . '</a>';
            } else {
                $display_value = is_array($value) ? implode(', ', $value) : $value;
            }

            $item_data[] = array(
                'name' => $label,
                'value' => $display_value
            );
        }

        return $item_data;
    }

    public function add_order_line_item_meta($item, $cart_item_key, $values, $order) {
        if (!isset($values['dfh_data'])) {
            return;
        }

        foreach ($values['dfh_data'] as $key => $value) {
            $label = isset($values['dfh_field_labels'][$key]) ? $values['dfh_field_labels'][$key] : ucfirst($key);
            $display_value = is_array($value) ? implode(', ', $value) : $value;
            $item->add_meta_data($label, $display_value, true);
        }
    }
}