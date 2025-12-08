(function () {
  const form = document.querySelector('form');
  if (!form) return;
  const textarea = form.querySelector('textarea[name="headers"]');
  if (!textarea) return;

  function ensureJson() {
    try {
      JSON.parse(textarea.value || '{}');
    } catch (e) {
      if (textarea.value && textarea.value.trim() !== '{}') {
        console.warn('Invalid JSON in headers field, resetting to empty object');
      }
      textarea.value = '{}';
    }
  }

  form.addEventListener('submit', ensureJson);
})();
