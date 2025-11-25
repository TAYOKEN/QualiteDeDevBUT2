<?php
class ProfilModel {
    private $conn;

    public function __construct() {
        require __DIR__ . '/../config.php'; 
        $this->conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
        if (!$this->conn) {
            die("Erreur de connexion : " . mysqli_connect_error());
        }
    }

    public function getClientByUserId($id_user) {
        $request = mysqli_prepare($this->conn, 
            "SELECT * 
            FROM Client
            WHERE Id_Utilisateur = ?"
        );
        mysqli_stmt_bind_param($request, "i", $id_user);
        mysqli_stmt_execute($request);
        $res = mysqli_stmt_get_result($request);
        return $res->fetch_assoc();
    }
} 
?>