<?php
session_start();
require_once __DIR__ . '/../Models/remise_models.php';
require_once __DIR__ . '/../Models/profil_models.php';

class RemiseController {
    private $model;

    public function __construct() {
        $this->model = new RemiseModel();
        $this->profilModel = new ProfilModel();
    }

    public function getRemisesStructure() {
        $client = $this->profilModel->getClientByUserId($_SESSION["id_Utilisateur"]);
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
                        'Id_Impaye'      => $i['Id_Impaye'],
                        'Num_dossier'    => $i['Num_dossier'],
                        'Libelle_impaye' => $i['Libelle_impaye']
                    ]
                ];
            }
            // Tri par date décroissante
            usort($transactions_complete, function($a, $b) {
                return strtotime($b['transaction']['Date_Transaction']) - strtotime($a['transaction']['Date_Transaction']);
            });
            $result[] = [
                'remise'       => $remise,          
                'transactions' => $transactions_complete
            ];
        }
        return $result;
    }

    // Nouvelle fonction pour récupérer le solde global
    public function getSoldeGlobalClient() {
        $client = $this->profilModel->getClientByUserId($_SESSION["id_Utilisateur"]);
        return $this->model->getSoldeGlobal($client['Id_Client']);
    }
}
?>
