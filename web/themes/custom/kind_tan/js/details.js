(function() {
  "use strict";

  /**
   * Initiate glightbox
   */
  const glightbox = GLightbox({
    selector: '.glightbox'
  });

  /**
   * Camp Shift Toggle
   */
  document.querySelectorAll('.camp-shift-item h3, .camp-shift-item .camp-shift-toggle').forEach((item) => {
    item.addEventListener('click', () => {
      item.parentNode.classList.toggle('camp-shift-active');
    });
  });

  /**
   * Voucher request modal form
   */
  const requestModal = new bootstrap.Modal('#request-form-container', {
    keyboard: true
  });

  document.querySelectorAll('.camp-shift-btn').forEach((item) => {
    item.onclick = function () {
      const form = document.querySelector('.webform-submission-voucher-request-form-form');
      const header = document.querySelector('#shift-name-header');
      const campShiftElement = form.querySelector('input[name="camp_shift"]');
      campShiftElement.value = item.dataset.campShift;
      header.innerText = item.dataset.campShift;

      requestModal.show();
      return false;
    }
  });

})();
