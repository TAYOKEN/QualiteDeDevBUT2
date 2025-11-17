<?php
session_start();
require_once __DIR__ . '/../models/user_models.php';

class UtilisateurController {
    private $user_model;

    public function __construct() {
        $this->user_model = new UtilisateurModel();
    }

    // connexion
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['Nom']) && !empty($_POST['password'])) {
            $nom = trim($_POST['Nom']);
            $password = $_POST['password'];
            $user = $this->user_model->getUserByUsername($nom);
            if ($user && password_verify($password, $user['Mot_de_passe'])) {
                $_SESSION['Nom'] = $nom;
                $_SESSION['id_Utilisateur']  = $user['id_Utilisateur'];
                header("Location: ../views/images_views.php");
                exit;
            } else {
                $message = "Incorrect username or password.";
                include __DIR__ . '/../Views/login.html';
            }
        } else {
            include __DIR__ . '/../Views/login.html';
        }
    }

    // inscription
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Nom'], $_POST['password'], $_POST['password_confirm'])) {     
            $nom = trim($_POST['Nom']);
            $password = $_POST['password'];
            $password_confirm = $_POST['password_confirm'];
            // valide le mot de passe
            $passwordPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';
            if (!preg_match($passwordPattern, $password)) {
                $message = "Le mot de passe doit contenir au moins 8 caracteres, dont une majuscule, une minuscule, un chiffre et un caractere speciale.";
                include __DIR__ . '/../Views/register.html';
                return;
            }
            // vérification de la confirmation
            if ($password !== $password_confirm) {
                $message = "Les mots de passe ne correspondent pas.";
                include __DIR__ . '/../Views/register.html';
                return;
            }
            // essayez d'ajouter l'utilisateur
            $result = $this->user_model->addUser($nom, $password, "client");
            if ($result === true) {
                $user = $this->user_model->getUserByUsername($nom);
                header("Location: ../Views/login.html");
                exit;
            } elseif ($result === "duplicate") {
                $_SESSION['register_message'] = "Ce nom d'utilisateur ou l'adresse email existe deja. Veuillez en choisir un autre.";
                header("Location: ../Views/register.html");
                exit;
            } else {
                $_SESSION['register_message'] = "L'inscription a echouer, veuillez ressayer.";
                header("Location: ../Views/register.html");
                exit;
            }
        } else {
            header("Location: ../Views/register.html");
            exit;
        }
    }


    // formulaire de reinitialisation de mot de passe
    public function resetPasswordForm() {
        if (isset($_POST['reset_password'], $_POST['password'], $_POST['password_confirm'])) {
            $password = $_POST['password'];
            $password_confirm = $_POST['password_confirm'];
            if ($password === $password_confirm) {
                $this->user_model->setPassword($_SESSION['user_id'], $password);
                $message = "Le mot de passe est mis à jour avec succès.";
                $this->logout();
                include __DIR__ . '/../Views/login.php';
            } else {
                $message = "Les mots de passe ne correspondent pas.";
                include __DIR__ . '/../Views/reset_password.php';
            }
        } else {
            include __DIR__ . '/../Views/reset_password.php';
        }
    }

    public function resetPassword() {
        // ..
    }

    // deconnexion
    public function logout() {
        session_unset();
        session_destroy();
        header("Location: ../Views/Acceuil.html");
        exit;
    }
}

$controller = new UtilisateurController();

// les actions post
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        $controller->login();
    } elseif (isset($_POST['register'])) {
        $controller->register();
    } elseif (isset($_POST['reset_password'])) {
        $controller->resetPasswordForm();
    } 
}

// les actions get
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'logout':
            $controller->logout();
            break;
        case 'resetPassword':
            $controller->resetPassword();
            break;
    }
}
?>
