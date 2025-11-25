<?php
// po_accounts.php
// Simple management page for the PO to view all clients and create new accounts.

// Load DB config
$cfg = require __DIR__ . '/api/config.php';
try{
    $dsn = "mysql:host={$cfg['host']};dbname={$cfg['dbname']};charset={$cfg['charset']}";
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}catch(Exception $e){
    http_response_code(500);
    echo "<h1>Erreur de connexion à la base de données</h1><pre>".htmlspecialchars($e->getMessage())."</pre>";
    exit;
}

$errors = [];
$success = null;

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Basic server-side validation
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
            // clear POST values for the form
            $code_tiers = $nom = $numero_tva_intra = $code_commercial = '';
        }catch(Exception $e){
            $errors[] = 'Erreur lors de la création du compte: ' . $e->getMessage();
        }
    }
}

// Fetch all clients
try{
    $stmt = $pdo->query('SELECT id_client, code_tiers, nom, numero_tva_intra, code_commercial FROM Clients ORDER BY nom ASC');
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
}catch(Exception $e){
    $clients = [];
    $errors[] = 'Impossible de récupérer les comptes: ' . $e->getMessage();
}

function h($s){ return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Gestion Comptes - PO</title>
    <link rel="stylesheet" href="css/dashboard2.css">
    <style>
        .container{max-width:960px;margin:20px auto;padding:12px}
        form .row{display:flex;gap:8px;align-items:center;margin-bottom:8px}
        form input{padding:6px}
        table{width:100%;border-collapse:collapse;margin-top:12px}
        table th, table td{border:1px solid #ddd;padding:8px;text-align:left}
        .errors{color:#b00020}
        .success{color:green}
    </style>
</head>
<body>
    <div class="container">
        <h1>Gestion des comptes (PO)</h1>
    <p><a href="dashboard_po.php">← Retour au dashboard</a></p>

        <?php if(!empty($errors)): ?>
            <div class="errors"><strong>Erreur(s):</strong>
                <ul><?php foreach($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <?php if($success): ?><div class="success"><?= h($success) ?></div><?php endif; ?>

        <h2>Créer un nouveau compte</h2>
        <form method="post">
            <div class="row">
                <label style="min-width:120px">Code Tiers (ID):</label>
                <input name="code_tiers" value="<?= h($code_tiers ?? '') ?>" required>
                <label style="min-width:80px">Nom:</label>
                <input name="nom" value="<?= h($nom ?? '') ?>" required>
            </div>
            <div class="row">
                <label style="min-width:120px">Numéro TVA intracom:</label>
                <input name="numero_tva_intra" value="<?= h($numero_tva_intra ?? '') ?>">
                <label style="min-width:80px">Code commercial:</label>
                <input name="code_commercial" value="<?= h($code_commercial ?? '') ?>">
            </div>
            <div class="row">
                <button class="btn" type="submit">Créer</button>
            </div>
        </form>

            <!-- Merchant-specific features removed: merchants are handled as clients using the existing Clients table.
                 No CREATE TABLE or ALTER TABLE operations are performed. -->

        <h2>Liste des comptes (<?= count($clients) ?>)</h2>
        <table>
            <thead><tr><th>ID</th><th>Code Tiers</th><th>Nom</th><th>TVA</th><th>Code Commercial</th></tr></thead>
            <tbody>
                <?php foreach($clients as $c): ?>
                    <tr>
                        <td><?= h($c['id_client']) ?></td>
                        <td><?= h($c['code_tiers']) ?></td>
                        <td><?= h($c['nom']) ?></td>
                        <td><?= h($c['numero_tva_intra']) ?></td>
                        <td><?= h($c['code_commercial']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
