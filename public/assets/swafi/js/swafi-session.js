(() => {
  'use strict';

  const body = document.body;

  if (!body || body.dataset.swafiProtected !== 'true') {
    return;
  }

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const loginUrl = body.dataset.swafiLoginUrl || '/login';
  const logoutUrl = body.dataset.swafiLogoutUrl || '/logout';
  const heartbeatUrl = body.dataset.swafiHeartbeatUrl || '/sesion/actividad';

  const parsePositiveInteger = (value, fallback) => {
    const parsed = Number.parseInt(value, 10);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
  };

  const inactivityLimitMs = parsePositiveInteger(body.dataset.swafiInactivityMs, 600000);
  const warningWindowMs = Math.min(
    parsePositiveInteger(body.dataset.swafiWarningMs, 60000),
    Math.max(inactivityLimitMs - 1000, 1000)
  );
  const heartbeatEveryMs = parsePositiveInteger(body.dataset.swafiHeartbeatMs, 60000);

  const warningElement = document.getElementById('swafiSessionWarning');
  const warningText = document.getElementById('swafiSessionWarningText');

  let lastUserActivityAt = Date.now();
  let lastHeartbeatAt = 0;
  let inactivityTimer = null;
  let warningTimer = null;
  let warningCountdownTimer = null;
  let heartbeatInProgress = false;
  let terminatingSession = false;
  let lastRecordedEventAt = 0;

  const loginUrlWithReason = (reason) => {
    const url = new URL(loginUrl, window.location.origin);
    url.searchParams.set('motivo', reason);

    return url.toString();
  };

  const hideWarning = () => {
    window.clearInterval(warningCountdownTimer);
    warningCountdownTimer = null;

    if (warningElement) {
      warningElement.classList.remove('is-visible');
    }
  };

  const updateWarningText = () => {
    if (!warningText) {
      return;
    }

    const remainingMs = Math.max(inactivityLimitMs - (Date.now() - lastUserActivityAt), 0);
    const remainingSeconds = Math.ceil(remainingMs / 1000);

    warningText.textContent = `La sesión se cerrará en ${remainingSeconds} segundo${remainingSeconds === 1 ? '' : 's'} si no se detecta actividad.`;
  };

  const showWarning = () => {
    if (terminatingSession) {
      return;
    }

    if (warningElement) {
      warningElement.classList.add('is-visible');
    }

    updateWarningText();
    window.clearInterval(warningCountdownTimer);
    warningCountdownTimer = window.setInterval(updateWarningText, 1000);
  };

  const redirectToLogin = (reason) => {
    window.location.replace(loginUrlWithReason(reason));
  };

  const postSessionAction = async (url, reason, keepalive = false) => {
    const payload = new URLSearchParams();
    payload.set('_token', csrfToken);

    if (reason) {
      payload.set('motivo', reason);
    }

    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      redirect: 'follow',
      keepalive,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: payload.toString(),
    });
  };

  const clearTimers = () => {
    window.clearTimeout(inactivityTimer);
    window.clearTimeout(warningTimer);
    window.clearInterval(warningCountdownTimer);
    inactivityTimer = null;
    warningTimer = null;
    warningCountdownTimer = null;
  };

  const terminateSession = async (reason) => {
    if (terminatingSession) {
      return;
    }

    terminatingSession = true;
    clearTimers();
    hideWarning();

    document.documentElement.setAttribute('data-swafi-session-closing', 'true');

    const forcedRedirect = window.setTimeout(() => {
      redirectToLogin(reason);
    }, 1400);

    try {
      const response = await postSessionAction(logoutUrl, reason, true);

      if (response.ok) {
        try {
          const data = await response.json();

          if (data && typeof data.redirect === 'string' && data.redirect !== '') {
            window.clearTimeout(forcedRedirect);
            window.location.replace(data.redirect);
            return;
          }
        } catch (error) {
          // Una respuesta sin JSON no impide el cierre local de la navegación.
        }
      }
    } catch (error) {
      // El redireccionamiento forzado impide conservar la pantalla protegida.
    }

    window.clearTimeout(forcedRedirect);
    redirectToLogin(reason);
  };

  const sendHeartbeat = async () => {
    if (terminatingSession || heartbeatInProgress || document.visibilityState === 'hidden') {
      return;
    }

    const now = Date.now();

    if ((now - lastHeartbeatAt) < heartbeatEveryMs) {
      return;
    }

    heartbeatInProgress = true;

    try {
      const response = await postSessionAction(heartbeatUrl, '', false);

      if (!response.ok) {
        await terminateSession('sesion_invalida');
        return;
      }

      lastHeartbeatAt = Date.now();
    } catch (error) {
      /*
       * Una interrupción temporal de red no cierra inmediatamente la sesión.
       * El middleware del servidor validará la vigencia en la siguiente solicitud.
       */
    } finally {
      heartbeatInProgress = false;
    }
  };

  const scheduleInactivityChecks = () => {
    window.clearTimeout(inactivityTimer);
    window.clearTimeout(warningTimer);
    hideWarning();

    const elapsedMs = Date.now() - lastUserActivityAt;
    const remainingMs = Math.max(inactivityLimitMs - elapsedMs, 0);
    const warningDelayMs = Math.max(remainingMs - warningWindowMs, 0);

    if (remainingMs <= 0) {
      void terminateSession('inactividad');
      return;
    }

    warningTimer = window.setTimeout(showWarning, warningDelayMs);
    inactivityTimer = window.setTimeout(() => {
      void terminateSession('inactividad');
    }, remainingMs);
  };

  const recordUserActivity = () => {
    if (terminatingSession) {
      return;
    }

    const now = Date.now();

    /* Evita procesar cientos de eventos mousemove por segundo. */
    if ((now - lastRecordedEventAt) < 750) {
      return;
    }

    lastRecordedEventAt = now;
    lastUserActivityAt = now;
    scheduleInactivityChecks();
    void sendHeartbeat();
  };

  const installHistoryGuard = () => {
    const navigationEntry = performance.getEntriesByType('navigation')[0];

    if (navigationEntry && navigationEntry.type === 'back_forward') {
      void terminateSession('navegacion_atras');
      return;
    }

    const currentState = history.state && typeof history.state === 'object'
      ? history.state
      : {};

    history.replaceState(
      { ...currentState, swafiProtectedEntry: true },
      document.title,
      window.location.href
    );

    history.pushState(
      { swafiHistoryGuard: true },
      document.title,
      window.location.href
    );

    window.addEventListener('popstate', () => {
      void terminateSession('navegacion_atras');
    });

    window.addEventListener('pageshow', (event) => {
      if (event.persisted) {
        void terminateSession('cache_restaurada');
      }
    });
  };

  ['click', 'keydown', 'pointerdown', 'touchstart', 'scroll', 'mousemove'].forEach((eventName) => {
    document.addEventListener(eventName, recordUserActivity, {
      passive: true,
      capture: false,
    });
  });

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState !== 'visible' || terminatingSession) {
      return;
    }

    if ((Date.now() - lastUserActivityAt) >= inactivityLimitMs) {
      void terminateSession('inactividad');
      return;
    }

    scheduleInactivityChecks();
    void sendHeartbeat();
  });

  document.querySelectorAll('form[action$="/logout"]').forEach((form) => {
    form.addEventListener('submit', () => {
      terminatingSession = true;
      clearTimers();
      hideWarning();
    });
  });

  installHistoryGuard();
  scheduleInactivityChecks();
  void sendHeartbeat();
})();
