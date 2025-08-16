/**
 * Frontend JavaScript for Student Registration Form
 * Handles multi-step form navigation, class selection, signature canvas, and form submission
 */

(function () {
  "use strict";

  // Check if the registration form exists on this page
  const form = document.getElementById("gm-form");
  if (!form) {
    return; // Exit early if form doesn't exist
  }

  // DOM elements
  const panes = document.querySelectorAll(".gm-step-pane");
  const bar = document.getElementById("gm-progress-bar");
  const stepperElement = document.getElementById("gm-stepper");
  const stepper = stepperElement ? stepperElement.children : [];

  // Early exit if essential elements are missing
  if (!stepperElement || !bar) {
    console.warn("GM Registration: Required DOM elements not found");
    return;
  }

  // Step navigation
  function go(step) {
    panes.forEach((p) => p.classList.add("gm-hidden"));
    document
      .querySelector('.gm-step-pane[data-step="' + step + '"]')
      .classList.remove("gm-hidden");
    [...stepper].forEach((s, i) => s.classList.toggle("active", i + 1 <= step));
    bar.style.width = step * 33 + "%";

    // Update kicker information whenever we navigate to a step
    if (step >= 2) {
      setTimeout(updateKicker, 50); // Small delay to ensure DOM is updated
    }

    if (step === 3 && typeof resizeCanvas === "function") {
      setTimeout(resizeCanvas, 30);
    }

    window.scrollTo({
      top: document.querySelector(".gm-container").offsetTop - 20,
      behavior: "smooth",
    });
  }

  // Update kicker information
  function updateKicker() {
    const locSel = document.querySelector('[name="location"]');
    const yrSel = document.querySelector('[name="year_group"]');
    const loc = locSel && locSel.value ? locSel.value : "—";
    const yr =
      yrSel && yrSel.value ? (yrSel.value.match(/\d+/) || ["—"])[0] : "—";

    const locEl = document.getElementById("gm-kicker-location");
    const yrEl = document.getElementById("gm-kicker-year");

    if (locEl) locEl.textContent = loc;
    if (yrEl) yrEl.textContent = yr;

    // Show/hide the year note based on year selection
    const yearNote = document.getElementById("gm-year-note");
    if (yearNote) {
      const isYear10or11 =
        yrSel && (yrSel.value === "Year 10" || yrSel.value === "Year 11");
      yearNote.style.display = isYear10or11 ? "block" : "none";
    }

    // Debug log to check if function is working
    console.log("updateKicker called - Location:", loc, "Year:", yr);
  }

  // Step 1 validation
  function validateStep1() {
    const req = [
      "parent_first_name",
      "parent_last_name",
      "parent_email",
      "parent_phone",
      "student_first_name",
      "student_last_name",
      "location",
      "year_group",
    ];

    for (const n of req) {
      const el = document.querySelector('[name="' + n + '"]');
      if (!el || !el.value.trim()) {
        el && el.reportValidity && el.reportValidity();
        return false;
      }
    }
    updateKicker();
    return true;
  }

  // Event listeners for navigation
  document.getElementById("gm-next-1").onclick = () => {
    if (validateStep1()) go(2);
  };

  document.getElementById("gm-prev-2").onclick = () => go(1);
  document.getElementById("gm-prev-3").onclick = () => go(2);

  // Update kicker on field changes
  const locEl = document.querySelector('[name="location"]');
  const yrEl = document.querySelector('[name="year_group"]');
  locEl && locEl.addEventListener("change", updateKicker);
  yrEl && yrEl.addEventListener("change", updateKicker);

  // Class Selection Logic
  const cards = document.querySelectorAll(".gm-class-card.selectable");
  const selectedUl = document.getElementById("gm-selected-ul");
  const next2 = document.getElementById("gm-next-2");
  const selection = new Map();

  function needsTwo() {
    const y = (
      document.querySelector('[name="year_group"]').value || ""
    ).toLowerCase();
    return y === "year 10" || y === "year 11";
  }

  function getMaxClasses() {
    return needsTwo() ? 2 : 4; // Year 10-11: exactly 2, Year 1-9: max 4
  }

  function renderSelected() {
    selectedUl.innerHTML = "";
    if (selection.size === 0) {
      selectedUl.innerHTML = "<li>No classes selected.</li>";
    } else {
      selection.forEach((o) => {
        const li = document.createElement("li");
        li.textContent = o.title;
        selectedUl.appendChild(li);
      });
    }
    // Update Next button state based on selection requirements
    if (needsTwo()) {
      next2.disabled = selection.size !== 2;
    } else {
      next2.disabled = selection.size < 1 || selection.size > 4;
    }
  }

  // Class card click handlers
  cards.forEach((card) => {
    card.addEventListener("click", function (e) {
      e.preventDefault();
      const id = this.dataset.id;
      const title = this.dataset.title;
      const price = this.dataset.price;

      if (selection.has(id)) {
        // Remove selection
        selection.delete(id);
        this.classList.remove("selected");
        this.querySelector(".gm-cta").textContent = "Click to select";
        this.querySelector(".gm-cta").classList.remove("selected");
        renderSelected();
        return;
      }

      if (selection.size >= getMaxClasses()) {
        // Shake animation for max selection
        this.style.animation = "gmShake .2s linear";
        setTimeout(() => (this.style.animation = ""), 200);
        return;
      }

      // Add selection
      selection.set(id, { id: id, title: title, price: price });
      this.classList.add("selected");
      this.querySelector(".gm-cta").textContent = "✓ Selected";
      this.querySelector(".gm-cta").classList.add("selected");
      renderSelected();
    });
  });

  renderSelected();

  // Step 3 summary
  const summaryTable = document.getElementById("gm-summary-table");
  const totalEl = document.getElementById("gm-total");

  function updateSummary() {
    summaryTable.innerHTML =
      '<tr><td colspan="2" style="font-weight:700;">Selected Classes:</td></tr>';
    let total = 0;

    selection.forEach((o) => {
      const tr = document.createElement("tr");
      const loc =
        document.querySelector('[name="location"]').value || "Nottingham";
      tr.innerHTML =
        "<td>" +
        loc +
        " - " +
        o.title +
        '</td><td style="text-align:right;">£' +
        Number(o.price).toFixed(2) +
        "</td>";
      summaryTable.appendChild(tr);
      total += parseFloat(o.price || 0);
    });

    totalEl.textContent = "Monthly Total: £" + total.toFixed(2);
  }

  next2.onclick = () => {
    updateSummary();
    go(3);
  };

  // Payment + signature + submit validation
  const terms = document.getElementById("gm-terms");
  const submitBtn = document.getElementById("gm-submit");
  let signed = false;

  function validForSubmit() {
    if (needsTwo()) {
      // Years 10-11: must select exactly 2 classes
      return selection.size === 2 && signed && terms.checked;
    } else {
      // Years 1-9: must select 1-4 classes
      return (
        selection.size >= 1 && selection.size <= 4 && signed && terms.checked
      );
    }
  }

  function updateSubmit() {
    submitBtn.disabled = !validForSubmit();

    // Update submit button text and payment note based on payment method
    const paymentMethod = document.querySelector(
      'input[name="payment_method"]:checked'
    );
    const paymentNote = document.getElementById("gm-payment-note");

    if (paymentMethod && paymentMethod.value === "cash") {
      submitBtn.textContent = "Complete Registration";
      if (paymentNote) {
        paymentNote.textContent =
          "Your registration will be submitted as pending. Payment is due on your first attendance.";
      }
    } else {
      submitBtn.textContent = "Proceed to Payment";
      if (paymentNote) {
        paymentNote.textContent =
          "You will be redirected to our secure online payment gateway to complete your registration.";
      }
    }
  }

  terms.addEventListener("change", updateSubmit);

  // Payment method change handler
  const paymentMethods = document.querySelectorAll(
    'input[name="payment_method"]'
  );
  paymentMethods.forEach((method) => {
    method.addEventListener("change", function () {
      // Update visual selection
      document
        .querySelectorAll(".gm-method")
        .forEach((m) => m.classList.remove("selected"));
      this.closest(".gm-method").classList.add("selected");

      // Update submit button and note
      updateSubmit();
    });
  });

  // Signature Canvas Implementation
  const canvas = document.getElementById("gm-sign-canvas");
  const clearBtn = document.getElementById("gm-sign-clear");
  let drawing = false;
  let ctx,
    ratio = window.devicePixelRatio || 1;

  function resizeCanvas() {
    const rect = canvas.getBoundingClientRect();
    if (rect.width === 0 || rect.height === 0) {
      return;
    }

    ratio = window.devicePixelRatio || 1;
    canvas.width = Math.round(rect.width * ratio);
    canvas.height = Math.round(rect.height * ratio);
    ctx = canvas.getContext("2d");
    ctx.lineWidth = 2 * ratio;
    ctx.lineCap = "round";
    ctx.strokeStyle = "#111827";
  }

  function pos(e) {
    const r = canvas.getBoundingClientRect();
    const t = e.touches ? e.touches[0] : e;
    const dpr = window.devicePixelRatio || 1;
    return {
      x: (t.clientX - r.left) * dpr,
      y: (t.clientY - r.top) * dpr,
    };
  }

  function start(e) {
    e.preventDefault();
    if (!ctx || canvas.width === 0 || canvas.height === 0) resizeCanvas();

    drawing = true;
    signed = true;
    const p = pos(e);
    ctx.beginPath();
    ctx.moveTo(p.x, p.y);
    updateSubmit();
  }

  function move(e) {
    if (!drawing) return;
    e.preventDefault();
    const p = pos(e);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
  }

  function end() {
    drawing = false;
  }

  // Canvas event listeners
  window.addEventListener("resize", resizeCanvas);
  canvas.addEventListener("mousedown", start);
  canvas.addEventListener("mousemove", move);
  window.addEventListener("mouseup", end);
  canvas.addEventListener("touchstart", start, { passive: false });
  canvas.addEventListener("touchmove", move, { passive: false });
  canvas.addEventListener("touchend", end);

  clearBtn.addEventListener("click", () => {
    if (ctx) {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
    }
    signed = false;
    updateSubmit();
  });

  function exportSignatureWhiteBG() {
    const ex = document.createElement("canvas");
    ex.width = canvas.width;
    ex.height = canvas.height;
    const ectx = ex.getContext("2d");
    ectx.fillStyle = "#ffffff";
    ectx.fillRect(0, 0, ex.width, ex.height);
    ectx.drawImage(canvas, 0, 0);
    return ex.toDataURL("image/jpeg", 0.9);
  }

  // Form submission handler
  form.addEventListener("submit", async function (e) {
    e.preventDefault();
    if (submitBtn.disabled) return;

    updateSubmit();
    if (!validForSubmit()) {
      // Provide specific feedback about what's missing
      const yearGroup = q("year_group");
      const needsExactlyTwo = needsTwo();
      const classCount = selection.size;

      if (needsExactlyTwo && classCount !== 2) {
        alert(
          `Year 10 and Year 11 students must select exactly 2 classes. You have selected ${classCount} classes.`
        );
      } else if (!needsExactlyTwo && (classCount < 1 || classCount > 4)) {
        if (classCount < 1) {
          alert("Years 1-9 students must select at least 1 class.");
        } else {
          alert(
            "Years 1-9 students can select a maximum of 4 classes. You have selected " +
              classCount +
              " classes."
          );
        }
      } else if (!signed) {
        alert("Please provide your signature.");
      } else if (!terms.checked) {
        alert("Please accept the terms and conditions.");
      }
      return;
    }

    // First let's test basic AJAX connectivity if debug=1 is in URL
    if (window.location.search.includes("debug=1")) {
      console.log("Running AJAX test...");
      const testData = new URLSearchParams();
      testData.append("action", "gm_save_registration");
      testData.append("test", "1");
      testData.append("_ajax_nonce", GM_AJAX.nonce);

      try {
        const testResponse = await fetch(GM_AJAX.url, {
          method: "POST",
          body: testData,
        });
        const testResult = await testResponse.text();
        console.log("AJAX Test Response Status:", testResponse.status);
        console.log("AJAX Test Response Body:", testResult);

        if (testResponse.ok) {
          alert("AJAX test successful! Check console for details.");
        } else {
          alert("AJAX test failed with status: " + testResponse.status);
        }
      } catch (testError) {
        console.error("AJAX Test Error:", testError);
        alert("AJAX test error: " + testError.message);
      }
      return;
    }

    const cls = Array.from(selection.values());
    let total = 0;
    cls.forEach((o) => (total += parseFloat(o.price || 0)));

    function q(n) {
      if (n === "payment_method") {
        const checkedEl = document.querySelector('[name="' + n + '"]:checked');
        console.log("Payment method debug - checked element:", checkedEl);
        console.log(
          "Payment method debug - value:",
          checkedEl ? checkedEl.value : "none"
        );
        return checkedEl ? checkedEl.value : "";
      }
      const el = document.querySelector('[name="' + n + '"]');
      return el ? el.value : "";
    }

    const formData = {
      action: "gm_save_registration",
      _ajax_nonce: GM_AJAX.nonce,
      parent_first_name: q("parent_first_name"),
      parent_last_name: q("parent_last_name"),
      parent_email: q("parent_email"),
      parent_phone: q("parent_phone"),
      student_first_name: q("student_first_name"),
      student_last_name: q("student_last_name"),
      location: q("location"),
      current_grades: q("current_grades"),
      year_group: q("year_group"),
      classes: JSON.stringify(cls),
      monthly_total: total.toFixed(2),
      payment_method: q("payment_method"),
      accepted_terms: "1",
      signature_data: "",
    };

    // Debug log
    console.log("Sending form data:", formData);
    console.log("Selected classes:", cls);
    console.log("Classes JSON:", JSON.stringify(cls));
    console.log("Number of selected classes:", cls.length);
    console.log("Year group:", q("year_group"));
    console.log("Payment method value:", q("payment_method"));
    console.log("Needs two classes:", needsTwo());

    try {
      formData.signature_data = exportSignatureWhiteBG();
    } catch (e) {
      console.warn("Failed to export signature:", e);
    }

    submitBtn.disabled = true;
    submitBtn.textContent = "Processing...";

    try {
      const response = await fetch(GM_AJAX.url, {
        method: "POST",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
        body: new URLSearchParams(formData),
      });

      const ct = response.headers.get("content-type") || "";
      let data;

      if (ct.includes("application/json")) {
        data = await response.json();
      } else {
        const text = await response.text();
        console.log("Raw response:", text);
        try {
          data = JSON.parse(text);
        } catch {
          throw new Error(text || "Server error");
        }
      }

      console.log("Response data:", data);

      if (!data || !data.success) {
        throw new Error(data && data.message ? data.message : "Unknown error");
      }

      // Debug WooCommerce integration
      const responseData = data.data || data; // Handle wp_send_json_success wrapper
      console.log("Full Response:", data);
      console.log("Payment Method:", q("payment_method"));
      console.log("WooCommerce Order ID:", responseData.wc_order_id);
      console.log("Checkout URL:", responseData.checkout_url);
      console.log("WooCommerce Error:", responseData.wc_error);
      console.log("WooCommerce Debug Info:", responseData.wc_debug);

      // Handle cash payment
      if (q("payment_method") === "cash") {
        alert(
          "Registration submitted successfully! Your registration is pending and payment is due on your first attendance. You will receive a confirmation email shortly."
        );
        // Optionally redirect to a success page or reload
        location.reload();
        return;
      }

      // Handle online payment (existing logic)
      if (responseData.checkout_url) {
        console.log(
          "Redirecting to WooCommerce checkout:",
          responseData.checkout_url
        );
        alert("Registration saved! Redirecting to payment...");
        window.location.href = responseData.checkout_url;
      } else {
        if (responseData.wc_error) {
          console.error("WooCommerce error:", responseData.wc_error);
          alert(
            "Registration saved! However, there was an issue setting up payment: " +
              responseData.wc_error
          );
        } else if (responseData.wc_order_id) {
          alert(
            "Registration and order created (Order #" +
              responseData.wc_order_id +
              "), but no checkout URL was generated. Please contact support."
          );
        } else {
          // Show debug info in alert for troubleshooting
          let debugMsg = "Registration saved! Debug info: ";
          if (responseData.wc_debug) {
            debugMsg +=
              "WC Class: " +
              (responseData.wc_debug.wc_class_exists ? "YES" : "NO") +
              ", ";
            debugMsg +=
              "WC Function: " +
              (responseData.wc_debug.wc_create_order_exists ? "YES" : "NO") +
              ", ";
            debugMsg +=
              "Classes: " + (responseData.wc_debug.classes_count || 0);
            if (responseData.wc_debug.availability) {
              debugMsg += ", Issue: " + responseData.wc_debug.availability;
            }
          }
          alert(debugMsg + " Please contact support to complete payment.");
        }
        // Don't reload - let user see the message
      }
    } catch (err) {
      alert(
        "Save failed: " +
          (err && err.message ? err.message : "Network/Server error")
      );
      submitBtn.disabled = false;
      const paymentMethod = q("payment_method");
      submitBtn.textContent =
        paymentMethod === "cash"
          ? "Complete Registration"
          : "Proceed to Payment";
    }
  });

  // Initialize form
  go(1);

  // Initialize payment method selection when DOM is ready
  function initializePaymentMethods() {
    // Set initial selection state for the checked radio button
    const checkedMethod = document.querySelector(
      'input[name="payment_method"]:checked'
    );
    if (checkedMethod) {
      checkedMethod.closest(".gm-method").classList.add("selected");
    }

    // Update submit button text and note based on initial selection
    setTimeout(updateSubmit, 100); // Small delay to ensure DOM is ready
  }

  // Initialize payment methods when page loads
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializePaymentMethods);
  } else {
    initializePaymentMethods();
  }

  // Make resizeCanvas globally available for step 3
  window.resizeCanvas = resizeCanvas;
})();
