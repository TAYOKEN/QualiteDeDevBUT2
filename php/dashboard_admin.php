<?php
session_start();

if (!isset($_SESSION["Profil"]) || $_SESSION["Profil"] !== "admin" || !isset($_SESSION["id_Utilisateur"])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/connection.php'; 

/**
 * === Partie "Model" ===
 */
class UtilisateurModel {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Récupère tous les utilisateurs (sans le mot de passe) sauf l'utilisateur connecté.
     */
    public function getAllUser(int $idUtilisateur): array {
        $sql = "SELECT id_Utilisateur, Nom, Profil 
                FROM Utilisateur 
                WHERE id_Utilisateur != :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $idUtilisateur]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un utilisateur par son id.
     */
    public function getUserById(int $id): ?array {
        $sql = "SELECT id_Utilisateur, Nom, Profil 
                FROM Utilisateur 
                WHERE id_Utilisateur = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /**
     * Crée un utilisateur (mot de passe déjà hashé).
     */
    public function createUser(string $nom, string $hashMdp, int $profil): bool {
        $sql = "INSERT INTO Utilisateur (Nom, Mot_de_passe, Profil)
                VALUES (:nom, :mdp, :profil)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':nom' => $nom,
            ':mdp' => $hashMdp,
            ':profil' => $profil
        ]);
    }

    /**
     * Met à jour un utilisateur.
     * Si $hashMdp est null, on ne change pas le mot de passe.
     */
    public function updateUser(int $id, string $nom, ?string $hashMdp, int $profil): bool {
        if ($hashMdp !== null) {
            $sql = "UPDATE Utilisateur
                    SET Nom = :nom,
                        Mot_de_passe = :mdp,
                        Profil = :profil
                    WHERE id_Utilisateur = :id";
            $params = [
                ':nom' => $nom,
                ':mdp' => $hashMdp,
                ':profil' => $profil,
                ':id' => $id
            ];
        } else {
            $sql = "UPDATE Utilisateur
                    SET Nom = :nom,
                        Profil = :profil
                    WHERE id_Utilisateur = :id";
            $params = [
                ':nom' => $nom,
                ':profil' => $profil,
                ':id' => $id
            ];
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Supprime un utilisateur définitivement, en gérant la dépendance avec la table Client.
     */
    public function deleteUser(int $id): bool {
        $this->pdo->beginTransaction();
        try {
            // 1. Suppression du Client associé à cet Utilisateur (nécessaire si ON DELETE RESTRICT)
            // Note: Si Client a d'autres dépendances (ex: Remise), elles doivent être gérées ici aussi.
            $sql_client = "DELETE FROM Client WHERE Id_Utilisateur = :id";
            $stmt_client = $this->pdo->prepare($sql_client);
            $stmt_client->execute([':id' => $id]);

            // 2. Suppression de l'Utilisateur
            $sql_user = "DELETE FROM Utilisateur WHERE id_Utilisateur = :id";
            $stmt_user = $this->pdo->prepare($sql_user);
            $stmt_user->execute([':id' => $id]);

            $this->pdo->commit();
            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            // Log l'erreur pour le debug (dans un environnement réel)
            // error_log("Erreur de suppression: " . $e->getMessage()); 
            return false;
        }
    }
}

/**
 * === Partie "Controller" ===
 */
class UtilisateurController {
    private UtilisateurModel $user_model;

    public function __construct(UtilisateurModel $model) {
        $this->user_model = $model;
    }

    public function logout(): void {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }

    /**
     * Gère la création d'un utilisateur à partir du POST.
     */
    public function handleCreate(): string {
        $nom    = trim($_POST['nom'] ?? '');
        $mdp    = $_POST['mot_de_passe'] ?? '';
        $profil = (int)($_POST['profil'] ?? 0);

        if ($nom === '' || $mdp === '' || !in_array($profil, [1, 2, 3], true)) {
            return "Veuillez remplir tous les champs correctement pour la création.";
        }

        // Hash du mot de passe
        $hash = password_hash($mdp, PASSWORD_DEFAULT);

        if ($this->user_model->createUser($nom, $hash, $profil)) {
            return "Utilisateur créé avec succès.";
        }
        return "Erreur lors de la création de l'utilisateur.";
    }

    /**
     * Gère l’édition d’un utilisateur à partir du POST.
     */
    public function handleEdit(): string {
        $id     = (int)($_POST['id_Utilisateur'] ?? 0);
        $nom    = trim($_POST['nom'] ?? '');
        $mdp    = $_POST['mot_de_passe'] ?? '';
        $profil = (int)($_POST['profil'] ?? 0);

        if ($id <= 0 || $nom === '' || !in_array($profil, [1, 2, 3], true)) {
            return "Veuillez remplir tous les champs nécessaires pour la modification.";
        }

        // Si le champ mot de passe est vide, on ne change pas le mot de passe
        $hash = null;
        if ($mdp !== '') {
            $hash = password_hash($mdp, PASSWORD_DEFAULT);
        }

        if ($this->user_model->updateUser($id, $nom, $hash, $profil)) {
            return "Utilisateur modifié avec succès.";
        }
        return "Erreur lors de la modification de l'utilisateur.";
    }
    
    /**
     * Gère la suppression d'un utilisateur par son ID.
     */
    public function handleDelete(int $id): string {
        if ($id <= 0) {
            return "ID d'utilisateur invalide pour la suppression.";
        }
        
        // Sécurité: Empêcher l'utilisateur de se supprimer lui-même
        if ($id === (int)($_SESSION["id_Utilisateur"] ?? 0)) {
            return "Vous ne pouvez pas supprimer votre propre compte d'administrateur.";
        }

        if ($this->user_model->deleteUser($id)) {
            return "Utilisateur ID #{$id} supprimé avec succès.";
        }
        return "Erreur lors de la suppression de l'utilisateur ID #{$id}. (Vérifiez les dépendances)";
    }
}

/**
 * Fonction utilitaire pour afficher le profil (1,2,3 -> admin, client, owner)
 */
function labelProfil($profil): string {
    $profil = (int)$profil;
    return match ($profil) {
        1 => 'admin',
        2 => 'client',
        3 => 'owner',
        default => 'inconnu',
    };
}

// Initialisation du modèle et du contrôleur
$model = new UtilisateurModel($pdo);
$controller = new UtilisateurController($model);

$message = "";

// Gestion des actions GET simples (logout / delete)
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'logout':
            $controller->logout();
            break;
        case 'delete':
            $idToDelete = (int)($_GET['id'] ?? 0);
            $message = $controller->handleDelete($idToDelete);
            
            // Redirection en cas de succès pour éviter la re-suppression accidentelle
            if (str_contains($message, 'supprimé avec succès')) {
                // Redirige vers la page pour afficher le message de succès dans une URL propre
                header("Location: dashboard_admin.php?success=delete");
                exit;
            }
            break;
    }
}

// Gestion des formulaires POST (create / edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';
    if ($action === 'create') {
        $message = $controller->handleCreate();
    } elseif ($action === 'edit') {
        $message = $controller->handleEdit();
    }
}

// Si la page est rechargée après une suppression réussie
if (isset($_GET['success']) && $_GET['success'] === 'delete') {
    $message = "Utilisateur supprimé avec succès.";
}

// Si on est en mode édition, on charge l’utilisateur à éditer
$userToEdit = null;
if (isset($_GET['edit'])) {
    $idEdit = (int)$_GET['edit'];
    if ($idEdit > 0) {
        $userToEdit = $model->getUserById($idEdit);
    }
}

// Récupération de la liste des utilisateurs pour l'affichage du tableau
$user = $model->getAllUser((int)$_SESSION["id_Utilisateur"]);
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
            <img src="logo.png" alt="logo">
        </div>
        <nav>
            <a href="logout.php">Déconnecter</a>
        </nav>
    </header>
    <main>
        <div class="container">
            <h1>Dashboard Admin</h1>

            <?php if ($message !== ""): ?>
                <div class="alert">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

                        <section class="form-section">
                <h2>Créer un nouvel utilisateur</h2>
                <form method="post">
                    <input type="hidden" name="form_action" value="create">

                    <div class="form-group">
                        <label for="nom_create">Nom d'utilisateur</label>
                        <input type="text" id="nom_create" name="nom" required>
                    </div>

                    <div class="form-group">
                        <label for="mdp_create">Mot de passe</label>
                        <input type="password" id="mdp_create" name="mot_de_passe" required>
                    </div>

                    <div class="form-group">
                        <label for="profil_create">Rôle</label>
                        <select id="profil_create" name="profil" required>
                            <option value="">-- Choisir un rôle --</option>
                            <option value="1">Admin</option>
                            <option value="2">Client</option>
                            <option value="3">Owner</option>
                        </select>
                    </div>

                    <button type="submit" class="btn primary">Créer</button>
                </form>
            </section>

                        <?php if ($userToEdit): ?>
                <section class="form-section">
                    <h2>Modifier l'utilisateur #<?= htmlspecialchars($userToEdit['id_Utilisateur']) ?></h2>
                    <form method="post">
                        <input type="hidden" name="form_action" value="edit">
                        <input type="hidden" name="id_Utilisateur" value="<?= htmlspecialchars($userToEdit['id_Utilisateur']) ?>">

                        <div class="form-group">
                            <label for="nom_edit">Nom d'utilisateur</label>
                            <input type="text" id="nom_edit" name="nom" required
                                   value="<?= htmlspecialchars($userToEdit['Nom']) ?>">
                        </div>

                        <div class="form-group">
                            <label for="mdp_edit">Nouveau mot de passe (laisser vide pour ne pas changer)</label>
                            <input type="password" id="mdp_edit" name="mot_de_passe">
                        </div>

                        <div class="form-group">
                            <label for="profil_edit">Rôle</label>
                            <select id="profil_edit" name="profil" required>
                                <option value="1" <?= (int)$userToEdit['Profil'] === 1 ? 'selected' : '' ?>>Admin</option>
                                <option value="2" <?= (int)$userToEdit['Profil'] === 2 ? 'selected' : '' ?>>Client</option>
                                <option value="3" <?= (int)$userToEdit['Profil'] === 3 ? 'selected' : '' ?>>Owner</option>
                            </select>
                        </div>

                        <button type="submit" class="btn primary">Enregistrer</button>
                        <a href="dashboard_admin.php" class="btn secondary">Annuler</a>
                    </form>
                </section>
            <?php endif; ?>

                        <h2>Comptes utilisateurs</h2>
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Profil</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($user as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['Nom']) ?></td>
                            <td><?= htmlspecialchars(labelProfil($u['Profil'])) ?></td>
                            <td>
                                <a href="?edit=<?= (int)$u['id_Utilisateur'] ?>" class="btn">Modifier</a>
                                
                                                                <a href="?action=delete&id=<?= (int)$u['id_Utilisateur'] ?>" 
                                   onclick="return confirm('Êtes-vous sûr de vouloir SUPPRIMER définitivement l\'utilisateur <?= htmlspecialchars($u['Nom']) ?> ?');" 
                                   class="btn danger">Supprimer définitivement</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        </div>
    </main>

            </body>
</html>