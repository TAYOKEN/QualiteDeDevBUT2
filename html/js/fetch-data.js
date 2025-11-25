// fetch-data.js
// Attempt to load transaction rows from the backend API and populate the table body
// If the API is unreachable, the existing static rows remain and the dashboard will boot normally.

(async function(){
    const tbody = document.querySelector('#table-clients tbody');
    if(!tbody) return;

    // If server-side already rendered rows exist, don't overwrite them.
    if(tbody.querySelectorAll('tr.data-row').length > 0){
        console.info('Rows already present in DOM — skipping API fetch.');
        return;
    }

    try{
        const resp = await fetch('./api/transactions.php');
        if(!resp.ok) throw new Error('API non disponible');
        const data = await resp.json();
        if(!Array.isArray(data)) throw new Error('Format inattendu de la réponse API');

        // Clear (should be empty) and populate rows from API
        tbody.innerHTML = '';

        data.forEach(row => {
            const tr = document.createElement('tr');
            tr.className = 'data-row';
            tr.setAttribute('data-impayes', JSON.stringify(row.impayes || []));
            tr.setAttribute('data-remises', JSON.stringify(row.remises || []));
            // attach client id when provided by API so sidebar/detail actions can fetch full client data
            if(row.id_client){ tr.setAttribute('data-client-id', String(row.id_client)); }

            const montantText = (row.montant >= 0 ? '+' : '') + Number(row.montant).toLocaleString() + ' $';

            tr.innerHTML = `
                <td>${row.date}</td>
                <td>${escapeHtml(row.intitule)}</td>
                <td>${escapeHtml(row.siret || '')}</td>
                <td class="${row.montant < 0 ? 'negatif' : 'positif'}">${montantText}</td>
                <td><button class="btn-acceder btn">⚙️ Accéder</button></td>
                <td><button class="btn-voir btn">👁️ Voir Plus</button></td>
            `;
            tbody.appendChild(tr);
        });

        function escapeHtml(s){ return String(s || '').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"})[c]); }

        console.info('Transactions chargées depuis API.');
    }catch(err){
        console.warn('Chargement via API échoué, utilisation des lignes statiques. ', err.message);
    }
})();
