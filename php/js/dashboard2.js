// ---------- Sélection des éléments DOM principaux ----------
const table = document.getElementById('table-clients');
const tbody = table.querySelector('tbody');
const headers = table.querySelectorAll('th[data-type]');
const perPageSelect = document.getElementById('perPage');
const searchInput = document.getElementById('search');
const resultCountEl = document.getElementById('result-count');
const visibleTotalEl = document.getElementById('visible-total');
const totalNegDisplay = document.getElementById('total-neg-display');
const totalRemises = document.getElementById('total-remises');
const sidebarBackdrop = document.getElementById('sidebarBackdrop');
const sidebarClose = document.getElementById('sidebarClose');

// Canvas et sélecteur de graphique
const ctx = document.getElementById('graphique').getContext('2d');
const pieCtx = document.getElementById('pieChart').getContext('2d');
const chartTypeSelect = document.getElementById('chartTypeSelect');


let rowsData = [];            // tableau d'objets dérivés du DOM
let filteredIndexList = [];   // indices des rowsData affichés après filtre
let currentPage = 1;

let chartInstance;
let pieChartInstance;

// ALL_TRANSACTIONS est défini via un tag <script> dans le PHP (si le PO a accès à tout)
// const ALL_TRANSACTIONS = (typeof ALL_TRANSACTIONS !== 'undefined') ? ALL_TRANSACTIONS : [];


// ---------- Fonctions utilitaires ----------
function parseAmount(text) {
    if (!text) return 0;
    // Remplace la virgule par un point et retire les espaces pour le format français
    const cleaned = text.replace(/\s/g, '').replace('€', '').replace('$', '').replace(',', '.');
    const num = parseFloat(cleaned) || 0;
    return num;
}

function parseDateDMY(text) {
    if (!text) return new Date('1970-01-01');
    const parts = text.split('/');
    if (parts.length !== 3) return new Date(text);
    const d = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10);
    const yRaw = parseInt(parts[2], 10);
    const y = yRaw < 100 ? 2000 + yRaw : yRaw;
    // Format YYYY, MM-1, DD
    return new Date(y, m - 1, d);
}

// ---------- Initialisation des lignes ----------
function initRows() {
    rowsData = [];
    const dataRows = Array.from(tbody.querySelectorAll('tr.data-row'));

    dataRows.forEach((r, idx) => {
        r.dataset.rowId = idx; // index stocké dans le DOM

        const cells = r.children;
        const date = cells[0].textContent.trim();
        const intitule = cells[1].textContent.trim();
        const siret = cells[2].textContent.trim();
        const montantText = cells[3].textContent.trim();
        
        // On récupère la valeur numérique signée du data-attribute pour une meilleure précision
        const montant = parseFloat(r.getAttribute('data-montant-val')) || parseAmount(montantText);

        // lecture JSON data-impayes (data-remises est ignoré ici car on utilise ALL_TRANSACTIONS)
        let impayes = [];
        try { impayes = JSON.parse(r.getAttribute('data-impayes') || '[]'); } catch (e) { impayes = []; }

        rowsData.push({
            rowEl: r,
            dateText: date,
            dateObj: parseDateDMY(date),
            intitule,
            siret,
            montant,
            montantText,
            impayes,
            idClient: r.getAttribute('data-id-client'), // Ajout de l'ID client pour 'Accéder'
            idRemise: r.getAttribute('data-id-remise'), // Ajout de l'ID remise pour 'Voir Plus'
            transactionLibelle: r.getAttribute('data-transaction-libelle')
        });
    });
}

// ---------- Coloration par bande de montant ----------
function applyColorBands() {
    // Logique conservée
    rowsData.forEach(item => {
        item.rowEl.classList.remove('color-band-1', 'color-band-2', 'color-band-3');
        const absM = Math.abs(item.montant);
        const bucket = Math.floor(absM / 100);

        if (bucket <= 1) item.rowEl.classList.add('color-band-1');
        else if (bucket <= 9) item.rowEl.classList.add('color-band-2');
        else item.rowEl.classList.add('color-band-3');
    });
}

// ---------- Compteurs et totaux ----------
function updateCountersAndTotals(visibleIndices) {
    let total = 0;
    let totalNeg = 0; // Total des montants négatifs (dépenses/impayés)

    visibleIndices.forEach(i => {
        const m = rowsData[i].montant;
        total += m;
        if (m < 0) totalNeg += m;
    });

    // Mise à jour du Total (visible)
    const totalDisplay = total.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' $';
    visibleTotalEl.textContent = totalDisplay.replace('−', '-'); // Utilise le tiret standard
    visibleTotalEl.className = total < 0 ? 'negatif' : 'positif';

    if (totalNeg < 0) {
        const totalNegFormat = totalNeg.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' $';
        totalNegDisplay.textContent = `Total Débit (visible) : ${totalNegFormat.replace('−', '-')}`;
        totalNegDisplay.style.color = 'var(--danger)'; // Assurez-vous que --danger est défini
    } else {
        totalNegDisplay.textContent = '';
    }

    totalRemises.textContent = ` | ${filteredIndexList.length} transactions trouvées`;
    resultCountEl.textContent = `${filteredIndexList.length} résultat(s)`;
}

// ---------- Rendu de la page (pagination) ----------
function renderPage() {
    const perPage = parseInt(perPageSelect.value, 10);
    const total = filteredIndexList.length;
    const totalPages = Math.max(1, Math.ceil(total / perPage));

    if (currentPage > totalPages) currentPage = totalPages;

    const start = (currentPage - 1) * perPage;
    const slice = filteredIndexList.slice(start, start + perPage);

    rowsData.forEach(item => item.rowEl.style.display = 'none');
    slice.forEach(i => {
        rowsData[i].rowEl.style.display = 'table-row';
    });

    const paginationEl = document.getElementById('pagination');
    paginationEl.innerHTML = '';
    for (let p = 1; p <= totalPages; p++) {
        const btn = document.createElement('button');
        btn.textContent = p;
        btn.className = 'btn';
        if (p === currentPage) btn.classList.add('primary');
        btn.addEventListener('click', () => {
            currentPage = p;
            renderPage();
        });
        paginationEl.appendChild(btn);
    }

    document.getElementById('prevPage').disabled = currentPage === 1;
    document.getElementById('nextPage').disabled = currentPage === totalPages;

    updateCountersAndTotals(slice);
}

// ---------- Filtre (recherche + dates) ----------
function applyFilterAndRefresh() {
    const q = searchInput.value.trim().toLowerCase();
    const startDateInput = document.getElementById('dateStart').value;
    const endDateInput = document.getElementById('dateEnd').value;
    const startDate = startDateInput ? new Date(startDateInput) : null;
    const endDate = endDateInput ? new Date(endDateInput) : null;

    filteredIndexList = [];

    rowsData.forEach((item, idx) => {
        // Texte de base à rechercher
        let hay = (item.dateText + ' ' + item.intitule + ' ' + item.siret + ' ' + item.montantText + ' ' + item.transactionLibelle).toLowerCase();
        
        // Recherche dans le libellé des impayés (si disponible dans le data-impayes)
        const impayeLibelle = item.impayes.length > 0 ? (item.impayes[0].Libelle_impaye || item.impayes[0].Libelle || '').toLowerCase() : '';
        hay += ' ' + impayeLibelle;
        
        if (q && !hay.includes(q)) return;

        // Filtre par date
        if (startDate || endDate) {
            const d = item.dateObj;
            // On s'assure que la date de fin inclut toute la journée
            const adjustedEndDate = endDate ? new Date(endDate.getFullYear(), endDate.getMonth(), endDate.getDate(), 23, 59, 59) : null;
            
            if (startDate && d < startDate) return;
            if (adjustedEndDate && d > adjustedEndDate) return;
        }

        filteredIndexList.push(idx);
    });

    currentPage = 1;
    renderPage();
    updateChartDatasets(); // Mettre à jour le graphique avec les données filtrées
}

// ---------- Tri des lignes ----------
let sortCol = 0;
let sortAscending = false;

function sortRows(indexCol, type, ascending) {
    const compare = (a, b) => {
        if (type === 'number') {
            return ascending ? a.montant - b.montant : b.montant - a.montant;
        }
        if (type === 'date') {
            return ascending ? a.dateObj - b.dateObj : b.dateObj - a.dateObj;
        }

        // text : index 1 = intitule (Nom), 2 = siret (N° Siret)
        let valA, valB;
        if (indexCol === 1) {
            valA = a.intitule.toLowerCase();
            valB = b.intitule.toLowerCase();
        } else {
            valA = a.siret.toLowerCase();
            valB = b.siret.toLowerCase();
        }
        return ascending ? valA.localeCompare(valB) : valB.localeCompare(valA);
    };

    filteredIndexList.sort((i, j) => compare(rowsData[i], rowsData[j]));
    currentPage = 1;
    renderPage();
}

// ---------- Logique de tri (header) ----------
function wireTableHeaders() {
    headers.forEach((h, index) => {
        h.addEventListener('click', () => {
            if (sortCol === index) {
                sortAscending = !sortAscending;
            } else {
                sortCol = index;
                sortAscending = true;
            }

            headers.forEach(hh => hh.querySelector('.arrow').textContent = '↓');
            h.querySelector('.arrow').textContent = sortAscending ? '↑' : '↓';

            sortRows(index, h.dataset.type, sortAscending);
        });
    });
}


// ---------- Chart.js : Calcul et mise à jour des graphiques ----------

// Calcule les données pour le graphique d'évolution (Trésorerie)
function computeChartDatas() {
    // Récupère toutes les dates uniques des transactions
    const dates = Array.from(new Set(rowsData.map(r => r.dateText)));
    dates.sort((a, b) => parseDateDMY(a) - parseDateDMY(b));
    
    // Initialisation des maps
    const caMap = {}; // Crédits (Sens='+')
    const impMap = {}; // Débits/Impayés (Sens='-')
    dates.forEach(l => {
        caMap[l] = 0;
        impMap[l] = 0;
    });

    // Agrégation des montants par date (basée sur toutes les transactions)
    rowsData.forEach(r => {
        const montant = parseFloat(r.montant);
        if (montant >= 0) {
            caMap[r.dateText] += montant;
        } else {
            // Montant absolu pour la ligne des dépenses/débits
            impMap[r.dateText] += Math.abs(montant); 
        }
    });

    // Préparation des tableaux pour Chart.js
    const caArr = dates.map(l => caMap[l]);
    const impArr = dates.map(l => impMap[l]);

    return { labels: dates, caArr, impArr };
}

// Met à jour les datasets du graphique principal avec les données filtrées
function updateChartDatasets() {
    // Collecte des données à partir des lignes filtrées
    const labelsSet = new Set();
    filteredIndexList.forEach(i => labelsSet.add(rowsData[i].dateText));
    let labels = Array.from(labelsSet);
    labels.sort((a, b) => parseDateDMY(a) - parseDateDMY(b));

    const caMap = {};
    const impMap = {};
    labels.forEach(l => {
        caMap[l] = 0;
        impMap[l] = 0;
    });

    filteredIndexList.forEach(i => {
        const r = rowsData[i];
        const montant = parseFloat(r.montant);
        if (montant >= 0) {
            caMap[r.dateText] += montant;
        } else {
            impMap[r.dateText] += Math.abs(montant);
        }
    });

    // Mise à jour du graphique de trésorerie
    chartInstance.data.labels = labels;
    chartInstance.data.datasets[0].data = labels.map(l => caMap[l]); // Crédits
    chartInstance.data.datasets[1].data = labels.map(l => impMap[l]); // Débits
    chartInstance.update();
    
    // Met à jour le camembert basé sur les données filtrées
    updatePieChart();
}


// Met à jour le graphique en camembert (Répartition des Impayés)
function updatePieChart() {
    const motifMap = {}; // Clé: Libellé de l'impayé, Valeur: Total Montant

    // On utilise les lignes filtrées pour la répartition
    const indices = filteredIndexList.length ? filteredIndexList : rowsData.map((_, idx) => idx);

    indices.forEach(i => {
        const item = rowsData[i];
        // Vérifie si la transaction est un impayé
        if (item.impayes && item.impayes.length > 0) {
            item.impayes.forEach(impaye => {
                const motif = impaye.Libelle_impaye || item.transactionLibelle || 'Autre impayé';
                const montant = parseFloat(impaye.Montant || 0); // Le montant est toujours positif pour l'impayé ici
                
                if (motifMap[motif]) {
                    motifMap[motif] += montant;
                } else {
                    motifMap[motif] = montant;
                }
            });
        }
    });

    const labels = Object.keys(motifMap);
    const data = Object.values(motifMap);

    const backgroundColors = labels.map((_, index) => [
        '#ff6384', '#36a2eb', '#cc65fe', '#ffce56', '#4bc0c0', '#f39c12', '#9b59b6', '#3498db'
    ][index % 8]);

    // Mise à jour du camembert
    pieChartInstance.data.labels = labels;
    pieChartInstance.data.datasets[0].data = data;
    pieChartInstance.data.datasets[0].backgroundColor = backgroundColors;
    
    // Masquer le graphique si aucune donnée
    const displayStyle = data.length > 0 ? 'block' : 'none';
    pieCtx.closest('div').style.display = displayStyle;

    pieChartInstance.update();
}

// ---------- Sidebar : Voir Plus ----------
function showSidebar(rowIndex) {
    const item = rowsData[rowIndex];
    const impayesTableBody = document.getElementById('impayesTable').querySelector('tbody');
    const remisesTableBody = document.getElementById('remisesTable').querySelector('tbody');
    const sidebarTotals = document.getElementById('sidebarTotals');
    const sidebarTitle = document.getElementById('sidebarTitle');
    
    sidebarTitle.textContent = `Détails Transaction : ${item.intitule} - ${item.dateText}`;
    impayesTableBody.innerHTML = '';
    remisesTableBody.innerHTML = '';

    let sumImp = 0;
    let sumRem = 0;
    
    // 1. Impayés (seulement si la transaction courante est un impayé)
    if (item.impayes && item.impayes.length > 0) {
        item.impayes.forEach(i => {
            const tr = document.createElement('tr');
            const montantDisplay = parseFloat(i.Montant).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' $';
            tr.innerHTML = `
                <td>${new Date(i.Date_Transaction).toLocaleDateString('fr-FR')}</td>
                <td>${i.Num_dossier || 'N/A'}</td> 
                <td>${i.Libelle_impaye || i.Libelle}</td>
                <td>${montantDisplay}</td>
            `;
            impayesTableBody.appendChild(tr);
            sumImp += Number(i.Montant || 0);
        });
        document.querySelector('#impayesTable').style.display = 'table';
        document.querySelector('#impayesTable').previousElementSibling.style.display = 'block'; // Afficher le titre 'Impayés'
    } else {
        document.querySelector('#impayesTable').style.display = 'none';
        document.querySelector('#impayesTable').previousElementSibling.style.display = 'none';
    }

    // 2. Rémises (Toutes transactions appartenant à la même Remise)
    const id_remise = item.idRemise;
    if (id_remise && typeof ALL_TRANSACTIONS !== 'undefined') {
        // Filtrer ALL_TRANSACTIONS (disponible depuis le PHP) par Id_Remise
        const transactionsForRemise = ALL_TRANSACTIONS.filter(t => t.Id_Remise == id_remise);
        
        transactionsForRemise.forEach(t => {
            const tr = document.createElement('tr');
            const montantVal = (t.Sens == '-') ? -parseFloat(t.Montant) : parseFloat(t.Montant);
            const montantDisplay = montantVal.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' $';
            const classe = montantVal < 0 ? 'negatif' : 'positif';
            
            const sensDisplay = t.Sens === '+' ? 'Crédit' : 'Débit';

            tr.innerHTML = `
                <td>${new Date(t.Date_Transaction).toLocaleDateString('fr-FR')}</td>
                <td>${sensDisplay}</td>
                <td>${t.Libelle}</td>
                <td class="${classe}">${montantDisplay}</td>
            `;
            remisesTableBody.appendChild(tr);
            sumRem += montantVal;
        });
        document.querySelector('#remisesTable').style.display = 'table';
        document.querySelector('#remisesTable').previousElementSibling.style.display = 'block'; // Afficher le titre 'Rémises'
    } else {
        document.querySelector('#remisesTable').style.display = 'none';
        document.querySelector('#remisesTable').previousElementSibling.style.display = 'none';
    }

    sidebarTotals.textContent = `Total Remise: ${sumRem.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} $`;
    sidebarBackdrop.style.display = 'flex';
    sidebarBackdrop.currentRowIndex = rowIndex;
}

function closeSidebar() {
    sidebarBackdrop.style.display = 'none';
}
sidebarClose.addEventListener('click', closeSidebar);
sidebarBackdrop.addEventListener('click', (e) => { 
    if (e.target === sidebarBackdrop) closeSidebar(); 
});

// Boutons "Voir Plus"
function attachVoirPlus() {
    const voirBtns = Array.from(document.querySelectorAll('.btn-voir'));
    voirBtns.forEach(btn => {
        btn.removeEventListener('click', voirPlusHandler);
        btn.addEventListener('click', voirPlusHandler);
    });
}
function voirPlusHandler(e) {
    const row = e.target.closest('tr.data-row');
    if (!row) return;
    const rowIndex = parseInt(row.dataset.rowId, 10);
    showSidebar(rowIndex);
}

// Boutons "Accéder Compte"
function attachAccederAccount() {
    const accBtn = Array.from(document.querySelectorAll('.btn-acceder'));
    accBtn.forEach(btn => {
        btn.removeEventListener('click', accederAccountHandler);
        btn.addEventListener('click', accederAccountHandler);
    });
}
function accederAccountHandler(e) {
    const row = e.target.closest('tr.data-row');
    if (!row) return;

    const idClient = row.getAttribute('data-id-client');
    const item = rowsData[row.dataset.rowId];
    
    if (idClient) {
        const clientName = item.intitule;
        const clientSiret = item.siret;

        alert(
            `Accès au compte Client (ID: ${idClient})\n\n` +
            `Nom Utilisateur: ${clientName}\n` +
            `N° SIRET: ${clientSiret}\n\n` +
            `Cette fonction devrait charger un tableau de bord spécifique au client.`
        );
    } else {
        alert("Information client non disponible pour cette transaction. (Client ID manquant)");
    }
}

// ---------- Exports CSV / XLS / PDF ----------

function getExportData(forSidebar = false, sidebarType = 'impayes') {
    if (forSidebar) {
        const tbl = document.getElementById(sidebarType === 'impayes' ? 'impayesTable' : 'remisesTable');
        const head = [Array.from(tbl.querySelector('thead tr').children).map(th => th.textContent.trim())];
        const body = Array.from(tbl.querySelectorAll('tbody tr')).map(tr => Array.from(tr.children).map(td => td.textContent.trim()) );
        return { head, body };
    } else {
        const perPage = parseInt(perPageSelect.value, 10);
        const start = (currentPage - 1) * perPage;
        const slice = filteredIndexList.slice(start, start + perPage);
        const body = slice.map(i => {
            const item = rowsData[i];
            return [item.dateText, item.intitule, item.siret, item.montantText];
        });
        const head = [['Date', 'Nom', 'N° Siret', 'Montant']];
        return { head, body };
    }
}


function exportCSV(forSidebar = false, sidebarType = 'impayes') {
    const { head, body } = getExportData(forSidebar, sidebarType);
    let csv = head[0].join(';') + '\n';
    body.forEach(r => {
        csv += r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(';') + '\n';
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = forSidebar ? `${sidebarType.toUpperCase()}_EXPORT.csv` : 'TRANSACTIONS_EXPORT.csv';
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

function exportXLS(forSidebar = false, sidebarType = 'impayes') {
    const { head, body } = getExportData(forSidebar, sidebarType);
    const wsData = [head[0], ...body];
    
    const ws = XLSX.utils.aoa_to_sheet(wsData);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Feuille1");

    XLSX.writeFile(wb, forSidebar ? `${sidebarType.toUpperCase()}_EXPORT.xlsx` : 'TRANSACTIONS_EXPORT.xlsx');
}

function exportPDF(forSidebar = false, sidebarType = 'impayes') {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    let titleText = forSidebar ? `Export ${sidebarType.toUpperCase()}` : 'Export Transactions';
    
    doc.text(titleText, 14, 10);
    
    const { head, body } = getExportData(forSidebar, sidebarType);

    doc.autoTable({ 
        head: head, 
        body: body, 
        startY: 15, 
        styles: { fontSize: 9 } 
    });

    doc.save(forSidebar ? `${sidebarType.toUpperCase()}_EXPORT.pdf` : 'TRANSACTIONS_EXPORT.pdf');
}


// Association des écouteurs d'événements pour les exports
document.getElementById('export-csv').addEventListener('click', () => { exportCSV(false); });
document.getElementById('export-xls').addEventListener('click', () => { exportXLS(false); });
document.getElementById('export-pdf').addEventListener('click', () => { exportPDF(false); });

document.getElementById('export-sidebar-csv').addEventListener('click', () => { exportCSV(true, sidebarBackdrop.currentTable === 'impayes' ? 'impayes' : 'remises'); });
document.getElementById('export-sidebar-xls').addEventListener('click', () => { exportXLS(true, sidebarBackdrop.currentTable === 'impayes' ? 'impayes' : 'remises'); });
document.getElementById('export-sidebar-pdf').addEventListener('click', () => { exportPDF(true, sidebarBackdrop.currentTable === 'impayes' ? 'impayes' : 'remises'); });

// Ajout d'un écouteur pour que les boutons export de la sidebar sachent quelle table exporter (la table impayes par défaut)
document.querySelector('#impayesTable').previousElementSibling.addEventListener('click', () => { sidebarBackdrop.currentTable = 'impayes'; });
document.querySelector('#remisesTable').previousElementSibling.addEventListener('click', () => { sidebarBackdrop.currentTable = 'remises'; });


// ---------- Boot global ----------
function boot() {
    initRows();
    applyColorBands();
    wireTableHeaders();
    attachVoirPlus();
    attachAccederAccount();

    filteredIndexList = rowsData.map((_, idx) => idx);
    currentPage = 1;
    renderPage();

    // 1. Gestion du Solde Global (Coloration Positive/Négative)
    const solde = parseFloat(document.getElementById('solde-global').textContent.replace(/[^\d.,-]/g, '').replace(',', '.'));
    const soldeEl = document.getElementById('solde-global');

    soldeEl.classList.remove('positif', 'negatif'); // Nettoyer les classes
    if (solde < 0) {
        soldeEl.classList.add('negatif'); // Rouge si négatif
    } else if (solde > 0) {
        soldeEl.classList.add('positif'); // Vert si positif
    }
    // Si le solde global n'était pas disponible via PHP, on le calcule ici (mais le PHP est prioritaire)
    if (isNaN(solde)) {
        const calculatedSolde = rowsData.reduce((acc, r) => acc + r.montant, 0);
        soldeEl.textContent = calculatedSolde.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' $';
    }


    // 2. Initialisation des graphiques
    const initialChartData = computeChartDatas();
    
    // Graphique principal (Évolution de la trésorerie)
    chartInstance = new Chart(ctx, {
        type: chartTypeSelect.value, // Type initial (line ou bar)
        data: {
            labels: initialChartData.labels,
            datasets: [{
                label: 'Crédit (Ventes)',
                data: initialChartData.caArr,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.5)',
                tension: 0.1,
                yAxisID: 'y',
                fill: false // Pas de remplissage pour la courbe par défaut
            },
            {
                label: 'Débit (Dépenses/Impayés)',
                data: initialChartData.impArr,
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.5)',
                tension: 0.1,
                yAxisID: 'y',
                fill: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('fr-FR') + ' $';
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += context.parsed.y.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' $';
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });

    // Camembert (Répartition des Impayés)
    pieChartInstance = new Chart(pieCtx, {
        type: 'pie',
        data: {
            labels: [],
            datasets: [{
                data: [],
                backgroundColor: [],
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 10
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed !== null) {
                                label += context.parsed.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' $';
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });
    
    updatePieChart(); // Appel initial pour remplir le camembert
    updateChartDatasets(); // S'assurer que le graphique est à jour avec les filtres initiaux (aucun)
}

// Événements
perPageSelect.addEventListener('change', renderPage);
searchInput.addEventListener('input', applyFilterAndRefresh);
document.getElementById('applyDateRange').addEventListener('click', applyFilterAndRefresh);

chartTypeSelect.addEventListener('change', (e) => {
    const t = e.target.value;
    chartInstance.config.type = t; 
    
    // Pour l'histogramme, on peut vouloir remplir les barres
    if (t === 'bar') {
        chartInstance.data.datasets.forEach(dataset => dataset.fill = true);
    } else {
        chartInstance.data.datasets.forEach(dataset => dataset.fill = false);
    }
    
    chartInstance.update();
});

document.addEventListener('DOMContentLoaded', boot);

// Supprimer les gestionnaires d'événements inutiles du code précédent
document.getElementById('prevPage').addEventListener('click', () => {
    if (currentPage > 1) {
        currentPage--;
        renderPage();
    }
});

document.getElementById('nextPage').addEventListener('click', () => {
    const perPage = parseInt(perPageSelect.value, 10);
    const totalPages = Math.max(1, Math.ceil(filteredIndexList.length / perPage));
    if (currentPage < totalPages) {
        currentPage++;
        renderPage();
    }
});