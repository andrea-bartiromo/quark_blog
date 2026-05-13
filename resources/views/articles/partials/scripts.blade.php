<script>
function copyArticleLink(url) {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(url).then(() => alert('Link copiato negli appunti'));
  }
}

window.addEventListener('scroll', () => {
  const progress = document.getElementById('reading-progress');
  if (!progress) return;
  const scrollTop = window.scrollY || document.documentElement.scrollTop;
  const docHeight = document.documentElement.scrollHeight - window.innerHeight;
  progress.style.width = docHeight > 0 ? ((scrollTop / docHeight) * 100) + '%' : '0%';
});
</script>
