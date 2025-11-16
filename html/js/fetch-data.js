// fetch-data.js
// Attempt to load transaction rows from the backend API and populate the table body
// If the API is unreachable, the existing static rows remain and the dashboard will boot normally.

(async function(){
    const tbody = document.querySelector('#table-clients tbody');
    if(!tbody) return;

    try{
        const resp = await fetch('./api/transactions.php');
        if(!resp.ok) throw new Error('API non disponible');
        const data = await resp.json();
        if(!Array.isArray(data)) throw new Error('Format inattendu de la réponse API');

        // Clear existing static rows (we assume API is authoritative)
        tbody.innerHTML = '';

        data.forEach(row => {
            const tr = document.createElement('tr');
            tr.className = 'data-row';
            // attach JSON attributes for existing sidebar logic
            tr.setAttribute('data-impayes', JSON.stringify(row.impayes || []));
            tr.setAttribute('data-remises', JSON.stringify(row.remises || []));

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

        // small helper to avoid XSS from server-rendered strings
        function escapeHtml(s){ return String(s || '').replace(/[&<>"']/g, c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;" })[c]); }

        console.info('Transactions chargées depuis API.');
    }catch(err){
        console.warn('Chargement via API échoué, utilisation des lignes statiques. ', err.message);
        // leave the existing static rows in place
    }
})();
