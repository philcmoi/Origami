-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : mar. 11 nov. 2025 à 07:06
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
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `adresse`
--

INSERT INTO `adresse` (`idAdresse`, `idClient`, `nom`, `prenom`, `adresse`, `codePostal`, `ville`, `pays`, `telephone`, `instructions`, `societe`, `type`, `dateCreation`) VALUES
(34, 151, 'LOR', 'Philippe', '116 rue de Javel', '75015', 'Paris', 'France', '', '', NULL, 'livraison', '2025-11-11 05:12:17'),
(35, 159, 'LOR', 'Philippe', '116 rue de javel', '75015', 'Paris', 'France', '', '', NULL, 'livraison', '2025-11-11 06:38:56');

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
) ENGINE=InnoDB AUTO_INCREMENT=162 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `client`
--

INSERT INTO `client` (`idClient`, `email`, `motDePasse`, `nom`, `prenom`, `telephone`, `email_confirme`, `token_confirmation`, `token_expires`, `type`, `date_creation`, `session_id`) VALUES
(151, 'lhpp.philippe@gmail.com', '$2y$10$ZgTl8xIZLY8f4Hi//sBBLu/ZKaPpR9Eg2yR6MGQyYTdXILaJx08X.', 'Client', '', '', 0, NULL, NULL, 'permanent', '2025-11-11 05:11:48', NULL),
(159, 'wongfeyhong45@gmail.com', '$2y$10$M8bNTFVgJ341Cz1mqzH8WOLsIXyMBf2WmnGv/op2LeMaSitEIluQ6', 'Client', '', '', 0, NULL, NULL, 'permanent', '2025-11-11 06:38:24', NULL),
(161, 'temp_6912da6f0674d@origamizen.fr', '', 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2025-11-11 07:40:47', 'gh9oh1obmm67tjdkkkrb7fct1c');

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
  PRIMARY KEY (`idCommande`),
  KEY `Commande_idClient_FK` (`idClient`),
  KEY `Commande_idAdresseLivraison_FK` (`idAdresseLivraison`),
  KEY `idAdresseFacturation` (`idAdresseFacturation`)
) ENGINE=InnoDB AUTO_INCREMENT=142 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `commande`
--

INSERT INTO `commande` (`idCommande`, `idClient`, `idAdresseLivraison`, `idAdresseFacturation`, `dateCommande`, `modeReglement`, `delaiLivraison`, `fraisDePort`, `montantTotal`, `statut`, `statut_paiement`, `idPaiement`) VALUES
(129, 151, 34, 34, '2025-11-11 05:12:17', 'PayPal', '2025-11-16', 0, 1, 'en_attente_paiement', 'en_attente', NULL),
(130, 151, 34, 34, '2025-11-11 05:22:12', 'PayPal', '2025-11-16', 0, 1, 'en_attente_paiement', 'en_attente', NULL),
(131, 151, 34, 34, '2025-11-11 05:28:39', 'PayPal', '2025-11-16', 0, 1, 'en_attente_paiement', 'en_attente', NULL),
(132, 151, 34, 34, '2025-11-11 05:39:41', 'PayPal', '2025-11-16', 0, 1, 'en_attente_paiement', 'en_attente', NULL),
(133, 151, 34, 34, '2025-11-11 05:52:26', 'Carte Bancaire (PayP', '2025-11-16', 0, 1, 'payee', 'en_attente', NULL),
(134, 151, 34, 34, '2025-11-11 06:06:38', 'PayPal', '2025-11-16', 0, 1, 'en_attente_paiement', 'en_attente', NULL),
(135, 151, 34, 34, '2025-11-11 06:10:01', 'PayPal', '2025-11-16', 0, 1, 'en_attente_paiement', 'en_attente', NULL),
(136, 151, 34, 34, '2025-11-11 06:13:06', 'PayPal', '2025-11-16', 0, 1, 'en_attente_paiement', 'en_attente', NULL),
(137, 151, 34, 34, '2025-11-11 06:16:13', 'PayPal', '2025-11-16', 0, 1, 'en_attente_paiement', 'en_attente', NULL),
(138, 151, 34, 34, '2025-11-11 06:36:55', 'PayPal', '2025-11-16', 0, 1, 'en_attente_paiement', 'en_attente', NULL),
(139, 159, 35, 35, '2025-11-11 06:38:56', 'PayPal', '2025-11-16', 0, 1, 'en_attente_paiement', 'en_attente', NULL),
(140, 159, 35, 35, '2025-11-11 06:40:16', 'PayPal', '2025-11-16', 0, 1, 'payee', 'en_attente', NULL),
(141, 151, 34, 34, '2025-11-11 06:50:25', 'Carte Bancaire (PayP', '2025-11-16', 0, 1, 'payee', 'en_attente', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=148 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `lignecommande`
--

INSERT INTO `lignecommande` (`idLigneCommande`, `idCommande`, `idOrigami`, `quantite`, `prixUnitaire`) VALUES
(135, 129, 5, 1, 1),
(136, 130, 5, 1, 1),
(137, 131, 5, 1, 1),
(138, 132, 5, 1, 1),
(139, 133, 5, 1, 1),
(140, 134, 5, 1, 1),
(141, 135, 5, 1, 1),
(142, 136, 5, 1, 1),
(143, 137, 5, 1, 1),
(144, 138, 5, 1, 1),
(145, 139, 5, 1, 1),
(146, 140, 5, 1, 1),
(147, 141, 5, 1, 1);

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
) ENGINE=InnoDB AUTO_INCREMENT=288 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `paiement`
--

INSERT INTO `paiement` (`idPaiement`, `idCommande`, `montant`, `currency`, `statut`, `methode_paiement`, `reference`, `date_creation`, `date_maj`) VALUES
(1, 133, 1.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1762836779_133', '2025-11-11 05:52:59', '2025-11-11 05:52:59'),
(2, 141, 1.00, 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1762840311_141', '2025-11-11 06:51:51', '2025-11-11 06:51:51');

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
) ENGINE=InnoDB AUTO_INCREMENT=144 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `panier`
--

INSERT INTO `panier` (`idPanier`, `idClient`, `dateModification`) VALUES
(134, 151, '2025-11-11 06:50:25'),
(142, 159, '2025-11-11 06:40:16');

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
) ENGINE=MyISAM AUTO_INCREMENT=156 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
