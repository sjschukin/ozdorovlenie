(function (Drupal, once) {
  "use strict";

  Drupal.behaviors.webformInit = {
    attach(context) {
      once('webformInit', '#request-form-container', context).forEach((element) => registerModal(element));
    }
  };

  /**
   * Voucher request modal form
   */
  function registerModal(element) {
    const requestModal = new bootstrap.Modal(element, {
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
  }

}(Drupal, once));
