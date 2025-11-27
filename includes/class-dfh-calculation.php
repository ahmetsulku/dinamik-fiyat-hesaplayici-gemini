<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DFH_Calculation {

    public function __construct() {
        // 1. Validasyon (gerekirse ek kontroller için kullanılabilir)
        add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 3 );

        // 2. Verileri İşleme
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );

        // 3. Fiyat Hesaplama
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'calculate_dynamic_price' ), 20, 1 );

        // 4. Verileri Gösterme
        add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );

        // 5. Siparişe Kaydetme
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_line_item_meta' ), 10, 4 );

        // 6. AJAX ile canlı fiyat hesaplama
        add_action( 'wp_ajax_dfh_calculate_price', array( $this, 'ajax_calculate_price' ) );
        add_action( 'wp_ajax_nopriv_dfh_calculate_price', array( $this, 'ajax_calculate_price' ) );
    }

    /**
     * ADIM 1: Validasyon
     * Şu an sadece temel kontroller için iskelet bir yapı.
     */
    public function validate_add_to_cart( $passed, $product_id, $quantity ) {
        return $passed;
    }

    /**
     * ADIM 2: Veriyi Kaydetme ve Dosya Upload
     * Validasyon geçtiyse burası çalışır.
     */
    public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
        if ( empty( $_POST['dfh_rule_id'] ) ) {
            return $cart_item_data;
        }

        $rule_id = absint( $_POST['dfh_rule_id'] );
        if ( ! $rule_id ) {
            return $cart_item_data;
        }

        $form_id = get_post_meta( $rule_id, '_dfh_selected_form', true );
        $form_fields = $form_id ? get_post_meta( $form_id, '_dfh_form_fields', true ) : array();

        $raw_inputs   = isset( $_POST['dfh_inputs'] ) ? (array) $_POST['dfh_inputs'] : array();
        $clean_inputs = array();
        $field_labels = array();

        if ( is_array( $form_fields ) ) {
            foreach ( $form_fields as $field ) {
                $field_name = isset( $field['name'] ) ? sanitize_key( $field['name'] ) : '';
                if ( empty( $field_name ) ) {
                    continue;
                }

                $type          = isset( $field['type'] ) ? $field['type'] : 'text';
                $field_labels[ $field_name ] = isset( $field['label'] ) && $field['label'] ? $field['label'] : $field_name;
                $raw_value     = isset( $raw_inputs[ $field_name ] ) ? $raw_inputs[ $field_name ] : '';

                switch ( $type ) {
                    case 'number':
                        $clean_inputs[ $field_name ] = is_numeric( $raw_value ) ? floatval( $raw_value ) : '';
                        break;
                    case 'textarea':
                        $clean_inputs[ $field_name ] = sanitize_textarea_field( $raw_value );
                        break;
                    case 'checkbox':
                        $clean_inputs[ $field_name ] = isset( $raw_inputs[ $field_name ] ) ? 'Evet' : 'Hayır';
                        break;
                    default:
                        $clean_inputs[ $field_name ] = is_array( $raw_value )
                            ? array_map( 'sanitize_text_field', $raw_value )
                            : sanitize_text_field( $raw_value );
                        break;
                }
            }
        }

        if ( empty( $clean_inputs ) ) {
            return $cart_item_data;
        }

        $cart_item_data['dfh_data']         = $clean_inputs;
        $cart_item_data['dfh_rule_id']      = $rule_id;
        $cart_item_data['dfh_field_labels'] = $field_labels;
        $cart_item_data['dfh_unique_key']   = md5( wp_json_encode( $clean_inputs ) . microtime() );

        return $cart_item_data;
    }

    /**
     * AJAX: Canlı fiyat hesaplama (ürün sayfasında ön izleme)
     */
    public function ajax_calculate_price() {
        check_ajax_referer( 'dfh_calc', 'nonce' );

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $rule_id    = isset( $_POST['dfh_rule_id'] ) ? absint( $_POST['dfh_rule_id'] ) : 0;
        $raw_inputs = isset( $_POST['dfh_inputs'] ) ? (array) $_POST['dfh_inputs'] : array();

        if ( ! $product_id || ! $rule_id ) {
            wp_send_json_error( array( 'message' => 'Geçersiz istek.' ), 400 );
        }

        $form_id     = get_post_meta( $rule_id, '_dfh_selected_form', true );
        $form_fields = $form_id ? get_post_meta( $form_id, '_dfh_form_fields', true ) : array();

        $clean_inputs = array();

        if ( is_array( $form_fields ) ) {
            foreach ( $form_fields as $field ) {
                $field_name = isset( $field['name'] ) ? sanitize_key( $field['name'] ) : '';
                if ( empty( $field_name ) ) {
                    continue;
                }

                $type      = isset( $field['type'] ) ? $field['type'] : 'text';
                $raw_value = isset( $raw_inputs[ $field_name ] ) ? $raw_inputs[ $field_name ] : '';

                switch ( $type ) {
                    case 'number':
                        $clean_inputs[ $field_name ] = is_numeric( $raw_value ) ? floatval( $raw_value ) : 0;
                        break;
                    case 'textarea':
                        $clean_inputs[ $field_name ] = sanitize_textarea_field( $raw_value );
                        break;
                    case 'checkbox':
                        $clean_inputs[ $field_name ] = isset( $raw_inputs[ $field_name ] ) ? 'Evet' : 'Hayır';
                        break;
                    default:
                        $clean_inputs[ $field_name ] = is_array( $raw_value )
                            ? array_map( 'sanitize_text_field', $raw_value )
                            : sanitize_text_field( $raw_value );
                        break;
                }
            }
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( array( 'message' => 'Ürün bulunamadı.' ), 404 );
        }

        $product_price = floatval( $product->get_price() );
        $php_code      = get_post_meta( $rule_id, '_dfh_php_code', true );

        if ( empty( $php_code ) ) {
            wp_send_json_error( array( 'message' => 'Hesaplama kodu tanımlı değil.' ), 400 );
        }

        $calculated_price = $this->execute_user_code( $php_code, $clean_inputs, $product_price );

        if ( $calculated_price === false || ! is_numeric( $calculated_price ) ) {
            wp_send_json_error( array( 'message' => 'Hesaplama hatası.' ), 500 );
        }

        $calculated_price = floatval( $calculated_price );

        wp_send_json_success( array(
            'price'     => $calculated_price,
            'formatted' => function_exists( 'wc_price' ) ? wc_price( $calculated_price ) : $calculated_price,
        ) );
    }

    // --- Diğer Fonksiyonlar ---

    public function calculate_dynamic_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( isset( $cart_item['dfh_data'] ) && isset( $cart_item['dfh_rule_id'] ) ) {
                $rule_id = $cart_item['dfh_rule_id'];
                $fields = $cart_item['dfh_data'];
                $product = $cart_item['data'];
                $product_price = floatval( $product->get_price() );
                $php_code = get_post_meta( $rule_id, '_dfh_php_code', true );
                if ( ! empty( $php_code ) ) {
                    $calculated_price = $this->execute_user_code( $php_code, $fields, $product_price );
                    if ( $calculated_price !== false && is_numeric( $calculated_price ) ) {
                        $cart_item['data']->set_price( $calculated_price );
                    }
                }
            }
        }
    }

    private function execute_user_code( $code, $fields, $product_price ) {
        if ( empty( $code ) ) return false;
        try {
            ob_start();
            $eval_result = eval($code); // Return değeri beklenir
            ob_end_clean();
            return $eval_result;
        } catch ( Exception $e ) {
            return false;
        }
    }

    public function display_cart_item_data( $item_data, $cart_item ) {
        if ( isset( $cart_item['dfh_data'] ) ) {
            foreach ( $cart_item['dfh_data'] as $key => $value ) {
                // Eğer value bir URL ise (Dosya) 'Dosya Linki' diye gösterelim
                if ( filter_var($value, FILTER_VALIDATE_URL) ) {
                    $display_value = '<a href="' . esc_url($value) . '" target="_blank">Dosyayı Görüntüle</a>';
                } else {
                    $display_value = $value;
                }

                $label = isset( $cart_item['dfh_field_labels'][ $key ] ) ? $cart_item['dfh_field_labels'][ $key ] : ucfirst($key);

                $item_data[] = array(
                    'name'  => $label,
                    'value' => $display_value
                );
            }
        }
        return $item_data;
    }

    public function add_order_line_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( isset( $values['dfh_data'] ) ) {
            foreach ( $values['dfh_data'] as $key => $value ) {
                $label = isset( $values['dfh_field_labels'][ $key ] ) ? $values['dfh_field_labels'][ $key ] : ucfirst( $key );
                $item->add_meta_data( $label, $value );
            }
        }
    }
}