<?php
session_start();
// Vérifie si l'utilisateur n'est pas connecté ou n'est pas admin
if (!isset($_SESSION["Profil"]) || $_SESSION["Profil"] != "admin") {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../Models/user_models.php';
$model = new UtilisateurModel();
$user = $model->getAllUser($_SESSION["id_Utilisateur"]); 
?>  

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Administration – Comptes utilisateurs</title>
<link rel="stylesheet" href="css/dashboard3.css">

</head>
<body>

<header>
    <div class="logo">
        <img src="" alt="logo">
    </div>
    <nav>
        <a href="/QualiteDeDevBUT2/Controllers/user_controller.php?action=logout">Déconnecter</a>
        <a href="#">Tableau de bord</a>
        <a href="#">Admin</a>
    </nav>
</header>

<main>
    <h1>Gestion des comptes utilisateurs</h1>
     <p><strong><?php echo htmlspecialchars($_SESSION["Nom"]); ?></strong><br>Admin</p>
    <div class="user-table">
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="🔍 Rechercher un utilisateur...">
        </div>

        <table id="userTable">
            <thead>
                <tr>
                    <th>Nom d'utilisateur</th>
                    <th>Rôle</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($user as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['Nom']) ?></td>
                    <td><?= htmlspecialchars($u['Profil']) ?></td>
                    <td>
                        <button class="btn danger">Demander suppression</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        </table>
    </div>
</main>

<div class="notif" id="notif">Demande de suppression envoyée</div>
    <script src="js/dashboard3.js"></script>


</body>
</html>
