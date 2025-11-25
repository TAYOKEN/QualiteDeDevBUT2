<?php
session_start();
require_once __DIR__ . "/connection.php";

/**
 * =====================
 *  LOG DÉBUG
 * =====================
 */
function log_login(string $msg): void {
    $file = __DIR__ . '/login_debug.log';
    $date = date('Y-m-d H:i:s');
    error_log("[$date] $msg\n", 3, $file);
}

log_login("==== Nouvelle requête sur login.php ====");

/**
 * =====================
 *  GESTION TENTATIVES
 * =====================
 */
if (!isset($_SESSION['tentatives'])) {
    $_SESSION['tentatives'] = 0;
    log_login("Init tentatives = 0");
}

/**
 * =====================
 *  NORMALISATION PROFIL
 * =====================
 * Accepte soit un profil numérique (1,2,3), soit déjà une chaîne ('admin', ...)
 */
function normalizeProfil($profilDb): ?string {
    // Si c'est déjà une chaîne attendue
    if (is_string($profilDb)) {
        $profilDb = strtolower(trim($profilDb));
        if (in_array($profilDb, ['admin', 'client', 'product_owner'], true)) {
            return $profilDb;
        }
    }

    // Si la BDD contient encore des entiers (1,2,3)
    switch ((string)$profilDb) {
        case '1':
            return 'admin';
        case '2':
            return 'client';
        case '3':
            return 'product_owner';
        default:
            return null;
    }
}

$message = "";

/**
 * =====================
 *  TRAITEMENT FORMULAIRE
 * =====================
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    log_login("Méthode POST détectée");

    // Trop de tentatives
    if ($_SESSION['tentatives'] >= 3) {
        $message = "<div class='error'>Connexion bloquée. Trop de tentatives.</div>";
        log_login("Blocage: trop de tentatives (" . $_SESSION['tentatives'] . ")");
    } else {
        $identifiant   = trim($_POST['identifiant'] ?? '');
        $passwordInput = trim($_POST['password'] ?? '');

        log_login("Identifiant reçu : '$identifiant'");

        // On récupère l'utilisateur
        $sql = "SELECT Id_Utilisateur, Nom, Mot_de_passe, Profil 
                FROM Utilisateur 
                WHERE Nom = :nom";
        $stmt = $pdo->prepare($sql);
        $ok   = $stmt->execute([':nom' => $identifiant]);

        if (!$ok) {
            $errorInfo = $stmt->errorInfo();
            log_login("Erreur SQL : " . implode(' | ', $errorInfo));
        }

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            log_login("Utilisateur trouvé : Id=" . $user['Id_Utilisateur'] . ", Nom=" . $user['Nom'] . ", Profil=" . $user['Profil']);
        } else {
            log_login("Aucun utilisateur trouvé pour ce nom.");
        }

        if ($user) {
            // Vérification du mot de passe
            $pwd_ok = password_verify($passwordInput, $user['Mot_de_passe']);
            log_login("Résultat password_verify = " . ($pwd_ok ? "OK" : "ECHEC"));

            if ($pwd_ok) {
                // Réinitialiser les tentatives
                $_SESSION['tentatives'] = 0;

                // Normaliser le profil au format attendu par le 2e code
                $profilString = normalizeProfil($user['Profil']);

                if ($profilString === null) {
                    log_login("Profil inconnu en base : " . $user['Profil']);
                    session_destroy();
                    $message = "<div class='error'>Profil utilisateur inconnu, contactez l'administrateur.</div>";
                } else {
                    // Variables de session attendues par le 2e code
                    $_SESSION['id_Utilisateur'] = (int)$user['Id_Utilisateur']; // utilisé dans dashboard_admin.php
                    $_SESSION['Profil']         = $profilString;               // 'admin', 'client', 'product_owner'

                    // Au besoin tu peux garder aussi ces infos-là
                    $_SESSION['user_nom'] = $user['Nom'];

                    log_login("Connexion OK, profil normalisé = " . $profilString);

                    // Redirection cohérente avec le 2e code :
                    // - admin / product_owner → dashboard_admin.php
                    // - client                → dashboard.php
                    if ($profilString === 'admin' || $profilString === 'product_owner') {
                        log_login("Redirection vers dashboard_admin.php");
                        header("Location: dashboard_admin.php");
                        exit;
                    } elseif ($profilString === 'client') {
                        log_login("Redirection vers dashboard.php");
                        header("Location: dashboard.php");
                        exit;
                    } else {
                        // Sécurité (au cas où un autre profil apparaisse)
                        log_login("Profil non géré après normalisation : " . $profilString);
                        session_destroy();
                        $message = "<div class='error'>Profil utilisateur inconnu, contactez l'administrateur.</div>";
                    }
                }
            } else {
                // Mot de passe incorrect
                $_SESSION['tentatives']++;
                log_login("Mauvais mot de passe. Tentatives = " . $_SESSION['tentatives']);

                if ($_SESSION['tentatives'] == 2) {
                    $message = "<div class='warning'>Attention, c'est votre <strong>dernier essai</strong></div>";
                } elseif ($_SESSION['tentatives'] >= 3) {
                    $message = "<div class='error'>Connexion bloquée. Trop de tentatives.</div>";
                } else {
                    $message = "<div class='error'>Identifiant ou mot de passe incorrect.</div>";
                }
            }
        } else {
            // Aucun utilisateur trouvé
            $_SESSION['tentatives']++;
            log_login("Aucun utilisateur, tentatives = " . $_SESSION['tentatives']);

            if ($_SESSION['tentatives'] == 2) {
                $message = "<div class='warning'>Attention, c'est votre <strong>dernier essai</strong></div>";
            } elseif ($_SESSION['tentatives'] >= 3) {
                $message = "<div class='error'>Connexion bloquée. Trop de tentatives.</div>";
            } else {
                $message = "<div class='error'>Identifiant ou mot de passe incorrect.</div>";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion</title>
    <link rel="stylesheet" href="css/login.css">
    <style>
        .error {
            background:#ffb3b3;
            padding:10px;
            border-left:5px solid red;
            margin-bottom:10px;
        }
        .warning {
            background:#ffdd99;
            padding:10px;
            border-left:5px solid orange;
            margin-bottom:10px;
        }
        .password-wrapper {
            position:relative;
        }
        #togglePassword {
            width: 22px;
            height: 22px;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            opacity: 0.7;
            transition: 0.2s ease;
        }
        #togglePassword:hover {
            opacity: 1;
        }
    </style>
</head>
<body>

<header>
    <div class="logo">
        <img src="logo.png" alt="logo">
    </div>
    <nav>
        <!-- Si besoin de liens -->
    </nav>
</header>

<main>
    <div class="login-section">
        <form class="login-box" method="POST">
            <h1>Bonjour</h1>

            <!-- Messages d'erreur / warning -->
            <?= $message ?>

            <label for="identifiant">Identifiant :</label>
            <input type="text" id="identifiant" name="identifiant" required>

            <label for="password">Mot de passe :</label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password" required>
                <img id="togglePassword" src="icons/view.png" alt="Afficher le mot de passe">
            </div>

            <button type="submit">Se connecter</button>
            <div>
                <a href="#">Mot de passe oublié</a>
            </div>
        </form>
    </div>
</main>

<script>
    const togglePassword = document.getElementById("togglePassword");
    const passwordField = document.getElementById("password");
    const eyeOpen = "icons/view.png";
    const eyeClosed = "icons/hide.png";

    togglePassword.addEventListener("click", () => {
        if (passwordField.type === "password") {
            passwordField.type = "text";
            togglePassword.src = eyeClosed;
            togglePassword.alt = "Cacher le mot de passe";
        } else {
            passwordField.type = "password";
            togglePassword.src = eyeOpen;
            togglePassword.alt = "Afficher le mot de passe";
        }
    });
</script>

</body>
</html>
