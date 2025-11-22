-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : ven. 21 nov. 2025 à 15:08
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
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `adresse`
--

INSERT INTO `adresse` (`idAdresse`, `idClient`, `nom`, `prenom`, `adresse`, `codePostal`, `ville`, `pays`, `telephone`, `instructions`, `societe`, `type`, `dateCreation`) VALUES
(16, 76, 'LOR', 'Philippe', '1 rue de Javel', '75015', 'Paris', 'France', '', '', NULL, 'livraison', '2025-11-21 10:57:18');

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
  `motDePasse` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `client`
--

INSERT INTO `client` (`idClient`, `email`, `motDePasse`, `nom`, `prenom`, `telephone`, `email_confirme`, `token_confirmation`, `token_expires`, `type`, `date_creation`, `session_id`) VALUES
(76, 'lhpp.philippe@gmail.com', '$2y$10$NHRFrLCj02Pqg6t7pI5Lu.uYXdIVestWgQRxlDesVi15Fd44fM17e', 'Client', '', '', 0, NULL, NULL, 'permanent', '2025-11-21 10:56:07', NULL),
(77, 'temp_6920438ea7ce3@origamizen.fr', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2025-11-21 11:48:46', '3cn9g0b79j16v6m84l1lb38avj');

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
  `statut` varchar(20) NOT NULL DEFAULT 'en_attente',
  `statut_paiement` enum('en_attente','payee','echec','annulee','rembourse') DEFAULT 'en_attente',
  `idPaiement` int DEFAULT NULL,
  PRIMARY KEY (`idCommande`),
  KEY `Commande_idClient_FK` (`idClient`),
  KEY `Commande_idAdresseLivraison_FK` (`idAdresseLivraison`),
  KEY `idAdresseFacturation` (`idAdresseFacturation`)
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `commande`
--

INSERT INTO `commande` (`idCommande`, `idClient`, `idAdresseLivraison`, `idAdresseFacturation`, `dateCommande`, `modeReglement`, `delaiLivraison`, `fraisDePort`, `montantTotal`, `statut`, `statut_paiement`, `idPaiement`) VALUES
(69, 76, 16, 16, '2025-11-21 10:57:18', 'PayPal', '2025-11-26', 0, 45, 'en_attente_paiement', 'en_attente', NULL),
(70, 76, 16, 16, '2025-11-21 11:02:32', 'Carte Bancaire (PayP', '2025-11-26', 0, 45, 'payee', 'en_attente', NULL),
(71, 76, 16, 16, '2025-11-21 11:06:59', 'Carte Bancaire (PayP', '2025-11-26', 0, 18, 'payee', 'en_attente', NULL),
(72, 76, 16, 16, '2025-11-21 11:09:25', 'Carte Bancaire (PayP', '2025-11-26', 0, 45, 'payee', 'en_attente', NULL),
(73, 76, 16, 16, '2025-11-21 11:13:41', 'Carte Bancaire (PayP', '2025-11-26', 0, 45, 'payee', 'en_attente', NULL),
(74, 76, 16, 16, '2025-11-21 11:23:40', 'Carte Bancaire (PayP', '2025-11-26', 0, 45, 'payee', 'en_attente', NULL),
(75, 76, 16, 16, '2025-11-21 12:13:58', 'Carte Bancaire (PayP', '2025-11-26', 0, 45, 'payee', 'en_attente', NULL),
(76, 76, 16, 16, '2025-11-21 15:01:10', 'Carte Bancaire (PayP', '2025-11-26', 0, 45, 'payee', 'en_attente', NULL),
(77, 76, 16, 16, '2025-11-21 15:31:07', 'Carte Bancaire (PayP', '2025-11-26', 0, 45, 'payee', 'en_attente', NULL),
(78, 76, 16, 16, '2025-11-21 15:34:21', 'Carte Bancaire (PayP', '2025-11-26', 0, 45, 'payee', 'en_attente', NULL),
(79, 76, 16, 16, '2025-11-21 15:37:55', 'PayPal', '2025-11-26', 0, 45, 'en_attente_paiement', 'en_attente', NULL),
(80, 76, 16, 16, '2025-11-21 15:39:55', 'Carte Bancaire (PayP', '2025-11-26', 0, 45, 'payee', 'en_attente', NULL),
(81, 76, 16, 16, '2025-11-21 15:49:44', 'Carte Bancaire (PayP', '2025-11-26', 0, 45, 'payee', 'en_attente', NULL),
(82, 76, 16, 16, '2025-11-21 15:58:28', 'Carte Bancaire (PayP', '2025-11-26', 0, 45, 'payee', 'en_attente', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=87 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `lignecommande`
--

INSERT INTO `lignecommande` (`idLigneCommande`, `idCommande`, `idOrigami`, `quantite`, `prixUnitaire`) VALUES
(73, 69, 3, 1, 45),
(74, 70, 3, 1, 45),
(75, 71, 2, 1, 18),
(76, 72, 3, 1, 45),
(77, 73, 3, 1, 45),
(78, 74, 3, 1, 45),
(79, 75, 3, 1, 45),
(80, 76, 3, 1, 45),
(81, 77, 3, 1, 45),
(82, 78, 3, 1, 45),
(83, 79, 3, 1, 45),
(84, 80, 3, 1, 45),
(85, 81, 3, 1, 45),
(86, 82, 3, 1, 45);

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
) ENGINE=InnoDB AUTO_INCREMENT=162 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `lignepanier`
--

INSERT INTO `lignepanier` (`idLignePanier`, `idPanier`, `idOrigami`, `quantite`, `prixUnitaire`) VALUES
(145, 65, 2, 1, 18);

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
(5, '1 euro', 'Pièce de monnaie', 'img/euro.jpg', 1);

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
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `paiement`
--

INSERT INTO `paiement` (`idPaiement`, `idCommande`, `montant`, `currency`, `statut`, `methode_paiement`, `reference`, `date_creation`, `date_maj`) VALUES
(13, 28, 1.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763526656_28', '2025-11-19 05:30:56', '2025-11-19 05:30:56'),
(14, 29, 1.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763527367_29', '2025-11-19 05:42:47', '2025-11-19 05:42:47'),
(15, 30, 1.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763527491_30', '2025-11-19 05:44:51', '2025-11-19 05:44:51'),
(16, 31, 1.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763529213_31', '2025-11-19 06:13:33', '2025-11-19 06:13:33'),
(17, 32, 1.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763529731_32', '2025-11-19 06:22:11', '2025-11-19 06:22:11'),
(18, 34, 1.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763530346_34', '2025-11-19 06:32:26', '2025-11-19 06:32:26'),
(19, 35, 1.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763531527_35', '2025-11-19 06:52:07', '2025-11-19 06:52:07'),
(20, 38, 2.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763571580_38', '2025-11-19 17:59:40', '2025-11-19 17:59:40'),
(21, 39, 1.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763571811_39', '2025-11-19 18:03:31', '2025-11-19 18:03:31'),
(22, 40, 1.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763573004_40', '2025-11-19 18:23:24', '2025-11-19 18:23:24'),
(23, 41, 1.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763606937_41', '2025-11-20 03:48:57', '2025-11-20 03:48:57'),
(24, 42, 1.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763608189_42', '2025-11-20 04:09:49', '2025-11-20 04:09:49'),
(25, 43, 1.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763608554_43', '2025-11-20 04:15:54', '2025-11-20 04:15:54'),
(26, 44, 1.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763608863_44', '2025-11-20 04:21:03', '2025-11-20 04:21:03'),
(27, 45, 45.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763611153_45', '2025-11-20 04:59:13', '2025-11-20 04:59:13'),
(28, 46, 18.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763611313_46', '2025-11-20 05:01:53', '2025-11-20 05:01:53'),
(29, 47, 45.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763611607_47', '2025-11-20 05:06:47', '2025-11-20 05:06:47'),
(30, 48, 45.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763612204_48', '2025-11-20 05:16:44', '2025-11-20 05:16:44'),
(31, 49, 45.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763612751_49', '2025-11-20 05:25:51', '2025-11-20 05:25:51'),
(32, 50, 45.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763613023_50', '2025-11-20 05:30:23', '2025-11-20 05:30:23'),
(33, 52, 45.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763614951_52', '2025-11-20 06:02:31', '2025-11-20 06:02:31'),
(34, 54, 45.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763615920_54', '2025-11-20 06:18:40', '2025-11-20 06:18:40'),
(35, 56, 45.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763616694_56', '2025-11-20 06:31:34', '2025-11-20 06:31:34'),
(36, 58, 45.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763617854_58', '2025-11-20 06:50:54', '2025-11-20 06:50:54'),
(37, 59, 45.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763618425_59', '2025-11-20 07:00:25', '2025-11-20 07:00:25'),
(38, 60, 1.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763618710_60', '2025-11-20 07:05:10', '2025-11-20 07:05:10'),
(39, 61, 45.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763619059_61', '2025-11-20 07:10:59', '2025-11-20 07:10:59'),
(40, 64, 45.00, 'EUR', 'payee', 'PayPal', NULL, '2025-11-20 07:26:15', '2025-11-20 07:26:15'),
(41, 65, 1.00, 'EUR', 'payee', 'PayPal', NULL, '2025-11-20 10:17:01', '2025-11-20 10:17:01'),
(42, 66, 1.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763630832_66', '2025-11-20 10:27:12', '2025-11-20 10:27:12'),
(43, 67, 1.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763631054_67', '2025-11-20 10:30:54', '2025-11-20 10:30:54'),
(44, 70, 45.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763719413_70', '2025-11-21 11:03:33', '2025-11-21 11:03:33'),
(45, 71, 18.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763719653_71', '2025-11-21 11:07:33', '2025-11-21 11:07:33'),
(46, 72, 45.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763719798_72', '2025-11-21 11:09:58', '2025-11-21 11:09:58'),
(47, 73, 45.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763720053_73', '2025-11-21 11:14:13', '2025-11-21 11:14:13'),
(48, 74, 45.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763720651_74', '2025-11-21 11:24:11', '2025-11-21 11:24:11'),
(49, 75, 45.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763723684_75', '2025-11-21 12:14:44', '2025-11-21 12:14:44'),
(50, 76, 45.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763733720_76', '2025-11-21 15:02:00', '2025-11-21 15:02:00'),
(51, 77, 45.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763735509_77', '2025-11-21 15:31:49', '2025-11-21 15:31:49'),
(52, 78, 45.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763735705_78', '2025-11-21 15:35:05', '2025-11-21 15:35:05'),
(53, 80, 45.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763736036_80', '2025-11-21 15:40:36', '2025-11-21 15:40:36'),
(54, 81, 45.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763736641_81', '2025-11-21 15:50:41', '2025-11-21 15:50:41'),
(55, 82, 45.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1763737147_82', '2025-11-21 15:59:07', '2025-11-21 15:59:07');

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
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `panier`
--

INSERT INTO `panier` (`idPanier`, `idClient`, `dateModification`) VALUES
(64, 76, '2025-11-21 15:58:28'),
(65, 77, '2025-11-21 11:48:51');

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
) ENGINE=MyISAM AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `tokens_confirmation`
--

INSERT INTO `tokens_confirmation` (`id`, `token`, `email`, `id_client`, `expiration`, `utilise`) VALUES
(97, '85c65d94639acc6ffd66101dff39315c53b154aca4a14585c3f9cc12d0e0fac8', 'lhpp.philippe@gmail.com', 76, '2025-11-21 16:12:56', 1);

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
