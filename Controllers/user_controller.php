<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../Models/user_models.php';

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
                $_SESSION['Profil'] = $user['Profil'];
                $_SESSION['id_Utilisateur']  = $user['id_Utilisateur'];
                $this->redirect();
                exit;
            } else {
                $message = "Incorrect username or password.";
                header("Location: /QualiteDeDevBUT2/Views/login.php");
            }
        } else {
            header("Location: /QualiteDeDevBUT2/Views/login.php");
        }
    }

    public function redirect() {
        if (!isset($_SESSION["Profil"])) {
            header("Location: /QualiteDeDevBUT2/Views/login.php");
            exit;
        }
        // Redirection selon le profil
        switch ($_SESSION["Profil"]) {
            case 'client':
                header("Location: /QualiteDeDevBUT2/Views/dashboard.php");
                exit;
            case 'admin':
                header("Location: /QualiteDeDevBUT2/Views/dashboard_admin.php");
                exit;
            case 'product_owner':
                header("Location: /QualiteDeDevBUT2/Views/dashboard_po.php");
                exit;
            default:
                header("Location: /QualiteDeDevBUT2/Views/login.php");
                exit;
        }
    }

    // inscription
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Nom'], $_POST['password'], $_POST['password_confirm'], $_POST['profil'])) {     
            $nom = trim($_POST['Nom']);
            $profil = $_POST['profil'];
            $password = $_POST['password'];
            $password_confirm = $_POST['password_confirm'];
            // valide le mot de passe
            $passwordPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';
            if (!preg_match($passwordPattern, $password)) {
                $message = "Le mot de passe doit contenir au moins 8 caracteres, dont une majuscule, une minuscule, un chiffre et un caractere speciale.";
                header("Location: /QualiteDeDevBUT2/Views/register.php");
                return;
            }
            // vérification de la confirmation
            if ($password !== $password_confirm) {
                $message = "Les mots de passe ne correspondent pas.";
                header("Location: /QualiteDeDevBUT2/Views/register.php");
                return;
            }
            // essayez d'ajouter l'utilisateur
            $result = $this->user_model->addUser($nom, $password, $profil);
            if ($result === true) {
                $user = $this->user_model->getUserByUsername($nom);
                header("Location: ../Views/dashboard_po.php");
                exit;
            } elseif ($result === "duplicate") {
                $_SESSION['register_message'] = "Ce nom d'utilisateur ou l'adresse email existe deja. Veuillez en choisir un autre.";
                header("Location: ../Views/register.php");
                exit;
            } else {
                $_SESSION['register_message'] = "L'inscription a echouer, veuillez ressayer.";
                header("Location: ../Views/register.php");
                exit;
            }
        } else {
            header("Location: ../Views/register.php");
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
                header("Location: /QualiteDeDevBUT2/Views/login.php");
            } else {
                $message = "Les mots de passe ne correspondent pas.";
                header("Location: /QualiteDeDevBUT2/Views/reset_password.php");
            }
        } else {
            header("Location: /QualiteDeDevBUT2/Views/reset_password.php");
        }
    }
    
    // deconnexion
    public function logout() {
        session_unset();
        session_destroy();
        header("Location: ../Views/login.php");
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
