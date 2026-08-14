(() => {
  const input = document.getElementById('hora_regreso');
  const display = document.getElementById('horaDisplay');
  const submit = document.getElementById('btnContinuarFoto');

  function render() {
    const digits = input.value.padEnd(4, '_');
    display.textContent = digits.slice(0, 2) + ':' + digits.slice(2, 4);
    submit.disabled = input.value.length !== 4;
  }

  input.addEventListener('input', () => {
    input.value = input.value.replace(/\D/g, '').slice(0, 4);
    render();
  });

  document.querySelectorAll('.quick').forEach((btn) => {
    btn.addEventListener('click', () => {
      const mins = Number(btn.getAttribute('data-mins'));
      const d = new Date(Date.now() + mins * 60000);
      input.value = String(d.getHours()).padStart(2, '0') + String(d.getMinutes()).padStart(2, '0');
      input.dispatchEvent(new Event('input', { bubbles: true }));
    });
  });

  render();
})();
