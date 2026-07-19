(() => {
  'use strict';

  const confirmedElements = new WeakSet();

  document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    const message = (form.dataset.confirm || '').trim();

    if (message === '' || confirmedElements.has(form)) {
      return;
    }

    if (!window.confirm(message)) {
      event.preventDefault();
      event.stopImmediatePropagation();
      return;
    }

    confirmedElements.add(form);
  }, true);

  document.addEventListener('click', (event) => {
    const target = event.target instanceof Element
      ? event.target.closest('[data-confirm]')
      : null;

    if (!target || target instanceof HTMLFormElement) {
      return;
    }

    const message = (target.getAttribute('data-confirm') || '').trim();

    if (message !== '' && !window.confirm(message)) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);

  document.addEventListener('change', (event) => {
    const target = event.target;

    if (!(target instanceof HTMLSelectElement || target instanceof HTMLInputElement)) {
      return;
    }

    if (target.dataset.autoSubmit === 'true' && target.form) {
      if (typeof target.form.requestSubmit === 'function') {
        target.form.requestSubmit();
      } else {
        target.form.submit();
      }
      return;
    }

    const navigateBase = (target.dataset.navigateBase || '').trim();

    if (navigateBase !== '') {
      window.location.assign(navigateBase + encodeURIComponent(target.value));
    }
  });

  document.addEventListener('DOMContentLoaded', () => {
    const main = document.getElementById('swafi-main-content');
    const status = document.getElementById('swafiAccessibilityStatus');

    if (main && !main.hasAttribute('tabindex')) {
      main.setAttribute('tabindex', '-1');
    }

    if (status) {
      status.textContent = 'Interfaz SWAFI cargada y lista para navegar.';
    }
  });
})();
