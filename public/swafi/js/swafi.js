document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.module-card').forEach(card => {
    card.addEventListener('mouseenter', () => card.style.boxShadow = '0 18px 36px rgba(19,63,133,.12)');
    card.addEventListener('mouseleave', () => card.style.boxShadow = '0 12px 30px rgba(19,63,133,.05)');
  });
});
