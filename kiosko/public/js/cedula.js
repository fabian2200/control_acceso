(() => {
  const input = document.getElementById('cedula');
  const display = document.getElementById('cedulaDisplay');
  const occInput = document.getElementById('salida_ocasional');
  const occBtn = document.getElementById('btnOccasional');
  const form = document.getElementById('formCedula');

  function render() {
    const value = input.value;
    if (!value) {
      display.textContent = '••••••••';
      display.classList.add('is-empty');
      return;
    }
    display.textContent = value;
    display.classList.remove('is-empty');
  }

  input.addEventListener('input', render);
  render();

  occBtn.addEventListener('click', () => {
    const on = occInput.value !== '1';
    occInput.value = on ? '1' : '0';
    occBtn.classList.toggle('is-on', on);
  });

  form.addEventListener('submit', (event) => {
    if (input.value.length < 5) {
      event.preventDefault();
    }
  });
})();
