<?php
class RemiseModel {
    private $conn;

    public function __construct() {
        require __DIR__ . '/../config.php'; 
        $this->conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
        if (!$this->conn) {
            die("Erreur de connexion : " . mysqli_connect_error());
        }
    }

    public function getRemiseByClientId($id_client) {
        $request = mysqli_prepare($this->conn, 
            "SELECT * 
            FROM Remise 
            WHERE Id_Client = ?
            ORDER BY Date_vente DESC"
        );
        mysqli_stmt_bind_param($request, "i", $id_client);
        mysqli_stmt_execute($request);
        $res = mysqli_stmt_get_result($request);
        return $res->fetch_all(MYSQLI_ASSOC);  
    }

    public function getImpayeByRemiseId($id_remise) {
        $request = mysqli_prepare($this->conn,
            "SELECT i.*, t.*
            FROM Impaye i
            JOIN Transactions t
                ON i.Id_Transactions = t.Id_Transactions
            WHERE t.Id_Remise = ?
            ORDER BY t.Date_Transaction DESC"
        );
        mysqli_stmt_bind_param($request, "i", $id_remise);
        mysqli_stmt_execute($request);
        $res = mysqli_stmt_get_result($request);
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    public function getNormalTransactionByRemiseId($id_remise) {
        $request = mysqli_prepare($this->conn,
            "SELECT t.*
            FROM Transactions t
            LEFT JOIN Impaye i
                ON t.Id_Transactions = i.Id_Transactions
            WHERE i.Id_Impaye IS NULL
            AND t.Id_Remise = ?
            ORDER BY t.Date_Transaction DESC"
        );
        mysqli_stmt_bind_param($request, "i", $id_remise);
        mysqli_stmt_execute($request);
        $res = mysqli_stmt_get_result($request);
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    public function getSoldeGlobal($id_client) {
        $request = mysqli_prepare($this->conn, 
            "SELECT 
            SUM(CASE 
                WHEN t.Sens = '+' THEN t.Montant
                WHEN t.Sens = '-' THEN -t.Montant
                ELSE 0
            END) AS solde_global
            FROM Transactions t
            JOIN Remise r ON t.Id_Remise = r.Id_Remise
            WHERE r.Id_Client = ?"
        );
        mysqli_stmt_bind_param($request, "i", $id_client);
        mysqli_stmt_execute($request);
        $res = mysqli_stmt_get_result($request);
        $row = $res->fetch_assoc();
        return $row['solde_global'] ?? 0;
    }

    public function getAllTransactionsWithStatus() {
        $request = "
            SELECT
            t.Id_Transactions,
            t.Date_Transaction,
            t.Montant,
            u.Nom AS Nom_Utilisateur,
            c.Siren AS Siret_Client,
            CASE 
                WHEN i.id_Impaye IS NOT NULL THEN 1
                ELSE 0
            END AS estImpaye
        FROM Transactions t
        LEFT JOIN Remise r ON t.Id_Remise = r.Id_Remise
        LEFT JOIN Client c ON r.Id_Client = c.Id_Client
        LEFT JOIN Utilisateur u ON c.Id_Utilisateur = u.Id_Utilisateur
        LEFT JOIN Impaye i ON i.Id_Transactions = t.Id_Transactions
        ORDER BY t.Date_Transaction DESC;
        ";
        $res = mysqli_query($this->conn, $request);
        return $res->fetch_all(MYSQLI_ASSOC);
    }


} 
?>