jQuery(document).ready(function ($) {
  var container = $("#dfh-fields-container");

  // Field index tracking
  var fieldIndex = 0;
  container.find(".dfh-field-item").each(function () {
    var idx = parseInt($(this).data("index"));
    if (idx > fieldIndex) fieldIndex = idx;
  });

  // Sortable (drag & drop)
  container.sortable({
    handle: ".dfh-handle",
    placeholder: "ui-state-highlight",
    axis: "y",
    tolerance: "pointer",
    opacity: 0.8,
  });

  // ============================================
  // FIELD TEMPLATE OLUŞTURMA
  // ============================================
  function getFieldTemplate(
    index,
    type,
    label,
    name,
    width,
    required,
    tooltip,
    options,
    threshold,
    placeholder,
    defaultVal
  ) {
    options = options || "";
    threshold = threshold || "";
    placeholder = placeholder || "";
    defaultVal = defaultVal || "";

    var extraHtml = "";

    // Seçenekler alanı (select, radio, image_radio)
    if (type === "select" || type === "radio" || type === "image_radio") {
      var formatInfo =
        type === "image_radio"
          ? "Format: değer|Label|resim_url (Her satıra bir seçenek)"
          : "Format: değer|Label (Her satıra bir seçenek)";

      extraHtml += `
                <div class="dfh-row">
                    <div class="dfh-col">
                        <label>Seçenekler</label>
                        <textarea class="widefat" rows="5" name="dfh_fields[${index}][options]">${options}</textarea>
                        <p class="description">${formatInfo}</p>
                    </div>
                </div>`;
    }

    // Dosya alanı
    if (type === "file") {
      extraHtml += `
                <div class="dfh-row">
                    <div class="dfh-col">
                        <label>İzin Verilen Dosya Uzantıları</label>
                        <input type="text" class="widefat" name="dfh_fields[${index}][options]" value="${options}" placeholder="jpg, png, pdf">
                        <p class="description">Virgülle ayırarak yazın</p>
                    </div>
                </div>`;
    }

    // Number için Threshold (Barem)
    if (type === "number") {
      extraHtml += `
                <div class="dfh-row" style="background: #fff3cd; padding: 15px; border-radius: 4px; border-left: 4px solid #ffc107;">
                    <div class="dfh-col">
                        <label><strong>Teklif Baremi (Opsiyonel)</strong></label>
                        <input type="number" class="widefat" name="dfh_fields[${index}][threshold]" value="${threshold}" placeholder="Örn: 1000" step="1">
                        <p class="description">
                            <span class="dashicons dashicons-info"></span>
                            Bu değer aşıldığında "Sepete Ekle" butonu gizlenir ve "Teklif Al" butonu görünür.
                        </p>
                    </div>
                </div>`;
    }

    return `
            <div class="dfh-field-item" data-index="${index}">
                <div class="dfh-field-header">
                    <span class="dashicons dashicons-move dfh-handle"></span>
                    <strong class="dfh-field-title">${label}</strong>
                    <span class="dfh-type-badge">${type}</span>
                    <code class="dfh-var-preview">$fields['<span class="var-name">${name}</span>']</code>
                    
                    <div class="dfh-actions">
                        <button type="button" class="button-link dfh-duplicate-field" title="Kopyala">
                            <span class="dashicons dashicons-admin-page"></span>
                        </button>
                        <button type="button" class="button-link dfh-remove-field" style="color: #b32d2e;" title="Sil">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                        <button type="button" class="button-link dfh-toggle-field" title="Düzenle">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                    </div>
                </div>
                <div class="dfh-field-body">
                    <input type="hidden" name="dfh_fields[${index}][type]" value="${type}">
                    
                    <div class="dfh-row">
                        <div class="dfh-col">
                            <label>Label (Görünen İsim)</label>
                            <input type="text" class="widefat dfh-input-label" name="dfh_fields[${index}][label]" value="${label}">
                        </div>
                        <div class="dfh-col">
                            <label>ID / Name (Değişken Adı)</label>
                            <input type="text" class="widefat dfh-input-name" name="dfh_fields[${index}][name]" value="${name}">
                            <p class="description">PHP kodunda kullanacağınız değişken adı</p>
                        </div>
                    </div>

                    <div class="dfh-row">
                        <div class="dfh-col">
                            <label>Placeholder</label>
                            <input type="text" class="widefat" name="dfh_fields[${index}][placeholder]" value="${placeholder}">
                        </div>
                        <div class="dfh-col">
                            <label>Varsayılan Değer</label>
                            <input type="text" class="widefat" name="dfh_fields[${index}][default]" value="${defaultVal}">
                        </div>
                    </div>

                    <div class="dfh-row">
                        <div class="dfh-col">
                            <label>Genişlik (Grid Sistemi)</label>
                            <select class="widefat" name="dfh_fields[${index}][width]">
                                <option value="12/12" ${
                                  width === "12/12" ? "selected" : ""
                                }>12/12 (Tam genişlik)</option>
                                <option value="6/12" ${
                                  width === "6/12" ? "selected" : ""
                                }>6/12 (Yarım)</option>
                                <option value="4/12" ${
                                  width === "4/12" ? "selected" : ""
                                }>4/12 (1/3)</option>
                                <option value="3/12" ${
                                  width === "3/12" ? "selected" : ""
                                }>3/12 (1/4)</option>
                                <option value="2/12" ${
                                  width === "2/12" ? "selected" : ""
                                }>2/12 (1/6)</option>
                                <option value="1/12" ${
                                  width === "1/12" ? "selected" : ""
                                }>1/12 (1/12)</option>
                            </select>
                        </div>
                        <div class="dfh-col">
                            <label>Zorunlu Alan?</label>
                            <select class="widefat" name="dfh_fields[${index}][required]">
                                <option value="no" ${
                                  required === "no" ? "selected" : ""
                                }>Hayır</option>
                                <option value="yes" ${
                                  required === "yes" ? "selected" : ""
                                }>Evet</option>
                            </select>
                        </div>
                    </div>

                    <div class="dfh-row">
                        <div class="dfh-col">
                            <label>Açıklama / Tooltip İçeriği</label>
                            <textarea class="widefat" rows="2" name="dfh_fields[${index}][tooltip]">${tooltip}</textarea>
                            <p class="description">Bu metin (?) ikonuna tıklandığında gösterilecek</p>
                        </div>
                    </div>

                    ${extraHtml}
                </div>
            </div>
        `;
  }

  // ============================================
  // YENİ ALAN EKLE
  // ============================================
  $(".dfh-add-field").on("click", function () {
    var type = $(this).data("type");
    fieldIndex++;
    var name = "field_" + fieldIndex;
    var typeLabels = {
      text: "Text Alanı",
      number: "Sayı Alanı",
      textarea: "Çok Satırlı",
      select: "Açılır Liste",
      radio: "Radio Buton",
      checkbox: "Checkbox",
      image_radio: "Görselli Seçim",
      file: "Dosya Yükleme",
    };

    var label = "Yeni " + (typeLabels[type] || type);

    var html = getFieldTemplate(
      fieldIndex,
      type,
      label,
      name,
      "12/12",
      "no",
      "",
      "",
      "",
      "",
      ""
    );
    container.append(html);
    $(".dfh-empty-placeholder").addClass("hidden");

    // Yeni eklenen alanı aç
    container.find(".dfh-field-item:last .dfh-field-body").slideDown();
  });

  // ============================================
  // ALANI KOPYALA
  // ============================================
  $(document).on("click", ".dfh-duplicate-field", function (e) {
    e.preventDefault();

    var item = $(this).closest(".dfh-field-item");
    var type = item.find('input[name*="[type]"]').val();
    var label = item.find(".dfh-input-label").val() + " (Kopya)";

    fieldIndex++;
    var name = item.find(".dfh-input-name").val() + "_" + fieldIndex;
    var width = item.find('select[name*="[width]"]').val();
    var required = item.find('select[name*="[required]"]').val();
    var tooltip = item.find('textarea[name*="[tooltip]"]').val();
    var placeholder = item.find('input[name*="[placeholder]"]').val() || "";
    var defaultVal = item.find('input[name*="[default]"]').val() || "";

    var options =
      item.find('textarea[name*="[options]"]').val() ||
      item.find('input[name*="[options]"]').val() ||
      "";
    var threshold = item.find('input[name*="[threshold]"]').val() || "";

    var html = getFieldTemplate(
      fieldIndex,
      type,
      label,
      name,
      width,
      required,
      tooltip,
      options,
      threshold,
      placeholder,
      defaultVal
    );
    item.after(html);
  });

  // ============================================
  // ALANI AÇ/KAPAT
  // ============================================
  $(document).on("click", ".dfh-toggle-field", function () {
    $(this).closest(".dfh-field-item").find(".dfh-field-body").slideToggle();
  });

  // ============================================
  // ALANI SİL
  // ============================================
  $(document).on("click", ".dfh-remove-field", function () {
    if (confirm(dfhAdmin.i18n.confirm_delete)) {
      $(this)
        .closest(".dfh-field-item")
        .fadeOut(300, function () {
          $(this).remove();

          // Hiç alan kalmadıysa placeholder göster
          if (container.find(".dfh-field-item").length === 0) {
            $(".dfh-empty-placeholder").removeClass("hidden");
          }
        });
    }
  });

  // ============================================
  // CANLI GÜNCELLEME (Name ve Label değişince)
  // ============================================
  $(document).on("keyup", ".dfh-input-name", function () {
    var newName = $(this).val();
    $(this).closest(".dfh-field-item").find(".var-name").text(newName);
  });

  $(document).on("keyup", ".dfh-input-label", function () {
    var newLabel = $(this).val();
    $(this).closest(".dfh-field-item").find(".dfh-field-title").text(newLabel);
  });

  // ============================================
  // FORM KAYDETME ÖNCESİ KONTROL
  // ============================================
  $("form#post").on("submit", function (e) {
    var postType = $("#post_type").val();

    if (postType === "dfh_rule") {
      var selectedForm = $('select[name="dfh_selected_form"]').val();
      var productIds = $('input[name="dfh_product_ids"]').val();

      if (!selectedForm) {
        alert("Lütfen bir form şablonu seçin!");
        e.preventDefault();
        return false;
      }

      if (!productIds || productIds.trim() === "") {
        if (
          !confirm(
            "Ürün ID'leri belirtmediniz. Bu kural hiçbir üründe görünmeyecek. Devam etmek istiyor musunuz?"
          )
        ) {
          e.preventDefault();
          return false;
        }
      }
    }
  });

  console.log("DFH Admin JS yüklendi");

  // ============================================
  // CODE EDITOR BAŞLATMA (Rule sayfasında)
  // ============================================
  if (typeof wp !== "undefined" && wp.codeEditor && $("#dfh_php_code").length) {
    var editorSettings = wp.codeEditor.defaultSettings
      ? _.clone(wp.codeEditor.defaultSettings)
      : {};
    editorSettings.codemirror = _.extend({}, editorSettings.codemirror, {
      indentUnit: 4,
      tabSize: 4,
      mode: "application/x-httpd-php",
      lineNumbers: true,
      lineWrapping: true,
      styleActiveLine: true,
      continueComments: true,
      extraKeys: {
        "Ctrl-Space": "autocomplete",
        "Ctrl-/": "toggleComment",
        "Cmd-/": "toggleComment",
      },
    });

    var editor = wp.codeEditor.initialize("dfh_php_code", editorSettings);
    console.log("DFH: Code Editor başlatıldı");
  }
});
