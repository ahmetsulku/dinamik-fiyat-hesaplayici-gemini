jQuery(document).ready(function ($) {
  var container = $("#dfh-fields-container");

  var fieldIndex = 0;
  container.find(".dfh-field-item").each(function () {
    var idx = parseInt($(this).data("index"));
    if (idx > fieldIndex) fieldIndex = idx;
  });

  container.sortable({
    handle: ".dfh-handle",
    placeholder: "ui-state-highlight",
    axis: "y",
  });

  // --- HTML Şablon Fonksiyonu ---
  function getFieldTemplate(
    index,
    type,
    label,
    name,
    width,
    required,
    tooltip,
    options,
    threshold
  ) {
    var extraHtml = "";

    if (type === "select" || type === "radio" || type === "image_radio") {
      extraHtml += `
            <div class="dfh-row">
                <div class="dfh-col">
                    <label>Seçenekler (değer | Label)</label>
                    <textarea class="widefat" rows="4" name="dfh_fields[${index}][options]">${options}</textarea>
                </div>
            </div>`;
    }

    if (type === "file") {
      extraHtml += `
            <div class="dfh-row">
                <div class="dfh-col">
                    <label>İzin Verilen Uzantılar</label>
                    <input type="text" class="widefat" name="dfh_fields[${index}][options]" value="${options}" placeholder="jpg, png, pdf">
                </div>
            </div>`;
    }

    // YENİ: Number için Barem Alanı
    if (type === "number") {
      extraHtml += `
            <div class="dfh-row" style="background: #eef5fa; padding: 10px; border-radius: 4px;">
                <div class="dfh-col">
                    <label>Teklif Baremi (Opsiyonel)</label>
                    <input type="number" class="widefat" name="dfh_fields[${index}][threshold]" value="${threshold}" placeholder="Örn: 1000">
                    <p class="description">Bu değer aşılırsa 'Sepete Ekle' gizlenir, 'Teklif Al' butonu çıkar.</p>
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
                        <button type="button" class="button-link dfh-duplicate-field" title="Kopyala"><span class="dashicons dashicons-admin-page"></span></button>
                        <button type="button" class="button-link dfh-remove-field" style="color: #b32d2d;" title="Sil"><span class="dashicons dashicons-trash"></span></button>
                        <button type="button" class="button-link dfh-toggle-field" title="Düzenle"><span class="dashicons dashicons-edit"></span></button>
                    </div>
                </div>
                <div class="dfh-field-body">
                    <input type="hidden" name="dfh_fields[${index}][type]" value="${type}">
                    <div class="dfh-row">
                        <div class="dfh-col">
                            <label>Label</label>
                            <input type="text" class="widefat dfh-input-label" name="dfh_fields[${index}][label]" value="${label}">
                        </div>
                        <div class="dfh-col">
                            <label>ID / Name</label>
                            <input type="text" class="widefat dfh-input-name" name="dfh_fields[${index}][name]" value="${name}">
                        </div>
                    </div>
                    <div class="dfh-row">
                        <div class="dfh-col">
                            <label>Genişlik</label>
                            <select class="widefat" name="dfh_fields[${index}][width]">
                                <option value="12/12" ${
                                  width === "12/12" ? "selected" : ""
                                }>12/12</option>
                                <option value="6/12" ${
                                  width === "6/12" ? "selected" : ""
                                }>6/12</option>
                                <option value="4/12" ${
                                  width === "4/12" ? "selected" : ""
                                }>4/12</option>
                            </select>
                        </div>
                        <div class="dfh-col">
                            <label>Zorunlu?</label>
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
                            <label>Tooltip / Açıklama</label>
                            <textarea class="widefat" rows="2" name="dfh_fields[${index}][tooltip]">${tooltip}</textarea>
                        </div>
                    </div>
                    ${extraHtml}
                </div>
            </div>
        `;
  }

  $(".dfh-add-field").on("click", function () {
    var type = $(this).data("type");
    fieldIndex++;
    var name = "field_" + fieldIndex;
    var html = getFieldTemplate(
      fieldIndex,
      type,
      "Yeni " + type,
      name,
      "12/12",
      "no",
      "",
      "",
      ""
    );
    container.append(html);
    $(".dfh-empty-placeholder").addClass("hidden");
  });

  $(document).on("click", ".dfh-duplicate-field", function (e) {
    e.preventDefault();
    var item = $(this).closest(".dfh-field-item");
    var type = item.find('input[name*="[type]"]').val();
    var label = item.find("input.dfh-input-label").val() + " (Kopya)";
    fieldIndex++;
    var name = item.find("input.dfh-input-name").val() + "_" + fieldIndex;
    var width = item.find('select[name*="[width]"]').val();
    var required = item.find('select[name*="[required]"]').val();
    var tooltip = item.find('textarea[name*="[tooltip]"]').val();

    var options =
      item.find('textarea[name*="[options]"]').val() ||
      item.find('input[name*="[options]"]').val() ||
      "";
    var threshold = item.find('input[name*="[threshold]"]').val() || ""; // Barem verisi

    var html = getFieldTemplate(
      fieldIndex,
      type,
      label,
      name,
      width,
      required,
      tooltip,
      options,
      threshold
    );
    item.after(html);
  });

  $(document).on("click", ".dfh-toggle-field", function () {
    $(this).closest(".dfh-field-item").find(".dfh-field-body").slideToggle();
  });
  $(document).on("click", ".dfh-remove-field", function () {
    if (confirm("Silmek istediğine emin misin?"))
      $(this).closest(".dfh-field-item").remove();
  });
  $(document).on("keyup", ".dfh-input-name", function () {
    $(this).closest(".dfh-field-item").find(".var-name").text($(this).val());
  });
  $(document).on("keyup", ".dfh-input-label", function () {
    $(this)
      .closest(".dfh-field-item")
      .find(".dfh-field-title")
      .text($(this).val());
  });
});
