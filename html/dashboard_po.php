<?php
// dashboard_po.php — server-rendered dashboard page for PO
// Renders the same UI as dashboard_po.html but populates the transactions table from the DB.

$cfg = require __DIR__ . '/api/config.php';
try{
    $dsn = "mysql:host={$cfg['host']};dbname={$cfg['dbname']};charset={$cfg['charset']}";
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}catch(Exception $e){
    // If DB connection fails we'll render the page with sample static rows below
    $pdo = null;
}

$errors = [];
$success = null;

// Handle form submissions (scoped by submit button names)
if($pdo && $_SERVER['REQUEST_METHOD'] === 'POST'){
    // Create client
    if(isset($_POST['create_client'])){
        $code_tiers = trim($_POST['code_tiers'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $numero_tva_intra = trim($_POST['numero_tva_intra'] ?? '');
        $code_commercial = trim($_POST['code_commercial'] ?? '');
        if($code_tiers === '') $errors[] = 'Le champ Code Tiers est requis.';
        if($nom === '') $errors[] = 'Le champ Nom est requis.';
        if(empty($errors)){
            try{
                $stmt = $pdo->prepare('INSERT INTO Clients (code_tiers, nom, numero_tva_intra, code_commercial) VALUES (:code_tiers, :nom, :numero_tva, :code_commercial)');
                $stmt->execute([':code_tiers'=>$code_tiers, ':nom'=>$nom, ':numero_tva'=>$numero_tva_intra, ':code_commercial'=>$code_commercial]);
                $success = 'Compte créé avec succès.';
            }catch(Exception $e){ $errors[] = 'Erreur lors de la création du compte: ' . $e->getMessage(); }
        }
    }

    if(isset($_POST['create_po_client'])){
        try{
            // check if a PO client already exists (code_tiers = 'PO')
            $check = $pdo->prepare('SELECT id_client FROM Clients WHERE code_tiers = :code LIMIT 1');
            $check->execute([':code'=>'PO']);
            $found = $check->fetch(PDO::FETCH_ASSOC);
            if($found){
                $success = 'Un compte PO existe déjà (id: '.intval($found['id_client']).').';
            } else {
                $stmt = $pdo->prepare('INSERT INTO Clients (code_tiers, nom, numero_tva_intra, code_commercial) VALUES (:code_tiers, :nom, :numero_tva, :code_commercial)');
                $stmt->execute([':code_tiers'=>'PO', ':nom'=>'PO Account', ':numero_tva'=>'', ':code_commercial'=>'PO']);
                $success = 'Compte PO créé avec succès.';
            }
        }catch(Exception $e){ $errors[] = 'Impossible de créer le compte PO: '.$e->getMessage(); }
    }

}

// Fetch recent transactions (Factures joined to Devis->Clients)
$rows = [];
        if($pdo){
    try{
        $sql = "SELECT c.id_client AS id_client, c.nom AS intitule, f.date_document AS date_document, c.code_tiers AS siret, f.total_brut_ht AS montant
                FROM Factures f
                LEFT JOIN Devis d ON f.id_devis = d.id_devis
                LEFT JOIN Clients c ON d.id_client = c.id_client
                ORDER BY f.date_document DESC
                LIMIT 200";
        $stmt = $pdo->query($sql);
        while($r = $stmt->fetch(PDO::FETCH_ASSOC)){
            $rows[] = $r;
        }
    }catch(Exception $e){
        // ignore and fall back to sample rows
        $rows = [];
    }
}

// Fetch clients for account management
$clients = [];
if($pdo){
    try{
        $stmt = $pdo->query('SELECT id_client, code_tiers, nom, numero_tva_intra, code_commercial FROM Clients ORDER BY nom ASC');
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }catch(Exception $e){ /* ignore */ }
}

// No merchants table usage — merchants are handled as clients in this deployment.
// $merchants intentionally not used.

function h($s){ return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
?><!DOCTYPE html>
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
            <a href="#">Déconnecter</a>
            <a href="#">à propos</a>
            <a href="#po-accounts">Comptes</a>
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
                <h1 id="solde-global" class="solde">+15 500$</h1>
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
                        <th data-type="number">Impayés : <span class="arrow">↓</span></th>
                        <th>Accéder compte :</th>
                        <th>Voir plus :</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($rows) > 0): ?>
                        <?php foreach($rows as $r):
                            $dateText = $r['date_document'] ? date('d/m/y', strtotime($r['date_document'])) : '';
                            $intitule = $r['intitule'] ?? '—';
                            $siret = $r['siret'] ?? '';
                            $montant = is_null($r['montant']) ? 0 : (float)$r['montant'];
                            $montText = ($montant < 0 ? '-' : '+') . number_format(abs($montant), 0, ',', ' ') . ' $';
                        ?>
                        <?php $clientIdAttr = isset($r['id_client']) ? ' data-client-id="'.h($r['id_client']).'"' : ''; ?>
                        <tr class="data-row"<?= $clientIdAttr ?> data-impayes='[]' data-remises='[]'>
                            <td><?= h($dateText) ?></td>
                            <td><?= h($intitule) ?></td>
                            <td><?= h($siret) ?></td>
                            <td class="<?= $montant < 0 ? 'negatif' : 'positif' ?>"><?= h($montText) ?></td>
                            <td><button class="btn-acceder btn">⚙️ Accéder</button></td>
                            <td><button class="btn-voir btn">👁️ Voir Plus</button></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Fallback sample rows if DB is empty or unavailable -->
                        <tr class="data-row" data-impayes='[{"date":"01/09/24","date_limite":"01/10/24","libelle":"Facture A","montant":1000},{"date":"05/09/24","date_limite":"05/11/24","libelle":"Facture B","montant":500}]' data-remises='[{"date":"02/09/24","date_limite":"-","libelle":"Remise 1","montant":200},{"date":"08/09/24","date_limite":"-","libelle":"Remise 2","montant":300}]'>
                            <td>01/09/24</td>
                            <td>Yehven Kefa</td>
                            <td>784 671 695 00103</td>
                            <td class="negatif">-1500 $</td>
                            <td><button class="btn-acceder btn">⚙️ Accéder</button></td>
                            <td><button class="btn-voir btn">👁️ Voir Plus</button></td>
                        </tr>
                        <tr class="data-row" data-impayes='[{"date":"10/09/24","date_limite":"10/10/24","libelle":"Facture C","montant":1500}]' data-remises='[{"date":"11/09/24","date_limite":"-","libelle":"Remise 3","montant":0}]'>
                            <td>10/09/24</td>
                            <td>Thomas Noël</td>
                            <td>784 671 678 04403</td>
                            <td class="negatif">-1500 $</td>
                            <td><button class="btn-acceder btn">⚙️ Accéder</button></td>
                            <td><button class="btn-voir btn">👁️ Voir Plus</button></td>
                        </tr>
                        <tr class="data-row" data-impayes='[{"date":"15/09/24","date_limite":"15/10/24","libelle":"Facture D","montant":2000}]' data-remises='[{"date":"16/09/24","date_limite":"-","libelle":"Remise 4","montant":0}]'>
                            <td>15/09/24</td>
                            <td>Yanis Boukayouh</td>
                            <td>784 671 695 00103</td>
                            <td class="negatif">-2000 $</td>
                            <td><button class="btn-acceder btn">⚙️ Accéder</button></td>
                            <td><button class="btn-voir btn">👁️ Voir Plus</button></td>
                        </tr>
                        <tr class="data-row" data-impayes='[]' data-remises='[{"date":"20/09/24","date_limite":"-","libelle":"Remise 5","montant":0}]'>
                            <td>20/09/24</td>
                            <td>Rayan Essaidi</td>
                            <td>784 671 695 08423</td>
                            <td class="positif">0</td>
                            <td><button class="btn-acceder btn">⚙️ Accéder</button></td>
                            <td><button class="btn-voir btn">👁️ Voir Plus</button></td>
                        </tr>
                        <tr class="data-row" data-impayes='[{"date":"01/08/24","date_limite":"01/09/24","libelle":"Facture E","montant":2000}]' data-remises='[]'>
                            <td>01/08/24</td>
                            <td>Hamza Revel</td>
                            <td>784 671 695 00103</td>
                            <td class="negatif">-2000 $</td>
                            <td><button class="btn-acceder btn">⚙️ Accéder</button></td>
                            <td><button class="btn-voir btn">👁️ Voir Plus</button></td>
                        </tr>
                        <tr class="data-row" data-impayes='[]' data-remises='[]'>
                            <td>25/09/24</td>
                            <td>John Pork</td>
                            <td>784 671 695 00104</td>
                            <td class="positif">0</td>
                            <td><button class="btn-acceder btn">⚙️ Accéder</button></td>
                            <td><button class="btn-voir btn">👁️ Voir Plus</button></td>
                        </tr>
                    <?php endif; ?>
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

        <!-- PO Accounts management embedded -->
        <section id="po-accounts" style="margin-top:24px;padding:12px;border-top:1px solid #eee">
            <h2>Gestion des comptes (PO)</h2>
            <?php if(!empty($errors)): ?>
                <div class="errors" style="color:#b00020"><strong>Erreur(s):</strong>
                    <ul><?php foreach($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>
            <?php if(!empty($success)): ?><div class="success" style="color:green"><?= h($success) ?></div><?php endif; ?>

            <form method="post" style="margin-bottom:12px">
                <button class="btn" name="create_po_client" type="submit">Créer mon compte PO (client)</button>
            </form>

            <h3>Créer un nouveau compte</h3>
            <form method="post">
                <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
                    <label style="min-width:120px">Code Tiers (ID):</label>
                    <input name="code_tiers" value="<?= h($_POST['code_tiers'] ?? '') ?>" required>
                    <label style="min-width:80px">Nom:</label>
                    <input name="nom" value="<?= h($_POST['nom'] ?? '') ?>" required>
                </div>
                <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
                    <label style="min-width:120px">Numéro TVA intracom:</label>
                    <input name="numero_tva_intra" value="<?= h($_POST['numero_tva_intra'] ?? '') ?>">
                    <label style="min-width:80px">Code commercial:</label>
                    <input name="code_commercial" value="<?= h($_POST['code_commercial'] ?? '') ?>">
                </div>
                <div style="margin-bottom:12px"><button class="btn" name="create_client" type="submit">Créer</button></div>
            </form>

            <!-- Merchant-specific UI and schema operations removed.
                 Merchants are represented as Clients in the existing database schema (see PO accounts list below).
                 No DDL (CREATE/ALTER) or Merchants table usage is performed. -->

            <h3>Liste des comptes (<?= count($clients) ?>)</h3>
            <table style="width:100%;border-collapse:collapse">
                <thead><tr><th style="border:1px solid #ddd;padding:8px">ID</th><th style="border:1px solid #ddd;padding:8px">Code Tiers</th><th style="border:1px solid #ddd;padding:8px">Nom</th><th style="border:1px solid #ddd;padding:8px">TVA</th><th style="border:1px solid #ddd;padding:8px">Code Commercial</th></tr></thead>
                <tbody>
                    <?php foreach($clients as $c): ?>
                        <tr>
                            <td style="border:1px solid #ddd;padding:8px"><?= h($c['id_client']) ?></td>
                            <td style="border:1px solid #ddd;padding:8px"><?= h($c['code_tiers']) ?></td>
                            <td style="border:1px solid #ddd;padding:8px"><?= h($c['nom']) ?></td>
                            <td style="border:1px solid #ddd;padding:8px"><?= h($c['numero_tva_intra']) ?></td>
                            <td style="border:1px solid #ddd;padding:8px"><?= h($c['code_commercial']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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

    <script src="js/fetch-data.js"></script>
    <script src="js/dashboard2.js"></script>
    <script src="js/client-access.js"></script>


</body>
</html>
