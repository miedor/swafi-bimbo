@extends('layouts.app')

@section('title', 'Valores fiscales y financieros | SWAFI')
@section('page_title', 'Valores fiscales y financieros')
@section('page_subtitle', 'Control contable, moneda, tipo de cambio y referencia técnica del XML')
@section('breadcrumb', 'Valores fiscales y financieros')

@php
  $panelSolicitado = (string) request('panel', '');
  $errorImportacion = $errors->has('archivo_csv')
    || $errors->has('lote')
    || $errors->has('confirmar_aplicacion');
  $errorCaptura = $errors->any() && !$errorImportacion;

  if (!$canAdministrarValores) {
    $panelActivo = 'consulta';
  } elseif ($valorEdit || $errorCaptura || $panelSolicitado === 'captura') {
    $panelActivo = 'captura';
  } elseif ($importBatch || $errorImportacion || $panelSolicitado === 'importar') {
    $panelActivo = 'importar';
  } else {
    $panelActivo = 'consulta';
  }

  $selectedAsset = old('numero_activo', $valorEdit->numero_activo ?? request('numero_activo', ''));
  $selectedCurrency = strtoupper((string) old('moneda', $valorEdit->moneda ?? 'MXN'));
  $selectedStatus = old('estatus_contable', $valorEdit->estatus_contable ?? 'vigente');
@endphp

@section('page_styles')
<style nonce="{{ request()->attributes->get('csp_nonce') }}">
  .vf-shell,
  .vf-card,
  .vf-panel,
  .vf-table-scroll {
    width: 100%;
    max-width: 100%;
    min-width: 0;
  }

  .vf-shell {
    display: grid;
    gap: 14px;
    overflow-x: hidden;
  }

  .vf-card {
    padding: 16px;
    border: 1px solid #dbe7f6;
    border-radius: 20px;
    background: #ffffff;
    box-shadow: 0 12px 28px rgba(15, 23, 42, .06);
    overflow: hidden;
  }

  .vf-tabs {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  .vf-tab-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 38px;
    padding: 9px 14px;
    border: 1px solid #d6e4f5;
    border-radius: 999px;
    background: #f7fbff;
    color: #174f9a;
    font: inherit;
    font-size: 13px;
    font-weight: 900;
    cursor: pointer;
    transition: background .15s ease, color .15s ease, transform .15s ease;
  }

  .vf-tab-button:hover {
    transform: translateY(-1px);
    background: #edf5ff;
  }

  .vf-tab-button.is-active {
    border-color: #174f9a;
    background: #174f9a;
    color: #ffffff;
    box-shadow: 0 8px 18px rgba(23, 79, 154, .18);
  }

  .vf-access-summary {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 11px;
    border-radius: 999px;
    background: {{ $canAdministrarValores ? '#e8f7ea' : '#eef6ff' }};
    color: {{ $canAdministrarValores ? '#1f6b2a' : '#174f9a' }};
    font-size: 12px;
    font-weight: 900;
    white-space: nowrap;
  }

  .vf-access-summary::before {
    content: '';
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: currentColor;
  }

  .vf-panel {
    display: none;
  }

  .vf-panel.is-active {
    display: block;
  }

  .vf-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
  }

  .vf-title h2 {
    margin: 0;
    color: #152f52;
    font-size: 18px;
    font-weight: 950;
  }

  .vf-title p {
    margin: 3px 0 0;
    color: #64748b;
    font-size: 12px;
    line-height: 1.4;
  }

  .vf-form {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
  }

  .vf-filters {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
  }

  .vf-field {
    min-width: 0;
  }

  .vf-field.full {
    grid-column: 1 / -1;
  }

  .vf-field.span-2 {
    grid-column: span 2;
  }

  .vf-field span {
    display: block;
    margin-bottom: 5px;
    color: #1d3558;
    font-size: 12px;
    font-weight: 900;
  }

  .vf-field input,
  .vf-field select,
  .vf-field textarea {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    min-height: 39px;
    padding: 8px 10px;
    border: 1px solid #d5e1ef;
    border-radius: 11px;
    background: #ffffff;
    color: #16304d;
    font-size: 13px;
    font-weight: 750;
  }

  .vf-field textarea {
    min-height: 72px;
    resize: vertical;
  }

  .vf-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
  }

  .vf-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    padding: 9px 14px;
    border: 1px solid #174f9a;
    border-radius: 999px;
    background: #ffffff;
    color: #174f9a;
    font: inherit;
    font-size: 13px;
    font-weight: 900;
    text-decoration: none;
    cursor: pointer;
  }

  .vf-button.primary {
    background: #174f9a;
    color: #ffffff;
  }

  .vf-button.soft {
    border-color: #d6e4f5;
    background: #eef5ff;
  }

  .vf-message {
    padding: 11px 13px;
    border-radius: 13px;
    font-weight: 800;
    line-height: 1.45;
  }

  .vf-message ul {
    margin: 7px 0 0;
    padding-left: 20px;
  }

  .vf-success {
    border: 1px solid #b9e5bf;
    background: #e8f7ea;
    color: #1f6b2a;
  }

  .vf-error {
    border: 1px solid #facc15;
    background: #fff4d6;
    color: #7a4b00;
  }

  .vf-info,
  .vf-readonly {
    border: 1px solid #c8dcf7;
    background: #eef6ff;
    color: #174f9a;
  }

  .vf-readonly {
    padding: 11px 13px;
    border-radius: 13px;
    font-size: 12px;
    font-weight: 850;
    line-height: 1.45;
  }

  .vf-import-box {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(290px, .55fr);
    gap: 16px;
    align-items: start;
  }

  .vf-import-guide {
    padding: 14px;
    border: 1px dashed #b8cbe5;
    border-radius: 15px;
    background: #f8fbff;
  }

  .vf-import-guide h3 {
    margin: 0 0 7px;
    color: #152f52;
    font-size: 15px;
  }

  .vf-import-guide p,
  .vf-import-guide li {
    color: #64748b;
    font-size: 12px;
    line-height: 1.45;
  }

  .vf-import-guide ul {
    margin: 8px 0 0;
    padding-left: 18px;
  }

  .vf-preview {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #e2ebf6;
  }

  .vf-preview-header {
    display: grid;
    gap: 12px;
    margin-bottom: 12px;
    padding: 14px;
    border: 1px solid #dbe7f6;
    border-radius: 16px;
    background: linear-gradient(135deg, #f8fbff 0%, #ffffff 72%);
  }

  .vf-preview-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
  }

  .vf-preview-heading h2 {
    margin: 0;
    color: #152f52;
    font-size: 18px;
    font-weight: 950;
  }

  .vf-preview-heading p {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 12px;
    line-height: 1.45;
  }

  .vf-preview-meta-grid {
    display: grid;
    grid-template-columns: minmax(190px, 1.3fr) repeat(2, minmax(145px, .75fr)) minmax(230px, 1fr);
    gap: 8px;
  }

  .vf-preview-meta-item {
    min-width: 0;
    padding: 9px 10px;
    border: 1px solid #e2ebf6;
    border-radius: 12px;
    background: #ffffff;
  }

  .vf-preview-meta-item span {
    display: block;
    margin-bottom: 3px;
    color: #74849a;
    font-size: 9px;
    font-weight: 900;
    letter-spacing: .05em;
    text-transform: uppercase;
  }

  .vf-preview-meta-item strong {
    display: block;
    overflow: hidden;
    color: #1d3558;
    font-size: 11px;
    font-weight: 850;
    line-height: 1.35;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .vf-preview-meta-item.is-code strong {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 10px;
  }

  .vf-preview-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 9px;
    margin-bottom: 12px;
  }

  .vf-preview-stat {
    position: relative;
    min-height: 78px;
    padding: 12px 13px 11px 16px;
    overflow: hidden;
    border: 1px solid #dbe7f6;
    border-radius: 14px;
    background: #f8fbff;
  }

  .vf-preview-stat::before {
    content: '';
    position: absolute;
    inset: 0 auto 0 0;
    width: 4px;
    background: #6b8fbd;
  }

  .vf-preview-stat strong {
    display: block;
    color: #152f52;
    font-size: 22px;
    font-weight: 950;
    line-height: 1;
  }

  .vf-preview-stat span {
    display: block;
    margin-top: 7px;
    color: #64748b;
    font-size: 11px;
    font-weight: 850;
  }

  .vf-preview-stat.correct {
    border-color: #b9e5bf;
    background: #eefbf2;
  }

  .vf-preview-stat.correct::before {
    background: #2f8f46;
  }

  .vf-preview-stat.incorrect {
    border-color: #efc0c0;
    background: #fff3f2;
  }

  .vf-preview-stat.incorrect::before {
    background: #c53d35;
  }

  .vf-preview-stat.insert {
    border-color: #c8dcf7;
    background: #eef6ff;
  }

  .vf-preview-stat.insert::before {
    background: #174f9a;
  }

  .vf-preview-toolbar {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
    align-items: end;
    margin-bottom: 10px;
    padding: 11px 12px;
    border: 1px solid #e2ebf6;
    border-radius: 14px;
    background: #f8fbff;
  }

  .vf-preview-filter {
    display: grid;
    grid-template-columns: minmax(220px, 330px) auto;
    gap: 8px;
    align-items: end;
  }

  .vf-preview-table-wrap {
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    border: 1px solid #dfe8f4;
    border-radius: 15px;
    background: #ffffff;
    scrollbar-gutter: stable;
    overscroll-behavior-inline: contain;
    -webkit-overflow-scrolling: touch;
  }

  .vf-preview-table {
    width: 100%;
    min-width: 1040px;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
    font-size: 11px;
  }

  .vf-preview-table th,
  .vf-preview-table td {
    padding: 10px;
    border-bottom: 1px solid #e7eef8;
    text-align: left;
    vertical-align: top;
  }

  .vf-preview-table th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f3f7fc;
    color: #48617f;
    font-size: 9px;
    font-weight: 950;
    letter-spacing: .045em;
    text-transform: uppercase;
  }

  .vf-preview-table th:nth-child(1) { width: 145px; }
  .vf-preview-table th:nth-child(2) { width: 105px; }
  .vf-preview-table th:nth-child(3) { width: 105px; }
  .vf-preview-table th:nth-child(4) { width: 310px; }
  .vf-preview-table th:nth-child(5) { width: 235px; }
  .vf-preview-table th:nth-child(6) { width: auto; }

  .vf-preview-table tbody tr:last-child td {
    border-bottom: 0;
  }

  .vf-preview-table tbody tr.is-correct td:first-child {
    box-shadow: inset 4px 0 0 #2f8f46;
  }

  .vf-preview-table tbody tr.is-incorrect {
    background: #fffafa;
  }

  .vf-preview-table tbody tr.is-incorrect td:first-child {
    box-shadow: inset 4px 0 0 #c53d35;
  }

  .vf-preview-asset {
    display: grid;
    grid-template-columns: 31px minmax(0, 1fr);
    gap: 8px;
    align-items: center;
  }

  .vf-preview-row-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 31px;
    height: 31px;
    border-radius: 9px;
    background: #eaf2fc;
    color: #174f9a;
    font-size: 11px;
    font-weight: 950;
  }

  .vf-preview-asset strong {
    display: block;
    color: #152f52;
    font-size: 11px;
    line-height: 1.25;
  }

  .vf-preview-asset small {
    display: block;
    margin-top: 2px;
    color: #74849a;
    font-size: 9px;
    font-weight: 750;
  }

  .vf-preview-action {
    display: inline-flex;
    align-items: center;
    min-height: 25px;
    padding: 5px 8px;
    border: 1px solid #d7e3f2;
    border-radius: 999px;
    background: #ffffff;
    color: #355775;
    font-size: 9px;
    font-weight: 900;
  }

  .vf-preview-values,
  .vf-preview-parameters {
    display: grid;
    gap: 6px;
  }

  .vf-preview-values {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .vf-preview-parameters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .vf-preview-data {
    min-width: 0;
    padding: 6px 7px;
    border: 1px solid #e6edf7;
    border-radius: 9px;
    background: #fbfdff;
  }

  .vf-preview-data span {
    display: block;
    margin-bottom: 2px;
    color: #7a899d;
    font-size: 8px;
    font-weight: 900;
    letter-spacing: .035em;
    text-transform: uppercase;
  }

  .vf-preview-data-wide {
    grid-column: 1 / -1;
  }

  .vf-preview-data strong {
    display: block;
    overflow-wrap: anywhere;
    color: #1d3558;
    font-size: 10px;
    font-weight: 900;
    line-height: 1.3;
  }

  .vf-preview-validation {
    color: #52657c;
    font-size: 10px;
    line-height: 1.45;
  }

  .vf-preview-validation.is-ready {
    display: flex;
    gap: 7px;
    align-items: flex-start;
    padding: 7px 8px;
    border-radius: 10px;
    background: #eefbf2;
    color: #1f6b2a;
    font-weight: 800;
  }

  .vf-preview-validation.is-ready::before {
    content: '✓';
    flex: 0 0 auto;
    font-weight: 950;
  }

  .vf-preview-errors {
    margin: 0;
    padding-left: 16px;
    color: #b42318;
    font-size: 10px;
    line-height: 1.45;
  }

  .vf-preview-footer {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
    align-items: center;
    gap: 12px;
    margin-top: 9px;
    padding: 0 2px;
    color: #64748b;
    font-size: 10px;
  }

  .vf-preview-footer > :last-child {
    text-align: right;
  }

  .vf-preview-decision {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(360px, .9fr);
    gap: 14px;
    align-items: center;
    margin-top: 13px;
    padding: 14px;
    border: 1px solid #c8dcf7;
    border-radius: 16px;
    background: linear-gradient(135deg, #eef6ff 0%, #f8fbff 70%, #ffffff 100%);
  }

  .vf-preview-decision-copy {
    display: grid;
    grid-template-columns: 38px minmax(0, 1fr);
    gap: 11px;
    align-items: start;
  }

  .vf-preview-decision-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 12px;
    background: #174f9a;
    color: #ffffff;
    font-size: 18px;
    font-weight: 950;
    box-shadow: 0 8px 18px rgba(23, 79, 154, .18);
  }

  .vf-preview-decision-copy h3 {
    margin: 0;
    color: #152f52;
    font-size: 14px;
    font-weight: 950;
  }

  .vf-preview-decision-copy p {
    margin: 4px 0 0;
    color: #52657c;
    font-size: 11px;
    line-height: 1.45;
  }

  .vf-preview-decision-actions {
    display: grid;
    gap: 9px;
  }

  .vf-preview-apply-form {
    display: grid;
    gap: 9px;
  }

  .vf-preview-confirmation-check {
    display: grid;
    grid-template-columns: 20px minmax(0, 1fr);
    gap: 9px;
    align-items: start;
    margin: 0;
    padding: 10px 11px;
    border: 1px solid #d7e4f4;
    border-radius: 12px;
    background: #ffffff;
    color: #1d3558;
    font-size: 11px;
    font-weight: 800;
    line-height: 1.4;
    cursor: pointer;
  }

  .vf-preview-confirmation-check input[type="checkbox"] {
    appearance: auto;
    width: 20px;
    height: 20px;
    min-width: 20px;
    min-height: 20px;
    margin: 0;
    padding: 0;
    border: 0;
    border-radius: 4px;
    flex: 0 0 auto;
    accent-color: #174f9a;
    cursor: pointer;
  }

  .vf-preview-decision-buttons {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    flex-wrap: wrap;
  }

  .vf-preview-cancel-form {
    margin: 0;
  }

  .vf-table-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: 14px 0 9px;
  }

  .vf-table-head strong {
    color: #152f52;
    font-size: 15px;
  }

  .vf-scroll-hint {
    color: #64748b;
    font-size: 11px;
    font-weight: 750;
  }

  .vf-table-scroll {
    overflow-x: auto;
    overflow-y: hidden;
    border: 1px solid #e2ebf6;
    border-radius: 16px;
    scrollbar-gutter: stable;
    overscroll-behavior-inline: contain;
    -webkit-overflow-scrolling: touch;
  }

  .vf-table-scroll table {
    width: 100%;
    min-width: 1110px;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 12px;
  }

  .vf-table-scroll th,
  .vf-table-scroll td {
    padding: 11px 10px;
    border-bottom: 1px solid #e7eef8;
    text-align: left;
    vertical-align: top;
    overflow-wrap: anywhere;
  }

  .vf-table-scroll th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f6faff;
    color: #48617f;
    font-size: 11px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .03em;
  }

  .vf-table-scroll th:nth-child(1) { width: 155px; }
  .vf-table-scroll th:nth-child(2) { width: 190px; }
  .vf-table-scroll th:nth-child(3) { width: 175px; }
  .vf-table-scroll th:nth-child(4) { width: 95px; }
  .vf-table-scroll th:nth-child(5) { width: 105px; }
  .vf-table-scroll th:nth-child(6) { width: 190px; }
  .vf-table-scroll th:nth-child(7) { width: 120px; }
  .vf-table-scroll th:nth-child(8) { width: 120px; }

  .vf-table-scroll tbody tr:hover {
    background: #fbfdff;
  }

  .vf-status {
    display: inline-flex;
    padding: 5px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 900;
  }

  .vf-status.ok {
    background: #e8f7ea;
    color: #1f6b2a;
  }

  .vf-status.warn {
    background: #fff4d6;
    color: #8a4b00;
  }

  .vf-status.danger {
    background: #fff0ee;
    color: #b42318;
  }

  .vf-details {
    margin-top: 5px;
    color: #64748b;
    font-size: 10px;
    line-height: 1.35;
  }

  .vf-row-actions {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    flex-wrap: wrap;
  }

  .vf-row-actions a,
  .vf-row-actions button {
    border: 0;
    background: none;
    color: #174f9a;
    font: inherit;
    font-size: 11px;
    font-weight: 900;
    text-decoration: none;
    cursor: pointer;
    padding: 0;
  }

  .vf-row-actions button.danger {
    color: #b42318;
  }

  .vf-form-note {
    grid-column: 1 / -1;
  }

  .vf-footer {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
    align-items: center;
    gap: 12px;
    margin-top: 10px;
    color: #64748b;
    font-size: 11px;
  }

  .vf-footer > :last-child {
    text-align: right;
  }

  .vf-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
  }

  .vf-page-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 32px;
    padding: 7px 10px;
    border: 1px solid #d6e4f5;
    border-radius: 10px;
    background: #ffffff;
    color: #174f9a;
    font-weight: 900;
    text-decoration: none;
  }

  .vf-page-link.active {
    border-color: #174f9a;
    background: #174f9a;
    color: #ffffff;
  }

  .vf-page-link.disabled {
    color: #94a3b8;
    cursor: not-allowed;
  }

  @media (max-width: 1250px) {
    .vf-form,
    .vf-filters {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }

  @media (max-width: 980px) {
    .vf-form,
    .vf-filters {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .vf-import-box {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .vf-preview-meta-grid,
    .vf-preview-summary {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .vf-preview-decision {
      grid-template-columns: minmax(0, 1fr);
      align-items: start;
    }

    .vf-footer {
      grid-template-columns: minmax(0, 1fr);
      justify-items: start;
    }

    .vf-footer > :last-child {
      text-align: left;
    }
  }

  @media (max-width: 680px) {
    .vf-card {
      padding: 13px;
      border-radius: 16px;
    }

    .vf-tabs,
    .vf-actions {
      align-items: stretch;
    }

    .vf-tab-button,
    .vf-button {
      flex: 1 1 100%;
    }

    .vf-access-summary {
      width: 100%;
      margin-left: 0;
      justify-content: center;
    }

    .vf-form,
    .vf-filters {
      grid-template-columns: minmax(0, 1fr);
    }

    .vf-field.span-2,
    .vf-field.full {
      grid-column: auto;
    }

    .vf-import-box,
    .vf-preview-meta-grid,
    .vf-preview-summary,
    .vf-preview-toolbar,
    .vf-preview-filter,
    .vf-preview-footer {
      grid-template-columns: minmax(0, 1fr);
    }

    .vf-preview-heading {
      align-items: flex-start;
    }

    .vf-preview-values,
    .vf-preview-parameters {
      grid-template-columns: minmax(0, 1fr);
    }

    .vf-preview-data-wide {
      grid-column: auto;
    }

    .vf-preview-decision-buttons,
    .vf-preview-decision-buttons .vf-button {
      width: 100%;
    }

    .vf-preview-footer > :last-child {
      text-align: left;
    }
  }
</style>
@endsection

@section('content')
<div class="vf-shell" data-values-page data-active-panel="{{ $panelActivo }}">
  @if(session('success'))
    <div class="vf-message vf-success">{{ session('success') }}</div>
  @endif

  @if(session('import_summary'))
    @php
      $summary = session('import_summary');
    @endphp
    <div class="vf-message vf-info">
      <strong>Carga masiva:</strong>
      {{ $summary['procesados'] ?? 0 }} procesados,
      {{ $summary['insertados'] ?? 0 }} insertados,
      {{ $summary['actualizados'] ?? 0 }} actualizados,
      {{ $summary['restaurados'] ?? 0 }} restaurados y
      {{ $summary['rechazados'] ?? 0 }} rechazados.

      @if(!empty($summary['errores']))
        <ul>
          @foreach(array_slice($summary['errores'], 0, 15) as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      @endif
    </div>
  @endif

  @if($errors->any())
    <div class="vf-message vf-error">
      <strong>Corrige los siguientes datos:</strong>
      <ul>
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @unless($canAdministrarValores)
    <div class="vf-readonly">
      @if($canViewSensitiveValues)
        Tu perfil cuenta con consulta fiscal y financiera completa en modo de solo lectura. La creación, edición, carga masiva y eliminación permanece reservada para Administrador SWAFI y Usuario Captura.
      @else
        Tu perfil cuenta con consulta operativa básica. Por seguridad, SWAFI oculta montos, proveedor, factura, moneda, tipo de cambio e historial financiero. Las exportaciones incluyen únicamente las columnas operativas visibles para tu perfil.
      @endif
    </div>
  @endunless

  <section class="vf-card">
    <div class="vf-tabs" role="tablist" aria-label="Opciones de valores fiscales y financieros">
      <button
        type="button"
        class="vf-tab-button {{ $panelActivo === 'consulta' ? 'is-active' : '' }}"
        data-vf-tab="consulta"
        role="tab"
        aria-selected="{{ $panelActivo === 'consulta' ? 'true' : 'false' }}"
      >
        Consulta y resultados
      </button>

      @if($canAdministrarValores)
        <button
          type="button"
          class="vf-tab-button {{ $panelActivo === 'captura' ? 'is-active' : '' }}"
          data-vf-tab="captura"
          role="tab"
          aria-selected="{{ $panelActivo === 'captura' ? 'true' : 'false' }}"
        >
          {{ $valorEdit ? 'Editar valores' : 'Captura contable' }}
        </button>

        <button
          type="button"
          class="vf-tab-button {{ $panelActivo === 'importar' ? 'is-active' : '' }}"
          data-vf-tab="importar"
          role="tab"
          aria-selected="{{ $panelActivo === 'importar' ? 'true' : 'false' }}"
        >
          Carga masiva
        </button>
      @endif

      <span class="vf-access-summary">
        {{ $canAdministrarValores ? 'Administración autorizada' : 'Consulta autorizada' }}
      </span>
    </div>
  </section>

  <section
    class="vf-card vf-panel {{ $panelActivo === 'consulta' ? 'is-active' : '' }}"
    data-vf-panel="consulta"
    data-swafi-query-workspace
    data-swafi-query-key="valores"
    role="tabpanel"
  >
    <div data-swafi-query-panel>
    <div class="vf-title">
      <div>
        <h2>Filtros de consulta</h2>
        <p>
          @if($canViewSensitiveValues)
            Localiza activos por estructura organizacional, estatus, soporte XML, moneda, fecha o valor.
          @else
            Localiza activos por estructura organizacional y estatus, sin exponer información fiscal o financiera sensible.
          @endif
        </p>
      </div>
      <span class="pill ok">{{ $resultados->total() }} resultado(s)</span>
    </div>

    <form method="GET" action="{{ route('valores') }}" data-swafi-query-form>
      <input type="hidden" name="panel" value="consulta">

      <div class="vf-filters">
        <label class="vf-field">
          <span>Número de activo</span>
          <input name="numero_activo" value="{{ $filtros['numero_activo'] ?? '' }}">
        </label>

        <label class="vf-field">
          <span>Planta</span>
          <select name="planta_id">
            <option value="">Todas</option>
            @foreach($catalogos['plantas'] as $item)
              <option value="{{ $item->id }}" {{ (string)($filtros['planta_id'] ?? '') === (string)$item->id ? 'selected' : '' }}>{{ $item->nombre }}</option>
            @endforeach
          </select>
        </label>

        @if($canViewSensitiveValues)
        <label class="vf-field">
          <span>Proveedor</span>
          <select name="proveedor_id">
            <option value="">Todos</option>
            @foreach($catalogos['proveedores'] as $item)
              <option value="{{ $item->id }}" {{ (string)($filtros['proveedor_id'] ?? '') === (string)$item->id ? 'selected' : '' }}>{{ $item->nombre }}</option>
            @endforeach
          </select>
        </label>

        @endif

        <label class="vf-field">
          <span>Centro de costo</span>
          <select name="centro_costo_id">
            <option value="">Todos</option>
            @foreach($catalogos['centrosCosto'] as $item)
              <option value="{{ $item->id }}" {{ (string)($filtros['centro_costo_id'] ?? '') === (string)$item->id ? 'selected' : '' }}>{{ $item->clave }}</option>
            @endforeach
          </select>
        </label>

        <label class="vf-field">
          <span>Tipo de activo</span>
          <select name="tipo_activo_id">
            <option value="">Todos</option>
            @foreach($catalogos['tiposActivo'] as $item)
              <option value="{{ $item->id }}" {{ (string)($filtros['tipo_activo_id'] ?? '') === (string)$item->id ? 'selected' : '' }}>{{ $item->descripcion }}</option>
            @endforeach
          </select>
        </label>

        <label class="vf-field">
          <span>Estatus contable</span>
          <select name="estatus_contable">
            <option value="">Todos</option>
            @foreach($catalogos['estatusContables'] as $estatus)
              <option value="{{ $estatus->clave }}" {{ ($filtros['estatus_contable'] ?? '') === $estatus->clave ? 'selected' : '' }}>
                {{ $estatus->nombre }}
              </option>
            @endforeach
          </select>
        </label>

        <label class="vf-field">
          <span>Estado técnico del XML</span>
          <select name="conciliacion_cfdi">
            <option value="">Todas</option>
            <option value="validado" {{ ($filtros['conciliacion_cfdi'] ?? '') === 'validado' ? 'selected' : '' }}>Validado</option>
            <option value="observado" {{ ($filtros['conciliacion_cfdi'] ?? '') === 'observado' ? 'selected' : '' }}>Observado</option>
            <option value="sin_xml" {{ ($filtros['conciliacion_cfdi'] ?? '') === 'sin_xml' ? 'selected' : '' }}>Sin XML validado</option>
          </select>
        </label>

        @if($canViewSensitiveValues)
        <label class="vf-field">
          <span>Moneda</span>
          <select name="moneda">
            <option value="">Todas</option>
            @foreach($catalogos['monedas'] as $moneda)
              <option value="{{ $moneda->clave }}" {{ ($filtros['moneda'] ?? '') === $moneda->clave ? 'selected' : '' }}>
                {{ $moneda->clave }} — {{ $moneda->nombre }}
              </option>
            @endforeach
          </select>
        </label>

        <label class="vf-field">
          <span>Fecha desde</span>
          <input type="date" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}">
        </label>

        <label class="vf-field">
          <span>Fecha hasta</span>
          <input type="date" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}">
        </label>

        <label class="vf-field">
          <span>Valor desde</span>
          <input type="number" step="0.01" name="valor_desde" value="{{ $filtros['valor_desde'] ?? '' }}">
        </label>

        <label class="vf-field">
          <span>Valor hasta</span>
          <input type="number" step="0.01" name="valor_hasta" value="{{ $filtros['valor_hasta'] ?? '' }}">
        </label>

        @endif

        <label class="vf-field">
          <span>Registros por página</span>
          <select name="per_page">
            @foreach([10, 25, 50, 100] as $size)
              <option value="{{ $size }}" {{ (string)($filtros['per_page'] ?? 10) === (string)$size ? 'selected' : '' }}>{{ $size }}</option>
            @endforeach
          </select>
        </label>
      </div>

      <div class="vf-actions">
        <button class="vf-button primary" type="submit">Consultar</button>

        @if($canExportarValores)
          <button class="vf-button" type="submit" name="export" value="csv">Exportar CSV</button>
          <button class="vf-button" type="submit" name="export" value="xlsx">Exportar Excel</button>
          <button class="vf-button" type="submit" name="export" value="pdf">Exportar PDF</button>
        @endif

        <a class="vf-button soft" href="{{ route('valores', ['panel' => 'consulta']) }}">Limpiar filtros</a>
      </div>
    </form>
    </div>

    <div class="vf-table-head" data-swafi-query-results id="swafi-valores-resultados">
      <strong>Valores registrados</strong>
      <span class="vf-scroll-hint">La tabla se desplaza dentro de este panel sin mover la página completa.</span>
    </div>

    <div class="vf-table-scroll" tabindex="0" aria-label="Tabla de valores fiscales y financieros">
      <table>
        <thead>
          @if($canViewSensitiveValues)
            <tr>
              <th>Activo / factura</th>
              <th>Proveedor / planta</th>
              <th>Valores</th>
              <th>Moneda</th>
              <th>Contable</th>
              <th>Estado técnico XML</th>
              <th>Fecha</th>
              <th>Acciones</th>
            </tr>
          @else
            <tr>
              <th>Activo</th>
              <th>Ubicación / clasificación</th>
              <th>Contable</th>
              <th>Soporte XML</th>
              <th>Fecha de corte</th>
              <th>Acciones</th>
            </tr>
          @endif
        </thead>
        <tbody>
          @forelse($resultados as $row)
            @php
              $conciliation = $row->conciliacion_cfdi ?: 'sin_xml';
              $conciliationClass = $conciliation === 'validado' ? 'ok' : ($conciliation === 'observado' ? 'warn' : 'danger');
              $accountClass = $row->estatus_contable === 'vigente' ? 'ok' : ($row->estatus_contable === 'en_revision' ? 'warn' : 'danger');
              $detail = [];

              if ($canViewSensitiveValues) {
                $detail = is_string($row->conciliacion_detalle) ? json_decode($row->conciliacion_detalle, true) : $row->conciliacion_detalle;
                $detail = is_array($detail) ? $detail : [];
              }
            @endphp

            @if($canViewSensitiveValues)
              <tr>
                <td>
                  <strong>{{ $row->numero_activo }}</strong><br>
                  <small>{{ $row->activo_descripcion }}</small><br>
                  <small>{{ $row->folio_factura ?: 'Sin folio' }}</small>
                </td>
                <td>
                  {{ $row->proveedor_nombre ?: 'Sin proveedor' }}<br>
                  <small>{{ $row->planta_nombre ?: 'Sin planta' }} · {{ $row->centro_costo_clave ?: 'Sin CC' }}</small>
                </td>
                <td>
                  Fiscal: ${{ number_format((float)$row->valor_fiscal, 2) }}<br>
                  Financiero: ${{ number_format((float)$row->valor_financiero, 2) }}<br>
                  <small>Libros Oracle ERP: ${{ number_format((float)$row->valor_en_libros, 2) }}</small>
                </td>
                <td>
                  {{ $row->moneda ?: 'MXN' }}<br>
                  <small>TC: {{ $row->tipo_cambio ? number_format((float)$row->tipo_cambio, 6) : 'N/A' }}</small>
                </td>
                <td>
                  <span class="vf-status {{ $accountClass }}">{{ ucfirst(str_replace('_', ' ', $row->estatus_contable)) }}</span>
                </td>
                <td>
                  <span class="vf-status {{ $conciliationClass }}">{{ ucfirst(str_replace('_', ' ', $conciliation)) }}</span>
                  <div class="vf-details">{{ implode(' ', array_slice($detail, 0, 2)) }}</div>
                </td>
                <td>
                  {{ $row->fecha_corte }}<br>
                  <small>{{ $row->updated_at }}</small>
                </td>
                <td>
                  <div class="vf-row-actions">
                    @if($row->expediente_id)
                      <a href="{{ route('expediente', $row->expediente_id) }}">Consultar</a>
                    @endif

                    <a href="{{ route('valores.historial', $row->numero_activo) }}">Historial</a>

                    @if($canExportarExcel)
                      <a
                        href="{{ route('valores.exportar-ficha', ['numeroActivo' => $row->numero_activo, 'formato' => 'xlsx']) }}"
                        aria-label="Exportar ficha fiscal y financiera del activo {{ $row->numero_activo }} a Excel"
                      >Ficha Excel</a>
                    @endif

                    @if($canExportarPdf)
                      <a
                        href="{{ route('valores.exportar-ficha', ['numeroActivo' => $row->numero_activo, 'formato' => 'pdf']) }}"
                        aria-label="Exportar ficha fiscal y financiera del activo {{ $row->numero_activo }} a PDF"
                      >Ficha PDF</a>
                    @endif

                    @if($canAdministrarValores)
                      <a href="{{ route('valores', array_merge(request()->query(), ['panel' => 'captura', 'editar_valor' => $row->valor_id])) }}">Editar</a>

                      <form
                        method="POST"
                        action="{{ route('valores.destroy', $row->valor_id) }}"
                        data-confirm="¿Dar de baja lógicamente los valores del activo? El registro se conservará para auditoría y el Dashboard lo marcará como pendiente."
                      >
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="motivo_baja" value="Baja lógica solicitada desde el módulo de valores.">
                        <button class="danger" type="submit">Dar de baja</button>
                      </form>
                    @endif
                  </div>
                </td>
              </tr>
            @else
              <tr>
                <td>
                  <strong>{{ $row->numero_activo }}</strong><br>
                  <small>{{ $row->activo_descripcion }}</small>
                </td>
                <td>
                  {{ $row->planta_nombre ?: 'Sin planta' }} · {{ $row->centro_costo_clave ?: 'Sin CC' }}<br>
                  <small>{{ $row->tipo_activo ?: 'Sin clasificación' }}</small>
                </td>
                <td>
                  <span class="vf-status {{ $accountClass }}">{{ ucfirst(str_replace('_', ' ', $row->estatus_contable)) }}</span>
                </td>
                <td>
                  <span class="vf-status {{ $conciliationClass }}">{{ ucfirst(str_replace('_', ' ', $conciliation)) }}</span>
                </td>
                <td>
                  {{ $row->fecha_corte ?: 'Sin fecha' }}<br>
                  <small>{{ $row->updated_at }}</small>
                </td>
                <td>
                  <div class="vf-row-actions">
                    @if($row->expediente_id)
                      <a href="{{ route('expediente', $row->expediente_id) }}">Consultar expediente</a>
                    @else
                      <span>Sin expediente relacionado</span>
                    @endif
                  </div>
                </td>
              </tr>
            @endif
          @empty
            <tr>
              <td colspan="{{ $canViewSensitiveValues ? 8 : 6 }}">No existen valores con los filtros seleccionados.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="vf-footer">
      <div>Mostrando {{ $resultados->firstItem() ?? 0 }}–{{ $resultados->lastItem() ?? 0 }} de {{ $resultados->total() }}</div>

      <div class="vf-pagination">
        @if($resultados->onFirstPage())
          <span class="vf-page-link disabled">Anterior</span>
        @else
          <a class="vf-page-link" href="{{ $resultados->appends(['panel' => 'consulta'])->previousPageUrl() }}">Anterior</a>
        @endif

        <span class="vf-page-link active">{{ $resultados->currentPage() }}</span>

        @if($resultados->hasMorePages())
          <a class="vf-page-link" href="{{ $resultados->appends(['panel' => 'consulta'])->nextPageUrl() }}">Siguiente</a>
        @else
          <span class="vf-page-link disabled">Siguiente</span>
        @endif
      </div>

      <div>Consulta controlada por rol</div>
    </div>
  </section>

  @if($canAdministrarValores)
    <section
      class="vf-card vf-panel {{ $panelActivo === 'captura' ? 'is-active' : '' }}"
      data-vf-panel="captura"
      role="tabpanel"
    >
      <div class="vf-title">
        <div>
          <h2>{{ $valorEdit ? 'Editar valores del activo' : 'Captura contable' }}</h2>
          <p>Captura y consulta los valores oficiales provenientes de Oracle ERP. El XML se conserva como soporte documental técnico independiente.</p>
        </div>
        <span class="pill ok">Edición autorizada</span>
      </div>

      <form method="POST" action="{{ route('valores.store') }}" data-vf-value-form>
        @csrf

        @if($valorEdit)
          <input type="hidden" name="valor_id" value="{{ $valorEdit->valor_id }}">
        @endif

        <div class="vf-form">
          <label class="vf-field span-2">
            <span>Activo fijo</span>
            <select name="numero_activo" required>
              <option value="">Seleccione...</option>
              @foreach($catalogos['activos'] as $activo)
                <option value="{{ $activo->numero_activo }}" {{ $selectedAsset === $activo->numero_activo ? 'selected' : '' }}>{{ $activo->numero_activo }} — {{ $activo->descripcion }}</option>
              @endforeach
            </select>
          </label>

          <label class="vf-field">
            <span>Valor fiscal</span>
            <input type="number" step="0.01" min="0" name="valor_fiscal" value="{{ old('valor_fiscal', $valorEdit->valor_fiscal ?? '') }}" required>
          </label>

          <label class="vf-field">
            <span>Valor financiero</span>
            <input type="number" step="0.01" min="0" name="valor_financiero" value="{{ old('valor_financiero', $valorEdit->valor_financiero ?? '') }}" required>
          </label>

          <label class="vf-field">
            <span>Moneda</span>
            <select name="moneda" data-vf-currency required>
              @foreach($catalogos['monedas'] as $moneda)
                <option
                  value="{{ $moneda->clave }}"
                  data-requires-exchange="{{ $moneda->requiere_tipo_cambio ? '1' : '0' }}"
                  {{ $selectedCurrency === $moneda->clave ? 'selected' : '' }}
                >
                  {{ $moneda->clave }} — {{ $moneda->nombre }}
                </option>
              @endforeach
            </select>
          </label>

          <label class="vf-field">
            <span>Tipo de cambio</span>
            <input type="number" step="0.000001" min="0" name="tipo_cambio" data-vf-exchange-rate value="{{ old('tipo_cambio', $valorEdit->tipo_cambio ?? ($selectedCurrency === 'MXN' ? '1' : '')) }}">
          </label>

          <label class="vf-field">
            <span>Fecha tipo de cambio</span>
            <input type="date" name="fecha_tipo_cambio" data-vf-exchange-date value="{{ old('fecha_tipo_cambio', !empty($valorEdit->fecha_tipo_cambio) ? \Illuminate\Support\Carbon::parse($valorEdit->fecha_tipo_cambio)->format('Y-m-d') : '') }}">
          </label>

          <label class="vf-field">
            <span>Origen tipo de cambio</span>
            <input name="origen_tipo_cambio" data-vf-exchange-origin value="{{ old('origen_tipo_cambio', $valorEdit->origen_tipo_cambio ?? '') }}" placeholder="Ej. CFDI / fuente corporativa">
          </label>

          <label class="vf-field">
            <span>Depreciación acumulada (Oracle ERP)</span>
            <input type="number" step="0.01" min="0" name="depreciacion_acumulada" value="{{ old('depreciacion_acumulada', $valorEdit->depreciacion_acumulada ?? '') }}" required>
          </label>

          <label class="vf-field">
            <span>Valor en libros (Oracle ERP)</span>
            <input type="number" step="0.01" min="0" name="valor_en_libros" value="{{ old('valor_en_libros', $valorEdit->valor_en_libros ?? '') }}" required>
          </label>

          <label class="vf-field">
            <span>Vida útil oficial (meses)</span>
            <input type="number" min="1" max="1200" name="vida_util_meses" value="{{ old('vida_util_meses', $valorEdit->vida_util_meses ?? '') }}">
          </label>

          <label class="vf-field">
            <span>Fecha de corte</span>
            <input type="date" name="fecha_corte" value="{{ old('fecha_corte', !empty($valorEdit->fecha_corte) ? \Illuminate\Support\Carbon::parse($valorEdit->fecha_corte)->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
          </label>

          <label class="vf-field">
            <span>Estatus contable</span>
            <select name="estatus_contable" required>
              @foreach($catalogos['estatusContables'] as $estatus)
                <option value="{{ $estatus->clave }}" {{ $selectedStatus === $estatus->clave ? 'selected' : '' }}>
                  {{ $estatus->nombre }}
                </option>
              @endforeach
            </select>
          </label>

          <div class="vf-message vf-info vf-form-note">
            <strong>Fuente oficial:</strong>
            SWAFI no calcula depreciación ni valor en libros. Captura exactamente los valores
            oficiales obtenidos de Oracle ERP; el sistema únicamente los resguarda, consulta,
            exporta y audita.
          </div>

          <label class="vf-field full">
            <span>Motivo del cambio {{ $valorEdit ? '(obligatorio)' : '' }}</span>
            <textarea name="motivo_cambio" placeholder="Describe el origen, ajuste o motivo del cambio realizado.">{{ old('motivo_cambio', '') }}</textarea>
          </label>
        </div>

        <div class="vf-actions">
          <button class="vf-button primary" type="submit">{{ $valorEdit ? 'Actualizar valores' : 'Guardar valores' }}</button>
          <a class="vf-button soft" href="{{ route('valores', ['panel' => 'captura']) }}">Limpiar captura</a>
          <button class="vf-button" type="button" data-vf-open="consulta">Regresar a consulta</button>
        </div>
      </form>
    </section>

    <section
      class="vf-card vf-panel {{ $panelActivo === 'importar' ? 'is-active' : '' }}"
      data-vf-panel="importar"
      role="tabpanel"
    >
      <div class="vf-title">
        <div>
          <h2>Carga masiva de valores</h2>
          <p>Previsualiza, valida y confirma los valores oficiales antes de modificar registros.</p>
        </div>
        <span class="pill ok">Importación autorizada</span>
      </div>

      <div class="vf-import-box">
        <div class="vf-import-guide">
          <h3>Reglas de importación</h3>
          <p>SWAFI valida catálogos financieros, tipo de cambio, montos, fechas, existencia del activo y duplicidad dentro del archivo. La depreciación acumulada, el valor en libros y la vida útil deben provenir de Oracle ERP; SWAFI no los calcula ni los compara entre sí.</p>
          <ul>
            <li>La previsualización no modifica valores fiscales ni financieros.</li>
            <li>Se informa cuántas filas son correctas e incorrectas antes de aplicar.</li>
            <li>Un activo existente se actualiza o restaura; no se duplica.</li>
            <li>Solo las filas correctas se aplican después de una confirmación expresa.</li>
          </ul>
        </div>

        <form method="POST" action="{{ route('valores.importar') }}" enctype="multipart/form-data">
          @csrf

          <label class="vf-field">
            <span>Archivo CSV</span>
            <input type="file" name="archivo_csv" accept=".csv,.txt" required>
          </label>

          <div class="vf-actions">
            <button class="vf-button primary" type="submit">Previsualizar CSV</button>
            <a class="vf-button soft" href="{{ route('valores.plantilla') }}">Descargar plantilla</a>
          </div>
        </form>
      </div>

      @if($importBatch)
        @php
          $batchState = (string) $importBatch->estado;
          $batchCanApply = $importBatch->puedeAplicarse();
          $stateClass = $batchState === 'aplicada'
            ? 'ok'
            : (in_array($batchState, ['cancelada', 'vencida'], true) ? 'danger' : 'warn');
        @endphp

        <div class="vf-preview" id="previsualizacion-valores">
          <div class="vf-preview-header">
            <div class="vf-preview-heading">
              <div>
                <h2>Previsualización del archivo</h2>
                <p>Verifica la información agrupada por activo antes de autorizar cualquier modificación.</p>
              </div>
              <span class="vf-status {{ $stateClass }}">{{ ucfirst($batchState) }}</span>
            </div>

            <div class="vf-preview-meta-grid" aria-label="Información del lote de importación">
              <div class="vf-preview-meta-item">
                <span>Archivo</span>
                <strong title="{{ $importBatch->archivo_nombre_original }}">{{ $importBatch->archivo_nombre_original }}</strong>
              </div>
              <div class="vf-preview-meta-item">
                <span>Generada</span>
                <strong>{{ optional($importBatch->created_at)->format('d/m/Y H:i') }}</strong>
              </div>
              <div class="vf-preview-meta-item">
                <span>Vigencia</span>
                <strong>{{ optional($importBatch->expira_at)->format('d/m/Y H:i') }}</strong>
              </div>
              <div class="vf-preview-meta-item is-code">
                <span>Identificador del lote</span>
                <strong title="{{ $importBatch->uuid }}">{{ $importBatch->uuid }}</strong>
              </div>
            </div>
          </div>

          <div class="vf-preview-summary" aria-label="Resumen de validación">
            <div class="vf-preview-stat">
              <strong>{{ $importBatch->total_filas }}</strong>
              <span>Filas revisadas</span>
            </div>
            <div class="vf-preview-stat correct">
              <strong>{{ $importBatch->filas_correctas }}</strong>
              <span>Registros correctos</span>
            </div>
            <div class="vf-preview-stat incorrect">
              <strong>{{ $importBatch->filas_incorrectas }}</strong>
              <span>Registros incorrectos</span>
            </div>
            <div class="vf-preview-stat insert">
              <strong>{{ $importBatch->filas_insertadas + $importBatch->filas_actualizadas + $importBatch->filas_restauradas }}</strong>
              <span>Registros aplicados</span>
            </div>
          </div>

          <div class="vf-preview-toolbar">
            <form method="GET" action="{{ route('valores') }}" class="vf-preview-filter">
              <input type="hidden" name="panel" value="importar">
              <input type="hidden" name="lote" value="{{ $importBatch->uuid }}">

              <label class="vf-field">
                <span>Filtrar previsualización</span>
                <select name="preview_status">
                  <option value="">Todas las filas</option>
                  <option value="correcta" {{ $previewStatus === 'correcta' ? 'selected' : '' }}>Solo correctas</option>
                  <option value="incorrecta" {{ $previewStatus === 'incorrecta' ? 'selected' : '' }}>Solo incorrectas</option>
                </select>
              </label>

              <button class="vf-button soft" type="submit">Aplicar filtro</button>
            </form>

            <a class="vf-button soft" href="{{ route('valores', ['panel' => 'importar']) }}">Cerrar previsualización</a>
          </div>

          @if($importRows)
            <div class="vf-preview-table-wrap" tabindex="0" aria-label="Detalle de la previsualización de valores">
              <table class="vf-preview-table">
                <thead>
                  <tr>
                    <th>Fila / activo</th>
                    <th>Resultado</th>
                    <th>Acción</th>
                    <th>Valores oficiales Oracle ERP</th>
                    <th>Parámetros del registro</th>
                    <th>Resultado de validación</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse($importRows as $previewRow)
                    @php
                      $previewData = is_array($previewRow->datos) ? $previewRow->datos : [];
                      $previewPayload = data_get($previewData, 'payload', []);
                      $previewErrors = is_array($previewRow->errores) ? $previewRow->errores : [];
                      $isCorrect = $previewRow->estatus === 'correcta';
                      $formatPreviewAmount = static function ($value, int $decimals = 2): string {
                        return is_numeric($value) ? number_format((float) $value, $decimals) : '—';
                      };
                    @endphp
                    <tr class="{{ $isCorrect ? 'is-correct' : 'is-incorrect' }}">
                      <td>
                        <div class="vf-preview-asset">
                          <span class="vf-preview-row-number">{{ $previewRow->numero_fila }}</span>
                          <div>
                            <strong>{{ $previewRow->numero_activo ?: 'Sin activo' }}</strong>
                            <small>Fila de origen {{ $previewRow->numero_fila }}</small>
                          </div>
                        </div>
                      </td>
                      <td>
                        <span class="vf-status {{ $isCorrect ? 'ok' : 'danger' }}">
                          {{ $isCorrect ? 'Correcto' : 'Incorrecto' }}
                        </span>
                      </td>
                      <td>
                        <span class="vf-preview-action">
                          {{ $previewRow->accion ? ucfirst($previewRow->accion) : 'No aplicable' }}
                        </span>
                      </td>
                      <td>
                        <div class="vf-preview-values">
                          <div class="vf-preview-data">
                            <span>Valor fiscal</span>
                            <strong>{{ $formatPreviewAmount(data_get($previewPayload, 'valor_fiscal')) }}</strong>
                          </div>
                          <div class="vf-preview-data">
                            <span>Depreciación acumulada</span>
                            <strong>{{ $formatPreviewAmount(data_get($previewPayload, 'depreciacion_acumulada')) }}</strong>
                          </div>
                          <div class="vf-preview-data">
                            <span>Valor en libros</span>
                            <strong>{{ $formatPreviewAmount(data_get($previewPayload, 'valor_en_libros')) }}</strong>
                          </div>
                          <div class="vf-preview-data">
                            <span>Valor financiero</span>
                            <strong>{{ $formatPreviewAmount(data_get($previewPayload, 'valor_financiero')) }}</strong>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div class="vf-preview-parameters">
                          <div class="vf-preview-data">
                            <span>Moneda</span>
                            <strong>{{ data_get($previewPayload, 'moneda') ?: '—' }}</strong>
                          </div>
                          <div class="vf-preview-data">
                            <span>Tipo de cambio</span>
                            <strong>{{ $formatPreviewAmount(data_get($previewPayload, 'tipo_cambio'), 6) }}</strong>
                          </div>
                          <div class="vf-preview-data">
                            <span>Vida útil</span>
                            <strong>{{ data_get($previewPayload, 'vida_util_meses') ? data_get($previewPayload, 'vida_util_meses').' meses' : '—' }}</strong>
                          </div>
                          <div class="vf-preview-data">
                            <span>Fecha de corte</span>
                            <strong>{{ data_get($previewPayload, 'fecha_corte') ?: '—' }}</strong>
                          </div>
                          <div class="vf-preview-data vf-preview-data-wide">
                            <span>Estatus contable</span>
                            <strong>{{ data_get($previewPayload, 'estatus_contable') ? ucfirst(str_replace('_', ' ', data_get($previewPayload, 'estatus_contable'))) : '—' }}</strong>
                          </div>
                        </div>
                      </td>
                      <td>
                        @if($previewErrors !== [])
                          <ul class="vf-preview-errors">
                            @foreach($previewErrors as $previewError)
                              <li>{{ $previewError }}</li>
                            @endforeach
                          </ul>
                        @elseif($previewRow->aplicada)
                          <div class="vf-preview-validation is-ready">Aplicada correctamente.</div>
                        @else
                          <div class="vf-preview-validation is-ready">La fila cumple las reglas vigentes y está lista para confirmación.</div>
                        @endif
                      </td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="6">No existen filas con el filtro seleccionado.</td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>

            <div class="vf-preview-footer">
              <div>Mostrando {{ $importRows->firstItem() ?? 0 }}–{{ $importRows->lastItem() ?? 0 }} de {{ $importRows->total() }} filas</div>

              <div class="vf-pagination">
                @if($importRows->onFirstPage())
                  <span class="vf-page-link disabled">Anterior</span>
                @else
                  <a class="vf-page-link" href="{{ $importRows->previousPageUrl() }}">Anterior</a>
                @endif

                <span class="vf-page-link active">{{ $importRows->currentPage() }}</span>

                @if($importRows->hasMorePages())
                  <a class="vf-page-link" href="{{ $importRows->nextPageUrl() }}">Siguiente</a>
                @else
                  <span class="vf-page-link disabled">Siguiente</span>
                @endif
              </div>

              <div>La previsualización no modifica datos.</div>
            </div>
          @endif

          @if($batchState === 'previsualizada')
            @if($batchCanApply)
              <div class="vf-preview-decision">
                <div class="vf-preview-decision-copy">
                  <span class="vf-preview-decision-icon" aria-hidden="true">✓</span>
                  <div>
                    <h3>Aplicación controlada del lote</h3>
                    <p>Se aplicarán únicamente {{ $importBatch->filas_correctas }} filas correctas. Las {{ $importBatch->filas_incorrectas }} filas incorrectas permanecerán sin cambios y conservarán su detalle para corrección.</p>
                  </div>
                </div>

                <div class="vf-preview-decision-actions">
                  <form method="POST" action="{{ route('valores.importaciones.aplicar', $importBatch->uuid) }}" class="vf-preview-apply-form">
                    @csrf

                    <label class="vf-preview-confirmation-check">
                      <input type="checkbox" name="confirmar_aplicacion" value="1" required>
                      <span>Confirmo que revisé la previsualización y autorizo aplicar solo las filas correctas.</span>
                    </label>

                    <div class="vf-preview-decision-buttons">
                      <button class="vf-button primary" type="submit" data-confirm="¿Aplicar las filas correctas de esta previsualización?">Confirmar y aplicar</button>
                    </div>
                  </form>

                  <form method="POST" action="{{ route('valores.importaciones.cancelar', $importBatch->uuid) }}" class="vf-preview-cancel-form">
                    @csrf
                    @method('DELETE')
                    <div class="vf-preview-decision-buttons">
                      <button class="vf-button" type="submit" data-confirm="¿Cancelar esta previsualización sin modificar los valores?">Cancelar previsualización</button>
                    </div>
                  </form>
                </div>
              </div>
            @else
              <div class="vf-preview-decision">
                <div class="vf-preview-decision-copy">
                  <span class="vf-preview-decision-icon" aria-hidden="true">!</span>
                  <div>
                    <h3>Lote sin posibilidad de aplicación</h3>
                    <p>
                      @if($importBatch->filas_correctas === 0)
                        No hay filas correctas. Corrige el archivo y genera una nueva previsualización.
                      @else
                        La previsualización venció. Genera una nueva antes de confirmar la carga.
                      @endif
                    </p>
                  </div>
                </div>

                <form method="POST" action="{{ route('valores.importaciones.cancelar', $importBatch->uuid) }}" class="vf-preview-cancel-form">
                  @csrf
                  @method('DELETE')
                  <div class="vf-preview-decision-buttons">
                    <button class="vf-button" type="submit" data-confirm="¿Cancelar esta previsualización sin modificar los valores?">Cancelar previsualización</button>
                  </div>
                </form>
              </div>
            @endif
          @elseif($batchState === 'aplicada')
            <div class="vf-message vf-success">
              Este lote ya fue aplicado: {{ $importBatch->filas_insertadas }} insertados,
              {{ $importBatch->filas_actualizadas }} actualizados y
              {{ $importBatch->filas_restauradas }} restaurados.
            </div>
          @endif
        </div>
      @endif
    </section>
  @endif
</div>
@endsection

@section('page_scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
  document.addEventListener('DOMContentLoaded', function () {
    const page = document.querySelector('[data-values-page]');

    if (!page) {
      return;
    }

    const tabs = Array.from(page.querySelectorAll('[data-vf-tab]'));
    const panels = Array.from(page.querySelectorAll('[data-vf-panel]'));
    const openButtons = Array.from(page.querySelectorAll('[data-vf-open]'));

    function activatePanel(panelName, updateUrl) {
      const targetExists = panels.some(function (panel) {
        return panel.dataset.vfPanel === panelName;
      });

      if (!targetExists) {
        panelName = 'consulta';
      }

      tabs.forEach(function (tab) {
        const active = tab.dataset.vfTab === panelName;
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
      });

      panels.forEach(function (panel) {
        panel.classList.toggle('is-active', panel.dataset.vfPanel === panelName);
      });

      page.dataset.activePanel = panelName;

      if (updateUrl && window.history && window.history.replaceState) {
        const url = new URL(window.location.href);
        url.searchParams.set('panel', panelName);
        window.history.replaceState({}, '', url.toString());
      }
    }

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        activatePanel(tab.dataset.vfTab, true);
      });
    });

    openButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        activatePanel(button.dataset.vfOpen, true);
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    });

    const valueForm = page.querySelector('[data-vf-value-form]');

    if (valueForm) {
      const currency = valueForm.querySelector('[data-vf-currency]');
      const exchangeRate = valueForm.querySelector('[data-vf-exchange-rate]');
      const exchangeDate = valueForm.querySelector('[data-vf-exchange-date]');
      const exchangeOrigin = valueForm.querySelector('[data-vf-exchange-origin]');
      function syncExchangeFields() {
        if (!currency) {
          return;
        }

        const option = currency.options[currency.selectedIndex];
        const requiresExchange = option && option.dataset.requiresExchange === '1';

        [exchangeRate, exchangeDate, exchangeOrigin].forEach(function (field) {
          if (!field) {
            return;
          }

          field.required = requiresExchange;
          field.disabled = !requiresExchange;
        });

        if (!requiresExchange) {
          if (exchangeRate) {
            exchangeRate.value = '1';
          }
          if (exchangeDate) {
            exchangeDate.value = '';
          }
          if (exchangeOrigin) {
            exchangeOrigin.value = '';
          }
        }
      }

      if (currency) {
        currency.addEventListener('change', syncExchangeFields);
      }

      syncExchangeFields();
    }

    activatePanel(page.dataset.activePanel || 'consulta', false);
  });
</script>
@endsection
