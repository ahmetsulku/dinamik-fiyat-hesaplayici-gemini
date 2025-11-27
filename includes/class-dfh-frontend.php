<?php
/**
 * Frontend işlemleri ve form render
 */

if (!defined('ABSPATH')) {
    exit;
}

class DFH_Frontend {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('woocommerce_before_add_to_cart_button', array($this, 'auto_render_form'), 10);
    }

    public function enqueue_scripts() {
        if (!is_product()) {
            return;
        }

        wp_enqueue_style(
            'dfh-frontend-css',
            DFH_URL . 'assets/css/frontend.css',
            array(),
            DFH_VERSION
        );

        wp_enqueue_script(
            'dfh-frontend-js',
            DFH_URL . 'assets/js/frontend.js',
            array('jquery'),
            DFH_VERSION,
            true
        );
    }

    public function auto_render_form() {
        if (defined('DFH_FORM_RENDERED')) {
            return;
        }

        global $product;
        if (!$product) {
            return;
        }

        $product_id = $product->get_id();
        $rule = $this->get_rule_for_product($product_id);
        
        if (!$rule) {
            return;
        }

        $form_id = get_post_meta($rule->ID, '_dfh_selected_form', true);
        
        if (!$form_id) {
            return;
        }

        $this->render_form($form_id, $rule->ID, $product_id);
    }

    private function render_form($form_id, $rule_id, $product_id) {
        // Form sadece bir kez render edilsin
        if (defined('DFH_FORM_RENDERED')) {
            return;
        }
        define('DFH_FORM_RENDERED', true);

        $fields = get_post_meta($form_id, '_dfh_form_fields', true);
        
        if (!is_array($fields) || empty($fields)) {
            return;
        }

        // JS için veri aktar
        wp_localize_script('dfh-frontend-js', 'dfhAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dfh_calc'),
            'product_id' => $product_id,
            'rule_id' => $rule_id,
        ));

        ?>
        <div class="dfh-product-form-wrapper" id="dfh-form">
            <input type="hidden" name="dfh_rule_id" value="<?php echo esc_attr($rule_id); ?>">
            
            <!-- Teklif Kutusu (Başlangıçta gizli) -->
            <div id="dfh-offer-box" style="display:none; text-align:center; padding:20px; background:#fff3cd; border:2px solid #ffc107; margin-bottom:20px; border-radius:5px;">
                <p style="color:#856404; font-weight:bold; margin-bottom:15px; font-size:16px;">
                    <span class="dashicons dashicons-info" style="font-size:20px; vertical-align:middle;"></span>
                    <?php _e('Bu miktar için özel fiyat teklifi almanız gerekmektedir.', 'dinamik-fiyat'); ?>
                </p>
                <a href="mailto:<?php echo get_option('admin_email'); ?>?subject=<?php echo urlencode('Teklif Talebi: Ürün #' . $product_id); ?>" 
                   class="button alt" 
                   style="background:#007cba; color:#fff; padding:12px 24px; text-decoration:none; display:inline-block; border-radius:4px;">
                    <span class="dashicons dashicons-email" style="vertical-align:middle;"></span>
                    <?php _e('Teklif İste', 'dinamik-fiyat'); ?>
                </a>
            </div>
            
            <div class="dfh-grid">
                <?php
                foreach ($fields as $field) {
                    $this->render_field($field);
                }
                ?>
            </div>
        </div>
        <?php
    }

    private function render_field($field) {
        $type = isset($field['type']) ? $field['type'] : 'text';
        $label = isset($field['label']) ? $field['label'] : '';
        $name = isset($field['name']) ? $field['name'] : '';
        $width = isset($field['width']) ? $field['width'] : '12/12';
        $required = isset($field['required']) && $field['required'] === 'yes';
        $tooltip = isset($field['tooltip']) ? $field['tooltip'] : '';
        $options_raw = isset($field['options']) ? $field['options'] : '';
        $threshold = isset($field['threshold']) ? $field['threshold'] : '';
        $placeholder = isset($field['placeholder']) ? $field['placeholder'] : '';
        $default_value = isset($field['default']) ? $field['default'] : '';

        $grid_class = 'dfh-col-' . str_replace('/', '-', $width);
        $input_name = 'dfh_inputs[' . esc_attr($name) . ']';
        
        echo '<div class="dfh-field-container ' . esc_attr($grid_class) . ' dfh-type-' . esc_attr($type) . '">';
        
        if ($label) {
            echo '<label class="dfh-label">';
            echo esc_html($label);
            if ($required) {
                echo ' <span class="required">*</span>';
            }
            if ($tooltip) {
                echo ' <span class="dfh-tooltip-icon" data-tooltip="' . esc_attr($tooltip) . '">?</span>';
            }
            echo '</label>';
        }

        switch ($type) {
            case 'text':
                echo '<input type="text" name="' . $input_name . '" class="dfh-input" ';
                if ($placeholder) echo 'placeholder="' . esc_attr($placeholder) . '" ';
                if ($default_value) echo 'value="' . esc_attr($default_value) . '" ';
                if ($required) echo 'required ';
                echo '>';
                break;

            case 'number':
                $threshold_attr = ($threshold !== '') ? 'data-threshold="' . esc_attr($threshold) . '"' : '';
                echo '<input type="number" name="' . $input_name . '" class="dfh-input" step="any" ';
                if ($placeholder) echo 'placeholder="' . esc_attr($placeholder) . '" ';
                if ($default_value) echo 'value="' . esc_attr($default_value) . '" ';
                if ($required) echo 'required ';
                echo $threshold_attr . '>';
                break;

            case 'textarea':
                echo '<textarea name="' . $input_name . '" class="dfh-input" rows="4" ';
                if ($placeholder) echo 'placeholder="' . esc_attr($placeholder) . '" ';
                if ($required) echo 'required';
                echo '>' . esc_textarea($default_value) . '</textarea>';
                break;

            case 'select':
                $options = $this->parse_options($options_raw);
                echo '<select name="' . $input_name . '" class="dfh-input" ';
                if ($required) echo 'required';
                echo '>';
                echo '<option value="">' . __('Seçiniz...', 'dinamik-fiyat') . '</option>';
                foreach ($options as $opt) {
                    $selected = ($default_value == $opt['value']) ? 'selected' : '';
                    echo '<option value="' . esc_attr($opt['value']) . '" ' . $selected . '>' . esc_html($opt['label']) . '</option>';
                }
                echo '</select>';
                break;

            case 'radio':
                $options = $this->parse_options($options_raw);
                echo '<div class="dfh-radio-group">';
                foreach ($options as $opt) {
                    $checked = ($default_value == $opt['value']) ? 'checked' : '';
                    echo '<label class="dfh-radio-label">';
                    echo '<input type="radio" name="' . $input_name . '" value="' . esc_attr($opt['value']) . '" ' . $checked . ' ';
                    if ($required) echo 'required ';
                    echo '> ';
                    echo '<span>' . esc_html($opt['label']) . '</span>';
                    echo '</label>';
                }
                echo '</div>';
                break;

            case 'checkbox':
                $checked = ($default_value) ? 'checked' : '';
                echo '<label class="dfh-checkbox-label">';
                echo '<input type="checkbox" name="' . $input_name . '" value="1" ' . $checked . ' ';
                if ($required) echo 'required ';
                echo '> ';
                echo '<span>' . esc_html($label) . '</span>';
                echo '</label>';
                break;

            case 'image_radio':
                $options = $this->parse_options($options_raw);
                echo '<div class="dfh-image-radio-group">';
                foreach ($options as $opt) {
                    $img_src = isset($opt['image']) && $opt['image'] ? $opt['image'] : 'https://via.placeholder.com/100x100?text=' . urlencode($opt['label']);
                    $checked = ($default_value == $opt['value']) ? 'checked' : '';
                    echo '<label class="dfh-image-option">';
                    echo '<input type="radio" name="' . $input_name . '" value="' . esc_attr($opt['value']) . '" ' . $checked . ' ';
                    if ($required) echo 'required ';
                    echo '>';
                    echo '<img src="' . esc_url($img_src) . '" alt="' . esc_attr($opt['label']) . '" loading="lazy">';
                    echo '<span class="dfh-opt-name">' . esc_html($opt['label']) . '</span>';
                    echo '</label>';
                }
                echo '</div>';
                break;

            case 'file':
                $allowed = !empty($options_raw) ? $options_raw : 'jpg, png, pdf';
                $accept_attr = '.' . str_replace(',', ',.', str_replace(' ', '', $allowed));
                echo '<input type="file" name="dfh_file_' . esc_attr($name) . '" class="dfh-input dfh-file-input" accept="' . esc_attr($accept_attr) . '" ';
                if ($required) echo 'required';
                echo '>';
                echo '<small class="dfh-file-help">' . sprintf(__('İzin verilen: %s', 'dinamik-fiyat'), esc_html($allowed)) . '</small>';
                break;
        }

        echo '</div>';
    }

    private function parse_options($raw) {
        $lines = explode("\n", trim($raw));
        $options = array();
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $parts = explode('|', $line);
            $opt = array(
                'value' => trim($parts[0]),
                'label' => isset($parts[1]) ? trim($parts[1]) : trim($parts[0])
            );

            if (isset($parts[2])) {
                $opt['image'] = trim($parts[2]);
            }

            if (!empty($opt['value'])) {
                $options[] = $opt;
            }
        }

        return $options;
    }

    private function get_rule_for_product($product_id) {
        $rules = get_posts(array(
            'post_type' => 'dfh_rule',
            'numberposts' => -1,
            'post_status' => 'publish'
        ));

        foreach ($rules as $rule) {
            $product_ids_str = get_post_meta($rule->ID, '_dfh_product_ids', true);
            
            if (empty($product_ids_str)) {
                continue;
            }

            $product_ids = array_map('trim', explode(',', $product_ids_str));
            
            if (in_array((string)$product_id, $product_ids, true)) {
                return $rule;
            }
        }

        return false;
    }
}