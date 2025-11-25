<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/reset_password.css">
    <title>TALK Bank</title>
</head>
<body>
    <div class="container">
        <h1>Réinitialiser le mot de passe</h1>
        <form method="POST">            
            <div class="form-group">
                <label for="password">Nouveau mot de passe:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirmer le mot de passe:</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>
            
            <button type="submit" name="reset_password">Réinitialiser</button>
        </form>
    </div>
</body>
</html>