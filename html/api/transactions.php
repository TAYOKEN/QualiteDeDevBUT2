<?php
// transactions.php
// Minimal JSON API that returns an array of transaction rows compatible with the front-end.
// Each row: { date, intitule, siret, montant, impayes:[], remises:[] }

header('Content-Type: application/json; charset=utf-8');

try{
    $cfg = require __DIR__ . '/config.php';
    $dsn = "mysql:host={$cfg['host']};dbname={$cfg['dbname']};charset={$cfg['charset']}";
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Try to fetch recent invoices joined to clients. This is a pragmatic, forgiving query
    // that works with the provided SQL schema if the database has data.
    $sql = "SELECT c.nom AS intitule, f.date_document AS date_document, c.code_tiers AS siret, f.total_brut_ht AS montant
            FROM Factures f
            LEFT JOIN Devis d ON f.id_devis = d.id_devis
            LEFT JOIN Clients c ON d.id_client = c.id_client
            ORDER BY f.date_document DESC
            LIMIT 200";

    $stmt = $pdo->query($sql);
    $rows = [];
    while($r = $stmt->fetch(PDO::FETCH_ASSOC)){
        // Format date as DD/MM/YY to match front-end expectations
        $date = $r['date_document'] ? date('d/m/y', strtotime($r['date_document'])) : '';
        $montant = is_null($r['montant']) ? 0 : (float)$r['montant'];

        $rows[] = [
            'date' => $date,
            'intitule' => $r['intitule'] ?? '—',
            'siret' => $r['siret'] ?? '',
            'montant' => $montant,
            'id_client' => isset($r['id_client']) ? (int)$r['id_client'] : null,
            // For compatibility we return empty arrays for impayes/remises. You can later
            // implement dedicated queries to fill these from your tables.
            'impayes' => [],
            'remises' => []
        ];
    }

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;

}catch(Exception $e){
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

?>
