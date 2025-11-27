<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DFH_Frontend {
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        
        // Shortcode: [dfh_form]
        add_shortcode( 'dfh_form', array( $this, 'render_shortcode' ) );

        // Varsayılan kanca (Otomatik ekleme)
        add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_product_form' ), 10 );
    }

    public function enqueue_scripts() {
        if ( is_product() ) {
            wp_enqueue_style( 'dfh-frontend-css', DFH_URL . 'assets/css/frontend.css', array(), '1.0' );
            // 'dfhAjax' nesnesini JS'e aktarmak için localize_script kullanacağız, aşağıda tanımlı.
            wp_enqueue_script( 'dfh-frontend-js', DFH_URL . 'assets/js/frontend.js', array( 'jquery' ), '1.0', true );
        }
    }

    private function get_rule_for_product( $product_id ) {
        $rules = get_posts(array( 'post_type' => 'dfh_rule', 'numberposts' => -1 ));
        foreach ( $rules as $rule ) {
            $product_ids_str = get_post_meta( $rule->ID, '_dfh_product_ids', true );
            if ( ! empty( $product_ids_str ) ) {
                $product_ids = array_map( 'trim', explode( ',', $product_ids_str ) );
                if ( in_array( (string)$product_id, $product_ids ) ) {
                    return $rule;
                }
            }
        }
        return false;
    }

    // Shortcode işleyici
    public function render_shortcode() {
        ob_start();
        $this->render_product_form();
        return ob_get_clean();
    }

    public function render_product_form() {
        // Eğer form sayfada zaten basıldıysa tekrar basma
        if ( defined( 'DFH_FORM_RENDERED' ) ) return;

        global $product;
        if ( ! $product ) {
            $product_id = get_the_ID();
        } else {
            $product_id = $product->get_id();
        }

        $rule = $this->get_rule_for_product( $product_id );
        if ( ! $rule ) return;

        $form_id = get_post_meta( $rule->ID, '_dfh_selected_form', true );
        if ( ! $form_id ) return;

        // --- KRİTİK: JS için Veri Aktarımı ---
        // Bu kısım olmazsa Fiyat Hesaplama çalışmaz.
        wp_localize_script( 'dfh-frontend-js', 'dfhAjax', array(
            'ajax_url'    => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'dfh_calc' ),
            'product_id'  => $product_id,
            'i18n_label'  => __( 'Hesaplanan Fiyat', 'din-fiyat-gemini' ),
        ) );

        $fields = get_post_meta( $form_id, '_dfh_form_fields', true );
        if ( ! is_array( $fields ) || empty( $fields ) ) return;

        // Form basıldı işaretini koy
        define( 'DFH_FORM_RENDERED', true );

        echo '<div class="dfh-product-form-wrapper" id="dfh-form">';
        echo '<input type="hidden" name="dfh_rule_id" value="' . esc_attr($rule->ID) . '">';
        
        // TEKLİF AL KUTUSU (Başlangıçta gizli)
        echo '<div id="dfh-offer-box" style="display:none; text-align:center; padding:20px; background:#f9f9f9; border:1px solid #ddd; margin-bottom:20px; border-radius:5px;">';
        echo '<p style="color:#d63638; font-weight:bold; margin-bottom:15px; font-size:16px;">Bu miktar için özel fiyat teklifi almalısınız.</p>';
        // mailto linkine ürün ID'sini ekliyoruz
        echo '<a href="mailto:info@siteadi.com?subject=Teklif Talebi: Ürün ID ' . $product_id . '" class="button alt" style="background:#007cba; color:#fff; padding:10px 20px;">Teklif İste</a>';
        echo '</div>';

        // CANLI FİYAT KUTUSU
        echo '<div class="dfh-live-price" id="dfh-live-price" style="font-weight:bold; font-size:18px; margin-bottom:15px; color:#333;"></div>';
        
        echo '<div class="dfh-grid">';
        foreach ( $fields as $field ) {
            $this->render_field( $field );
        }
        echo '</div>'; 
        echo '</div>'; 
    }

    private function render_field( $field ) {
        $type = isset($field['type']) ? $field['type'] : 'text';
        $label = isset($field['label']) ? $field['label'] : '';
        $name = isset($field['name']) ? $field['name'] : '';
        $width = isset($field['width']) ? $field['width'] : '12/12';
        $required = isset($field['required']) && $field['required'] === 'yes';
        $tooltip = isset($field['tooltip']) ? $field['tooltip'] : '';
        $options_raw = isset($field['options']) ? $field['options'] : '';
        $threshold = isset($field['threshold']) ? $field['threshold'] : ''; // Barem verisi
        
        $grid_class = 'dfh-col-' . str_replace('/', '-', $width);
        $input_name = 'dfh_inputs[' . $name . ']';

        echo '<div class="dfh-field-container ' . esc_attr($grid_class) . ' dfh-type-' . esc_attr($type) . '">';
        
        if ( $label ) {
            echo '<label class="dfh-label">' . esc_html($label);
            if ( $required ) echo ' <span class="required">*</span>';
            if ( $tooltip ) {
                echo ' <span class="dfh-tooltip-icon" data-tooltip="' . esc_attr($tooltip) . '">?</span>';
            }
            echo '</label>';
        }

        switch ( $type ) {
            case 'text':
            case 'number':
                $input_type = ($type === 'number') ? 'number' : 'text';
                $step = ($type === 'number') ? 'step="any"' : '';
                
                // --- KRİTİK: DATA-THRESHOLD EKLENİYOR ---
                // JS bu özelliği okuyarak işlem yapar.
                $thresh_attr = ($type === 'number' && $threshold !== '') ? 'data-threshold="' . esc_attr($threshold) . '"' : '';
                
                echo '<input type="' . $input_type . '" name="' . esc_attr($input_name) . '" class="dfh-input" ' . ($required ? 'required' : '') . ' ' . $step . ' ' . $thresh_attr . '>';
                break;

            case 'textarea':
                echo '<textarea name="' . esc_attr($input_name) . '" class="dfh-input" rows="3" ' . ($required ? 'required' : '') . '></textarea>';
                break;

            case 'file':
                $allowed = !empty($options_raw) ? $options_raw : 'jpg, png, pdf';
                echo '<input type="file" name="dfh_file_' . esc_attr($name) . '" class="dfh-input dfh-file-input" accept=".' . str_replace(',', ',.', str_replace(' ', '', $allowed)) . '" ' . ($required ? 'required' : '') . '>';
                echo '<small class="dfh-file-help">İzin verilenler: ' . esc_html($allowed) . '</small>';
                break;

            case 'select':
                $options = $this->parse_options($options_raw);
                echo '<select name="' . esc_attr($input_name) . '" class="dfh-input" ' . ($required ? 'required' : '') . '>';
                echo '<option value="">Seçiniz...</option>';
                foreach($options as $opt) {
                    echo '<option value="' . esc_attr($opt['value']) . '">' . esc_html($opt['label']) . '</option>';
                }
                echo '</select>';
                break;

            case 'radio':
                $options = $this->parse_options($options_raw);
                echo '<div class="dfh-radio-group">';
                foreach($options as $opt) {
                    echo '<label class="dfh-radio-label">';
                    echo '<input type="radio" name="' . esc_attr($input_name) . '" value="' . esc_attr($opt['value']) . '" ' . ($required ? 'required' : '') . '> ';
                    echo '<span>' . esc_html($opt['label']) . '</span>';
                    echo '</label>';
                }
                echo '</div>';
                break;
            
            case 'checkbox':
                echo '<label class="dfh-checkbox-label">';
                echo '<input type="checkbox" name="' . esc_attr($input_name) . '" value="1" ' . ($required ? 'required' : '') . '> ';
                echo '<span>' . esc_html($label) . '</span>';
                echo '</label>';
                break;

            case 'image_radio':
                $options = $this->parse_options($options_raw);
                echo '<div class="dfh-image-radio-group">';
                foreach($options as $opt) {
                    $img_src = isset($opt['image']) ? $opt['image'] : 'https://placehold.co/100x100?text=No+Img';
                    echo '<label class="dfh-image-option">';
                    echo '<input type="radio" name="' . esc_attr($input_name) . '" value="' . esc_attr($opt['value']) . '" ' . ($required ? 'required' : '') . '>';
                    echo '<img src="' . esc_url($img_src) . '" alt="' . esc_attr($opt['label']) . '">';
                    echo '<span class="dfh-opt-name">' . esc_html($opt['label']) . '</span>';
                    echo '</label>';
                }
                echo '</div>';
                break;
        }

        echo '</div>'; 
    }

    private function parse_options($raw) {
        $lines = explode("\n", $raw);
        $options = [];
        foreach($lines as $line) {
            $parts = explode('|', $line);
            if(count($parts) >= 1) {
                $opt = [
                    'value' => trim($parts[0]),
                    'label' => isset($parts[1]) ? trim($parts[1]) : trim($parts[0])
                ];
                if(isset($parts[2])) {
                    $opt['image'] = trim($parts[2]);
                }
                if(!empty($opt['value'])) $options[] = $opt;
            }
        }
        return $options;
    }
}