document.addEventListener("DOMContentLoaded", function () {
  // 1. Bizim form var mı kontrol et
  var customForm = document.getElementById("dfh-form");
  if (!customForm) return;

  console.log("DFH: Saf JS Modu Başlatıldı.");

  // 2. Butonu Bul
  var oldBtn = document.querySelector(".single_add_to_cart_button");
  if (oldBtn) {
    // 4. BUTONU KLONLA (Bu işlem tüm event listener'ları siler!)
    var newBtn = oldBtn.cloneNode(true);

    // Klonlanan butona temiz bir ID verelim
    newBtn.id = "dfh-clean-submit";
    newBtn.classList.remove("ajax_add_to_cart"); // WC sınıfını sil
    newBtn.type = "button"; // Submit özelliğini kapat, biz yöneteceğiz

    // 5. Eski butonu yenisiyle değiştir
    oldBtn.parentNode.replaceChild(newBtn, oldBtn);

    // 6. Yeni Tıklama Olayı (Validasyon ve Gönderim)
    newBtn.addEventListener("click", function (e) {
      e.preventDefault();

      // Validasyon
      var isValid = true;
      var inputs = document.querySelectorAll(
        "#dfh-form input[required], #dfh-form select[required], #dfh-form textarea[required]"
      );

      inputs.forEach(function (input) {
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

      console.log("DFH: Form native submit ediliyor...");
      form.submit(); // Native Submit (AJAX'sız)
    });
  }

  // 7. JS TOOLTIP (Kesin Çözüm)
  // Tooltip'i fareyi takip ettirmek yerine sabit bir yere koyacağız ki taşma olmasın.
  var tips = document.querySelectorAll(".dfh-tooltip-icon");

  // Sayfaya tek bir tooltip kutusu ekle
  var tooltipBox = document.createElement("div");
  tooltipBox.id = "dfh-global-tooltip";
  tooltipBox.style.display = "none";
  tooltipBox.style.position = "fixed";
  tooltipBox.style.zIndex = "99999999";
  tooltipBox.style.background = "#000";
  tooltipBox.style.color = "#fff";
  tooltipBox.style.padding = "10px";
  tooltipBox.style.borderRadius = "5px";
  tooltipBox.style.fontSize = "14px";
  tooltipBox.style.maxWidth = "300px";
  tooltipBox.style.boxShadow = "0 10px 20px rgba(0,0,0,0.5)";
  tooltipBox.style.pointerEvents = "none"; // Tıklamayı engellemesin
  document.body.appendChild(tooltipBox);

  tips.forEach(function (tip) {
    tip.addEventListener("mouseenter", function (e) {
      var text = this.getAttribute("data-tooltip");
      if (text) {
        tooltipBox.innerHTML = text;
        tooltipBox.style.display = "block";
        // Mouse'un hemen yanına koy
        updateTooltipPos(e);
      }
    });

    tip.addEventListener("mousemove", function (e) {
      updateTooltipPos(e);
    });

    tip.addEventListener("mouseleave", function () {
      tooltipBox.style.display = "none";
    });
  });

  function updateTooltipPos(e) {
    var x = e.clientX + 15;
    var y = e.clientY + 15;

    // Ekrandan taşıyorsa düzelt
    if (x + 300 > window.innerWidth) x = e.clientX - 315;

    tooltipBox.style.left = x + "px";
    tooltipBox.style.top = y + "px";
  }

  // 8. Canlı fiyat hesaplama (AJAX)
  var livePriceBox = document.getElementById("dfh-live-price");
  var dfhRuleInput = document.querySelector('input[name="dfh_rule_id"]');
  var form = document.querySelector("form.cart");

  if (livePriceBox && dfhRuleInput && typeof dfhAjax !== "undefined") {
    var debounceTimer = null;

    function triggerLivePrice() {
      if (!form) return;

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
          if (!json) return;
          if (
            json.success &&
            json.data &&
            typeof json.data.formatted !== "undefined"
          ) {
            livePriceBox.innerHTML =
              "<strong>" +
              (dfhAjax.i18n_label || "Hesaplanan fiyat") +
              ":</strong> " +
              json.data.formatted;
          } else if (!json.success && json.data && json.data.message) {
            livePriceBox.innerHTML = "<small>" + json.data.message + "</small>";
          }
        })
        .catch(function () {
          // Sessiz geç
        });
    }

    customForm.addEventListener("input", function (e) {
      if (
        !e.target.matches("input, select, textarea") ||
        e.target.type === "hidden"
      ) {
        return;
      }
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(triggerLivePrice, 400);
    });
  }
});
