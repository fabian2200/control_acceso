(() => {
  const video = document.getElementById('camVideo');
  const canvas = document.getElementById('camCanvas');
  const countEl = document.getElementById('camCount');
  const flash = document.getElementById('camFlash');
  const form = document.getElementById('formFoto');
  const foto = document.getElementById('foto');
  let stream = null;
  let count = 3;

  async function start() {
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'user', width: 1280, height: 720 },
        audio: false,
      });
      video.srcObject = stream;
    } catch (e) {
      stream = null;
    }
    setTimeout(tick, 900);
  }

  function tick() {
    count -= 1;
    if (count > 0) {
      countEl.textContent = String(count);
      countEl.style.animation = 'none';
      void countEl.offsetWidth;
      countEl.style.animation = 'countIn 900ms cubic-bezier(.22,.7,.3,1) both';
      setTimeout(tick, 900);
      return;
    }
    countEl.textContent = '';
    flash.classList.remove('hidden');
    foto.value = capture() || '';
    setTimeout(() => {
      stop();
      form.submit();
    }, 420);
  }

  function capture() {
    if (!video.videoWidth) return '';
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    return canvas.toDataURL('image/jpeg', 0.82);
  }

  function stop() {
    if (stream) {
      stream.getTracks().forEach((t) => t.stop());
      stream = null;
    }
    video.srcObject = null;
  }

  start();
})();
