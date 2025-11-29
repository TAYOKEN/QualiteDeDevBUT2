<?php
// Assurez-vous que connection.php définit la variable $pdo
require_once 'connection.php';
session_start();

/**
 * Classe Model pour les opérations liées aux Remises et Transactions.
 */
class RemiseModel {
    private $pdo;

    // Le constructeur prend l'objet PDO comme argument
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getRemiseByClientId($id_client) {
        $sql = "SELECT * FROM Remise 
                WHERE Id_Client = :id_client
                ORDER BY Date_vente DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id_client', $id_client, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getImpayeByRemiseId($id_remise) {
        $sql = "SELECT i.*, t.*
                FROM Impaye i
                JOIN Transactions t
                    ON i.Id_Transactions = t.Id_Transactions
                WHERE t.Id_Remise = :id_remise
                ORDER BY t.Date_Transaction DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id_remise', $id_remise, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getNormalTransactionByRemiseId($id_remise) {
        $sql = "SELECT t.*
                FROM Transactions t
                LEFT JOIN Impaye i
                    ON t.Id_Transactions = i.Id_Transactions
                WHERE i.Id_Impaye IS NULL
                AND t.Id_Remise = :id_remise
                ORDER BY t.Date_Transaction DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id_remise', $id_remise, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSoldeGlobal($id_client) {
        $sql = "SELECT 
                SUM(CASE 
                    WHEN t.Sens = '+' THEN t.Montant
                    WHEN t.Sens = '-' THEN -t.Montant
                    ELSE 0
                END) AS solde_global
                FROM Transactions t
                JOIN Remise r ON t.Id_Remise = r.Id_Remise
                WHERE r.Id_Client = :id_client";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id_client', $id_client, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['solde_global'] ?? 0;
    }

    public function getAllTransactionsWithStatus() {
        $sql = "
            SELECT
            t.Id_Transactions,
            t.Date_Transaction,
            t.Montant,
            u.Nom AS Nom_Utilisateur,
            c.Siren AS Siret_Client,
            CASE 
                WHEN i.Id_Impaye IS NOT NULL THEN 1
                ELSE 0
            END AS estImpaye
            FROM Transactions t
            LEFT JOIN Remise r ON t.Id_Remise = r.Id_Remise
            LEFT JOIN Client c ON r.Id_Client = c.Id_Client
            LEFT JOIN Utilisateur u ON c.Id_Utilisateur = u.Id_Utilisateur
            LEFT JOIN Impaye i ON i.Id_Transactions = t.Id_Transactions
            ORDER BY t.Date_Transaction DESC;
        ";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} 

/**
 * Classe Model pour les opérations liées aux Profils/Clients.
 */
class ProfilModel {
    private $pdo;

    // Le constructeur prend l'objet PDO comme argument
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getClientByUserId($id_user) {
        $sql = "SELECT * FROM Client
                WHERE Id_Utilisateur = :id_user";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
} 

/**
 * Classe Model pour les opérations liées aux Utilisateurs.
 */
class UtilisateurModel {
    private $pdo;

    // Le constructeur prend l'objet PDO comme argument
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // récupère l'utilisateur par son nom
    public function getUserByUsername($nom) {
        $sql = "SELECT id_Utilisateur, Mot_de_passe, Profil FROM Utilisateur WHERE Nom = :nom";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':nom', $nom, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // récupère l'utilisateur par son id
    public function getUsernameById($id_user) {
        $sql = "SELECT * FROM Utilisateur WHERE id_Utilisateur = :id_user";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ajoute un nouvel utilisateur
    public function addUser($nom, $mdp, $profil) {
        $mdp_secure = password_hash($mdp, PASSWORD_DEFAULT);
        $sql = "INSERT INTO Utilisateur (Nom, Mot_de_passe, Profil) VALUES (:nom, :mdp, :profil)";
        $stmt = $this->pdo->prepare($sql);
        try {
            $stmt->execute([':nom' => $nom, ':mdp' => $mdp_secure, ':profil' => $profil]);
            return true;
        } catch (PDOException $e) {
            // Code 23000 pour erreur d'intégrité (duplication)
            if ($e->getCode() == 23000) {
                return "duplicate";
            }
            throw $e; // Renvoyer les autres exceptions
        }
    }

    // met à jour le mot de passe d'un utilisateur
    public function setPassword($id_user, $password) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE Utilisateur SET Mot_de_passe = :password WHERE Id_Utilisateur = :id_user";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':password' => $hashed, ':id_user' => $id_user]);
    }

    // recupere tout les utilisateur (sans le mot de passe) et sans le compte admin
    public function getAllUser($id_user) {
        $sql = "SELECT id_Utilisateur, Nom, Profil FROM Utilisateur WHERE id_Utilisateur != :id_user";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} 

/**
 * Classe Controller pour les opérations liées aux Remises.
 */
class RemiseController {
    private $model;
    private $profilModel;

    // Le constructeur prend l'objet PDO comme argument
    public function __construct(PDO $pdo) {
        $this->model = new RemiseModel($pdo);
        $this->profilModel = new ProfilModel($pdo);
    }

    public function getRemisesStructure() {
        // ... (Le reste de la logique de cette méthode est correcte)
        if (!isset($_SESSION["id_Utilisateur"])) {
            return [];
        }
        
        $client = $this->profilModel->getClientByUserId($_SESSION["id_Utilisateur"]);
        
        if (!$client) {
            return [];
        }

        $remises = $this->model->getRemiseByClientId($client['Id_Client']);
        $result = [];
        foreach ($remises as $remise) {
            $id_remise = $remise['Id_Remise'];
            $transactions_normales = $this->model->getNormalTransactionByRemiseId($id_remise);
            $impayes = $this->model->getImpayeByRemiseId($id_remise);
            $transactions_complete = [];
            
            foreach ($transactions_normales as $t) {
                $transactions_complete[] = [
                    'transaction' => $t,
                    'impaye'      => null
                ];
            }
            
            foreach ($impayes as $i) {
                $transactions_complete[] = [
                    'transaction' => [
                        'Id_Transactions' => $i['Id_Transactions'],
                        'Id_Remise'       => $i['Id_Remise'],
                        'Date_Transaction'=> $i['Date_Transaction'],
                        'Sens'            => $i['Sens'],
                        'Libelle'         => $i['Libelle'],
                        'Num_Carte'       => $i['Num_Carte'],
                        'Montant'         => $i['Montant']
                    ],
                    'impaye' => [
                        'Id_Impaye'       => $i['Id_Impaye'],
                        'Num_dossier'     => $i['Num_dossier'],
                        'Libelle_impaye'  => $i['Libelle_impaye']
                    ]
                ];
            }
            // Tri par date décroissante
            usort($transactions_complete, function($a, $b) {
                return strtotime($b['transaction']['Date_Transaction']) - strtotime($a['transaction']['Date_Transaction']);
            });
            $result[] = [
                'remise'      => $remise,      
                'transactions' => $transactions_complete
            ];
        }
        return $result;
    }

    // Nouvelle fonction pour récupérer le solde global
    public function getSoldeGlobalClient() {
        if (!isset($_SESSION["id_Utilisateur"])) {
            return 0;
        }

        $client = $this->profilModel->getClientByUserId($_SESSION["id_Utilisateur"]);
        
        if (!$client) {
            return 0;
        }
        
        return $this->model->getSoldeGlobal($client['Id_Client']);
    }
}

// Vérifie si l'utilisateur n'est pas connecté ou n'est pas product_owner
if (!isset($_SESSION["Profil"]) || $_SESSION["Profil"] != "product_owner") {
    // La variable $pdo est définie dans connection.php
    header("Location: ../../login.php"); 
    exit;
}

// Initialisation du modèle Remise en passant l'objet PDO
// $pdo est supposé être disponible via require_once 'connection.php';
$model = new RemiseModel($pdo);
$transactions = $model->getAllTransactionsWithStatus();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Tableau de bord - Trésorerie (avec recherche avancée & sidebar)</title>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
    <link rel="stylesheet" href="css/dashboard2.css"> 

</head>
<body>
    <header>
        <div class="logo">
            <img src="logo.png" alt="logo">
        </div>
        <nav>
            <a href="/QualiteDeDevBUT2/Controllers/user_controller.php?action=logout">Déconnecter</a>
            <a href="#">à propos</a>
            <div class="menu">☰</div>
            <a href="register.php">Ajouter un utilisateur</a>
        </nav>
    </header>

    <main>
        <section class="infos">
            <div class="client-card">
                <p><strong><?php echo htmlspecialchars($_SESSION["Nom"] ?? 'Utilisateur'); ?></strong><br>PO</p>
                <p class="small">Portail de gestion de paiement - démonstration</p>
            </div>

            <div class="solde-card">
                <p>Solde Global :</p>
                <h1 id="solde-global" class="solde"></h1>
                <p class="small" id="total-neg-display"></p>
            </div>

            <div class="chart-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <div><strong>Évolution de la trésorerie</strong></div>
                    <div style="display:flex;gap:8px;align-items:center">
                        <label class="small">Type :</label>
                        <select id="chartTypeSelect" class="btn">
                            <option value="line">Courbe</option>
                            <option value="bar">Histogramme</option>
                        </select>
                    </div>
                </div>
                <canvas id="graphique" style="max-height:220px"></canvas>
                <div style="margin-top:10px"><strong>Répartition (Camembert)</strong>
                    <canvas id="pieChart" style="max-height:160px"></canvas>
                </div>
            </div>
        </section>

        <section class="transactions">
            <div class="controls">
                <div class="topbar-actions">
                    <button class="btn" id="export-csv">Export CSV</button>
                    <button class="btn" id="export-xls">Export XLS</button>
                    <button class="btn" id="export-pdf">Export PDF</button>
                </div>

                <div style="margin-left:auto; display:flex; gap:8px; align-items:center">
                    <label for="search" class="small">Recherche :</label>
                    <input id="search" placeholder="Rechercher (SIREN, intitulé, date, remise...)" />

                    <label class="small">Recherche approfondie :</label>
                    <input id="dateStart" type="date" class="btn" title="Date début" />
                    <input id="dateEnd" type="date" class="btn" title="Date fin" />
                    <button class="btn" id="applyDateRange">Appliquer</button>

                    <label class="small">Lignes :</label>
                    <select id="perPage" class="btn">
                        <option>5</option>
                        <option selected>10</option>
                        <option>25</option>
                        <option>50</option>
                    </select>
                </div>
            </div>

            <div style="display:flex;align-items:center;margin-bottom:8px">
                <div><strong id="result-count"><?= count($transactions) ?> résultats</strong></div>
                <div class="result-count" id="total-remises"></div>
            </div>

            <table id="table-clients" aria-describedby="result-count">
                <thead>
                    <tr>
                        <th data-type="date">Date : <span class="arrow">↓</span></th>
                        <th data-type="text">Nom : <span class="arrow">↓</span></th>
                        <th data-type="text">N° Siret : <span class="arrow">↓</span></th>
                        <th data-type="number">Montant : <span class="arrow">↓</span></th>
                        <th>Accéder compte :</th>
                        <th>Voir plus :</th>
                    </tr>
                </thead>
                    <tbody>
                        <?php
                        if (!empty($transactions)):
                            foreach ($transactions as $t):
                                // Détermine la classe en fonction du statut (positif/négatif)
                                $classe = $t['estImpaye'] ? 'negatif' : 'positif';
                                // Le montant affiché est le Montant de la transaction si impayé, ou 0.00 $ sinon
                                // Note: La logique initiale qui affichait 0.00 $ si ce n'est pas impayé semble étrange pour une transaction.
                                // Si c'est pour un tableau de transactions globales, vous voudrez peut-être afficher le Montant
                                // de la transaction. J'ai conservé la logique initiale d'affichage conditionnel.
                                $montant_display = $t['estImpaye'] ? number_format($t['Montant'] ?? 0, 2) . ' $' : number_format(0, 2) . ' $';
                                // Les données data-impayes et data-remises sont codées pour JavaScript
                                $data_impayes = $t['estImpaye'] ? json_encode([$t]) : json_encode([]);
                                // L'ID Remise n'est pas récupéré dans getAllTransactionsWithStatus, donc la structure est limitée ici.
                                // Si vous voulez les remises, vous devez adapter getAllTransactionsWithStatus ou les récupérer séparément.
                                // Pour l'instant, je laisse un tableau vide pour data-remises comme dans votre code initial.
                        ?>
                        <tr class="data-row" 
                            data-impayes='<?= htmlspecialchars($data_impayes, ENT_QUOTES, 'UTF-8') ?>' 
                            data-remises='<?= htmlspecialchars(json_encode([]), ENT_QUOTES, 'UTF-8') ?>'>
                            <td><?= date('d/m/Y', strtotime($t['Date_Transaction'])) ?></td>
                            <td><?= htmlspecialchars($t['Nom_Utilisateur'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($t['Siret_Client'] ?? 'N/A') ?></td>
                            <td class="<?= $classe ?>"><?= $montant_display ?></td>
                            <td><button class="btn-acceder btn">⚙️ Accéder</button></td>
                            <td><button class="btn-voir btn">👁️ Voir Plus</button></td>
                        </tr>
                        <?php endforeach; 
                        endif;
                        ?>
                    </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" class="totals">Total (visible) : <span id="visible-total">0 $</span></td>
                    </tr>
                </tfoot>
            </table>

            <div class="pager">
                <div class="pagination" id="pagination"></div>
                <div>
                    <button class="btn" id="prevPage">Préc</button>
                    <button class="btn" id="nextPage">Suiv</button>
                </div>
            </div>
        </section>
    </main>

    <div class="sidebar-backdrop" id="sidebarBackdrop">
        <aside class="sidebar" id="sidebar">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <h3 id="sidebarTitle">Détails</h3>
                <div class="close" id="sidebarClose">✖</div>
            </div>

            <div style="margin-top:8px;display:flex;gap:8px;align-items:center;">
                <button class="btn" id="export-sidebar-csv">Export CSV</button>
                <button class="btn" id="export-sidebar-xls">Export XLS</button>
                <button class="btn" id="export-sidebar-pdf">Export PDF</button>
                <div style="margin-left:auto"><span class="small" id="sidebarTotals"></span></div>
            </div>

            <h4>Impayés</h4>
            <table id="impayesTable"><thead><tr><th>Date</th><th>Date limite</th><th>Libellé</th><th>Montant</th></tr></thead><tbody></tbody></table>

            <h4 style="margin-top:12px">Rémises</h4>
            <table id="remisesTable"><thead><tr><th>Date</th><th>Date limite</th><th>Libellé</th><th>Montant</th></tr></thead><tbody></tbody></table>
        </aside>
    </div>

    <script src="js/dashboard2.js"></script> 


</body>
</html>