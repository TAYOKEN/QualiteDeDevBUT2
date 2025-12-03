-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:3306
-- Généré le : dim. 30 nov. 2025 à 21:23
-- Version du serveur : 8.0.44-0ubuntu0.24.04.1
-- Version de PHP : 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `TALK`
--

-- --------------------------------------------------------

--
-- Structure de la table `Carte_Bancaire`
--

CREATE TABLE `Carte_Bancaire` (
  `Num_Carte` char(50) COLLATE utf8mb4_general_ci NOT NULL,
  `Date_expiration` char(50) COLLATE utf8mb4_general_ci NOT NULL,
  `Code` char(50) COLLATE utf8mb4_general_ci NOT NULL,
  `Reseau` varchar(20) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `Carte_Bancaire`
--

INSERT INTO `Carte_Bancaire` (`Num_Carte`, `Date_expiration`, `Code`, `Reseau`) VALUES
('1111222233334444', '10/24', '789', 'VISA'),
('2222333344445555', '12/26', '321', 'VISA'),
('9876543210987654', '11/25', '456', 'MASTERCARD');

-- --------------------------------------------------------

--
-- Structure de la table `Client`
--

CREATE TABLE `Client` (
  `Id_Client` int NOT NULL,
  `Siren` char(9) COLLATE utf8mb4_general_ci NOT NULL,
  `Raison_sociale` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `Id_Utilisateur` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `Client`
--

INSERT INTO `Client` (`Id_Client`, `Siren`, `Raison_sociale`, `Id_Utilisateur`) VALUES
(5, '784671678', 'Noel SAS', 4),
(6, '784671684', 'Admineee Corp', 5);

-- --------------------------------------------------------

--
-- Structure de la table `Impaye`
--

CREATE TABLE `Impaye` (
  `id_Impaye` int NOT NULL,
  `Num_dossier` char(5) COLLATE utf8mb4_general_ci NOT NULL,
  `Libelle_impaye` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `Id_Transactions` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `Impaye`
--

INSERT INTO `Impaye` (`id_Impaye`, `Num_dossier`, `Libelle_impaye`, `Id_Transactions`) VALUES
(1, 'D001', 'Impaye chaussures Nike', 25),
(2, 'D002', 'Impaye T-shirt blanc', 26),
(3, 'D003', 'Impaye Polo', 27),
(5, 'D005', 'Impaye Fumo Cirno', 29),
(6, 'D006', 'Impaye baskets Adidas', 30),
(7, 'D007', 'Impaye jeans Levi’s', 31),
(9, 'D009', 'Impaye pulls H&M', 33);

-- --------------------------------------------------------

--
-- Structure de la table `Remise`
--

CREATE TABLE `Remise` (
  `Id_Remise` int NOT NULL,
  `Num_remise` char(10) COLLATE utf8mb4_general_ci NOT NULL,
  `Date_vente` datetime NOT NULL,
  `Num_autorisation` char(6) COLLATE utf8mb4_general_ci NOT NULL,
  `Nb_transaction` int NOT NULL,
  `Id_Client` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `Remise`
--

INSERT INTO `Remise` (`Id_Remise`, `Num_remise`, `Date_vente`, `Num_autorisation`, `Nb_transaction`, `Id_Client`) VALUES
(3, 'REM-001', '2025-10-05 00:00:00', 'A001', 1, 5),
(4, 'REM-002', '2025-10-05 00:00:00', 'A002', 1, 5),
(5, 'REM-003', '2025-10-05 00:00:00', 'A003', 1, 5),
(6, 'REM-010', '2025-09-15 00:00:00', 'A010', 1, 5),
(7, 'REM-011', '2025-09-09 00:00:00', 'A011', 1, 5),
(8, 'REM-020', '2025-08-20 00:00:00', 'A020', 1, 5),
(9, 'REM-030', '2025-08-10 00:00:00', 'A030', 1, 5),
(10, 'REM-040', '2025-07-01 00:00:00', 'A040', 1, 5),
(11, 'REM-050', '2025-06-15 00:00:00', 'A050', 1, 5),
(12, 'REM-060', '2025-05-05 00:00:00', 'A060', 1, 5);

-- --------------------------------------------------------

--
-- Structure de la table `Transactions`
--

CREATE TABLE `Transactions` (
  `Id_Transactions` int NOT NULL,
  `Date_Transaction` datetime NOT NULL,
  `Sens` char(1) COLLATE utf8mb4_general_ci NOT NULL,
  `Montant` decimal(10,2) NOT NULL DEFAULT '0.00',
  `Libelle` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Num_Carte` char(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Id_Remise` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `Transactions`
--

INSERT INTO `Transactions` (`Id_Transactions`, `Date_Transaction`, `Sens`, `Montant`, `Libelle`, `Num_Carte`, `Id_Remise`) VALUES
(25, '2025-10-05 00:00:00', '-', 6000.00, 'Achat de stock de chaussures Nike', '1111222233334444', 12),
(26, '2025-10-05 00:00:00', '-', 1800.00, 'Achat de T-shirt blanc', '1111222233334444', 11),
(27, '2025-10-05 00:00:00', '-', 1000.00, 'Achat de Polo', '1111222233334444', 3),
(28, '2025-09-15 00:00:00', '+', 1500.00, 'Vente de stock de T-shirt', '2222333344445555', 4),
(29, '2025-09-09 00:00:00', '-', 2000.00, 'Achat de stock de fumo Cirno', '1111222233334444', 5),
(30, '2025-08-20 00:00:00', '+', 10000.00, 'Vente de baskets Adidas', '9876543210987654', 6),
(31, '2025-08-10 00:00:00', '-', 2000.00, 'Achat de jeans Levi’s', '2222333344445555', 7),
(32, '2025-07-01 00:00:00', '+', 3000.00, 'Vente de chemises', '9876543210987654', 8),
(33, '2025-06-15 00:00:00', '-', 2500.00, 'Achat de pulls H&M', '2222333344445555', 9),
(34, '2025-05-05 00:00:00', '+', 4000.00, 'Vente de manteaux Zara', '9876543210987654', 10);

-- --------------------------------------------------------

--
-- Structure de la table `Utilisateur`
--

CREATE TABLE `Utilisateur` (
  `Id_Utilisateur` int NOT NULL,
  `Nom` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `Mot_de_passe` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `Profil` varchar(50) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `Utilisateur`
--

INSERT INTO `Utilisateur` (`Id_Utilisateur`, `Nom`, `Mot_de_passe`, `Profil`) VALUES
(2, 'admin', '$2y$10$Ok7w5M4Tg9l32qEqfluxVOb8FBvKU9s8ssR91IgpU/ufK8VvMGpKW', 'client'),
(3, 'Yianis', '$2y$10$YOl84/UnPxpkUbdafMoqNuxA1PUZpDHk3TxWYvTk2j7S/R88PAi4q', 'product_owner'),
(4, 'amine', '$2y$10$zNXWMpLN8uKBTCpQ/8BfaOEAAm1bQlwooy9hhCqS.JXf65ypjw5lW', 'client'),
(5, 'admineee', '$2y$10$vztZs/nqWTNTWtX10ujpzOSPSYPUWbd4c15m/vIm6m/oH.jx4VUp.', 'client'),
(6, 'azerty', '$2y$10$KlAT2qaSi1R5sk8pGvemy.1k1Oe7Ht9qyNQo17fuZlo4r3e2k65Wy', 'admin');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `Carte_Bancaire`
--
ALTER TABLE `Carte_Bancaire`
  ADD PRIMARY KEY (`Num_Carte`);

--
-- Index pour la table `Client`
--
ALTER TABLE `Client`
  ADD PRIMARY KEY (`Id_Client`),
  ADD UNIQUE KEY `Id_Utilisateur` (`Id_Utilisateur`),
  ADD UNIQUE KEY `Siren` (`Siren`);

--
-- Index pour la table `Impaye`
--
ALTER TABLE `Impaye`
  ADD PRIMARY KEY (`id_Impaye`),
  ADD UNIQUE KEY `Id_Transactions` (`Id_Transactions`);

--
-- Index pour la table `Remise`
--
ALTER TABLE `Remise`
  ADD PRIMARY KEY (`Id_Remise`),
  ADD UNIQUE KEY `Num_remise` (`Num_remise`),
  ADD KEY `fk_remise_client` (`Id_Client`);

--
-- Index pour la table `Transactions`
--
ALTER TABLE `Transactions`
  ADD PRIMARY KEY (`Id_Transactions`),
  ADD KEY `Num_Carte` (`Num_Carte`),
  ADD KEY `fk_remise` (`Id_Remise`);

--
-- Index pour la table `Utilisateur`
--
ALTER TABLE `Utilisateur`
  ADD PRIMARY KEY (`Id_Utilisateur`),
  ADD UNIQUE KEY `Nom` (`Nom`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `Client`
--
ALTER TABLE `Client`
  MODIFY `Id_Client` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `Impaye`
--
ALTER TABLE `Impaye`
  MODIFY `id_Impaye` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `Remise`
--
ALTER TABLE `Remise`
  MODIFY `Id_Remise` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `Transactions`
--
ALTER TABLE `Transactions`
  MODIFY `Id_Transactions` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT pour la table `Utilisateur`
--
ALTER TABLE `Utilisateur`
  MODIFY `Id_Utilisateur` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `Client`
--
ALTER TABLE `Client`
  ADD CONSTRAINT `Client_ibfk_1` FOREIGN KEY (`Id_Utilisateur`) REFERENCES `Utilisateur` (`Id_Utilisateur`);

--
-- Contraintes pour la table `Impaye`
--
ALTER TABLE `Impaye`
  ADD CONSTRAINT `Impaye_ibfk_1` FOREIGN KEY (`Id_Transactions`) REFERENCES `Transactions` (`Id_Transactions`);

--
-- Contraintes pour la table `Remise`
--
ALTER TABLE `Remise`
  ADD CONSTRAINT `fk_remise_client` FOREIGN KEY (`Id_Client`) REFERENCES `Client` (`Id_Client`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Contraintes pour la table `Transactions`
--
ALTER TABLE `Transactions`
  ADD CONSTRAINT `fk_remise` FOREIGN KEY (`Id_Remise`) REFERENCES `Remise` (`Id_Remise`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `Transactions_ibfk_1` FOREIGN KEY (`Num_Carte`) REFERENCES `Carte_Bancaire` (`Num_Carte`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
