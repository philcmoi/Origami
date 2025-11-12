-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : mer. 12 nov. 2025 à 04:47
-- Version du serveur : 8.2.0
-- Version de PHP : 8.2.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `origami`
--

-- --------------------------------------------------------

--
-- Structure de la table `administrateur`
--

DROP TABLE IF EXISTS `administrateur`;
CREATE TABLE IF NOT EXISTS `administrateur` (
  `idAdmin` bigint NOT NULL AUTO_INCREMENT,
  `email` varchar(50) NOT NULL,
  `motDePasse` varchar(255) NOT NULL,
  PRIMARY KEY (`idAdmin`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `administrateur`
--

INSERT INTO `administrateur` (`idAdmin`, `email`, `motDePasse`) VALUES
(1, 'lhpp.philippe@gmail.com', '$2y$10$3xC95pryxvZKeGjJ4FbYVO6VI.PJtRaLWeO7fE.jdxJ0tYC8IqR6S');

-- --------------------------------------------------------

--
-- Structure de la table `adresse`
--

DROP TABLE IF EXISTS `adresse`;
CREATE TABLE IF NOT EXISTS `adresse` (
  `idAdresse` bigint NOT NULL AUTO_INCREMENT,
  `idClient` bigint NOT NULL,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `adresse` varchar(50) NOT NULL,
  `codePostal` varchar(50) NOT NULL,
  `ville` varchar(50) NOT NULL,
  `pays` varchar(50) NOT NULL,
  `telephone` varchar(50) NOT NULL,
  `instructions` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `societe` varchar(50) DEFAULT NULL,
  `type` varchar(20) DEFAULT 'livraison',
  `dateCreation` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idAdresse`),
  KEY `Adresse_idClient_FK` (`idClient`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `adresse`
--

INSERT INTO `adresse` (`idAdresse`, `idClient`, `nom`, `prenom`, `adresse`, `codePostal`, `ville`, `pays`, `telephone`, `instructions`, `societe`, `type`, `dateCreation`) VALUES
(38, 169, 'LOR', 'Philippe', '116 rue de Javel', '75015', 'Paris', 'France', '', '', NULL, 'livraison', '2025-11-11 09:00:26'),
(39, 171, 'LOR', 'Philippe', '116 rue de javel', '75015', 'Paris', 'France', '', '', NULL, 'livraison', '2025-11-11 09:04:19');

-- --------------------------------------------------------

--
-- Structure de la table `cartebancaire`
--

DROP TABLE IF EXISTS `cartebancaire`;
CREATE TABLE IF NOT EXISTS `cartebancaire` (
  `idCarteBancaire` bigint NOT NULL AUTO_INCREMENT,
  `idClient` bigint NOT NULL,
  `nomTitulaire` varchar(100) NOT NULL,
  `derniersChiffres` varchar(4) NOT NULL,
  `dateExpiration` date NOT NULL,
  `typeCarte` varchar(20) NOT NULL,
  PRIMARY KEY (`idCarteBancaire`),
  KEY `CarteBancaire_idClient_FK` (`idClient`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `client`
--

DROP TABLE IF EXISTS `client`;
CREATE TABLE IF NOT EXISTS `client` (
  `idClient` bigint NOT NULL AUTO_INCREMENT,
  `email` varchar(50) NOT NULL,
  `motDePasse` varchar(255) NOT NULL,
  `nom` varchar(50) DEFAULT NULL,
  `prenom` varchar(50) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email_confirme` tinyint(1) DEFAULT '0',
  `token_confirmation` varchar(64) DEFAULT NULL,
  `token_expires` datetime DEFAULT NULL,
  `type` enum('temporaire','permanent') DEFAULT 'temporaire',
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `session_id` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`idClient`),
  KEY `idx_client_type` (`type`),
  KEY `idx_client_session` (`session_id`),
  KEY `idx_client_date` (`date_creation`)
) ENGINE=InnoDB AUTO_INCREMENT=174 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `client`
--

INSERT INTO `client` (`idClient`, `email`, `motDePasse`, `nom`, `prenom`, `telephone`, `email_confirme`, `token_confirmation`, `token_expires`, `type`, `date_creation`, `session_id`) VALUES
(169, 'lhpp.philippe@gmail.com', '$2y$10$cWbvih7eAEyMQlDeMTVCF.VME2c43EvXH5ZTdwRCXw0NAPd7Wv8Se', 'Client', '', '', 0, NULL, NULL, 'permanent', '2025-11-11 09:00:02', NULL),
(171, 'wongfeyhong45@gmail.com', '$2y$10$qLmvYcdIce75KkPP2aTU3OmeSZLOdH1HqmFVsOCTzXkDYI6iOs/Vy', 'Client', '', '', 0, NULL, NULL, 'permanent', '2025-11-11 09:03:44', NULL),
(173, 'temp_6914044f02b89@origamizen.fr', '', 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2025-11-12 04:51:43', 'vru0mk99fg8m5sab3fng9a6c5u');

-- --------------------------------------------------------

--
-- Structure de la table `codeconfirmation`
--

DROP TABLE IF EXISTS `codeconfirmation`;
CREATE TABLE IF NOT EXISTS `codeconfirmation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `code` varchar(8) NOT NULL,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `utilise` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `commande`
--

DROP TABLE IF EXISTS `commande`;
CREATE TABLE IF NOT EXISTS `commande` (
  `idCommande` bigint NOT NULL AUTO_INCREMENT,
  `idClient` bigint NOT NULL,
  `idAdresseLivraison` bigint NOT NULL,
  `idAdresseFacturation` bigint DEFAULT NULL,
  `dateCommande` datetime NOT NULL,
  `modeReglement` varchar(20) NOT NULL DEFAULT 'CB',
  `delaiLivraison` date NOT NULL,
  `fraisDePort` double NOT NULL,
  `montantTotal` double NOT NULL,
  `statut` enum('en_attente','en_attente_paiement','payee','confirmee','expediee','livree','annulee') NOT NULL DEFAULT 'en_attente',
  `statut_paiement` enum('en_attente','payee','echec','annulee','rembourse') DEFAULT 'en_attente',
  `idPaiement` int DEFAULT NULL,
  `visible` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`idCommande`),
  KEY `Commande_idClient_FK` (`idClient`),
  KEY `Commande_idAdresseLivraison_FK` (`idAdresseLivraison`),
  KEY `idAdresseFacturation` (`idAdresseFacturation`)
) ENGINE=InnoDB AUTO_INCREMENT=149 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `commande`
--

INSERT INTO `commande` (`idCommande`, `idClient`, `idAdresseLivraison`, `idAdresseFacturation`, `dateCommande`, `modeReglement`, `delaiLivraison`, `fraisDePort`, `montantTotal`, `statut`, `statut_paiement`, `idPaiement`, `visible`) VALUES
(146, 169, 38, 38, '2025-11-11 09:00:26', 'PayPal', '2025-11-16', 0, 1, 'payee', 'en_attente', NULL, 1),
(148, 171, 39, 39, '2025-11-11 09:04:19', 'Carte Bancaire (PayP', '2025-11-16', 0, 1, 'livree', 'en_attente', NULL, 0);

-- --------------------------------------------------------

--
-- Structure de la table `lignecommande`
--

DROP TABLE IF EXISTS `lignecommande`;
CREATE TABLE IF NOT EXISTS `lignecommande` (
  `idLigneCommande` bigint NOT NULL AUTO_INCREMENT,
  `idCommande` bigint NOT NULL,
  `idOrigami` bigint NOT NULL,
  `quantite` int NOT NULL,
  `prixUnitaire` double NOT NULL,
  PRIMARY KEY (`idLigneCommande`),
  KEY `LigneCommande_idCommande_FK` (`idCommande`),
  KEY `LigneCommande_idOrigami_FK` (`idOrigami`)
) ENGINE=InnoDB AUTO_INCREMENT=155 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `lignecommande`
--

INSERT INTO `lignecommande` (`idLigneCommande`, `idCommande`, `idOrigami`, `quantite`, `prixUnitaire`) VALUES
(152, 146, 5, 1, 1),
(154, 148, 5, 1, 1);

-- --------------------------------------------------------

--
-- Structure de la table `lignepanier`
--

DROP TABLE IF EXISTS `lignepanier`;
CREATE TABLE IF NOT EXISTS `lignepanier` (
  `idLignePanier` bigint NOT NULL AUTO_INCREMENT,
  `idPanier` bigint NOT NULL,
  `idOrigami` bigint NOT NULL,
  `quantite` int NOT NULL DEFAULT '1',
  `prixUnitaire` double NOT NULL,
  PRIMARY KEY (`idLignePanier`),
  KEY `LignePanier_idPanier_FK` (`idPanier`),
  KEY `LignePanier_idOrigami_FK` (`idOrigami`)
) ENGINE=InnoDB AUTO_INCREMENT=301 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `origami`
--

DROP TABLE IF EXISTS `origami`;
CREATE TABLE IF NOT EXISTS `origami` (
  `idOrigami` bigint NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) NOT NULL,
  `description` varchar(300) NOT NULL,
  `photo` varchar(300) NOT NULL,
  `prixHorsTaxe` double NOT NULL,
  PRIMARY KEY (`idOrigami`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `origami`
--

INSERT INTO `origami` (`idOrigami`, `nom`, `description`, `photo`, `prixHorsTaxe`) VALUES
(1, 'La grue Élégante', 'Symbole de paix et de longévité, cette grue est pliée avec un papier washi traditionnel.', 'img/couple de sygnes.jpg', 24),
(2, 'Fleur de Cerisier', 'Inspirée des sakura japonais, cette fleur délicate apporte une touche de printemps éternel.', 'img/flower.jpg', 18),
(3, 'Dragon Majestueux', 'Une création complexe et impressionnante, symbole de puissance et de sagesse.', 'img/dragon.png', 45),
(4, 'Éventail Traditionnel', 'Accessoire élégant et fonctionnel, plié avec un papier aux motifs traditionnels.', 'img/eventail.jpg', 32),
(5, '1 euro', ' Pièce de monnaie', 'img/euro.jpg', 1);

-- --------------------------------------------------------

--
-- Structure de la table `paiement`
--

DROP TABLE IF EXISTS `paiement`;
CREATE TABLE IF NOT EXISTS `paiement` (
  `idPaiement` int NOT NULL AUTO_INCREMENT,
  `idCommande` int NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'EUR',
  `statut` varchar(20) DEFAULT 'en_attente',
  `methode_paiement` varchar(50) DEFAULT 'carte',
  `reference` varchar(100) DEFAULT NULL,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_maj` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idPaiement`),
  KEY `idCommande` (`idCommande`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `paiement`
--

INSERT INTO `paiement` (`idPaiement`, `idCommande`, `montant`, `currency`, `statut`, `methode_paiement`, `reference`, `date_creation`, `date_maj`) VALUES
(4, 147, 1.00, 'EUR', 'payee', 'PayPal', NULL, '2025-11-11 09:02:59', '2025-11-11 09:02:59'),
(5, 148, 1.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1762848386_148', '2025-11-11 09:06:26', '2025-11-11 09:06:26');

-- --------------------------------------------------------

--
-- Structure de la table `panier`
--

DROP TABLE IF EXISTS `panier`;
CREATE TABLE IF NOT EXISTS `panier` (
  `idPanier` bigint NOT NULL AUTO_INCREMENT,
  `idClient` bigint NOT NULL,
  `dateModification` datetime NOT NULL,
  PRIMARY KEY (`idPanier`),
  UNIQUE KEY `idClient` (`idClient`)
) ENGINE=InnoDB AUTO_INCREMENT=154 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `panier`
--

INSERT INTO `panier` (`idPanier`, `idClient`, `dateModification`) VALUES
(151, 169, '2025-11-11 09:02:15'),
(153, 171, '2025-11-11 09:04:19');

-- --------------------------------------------------------

--
-- Structure de la table `tokens_confirmation`
--

DROP TABLE IF EXISTS `tokens_confirmation`;
CREATE TABLE IF NOT EXISTS `tokens_confirmation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `token` varchar(64) NOT NULL,
  `email` varchar(255) NOT NULL,
  `id_client` bigint DEFAULT NULL,
  `expiration` datetime NOT NULL,
  `utilise` tinyint DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `id_client` (`id_client`)
) ENGINE=MyISAM AUTO_INCREMENT=163 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `adresse`
--
ALTER TABLE `adresse`
  ADD CONSTRAINT `Adresse_idClient_FK` FOREIGN KEY (`idClient`) REFERENCES `client` (`idClient`);

--
-- Contraintes pour la table `cartebancaire`
--
ALTER TABLE `cartebancaire`
  ADD CONSTRAINT `CarteBancaire_idClient_FK` FOREIGN KEY (`idClient`) REFERENCES `client` (`idClient`);

--
-- Contraintes pour la table `commande`
--
ALTER TABLE `commande`
  ADD CONSTRAINT `commande_ibfk_1` FOREIGN KEY (`idAdresseFacturation`) REFERENCES `adresse` (`idAdresse`),
  ADD CONSTRAINT `Commande_idAdresseLivraison_FK` FOREIGN KEY (`idAdresseLivraison`) REFERENCES `adresse` (`idAdresse`),
  ADD CONSTRAINT `Commande_idClient_FK` FOREIGN KEY (`idClient`) REFERENCES `client` (`idClient`);

--
-- Contraintes pour la table `lignecommande`
--
ALTER TABLE `lignecommande`
  ADD CONSTRAINT `LigneCommande_idCommande_FK` FOREIGN KEY (`idCommande`) REFERENCES `commande` (`idCommande`),
  ADD CONSTRAINT `LigneCommande_idOrigami_FK` FOREIGN KEY (`idOrigami`) REFERENCES `origami` (`idOrigami`);

--
-- Contraintes pour la table `lignepanier`
--
ALTER TABLE `lignepanier`
  ADD CONSTRAINT `LignePanier_idOrigami_FK` FOREIGN KEY (`idOrigami`) REFERENCES `origami` (`idOrigami`),
  ADD CONSTRAINT `LignePanier_idPanier_FK` FOREIGN KEY (`idPanier`) REFERENCES `panier` (`idPanier`);

--
-- Contraintes pour la table `panier`
--
ALTER TABLE `panier`
  ADD CONSTRAINT `Panier_idClient_FK` FOREIGN KEY (`idClient`) REFERENCES `client` (`idClient`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
