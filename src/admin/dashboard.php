<?php
// File: src/admin/dashboard.php
require_once '../auth.php';
require_once '../db_connect.php';

start_secure_session();
require_admin('../index.php?error=unauthorized_dashboard');

$pageTitle = "Dashboard";

require_once '../templates/header.php';
?>

<style>
    /* Estilos gerais da página */
    .filter-input { width: 140px; box-sizing: border-box; padding: 6px 10px; height: 35px; border: 1px solid #ccc; border-radius: 4px; }
    .form-field-group label { display: block; margin-bottom: 5px; font-weight: bold; }
    .filters-row { display: flex; flex-wrap: nowrap; gap: 15px; align-items: flex-end; margin-bottom: 20px; }
    .filters-row .form-field-group, .filters-row .filter-actions { margin-bottom: 0; }
    .filter-actions, .export-actions { display: flex; gap: 10px; align-self: flex-end; }
    .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; }
    .grid-item { border: 1px solid #ccc; border-radius: 5px; padding: 20px; display: flex; flex-direction: column; height: 380px; }
    .grid-item.kpi { justify-content: center; align-items: center; height: auto; min-height: 170px; }
    .grid-item h3 { margin-top: 0; margin-bottom: 15px; text-align: center; }
    .chart-canvas-container { flex-grow: 1; position: relative; }
    .kpi p { font-size: 1.8em; line-height: 1.9; margin: 0; text-align: center; }
    .kpi p strong { color: #007bff; }
    .button-small-action { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; font-size: 0.85em; font-weight: bold; border-radius: 5px; cursor: pointer; text-decoration: none; color: white; border: none; transition: background-color 0.2s; height: 35px; box-sizing: border-box; white-space: nowrap; }
    .button-filter-clear { background-color: #6c757d; }
    .button-filter-clear:hover { background-color: #5a6268; }
    .filters-row .button-filter { background-color: #007bff !important; }
    .filters-row .button-filter:hover { background-color: #0056b3 !important; }
    .button-export-pdf { background-color: #B30B00 !important; }
    .button-export-pdf:hover { background-color: #8C0900; }
    .button-small-action img { width: 18px; height: 18px; }
    .dashboard-footer-actions { display: flex; justify-content: flex-end; margin-top: 20px; width: 100%; }

    /* ================================================================== */
    /* CORREÇÃO DE ESTILO PARA O BOTÃO EXCEL                              */
    /* ================================================================== */
    a.button-small-action:hover {
        color: white !important; /* Garante que o texto fique branco */
        text-decoration: none !important; /* Remove o sublinhado */
    }
    #export_xls_btn:hover {
        background-color: #185232 !important; /* Escurece o verde no hover */
    }


    /* Estilos para a área de impressão do PDF */
    #printable-area {
        position: absolute;
        left: -9999px;
        padding: 20px;
        background: #fff;
        width: 1200px;
    }
    #printable-area .pdf-main-title { color: #007bff; text-align: center; margin-bottom: 25px; font-size: 28px; font-weight: bold; }
    #printable-area .filter-summary h3 { color: #007bff; font-size: 20px; margin-bottom: 10px; }
    #printable-area .filter-summary p { font-family: sans-serif; font-size: 14px; margin: 4px 0; }
    #printable-area h2 { font-size: 32px !important; text-align: center; margin-top: 25px; margin-bottom: 20px; }
    #printable-area .charts-grid { grid-template-columns: repeat(2, 1fr) !important; }
</style>

<div class="container admin-container">
    <header class="admin-header">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
    </header>
    
    <section id="dashboard-filters" class="dashboard-section">
        <h2>Filtros</h2>
        <form id="filters_form" class="form-admin">
            <div class="filters-row">
                <div class="form-field-group">
                    <label for="filter_date_start">Data Inicial:</label>
                    <input type="date" id="filter_date_start" name="filter_date_start" class="form-control filter-input">
                </div>
                <div class="form-field-group">
                    <label for="filter_date_end">Data Final:</label>
                    <input type="date" id="filter_date_end" name="filter_date_end" class="form-control filter-input">
                </div>
                <div class="form-field-group">
                    <label for="filter_status">Status:</label>
                    <select id="filter_status" name="filter_status" class="form-control filter-input">
                        <option value="">Todos</option>
                        <option value="Pendente">Pendente</option>
                        <option value="Devolvido">Devolvido</option>
                        <option value="Doado">Doado</option>
                        <option value="Descartado">Descartado</option>
                        <option value="Aguardando Aprovação">Aguardando Aprovação</option>
                    </select>
                </div>
                <div class="form-field-group">
                    <label for="filter_category">Categoria:</label>
                    <select id="filter_category" name="filter_category" class="form-control filter-input">
                        <option value="">Todas</option>
                    </select>
                </div>
                <div class="form-field-group">
                    <label for="filter_location">Local:</label>
                    <select id="filter_location" name="filter_location" class="form-control filter-input">
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="button" id="clear_filters_btn" class="button-small-action button-filter-clear">
                        <i class="fa-solid fa-broom"></i> Limpar Filtros
                    </button>
                    <button type="button" id="apply_filters_btn" class="button-small-action button-filter">
                        <i class="fa-solid fa-check"></i> Aplicar Filtro
                    </button>
                </div>
            </div>
        </form>
    </section>

    <section id="dashboard-charts" class="dashboard-section">
        <h2>Análises Gráficas</h2>
        <div class="charts-grid">
            <div class="grid-item">
                <h3>Itens por Status</h3>
                <div class="chart-canvas-container">
                    <canvas id="statusPieChart"></canvas>
                </div>
            </div>
            <div class="grid-item">
                <h3>Top Locais (Itens Encontrados)</h3>
                <div class="chart-canvas-container">
                    <canvas id="topLocationsChart"></canvas>
                </div>
            </div>
            <div class="grid-item">
                <h3>Itens Registrados vs. Resolvidos</h3>
                <div class="chart-canvas-container">
                    <canvas id="registeredResolvedChart"></canvas>
                </div>
            </div>
            <div class="grid-item">
                <h3>Itens Doados por Instituição (CNPJ)</h3>
                <div class="chart-canvas-container">
                    <canvas id="donationsInstitutionChart"></canvas>
                </div>
            </div>
            <div class="grid-item">
                <h3>Top 5 Categorias (Itens Pendentes)</h3>
                <div class="chart-canvas-container">
                    <canvas id="topPendingCategoriesChart"></canvas>
                </div>
            </div>
            <div class="grid-item kpi">
                <p>
                    Taxa de Devoluções: <strong id="devolutionRateKPI">--%</strong><br>
                    Taxa de Doações: <strong id="donationRateKPI">--%</strong><br>
                    Taxa de Descarte: <strong id="discardRateKPI">--%</strong><br>
                    Taxa de Pendências: <strong id="pendingRateKPI">--%</strong>
                </p>
            </div>
        </div>
    </section>

    <div class="dashboard-footer-actions">
        <div class="export-actions">
            <a href="#" id="export_xls_btn" class="button-small-action" style="background-color: #217346;" title="Exportar Dados para Excel">
                <img src="https://img.icons8.com/color/48/microsoft-excel-2019--v1.png" alt="Excel Icon"/>
                <span>Exportar para Excel</span>
            </a>
            <button type="button" id="export_pdf_btn" class="button-small-action button-export-pdf" title="Exportar Dashboard para PDF">
                <img src="https://img.icons8.com/ios-filled/50/ffffff/pdf.png" alt="PDF Icon"/>
                <span>Exportar Dashboard</span>
            </button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    Chart.register(ChartDataLabels);

    const exportXLSBtn = document.getElementById('export_xls_btn');
    exportXLSBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const dateStart = document.getElementById('filter_date_start').value;
        const dateEnd = document.getElementById('filter_date_end').value;
        const status = document.getElementById('filter_status').value;
        const categoryId = document.getElementById('filter_category').value;
        const locationId = document.getElementById('filter_location').value;
        let queryParams = new URLSearchParams();
        if (dateStart) queryParams.append('date_start', dateStart);
        if (dateEnd) queryParams.append('date_end', dateEnd);
        if (status) queryParams.append('status', status);
        if (categoryId) queryParams.append('category_id', categoryId);
        if (locationId) queryParams.append('location_id', locationId);
        window.location.href = `../export_xls.php?${queryParams.toString()}`;
    });

    const exportPDFBtn = document.getElementById('export_pdf_btn');
    exportPDFBtn.addEventListener('click', () => {
        const chartsSection = document.getElementById('dashboard-charts');
        const printArea = document.createElement('div');
        printArea.id = 'printable-area';
        const mainTitle = document.createElement('h1');
        mainTitle.className = 'pdf-main-title';
        mainTitle.textContent = 'Dashboard - Sistema de Achados e Perdidos';
        printArea.appendChild(mainTitle);
        const filterSummaryDiv = document.createElement('div');
        filterSummaryDiv.className = 'filter-summary';
        const summaryTitle = document.createElement('h3');
        summaryTitle.textContent = 'Filtros Selecionados';
        filterSummaryDiv.appendChild(summaryTitle);
        const summaryLines = [];
        const formatDate = (dateString) => {
            if (!dateString) return '';
            const [year, month, day] = dateString.split('-');
            return `${day}/${month}/${year}`;
        };
        const dateStart = document.getElementById('filter_date_start').value;
        const dateEnd = document.getElementById('filter_date_end').value;
        const statusSelect = document.getElementById('filter_status');
        const categorySelectForPdf = document.getElementById('filter_category');
        const locationSelectForPdf = document.getElementById('filter_location');
        if (dateStart) summaryLines.push(`Data Inicial: ${formatDate(dateStart)}`);
        if (dateEnd) summaryLines.push(`Data Final: ${formatDate(dateEnd)}`);
        if (statusSelect.value) summaryLines.push(`Status: ${statusSelect.options[statusSelect.selectedIndex].text}`);
        if (categorySelectForPdf.value) summaryLines.push(`Categoria: ${categorySelectForPdf.options[categorySelectForPdf.selectedIndex].text}`);
        if (locationSelectForPdf.value) summaryLines.push(`Local: ${locationSelectForPdf.options[locationSelectForPdf.selectedIndex].text}`);
        if (summaryLines.length === 0) {
            const p = document.createElement('p');
            p.textContent = 'Não há filtros selecionados';
            filterSummaryDiv.appendChild(p);
        } else {
            summaryLines.forEach(line => {
                const p = document.createElement('p');
                p.textContent = line;
                filterSummaryDiv.appendChild(p);
            });
        }
        printArea.appendChild(filterSummaryDiv);
        printArea.appendChild(chartsSection.cloneNode(true));
        document.body.appendChild(printArea);
        const originalCharts = [
            { id: 'statusPieChart', instance: statusPieChartInstance },
            { id: 'topLocationsChart', instance: topLocationsChartInstance },
            { id: 'registeredResolvedChart', instance: registeredResolvedChartInstance },
            { id: 'donationsInstitutionChart', instance: donationsByInstitutionChartInstance },
            { id: 'topPendingCategoriesChart', instance: topPendingCategoriesChartInstance }
        ];
        originalCharts.forEach(chartInfo => {
            if (chartInfo.instance) {
                const clonedCanvas = printArea.querySelector(`#${chartInfo.id}`);
                new Chart(clonedCanvas, {
                    type: chartInfo.instance.config.type,
                    data: chartInfo.instance.config.data,
                    options: { ...chartInfo.instance.config.options, animation: { duration: 0 }, responsive: false }
                });
            }
        });
        setTimeout(() => {
            const { jsPDF } = window.jspdf;
            html2canvas(printArea, { scale: 2, width: printArea.scrollWidth, height: printArea.scrollHeight }).then(canvas => {
                document.body.removeChild(printArea);
                const imgData = canvas.toDataURL('image/png');
                const pdf = new jsPDF('p', 'mm', 'a4');
                const pdfWidth = pdf.internal.pageSize.getWidth();
                const pdfHeight = (canvas.height * pdfWidth) / canvas.width;
                pdf.addImage(imgData, 'PNG', 5, 5, pdfWidth - 10, pdfHeight);
                const today = new Date();
                const dateStr = today.getFullYear() + '-' + (today.getMonth() + 1).toString().padStart(2, '0') + '-' + today.getDate().toString().padStart(2, '0');
                const fileName = `dashboard-analise-${dateStr}.pdf`;
                pdf.save(fileName);
            });
        }, 500);
    });

    const filterForm = document.getElementById('filters_form');
    const applyFiltersBtn = document.getElementById('apply_filters_btn');
    const clearFiltersBtn = document.getElementById('clear_filters_btn');
    const categorySelect = document.getElementById('filter_category');
    const locationSelect = document.getElementById('filter_location');

    let statusPieChartInstance = null;
    let topLocationsChartInstance = null;
    let registeredResolvedChartInstance = null;
    let donationsByInstitutionChartInstance = null;
    let topPendingCategoriesChartInstance = null;

    async function fetchDashboardData() {
        const dateStart = document.getElementById('filter_date_start').value;
        const dateEnd = document.getElementById('filter_date_end').value;
        const status = document.getElementById('filter_status').value;
        const categoryId = categorySelect.value;
        const locationId = locationSelect.value;
        let queryParams = new URLSearchParams();
        if (dateStart) queryParams.append('date_start', dateStart);
        if (dateEnd) queryParams.append('date_end', dateEnd);
        if (status) queryParams.append('status', status);
        if (categoryId) queryParams.append('category_id', categoryId);
        if (locationId) queryParams.append('location_id', locationId);
        try {
            const response = await fetch(`../get_dashboard_data.php?${queryParams.toString()}`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();
            if (data.errors && data.errors.length > 0) {
                alert("Erro ao carregar dados do dashboard:\n" + data.errors.join("\n"));
            }
            const firstLoad = categorySelect.options.length <= 1;
            if (firstLoad && data.available_categories) {
                populateSelect(categorySelect, data.available_categories, "Todas");
            }
            if (firstLoad && data.available_locations) {
                populateSelect(locationSelect, data.available_locations, "Todos");
            }
            renderStatusPieChart(data.status_counts || []);
            renderTopLocationsChart(data.top_locations || []);
            renderRegisteredResolvedChart(
                data.registered_items_timeline || [],
                data.devolved_items_timeline || [],
                data.donated_items_timeline || []
            );
            renderDonationsByInstitutionChart(data.donations_by_institution || []);
            renderTopPendingCategoriesChart(data.top_pending_categories || []);
            updateRatesKPIs(data);
        } catch (error) {
            console.error('Erro ao buscar dados do dashboard:', error);
            alert('Falha ao carregar dados do dashboard. Verifique o console para mais detalhes.');
        }
    }

    function populateSelect(selectElement, options, defaultOptionText = "Todas") {
        const currentValue = selectElement.value;
        selectElement.innerHTML = `<option value="">${defaultOptionText}</option>`;
        options.forEach(option => {
            const opt = document.createElement('option');
            opt.value = option.id;
            opt.textContent = option.name;
            selectElement.appendChild(opt);
        });
        if (options.find(opt => opt.id == currentValue)) {
            selectElement.value = currentValue;
        }
    }

    function updateRatesKPIs(data) {
        const devolutionKPI = document.getElementById('devolutionRateKPI');
        const donationKPI = document.getElementById('donationRateKPI');
        const pendingKPI = document.getElementById('pendingRateKPI');
        const discardKPI = document.getElementById('discardRateKPI');

        if (devolutionKPI) {
            devolutionKPI.textContent = `${parseFloat(data.devolution_rate || 0).toFixed(2)}%`;
        }
        if (donationKPI) {
            donationKPI.textContent = `${parseFloat(data.donation_rate || 0).toFixed(2)}%`;
        }
        if (discardKPI) {
            discardKPI.textContent = `${parseFloat(data.discard_rate || 0).toFixed(2)}%`;
        }
        if (pendingKPI) {
            pendingKPI.textContent = `${parseFloat(data.pending_rate || 0).toFixed(2)}%`;
        }
    }

    function renderStatusPieChart(statusData) {
        const ctx = document.getElementById('statusPieChart');
        if (!ctx) return;
        const labels = statusData.map(item => item.status);
        const data = statusData.map(item => item.total);
        const backgroundColors = ['rgba(255, 99, 132, 0.7)', 'rgba(54, 162, 235, 0.7)', 'rgba(255, 206, 86, 0.7)', 'rgba(75, 192, 192, 0.7)', 'rgba(153, 102, 255, 0.7)', 'rgba(255, 159, 64, 0.7)'];
        const borderColors = backgroundColors.map(color => color.replace('0.7', '1'));
        if (statusPieChartInstance) statusPieChartInstance.destroy();
        statusPieChartInstance = new Chart(ctx, {
            type: 'pie',
            data: { labels, datasets: [{ label: 'Itens por Status', data, backgroundColor: backgroundColors.slice(0, data.length), borderColor: borderColors.slice(0, data.length), borderWidth: 1 }] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    datalabels: {
                        formatter: (value, ctx) => {
                            return value > 0 ? value : '';
                        },
                        color: '#fff',
                        font: {
                            weight: 'bold',
                            size: 14
                        }
                    }
                }
            }
        });
    }

    function renderTopLocationsChart(locationData) {
        const ctx = document.getElementById('topLocationsChart');
        if (!ctx) return;
        const labels = locationData.map(item => item.location_name);
        const data = locationData.map(item => item.total_items);
        if (topLocationsChartInstance) topLocationsChartInstance.destroy();
        topLocationsChartInstance = new Chart(ctx, {
            type: 'bar',
            data: { labels, datasets: [{ label: 'Nº de Itens Encontrados', data, backgroundColor: 'rgba(75, 192, 192, 0.7)', borderColor: 'rgba(75, 192, 192, 1)', borderWidth: 1 }] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } },
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        anchor: 'center',
                        align: 'center',
                        color: '#fff',
                        font: {
                            weight: 'bold'
                        },
                        formatter: (value) => {
                            return value > 0 ? value : '';
                        }
                    }
                }
            }
        });
    }

    function renderRegisteredResolvedChart(registered, devolved, donated) {
        const ctx = document.getElementById('registeredResolvedChart');
        if (!ctx) return;
        const allDatesSet = new Set([...registered.map(i => i.date), ...devolved.map(i => i.date), ...donated.map(i => i.date)]);
        const sortedDates = Array.from(allDatesSet).sort((a, b) => new Date(a) - new Date(b));
        const registeredData = processTimelineData(registered, sortedDates);
        const devolvedData = processTimelineData(devolved, sortedDates);
        const donatedData = processTimelineData(donated, sortedDates);
        if (registeredResolvedChartInstance) registeredResolvedChartInstance.destroy();
        registeredResolvedChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: sortedDates,
                datasets: [
                    { label: 'Registrados', data: registeredData, borderColor: 'rgba(54, 162, 235, 1)', backgroundColor: 'rgba(54, 162, 235, 0.5)', fill: false, tension: 0.1 },
                    { label: 'Devolvidos', data: devolvedData, borderColor: 'rgba(75, 192, 192, 1)', backgroundColor: 'rgba(75, 192, 192, 0.5)', fill: false, tension: 0.1 },
                    { label: 'Doados', data: donatedData, borderColor: 'rgba(255, 159, 64, 1)', backgroundColor: 'rgba(255, 159, 64, 0.5)', fill: false, tension: 0.1 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { type: 'time', time: { unit: 'day', tooltipFormat: 'dd/MM/yyyy', displayFormats: { day: 'dd/MM/yy' } }, title: { display: true, text: 'Data' } },
                    y: { beginAtZero: true, title: { display: true, text: 'Quantidade de Itens' }, ticks: { stepSize: 1 } }
                },
                plugins: {
                    legend: { position: 'top' },
                    datalabels: {
                        display: false
                    }
                }
            }
        });
    }

    function renderDonationsByInstitutionChart(donationData) {
        const ctx = document.getElementById('donationsInstitutionChart');
        if(!ctx) return;
        const labels = donationData.map(item => item.institution_name || item.institution_cnpj || 'Desconhecida');
        const data = donationData.map(item => parseInt(item.total_items, 10));
        if (donationsByInstitutionChartInstance) donationsByInstitutionChartInstance.destroy();
        donationsByInstitutionChartInstance = new Chart(ctx, {
            type: 'bar',
            data: { labels, datasets: [{ label: 'Itens Doados', data, backgroundColor: 'rgba(153, 102, 255, 0.7)', borderColor: 'rgba(153, 102, 255, 1)', borderWidth: 1 }] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } },
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        anchor: 'center',
                        align: 'center',
                        color: '#fff',
                        font: {
                            weight: 'bold'
                        },
                        formatter: (value) => {
                            return value > 0 ? value : '';
                        }
                    }
                }
            }
        });
    }

    function renderTopPendingCategoriesChart(pendingCategoriesData) {
        const ctx = document.getElementById('topPendingCategoriesChart');
        if (!ctx) return;
        const labels = pendingCategoriesData.map(item => item.category_name);
        const data = pendingCategoriesData.map(item => parseInt(item.total_pending, 10));
        if (topPendingCategoriesChartInstance) topPendingCategoriesChartInstance.destroy();
        topPendingCategoriesChartInstance = new Chart(ctx, {
            type: 'bar',
            data: { labels, datasets: [{ label: 'Itens Pendentes', data, backgroundColor: 'rgba(255, 99, 132, 0.7)', borderColor: 'rgba(255, 99, 132, 1)', borderWidth: 1 }] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } },
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        anchor: 'center',
                        align: 'center',
                        color: '#fff',
                        font: {
                            weight: 'bold'
                        },
                        formatter: (value) => {
                            return value > 0 ? value : '';
                        }
                    }
                }
            }
        });
    }

    function processTimelineData(timelineData, allDates) {
        const dataMap = new Map(timelineData.map(item => [item.date, parseInt(item.count, 10)]));
        return allDates.map(date => dataMap.get(date) || 0);
    }

    if (applyFiltersBtn) applyFiltersBtn.addEventListener('click', fetchDashboardData);
    if (clearFiltersBtn) clearFiltersBtn.addEventListener('click', () => { filterForm.reset(); fetchDashboardData(); });

    fetchDashboardData();
});
</script>

<?php
require_once '../templates/footer.php';
?>