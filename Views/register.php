<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription</title>
    <link rel="stylesheet" href="css/register.css">
</head>
<body>
    <header>
        <div class="logo">
            <img src="logo.png" alt="logo">
        </div>
        <nav>
            <a href="#">à propos</a>
        </nav>
    </header>

    <main>
        <div class="image-container">
            <img src="" alt="ville">
        </div>

        <div class="form-container">
            <h1>Bienvenue</h1>
            <form method="POST" action="/QualiteDeDevBUT2/Controllers/user_controller.php">
                <label for="identifiant">Identifiant :</label>
                <input type="text" id="identifiant" name="Nom" required>

                <label for="profil">Profil :</label>
                <select id="profil" name="profil">
                    <option value="client">Client</option>
                    <option value="admin">Admin</option>
                    <option value="product_owner">Product Owner</option>
                </select required>

                <label for="password">Mot de passe :</label>
                <input type="password" id="password" name="password" required>

                <label for="confirm-password">Confirmer mot de passe :</label>
                <input type="password" id="confirm-password" name="password_confirm" required>

                <div class="conditions">
                    <input type="checkbox" id="conditions" required>
                    <label for="conditions">J'accepte les conditions d'utilisation</label>
                </div>

                <button type="submit" name="register">S'inscrire</button>
            </form>
        </div>
    </main>
</body>
</html>
