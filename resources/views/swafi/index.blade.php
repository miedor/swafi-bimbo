@extends('layouts.app')

@section('title', 'SWAFI | Dashboard')

@section('content')
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <img src="{{ asset('swafi/img/logo-bimbo.jpg') }}" alt="Bimbo" class="sidebar-logo">
            <div>
                <strong>SWAFI</strong>
                <span>Bimbo S.A. de C.V.</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <button class="nav-item active" data-target="dashboard">Dashboard</button>
            <button class="nav-item" data-target="registro-individual">Registro individual</button>
            <button class="nav-item" data-target="registro-masivo">Registro masivo</button>
            <button class="nav-item" data-target="valores">Valores fiscales y financieros</button>
            <button class="nav-item" data-target="ubicacion">Ubicación e inventario</button>
            <button class="nav-item" data-target="busqueda">Búsqueda avanzada</button>
            <button class="nav-item" data-target="reportes">Reportes ad hoc</button>
            <button class="nav-item" data-target="catalogos">Catálogos base</button>
            <button class="nav-item" data-target="seguridad">Seguridad y acceso</button>
            <button class="nav-item" data-target="detalle">Detalle de expediente</button>
        </nav>

        <div class="sidebar-footer">
            <div class="mini-card">
                <span class="muted">Estatus documental</span>
                <strong>94.2% trazabilidad</strong>
            </div>
        </div>
    </aside>

    <main class="main-panel">
        <header class="topbar">
            <div>
                <p class="eyebrow">Sistema Web de Gestión de Facturas de Activo Fijo</p>
                <h1 id="page-title">Dashboard ejecutivo</h1>
            </div>
            <div class="topbar-tools">
                <input type="text" class="search-input" placeholder="Buscar factura, activo, proveedor o serie">
                <button class="icon-btn">🔔</button>
                <div class="user-chip">ED</div>
            </div>
        </header>

        <div class="breadcrumb">Inicio / <span id="breadcrumb-current">Dashboard</span></div>

        <section class="page-section active" id="dashboard">
            <div class="kpi-grid">
                <article class="kpi-card">
                    <span>Total de facturas registradas</span>
                    <strong>12,486</strong>
                    <small>+4.8% vs. mes anterior</small>
                </article>
                <article class="kpi-card">
                    <span>Expedientes pendientes de validación</span>
                    <strong>138</strong>
                    <small>24 en prioridad alta</small>
                </article>
                <article class="kpi-card">
                    <span>Activos con ubicación pendiente</span>
                    <strong>67</strong>
                    <small>3 plantas involucradas</small>
                </article>
                <article class="kpi-card">
                    <span>Valores fiscales incompletos</span>
                    <strong>42</strong>
                    <small>Seguimiento contable requerido</small>
                </article>
                <article class="kpi-card">
                    <span>Reportes generados</span>
                    <strong>326</strong>
                    <small>Últimos 30 días</small>
                </article>
            </div>

            <div class="two-col-grid">
                <article class="card">
                    <div class="card-head">
                        <h3>Facturas por estatus</h3>
                        <span class="status-pill status-info">Tiempo real</span>
                    </div>
                    <div class="bar-chart">
                        <div class="bar-item"><label>Validado</label><div class="bar"><span style="width:82%"></span></div><strong>82%</strong></div>
                        <div class="bar-item"><label>En revisión</label><div class="bar"><span style="width:46%"></span></div><strong>46%</strong></div>
                        <div class="bar-item"><label>Observado</label><div class="bar"><span style="width:18%"></span></div><strong>18%</strong></div>
                        <div class="bar-item"><label>Pendiente</label><div class="bar"><span style="width:29%"></span></div><strong>29%</strong></div>
                    </div>
                </article>
                <article class="card">
                    <div class="card-head">
                        <h3>Activos por ubicación</h3>
                        <span class="status-pill status-success">Inventario al día</span>
                    </div>
                    <div class="donut-wrap">
                        <div class="donut"></div>
                        <ul class="legend">
                            <li><span class="dot dot-blue"></span>Planta Guadalajara — 36%</li>
                            <li><span class="dot dot-red"></span>Planta CDMX — 27%</li>
                            <li><span class="dot dot-gray"></span>Planta Monterrey — 21%</li>
                            <li><span class="dot dot-slate"></span>Otras ubicaciones — 16%</li>
                        </ul>
                    </div>
                </article>
            </div>

            <div class="two-col-grid">
                <article class="card">
                    <div class="card-head">
                        <h3>Actividad reciente</h3>
                        <button class="btn btn-light">Ver todo</button>
                    </div>
                    <ul class="activity-list">
                        <li><strong>Factura AF-20391</strong><span>Validación financiera completada por Auditor Patrimonial.</span></li>
                        <li><strong>Activo HNO-8872</strong><span>Ubicación actualizada a Planta Guadalajara / Almacén 3.</span></li>
                        <li><strong>Reporte mensual</strong><span>Exportado a Excel por Supervisor de Control Patrimonial.</span></li>
                        <li><strong>Carga masiva lote 18</strong><span>95 registros exitosos y 4 con observaciones.</span></li>
                    </ul>
                </article>
                <article class="card">
                    <div class="card-head">
                        <h3>Accesos rápidos</h3>
                    </div>
                    <div class="quick-actions">
                        <button class="quick-btn" data-target="registro-individual">Nuevo expediente</button>
                        <button class="quick-btn" data-target="registro-masivo">Cargar layout</button>
                        <button class="quick-btn" data-target="busqueda">Buscar factura</button>
                        <button class="quick-btn" data-target="reportes">Crear reporte</button>
                    </div>
                </article>
            </div>
        </section>

        <section class="page-section" id="registro-individual">
            <div class="card">
                <div class="card-head">
                    <h3>Registro individual de expediente</h3>
                    <div class="stack-actions">
                        <button class="btn btn-light">Cancelar</button>
                        <button class="btn btn-light">Vista previa</button>
                        <button class="btn btn-primary">Guardar</button>
                    </div>
                </div>
                <div class="form-grid">
                    <label>Folio de factura<input type="text" value="AF-2026-001257"></label>
                    <label>RFC proveedor<input type="text" value="PROV840220AB1"></label>
                    <label>Nombre del proveedor<input type="text" value="Tecnología Industrial del Bajío"></label>
                    <label>Fecha de factura<input type="date" value="2026-03-15"></label>
                    <label>Número de activo fijo<input type="text" value="AFI-778193"></label>
                    <label>Descripción del bien<input type="text" value="Montacargas eléctrico 3 toneladas"></label>
                    <label>Serie<input type="text" value="SER-99281-MX"></label>
                    <label>Marca<input type="text" value="Toyota"></label>
                    <label>Modelo<input type="text" value="8FBE20"></label>
                    <label>Monto fiscal<input type="text" value="$ 482,500.00"></label>
                    <label>Valor financiero<input type="text" value="$ 471,000.00"></label>
                    <label>Moneda<input type="text" value="MXN"></label>
                    <label>Centro de costo<input type="text" value="CC-ALM-302"></label>
                    <label>Planta o sucursal<input type="text" value="Planta Guadalajara"></label>
                    <label>Ubicación física<input type="text" value="Almacén 3 / Pasillo B"></label>
                    <label>Responsable<input type="text" value="Coordinador de almacén"></label>
                    <label class="full-width">Observaciones<textarea rows="4">Expediente completo, pendiente de validación fiscal final y carga del XML timbrado.</textarea></label>
                    <div class="upload-box full-width">
                        <strong>Carga documental PDF / XML</strong>
                        <p>Arrastra archivos aquí o usa el botón de selección.</p>
                        <button class="btn btn-light">Seleccionar archivos</button>
                    </div>
                </div>
            </div>
        </section>

        <section class="page-section" id="registro-masivo">
            <div class="card">
                <div class="card-head">
                    <h3>Registro masivo por layout</h3>
                    <div class="stack-actions">
                        <button class="btn btn-light">Descargar plantilla</button>
                        <button class="btn btn-light">Validar archivo</button>
                        <button class="btn btn-primary">Procesar carga</button>
                    </div>
                </div>
                <div class="upload-box large">
                    <strong>Zona de carga drag &amp; drop</strong>
                    <p>Sube archivo Excel o layout con la estructura autorizada para integración de expedientes.</p>
                </div>
                <div class="stats-inline">
                    <div><strong>99</strong><span>Registros leídos</span></div>
                    <div><strong>95</strong><span>Correctos</span></div>
                    <div><strong>4</strong><span>Con error</span></div>
                </div>
                <table class="data-table">
                    <thead>
                        <tr><th>Fila</th><th>Folio</th><th>Activo</th><th>Proveedor</th><th>Estatus</th><th>Observación</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>1</td><td>AF-2026-1001</td><td>AFI-1001</td><td>Proveedor Norte</td><td><span class="status-pill status-success">Correcto</span></td><td>Sin observaciones</td></tr>
                        <tr><td>2</td><td>AF-2026-1002</td><td>AFI-1002</td><td>Industrial MX</td><td><span class="status-pill status-warning">Revisar</span></td><td>Falta XML</td></tr>
                        <tr><td>3</td><td>AF-2026-1003</td><td>AFI-1003</td><td>Servicios Delta</td><td><span class="status-pill status-danger">Error</span></td><td>RFC inválido</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="page-section" id="valores">
            <div class="card">
                <div class="card-head">
                    <h3>Valores fiscales y financieros</h3>
                    <div class="stack-actions">
                        <button class="btn btn-light">Consultar historial</button>
                        <button class="btn btn-light">Exportar</button>
                    </div>
                </div>
                <div class="filter-grid mini">
                    <input type="text" placeholder="Planta" value="Guadalajara">
                    <input type="text" placeholder="Proveedor" value="Todos">
                    <input type="text" placeholder="Año" value="2026">
                    <input type="text" placeholder="Tipo de activo" value="Montacargas">
                    <input type="text" placeholder="Estatus" value="Activo">
                </div>
                <table class="data-table">
                    <thead>
                        <tr><th>Folio</th><th>Activo</th><th>Valor fiscal</th><th>Depreciación</th><th>Valor en libros</th><th>Valor financiero</th><th>Vida útil</th><th>Estatus</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>AF-2026-001257</td><td>AFI-778193</td><td>$482,500</td><td>$32,100</td><td>$450,400</td><td>$471,000</td><td>8 años</td><td><span class="status-pill status-info">Vigente</span></td></tr>
                        <tr><td>AF-2026-001129</td><td>AFI-773812</td><td>$126,300</td><td>$8,540</td><td>$117,760</td><td>$121,000</td><td>5 años</td><td><span class="status-pill status-success">Conciliado</span></td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="page-section" id="ubicacion">
            <div class="card">
                <div class="card-head">
                    <h3>Ubicación física e inventario</h3>
                    <button class="btn btn-primary">Registrar toma de inventario</button>
                </div>
                <div class="form-grid two-up">
                    <label>Planta<input type="text" value="Planta Guadalajara"></label>
                    <label>Área<input type="text" value="Almacén"></label>
                    <label>Edificio<input type="text" value="B"></label>
                    <label>Piso<input type="text" value="1"></label>
                    <label>Pasillo<input type="text" value="B-12"></label>
                    <label>Responsable<input type="text" value="Jefe de almacén"></label>
                    <label class="full-width">Código interno<input type="text" value="UBI-GDL-ALM3-B12-AFI778193"></label>
                </div>
                <table class="data-table mt-20">
                    <thead>
                        <tr><th>Activo</th><th>Descripción</th><th>Ubicación</th><th>Responsable</th><th>Estatus de localización</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>AFI-778193</td><td>Montacargas eléctrico</td><td>Almacén 3 / Pasillo B</td><td>Jefe de almacén</td><td><span class="status-pill status-success">Localizado</span></td></tr>
                        <tr><td>AFI-778205</td><td>Escáner industrial</td><td>Plataforma logística</td><td>Supervisor patrimonial</td><td><span class="status-pill status-warning">Pendiente</span></td></tr>
                        <tr><td>AFI-778240</td><td>Compresor auxiliar</td><td>No disponible</td><td>Sin asignar</td><td><span class="status-pill status-danger">No encontrado</span></td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="page-section" id="busqueda">
            <div class="card">
                <div class="card-head">
                    <h3>Búsqueda avanzada</h3>
                    <button class="btn btn-primary">Consultar expediente</button>
                </div>
                <div class="filter-grid">
                    <input type="text" placeholder="Folio factura">
                    <input type="text" placeholder="Proveedor">
                    <input type="text" placeholder="RFC">
                    <input type="text" placeholder="Número de activo">
                    <input type="text" placeholder="Serie">
                    <input type="text" placeholder="Planta">
                    <input type="text" placeholder="Ubicación">
                    <input type="date">
                    <input type="text" placeholder="Rango de montos">
                    <input type="text" placeholder="Estatus documental">
                </div>
                <table class="data-table mt-20">
                    <thead>
                        <tr><th>Folio</th><th>Proveedor</th><th>Activo</th><th>Planta</th><th>Ubicación</th><th>Estatus</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>AF-2026-001257</td><td>Tecnología Industrial del Bajío</td><td>AFI-778193</td><td>Guadalajara</td><td>Almacén 3</td><td><span class="status-pill status-success">Completo</span></td><td>Consultar · Descargar · Historial</td></tr>
                        <tr><td>AF-2026-001188</td><td>Proveedor Norte</td><td>AFI-778010</td><td>Monterrey</td><td>Área de proceso</td><td><span class="status-pill status-warning">Con observación</span></td><td>Consultar · Editar · Historial</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="page-section" id="reportes">
            <div class="two-col-grid report-grid">
                <article class="card">
                    <div class="card-head">
                        <h3>Constructor de reportes</h3>
                        <button class="btn btn-primary">Vista previa</button>
                    </div>
                    <div class="form-grid two-up compact">
                        <label>Planta<select><option>Guadalajara</option></select></label>
                        <label>Tipo de reporte<select><option>Facturas por planta</option></select></label>
                        <label>Fecha inicial<input type="date" value="2026-03-01"></label>
                        <label>Fecha final<input type="date" value="2026-03-31"></label>
                    </div>
                    <div class="stack-actions mt-20">
                        <button class="btn btn-light">Exportar PDF</button>
                        <button class="btn btn-light">Exportar Excel</button>
                        <button class="btn btn-primary">Generar</button>
                    </div>
                </article>
                <article class="card">
                    <div class="card-head"><h3>Reportes frecuentes</h3></div>
                    <div class="report-cards">
                        <div class="mini-report">Facturas por planta</div>
                        <div class="mini-report">Activos por ubicación</div>
                        <div class="mini-report">Expedientes incompletos</div>
                        <div class="mini-report">Valores fiscales</div>
                        <div class="mini-report">Inventario patrimonial</div>
                    </div>
                </article>
            </div>
        </section>

        <section class="page-section" id="catalogos">
            <div class="card">
                <div class="card-head">
                    <h3>Catálogos base</h3>
                    <div class="stack-actions">
                        <button class="btn btn-light">Buscar</button>
                        <button class="btn btn-primary">Alta de catálogo</button>
                    </div>
                </div>
                <table class="data-table">
                    <thead>
                        <tr><th>Catálogo</th><th>Descripción</th><th>Registros</th><th>Última actualización</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Proveedores</td><td>Relación de proveedores autorizados</td><td>148</td><td>19/03/2026</td><td>Editar · Consultar</td></tr>
                        <tr><td>Plantas</td><td>Centros operativos y sucursales</td><td>23</td><td>18/03/2026</td><td>Editar · Consultar</td></tr>
                        <tr><td>Centros de costo</td><td>Clasificación financiera</td><td>74</td><td>15/03/2026</td><td>Editar · Consultar</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="page-section" id="seguridad">
            <div class="card">
                <div class="card-head">
                    <h3>Seguridad y acceso</h3>
                    <button class="btn btn-primary">Nuevo usuario</button>
                </div>
                <table class="data-table">
                    <thead>
                        <tr><th>Usuario</th><th>Rol</th><th>Correo</th><th>Último acceso</th><th>Permisos</th><th>Estatus</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>ana.lopez</td><td>Administrador</td><td>ana.lopez@bimbo.com</td><td>Hoy 08:42</td><td>Total</td><td><span class="status-pill status-success">Activo</span></td></tr>
                        <tr><td>carlos.mena</td><td>Capturista</td><td>carlos.mena@bimbo.com</td><td>Hoy 08:01</td><td>Registro / Consulta</td><td><span class="status-pill status-info">Vigente</span></td></tr>
                        <tr><td>maria.arias</td><td>Auditor</td><td>maria.arias@bimbo.com</td><td>Ayer 17:30</td><td>Consulta / Reportes</td><td><span class="status-pill status-warning">Pendiente MFA</span></td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="page-section" id="detalle">
            <div class="card">
                <div class="card-head">
                    <h3>Ficha ejecutiva del expediente</h3>
                    <span class="status-pill status-success">Expediente completo</span>
                </div>
                <div class="tabs-mock">
                    <span class="tab active">Datos generales</span>
                    <span class="tab">Activo fijo</span>
                    <span class="tab">Valores</span>
                    <span class="tab">Ubicación</span>
                    <span class="tab">Adjuntos</span>
                    <span class="tab">Historial</span>
                </div>
                <div class="detail-grid">
                    <div class="detail-card"><span>Folio</span><strong>AF-2026-001257</strong></div>
                    <div class="detail-card"><span>Proveedor</span><strong>Tecnología Industrial del Bajío</strong></div>
                    <div class="detail-card"><span>Activo</span><strong>AFI-778193</strong></div>
                    <div class="detail-card"><span>Valor financiero</span><strong>$471,000.00</strong></div>
                    <div class="detail-card"><span>Ubicación</span><strong>Planta Guadalajara / Almacén 3</strong></div>
                    <div class="detail-card"><span>Trazabilidad</span><strong>100% documentada</strong></div>
                </div>
                <div class="timeline-box">
                    <h4>Historial de cambios</h4>
                    <ul class="activity-list">
                        <li><strong>15/03/2026</strong><span>Alta del expediente y carga inicial de factura PDF.</span></li>
                        <li><strong>16/03/2026</strong><span>Actualización de valor financiero y clasificación contable.</span></li>
                        <li><strong>18/03/2026</strong><span>Registro de ubicación física y validación de inventario.</span></li>
                    </ul>
                </div>
            </div>
        </section>
    </main>
</div>
@endsection
