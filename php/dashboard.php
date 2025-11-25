<?php
session_start();

// ========================
//  LOGOUT (fusion user_controller.php)
// ========================
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// ========================
//  SÉCURITÉ / LOGIN
// ========================
if (
    !isset($_SESSION['Profil']) ||
    $_SESSION['Profil'] !== 'client' ||
    !isset($_SESSION['id_Utilisateur'])
) {
    header('Location: login.php');
    exit;
}

// ========================
//  CONNEXION PDO
// ========================
require_once __DIR__ . '/connection.php';   // $pdo vient d'ici

// ========================
//  MODELES (fusion profil_models.php + remise_models.php)
// ========================

/**
 * Classe pour récupérer les infos Client à partir de l'utilisateur
 */
class ProfilModel {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Retourne la ligne Client correspondant à l'utilisateur
     * (Id_Utilisateur = clé étrangère dans Client)
     */
    public function getClientByUserId($idUser) {
        $sql = "SELECT * FROM Client WHERE Id_Utilisateur = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $idUser]);
        $client = $stmt->fetch();
        return $client ?: null;
    }
}

/**
 * Classe pour toutes les requêtes liées aux remises et transactions
 * (conversion de remise_models.php en PDO)
 */
class RemiseModel {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Toutes les remises d'un client
     */
    public function getRemiseByClientId($id_client) {
        $sql = "
            SELECT *
            FROM Remise
            WHERE Id_Client = :id_client
            ORDER BY Date_vente DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id_client' => $id_client]);
        return $stmt->fetchAll();
    }

    /**
     * Transactions impayées d'une remise
     */
    public function getImpayeByRemiseId($id_remise) {
        $sql = "
            SELECT i.*, t.*
            FROM Impaye i
            JOIN Transactions t
                ON i.Id_Transactions = t.Id_Transactions
            WHERE t.Id_Remise = :id_remise
            ORDER BY t.Date_Transaction DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id_remise' => $id_remise]);
        return $stmt->fetchAll();
    }

    /**
     * Transactions normales (non impayées) d'une remise
     */
    public function getNormalTransactionByRemiseId($id_remise) {
        $sql = "
            SELECT t.*
            FROM Transactions t
            LEFT JOIN Impaye i
                ON t.Id_Transactions = i.Id_Transactions
            WHERE i.Id_Impaye IS NULL
              AND t.Id_Remise = :id_remise
            ORDER BY t.Date_Transaction DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id_remise' => $id_remise]);
        return $stmt->fetchAll();
    }

    /**
     * Solde global d'un client (toutes remises confondues)
     */
    public function getSoldeGlobal($id_client) {
        $sql = "
            SELECT 
                SUM(
                    CASE 
                        WHEN t.Sens = '+' THEN t.Montant
                        WHEN t.Sens = '-' THEN -t.Montant
                        ELSE 0
                    END
                ) AS solde_global
            FROM Transactions t
            JOIN Remise r ON t.Id_Remise = r.Id_Remise
            WHERE r.Id_Client = :id_client
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id_client' => $id_client]);
        $row = $stmt->fetch();
        return isset($row['solde_global']) ? (float)$row['solde_global'] : 0.0;
    }
}

// ========================
//  CONTROLLER (fusion remise_controller.php)
// ========================
class RemiseController {
    private $model;
    private $profilModel;

    public function __construct(PDO $pdo) {
        $this->model       = new RemiseModel($pdo);
        $this->profilModel = new ProfilModel($pdo);
    }

    /**
     * Construit la structure attendue par le dashboard :
     * [
     *   [
     *     'remise' => [...],
     *     'transactions' => [
     *         [
     *           'transaction' => [...],
     *           'impaye'      => [...] ou null
     *         ],
     *         ...
     *     ]
     *   ],
     *   ...
     * ]
     */
    public function getRemisesStructureForUser($idUser) {
        $client = $this->profilModel->getClientByUserId($idUser);
        if (!$client) {
            return [];
        }

        $remises = $this->model->getRemiseByClientId($client['Id_Client']);
        $result  = [];

        foreach ($remises as $remise) {
            if (!isset($remise['Id_Remise'])) {
                // au cas où le nom de colonne diffère
                continue;
            }
            $idRemise = (int)$remise['Id_Remise'];

            // impayés indexés par Id_Transactions
            $impayes = $this->model->getImpayeByRemiseId($idRemise);
            $impayesParTransaction = [];
            foreach ($impayes as $imp) {
                if (isset($imp['Id_Transactions'])) {
                    $impayesParTransaction[(int)$imp['Id_Transactions']] = $imp;
                }
            }

            // transactions normales
            $transactionsNormales = $this->model->getNormalTransactionByRemiseId($idRemise);
            $transactions_complete = [];

            foreach ($transactionsNormales as $t) {
                $idTrans = isset($t['Id_Transactions']) ? (int)$t['Id_Transactions'] : null;
                $impaye  = ($idTrans !== null && isset($impayesParTransaction[$idTrans]))
                    ? $impayesParTransaction[$idTrans]
                    : null;

                $transactions_complete[] = [
                    'transaction' => $t,
                    'impaye'      => $impaye
                ];
            }

            $result[] = [
                'remise'       => $remise,
                'transactions' => $transactions_complete
            ];
        }

        return $result;
    }

    /**
     * Solde global pour l'utilisateur connecté
     */
    public function getSoldeGlobalForUser($idUser) {
        $client = $this->profilModel->getClientByUserId($idUser);
        if (!$client) {
            return 0.0;
        }
        return $this->model->getSoldeGlobal($client['Id_Client']);
    }
}

// ========================
//  INITIALISATION DES DONNÉES POUR LA VUE
// ========================
$idUser      = (int) $_SESSION['id_Utilisateur'];
$nomConnecte = $_SESSION['user_nom'] ?? ($_SESSION['Nom'] ?? 'Utilisateur');

$remiseController = new RemiseController($pdo);
$remisesData      = $remiseController->getRemisesStructureForUser($idUser);
$soldeTotal       = $remiseController->getSoldeGlobalForUser($idUser);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <title>Tableau de bord - Trésorerie</title>
    <link rel="stylesheet" href="css/dashboard1.css">
    <style>
        .detail-row { display: none; }
        .row-toggle { cursor: pointer; }
        .positif { color: green; }
        .negatif { color: red; }
        .inner-transactions { width: 100%; border-collapse: collapse; }
        .inner-transactions td { padding: 4px; border: 1px solid #ccc; }
        .small { font-size: 0.9em; color:#555; }
        header nav a { margin-right:10px; }
    </style>
</head>
<body>
<header>
    <div class="logo"><img src="logo.png" alt="logo"></div>
    <nav>
        <!-- logout fusionné ici -->
        <a href="dashboard.php?action=logout">Déconnexion</a>
        <a href="#">À propos</a>
    </nav>
</header>

<main>
    <section class="infos">
        <div class="client-card">
            <p><strong>Client connecté :</strong>
                <?php echo htmlspecialchars($nomConnecte); ?>
            </p>
            <p class="small">Portail de gestion de paiement - démonstration</p>
        </div>

        <div class="solde-card">
            <p>Solde Global :</p>
            <?php
            $soldeTotal = (float)$soldeTotal;
            $soldeClass = ($soldeTotal < 0) ? 'negatif' : 'positif';
            $soldeAff   = ($soldeTotal >= 0 ? '+' : '-') . number_format(abs($soldeTotal), 2) . ' $';
            ?>
            <h1 class="<?php echo $soldeClass; ?>"><?php echo $soldeAff; ?></h1>
        </div>
    </section>

    <section class="transactions">
        <h2>Remises et transactions</h2>

        <?php if (empty($remisesData)): ?>
            <p>Aucune remise trouvée pour votre compte.</p>
        <?php else: ?>

        <table class="remises-table">
            <thead>
                <tr>
                    <th>N° Remise</th>
                    <th>Date de vente</th>
                    <th>Total remise</th>
                    <th>Détails</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($remisesData as $remiseItem): 
                $remise       = $remiseItem['remise'];
                $transactions = $remiseItem['transactions'];

                // calcul du total de la remise
                $totalRemise = 0;
                foreach ($transactions as $tr) {
                    $sens    = $tr['transaction']['Sens'] ?? '+';
                    $montant = (float)($tr['transaction']['Montant'] ?? 0);
                    $totalRemise += ($sens === '+') ? $montant : -$montant;
                }
                $classTotal = ($totalRemise < 0) ? 'negatif' : 'positif';
            ?>
                <!-- Ligne de synthèse de la remise -->
                <tr class="remise-row">
                    <td><?php echo htmlspecialchars($remise['Num_remise'] ?? ''); ?></td>
                    <td>
                        <?php
                        $dateVente = $remise['Date_vente'] ?? null;
                        echo $dateVente ? date("d/m/Y", strtotime($dateVente)) : '';
                        ?>
                    </td>
                    <td class="<?php echo $classTotal; ?>">
                        <?php echo number_format($totalRemise, 2); ?> $
                    </td>
                    <td class="row-toggle">➕</td>
                </tr>

                <!-- Lignes détaillées -->
                <tr class="detail-row">
                    <td colspan="4">
                        <table class="inner-transactions">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Libellé</th>
                                    <th>Num Carte</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($transactions as $tr): 
                                $t       = $tr['transaction'];
                                $impaye  = $tr['impaye'];
                                $sens    = $t['Sens'] ?? '+';
                                $montant = (float)($t['Montant'] ?? 0);
                                $classMontant = $impaye ? 'negatif' : 'positif';
                                $montantAff   = ($sens === '+')
                                    ? '+' . number_format($montant, 2)
                                    : '-' . number_format($montant, 2);
                            ?>
                                <tr>
                                    <td>
                                        <?php
                                        $dt = $t['Date_Transaction'] ?? null;
                                        echo $dt ? date("d/m/Y", strtotime($dt)) : '';
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($t['Libelle'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($t['Num_Carte'] ?? ''); ?></td>
                                    <td class="<?php echo $classMontant; ?>">
                                        <?php echo $montantAff; ?> $
                                    </td>
                                    <td>
                                        <?php 
                                        if ($impaye && isset($impaye['Num_dossier'])) {
                                            echo "IMPAYÉ - Dossier : " . htmlspecialchars($impaye['Num_dossier']);
                                        } else {
                                            echo "Transaction normale";
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php endif; ?>
    </section>
</main>

<script>
// Toggle affichage des lignes de détail (équivalent JS d'origine)
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".row-toggle").forEach(function (btn) {
        btn.addEventListener("click", function () {
            const tr = this.closest("tr");
            const detailRow = tr.nextElementSibling;
            if (detailRow && detailRow.classList.contains("detail-row")) {
                detailRow.style.display = (detailRow.style.display === "table-row") ? "none" : "table-row";
                this.textContent = (this.textContent === "➕") ? "➖" : "➕";
            }
        });
    });
});
</script>
<script src="js/dashboard1.js"></script>
</body>
</html>
