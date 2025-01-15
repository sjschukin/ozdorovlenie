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

})();
