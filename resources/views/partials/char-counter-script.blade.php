{{--
  Contatore caratteri generico, riusabile su qualunque campo: aggiunge un
  <span class="js-char-counter" data-target="{id campo}" data-recommended="{limite consigliato}">
  vicino alla label e aggiorna testo/colore ad ogni input, evidenziando il
  superamento del limite consigliato (soft, solo visivo — la validazione
  server-side applica un limite massimo più permissivo).
--}}
<script>
document.querySelectorAll('.js-char-counter').forEach(function (counter) {
  const field = document.getElementById(counter.dataset.target);
  const recommended = parseInt(counter.dataset.recommended, 10);
  if (! field || ! recommended) {
    return;
  }

  function update() {
    const length = field.value.length;
    const overLimit = length > recommended;
    counter.textContent = length + '/' + recommended + ' caratteri';
    counter.classList.toggle('char-counter--over', overLimit);
  }

  field.addEventListener('input', update);
  update();
});
</script>
