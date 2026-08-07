{{--
  Anteprima live del tempo di lettura, sola visualizzazione: nessun campo del
  form la trasporta al server, che resta l'unica fonte di verità (calcola lo
  stesso valore con Article::calculateReadMinutes() al salvataggio — stessa
  formula: 200 parole/minuto, arrotondata, minimo 1 minuto). Questo script
  serve solo a mostrare in anteprima cosa verrà salvato mentre si scrive,
  senza introdurre un secondo valore che potrebbe disallinearsi dal server.
--}}
<script>
window.kairusUpdateReadMinutesPreview = function () {
  const bodyField = document.getElementById('body');
  const preview = document.getElementById('read-minutes-preview');
  if (! bodyField || ! preview) {
    return;
  }

  const text = new DOMParser().parseFromString(bodyField.value, 'text/html').body.textContent || '';
  const wordCount = text.trim().split(/\s+/).filter(Boolean).length;
  const minutes = Math.max(1, Math.round(wordCount / 200));

  preview.textContent = minutes + ' min';
};

document.addEventListener('DOMContentLoaded', window.kairusUpdateReadMinutesPreview);
</script>
