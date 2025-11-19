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
        $request = mysqli_prepare($this->conn, "SELECT id_Utilisateur, Mot_de_passe, Profil FROM Utilisateur WHERE Nom = ?");
        mysqli_stmt_bind_param($request, "s", $nom);
        mysqli_stmt_execute($request);
        $res = mysqli_stmt_get_result($request);
        return $res->fetch_assoc();
    }

    // récupère l'utilisateur par son id
    public function getUsernameById($id_user) {
        $request = mysqli_prepare($this->conn, "SELECT * FROM Utilisateur WHERE id_Utilisateur = ?");
        mysqli_stmt_bind_param($request, "i", $id_user);
        mysqli_stmt_execute($request);
        $res = mysqli_stmt_get_result($request);
        return $res->fetch_assoc();
    }

    // ajoute un nouvel utilisateur
    public function addUser($nom, $mdp, $profil) {
        $mdp_secure = password_hash($mdp, PASSWORD_DEFAULT);
        $request = mysqli_prepare($this->conn, "INSERT INTO Utilisateur (Nom, Mot_de_passe, Profil) VALUES (?, ?, ?)"); 
        if (!$request) {
            die("Erreur préparation SQL : " . mysqli_error($this->conn));
        }
        mysqli_stmt_bind_param($request, "sss", $nom, $mdp_secure, $profil);
        $result = mysqli_stmt_execute($request);
        if (!$result) {
            die("Erreur execution requête : " . mysqli_stmt_error($request));
        }
        return $result;
    }


    // met à jour le mot de passe d'un utilisateur
    public function setPassword($id_user, $password) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $request = mysqli_prepare($this->conn, "UPDATE Utilisateur SET Mot_de_passe = ? WHERE id_Utilisateur = ?");
        mysqli_stmt_bind_param($request, "si", $hashed, $id_user);
        return mysqli_stmt_execute($request);
    }

} 
?>
