<?php
// api/client.php
// Returns JSON with client details and related invoices/addresses for a given id_client
header('Content-Type: application/json; charset=utf-8');
$cfg = require __DIR__ . '/config.php';
try{
    $dsn = "mysql:host={$cfg['host']};dbname={$cfg['dbname']};charset={$cfg['charset']}";
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}catch(Exception $e){ http_response_code(500); echo json_encode(['error'=>'DB connection failed']); exit; }

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if($id <= 0){ echo json_encode(['error'=>'missing id']); exit; }

try{
    // client
    $stmt = $pdo->prepare('SELECT id_client, code_tiers, nom, numero_tva_intra, code_commercial FROM Clients WHERE id_client = :id LIMIT 1');
    $stmt->execute([':id'=>$id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    // invoices (Factures) related via Devis -> Clients
    $invoices = [];
    try{
        $sql = 'SELECT f.* FROM Factures f LEFT JOIN Devis d ON f.id_devis = d.id_devis WHERE d.id_client = :id ORDER BY f.date_document DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id'=>$id]);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }catch(Exception $e){ $invoices = []; }

    // addresses (if table exists)
    $addresses = [];
    try{
        $res = $pdo->query("SHOW TABLES LIKE 'Adresse'")->fetch();
        if($res){
            $stmt = $pdo->prepare('SELECT * FROM Adresse WHERE id_client = :id');
            $stmt->execute([':id'=>$id]);
            $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }catch(Exception $e){ $addresses = []; }

    echo json_encode(['client'=>$client, 'invoices'=>$invoices, 'addresses'=>$addresses], JSON_UNESCAPED_UNICODE);
}catch(Exception $e){ http_response_code(500); echo json_encode(['error'=>'query error']); }
