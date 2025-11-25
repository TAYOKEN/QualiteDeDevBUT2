<?php
    session_start();
    require "config.php";
    if (!isset($_SESSION['tentatives'])) {
        $_SESSION['tentatives'] = 0;
    }
    $message = "";
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $identifiant = trim($_POST['identifiant']);
        $passwordInput = trim($_POST['password']);
        $query = $pdo->prepare("SELECT * FROM Utilisateur WHERE Nom = ?");
        $query->execute([$identifiant]);
        $user = $query->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($passwordInput, $user['Mot_de_passe'])) {
            $_SESSION['tentatives'] = 0; // reset
            $_SESSION['user'] = $user['Nom']; 
            header("Location: accueil.php");
            exit;
        } 
        else {
            $_SESSION['tentatives']++;
            if ($_SESSION['tentatives'] == 2) {
                $message = "<div class='warning'>Attention, c'est votre **dernier essai**</div>";
            }
            elseif ($_SESSION['tentatives'] >= 3) {
                $message = "<div class='error'>Connexion bloquée. Trop de tentatives.</div>";
            } 
            else {
                $message = "<div class='error'>Identifiant ou mot de passe incorrect.</div>";
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
        .error { background:#ffb3b3; padding:10px; border-left:5px solid red; margin-bottom:10px; }
        .warning { background:#ffdd99; padding:10px; border-left:5px solid orange; margin-bottom:10px; }
        .password-wrapper { position:relative; }
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
      <a href="#">À propos</a>
      <a href="register.html">S'inscrire</a>
    </nav>
</header>

<main>
    <div class="login-section">
        <form class="login-box" method="POST">
            <h1>Bonjour</h1>
            <?= $message ?>
            <label for="identifiant">Identifiant :</label>
            <input type="text" id="identifiant" name="identifiant" required>

            <label for="password">Mot de passe :</label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password" required>
                <img id="togglePassword" src="icons/view.png" alt="Afficher mot de passe">
            </div>
            <a href="#">Mot de passe oublié</a>
            <button type="submit">Se connecter</button>
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
