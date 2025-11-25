    // --- Filtrage de la table ---
    const searchInput = document.getElementById('searchInput');
    const rows = document.querySelectorAll('#userTable tbody tr');

    searchInput.addEventListener('input', () => {
        const filter = searchInput.value.toLowerCase();
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });

    // --- Simulation de demande de suppression ---
    const notif = document.getElementById('notif');
    const buttons = document.querySelectorAll('.btn.danger');

    buttons.forEach(btn => {
        btn.addEventListener('click', () => {
            btn.textContent = "Demande envoyée";
            btn.disabled = true;

            notif.classList.add('show');
            setTimeout(() => notif.classList.remove('show'), 2000);
        });
    });