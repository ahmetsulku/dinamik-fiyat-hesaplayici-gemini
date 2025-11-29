<?php
if (!defined('ABSPATH')) exit;

class DFH_Admin {
    
    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'assets'));
        add_action('add_meta_boxes', array($this, 'meta_boxes'));
        add_action('save_post', array($this, 'save'), 10, 2);
    }

    public function assets($hook) {
        global $post, $pagenow;
        if (!in_array($pagenow, array('post.php', 'post-new.php'))) return;
        
        $type = $post ? $post->post_type : (isset($_GET['post_type']) ? $_GET['post_type'] : '');
        if (!in_array($type, array('dfh_form', 'dfh_rule'))) return;

        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_style('dfh-admin', DFH_URL . 'assets/css/admin.css', array(), DFH_VERSION);
        wp_enqueue_script('dfh-admin', DFH_URL . 'assets/js/admin.js', array('jquery', 'jquery-ui-sortable'), DFH_VERSION, true);

        if ($type === 'dfh_rule') {
            $settings = wp_enqueue_code_editor(array('type' => 'text/javascript'));
            if ($settings) {
                wp_add_inline_script('code-editor', 'jQuery(function($){
                    if($("#dfh_formula").length && wp.codeEditor){
                        wp.codeEditor.initialize("dfh_formula", '.wp_json_encode($settings).');
                    }
                });');
            }
        }
    }

    public function meta_boxes() {
        add_meta_box('dfh_form_builder', 'Form Alanları', array($this, 'render_builder'), 'dfh_form', 'normal', 'high');
        add_meta_box('dfh_form_settings', 'Form Ayarları', array($this, 'render_form_settings'), 'dfh_form', 'side', 'default');
        add_meta_box('dfh_rule_settings', 'Kural Ayarları', array($this, 'render_rule_settings'), 'dfh_rule', 'normal', 'high');
        add_meta_box('dfh_formula_box', '📐 Fiyat Formülü', array($this, 'render_formula'), 'dfh_rule', 'normal', 'high');
    }

    public function render_form_settings($post) {
        $settings = get_post_meta($post->ID, '_dfh_form_settings', true) ?: array();
        $label_pos = isset($settings['label_position']) ? $settings['label_position'] : 'top';
        ?>
        <p>
            <label><strong>Varsayılan Label Pozisyonu</strong></label><br>
            <select name="dfh_form_settings[label_position]" class="widefat">
                <option value="top" <?php selected($label_pos, 'top'); ?>>Üstte</option>
                <option value="left" <?php selected($label_pos, 'left'); ?>>Solda</option>
                <option value="hidden" <?php selected($label_pos, 'hidden'); ?>>Gizli</option>
            </select>
        </p>
        <?php
    }

    public function render_builder($post) {
        wp_nonce_field('dfh_save', 'dfh_nonce');
        $fields = get_post_meta($post->ID, '_dfh_fields', true) ?: array();
        ?>
        <div id="dfh-builder">
            <div class="dfh-toolbar">
                <span>Alan Ekle:</span>
                <button type="button" class="button dfh-add" data-type="number">🔢 Sayı</button>
                <button type="button" class="button dfh-add" data-type="text">📝 Text</button>
                <button type="button" class="button dfh-add" data-type="select">📋 Select</button>
                <button type="button" class="button dfh-add" data-type="radio">⚪ Radio</button>
                <button type="button" class="button dfh-add" data-type="radio_button">🔘 Button Radio</button>
                <button type="button" class="button dfh-add" data-type="image_radio">🖼️ Image Radio</button>
                <button type="button" class="button dfh-add" data-type="checkbox">☑️ Checkbox</button>
                <button type="button" class="button dfh-add" data-type="textarea">📄 Textarea</button>
            </div>
            <div id="dfh-fields">
                <?php foreach ($fields as $i => $f) $this->render_field_row($i, $f); ?>
            </div>
            <p id="dfh-empty" <?php if(!empty($fields)) echo 'style="display:none"'; ?>>Henüz alan yok.</p>
        </div>
        <?php
    }

    private function render_field_row($i, $f) {
        $type = $f['type'] ?? 'text';
        $label = $f['label'] ?? '';
        $name = $f['name'] ?? 'field_'.$i;
        $required = !empty($f['required']);
        $tooltip = $f['tooltip'] ?? '';
        $options = $f['options'] ?? '';
        $threshold = $f['threshold'] ?? '';
        $width = $f['width'] ?? '6/12';
        $label_pos = $f['label_position'] ?? 'default';

        $type_labels = array(
            'number' => '🔢 Sayı',
            'text' => '📝 Text',
            'select' => '📋 Select',
            'radio' => '⚪ Radio',
            'radio_button' => '🔘 Button Radio',
            'image_radio' => '🖼️ Image Radio',
            'checkbox' => '☑️ Checkbox',
            'textarea' => '📄 Textarea'
        );
        ?>
        <div class="dfh-field" data-index="<?php echo $i; ?>">
            <div class="dfh-field-header">
                <span class="dashicons dashicons-move dfh-handle"></span>
                <span class="dfh-title"><?php echo esc_html($label ?: 'Yeni Alan'); ?></span>
                <code>fields.<?php echo esc_html($name); ?></code>
                <span class="dfh-type"><?php echo $type_labels[$type] ?? $type; ?></span>
                <button type="button" class="dfh-toggle">▼</button>
                <button type="button" class="dfh-duplicate" title="Kopyala">⧉</button>
                <button type="button" class="dfh-remove">✕</button>
            </div>
            <div class="dfh-field-body">
                <input type="hidden" name="dfh_fields[<?php echo $i; ?>][type]" value="<?php echo esc_attr($type); ?>">
                
                <div class="dfh-row">
                    <div class="dfh-col">
                        <label>Etiket</label>
                        <input type="text" name="dfh_fields[<?php echo $i; ?>][label]" value="<?php echo esc_attr($label); ?>" class="widefat dfh-label-input">
                    </div>
                    <div class="dfh-col">
                        <label>Alan Adı</label>
                        <input type="text" name="dfh_fields[<?php echo $i; ?>][name]" value="<?php echo esc_attr($name); ?>" class="widefat dfh-name-input">
                        <small>Formülde: <code>fields.<?php echo esc_html($name); ?></code></small>
                    </div>
                </div>

                <div class="dfh-row">
                    <div class="dfh-col">
                        <label>Genişlik</label>
                        <select name="dfh_fields[<?php echo $i; ?>][width]" class="widefat">
                            <option value="12/12" <?php selected($width, '12/12'); ?>>Tam (12/12)</option>
                            <option value="6/12" <?php selected($width, '6/12'); ?>>Yarım (6/12)</option>
                            <option value="4/12" <?php selected($width, '4/12'); ?>>1/3 (4/12)</option>
                            <option value="3/12" <?php selected($width, '3/12'); ?>>1/4 (3/12)</option>
                        </select>
                    </div>
                    <div class="dfh-col">
                        <label>Label Pozisyonu</label>
                        <select name="dfh_fields[<?php echo $i; ?>][label_position]" class="widefat">
                            <option value="default" <?php selected($label_pos, 'default'); ?>>Varsayılan</option>
                            <option value="top" <?php selected($label_pos, 'top'); ?>>Üstte</option>
                            <option value="left" <?php selected($label_pos, 'left'); ?>>Solda</option>
                            <option value="hidden" <?php selected($label_pos, 'hidden'); ?>>Gizli</option>
                        </select>
                    </div>
                </div>

                <div class="dfh-row">
                    <div class="dfh-col">
                        <label>
                            <input type="checkbox" name="dfh_fields[<?php echo $i; ?>][required]" value="1" <?php checked($required); ?>>
                            Zorunlu Alan
                        </label>
                    </div>
                    <div class="dfh-col">
                        <label>Tooltip</label>
                        <input type="text" name="dfh_fields[<?php echo $i; ?>][tooltip]" value="<?php echo esc_attr($tooltip); ?>" class="widefat" placeholder="Yardım metni...">
                    </div>
                </div>

                <?php if (in_array($type, array('select', 'radio', 'radio_button'))): ?>
                <div class="dfh-options-section">
                    <label><strong>Seçenekler</strong></label>
                    <p class="description">Her satıra: <code>değer|Etiket|Subtitle (opsiyonel)</code></p>
                    <textarea name="dfh_fields[<?php echo $i; ?>][options]" rows="5" class="widefat"><?php echo esc_textarea($options); ?></textarea>
                </div>
                <?php endif; ?>

                <?php if ($type === 'image_radio'): ?>
                <div class="dfh-options-section">
                    <label><strong>Görsel Seçenekler</strong></label>
                    <div class="dfh-image-options" data-index="<?php echo $i; ?>">
                        <?php 
                        $img_options = !empty($options) ? json_decode($options, true) : array();
                        if (!empty($img_options)):
                            foreach ($img_options as $oi => $opt): ?>
                            <div class="dfh-image-option">
                                <input type="hidden" name="dfh_fields[<?php echo $i; ?>][image_options][<?php echo $oi; ?>][value]" value="<?php echo esc_attr($opt['value'] ?? ''); ?>">
                                <input type="hidden" name="dfh_fields[<?php echo $i; ?>][image_options][<?php echo $oi; ?>][image]" value="<?php echo esc_attr($opt['image'] ?? ''); ?>" class="img-url-input">
                                <img src="<?php echo esc_url($opt['image'] ?? ''); ?>" class="img-preview" style="<?php echo empty($opt['image']) ? 'display:none;' : ''; ?>">
                                <button type="button" class="button dfh-select-image">Görsel Seç</button>
                                <input type="text" name="dfh_fields[<?php echo $i; ?>][image_options][<?php echo $oi; ?>][label]" value="<?php echo esc_attr($opt['label'] ?? ''); ?>" placeholder="Etiket" class="widefat">
                                <input type="text" name="dfh_fields[<?php echo $i; ?>][image_options][<?php echo $oi; ?>][subtitle]" value="<?php echo esc_attr($opt['subtitle'] ?? ''); ?>" placeholder="Subtitle (opsiyonel)" class="widefat">
                                <button type="button" class="dfh-remove-option">✕</button>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <button type="button" class="button dfh-add-image-option" data-index="<?php echo $i; ?>">+ Seçenek Ekle</button>
                </div>
                <?php endif; ?>

                <?php if ($type === 'number'): ?>
                <div class="dfh-threshold-section">
                    <label><strong>⚠️ Teklif Baremi</strong></label>
                    <input type="number" name="dfh_fields[<?php echo $i; ?>][threshold]" value="<?php echo esc_attr($threshold); ?>" class="widefat" placeholder="Bu değer aşılınca teklif modu aktif">
                    <p class="description">Bu değer aşıldığında "Sepete Ekle" yerine "Teklif Al" butonu görünür.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function render_rule_settings($post) {
        wp_nonce_field('dfh_save', 'dfh_nonce');
        $form_id = get_post_meta($post->ID, '_dfh_form_id', true);
        $product_ids = get_post_meta($post->ID, '_dfh_product_ids', true);
        $forms = get_posts(array('post_type' => 'dfh_form', 'numberposts' => -1, 'post_status' => 'publish'));
        ?>
        <table class="form-table">
            <tr>
                <th>Form Şablonu</th>
                <td>
                    <select name="dfh_form_id" class="widefat" id="dfh-form-select">
                        <option value="">Seçin...</option>
                        <?php foreach ($forms as $f): ?>
                        <option value="<?php echo $f->ID; ?>" <?php selected($form_id, $f->ID); ?>><?php echo esc_html($f->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th>Ürün ID'leri</th>
                <td>
                    <input type="text" name="dfh_product_ids" value="<?php echo esc_attr($product_ids); ?>" class="widefat" placeholder="123, 456, 789">
                    <p class="description">Virgülle ayırın. Bu ürünlerde form görünecek.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function render_formula($post) {
        $formula = get_post_meta($post->ID, '_dfh_formula', true);
        if (empty($formula)) {
            $formula = "// Örnek formül\nvar en = fields.en || 0;\nvar boy = fields.boy || 0;\nvar alan = (en / 100) * (boy / 100);\nvar fiyat = alan * 150 * quantity;\n\nif (fiyat < 50) fiyat = 50;\n\nfiyat;";
        }

        $form_id = get_post_meta($post->ID, '_dfh_form_id', true);
        $fields = $form_id ? get_post_meta($form_id, '_dfh_fields', true) : array();
        ?>
        <div class="dfh-formula-help">
            <strong>📌 Değişkenler:</strong>
            <code>fields.alan_adi</code> → Form değeri &nbsp;|&nbsp;
            <code>quantity</code> → Adet &nbsp;|&nbsp;
            <code>product_price</code> → Ürün fiyatı
            
            <?php if (!empty($fields)): ?>
            <br><br><strong>📋 Form Alanları:</strong><br>
            <?php foreach ($fields as $f): if(!empty($f['name'])): ?>
            <code>fields.<?php echo esc_html($f['name']); ?></code> = <?php echo esc_html($f['label'] ?? $f['name']); ?> &nbsp;
            <?php endif; endforeach; ?>
            <?php endif; ?>
        </div>
        <textarea name="dfh_formula" id="dfh_formula" rows="18" class="widefat" style="font-family:monospace;"><?php echo esc_textarea($formula); ?></textarea>
        <p class="description">Son satır sonucu döndürür. <code>return</code> yazmayın, sadece değişken adı yazın.</p>
        <?php
    }

    public function save($post_id, $post) {
        if (!isset($_POST['dfh_nonce']) || !wp_verify_nonce($_POST['dfh_nonce'], 'dfh_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if ($post->post_type === 'dfh_form') {
            // Form ayarları
            if (isset($_POST['dfh_form_settings'])) {
                update_post_meta($post_id, '_dfh_form_settings', $_POST['dfh_form_settings']);
            }

            // Alanlar
            $fields = isset($_POST['dfh_fields']) ? $_POST['dfh_fields'] : array();
            foreach ($fields as &$f) {
                // Image radio için JSON'a çevir
                if ($f['type'] === 'image_radio' && isset($f['image_options'])) {
                    $f['options'] = json_encode(array_values($f['image_options']));
                    unset($f['image_options']);
                }
            }
            update_post_meta($post_id, '_dfh_fields', $fields);
        }

        if ($post->post_type === 'dfh_rule') {
            update_post_meta($post_id, '_dfh_form_id', absint($_POST['dfh_form_id'] ?? 0));
            update_post_meta($post_id, '_dfh_product_ids', sanitize_text_field($_POST['dfh_product_ids'] ?? ''));
            if (isset($_POST['dfh_formula'])) {
                update_post_meta($post_id, '_dfh_formula', wp_unslash($_POST['dfh_formula']));
            }
        }
    }
}
