// client-access.js
// Binds "Accéder" buttons to fetch client details and populate the sidebar.
(function(){
    function fmtAmount(n){
        if(n === null || n === undefined) return '0';
        const num = Number(n)||0; return (num < 0 ? '-' : '+') + Math.abs(num).toLocaleString() + ' $';
    }

    function createRow(cells){
        const tr = document.createElement('tr');
        tr.innerHTML = cells.map(c => `<td>${c}</td>`).join('');
        return tr;
    }

    function openSidebar(){
        const backdrop = document.getElementById('sidebarBackdrop');
        backdrop.style.display = 'flex';
    }

    function closeSidebarIfAny(){
        const backdrop = document.getElementById('sidebarBackdrop');
        backdrop.style.display = 'none';
    }

    async function fetchClient(id){
        const url = `api/client.php?id=${encodeURIComponent(id)}`;
        const res = await fetch(url, {credentials:'same-origin'});
        if(!res.ok) throw new Error('Network');
        return res.json();
    }

    function populateSidebarWithData(data){
        const sidebarTitle = document.getElementById('sidebarTitle');
        const impayesBody = document.querySelector('#impayesTable tbody');
        const remisesBody = document.querySelector('#remisesTable tbody');
        const sidebarTotals = document.getElementById('sidebarTotals');
        impayesBody.innerHTML = '';
        remisesBody.innerHTML = '';

        const client = data.client || {};
        sidebarTitle.textContent = `${client.nom || 'Client'} — ${client.code_tiers || ''}`;

        let sumImp = 0;
        (data.invoices || []).forEach(inv =>{
            // try to display date and amount
            const date = inv.date_document ? inv.date_document : (inv.date ? inv.date : '-');
            const lib = inv.numero_facture || inv.reference || (`Facture ${inv.id_facture || ''}`);
            const montant = Number(inv.total_brut_ht ?? inv.montant ?? 0) || 0;
            const tr = createRow([date, inv.date_echeance ?? '-', lib, fmtAmount(montant)]);
            impayesBody.appendChild(tr);
            sumImp += montant;
        });

        // remises not available in schema sample; keep empty
        let sumRem = 0;

        sidebarTotals.textContent = `Impayés: ${sumImp.toLocaleString()} $ | Rémises: ${sumRem.toLocaleString()} $`;
    }

    function attach(){
        document.querySelectorAll('.btn-acceder').forEach(btn =>{
            btn.addEventListener('click', async (e)=>{
                e.preventDefault(); e.stopPropagation();
                const tr = btn.closest('tr.data-row');
                if(!tr) return;
                const clientId = tr.getAttribute('data-client-id') || tr.getAttribute('data-id') || tr.dataset.clientId;
                if(!clientId){
                    // no id available — show fallback sidebar
                    return;
                }
                try{
                    const data = await fetchClient(clientId);
                    if(data && !data.error){
                        populateSidebarWithData(data);
                        openSidebar();
                    }else{
                        console.warn('No client data', data);
                    }
                }catch(err){ console.error(err); }
            });
        });
    }

    // wait until DOM loaded
    if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', attach); else attach();
})();
