<?php
class UtilisateurModel {
    private $conn;

    public function __construct() {
        require __DIR__ . '/../config.php'; 
        $this->conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
        if (!$this->conn) {
            die("Erreur de connexion : " . mysqli_connect_error());
        }
    }

    // récupère l'utilisateur par son nom
    public function getUserByUsername($nom) {
        $request = mysqli_prepare($this->conn, "SELECT id_Utilisateur, Mot_de_passe FROM utilisateur WHERE Nom = ?");
        mysqli_stmt_bind_param($request, "s", $nom);
        mysqli_stmt_execute($request);
        $res = mysqli_stmt_get_result($request);
        return $res->fetch_assoc();
    }

    // récupère l'utilisateur par son id
    public function getUsernameById($id_user) {
        $request = mysqli_prepare($this->conn, "SELECT * FROM utilisateur WHERE id_Utilisateur = ?");
        mysqli_stmt_bind_param($request, "i", $id_user);
        mysqli_stmt_execute($request);
        $res = mysqli_stmt_get_result($request);
        return $res->fetch_assoc();
    }

    // ajoute un nouvel utilisateur
    public function addUser($nom, $mdp, $profil) {
        $mdp_secure = password_hash($mdp, PASSWORD_DEFAULT);
        $request = mysqli_prepare($this->conn, "INSERT INTO utilisateur (Nom, Mot_de_passe, Profil) VALUES (?, ?, ?)"); 
        mysqli_stmt_bind_param($request, "sss", $nom, $mdp_secure, $profil);
        try {
            return mysqli_stmt_execute($request);
        } catch (mysqli_sql_exception $e) {
            // Gère les erreurs de duplication
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return "duplicate";
            }
            return false;
        }
    }

    // met à jour le mot de passe d'un utilisateur
    public function setPassword($id_user, $password) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $request = mysqli_prepare($this->conn, "UPDATE utilisateur SET Mot_de_passe = ? WHERE id_Utilisateur = ?");
        mysqli_stmt_bind_param($request, "si", $hashed, $id_user);
        return mysqli_stmt_execute($request);
    }

} 
?>
