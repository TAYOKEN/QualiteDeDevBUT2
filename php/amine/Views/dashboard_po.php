<?php
session_start();
// Vérifie si l'utilisateur n'est pas connecté ou n'est pas product_owner
if (!isset($_SESSION["Profil"]) || $_SESSION["Profil"] != "product_owner") {
    header("Location: ../../login.php");
    exit;
}
require_once __DIR__ . '/../Models/remise_models.php';
$model = new RemiseModel();
$transactions= $model->getAllTransactionsWithStatus();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Tableau de bord - Trésorerie (avec recherche avancée & sidebar)</title>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- jsPDF pour export PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

    <!-- SheetJS pour export XLS (simple) -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
        <link rel="stylesheet" href="css/dashboard2.css">

</head>
<body>
    <header>
        <div class="logo">
            <img src="logo.png" alt="logo">
        </div>
        <nav>
            <a href="/QualiteDeDevBUT2/Controllers/user_controller.php?action=logout">Déconnecter</a>
            <a href="#">à propos</a>
            <div class="menu">☰</div>
            <a href="register.php">Ajouter un utilisateur</a>
        </nav>
    </header>

    <main>
        <section class="infos">
            <div class="client-card">
                <p><strong><?php echo htmlspecialchars($_SESSION["Nom"]); ?></strong><br>PO</p>
                <p class="small">Portail de gestion de paiement - démonstration</p>
            </div>

            <div class="solde-card">
                <p>Solde Global :</p>
                <h1 id="solde-global" class="solde"></h1>
                <p class="small" id="total-neg-display"></p>
            </div>

            <div class="chart-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <div><strong>Évolution de la trésorerie</strong></div>
                    <div style="display:flex;gap:8px;align-items:center">
                        <label class="small">Type :</label>
                        <select id="chartTypeSelect" class="btn">
                            <option value="line">Courbe</option>
                            <option value="bar">Histogramme</option>
                        </select>
                    </div>
                </div>
                <canvas id="graphique" style="max-height:220px"></canvas>
                <div style="margin-top:10px"><strong>Répartition (Camembert)</strong>
                    <canvas id="pieChart" style="max-height:160px"></canvas>
                </div>
            </div>
        </section>

        <section class="transactions">
            <div class="controls">
                <div class="topbar-actions">
                    <button class="btn" id="export-csv">Export CSV</button>
                    <button class="btn" id="export-xls">Export XLS</button>
                    <button class="btn" id="export-pdf">Export PDF</button>
                </div>

                <div style="margin-left:auto; display:flex; gap:8px; align-items:center">
                    <label for="search" class="small">Recherche :</label>
                    <input id="search" placeholder="Rechercher (SIREN, intitulé, date, remise...)" />

                    <!-- Recherche approfondie -->
                    <label class="small">Recherche approfondie :</label>
                    <input id="dateStart" type="date" class="btn" title="Date début" />
                    <input id="dateEnd" type="date" class="btn" title="Date fin" />
                    <button class="btn" id="applyDateRange">Appliquer</button>

                    <label class="small">Lignes :</label>
                    <select id="perPage" class="btn">
                        <option>5</option>
                        <option selected>10</option>
                        <option>25</option>
                        <option>50</option>
                    </select>
                </div>
            </div>

            <div style="display:flex;align-items:center;margin-bottom:8px">
                <div><strong id="result-count">10 résultats</strong></div>
                <div class="result-count" id="total-remises"></div>
            </div>

            <table id="table-clients" aria-describedby="result-count">
                <thead>
                    <tr>
                        <th data-type="date">Date : <span class="arrow">↓</span></th>
                        <th data-type="text">Nom : <span class="arrow">↓</span></th>
                        <th data-type="text">N° Siret : <span class="arrow">↓</span></th>
                        <th data-type="number">Montant : <span class="arrow">↓</span></th>
                        <th>Accéder compte :</th>
                        <th>Voir plus :</th>
                    </tr>
                </thead>
                 <tbody>
                    <?php
                    foreach ($transactions as $t):
                        $classe = $t['estImpaye'] ? 'negatif' : 'positif';
                    ?>
                    <tr class="data-row" 
                        data-impayes='<?= json_encode($t['estImpaye'] ? [$t] : []) ?>' 
                        data-remises='<?= json_encode([]) ?>'>
                        <td><?= date('d/m/Y', strtotime($t['Date_Transaction'])) ?></td>
                        <td><?= htmlspecialchars($t['Nom_Utilisateur']) ?></td>
                        <td><?= htmlspecialchars($t['Siret_Client']) ?></td>
                        <td class="<?= $classe ?>"><?= $t['estImpaye'] ? number_format($t['Montant'] ?? 0, 2) . ' $' : number_format(0, 2) . ' $' ?></td>
                        <td><button class="btn-acceder btn">⚙️ Accéder</button></td>
                        <td><button class="btn-voir btn">👁️ Voir Plus</button></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" class="totals">Total (visible) : <span id="visible-total">0 $</span></td>
                    </tr>
                </tfoot>
            </table>

            <div class="pager">
                <div class="pagination" id="pagination"></div>
                <div>
                    <button class="btn" id="prevPage">Préc</button>
                    <button class="btn" id="nextPage">Suiv</button>
                </div>
            </div>
        </section>
    </main>

    <!-- SIDEBAR pour "Voir Plus" -->
    <div class="sidebar-backdrop" id="sidebarBackdrop">
        <aside class="sidebar" id="sidebar">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <h3 id="sidebarTitle">Détails</h3>
                <div class="close" id="sidebarClose">✖</div>
            </div>

            <div style="margin-top:8px;display:flex;gap:8px;align-items:center;">
                <button class="btn" id="export-sidebar-csv">Export CSV</button>
                <button class="btn" id="export-sidebar-xls">Export XLS</button>
                <button class="btn" id="export-sidebar-pdf">Export PDF</button>
                <div style="margin-left:auto"><span class="small" id="sidebarTotals"></span></div>
            </div>

            <h4>Impayés</h4>
            <table id="impayesTable"><thead><tr><th>Date</th><th>Date limite</th><th>Libellé</th><th>Montant</th></tr></thead><tbody></tbody></table>

            <h4 style="margin-top:12px">Rémises</h4>
            <table id="remisesTable"><thead><tr><th>Date</th><th>Date limite</th><th>Libellé</th><th>Montant</th></tr></thead><tbody></tbody></table>
        </aside>
    </div>

    <script src="js/dashboard2.js"></script>


</body>
</html>
