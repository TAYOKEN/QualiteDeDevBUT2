    // ---------- UTIL / state ----------
    const table = document.getElementById('table-transactions');
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

    // parse un montant (ex: "+1 000$" ou "-1000$")
    function parseAmount(text){
        // supprime espaces et $ et +, mais conserver signe
        if(!text) return 0;
        const num = parseFloat(text.replace(/\s/g,'').replace('€','').replace('$','').replace('+',''));
        return text.includes('-') ? -Math.abs(num) : Math.abs(num);
    }

    // parse date format dd/mm/yy into Date object (année 2000+yy)
    function parseDateDMY(text){
        const parts = text.split('/');
        if(parts.length !== 3) return new Date(text);
        const d = parseInt(parts[0],10), m = parseInt(parts[1],10), yRaw = parseInt(parts[2],10);
        const y = yRaw < 100 ? 2000 + yRaw : yRaw;
        return new Date(y, m-1, d);
    }

    // initialiser rowsData depuis le DOM (assume chaque data-row suivi de detail-row)
    function initRows(){
        rowsData = [];
        const dataRows = Array.from(tbody.querySelectorAll('tr.data-row'));
        dataRows.forEach((r, idx) => {
            const cells = r.children;
            const date = cells[0].textContent.trim();
            const intitule = cells[1].textContent.trim();
            const siret = cells[2].textContent.trim();
            const montantText = cells[3].textContent.trim();
            const montant = parseAmount(montantText);
            const detailRow = r.nextElementSibling && r.nextElementSibling.classList.contains('detail-row') ? r.nextElementSibling : null;
            const detail = detailRow ? detailRow.children[0].textContent.trim() : '';
            rowsData.push({rowEl:r, detailEl: detailRow, dateText:date, dateObj: parseDateDMY(date), intitule, siret, montant, montantText, detail});
        });
    }

    // colorisation par tranche (Epic 3 US4)
    function applyColorBands(){
        rowsData.forEach(item => {
            item.rowEl.classList.remove('color-band-1','color-band-2','color-band-3');
            const absM = Math.abs(item.montant);
            if(absM < 1000) item.rowEl.classList.add('color-band-1');
            else if(absM < 3000) item.rowEl.classList.add('color-band-2');
            else item.rowEl.classList.add('color-band-3');
        });
    }

    // mise à jour des compteurs, totaux affichés
    function updateCountersAndTotals(visibleIndices){
        // total visible
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
        } else {
            totalNegDisplay.textContent = '';
        }

        // total remises = nb de lignes (peut être changé pour compter remises réelles)
        totalRemises.textContent = ` | ${filteredIndexList.length} remises trouvées`;
        resultCountEl.textContent = `${filteredIndexList.length} résultat(s)`;
    }

    // rend visible une page (pagination)
    function renderPage(){
        const perPage = parseInt(perPageSelect.value,10);
        const total = filteredIndexList.length;
        const totalPages = Math.max(1, Math.ceil(total / perPage));
        if(currentPage > totalPages) currentPage = totalPages;
        const start = (currentPage - 1) * perPage;
        const slice = filteredIndexList.slice(start, start + perPage);

        // hide all rows first
        rowsData.forEach(item => {
            item.rowEl.style.display = 'table-row';
            if(item.detailEl) item.detailEl.style.display = 'none';
            item.rowEl.classList.add('hidden-temp');
        });

        // show only slice, hide others
        rowsData.forEach((item,i) => {
            if(!slice.includes(i)){
                item.rowEl.style.display = 'none';
                if(item.detailEl) item.detailEl.style.display = 'none';
            } else {
                item.rowEl.style.display = 'table-row';
            }
        });

        // update pagination UI
        const paginationEl = document.getElementById('pagination');
        paginationEl.innerHTML = '';
        for(let p = 1; p <= totalPages; p++){
            const btn = document.createElement('button');
            btn.textContent = p;
            btn.className = 'btn';
            if(p === currentPage) btn.classList.add('primary');
            btn.addEventListener('click', ()=>{ currentPage = p; renderPage(); });
            paginationEl.appendChild(btn);
        }

        updateCountersAndTotals(slice);
    }

    // filtrer selon la recherche (sur date, intitulé, siret, montant, detail)
    function applyFilterAndRefresh(){
        const q = searchInput.value.trim().toLowerCase();
        filteredIndexList = [];
        rowsData.forEach((item, idx) => {
            const hay = (item.dateText + ' ' + item.intitule + ' ' + item.siret + ' ' + item.montantText + ' ' + item.detail).toLowerCase();
            if(hay.includes(q)) filteredIndexList.push(idx);
        });
        currentPage = 1;
        renderPage();
    }

    // tri des lignes (index col, type, ascending)
    function sortRows(indexCol, type, ascending){
        // indexCol corresponds to headers order: date=0,intitule=1,siret=2,montant=3
        const compare = (a,b) => {
            if(type === 'number') return ascending ? a.montant - b.montant : b.montant - a.montant;
            if(type === 'date') return ascending ? a.dateObj - b.dateObj : b.dateObj - a.dateObj;
            // text
            const aa = (indexCol===1? a.intitule : a.siret).toLowerCase();
            const bb = (indexCol===1? b.intitule : b.siret).toLowerCase();
            return ascending ? aa.localeCompare(bb) : bb.localeCompare(aa);
        };

        // sort rowsData but preserve original mapping; we'll sort filteredIndexList based on values to preserve all
        filteredIndexList.sort((i,j) => compare(rowsData[i], rowsData[j]));
        currentPage = 1;
        renderPage();
    }

    // toggle detail row
    function attachRowToggles(){
        rowsData.forEach(item => {
            const toggleCell = item.rowEl.querySelector('.row-toggle');
            if(toggleCell){
                toggleCell.style.cursor='pointer';
                toggleCell.addEventListener('click', ()=>{
                    if(!item.detailEl) return;
                    const isHidden = item.detailEl.style.display === 'none' || item.detailEl.style.display === '';
                    item.detailEl.style.display = isHidden ? 'table-row' : 'none';
                    toggleCell.textContent = isHidden ? '➖' : '➕';
                });
                // also allow clicking row to toggle
                item.rowEl.addEventListener('click', (e)=>{
                    if(e.target === toggleCell) return; // already handled
                    if(!item.detailEl) return;
                    const isHidden = item.detailEl.style.display === 'none' || item.detailEl.style.display === '';
                    item.detailEl.style.display = isHidden ? 'table-row' : 'none';
                    toggleCell.textContent = isHidden ? '➖' : '➕';
                });
            }
        });
    }

    // exports
    function exportCSV(){
        const headerCells = ['Date','Intitulé','N° Siret','Montant','Détails'];
        const rows = [];
        // exporter seulement les lignes visibles (filteredIndexList for current page)
        const perPage = parseInt(perPageSelect.value,10);
        const start = (currentPage-1)*perPage;
        const slice = filteredIndexList.slice(start, start + perPage);
        slice.forEach(i => {
            const item = rowsData[i];
            rows.push([item.dateText, item.intitule, item.siret, item.montantText, item.detail]);
        });
        let csv = headerCells.join(';') + '\n';
        rows.forEach(r => {
            csv += r.map(c => `"${String(c).replace(/"/g,'""')}"`).join(';') + '\n';
        });
        const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'remises_export.csv';
        document.body.appendChild(a); a.click(); a.remove();
        URL.revokeObjectURL(url);
    }

    function exportXLS(){
        // construit worksheet à partir des données visibles
        const perPage = parseInt(perPageSelect.value,10);
        const start = (currentPage-1)*perPage;
        const slice = filteredIndexList.slice(start, start + perPage);
        const wsData = [['Date','Intitulé','N° Siret','Montant','Détails']];
        slice.forEach(i => {
            const item = rowsData[i];
            wsData.push([item.dateText, item.intitule, item.siret, item.montant, item.detail]);
        });
        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.aoa_to_sheet(wsData);
        XLSX.utils.book_append_sheet(wb, ws, 'Remises');
        XLSX.writeFile(wb, 'remises_export.xlsx');
    }

    async function exportPDF(){
        // utilise jsPDF autotable
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        const perPage = parseInt(perPageSelect.value,10);
        const start = (currentPage-1)*perPage;
        const slice = filteredIndexList.slice(start, start + perPage);
        const body = slice.map(i => {
            const item = rowsData[i];
            return [item.dateText, item.intitule, item.siret, item.montantText, item.detail];
        });
        doc.setFontSize(12);
        doc.text(`EXTRAIT DU ${new Date().toLocaleDateString('fr-FR')}`, 14, 16);
        doc.autoTable({
            head: [['Date','Intitulé','N° Siret','Montant','Détails']],
            body,
            startY: 22,
            styles: { fontSize: 9 }
        });
        doc.save('remises_export.pdf');
    }

    // ---------- graphique Chart.js ----------
    const ctx = document.getElementById('graphique').getContext('2d');
    // datas de démo (tu pourras les lier à tes données serveur)
    const chartData = {
        labels: ['26/08/25', '05/09/25', '15/09/25', '05/10/25'],
        datasets: [{
            label: 'Profit',
            data: [1000, 5000, 5500, 15500],
            borderColor: 'green',
            backgroundColor: 'transparent',
            tension: 0.2
        }]
    };
    let currentChartType = 'line';
    const chartInstance = new Chart(ctx, {
        type: currentChartType,
        data: chartData,
        options: {
            plugins: { legend: { display: false } },
            scales: {
                x: { title: { text: 'Date', display: true } },
                y: { title: { text: 'Montant ($)', display: true } }
            }
        }
    });

    document.getElementById('chartTypeSelect').addEventListener('change', (e)=>{
        const t = e.target.value;
        // recréer le chart en changeant le type
        chartInstance.config.type = t;
        chartInstance.update();
    });

    // ---------- initialisation et events ----------
    function wireTableHeaders(){
        headers.forEach((header, idx) => {
            header.addEventListener('click', ()=>{
                const type = header.dataset.type;
                const index = idx; // 0=date,1=text,2=text,3=number
                const arrow = header.querySelector('.arrow');
                const ascending = arrow.textContent === '↓'; // toggling behavior
                // toggle arrows (reset others)
                headers.forEach(h => { const a = h.querySelector('.arrow'); if(a) a.textContent = '↓'; });
                arrow.textContent = ascending ? '↑' : '↓';
                sortRows(index, type, ascending);
            });
        });
    }

    document.getElementById('perPage').addEventListener('change', ()=>{
        currentPage = 1;
        renderPage();
    });
    document.getElementById('prevPage').addEventListener('click', ()=>{
        if(currentPage>1){ currentPage--; renderPage(); }
    });
    document.getElementById('nextPage').addEventListener('click', ()=>{
        const perPage = parseInt(perPageSelect.value,10);
        const totalPages = Math.max(1, Math.ceil(filteredIndexList.length / perPage));
        if(currentPage < totalPages){ currentPage++; renderPage(); }
    });

    searchInput.addEventListener('input', ()=>{ applyFilterAndRefresh(); });

    document.getElementById('export-csv').addEventListener('click', exportCSV);
    document.getElementById('export-xls').addEventListener('click', exportXLS);
    document.getElementById('export-pdf').addEventListener('click', exportPDF);

    // wrapper pour initialiser l'UI
    function boot(){
        initRows();
        applyColorBands();
        attachRowToggles();
        wireTableHeaders();

        // initial filtered list = tout
        filteredIndexList = rowsData.map((_, idx) => idx);
        currentPage = 1;
        renderPage();

        // calcule solde global (démonstration : sommation de tous)
        const solde = rowsData.reduce((acc, r) => acc + r.montant, 0);
        const soldeEl = document.getElementById('solde-global');
        soldeEl.textContent = (solde >= 0 ? '+' : '') + solde.toLocaleString() + '$';
        if(solde < 0) soldeEl.classList.add('negatif');

        // accessibility/UX small improvement: show initial counts
        totalRemises.textContent = ` | ${rowsData.length} remises totales`;
    }

    // run boot
    boot();