document.addEventListener("DOMContentLoaded", function () {
  var customForm = document.getElementById("dfh-form");
  if (!customForm) return;

  console.log("DFH: Frontend JS Aktif (Tooltip + Barem + Fiyat)");

  // --- 1. TOOLTIP (Gövdeye Ekleyerek Taşmayı Önle) ---
  var tooltipBox = document.createElement("div");
  tooltipBox.id = "dfh-global-tooltip";
  tooltipBox.style.display = "none";
  tooltipBox.style.position = "fixed";
  tooltipBox.style.zIndex = "2147483647"; // Maksimum Z-Index
  tooltipBox.style.background = "#222";
  tooltipBox.style.color = "#fff";
  tooltipBox.style.padding = "8px 12px";
  tooltipBox.style.borderRadius = "4px";
  tooltipBox.style.fontSize = "13px";
  tooltipBox.style.maxWidth = "250px";
  tooltipBox.style.pointerEvents = "none";
  tooltipBox.style.boxShadow = "0 5px 15px rgba(0,0,0,0.4)";
  tooltipBox.style.lineHeight = "1.4";
  document.body.appendChild(tooltipBox);

  // Olay delegasyonu ile tooltip kontrolü
  document.body.addEventListener("mouseover", function (e) {
    if (e.target.matches(".dfh-tooltip-icon")) {
      var text = e.target.getAttribute("data-tooltip");
      if (text) {
        tooltipBox.innerHTML = text;
        tooltipBox.style.display = "block";
        updateTooltipPos(e);
      }
    }
  });

  document.body.addEventListener("mousemove", function (e) {
    if (tooltipBox.style.display === "block") {
      updateTooltipPos(e);
    }
  });

  document.body.addEventListener("mouseout", function (e) {
    if (e.target.matches(".dfh-tooltip-icon")) {
      tooltipBox.style.display = "none";
    }
  });

  function updateTooltipPos(e) {
    var x = e.clientX + 15;
    var y = e.clientY + 15;
    // Ekrandan taşıyorsa sola/yukarı kaydır
    if (x + 260 > window.innerWidth) x = e.clientX - 270;
    if (y + 50 > window.innerHeight) y = e.clientY - 40;

    tooltipBox.style.left = x + "px";
    tooltipBox.style.top = y + "px";
  }

  // --- 2. BUTON DEĞİŞİMİ VE FORM GÖNDERİMİ ---
  var form = document.querySelector("form.cart");
  var oldBtn = document.querySelector(".single_add_to_cart_button");
  var offerBox = document.getElementById("dfh-offer-box");
  var livePriceBox = document.getElementById("dfh-live-price");
  var themePrice = document.querySelector(
    ".price, .product-price, .summary .price"
  );

  // Butonu temizle ve klonla (AJAX'ı iptal et)
  if (oldBtn) {
    var newBtn = oldBtn.cloneNode(true);
    newBtn.id = "dfh-clean-submit";
    newBtn.classList.remove("ajax_add_to_cart");
    newBtn.type = "button";
    oldBtn.parentNode.replaceChild(newBtn, oldBtn);

    newBtn.addEventListener("click", function (e) {
      e.preventDefault();

      // Validasyon
      var isValid = true;
      var inputs = document.querySelectorAll(
        "#dfh-form input[required], #dfh-form select[required], #dfh-form textarea[required]"
      );

      inputs.forEach(function (input) {
        if (!input.offsetParent) return; // Görünür değilse atla

        if (input.type === "radio" || input.type === "checkbox") {
          var name = input.name;
          var checked = document.querySelector(
            'input[name="' + name + '"]:checked'
          );
          if (!checked) {
            isValid = false;
            input.closest(".dfh-field-container").style.border =
              "2px solid red";
          } else {
            input.closest(".dfh-field-container").style.border = "none";
          }
        } else {
          if (!input.value) {
            isValid = false;
            input.style.border = "2px solid red";
          } else {
            input.style.border = "1px solid #ccc";
          }
        }
      });

      if (!isValid) {
        alert("Lütfen zorunlu alanları doldurunuz.");
        return;
      }
      form.submit();
    });
  }

  // --- 3. BAREM KONTROLÜ VE CANLI FİYAT ---
  var debounceTimer = null;

  function handleInputLogic() {
    // 3.1 Barem (Threshold) Kontrolü
    var thresholdExceeded = false;
    var inputs = document.querySelectorAll("#dfh-form input[data-threshold]");

    inputs.forEach(function (input) {
      var val = parseFloat(input.value);
      var limit = parseFloat(input.getAttribute("data-threshold"));

      if (!isNaN(val) && !isNaN(limit) && val > limit) {
        thresholdExceeded = true;
      }
    });

    var submitBtn = document.getElementById("dfh-clean-submit");

    if (thresholdExceeded) {
      // LİMİT AŞILDI: Teklif Modu
      if (submitBtn) submitBtn.style.display = "none";
      if (themePrice) themePrice.style.display = "none"; // Tema fiyatını gizle
      if (livePriceBox) livePriceBox.style.display = "none"; // Bizim fiyatı gizle
      if (offerBox) offerBox.style.display = "block"; // Teklif kutusunu aç
      return; // Fiyat hesaplamasına gerek yok
    } else {
      // NORMAL MOD
      if (submitBtn) submitBtn.style.display = "inline-block";
      if (themePrice) themePrice.style.display = "block";
      if (livePriceBox) livePriceBox.style.display = "block";
      if (offerBox) offerBox.style.display = "none";
    }

    // 3.2 Canlı Fiyat (AJAX)
    if (!form || typeof dfhAjax === "undefined") return;

    var formData = new FormData(form);
    formData.append("action", "dfh_calculate_price");
    formData.append("nonce", dfhAjax.nonce);
    formData.append("product_id", dfhAjax.product_id);

    fetch(dfhAjax.ajax_url, {
      method: "POST",
      body: formData,
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (json) {
        if (json.success && json.data.formatted) {
          if (livePriceBox) {
            livePriceBox.innerHTML = json.data.formatted;
          }
          // Tema fiyatını da güncelle (Opsiyonel: Tema yapısına göre class değişebilir)
          if (themePrice) {
            // Bazı temalarda <span class="woocommerce-Price-amount">...</span> yapısını korumak gerekir
            // En basit yöntem: İçeriği direkt güncellemek.
            // themePrice.innerHTML = json.data.formatted;
          }
        }
      })
      .catch(function (err) {
        console.log(err);
      });
  }

  // Değişiklikleri dinle
  customForm.addEventListener("input", function (e) {
    if (!e.target.matches("input, select, textarea")) return;
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(handleInputLogic, 300); // 300ms gecikmeli çalıştır
  });
});
