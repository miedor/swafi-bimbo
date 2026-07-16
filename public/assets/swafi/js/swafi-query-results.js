(function () {
  'use strict';

  const FOCUS_PARAMETER = 'swafi_focus';
  const VALIDATION_ERROR_SELECTOR = [
    '.search-message-error',
    '.rp-message.error',
    '.vf-message.vf-error',
    '.ui-message-error',
    '.cat-message-error',
    '.sec-message-error',
    '[data-swafi-query-validation-errors]'
  ].join(',');

  const IGNORED_SUMMARY_FIELDS = new Set([
    '_token',
    '_method',
    FOCUS_PARAMETER,
    'export',
    'page',
    'panel',
    'tab',
    'per_page',
    'ordenar_por',
    'direccion',
    'orientacion',
    'columnas[]'
  ]);

  function normalizeKey(value, fallback) {
    const normalized = String(value || '')
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9_-]+/g, '-');

    return normalized || fallback;
  }

  function getSelectedText(field) {
    if (!(field instanceof HTMLSelectElement)) {
      return '';
    }

    const option = field.options[field.selectedIndex];

    return option ? String(option.textContent || '').trim() : '';
  }

  function isMeaningfulField(field) {
    if (!field.name || field.disabled || IGNORED_SUMMARY_FIELDS.has(field.name)) {
      return false;
    }

    if (field.dataset.swafiSummaryIgnore === 'true') {
      return false;
    }

    if (field.type === 'hidden' || field.type === 'submit' || field.type === 'button') {
      return false;
    }

    if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
      return false;
    }

    const value = String(field.value || '').trim();

    if (value === '') {
      return false;
    }

    if (field instanceof HTMLSelectElement) {
      const selectedText = getSelectedText(field).toLowerCase();

      if (['todos', 'todas', 'todos los registros', 'todas las plantas'].includes(selectedText)) {
        return false;
      }
    }

    return true;
  }

  function countMeaningfulCriteria(form) {
    const names = new Set();

    Array.from(form.elements).forEach(function (field) {
      if (isMeaningfulField(field)) {
        names.add(field.name);
      }
    });

    return names.size;
  }

  function createCollapsedBar(workspace, form, key) {
    const bar = document.createElement('div');
    bar.className = 'swafi-query-collapsed-bar';
    bar.setAttribute('role', 'status');
    bar.setAttribute('aria-live', 'polite');
    bar.dataset.swafiQueryCollapsedBar = key;

    const copy = document.createElement('div');
    copy.className = 'swafi-query-collapsed-copy';

    const title = document.createElement('strong');
    title.textContent = 'Resultados listos para revisión';

    const detail = document.createElement('span');
    const criteriaCount = countMeaningfulCriteria(form);
    detail.textContent = criteriaCount > 0
      ? criteriaCount + (criteriaCount === 1 ? ' criterio aplicado. ' : ' criterios aplicados. ') + 'Los filtros se contrajeron para mostrar la información encontrada.'
      : 'Los filtros se contrajeron para mostrar inmediatamente la información encontrada.';

    copy.appendChild(title);
    copy.appendChild(detail);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'swafi-query-edit-button';
    button.textContent = 'Modificar filtros';
    button.setAttribute('aria-expanded', 'false');
    button.dataset.swafiQueryExpand = key;

    bar.appendChild(copy);
    bar.appendChild(button);
    workspace.insertBefore(bar, workspace.firstChild);

    return bar;
  }

  function getHeaderOffset() {
    const header = document.querySelector('.swafi-page-header');
    const headerHeight = header ? Math.ceil(header.getBoundingClientRect().height) : 0;

    return Math.max(headerHeight + 14, 92);
  }

  function scrollToElement(element, behavior) {
    if (!element) {
      return;
    }

    const top = window.scrollY + element.getBoundingClientRect().top - getHeaderOffset();

    window.scrollTo({
      top: Math.max(0, top),
      behavior: behavior || 'auto'
    });
  }

  function addFocusMarker(form, key) {
    let marker = form.querySelector('input[data-swafi-query-focus-marker]');

    if (!marker) {
      marker = document.createElement('input');
      marker.type = 'hidden';
      marker.name = FOCUS_PARAMETER;
      marker.dataset.swafiQueryFocusMarker = 'true';
      form.appendChild(marker);
    }

    marker.value = key;
  }

  function removeFocusMarker(form) {
    const marker = form.querySelector('input[data-swafi-query-focus-marker]');

    if (marker) {
      marker.remove();
    }
  }

  function initializeWorkspace(workspace, index, requestedFocus) {
    const key = normalizeKey(workspace.dataset.swafiQueryKey, 'consulta-' + (index + 1));
    const panel = workspace.querySelector('[data-swafi-query-panel]');
    const results = workspace.querySelector('[data-swafi-query-results]');
    const form = workspace.querySelector('[data-swafi-query-form]');

    if (!panel || !results || !form) {
      return;
    }

    const collapsedBar = createCollapsedBar(workspace, form, key);
    const expandButton = collapsedBar.querySelector('[data-swafi-query-expand]');

    function collapseAndFocusResults() {
      panel.hidden = true;
      workspace.classList.add('is-query-collapsed');
      collapsedBar.classList.add('is-visible');
      expandButton.setAttribute('aria-expanded', 'false');

      results.classList.add('swafi-query-results-highlight');

      window.requestAnimationFrame(function () {
        window.requestAnimationFrame(function () {
          scrollToElement(results, 'auto');
        });
      });

      window.setTimeout(function () {
        results.classList.remove('swafi-query-results-highlight');
      }, 1400);
    }

    function expandFilters() {
      panel.hidden = false;
      workspace.classList.remove('is-query-collapsed');
      collapsedBar.classList.remove('is-visible');
      expandButton.setAttribute('aria-expanded', 'true');

      window.requestAnimationFrame(function () {
        scrollToElement(panel, 'smooth');

        const firstField = form.querySelector('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])');

        if (firstField) {
          window.setTimeout(function () {
            firstField.focus({ preventScroll: true });
          }, 250);
        }
      });
    }

    expandButton.addEventListener('click', expandFilters);

    form.addEventListener('submit', function (event) {
      const submitter = event.submitter;
      const isExport = submitter && submitter.name === 'export';

      if (isExport) {
        removeFocusMarker(form);
        return;
      }

      addFocusMarker(form, key);
    });

    const hasValidationErrors = Boolean(document.querySelector(VALIDATION_ERROR_SELECTOR));

    if (requestedFocus === key && !hasValidationErrors) {
      collapseAndFocusResults();
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    const requestedFocus = new URLSearchParams(window.location.search).get(FOCUS_PARAMETER) || '';

    document.querySelectorAll('[data-swafi-query-workspace]').forEach(function (workspace, index) {
      initializeWorkspace(workspace, index, requestedFocus);
    });
  });
}());
