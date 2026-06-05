document.addEventListener('DOMContentLoaded', () => {
  /*
   * Navegación demo existente
   */
  document.querySelectorAll('[data-demo-nav]').forEach((btn) => {
    btn.addEventListener('click', (event) => {
      event.preventDefault();

      const to = btn.getAttribute('href');

      if (to) {
        window.location.href = to;
      }
    });
  });

  /*
   * Menú lateral desplegable por módulos
   * - Permite abrir/cerrar cada módulo.
   * - Conserva el estado en localStorage.
   * - Si hay una página activa dentro de un módulo, ese módulo inicia abierto.
   */
  const storageKey = 'swafi.sidebar.modules';
  const toggles = document.querySelectorAll('[data-nav-toggle]');
  const groups = document.querySelectorAll('[data-nav-group]');

  let savedState = {};

  try {
    savedState = JSON.parse(localStorage.getItem(storageKey) || '{}');
  } catch (error) {
    savedState = {};
  }

  const setModuleState = (moduleId, isOpen) => {
    const toggle = document.querySelector(`[data-nav-toggle="${moduleId}"]`);
    const group = document.querySelector(`[data-nav-group="${moduleId}"]`);

    if (!toggle || !group) {
      return;
    }

    toggle.classList.toggle('is-open', isOpen);
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    group.classList.toggle('is-open', isOpen);
  };

  const saveModuleState = (moduleId, isOpen) => {
    savedState[moduleId] = isOpen;

    try {
      localStorage.setItem(storageKey, JSON.stringify(savedState));
    } catch (error) {
      /*
       * Si el navegador bloquea localStorage, el menú sigue funcionando
       * durante la sesión actual.
       */
    }
  };

  /*
   * Aplica el estado guardado. Si el grupo ya viene abierto desde Blade
   * por tener una ruta activa, se respeta como prioridad.
   */
  groups.forEach((group) => {
    const moduleId = group.getAttribute('data-nav-group');
    const toggle = document.querySelector(`[data-nav-toggle="${moduleId}"]`);

    if (!moduleId || !toggle) {
      return;
    }

    const hasActiveItem = group.querySelector('.nav-item.active') !== null;

    if (hasActiveItem) {
      setModuleState(moduleId, true);
      return;
    }

    if (Object.prototype.hasOwnProperty.call(savedState, moduleId)) {
      setModuleState(moduleId, Boolean(savedState[moduleId]));
    }
  });

  toggles.forEach((toggle) => {
    toggle.addEventListener('click', () => {
      const moduleId = toggle.getAttribute('data-nav-toggle');
      const group = document.querySelector(`[data-nav-group="${moduleId}"]`);

      if (!moduleId || !group) {
        return;
      }

      const isOpen = !group.classList.contains('is-open');

      setModuleState(moduleId, isOpen);
      saveModuleState(moduleId, isOpen);
    });
  });
});
