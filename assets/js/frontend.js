document.addEventListener("DOMContentLoaded", function () {
  var customForm = document.getElementById("dfh-form");
  if (!customForm) return;

  console.log("DFH: Frontend JS aktif");

  // ============================================
  // 1. TOOLTIP SİSTEMİ
  // ============================================
  var tooltipBox = document.createElement("div");
  tooltipBox.id = "dfh-global-tooltip";
  tooltipBox.style.cssText =
    "display:none; position:fixed; z-index:999999; background:#333; color:#fff; padding:10px 14px; border-radius:5px; font-size:13px; max-width:280px; pointer-events:none; box-shadow:0 5px 20px rgba(0,0,0,0.3); line-height:1.5;";
  document.body.appendChild(tooltipBox);

  document.body.addEventListener("mouseover", function (e) {
    if (e.target.classList.contains("dfh-tooltip-icon")) {
      var text = e.target.getAttribute("data-tooltip");
      if (text) {
        tooltipBox.innerHTML = text;
        tooltipBox.style.display = "block";
        updateTooltipPosition(e);
      }
    }
  });

  document.body.addEventListener("mousemove", function (e) {
    if (
      tooltipBox.style.display === "block" &&
      e.target.classList.contains("dfh-tooltip-icon")
    ) {
      updateTooltipPosition(e);
    }
  });

  document.body.addEventListener("mouseout", function (e) {
    if (e.target.classList.contains("dfh-tooltip-icon")) {
      tooltipBox.style.display = "none";
    }
  });

  function updateTooltipPosition(e) {
    var x = e.clientX + 15;
    var y = e.clientY + 15;

    var tooltipWidth = tooltipBox.offsetWidth;
    var tooltipHeight = tooltipBox.offsetHeight;

    if (x + tooltipWidth + 20 > window.innerWidth) {
      x = e.clientX - tooltipWidth - 15;
    }

    if (y + tooltipHeight + 20 > window.innerHeight) {
      y = e.clientY - tooltipHeight - 15;
    }

    tooltipBox.style.left = x + "px";
    tooltipBox.style.top = y + "px";
  }

  // ============================================
  // 2. BAREM KONTROLÜ VE FİYAT GÜNCELLEMESİ
  // ============================================
  var form = document.querySelector("form.cart");
  var oldBtn = document.querySelector(".single_add_to_cart_button");
  var offerBox = document.getElementById("dfh-offer-box");
  var themePrice = document.querySelector(
    ".summary .price, .product-price, p.price"
  );

  var debounceTimer = null;

  function handleInputChange() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function () {
      checkThresholdAndCalculatePrice();
    }, 300);
  }

  function checkThresholdAndCalculatePrice() {
    // 2.1 Threshold (Barem) Kontrolü
    var thresholdExceeded = false;
    var thresholdInputs = document.querySelectorAll(
      "#dfh-form input[data-threshold]"
    );

    thresholdInputs.forEach(function (input) {
      var value = parseFloat(input.value);
      var threshold = parseFloat(input.getAttribute("data-threshold"));

      if (!isNaN(value) && !isNaN(threshold) && value > threshold) {
        thresholdExceeded = true;
      }
    });

    if (thresholdExceeded) {
      // BAREM AŞILDI: Buton ve fiyatı gizle, teklif göster
      if (oldBtn) oldBtn.style.display = "none";
      if (themePrice) {
        themePrice.innerHTML =
          '<span style="color:#856404; font-weight:bold;">Teklif Alınız</span>';
      }
      if (offerBox) offerBox.style.display = "block";
      return;
    } else {
      // NORMAL MOD
      if (oldBtn) oldBtn.style.display = "inline-block";
      if (offerBox) offerBox.style.display = "none";
    }

    // 2.2 Canlı Fiyat Hesaplama (WooCommerce fiyatını güncelle)
    if (!form || typeof dfhAjax === "undefined") {
      return;
    }

    var formData = new FormData(form);
    formData.append("action", "dfh_calculate_price");
    formData.append("nonce", dfhAjax.nonce);
    formData.append("product_id", dfhAjax.product_id);
    formData.append("rule_id", dfhAjax.rule_id);

    var qtyInput = form.querySelector('input[name="quantity"]');
    if (qtyInput) {
      formData.append("quantity", qtyInput.value);
    }

    fetch(dfhAjax.ajax_url, {
      method: "POST",
      body: formData,
      credentials: "same-origin",
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (data) {
        if (data.success && data.data.formatted && themePrice) {
          // WooCommerce'in kendi fiyat alanını güncelle
          themePrice.innerHTML = data.data.formatted;
        }
      })
      .catch(function (error) {
        console.error("DFH: AJAX hatası", error);
      });
  }

  // ============================================
  // 3. INPUT DEĞİŞİKLİKLERİNİ DİNLE
  // ============================================
  customForm.addEventListener("input", function (e) {
    if (e.target.matches("input, select, textarea")) {
      handleInputChange();
    }
  });

  customForm.addEventListener("change", function (e) {
    if (
      e.target.matches('input[type="radio"], input[type="checkbox"], select')
    ) {
      handleInputChange();
    }
  });

  // Quantity input'u da dinle
  if (form) {
    var qtyInput = form.querySelector('input[name="quantity"]');
    if (qtyInput) {
      qtyInput.addEventListener("input", handleInputChange);
      qtyInput.addEventListener("change", handleInputChange);
    }
  }

  // İlk hesaplama
  setTimeout(function () {
    checkThresholdAndCalculatePrice();
  }, 500);

  console.log("DFH: Tüm event listener'lar kuruldu");
});
