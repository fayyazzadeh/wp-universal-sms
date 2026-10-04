(function () {
  'use strict';

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-wpu-copy]');
    if (!button) {
      return;
    }

    var value = button.getAttribute('data-wpu-copy');
    if (!value || !navigator.clipboard) {
      return;
    }

    navigator.clipboard.writeText(value).then(function () {
      button.textContent = 'Copied';
      window.setTimeout(function () {
        button.textContent = 'Copy';
      }, 1200);
    });
  });
}());
