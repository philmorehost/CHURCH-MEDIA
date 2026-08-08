(function () {
  'use strict';

  document.querySelectorAll('.form-upload input[type="file"]').forEach(function (input) {
    input.addEventListener('change', function () {
      var label = input.closest('.form-upload');
      if (!label) { return; }
      var count = label.querySelector('[data-upload-count]');
      if (!count) { return; }
      var files = input.files ? input.files.length : 0;
      if (files === 0) {
        count.textContent = 'No files chosen';
      } else if (files === 1) {
        count.textContent = input.files[0].name;
      } else {
        count.textContent = files + ' images chosen';
      }
    });
  });
})();
