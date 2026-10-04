(function () {
  'use strict';

  function updateAuthFields() {
    var select = document.querySelector('select[name="wpu_sms[auth[type]]"]');
    if (!select) return;
    var type = select.value;
    var fields = document.querySelector('[data-wpu-auth-fields]');
    if (!fields) return;

    var name = fields.querySelector('input[name="wpu_sms[auth[name]]"]');
    var credential = fields.querySelector('input[name="wpu_sms[auth[value]]"]');
    var username = fields.querySelector('input[name="wpu_sms[auth[username]]"]');
    var password = fields.querySelector('input[name="wpu_sms[auth[password]]"]');

    if (name) name.closest('.wpu-sms-field').hidden = !['api_key', 'header', 'query'].includes(type);
    if (credential) credential.closest('.wpu-sms-field').hidden = !['api_key', 'header', 'query', 'bearer'].includes(type);
    if (username) username.closest('.wpu-sms-field').hidden = type !== 'basic';
    if (password) password.closest('.wpu-sms-field').hidden = type !== 'basic';
  }

  function addPair(container) {
    var name = container.getAttribute('data-name');
    var index = container.querySelectorAll('.wpu-sms-pair').length;
    var row = document.createElement('div');
    row.className = 'wpu-sms-pair';
    row.innerHTML =
      '<input type="text" name="wpu_sms[' + name + '][' + index + '][name]" placeholder="Name">' +
      '<input type="text" name="wpu_sms[' + name + '][' + index + '][value]" placeholder="Value">' +
      '<button type="button" class="button" data-wpu-remove>Remove</button>';
    container.insertBefore(row, container.querySelector('[data-wpu-add]'));
  }

  document.addEventListener('change', function (event) {
    if (event.target.matches('select[name="wpu_sms[auth[type]]"]')) updateAuthFields();
  });

  document.addEventListener('click', function (event) {
    var copy = event.target.closest('[data-wpu-copy]');
    if (copy) {
      var value = copy.getAttribute('data-wpu-copy');
      if (value && navigator.clipboard) {
        navigator.clipboard.writeText(value).then(function () {
          copy.textContent = 'Copied';
          window.setTimeout(function () { copy.textContent = 'Copy'; }, 1200);
        });
      }
      return;
    }

    var add = event.target.closest('[data-wpu-add]');
    if (add) {
      addPair(add.closest('[data-wpu-pairs]'));
      return;
    }

    var remove = event.target.closest('[data-wpu-remove]');
    if (remove) {
      var row = remove.closest('.wpu-sms-pair');
      if (row) row.remove();
    }
  });

  updateAuthFields();
}());
