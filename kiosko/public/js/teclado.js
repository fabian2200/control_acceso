document.querySelectorAll('.keypad').forEach((pad) => {
  const input = document.getElementById(pad.dataset.target);
  if (!input) return;

  const max = Number(input.getAttribute('maxlength') || 12);

  pad.addEventListener('click', (event) => {
    const btn = event.target.closest('[data-key]');
    if (!btn) return;

    const key = btn.getAttribute('data-key');
    if (key === 'C') {
      input.value = '';
    } else if (key === '⌫') {
      input.value = input.value.slice(0, -1);
    } else if (input.value.length < max) {
      input.value += key;
    }

    input.dispatchEvent(new Event('input', { bubbles: true }));
  });
});
