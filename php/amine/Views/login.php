<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion</title>
  <link rel="stylesheet" href="css/login.css">
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
    <div class="image-section">
      <img src="immeubles.jpg" alt="immeubles">
    </div>

    <div class="login-section">
      <form class="login-box" method="POST" action="../Controllers/user_controller.php">
        <h1>Bonjour</h1>
        <label for="identifiant">Identifiant :</label>
        <input type="text" id="identifiant" name="Nom" required>
        <label for="password">Mot de passe :</label>
        <input type="password" id="password" name="password" required>
        <a href="reset_password.php" class="forgot">mot de passe oublié</a>
        <button type="submit" name="login">Se connecter</button>
      </form>
    </div>
  </main>
</body>
</html>
