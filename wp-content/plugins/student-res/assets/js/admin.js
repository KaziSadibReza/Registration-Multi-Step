/**
 * Admin JavaScript for Student Registration Plugin
 * Handles admin interface functionality, modal popups, and class management
 */

(function ($) {
  "use strict";

  // Signature modal functionality
  function initSignatureModal() {
    const modal = document.getElementById("gm-modal");
    const img = document.getElementById("gm-modal-img");
    const close = document.getElementById("gm-modal-close");

    if (!modal || !img || !close) return;

    document.querySelectorAll(".gm-view-sig").forEach((a) => {
      a.addEventListener("click", function (e) {
        e.preventDefault();
        img.src = this.dataset.src || this.href;
        modal.classList.add("open");
      });
    });

    function hide() {
      modal.classList.remove("open");
      img.src = "";
    }

    close.addEventListener("click", function (e) {
      e.preventDefault();
      hide();
    });

    modal.addEventListener("click", function (e) {
      if (e.target === modal) hide();
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") hide();
    });
  }

  // Class management functionality
  function initClassManagement() {
    const editForm = document.getElementById("edit-class-form");
    if (!editForm) return;

    // Check if classes data exists
    if (
      typeof classesData !== "undefined" &&
      typeof classesMap !== "undefined"
    ) {
      window.editClass = function (classId) {
        const cls = classesMap.get(classId);
        if (!cls) return;

        document.getElementById("edit-class-id").value = cls.class_id;
        document.getElementById("edit-title").value = cls.title;
        document.getElementById("edit-price").value = cls.price;
        document.getElementById("edit-seats").value = cls.max_seats;
        document.getElementById("edit-description").value =
          cls.description || "";

        editForm.style.display = "block";
        editForm.scrollIntoView({ behavior: "smooth" });
        document.getElementById("edit-title").focus();
      };

      window.cancelEdit = function () {
        editForm.style.display = "none";
      };
    }
  }

  // Initialize when DOM is ready
  $(document).ready(function () {
    initSignatureModal();
    initClassManagement();
  });
})(jQuery);
