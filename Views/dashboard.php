<?php
session_start();

if (!isset($_SESSION["Profil"]) || $_SESSION["Profil"] != "client") {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../Controllers/remise_controller.php';
$remiseController = new RemiseController();
$remisesData = $remiseController->getRemisesStructure();
$soldeTotal = $remiseController->getSoldeGlobalClient();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Tableau de bord - Trésorerie</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
<link rel="stylesheet" href="css/dashboard1.css">
<style>
.detail-row { display: none; }
.row-toggle { cursor: pointer; }
.positif { color: green; }
.negatif { color: red; }
.inner-transactions { width: 100%; border-collapse: collapse; }
.inner-transactions td { padding: 4px; border: 1px solid #ccc; }
</style>
</head>
<body>
<header>
<div class="logo"><img src="logo.png" alt="logo"></div>
<nav>
<a href="/QualiteDeDevBUT2/Controllers/user_controller.php?action=logout">Déconnecter</a>
<a href="#">à propos</a>
<div class="menu">☰</div>
</nav>
</header>

<main>
<section class="infos">
<div class="client-card">
<p><strong>Client connecté :</strong> <?php echo htmlspecialchars($_SESSION['Nom']); ?></p>
<p class="small">Portail de gestion de paiement - démonstration</p>
</div>

<div class="solde-card">
    <p>Solde Global :</p>
    <h1 class="<?php echo ($soldeTotal < 0) ? 'negatif' : 'positif'; ?>">
        <?php 
        $soldeTotal = (float)$soldeTotal;
        echo ($soldeTotal >= 0 ? '+' : '-') . number_format(abs($soldeTotal), 2) . '$'; 
        ?>
    </h1>
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
<label class="small">Lignes :</label>
<select id="perPage" class="btn">
<option>5</option>
<option selected>10</option>
<option>25</option>
<option>50</option>
</select>
</div>
</div>

<table id="table-transactions" aria-describedby="result-count">
<thead>
<tr>
<th>Numéro Remise</th>
<th>Date de vente</th>
<th>Montant total</th>
<th></th>
</tr>
</thead>
<tbody>
<?php foreach($remisesData as $remise): 
    $transactions = $remise['transactions'];
    $totalRemise = 0;

    // Calcul du total de la remise (somme de toutes les transactions)
    foreach($transactions as $tr) {
        $sens = $tr['transaction']['Sens'];
        $montant = $tr['transaction']['Montant'] ?? 0;
        $totalRemise += ($sens === '+') ? $montant : -$montant;
    }

    // Classe pour couleur du total de la remise
    $classTotal = ($totalRemise < 0) ? 'negatif' : 'positif';
?>
<tr class="remise-row">
    <td><?php echo htmlspecialchars($remise['remise']['Num_remise'] ?? ''); ?></td>
    <td><?php echo date("d/m/Y", strtotime($remise['remise']['Date_vente'])); ?></td>
    <td class="<?php echo $classTotal; ?>">
        <?php echo number_format($totalRemise, 2); ?>$
    </td>
    <td class="row-toggle">➕</td>
</tr>

<?php foreach($transactions as $tr): 
    $sens = $tr['transaction']['Sens'];
    $montant = $tr['transaction']['Montant'] ?? 0;
    $impaye = $tr['impaye'];

    // Si impayé => rouge, sinon vert
    $classMontant = $impaye ? 'negatif' : 'positif';
    $montantAffiche = ($sens == '+') ? '+' . number_format($montant,2) : '-' . number_format($montant,2);
?>
<tr class="detail-row">
    <td colspan="4" class="inner-container">
        <table class="inner-transactions">
            <thead>
                <tr>
                    <th>Libellé</th>
                    <th>Num Carte</th>
                    <th>Montant</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($transactions as $tr): 
                    $sens = $tr['transaction']['Sens'];
                    $montant = $tr['transaction']['Montant'] ?? 0;
                    $impaye = $tr['impaye'];
                    $classMontant = $impaye ? 'negatif' : 'positif';
                    $montantAffiche = ($sens == '+') ? '+' . number_format($montant,2) : '-' . number_format($montant,2);
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($tr['transaction']['Libelle']); ?></td>
                    <td><?php echo htmlspecialchars($tr['transaction']['Num_Carte']); ?></td>
                    <td class="<?php echo $classMontant; ?>"><?php echo $montantAffiche; ?>$</td>
                    <td><?php echo $impaye ? "IMPAYÉ - Dossier: ".$impaye['Num_dossier'] : "Transaction normale"; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </td>
</tr>

<?php endforeach; ?>
<?php endforeach; ?>
</tbody>
</table>
</section>
</main>

<script>
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll(".row-toggle").forEach(function(btn){
        btn.addEventListener("click", function(){
            let tr = this.closest("tr");
            let next = tr.nextElementSibling;
            while(next && next.classList.contains("detail-row")) {
                next.style.display = (next.style.display === "table-row") ? "none" : "table-row";
                next = next.nextElementSibling;
            }
            this.textContent = (this.textContent === "➕") ? "➖" : "➕";
        });
    });
});
</script>
<script src="js/dashboard1.js"></script>
</body>
</html>
