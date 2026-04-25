-- phpMyAdmin SQL Dump
-- version 5.1.1deb5ubuntu1
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:3306
-- Généré le : sam. 25 avr. 2026 à 14:57
-- Version du serveur : 8.0.45-0ubuntu0.22.04.1
-- Version de PHP : 8.1.2-1ubuntu2.23

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
-- Structure de la table `Administrateur`
--

CREATE TABLE `Administrateur` (
  `idAdmin` bigint NOT NULL,
  `email` varchar(50) NOT NULL,
  `motDePasse` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `Administrateur`
--

INSERT INTO `Administrateur` (`idAdmin`, `email`, `motDePasse`) VALUES
(1, 'lhpp.philippe@gmail.com', '$2y$10$3xC95pryxvZKeGjJ4FbYVO6VI.PJtRaLWeO7fE.jdxJ0tYC8IqR6S');

-- --------------------------------------------------------

--
-- Structure de la table `Adresse`
--

CREATE TABLE `Adresse` (
  `idAdresse` bigint NOT NULL,
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
  `dateCreation` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `Adresse`
--

INSERT INTO `Adresse` (`idAdresse`, `idClient`, `nom`, `prenom`, `adresse`, `codePostal`, `ville`, `pays`, `telephone`, `instructions`, `societe`, `type`, `dateCreation`) VALUES
(50, 844, 'Lor', 'Philippe', '116 rue de Javel', '75015', 'Paris', 'France', '+33644982807', '', NULL, 'livraison', '2026-04-01 04:26:22');

-- --------------------------------------------------------

--
-- Structure de la table `cartebancaire`
--

CREATE TABLE `cartebancaire` (
  `idCarteBancaire` bigint NOT NULL,
  `idClient` bigint NOT NULL,
  `nomTitulaire` varchar(100) NOT NULL,
  `derniersChiffres` varchar(4) NOT NULL,
  `dateExpiration` date NOT NULL,
  `typeCarte` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `Client`
--

CREATE TABLE `Client` (
  `idClient` bigint NOT NULL,
  `email` varchar(50) NOT NULL,
  `motDePasse` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `nom` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `prenom` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email_confirme` tinyint(1) DEFAULT '0',
  `token_confirmation` varchar(64) DEFAULT NULL,
  `token_expires` datetime DEFAULT NULL,
  `type` enum('temporaire','permanent') DEFAULT 'temporaire',
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `session_id` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `Client`
--

INSERT INTO `Client` (`idClient`, `email`, `motDePasse`, `nom`, `prenom`, `telephone`, `email_confirme`, `token_confirmation`, `token_expires`, `type`, `date_creation`, `session_id`) VALUES
(844, 'lhpp.philippe@gmail.com', '$2y$10$V1KGuchuMcpIp0q7vv0TsOWfh5ULXl84fv5kFhe9iyZ8iQazz0hoG', 'Client', '', '', 0, NULL, NULL, 'permanent', '2026-04-01 04:25:48', NULL),
(951, 'temp_69ec1fb67c317@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-25 01:58:14', 'su2ainsf9fc072uo6geoqv7o2i'),
(952, 'temp_69ec25ab468a4@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-25 02:23:39', '8v0jvhdl4qcfb1kr66vvajd88q'),
(955, 'temp_69ec35cd4966d@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-25 03:32:29', '5572nhekqsm97o9lvtn098t6mv'),
(957, 'temp_69ec381fc9d7f@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-25 03:42:23', 'fdgkjevicmanirie2vekkqcjab'),
(961, 'temp_69ec3d7d6feb9@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-25 04:05:17', 'r8pau342fgldo8s2s58t4ku224'),
(962, 'temp_69ec3e0a9a918@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-25 04:07:38', 'o708q1h6ccp73o7vqpgjm125po'),
(963, 'temp_69ecb3c290ab2@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-25 12:29:54', 'uuotvm7695gl3a82gd2u8cne61'),
(964, 'temp_69ecba2909e73@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-25 12:57:13', 'ghhu974l84ro95518md49a9r12'),
(965, 'temp_69ecba2980c73@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-25 12:57:13', '0e0ed6nhtdmp8ktg41130f9epu'),
(966, 'temp_69ecc39333d2d@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-25 13:37:23', 'pi1617h689ivvifgeqvjosheh7'),
(967, 'temp_69ecc43e7e0fd@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-25 13:40:14', 'f3o3plot1m2d6i3c23ljqil660'),
(968, 'temp_69ecc47028f4d@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-25 13:41:04', 'aptikgrnfdnvt9g2e4j44oj9eg'),
(969, 'temp_69ecc676d1022@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-25 13:49:42', 'qeutla97k6h5d7o7o2v1t18okv'),
(970, 'temp_69ecc8aa04bb5@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-25 13:59:06', '9uu6mmkeof6kd264e9g0l9u31j'),
(971, 'temp_69ecca07506e5@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-25 14:04:55', 'uvobjaf1509ulv633mioi6op19'),
(972, 'temp_69eccaf5abba6@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-25 14:08:53', 'gm9irg7tgr252a7fp7o4snf4p2'),
(973, 'temp_69eccc64aa0e5@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-25 14:15:00', 'u2kh07p743vbrtiktep5qob5tf'),
(975, 'temp_69eccf33c4848@origamizen.fr', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-25 14:26:59', 'a74v7dgh3jsrte0i1q6k0hnkam'),
(976, 'temp_69ecd108bb75a@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-25 14:34:48', '0kjehqfuji1ailb3vq6uagfod0'),
(977, 'temp_69ecd141df3ae@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-25 14:35:45', 'qa5rao6umdale532guonu9627d'),
(979, 'temp_69ecd2b4eaf03@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-25 14:41:56', '197210eerlud90k0egdq54pqan'),
(980, 'temp_69ecd3d922899@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-25 14:46:49', 'v862c802eubsu01jmepmbhi1sc');

-- --------------------------------------------------------

--
-- Structure de la table `codeconfirmation`
--

CREATE TABLE `codeconfirmation` (
  `id` int NOT NULL,
  `email` varchar(255) NOT NULL,
  `code` varchar(8) NOT NULL,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `utilise` tinyint(1) DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `Commande`
--

CREATE TABLE `Commande` (
  `idCommande` bigint NOT NULL,
  `idClient` bigint NOT NULL,
  `idAdresseLivraison` bigint NOT NULL,
  `idAdresseFacturation` bigint DEFAULT NULL,
  `dateCommande` datetime NOT NULL,
  `modeReglement` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'CB',
  `delaiLivraison` date NOT NULL,
  `fraisDePort` double NOT NULL,
  `montantTotal` double NOT NULL,
  `statut` varchar(20) NOT NULL DEFAULT 'en_attente',
  `statut_paiement` enum('en_attente','payee','echec','annulee','rembourse') DEFAULT 'en_attente',
  `idPaiement` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `Commande`
--

INSERT INTO `Commande` (`idCommande`, `idClient`, `idAdresseLivraison`, `idAdresseFacturation`, `dateCommande`, `modeReglement`, `delaiLivraison`, `fraisDePort`, `montantTotal`, `statut`, `statut_paiement`, `idPaiement`) VALUES
(158, 844, 50, 50, '2026-04-01 04:26:22', 'PayPal', '2026-04-06', 0, 1, 'payee', 'en_attente', NULL),
(159, 844, 50, 50, '2026-04-01 04:43:28', 'PayPal', '2026-04-06', 0, 1, 'payee', 'en_attente', NULL),
(160, 844, 50, 50, '2026-04-01 04:45:19', 'PayPal', '2026-04-06', 0, 47, 'payee', 'en_attente', NULL),
(161, 844, 50, 50, '2026-04-01 04:52:25', 'Carte Bancaire (PayPal)', '2026-04-06', 0, 66, 'payee', 'en_attente', NULL),
(162, 844, 50, 50, '2026-04-01 05:12:30', 'PayPal', '2026-04-06', 0, 66, 'payee', 'en_attente', NULL),
(163, 844, 50, 50, '2026-04-01 06:59:15', 'PayPal', '2026-04-06', 0, 47, 'en_attente_paiement', 'en_attente', NULL),
(164, 844, 50, 50, '2026-04-01 07:23:14', 'PayPal', '2026-04-06', 0, 45, 'en_attente_paiement', 'en_attente', NULL),
(165, 844, 50, 50, '2026-04-01 07:25:33', 'PayPal', '2026-04-06', 0, 34, 'en_attente_paiement', 'en_attente', NULL),
(166, 844, 50, 50, '2026-04-01 07:27:41', 'PayPal', '2026-04-06', 0, 65, 'en_attente_paiement', 'en_attente', NULL),
(167, 844, 50, 50, '2026-04-01 07:33:04', 'PayPal', '2026-04-06', 0, 47, 'payee', 'en_attente', NULL),
(168, 844, 50, 50, '2026-04-01 14:47:11', 'PayPal', '2026-04-06', 0, 47, 'payee', 'en_attente', NULL),
(169, 844, 50, 50, '2026-04-01 14:59:40', 'PayPal', '2026-04-06', 0, 65, 'payee', 'en_attente', NULL),
(170, 844, 50, 50, '2026-04-23 05:05:52', 'PayPal', '2026-04-28', 0, 1, 'en_attente_paiement', 'en_attente', NULL),
(171, 844, 50, 50, '2026-04-24 02:28:53', 'PayPal', '2026-04-29', 0, 84, 'payee', 'en_attente', NULL),
(172, 844, 50, 50, '2026-04-25 03:17:25', 'PayPal', '2026-04-30', 0, 45, 'payee', 'en_attente', NULL),
(173, 844, 50, 50, '2026-04-25 14:39:47', 'PayPal', '2026-04-30', 0, 81, 'payee', 'en_attente', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `LigneCommande`
--

CREATE TABLE `LigneCommande` (
  `idLigneCommande` bigint NOT NULL,
  `idCommande` bigint NOT NULL,
  `idOrigami` bigint NOT NULL,
  `quantite` int NOT NULL,
  `prixUnitaire` double NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `LigneCommande`
--

INSERT INTO `LigneCommande` (`idLigneCommande`, `idCommande`, `idOrigami`, `quantite`, `prixUnitaire`) VALUES
(188, 158, 5, 1, 1),
(189, 159, 5, 1, 1),
(190, 160, 3, 1, 45),
(191, 160, 5, 2, 1),
(192, 161, 2, 1, 18),
(193, 161, 3, 1, 45),
(194, 161, 5, 3, 1),
(195, 162, 2, 1, 18),
(196, 162, 3, 1, 45),
(197, 162, 5, 3, 1),
(198, 163, 3, 1, 45),
(199, 163, 5, 2, 1),
(200, 164, 3, 1, 45),
(201, 165, 5, 2, 1),
(202, 165, 4, 1, 32),
(203, 166, 2, 1, 18),
(204, 166, 3, 1, 45),
(205, 166, 5, 2, 1),
(206, 167, 3, 1, 45),
(207, 167, 5, 2, 1),
(208, 168, 3, 1, 45),
(209, 168, 5, 2, 1),
(210, 169, 3, 1, 45),
(211, 169, 2, 1, 18),
(212, 169, 5, 2, 1),
(213, 170, 5, 1, 1),
(214, 171, 5, 1, 1),
(215, 171, 15, 2, 10),
(216, 171, 2, 1, 18),
(217, 171, 3, 1, 45),
(218, 172, 3, 1, 45),
(219, 173, 2, 2, 18),
(220, 173, 3, 1, 45);

-- --------------------------------------------------------

--
-- Structure de la table `LignePanier`
--

CREATE TABLE `LignePanier` (
  `idLignePanier` bigint NOT NULL,
  `idPanier` bigint NOT NULL,
  `idOrigami` bigint NOT NULL,
  `quantite` int NOT NULL DEFAULT '1',
  `prixUnitaire` double NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `LignePanier`
--

INSERT INTO `LignePanier` (`idLignePanier`, `idPanier`, `idOrigami`, `quantite`, `prixUnitaire`) VALUES
(485, 218, 3, 3, 45),
(486, 218, 2, 2, 18),
(487, 219, 3, 1, 45),
(488, 219, 2, 1, 18),
(491, 220, 1, 2, 24),
(492, 220, 3, 1, 45),
(498, 222, 3, 1, 45),
(503, 224, 15, 1, 10),
(504, 224, 5, 2, 1),
(505, 225, 2, 1, 18),
(506, 225, 3, 1, 45),
(507, 226, 3, 1, 45),
(508, 226, 2, 2, 18),
(509, 227, 3, 2, 45),
(510, 227, 2, 2, 18),
(511, 228, 3, 1, 45),
(512, 228, 2, 1, 18),
(513, 228, 15, 1, 10),
(514, 229, 3, 2, 45),
(515, 230, 3, 1, 45),
(516, 230, 2, 1, 18),
(519, 232, 3, 1, 45),
(520, 232, 2, 1, 18),
(524, 234, 15, 1, 10);

-- --------------------------------------------------------

--
-- Structure de la table `Origami`
--

CREATE TABLE `Origami` (
  `idOrigami` bigint NOT NULL,
  `nom` varchar(50) NOT NULL,
  `description` varchar(300) NOT NULL,
  `photo` varchar(300) NOT NULL,
  `prixHorsTaxe` double NOT NULL,
  `date_modification` datetime DEFAULT NULL,
  `niveau_difficulte` varchar(50) DEFAULT NULL,
  `visible` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `Origami`
--

INSERT INTO `Origami` (`idOrigami`, `nom`, `description`, `photo`, `prixHorsTaxe`, `date_modification`, `niveau_difficulte`, `visible`) VALUES
(1, 'La grue Élégante', 'Symbole de paix et de longévité, cette grue est pliée avec un papier washi traditionnel.', 'img/couple de sygnes.jpg', 24, NULL, NULL, 1),
(2, 'Fleur de Cerisier', 'Inspirée des sakura japonais, cette fleur délicate apporte une touche de printemps éternel.', 'img/flower.jpg', 18, NULL, NULL, 1),
(3, 'Dragon Majestueux', 'Une création complexe et impressionnante, symbole de puissance et de sagesse.', 'img/dragon.png', 45, NULL, NULL, 1),
(4, 'Éventail Traditionnel', 'Accessoire élégant et fonctionnel, plié avec un papier aux motifs traditionnels.', 'img/eventail.jpg', 32, NULL, NULL, 1),
(5, '1 euro', 'Pièce de monnaie', 'img/euro.jpg', 1, NULL, NULL, 1),
(15, 'AZERTY', 'AZERTY', 'uploads/origami/produit_69eacdff542cd_20260424_015719.jpg', 10, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Structure de la table `Paiement`
--

CREATE TABLE `Paiement` (
  `idPaiement` int NOT NULL,
  `idCommande` int NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'EUR',
  `statut` varchar(20) DEFAULT 'en_attente',
  `methode_paiement` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'carte',
  `reference` varchar(100) DEFAULT NULL,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_maj` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `Paiement`
--

INSERT INTO `Paiement` (`idPaiement`, `idCommande`, `montant`, `currency`, `statut`, `methode_paiement`, `reference`, `date_creation`, `date_maj`) VALUES
(104, 136, '1.00', 'EUR', 'payee', 'PayPal', NULL, '2025-12-06 06:11:19', '2025-12-06 06:11:19'),
(105, 137, '33.00', 'EUR', 'payee', 'PayPal', NULL, '2025-12-06 06:37:55', '2025-12-06 06:37:55'),
(106, 138, '33.00', 'EUR', 'payee', 'PayPal', NULL, '2025-12-06 06:43:25', '2025-12-06 06:43:25'),
(123, 158, '34.00', 'EUR', 'payee', 'PayPal', NULL, '2026-03-03 04:20:57', '2026-03-03 04:20:57'),
(124, 159, '66.00', 'EUR', 'payee', 'PayPal', NULL, '2026-03-03 04:22:24', '2026-03-03 04:22:24'),
(125, 160, '81.00', 'EUR', 'payee', 'PayPal', NULL, '2026-03-03 04:25:47', '2026-03-03 04:25:47'),
(126, 161, '18.00', 'EUR', 'payee', 'PayPal', NULL, '2026-03-11 03:08:10', '2026-03-11 03:08:10'),
(127, 139, '11.00', 'EUR', 'payee', 'PayPal', NULL, '2026-03-11 05:35:07', '2026-03-11 05:35:07'),
(128, 140, '18.00', 'EUR', 'payee', 'PayPal', NULL, '2026-03-12 03:24:54', '2026-03-12 03:24:54'),
(129, 141, '45.00', 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1773286005_141', '2026-03-12 03:26:45', '2026-03-12 03:26:45'),
(130, 142, '1.00', 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1773374737_142', '2026-03-13 04:05:37', '2026-03-13 04:05:37'),
(131, 143, '33.00', 'EUR', 'payee', 'PayPal', NULL, '2026-03-13 04:20:30', '2026-03-13 04:20:30'),
(132, 144, '33.00', 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1773375874_144', '2026-03-13 04:24:34', '2026-03-13 04:24:34'),
(133, 145, '77.00', 'EUR', 'payee', 'PayPal', NULL, '2026-03-13 04:36:36', '2026-03-13 04:36:36'),
(134, 146, '33.00', 'EUR', 'payee', 'PayPal', NULL, '2026-03-13 04:40:05', '2026-03-13 04:40:05'),
(135, 147, '19.00', 'EUR', 'payee', 'PayPal', NULL, '2026-03-13 04:44:26', '2026-03-13 04:44:26'),
(136, 148, '33.00', 'EUR', 'payee', 'PayPal', NULL, '2026-03-13 04:48:36', '2026-03-13 04:48:36'),
(137, 149, '1.00', 'EUR', 'payee', 'PayPal', NULL, '2026-03-16 17:09:15', '2026-03-16 17:09:15'),
(138, 150, '1.00', 'EUR', 'payee', 'PayPal', NULL, '2026-03-27 03:33:40', '2026-03-27 03:33:40'),
(139, 151, '1.00', 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1774585094_151', '2026-03-27 04:18:14', '2026-03-27 04:18:14'),
(140, 152, '1.00', 'EUR', 'payee', 'PayPal', NULL, '2026-03-27 04:24:56', '2026-03-27 04:24:56'),
(141, 153, '1.00', 'EUR', 'payee', 'PayPal', NULL, '2026-03-27 04:40:51', '2026-03-27 04:40:51'),
(142, 154, '2.00', 'EUR', 'payee', 'PayPal', NULL, '2026-03-27 05:07:10', '2026-03-27 05:07:10'),
(143, 155, '1.00', 'EUR', 'payee', 'PayPal', NULL, '2026-03-30 04:23:46', '2026-03-30 04:23:46'),
(144, 156, '1.00', 'EUR', 'payee', 'PayPal', NULL, '2026-03-30 04:40:26', '2026-03-30 04:40:26'),
(145, 157, '1.00', 'EUR', 'payee', 'PayPal', NULL, '2026-03-30 05:09:58', '2026-03-30 05:09:58'),
(146, 158, '1.00', 'EUR', 'payee', 'PayPal', NULL, '2026-04-01 04:27:10', '2026-04-01 04:27:10'),
(147, 159, '1.00', 'EUR', 'payee', 'PayPal', NULL, '2026-04-01 04:43:53', '2026-04-01 04:43:53'),
(148, 160, '47.00', 'EUR', 'payee', 'PayPal', NULL, '2026-04-01 04:45:58', '2026-04-01 04:45:58'),
(149, 161, '66.00', 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1775019214_161', '2026-04-01 04:53:34', '2026-04-01 04:53:34'),
(150, 162, '66.00', 'EUR', 'payee', 'PayPal', NULL, '2026-04-01 05:13:00', '2026-04-01 05:13:00'),
(151, 167, '47.00', 'EUR', 'payee', 'PayPal', NULL, '2026-04-01 07:34:13', '2026-04-01 07:34:13'),
(152, 168, '47.00', 'EUR', 'payee', 'PayPal', NULL, '2026-04-01 14:48:33', '2026-04-01 14:48:33'),
(153, 169, '65.00', 'EUR', 'payee', 'PayPal', NULL, '2026-04-01 15:00:26', '2026-04-01 15:00:26'),
(154, 171, '84.00', 'EUR', 'payee', 'PayPal', NULL, '2026-04-24 02:29:53', '2026-04-24 02:29:53'),
(155, 172, '45.00', 'EUR', 'payee', 'PayPal', NULL, '2026-04-25 03:18:01', '2026-04-25 03:18:01'),
(156, 173, '81.00', 'EUR', 'payee', 'PayPal', NULL, '2026-04-25 14:40:17', '2026-04-25 14:40:17');

-- --------------------------------------------------------

--
-- Structure de la table `Panier`
--

CREATE TABLE `Panier` (
  `idPanier` bigint NOT NULL,
  `idClient` bigint NOT NULL,
  `dateModification` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `Panier`
--

INSERT INTO `Panier` (`idPanier`, `idClient`, `dateModification`) VALUES
(192, 844, '2026-04-25 14:39:47'),
(215, 951, '2026-04-25 02:21:01'),
(216, 952, '2026-04-25 02:24:18'),
(218, 955, '2026-04-25 03:33:08'),
(219, 957, '2026-04-25 03:42:33'),
(220, 961, '2026-04-25 04:06:20'),
(221, 962, '2026-04-25 04:08:03'),
(222, 963, '2026-04-25 12:37:47'),
(223, 965, '2026-04-25 13:11:18'),
(224, 966, '2026-04-25 13:37:58'),
(225, 967, '2026-04-25 13:40:26'),
(226, 969, '2026-04-25 13:51:40'),
(227, 970, '2026-04-25 14:01:15'),
(228, 971, '2026-04-25 14:05:11'),
(229, 972, '2026-04-25 14:09:01'),
(230, 973, '2026-04-25 14:15:12'),
(232, 976, '2026-04-25 14:34:57'),
(234, 979, '2026-04-25 14:42:50'),
(235, 980, '2026-04-25 14:47:09');

-- --------------------------------------------------------

--
-- Structure de la table `tokens_confirmation`
--

CREATE TABLE `tokens_confirmation` (
  `id` int NOT NULL,
  `token` varchar(64) NOT NULL,
  `email` varchar(255) NOT NULL,
  `id_client` bigint DEFAULT NULL,
  `expiration` datetime NOT NULL,
  `utilise` tinyint DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `Administrateur`
--
ALTER TABLE `Administrateur`
  ADD PRIMARY KEY (`idAdmin`);

--
-- Index pour la table `Adresse`
--
ALTER TABLE `Adresse`
  ADD PRIMARY KEY (`idAdresse`),
  ADD KEY `Adresse_idClient_FK` (`idClient`);

--
-- Index pour la table `cartebancaire`
--
ALTER TABLE `cartebancaire`
  ADD PRIMARY KEY (`idCarteBancaire`),
  ADD KEY `CarteBancaire_idClient_FK` (`idClient`);

--
-- Index pour la table `Client`
--
ALTER TABLE `Client`
  ADD PRIMARY KEY (`idClient`),
  ADD KEY `idx_client_type` (`type`),
  ADD KEY `idx_client_session` (`session_id`),
  ADD KEY `idx_client_date` (`date_creation`);

--
-- Index pour la table `codeconfirmation`
--
ALTER TABLE `codeconfirmation`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `Commande`
--
ALTER TABLE `Commande`
  ADD PRIMARY KEY (`idCommande`),
  ADD KEY `Commande_idClient_FK` (`idClient`),
  ADD KEY `Commande_idAdresseLivraison_FK` (`idAdresseLivraison`),
  ADD KEY `idAdresseFacturation` (`idAdresseFacturation`);

--
-- Index pour la table `LigneCommande`
--
ALTER TABLE `LigneCommande`
  ADD PRIMARY KEY (`idLigneCommande`),
  ADD KEY `LigneCommande_idCommande_FK` (`idCommande`),
  ADD KEY `LigneCommande_idOrigami_FK` (`idOrigami`);

--
-- Index pour la table `LignePanier`
--
ALTER TABLE `LignePanier`
  ADD PRIMARY KEY (`idLignePanier`),
  ADD KEY `LignePanier_idPanier_FK` (`idPanier`),
  ADD KEY `LignePanier_idOrigami_FK` (`idOrigami`);

--
-- Index pour la table `Origami`
--
ALTER TABLE `Origami`
  ADD PRIMARY KEY (`idOrigami`);

--
-- Index pour la table `Paiement`
--
ALTER TABLE `Paiement`
  ADD PRIMARY KEY (`idPaiement`),
  ADD KEY `idCommande` (`idCommande`);

--
-- Index pour la table `Panier`
--
ALTER TABLE `Panier`
  ADD PRIMARY KEY (`idPanier`),
  ADD UNIQUE KEY `idClient` (`idClient`);

--
-- Index pour la table `tokens_confirmation`
--
ALTER TABLE `tokens_confirmation`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `id_client` (`id_client`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `Administrateur`
--
ALTER TABLE `Administrateur`
  MODIFY `idAdmin` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `Adresse`
--
ALTER TABLE `Adresse`
  MODIFY `idAdresse` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT pour la table `cartebancaire`
--
ALTER TABLE `cartebancaire`
  MODIFY `idCarteBancaire` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `Client`
--
ALTER TABLE `Client`
  MODIFY `idClient` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=981;

--
-- AUTO_INCREMENT pour la table `codeconfirmation`
--
ALTER TABLE `codeconfirmation`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `Commande`
--
ALTER TABLE `Commande`
  MODIFY `idCommande` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=174;

--
-- AUTO_INCREMENT pour la table `LigneCommande`
--
ALTER TABLE `LigneCommande`
  MODIFY `idLigneCommande` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=221;

--
-- AUTO_INCREMENT pour la table `LignePanier`
--
ALTER TABLE `LignePanier`
  MODIFY `idLignePanier` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=526;

--
-- AUTO_INCREMENT pour la table `Origami`
--
ALTER TABLE `Origami`
  MODIFY `idOrigami` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT pour la table `Paiement`
--
ALTER TABLE `Paiement`
  MODIFY `idPaiement` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=157;

--
-- AUTO_INCREMENT pour la table `Panier`
--
ALTER TABLE `Panier`
  MODIFY `idPanier` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=236;

--
-- AUTO_INCREMENT pour la table `tokens_confirmation`
--
ALTER TABLE `tokens_confirmation`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=199;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `Adresse`
--
ALTER TABLE `Adresse`
  ADD CONSTRAINT `Adresse_idClient_FK` FOREIGN KEY (`idClient`) REFERENCES `Client` (`idClient`);

--
-- Contraintes pour la table `cartebancaire`
--
ALTER TABLE `cartebancaire`
  ADD CONSTRAINT `CarteBancaire_idClient_FK` FOREIGN KEY (`idClient`) REFERENCES `Client` (`idClient`);

--
-- Contraintes pour la table `Commande`
--
ALTER TABLE `Commande`
  ADD CONSTRAINT `Commande_ibfk_1` FOREIGN KEY (`idAdresseFacturation`) REFERENCES `Adresse` (`idAdresse`),
  ADD CONSTRAINT `Commande_idAdresseLivraison_FK` FOREIGN KEY (`idAdresseLivraison`) REFERENCES `Adresse` (`idAdresse`),
  ADD CONSTRAINT `Commande_idClient_FK` FOREIGN KEY (`idClient`) REFERENCES `Client` (`idClient`);

--
-- Contraintes pour la table `LigneCommande`
--
ALTER TABLE `LigneCommande`
  ADD CONSTRAINT `LigneCommande_idCommande_FK` FOREIGN KEY (`idCommande`) REFERENCES `Commande` (`idCommande`),
  ADD CONSTRAINT `LigneCommande_idOrigami_FK` FOREIGN KEY (`idOrigami`) REFERENCES `Origami` (`idOrigami`);

--
-- Contraintes pour la table `LignePanier`
--
ALTER TABLE `LignePanier`
  ADD CONSTRAINT `LignePanier_idOrigami_FK` FOREIGN KEY (`idOrigami`) REFERENCES `Origami` (`idOrigami`),
  ADD CONSTRAINT `LignePanier_idPanier_FK` FOREIGN KEY (`idPanier`) REFERENCES `Panier` (`idPanier`);

--
-- Contraintes pour la table `Panier`
--
ALTER TABLE `Panier`
  ADD CONSTRAINT `Panier_idClient_FK` FOREIGN KEY (`idClient`) REFERENCES `Client` (`idClient`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
