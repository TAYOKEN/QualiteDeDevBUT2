<?php
require_once 'connection.php';
session_start();

/**
 * Classe Model pour les opérations liées aux Remises et Transactions.
 */
class RemiseModel {
    private $pdo;

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

    /**
     * Nouvelle fonction pour le solde global de tous les clients (pour le Product Owner)
     */
    public function getGlobalSoldeAllClients() {
        $sql = "SELECT
                SUM(CASE
                        WHEN t.Sens = '+' THEN t.Montant
                        WHEN t.Sens = '-' THEN -t.Montant
                        ELSE 0
                    END) AS solde_global
                FROM Transactions t";
        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['solde_global'] ?? 0;
    }

    /**
     * Mise à jour pour inclure toutes les colonnes nécessaires pour le tableau, la recherche et les data-attributes.
     */
    public function getAllTransactionsWithStatus() {
        $sql = "
            SELECT
            t.Id_Transactions,
            t.Date_Transaction,
            t.Sens,               -- Ajouté: Sens de la transaction
            t.Montant,
            t.Libelle,            -- Ajouté: Libellé de la transaction
            t.Num_Carte,
            t.Id_Remise,          -- Ajouté: Id_Remise pour relier les transactions
            r.Id_Client,          -- Ajouté: Id_Client pour 'Accéder'
            u.Nom AS Nom_Utilisateur,
            c.Siren AS Siret_Client,
            i.Num_dossier,        -- Ajouté: Détails de l'Impayé
            i.Libelle_impaye,     -- Ajouté: Détails de l'Impayé
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

// ... (ProfilModel et UtilisateurModel restent inchangées)
class ProfilModel {
    private $pdo;

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
class UtilisateurModel {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getUserByUsername($nom) {
        $sql = "SELECT id_Utilisateur, Mot_de_passe, Profil FROM Utilisateur WHERE Nom = :nom";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':nom', $nom, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getUsernameById($id_user) {
        $sql = "SELECT * FROM Utilisateur WHERE id_Utilisateur = :id_user";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function addUser($nom, $mdp, $profil) {
        $mdp_secure = password_hash($mdp, PASSWORD_DEFAULT);
        $sql = "INSERT INTO Utilisateur (Nom, Mot_de_passe, Profil) VALUES (:nom, :mdp, :profil)";
        $stmt = $this->pdo->prepare($sql);
        try {
            $stmt->execute([':nom' => $nom, ':mdp' => $mdp_secure, ':profil' => $profil]);
            return true;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                return "duplicate";
            }
            throw $e;
        }
    }

    public function setPassword($id_user, $password) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE Utilisateur SET Mot_de_passe = :password WHERE Id_Utilisateur = :id_user";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':password' => $hashed, ':id_user' => $id_user]);
    }

    public function getAllUser($id_user) {
        $sql = "SELECT id_Utilisateur, Nom, Profil FROM Utilisateur WHERE id_Utilisateur != :id_user";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} 

// ... (RemiseController reste inchangée, elle n'est pas utilisée ici pour le PO dashboard)
class RemiseController {
    private $model;
    private $profilModel;

    public function __construct(PDO $pdo) {
        $this->model = new RemiseModel($pdo);
        $this->profilModel = new ProfilModel($pdo);
    }

    public function getRemisesStructure() {
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

// Initialisation des données pour le Product Owner
$model = new RemiseModel($pdo);
$transactions = $model->getAllTransactionsWithStatus();
$global_solde = $model->getGlobalSoldeAllClients(); // Récupère le solde global pour la carte Solde Global
$transactions_json = json_encode($transactions); // Expose toutes les transactions au JS pour les graphiques et "Voir Plus"
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
            <a href="logout.php">Déconnecter</a>
            <a href="user_gestion.php">Gerer Users</a>
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
                <h1 id="solde-global" class="solde"><?php echo number_format($global_solde, 2, ',', ' ') . ' $'; ?></h1>
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
                <div style="margin-top:10px"><strong>Répartition des Impayés (Camembert)</strong>
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
                                // Calcul du montant signé et affichage
                                $montant_val = ($t['Sens'] == '-') ? -$t['Montant'] : $t['Montant'];
                                $classe = $t['Sens'] == '-' ? 'negatif' : 'positif';
                                $montant_display = number_format($montant_val, 2, ',', ' ') . ' $';
                                
                                // Préparation des données complètes de la transaction (inclut les champs Impaye si c'en est un)
                                // pour que le JS puisse extraire les détails pour le camembert et la sidebar.
                                $data_impayes = $t['estImpaye'] ? json_encode([$t]) : json_encode([]);
                                
                                // data-remises reste vide, car le JS va simuler le fetch sur ALL_TRANSACTIONS
                        ?>
                        <tr class="data-row" 
                            data-impayes='<?= htmlspecialchars($data_impayes, ENT_QUOTES, 'UTF-8') ?>' 
                            data-remises='<?= htmlspecialchars(json_encode([]), ENT_QUOTES, 'UTF-8') ?>'
                            data-id-client="<?= htmlspecialchars($t['Id_Client'] ?? '') ?>"
                            data-id-remise="<?= htmlspecialchars($t['Id_Remise'] ?? '') ?>"
                            data-transaction-libelle="<?= htmlspecialchars($t['Libelle'] ?? '') ?>"
                            data-montant-val="<?= htmlspecialchars($montant_val) ?>"
                            >
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

            <h4>Impayés (Transaction Courante)</h4>
            <table id="impayesTable"><thead><tr><th>Date</th><th>N° Dossier</th><th>Libellé Impayé</th><th>Montant</th></tr></thead><tbody></tbody></table>

            <h4 style="margin-top:12px">Transactions de la Remise Associée</h4>
            <table id="remisesTable"><thead><tr><th>Date</th><th>Sens</th><th>Libellé</th><th>Montant</th></tr></thead><tbody></tbody></table>
        </aside>
    </div>

    <script>
        const ALL_TRANSACTIONS = <?= $transactions_json ?>;
        // On rend le solde global disponible pour la classe .negatif côté client
        const soldeGlobalEl = document.getElementById('solde-global');
        if (parseFloat('<?= $global_solde ?>') < 0) {
            soldeGlobalEl.classList.add('negatif');
        }
    </script>
    
    <script src="js/dashboard2.js"></script> 
</body>
</html>