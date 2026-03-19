document.addEventListener('DOMContentLoaded', () => {
    const navItems = document.querySelectorAll('.nav-item');
    const quickBtns = document.querySelectorAll('.quick-btn');
    const sections = document.querySelectorAll('.page-section');
    const pageTitle = document.getElementById('page-title');
    const breadcrumbCurrent = document.getElementById('breadcrumb-current');

    const titles = {
        'dashboard': 'Dashboard ejecutivo',
        'registro-individual': 'Registro individual de expedientes',
        'registro-masivo': 'Registro masivo de expedientes',
        'valores': 'Valores fiscales y financieros',
        'ubicacion': 'Ubicación física e inventario',
        'busqueda': 'Búsqueda avanzada',
        'reportes': 'Reportes ad hoc',
        'catalogos': 'Catálogos base',
        'seguridad': 'Seguridad y acceso',
        'detalle': 'Detalle de expediente'
    };

    function activateSection(target) {
        sections.forEach(section => section.classList.toggle('active', section.id === target));
        navItems.forEach(item => item.classList.toggle('active', item.dataset.target === target));

        if (pageTitle && titles[target]) {
            pageTitle.textContent = titles[target];
        }

        if (breadcrumbCurrent && titles[target]) {
            breadcrumbCurrent.textContent = titles[target];
        }

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    navItems.forEach(item => item.addEventListener('click', () => activateSection(item.dataset.target)));
    quickBtns.forEach(btn => btn.addEventListener('click', () => activateSection(btn.dataset.target)));
});
