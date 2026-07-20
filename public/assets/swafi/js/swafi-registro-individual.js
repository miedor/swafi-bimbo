'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const selector = document.querySelector('[data-asset-selector]');
    const form = document.querySelector('[data-registration-form]');

    if (!selector || !form) {
        return;
    }

    const lookupUrl = selector.dataset.lookupUrl || '';
    const searchUrl = selector.dataset.searchUrl || '';
    const modeInput = form.querySelector('[data-asset-mode]');
    const numberInput = form.querySelector('[data-asset-number]');
    const searchButton = form.querySelector('[data-asset-search]');
    const newButton = form.querySelector('[data-asset-new]');
    const status = form.querySelector('[data-asset-status]');
    const summary = form.querySelector('[data-asset-summary]');
    const existingNotice = form.querySelector('[data-existing-asset-notice]');
    const assetFields = Array.from(form.querySelectorAll('[data-asset-field]'));

    const browser = form.querySelector('[data-asset-browser]');
    const browserQuery = form.querySelector('[data-asset-filter-query]');
    const browserProvider = form.querySelector('[data-asset-filter-provider]');
    const browserPlant = form.querySelector('[data-asset-filter-plant]');
    const browserSearch = form.querySelector('[data-asset-filter-search]');
    const browserClear = form.querySelector('[data-asset-filter-clear]');
    const browserStatus = form.querySelector('[data-asset-browser-status]');
    const results = form.querySelector('[data-asset-results]');
    const resultsBody = form.querySelector('[data-asset-results-body]');
    const paginationInfo = form.querySelector('[data-asset-pagination-info]');
    const paginationActions = form.querySelector('[data-asset-pagination-actions]');

    if (!lookupUrl || !modeInput || !numberInput || !searchButton || !newButton || !status) {
        return;
    }

    let selectedAssetNumber = '';
    let lookupController = null;
    let browserController = null;

    const normalizeNumber = (value) => String(value || '').trim().toUpperCase();

    const setStatus = (message, type = '') => {
        status.textContent = message;
        status.classList.remove('is-success', 'is-error');

        if (type === 'success') {
            status.classList.add('is-success');
        }

        if (type === 'error') {
            status.classList.add('is-error');
        }
    };

    const setBrowserStatus = (message, type = '') => {
        if (!browserStatus) {
            return;
        }

        browserStatus.textContent = message;
        browserStatus.classList.remove('is-error');

        if (type === 'error') {
            browserStatus.classList.add('is-error');
        }
    };

    const setFieldValue = (name, value) => {
        const field = form.elements.namedItem(name);

        if (!field) {
            return;
        }

        field.value = value === null || value === undefined ? '' : String(value);
    };

    const toggleAssetFields = (disabled) => {
        assetFields.forEach((field) => {
            field.disabled = disabled;
            field.setAttribute('aria-disabled', disabled ? 'true' : 'false');
        });
    };

    const clearAssetFields = () => {
        assetFields.forEach((field) => {
            field.value = '';
        });

        const operationalStatus = form.elements.namedItem('estatus_operativo');

        if (operationalStatus) {
            operationalStatus.value = 'en_operacion';
        }
    };

    const setSummaryText = (selectorName, value) => {
        const element = summary?.querySelector(selectorName);

        if (element) {
            element.textContent = value || 'Sin dato';
        }
    };

    const activateExistingMode = (asset) => {
        selectedAssetNumber = normalizeNumber(asset.numero_activo);
        modeInput.value = 'existing';
        numberInput.value = selectedAssetNumber;
        numberInput.readOnly = true;
        numberInput.setAttribute('aria-readonly', 'true');

        setFieldValue('tipo_activo_id', asset.tipo_activo_id);
        setFieldValue('proveedor_id', asset.proveedor_id);
        setFieldValue('centro_costo_id', asset.centro_costo_id);
        setFieldValue('planta_id', asset.planta_id);
        setFieldValue('ubicacion_id', asset.ubicacion_id);
        setFieldValue('responsable_id', asset.responsable_id);
        setFieldValue('descripcion', asset.descripcion);
        setFieldValue('serie', asset.serie);
        setFieldValue('marca', asset.marca);
        setFieldValue('modelo', asset.modelo);
        setFieldValue('fecha_adquisicion', asset.fecha_adquisicion);
        setFieldValue('estatus_operativo', asset.estatus_operativo);

        toggleAssetFields(true);
        searchButton.hidden = true;
        newButton.textContent = 'Cambiar o registrar otro activo';

        if (existingNotice) {
            existingNotice.hidden = false;
        }

        if (summary) {
            summary.hidden = false;
            setSummaryText('[data-summary-number]', selectedAssetNumber);
            setSummaryText('[data-summary-type]', asset.labels?.tipo_activo);
            setSummaryText('[data-summary-plant]', asset.labels?.planta);
            setSummaryText('[data-summary-expedientes]', String(asset.expedientes_vigentes ?? 0));
        }

        if (browser) {
            browser.open = false;
        }

        setStatus(
            'Activo vigente seleccionado. SWAFI registrará el expediente sin modificar los datos maestros del activo.',
            'success'
        );
    };

    const activateNewMode = ({ clear = false } = {}) => {
        selectedAssetNumber = '';
        modeInput.value = 'new';
        numberInput.readOnly = false;
        numberInput.removeAttribute('aria-readonly');
        searchButton.hidden = false;
        newButton.textContent = 'Registrar activo nuevo';
        toggleAssetFields(false);

        if (existingNotice) {
            existingNotice.hidden = true;
        }

        if (summary) {
            summary.hidden = true;
        }

        if (clear) {
            numberInput.value = '';
            clearAssetFields();
        }

        setStatus(
            'Modo de alta nueva. Si el número ya existe, utiliza “Buscar activo existente”.'
        );
        numberInput.focus();
    };

    const responseErrorMessage = async (response, fallback) => {
        try {
            const payload = await response.json();
            const errors = payload?.errors || {};
            const firstError = Object.values(errors)
                .flat()
                .find((message) => typeof message === 'string' && message.trim() !== '');

            return firstError || payload?.message || fallback;
        } catch (error) {
            return fallback;
        }
    };

    const lookupAsset = async (requestedNumber = null) => {
        const number = normalizeNumber(requestedNumber ?? numberInput.value);

        if (!/^[A-Z0-9][A-Z0-9._-]{0,29}$/.test(number)) {
            setStatus(
                'Captura un número válido con letras, números, punto, guion o guion bajo antes de buscar.',
                'error'
            );
            numberInput.focus();
            return false;
        }

        if (lookupController) {
            lookupController.abort();
        }

        const controller = new AbortController();
        lookupController = controller;
        searchButton.disabled = true;
        newButton.disabled = true;
        setStatus('Buscando el activo vigente en SWAFI...');

        const url = new URL(lookupUrl, window.location.origin);
        url.searchParams.set('numero_activo', number);

        try {
            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: controller.signal,
            });

            if (!response.ok) {
                setStatus(
                    await responseErrorMessage(response, 'No fue posible localizar el activo.'),
                    'error'
                );
                return false;
            }

            const payload = await response.json();

            if (!payload?.data) {
                setStatus('La respuesta del servidor no contiene un activo válido.', 'error');
                return false;
            }

            activateExistingMode(payload.data);
            return true;
        } catch (error) {
            if (error.name !== 'AbortError') {
                setStatus(
                    'No fue posible completar la búsqueda. Revisa la conexión e inténtalo nuevamente.',
                    'error'
                );
            }

            return false;
        } finally {
            if (lookupController === controller) {
                searchButton.disabled = false;
                newButton.disabled = false;
                lookupController = null;
            }
        }
    };

    const createCell = (primary, secondary = '') => {
        const cell = document.createElement('td');
        const strong = document.createElement('strong');
        strong.textContent = primary || 'Sin dato';
        cell.append(strong);

        if (secondary) {
            const small = document.createElement('small');
            small.textContent = secondary;
            cell.append(small);
        }

        return cell;
    };

    const createPaginationButton = (label, page, disabled, ariaLabel) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'ri-btn ri-btn-soft';
        button.textContent = label;
        button.disabled = disabled;
        button.setAttribute('aria-label', ariaLabel);
        button.addEventListener('click', () => searchAssets(page));

        return button;
    };

    const renderResults = (payload) => {
        if (!results || !resultsBody || !paginationInfo || !paginationActions) {
            return;
        }

        const items = Array.isArray(payload?.data) ? payload.data : [];
        const meta = payload?.meta || {};
        resultsBody.replaceChildren();
        paginationActions.replaceChildren();

        if (items.length === 0) {
            results.hidden = true;
            setBrowserStatus('No se encontraron activos vigentes con los criterios seleccionados.');
            return;
        }

        items.forEach((asset) => {
            const row = document.createElement('tr');
            row.append(createCell(asset.numero_activo, asset.labels?.tipo_activo));
            row.append(createCell(asset.descripcion, asset.serie ? `Serie: ${asset.serie}` : ''));
            row.append(createCell(asset.labels?.proveedor));
            row.append(createCell(asset.labels?.planta, asset.labels?.estatus_operativo));
            row.append(createCell(String(asset.expedientes_vigentes ?? 0)));

            const actionCell = document.createElement('td');
            const selectButton = document.createElement('button');
            selectButton.type = 'button';
            selectButton.className = 'ri-btn ri-btn-primary ri-result-select';
            selectButton.textContent = 'Seleccionar';
            selectButton.setAttribute('aria-label', `Seleccionar activo ${asset.numero_activo}`);
            selectButton.addEventListener('click', async () => {
                selectButton.disabled = true;
                setBrowserStatus(`Validando el activo ${asset.numero_activo}...`);
                numberInput.value = normalizeNumber(asset.numero_activo);
                const selected = await lookupAsset(asset.numero_activo);

                if (!selected) {
                    selectButton.disabled = false;
                    setBrowserStatus(
                        'El activo cambió o dejó de estar disponible. Actualiza la búsqueda e inténtalo nuevamente.',
                        'error'
                    );
                }
            });
            actionCell.append(selectButton);
            row.append(actionCell);
            resultsBody.append(row);
        });

        const currentPage = Number(meta.current_page || 1);
        const lastPage = Number(meta.last_page || 1);
        const total = Number(meta.total || items.length);
        const from = Number(meta.from || 1);
        const to = Number(meta.to || items.length);

        paginationInfo.textContent = `Mostrando ${from}-${to} de ${total} activo(s). Página ${currentPage} de ${lastPage}.`;
        paginationActions.append(
            createPaginationButton(
                'Anterior',
                Math.max(1, currentPage - 1),
                currentPage <= 1,
                'Mostrar la página anterior de activos'
            ),
            createPaginationButton(
                'Siguiente',
                Math.min(lastPage, currentPage + 1),
                currentPage >= lastPage,
                'Mostrar la página siguiente de activos'
            )
        );

        results.hidden = false;
        setBrowserStatus(`${total} activo(s) vigente(s) encontrado(s). Selecciona uno para continuar.`);
    };

    const searchAssets = async (page = 1) => {
        if (
            !searchUrl
            || !browserQuery
            || !browserProvider
            || !browserPlant
            || !browserSearch
            || !browserClear
        ) {
            return;
        }

        const query = normalizeNumber(browserQuery.value);
        const providerId = String(browserProvider.value || '').trim();
        const plantId = String(browserPlant.value || '').trim();

        if (query === '' && providerId === '' && plantId === '') {
            setBrowserStatus(
                'Captura al menos dos caracteres o selecciona un proveedor o una planta.',
                'error'
            );
            browserQuery.focus();
            return;
        }

        if (query !== '' && !/^[A-Z0-9][A-Z0-9._-]{1,29}$/.test(query)) {
            setBrowserStatus(
                'El criterio debe contener al menos dos caracteres válidos: letras, números, punto, guion o guion bajo.',
                'error'
            );
            browserQuery.focus();
            return;
        }

        if (browserController) {
            browserController.abort();
        }

        const controller = new AbortController();
        browserController = controller;
        browserSearch.disabled = true;
        browserClear.disabled = true;
        setBrowserStatus('Buscando activos vigentes...');

        const url = new URL(searchUrl, window.location.origin);
        url.searchParams.set('page', String(Math.max(1, Number(page) || 1)));
        url.searchParams.set('per_page', '8');

        if (query !== '') {
            url.searchParams.set('q', query);
        }

        if (providerId !== '') {
            url.searchParams.set('proveedor_id', providerId);
        }

        if (plantId !== '') {
            url.searchParams.set('planta_id', plantId);
        }

        try {
            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: controller.signal,
            });

            if (!response.ok) {
                if (results) {
                    results.hidden = true;
                }

                setBrowserStatus(
                    await responseErrorMessage(response, 'No fue posible completar la búsqueda de activos.'),
                    'error'
                );
                return;
            }

            renderResults(await response.json());
        } catch (error) {
            if (error.name !== 'AbortError') {
                if (results) {
                    results.hidden = true;
                }

                setBrowserStatus(
                    'No fue posible completar la búsqueda. Revisa la conexión e inténtalo nuevamente.',
                    'error'
                );
            }
        } finally {
            if (browserController === controller) {
                browserSearch.disabled = false;
                browserClear.disabled = false;
                browserController = null;
            }
        }
    };

    const clearBrowser = () => {
        if (browserQuery) {
            browserQuery.value = '';
        }

        if (browserProvider) {
            browserProvider.value = '';
        }

        if (browserPlant) {
            browserPlant.value = '';
        }

        if (resultsBody) {
            resultsBody.replaceChildren();
        }

        if (paginationActions) {
            paginationActions.replaceChildren();
        }

        if (results) {
            results.hidden = true;
        }

        setBrowserStatus('Captura al menos dos caracteres o selecciona un proveedor o una planta.');
        browserQuery?.focus();
    };

    searchButton.addEventListener('click', () => lookupAsset());

    newButton.addEventListener('click', () => {
        activateNewMode({ clear: modeInput.value === 'existing' });
    });

    numberInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !numberInput.readOnly) {
            event.preventDefault();
            lookupAsset();
        }
    });

    browserSearch?.addEventListener('click', () => searchAssets(1));
    browserClear?.addEventListener('click', clearBrowser);

    browserQuery?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            searchAssets(1);
        }
    });

    form.addEventListener('submit', (event) => {
        if (
            modeInput.value === 'existing'
            && normalizeNumber(numberInput.value) !== selectedAssetNumber
        ) {
            event.preventDefault();
            setStatus(
                'El activo seleccionado cambió. Búscalo nuevamente antes de guardar.',
                'error'
            );
        }
    });

    if (modeInput.value === 'existing' && normalizeNumber(numberInput.value) !== '') {
        lookupAsset();
    } else {
        activateNewMode();
    }
});
