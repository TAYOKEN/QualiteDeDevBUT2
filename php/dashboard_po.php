<?php
require_once 'connection.php';


$soldeGlobal  = 0;
$totalImpaye  = 0.0;
$transactions = [];

try {
    $stmtSolde = $pdo->query("SELECT SUM(Solde) AS total_solde FROM Compte");
    $soldeGlobal = (float) $stmtSolde->fetchColumn();
// Requete + calcul de somme pour avoir le total impayé
    $stmtImpaye = $pdo->query("
        SELECT SUM(t.Montant_Total) AS total_impaye
        FROM Transactions t
        INNER JOIN Impaye i ON i.Id_Transactions = t.Id_Transactions
    ");
    $totalImpaye = (float) $stmtImpaye->fetchColumn();
// Requete de recup
    $stmt = $pdo->query("
        SELECT 
            t.Date_Transaction,
            u.Nom AS nom_client,
            c.Siren,
            CASE 
                WHEN i.Id_Impaye IS NULL THEN 0 
                ELSE -t.Montant_Total 
            END AS montant_impaye
        FROM Transactions t
        JOIN Client c       ON t.Id_Client = c.Id_Client
        JOIN Utilisateur u  ON c.Id_Utilisateur = u.Id_Utilisateur
        LEFT JOIN Impaye i  ON i.Id_Transactions = t.Id_Transactions
        ORDER BY t.Date_Transaction DESC
    ");
    $transactions = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Erreur SQL : " . $e->getMessage());
}


function formatMontant($m) {
    if ($m == 0 || $m === null) {
        return "0 $";
    }
    $sign = $m < 0 ? '-' : '+';
    $val  = number_format(abs($m), 0, ',', ' ');
    return $sign . $val . " $";
}

function formatDateFr($dateSql) {
    if (!$dateSql) return '';
    try {
        $d = new DateTime($dateSql);
        return $d->format('d/m/y');
    } catch (Exception $e) {
        return $dateSql;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Tableau de bord - PO</title>

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
            <a href="#">Déconnecter</a>
            <a href="#">à propos</a>

            <!-- peut etre supprimer celui la -->
            <div class="menu">☰</div>
        </nav>
    </header>

    <main>
        <section class="infos">
            <div class="client-card">
                <p><strong>Mr. Boukayouh Yanis</strong><br>PO</p>
                <p class="small">Portail de gestion de paiement - démonstration</p>
            </div>

            <div class="solde-card">
                <p>Solde Global :</p>
                <h1 id="solde-global" class="solde">
                    <?php echo htmlspecialchars(formatMontant($soldeGlobal)); ?>
                </h1>
                <p class="small" id="total-neg-display">
                    Total impayés : <?php echo htmlspecialchars(formatMontant(-$totalImpaye)); ?>
                </p>
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
                <div><strong id="result-count"><?php echo count($transactions); ?> résultats</strong></div>
                <div class="result-count" id="total-remises"></div>
            </div>

            <table id="table-clients" aria-describedby="result-count">
                <thead>
                    <tr>
                        <th data-type="date">Date : <span class="arrow">↓</span></th>
                        <th data-type="text">Nom : <span class="arrow">↓</span></th>
                        <th data-type="text">N° Siret : <span class="arrow">↓</span></th>
                        <th data-type="number">Impayés : <span class="arrow">↓</span></th>
                        <th>Accéder compte :</th>
                        <th>Voir plus :</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $row): 
                        $date   = formatDateFr($row['Date_Transaction']);
                        $nom    = $row['nom_client'];
                        $siren  = $row['Siren'];
                        $montantImpaye = (float) $row['montant_impaye'];
                        $classMontant  = $montantImpaye < 0 ? 'negatif' : 'positif';

                        $dataImpayes = '[]';
                        $dataRemises = '[]';
                    ?>
                    <tr class="data-row"
                        data-impayes='<?php echo $dataImpayes; ?>'
                        data-remises='<?php echo $dataRemises; ?>'>
                        <td><?php echo htmlspecialchars($date); ?></td>
                        <td><?php echo htmlspecialchars($nom); ?></td>
                        <td><?php echo htmlspecialchars($siren); ?></td>
                        <td class="<?php echo $classMontant; ?>">
                            <?php echo htmlspecialchars(formatMontant($montantImpaye)); ?>
                        </td>
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
                            <!-- Qui s'occupe d'avoir les requete pour voir tout les impayés ???? -->
            <div class="pager">
                <div class="pagination" id="pagination"></div>
                <div>
                    <button class="btn" id="prevPage">Préc</button>
                    <button class="btn" id="nextPage">Suiv</button>
                    <button id="btn-global-remises" class="btn primary">Toutes les remises</button>
                </div>
            </div>
        </section>
    </main>

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
            <table id="impayesTable">
                <thead>
                    <tr><th>Date</th><th>Date limite</th><th>Libellé</th><th>Montant</th></tr>
                </thead>
                <tbody></tbody>
            </table>

            <h4 style="margin-top:12px">Rémises</h4>
            <table id="remisesTable">
                <thead>
                    <tr><th>Date</th><th>Date limite</th><th>Libellé</th><th>Montant</th></tr>
                </thead>
                <tbody></tbody>
            </table>
        </aside>
    </div>

    <div id="globalRemisesModal" class="modal-backdrop">
        <div class="modal-window">
            <h2>Toutes les remises</h2>
            <button id="close-global-remises">Close</button>

            <table id="globalRemisesTable">
                <thead>
                    <tr>
                        <th>Entreprise</th>
                        <th>SIRET</th>
                        <th>Date</th>
                        <th>Libellé</th>
                        <th>Montant</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

            <div class="export-global-remises">
                <button id="export-global-remises-csv" class="btn">CSV</button>
                <button id="export-global-remises-xls" class="btn">XLS</button>
                <button id="export-global-remises-pdf" class="btn">PDF</button>
            </div>
        </div>
    </div>

    <script>
 <?php echo json_encode($transactions); ?>;
    </script>
    <script src="js/dashboard2.js"></script>

</body>
</html>
