'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const selector = document.querySelector('[data-asset-selector]');
    const form = document.querySelector('[data-registration-form]');

    if (!selector || !form) {
        return;
    }

    const lookupUrl = selector.dataset.lookupUrl || '';
    const modeInput = form.querySelector('[data-asset-mode]');
    const numberInput = form.querySelector('[data-asset-number]');
    const searchButton = form.querySelector('[data-asset-search]');
    const newButton = form.querySelector('[data-asset-new]');
    const status = form.querySelector('[data-asset-status]');
    const summary = form.querySelector('[data-asset-summary]');
    const existingNotice = form.querySelector('[data-existing-asset-notice]');
    const assetFields = Array.from(form.querySelectorAll('[data-asset-field]'));

    if (!lookupUrl || !modeInput || !numberInput || !searchButton || !newButton || !status) {
        return;
    }

    let selectedAssetNumber = '';
    let requestController = null;

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

    const responseErrorMessage = async (response) => {
        try {
            const payload = await response.json();
            const validationMessage = payload?.errors?.numero_activo?.[0];

            return validationMessage || payload?.message || 'No fue posible localizar el activo.';
        } catch (error) {
            return 'No fue posible localizar el activo.';
        }
    };

    const lookupAsset = async () => {
        const number = normalizeNumber(numberInput.value);

        if (!/^[A-Z0-9][A-Z0-9-]{0,29}$/.test(number)) {
            setStatus(
                'Captura un número válido con letras, números o guiones antes de buscar.',
                'error'
            );
            numberInput.focus();
            return;
        }

        if (requestController) {
            requestController.abort();
        }

        requestController = new AbortController();
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
                signal: requestController.signal,
            });

            if (!response.ok) {
                setStatus(await responseErrorMessage(response), 'error');
                return;
            }

            const payload = await response.json();

            if (!payload?.data) {
                setStatus('La respuesta del servidor no contiene un activo válido.', 'error');
                return;
            }

            activateExistingMode(payload.data);
        } catch (error) {
            if (error.name !== 'AbortError') {
                setStatus(
                    'No fue posible completar la búsqueda. Revisa la conexión e inténtalo nuevamente.',
                    'error'
                );
            }
        } finally {
            searchButton.disabled = false;
            newButton.disabled = false;
            requestController = null;
        }
    };

    searchButton.addEventListener('click', lookupAsset);

    newButton.addEventListener('click', () => {
        activateNewMode({ clear: modeInput.value === 'existing' });
    });

    numberInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !numberInput.readOnly) {
            event.preventDefault();
            lookupAsset();
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
