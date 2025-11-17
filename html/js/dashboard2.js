    const table = document.getElementById('table-clients');
    const tbody = table.querySelector('tbody');
    const headers = table.querySelectorAll('th[data-type]');
    const perPageSelect = document.getElementById('perPage');
    const searchInput = document.getElementById('search');
    const resultCountEl = document.getElementById('result-count');
    const visibleTotalEl = document.getElementById('visible-total');
    const totalNegDisplay = document.getElementById('total-neg-display');
    const totalRemises = document.getElementById('total-remises');

    let rowsData = []; // tableau d'objets dérivés du DOM
    let filteredIndexList = []; // indices des rowsData affichés après filtre
    let currentPage = 1;

    function parseAmount(text){
        if(!text) return 0;
        const cleaned = text.replace(/\s/g,'').replace('€','').replace('$','');
        const num = parseFloat(cleaned.replace('+','').replace('-','')) || 0;
        return text.includes('-') ? -Math.abs(num) : Math.abs(num);
    }

    function parseDateDMY(text){
        if(!text) return new Date('1970-01-01');
        const parts = text.split('/');
        if(parts.length !== 3) return new Date(text);
        const d = parseInt(parts[0],10), m = parseInt(parts[1],10), yRaw = parseInt(parts[2],10);
        const y = yRaw < 100 ? 2000 + yRaw : yRaw;
        return new Date(y, m-1, d);
    }

    function initRows(){
        rowsData = [];
        const dataRows = Array.from(tbody.querySelectorAll('tr.data-row'));
        dataRows.forEach((r, idx) => {
            r.dataset.rowId = idx;  // <<< add in DOM index line
            const cells = r.children;
            const date = cells[0].textContent.trim();
            const intitule = cells[1].textContent.trim();
            const siret = cells[2].textContent.trim();
            const montantText = cells[3].textContent.trim();
            const montant = parseAmount(montantText);
            // read JSON data attributes for impayes/remises if present
            let impayes = [];
            let remises = [];
            try{ impayes = JSON.parse(r.getAttribute('data-impayes') || '[]'); }catch(e){ impayes = []; }
            try{ remises = JSON.parse(r.getAttribute('data-remises') || '[]'); }catch(e){ remises = []; }
            rowsData.push({rowEl:r, dateText:date, dateObj: parseDateDMY(date), intitule, siret, montant, montantText, impayes, remises});
        });
    }

    function applyColorBands(){
        rowsData.forEach(item => {
            item.rowEl.classList.remove('color-band-1','color-band-2','color-band-3');
            const absM = Math.abs(item.montant);
            const bucket = Math.floor(absM / 100);
            if(bucket <= 1) item.rowEl.classList.add('color-band-1');
            else if(bucket <= 9) item.rowEl.classList.add('color-band-2');
            else item.rowEl.classList.add('color-band-3');
        });
    }

    function updateCountersAndTotals(visibleIndices){
        let total = 0;
        let totalNeg = 0;
        visibleIndices.forEach(i => {
            total += rowsData[i].montant;
            if(rowsData[i].montant < 0) totalNeg += rowsData[i].montant;
        });
        visibleTotalEl.textContent = (total >= 0 ? '+' : '') + total.toLocaleString() + '$';
        if(total < 0) visibleTotalEl.classList.add('negatif'); else visibleTotalEl.classList.remove('negatif');

        if(totalNeg < 0){
            totalNegDisplay.textContent = `Total impayés (visible) : ${totalNeg.toLocaleString()} $`;
            totalNegDisplay.style.color = 'var(--danger)';
        } else { totalNegDisplay.textContent = ''; }

        totalRemises.textContent = ` | ${filteredIndexList.length} remises trouvées`;
        resultCountEl.textContent = `${filteredIndexList.length} résultat(s)`;

        // update pie chart data too
        updatePieChart();
    }

    function renderPage(){
        const perPage = parseInt(perPageSelect.value,10);
        const total = filteredIndexList.length;
        const totalPages = Math.max(1, Math.ceil(total / perPage));
        if(currentPage > totalPages) currentPage = totalPages;
        const start = (currentPage - 1) * perPage;
        const slice = filteredIndexList.slice(start, start + perPage);

        rowsData.forEach(item => item.rowEl.style.display = 'none');
        rowsData.forEach((item,i) => { if(slice.includes(i)) item.rowEl.style.display = 'table-row'; });

        const paginationEl = document.getElementById('pagination');
        paginationEl.innerHTML = '';
        for(let p = 1; p <= totalPages; p++){
            const btn = document.createElement('button'); btn.textContent = p; btn.className = 'btn';
            if(p === currentPage) btn.classList.add('primary');
            btn.addEventListener('click', ()=>{ currentPage = p; renderPage(); });
            paginationEl.appendChild(btn);
        }

        updateCountersAndTotals(slice);
    }

    function applyFilterAndRefresh(){
        const q = searchInput.value.trim().toLowerCase();
        const startDateInput = document.getElementById('dateStart').value;
        const endDateInput = document.getElementById('dateEnd').value;
        const startDate = startDateInput ? new Date(startDateInput) : null;
        const endDate = endDateInput ? new Date(endDateInput) : null;

        filteredIndexList = [];
        rowsData.forEach((item, idx) => {
            const hay = (item.dateText + ' ' + item.intitule + ' ' + item.siret + ' ' + item.montantText).toLowerCase();
            if(!hay.includes(q)) return; // quick text filter
            // date-range filter if provided
            if(startDate || endDate){
                const d = item.dateObj;
                if(startDate && d < startDate) return;
                if(endDate && d > (new Date(endDate.getFullYear(), endDate.getMonth(), endDate.getDate(), 23,59,59))) return;
            }
            filteredIndexList.push(idx);
        });
        currentPage = 1;
        renderPage();
        updateChartDatasets();
    }

    function sortRows(indexCol, type, ascending){
        const compare = (a,b) => {
            if(type === 'number') return ascending ? a.montant - b.montant : b.montant - a.montant;
            if(type === 'date') return ascending ? a.dateObj - b.dateObj : b.dateObj - a.dateObj;
            const aa = (indexCol===1? a.intitule : a.siret).toLowerCase();
            const bb = (indexCol===1? b.intitule : b.siret).toLowerCase();
            return ascending ? aa.localeCompare(bb) : bb.localeCompare(aa);
        };
        filteredIndexList.sort((i,j) => compare(rowsData[i], rowsData[j]));
        currentPage = 1; renderPage();
    }

    // ---------- Exports améliorés (possibilité d'exporter le tableau principal ou celui de la sidebar) ----------
    function exportCSV(forSidebar=false, sidebarType='impayes'){
        const headerCells = ['Date','Intitulé','N° Siret','Montant','Détails'];
        let rows = [];
        if(forSidebar){
            // export impayes or remises from currently opened sidebar (uses the DOM tables)
            const tbl = document.getElementById(sidebarType === 'impayes' ? 'impayesTable' : 'remisesTable');
            Array.from(tbl.querySelectorAll('tbody tr')).forEach(tr =>{
                const cells = Array.from(tr.children).map(c => c.textContent || '');
                rows.push(cells);
            });
        } else {
            const perPage = parseInt(perPageSelect.value,10);
            const start = (currentPage-1)*perPage;
            const slice = filteredIndexList.slice(start, start + perPage);
            slice.forEach(i => {
                const item = rowsData[i];
                rows.push([item.dateText, item.intitule, item.siret, item.montantText, '']);
            });
        }

        let csv = headerCells.join(';') + '\n';
        rows.forEach(r => { csv += r.map(c => `"${String(c).replace(/"/g,'""')}"`).join(';') + '\n'; });
        const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a'); a.href = url; a.download = forSidebar ? `${sidebarType.toUpperCase()}_EXPORT.csv` : 'REMISSES_EXPORT.csv'; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
    }

    function exportXLS(forSidebar=false, sidebarType='impayes'){
        const wsData = [['DATE','INTITULÉ','N° SIRET','MONTANT','DÉTAILS']];
        if(forSidebar){
            const tbl = document.getElementById(sidebarType === 'impayes' ? 'impayesTable' : 'remisesTable');
            Array.from(tbl.querySelectorAll('tbody tr')).forEach(tr =>{
                const cells = Array.from(tr.children).map(c => c.textContent || '');
                wsData.push(cells);
            });
        } else {
            const perPage = parseInt(perPageSelect.value,10);
            const start = (currentPage-1)*perPage;
            const slice = filteredIndexList.slice(start, start + perPage);
            slice.forEach(i => {
                const item = rowsData[i];
                wsData.push([item.dateText, item.intitule, item.siret, item.montant, '']);
            });
        }
        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.aoa_to_sheet(wsData);
        XLSX.utils.book_append_sheet(wb, ws, 'REMISSES');
        XLSX.writeFile(wb, forSidebar ? `${sidebarType.toUpperCase()}_EXPORT.xlsx` : 'REMISSES_EXPORT.xlsx');
    }

    async function exportPDF(forSidebar=false, sidebarType='impayes'){
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        const dateExtraction = new Date().toLocaleDateString('fr-FR');
        const title = `EXTRAIT - ${dateExtraction}`;
        doc.setFontSize(12); doc.text(title, 14, 12);

        // ajouter graphiques : main chart + camembert
        try{
            // primary chart
            const imgData = chartInstance.toBase64Image();
            doc.addImage(imgData, 'PNG', 14, 16, 180, 50);
            // pie chart
            const pieData = pieChartInstance.toBase64Image();
            doc.addImage(pieData, 'PNG', 14, 72, 80, 60);
            var startY = 140;
        }catch(e){ var startY = 30; }

        if(forSidebar){
            const tbl = document.getElementById(sidebarType === 'impayes' ? 'impayesTable' : 'remisesTable');
            const body = Array.from(tbl.querySelectorAll('tbody tr')).map(tr => Array.from(tr.children).map(td => td.textContent));
            doc.autoTable({ head: [['Date','Date limite','Libellé','Montant']], body, startY, styles:{fontSize:9} });
        } else {
            const perPage = parseInt(perPageSelect.value,10);
            const start = (currentPage-1)*perPage;
            const slice = filteredIndexList.slice(start, start + perPage);
            const body = slice.map(i => { const item = rowsData[i]; return [item.dateText, item.intitule, item.siret, item.montantText]; });
            doc.autoTable({ head: [['DATE','INTITULÉ','N° SIRET','MONTANT']], body, startY, styles:{fontSize:9} });
        }
        doc.save(forSidebar ? `${sidebarType.toUpperCase()}_EXPORT.pdf` : 'REMISSES_EXPORT.pdf');
    }

    // ---------- Chart.js setup ----------
    const ctx = document.getElementById('graphique').getContext('2d');
    const pieCtx = document.getElementById('pieChart').getContext('2d');

    function getSortedUniqueDates(){
        const dates = Array.from(new Set(rowsData.map(r => r.dateText)));
        dates.sort((a,b) => parseDateDMY(a) - parseDateDMY(b));
        return dates;
    }

    function computeChartDatas(){
        const labels = getSortedUniqueDates();
        const caMap = {}; const impMap = {};
        labels.forEach(l => { caMap[l]=0; impMap[l]=0; });
        rowsData.forEach(r => { if(!(r.dateText in caMap)){ caMap[r.dateText] = 0; impMap[r.dateText] = 0; }
            if(r.montant >= 0) caMap[r.dateText] += r.montant; else impMap[r.dateText] += Math.abs(r.montant);
        });
        const caArr = labels.map(l => caMap[l]);
        const impArr = labels.map(l => impMap[l]);
        return { labels, caArr, impArr };
    }

    let chartDataObj = computeChartDatas();
    const chartData = {
        labels: chartDataObj.labels.length ? chartDataObj.labels : ['--'],
        datasets: [
            { label: 'Chiffre d\'affaires', data: chartDataObj.caArr.length ? chartDataObj.caArr : [0], borderColor: 'green', backgroundColor:'transparent', tension:0.2, type:'line', yAxisID:'y' },
            { label: 'Impayés', data: chartDataObj.impArr.length ? chartDataObj.impArr : [0], borderColor: 'red', backgroundColor:'rgba(255,0,0,0.1)', type:'bar', yAxisID:'y' }
        ]
    };

    let currentChartType = 'line';
    const chartInstance = new Chart(ctx, { type: currentChartType, data: chartData, options: { plugins:{ legend:{ display:true }}, scales:{ x:{ title:{ text:'Date', display:true }}, y:{ title:{ text:'Montant ($)', display:true }, beginAtZero:true } } } });

    // pie chart
    let pieChartInstance = new Chart(pieCtx, { type: 'pie', data: { labels: ['CA','Impayés'], datasets:[{ data: [1,1], backgroundColor: ['#4caf50','#f44336'] }] }, options:{ plugins:{ legend:{ position:'bottom' } } } });

    document.getElementById('chartTypeSelect').addEventListener('change', (e)=>{ const t = e.target.value; chartInstance.config.data.datasets[0].type = t; chartInstance.update(); });

    function updateChartDatasets(){
        const labelsSet = new Set();
        filteredIndexList.forEach(i => labelsSet.add(rowsData[i].dateText));
        let labels = Array.from(labelsSet);
        labels.sort((a,b) => parseDateDMY(a) - parseDateDMY(b));
        if(labels.length === 0){ const all = computeChartDatas(); chartInstance.data.labels = all.labels; chartInstance.data.datasets[0].data = all.caArr; chartInstance.data.datasets[1].data = all.impArr; }
        else {
            const caMap = {}; const impMap = {};
            labels.forEach(l => { caMap[l]=0; impMap[l]=0; });
            filteredIndexList.forEach(i => { const r = rowsData[i]; if(r.montant >= 0) caMap[r.dateText] += r.montant; else impMap[r.dateText] += Math.abs(r.montant); });
            chartInstance.data.labels = labels;
            chartInstance.data.datasets[0].data = labels.map(l => caMap[l]);
            chartInstance.data.datasets[1].data = labels.map(l => impMap[l]);
        }
        chartInstance.update(); updatePieChart();
    }

    function updatePieChart(){
        let totalCA = 0, totalImp = 0;
        filteredIndexList.forEach(i => { const r = rowsData[i]; if(r.montant >= 0) totalCA += r.montant; else totalImp += Math.abs(r.montant); });
        // fallback to all if none filtered
        if(filteredIndexList.length === 0){ const all = computeChartDatas(); totalCA = all.caArr.reduce((a,b)=>a+b,0); totalImp = all.impArr.reduce((a,b)=>a+b,0); }
        pieChartInstance.data.datasets[0].data = [totalCA, totalImp];
        pieChartInstance.update();
    }

    // ---------- SIDEBAR logic ----------
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const sidebar = document.getElementById('sidebar');
    const sidebarTitle = document.getElementById('sidebarTitle');
    const sidebarClose = document.getElementById('sidebarClose');
    const impayesTableBody = document.querySelector('#impayesTable tbody');
    const remisesTableBody = document.querySelector('#remisesTable tbody');
    const sidebarTotals = document.getElementById('sidebarTotals');

    function openSidebarForRow(rowIndex){
        const item = rowsData[rowIndex];
        sidebarTitle.textContent = `${item.intitule} — ${item.siret}`;
        // fill tables
        impayesTableBody.innerHTML = '';
        remisesTableBody.innerHTML = '';
        let sumImp = 0, sumRem = 0;
        item.impayes.forEach(i =>{
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${i.date}</td><td>${i.date_limite}</td><td>${i.libelle}</td><td>${i.montant} $</td>`;
            impayesTableBody.appendChild(tr); sumImp += Number(i.montant || 0);
        });
        item.remises.forEach(r =>{
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${r.date}</td><td>${r.date_limite || '-'} </td><td>${r.libelle}</td><td>${r.montant} $</td>`;
            remisesTableBody.appendChild(tr); sumRem += Number(r.montant || 0);
        });
        sidebarTotals.textContent = `Impayés: ${sumImp} $ | Rémises: ${sumRem} $`;
        sidebarBackdrop.style.display = 'flex';
        // store current opened user type selection for exports
        sidebarBackdrop.currentRowIndex = rowIndex;
    }

    function closeSidebar(){ sidebarBackdrop.style.display = 'none'; }

    sidebarClose.addEventListener('click', closeSidebar);
    sidebarBackdrop.addEventListener('click', (e)=>{ if(e.target === sidebarBackdrop) closeSidebar(); });

    // export buttons inside sidebar
    document.getElementById('export-sidebar-csv').addEventListener('click', ()=>{ exportCSV(true, 'impayes'); });
    document.getElementById('export-sidebar-xls').addEventListener('click', ()=>{ exportXLS(true, 'impayes'); });
    document.getElementById('export-sidebar-pdf').addEventListener('click', ()=>{ exportPDF(true, 'impayes'); });

    // Voir Plus buttons binding
    function attachVoirPlus(){
        const voirBtns = Array.from(document.querySelectorAll('.btn-voir'));
        voirBtns.forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const tr = btn.closest('tr');
                const realIndex = parseInt(tr.dataset.rowId, 10);
                openSidebarForRow(realIndex);
            });
        });
    }

    // ---------- header binding and boot ----------
    function wireTableHeaders(){
        headers.forEach((header, idx) => {
            header.addEventListener('click', ()=>{
                const type = header.dataset.type;
                const index = idx; // 0=date,1=text,2=text,3=number
                const arrow = header.querySelector('.arrow');
                const ascending = arrow.textContent === '↓';
                headers.forEach(h => { const a = h.querySelector('.arrow'); if(a) a.textContent = '↓'; });
                arrow.textContent = ascending ? '↑' : '↓';
                sortRows(index, type, ascending);
            });
        });
    }

    //---------remise for PO ----------------//
    function getAllRemises(){
        const result = [];
        rowsData.forEach(item => {
            item.remises.forEach(r => {
                result.push({
                    entreprise: item.intitule,
                    siret: item.siret,
                    date: r.date,
                    libelle: r.libelle,
                    montant: r.montant
                });
            });
        });
        return result;
    }
    function openGlobalRemises(){
        const modal = document.getElementById('globalRemisesModal');
        const tbody = document.querySelector('#globalRemisesTable tbody');
        tbody.innerHTML = '';

        const all = getAllRemises();
        all.forEach(r=>{
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${r.entreprise}</td>
                <td>${r.siret}</td>
                <td>${r.date}</td>
                <td>${r.libelle}</td>
                <td>${r.montant} $</td>`;
            tbody.appendChild(tr);
        });

        modal.style.display = 'flex';
    }

    document.getElementById('perPage').addEventListener('change', ()=>{ currentPage = 1; renderPage(); });
    document.getElementById('prevPage').addEventListener('click', ()=>{ if(currentPage>1){ currentPage--; renderPage(); } });
    document.getElementById('nextPage').addEventListener('click', ()=>{ const perPage = parseInt(perPageSelect.value,10); const totalPages = Math.max(1, Math.ceil(filteredIndexList.length / perPage)); if(currentPage < totalPages){ currentPage++; renderPage(); } });

    searchInput.addEventListener('input', ()=>{ applyFilterAndRefresh(); });
    document.getElementById('applyDateRange').addEventListener('click', ()=>{ applyFilterAndRefresh(); });

    document.getElementById('export-csv').addEventListener('click', ()=>{ exportCSV(false); });
    document.getElementById('export-xls').addEventListener('click', ()=>{ exportXLS(false); });
    document.getElementById('export-pdf').addEventListener('click', ()=>{ exportPDF(false); });
    //--------modal window with remise for PO-------------//
    document.getElementById('close-global-remises')
        .addEventListener('click', () => {
            document.getElementById('globalRemisesModal').style.display = 'none';
        });
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('btn-global-remises').addEventListener('click', openGlobalRemises);
        const globalModal = document.getElementById('globalRemisesModal');
        globalModal.addEventListener('click', (e) => {
            if (e.target === globalModal) globalModal.style.display = 'none';
        });
    });


    function boot(){
        initRows(); applyColorBands(); wireTableHeaders(); attachVoirPlus();
        filteredIndexList = rowsData.map((_, idx) => idx);
        currentPage = 1; renderPage();
        const solde = rowsData.reduce((acc, r) => acc + r.montant, 0);
        const soldeEl = document.getElementById('solde-global'); soldeEl.textContent = (solde >= 0 ? '+' : '') + solde.toLocaleString() + '$'; if(solde < 0) soldeEl.classList.add('negatif');
        totalRemises.textContent = ` | ${rowsData.length} remises totales`;
        const initial = computeChartDatas(); chartInstance.data.labels = initial.labels; chartInstance.data.datasets[0].data = initial.caArr; chartInstance.data.datasets[1].data = initial.impArr; chartInstance.update(); updatePieChart();
    }




    boot();