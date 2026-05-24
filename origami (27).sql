-- phpMyAdmin SQL Dump
-- version 5.1.1deb5ubuntu1
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:3306
-- Généré le : sam. 09 mai 2026 à 05:01
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
(51, 992, 'Lor', 'Philippe', '116 rue de Javel', '75015', 'Paris', 'France', '+33644982807', '', NULL, 'livraison', '2026-04-27 02:05:54');

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
(992, 'lhpp.philippe@gmail.com', '$2y$10$ITAxRLM/plKjjeLcG8Si3eiRngiyGOVC5rhaydxWCFzcQd7L3Oj9O', 'Client', '', '', 0, NULL, NULL, 'permanent', '2026-04-27 02:05:18', NULL),
(1026, 'temp_69feae2e367cd@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-05-09 03:46:54', 'ikm293v6cep96pk7e9rcg6o2b1'),
(1027, 'temp_69feb196b5f97@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-05-09 04:01:26', 'dmv8tcs0iuvjg3r4mt2edbv9c5'),
(1028, 'temp_69feb33b9a7ee@YoukiAndCo.fr', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-05-09 04:08:27', 'iq53uj6rdn5vcfb9prqte6630p'),
(1029, 'temp_69feb60c1cd9f@YoukiAndCo.fr', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-05-09 04:20:28', 'sdnpiec2lp3mjdpia9s1jbtcm8'),
(1030, 'temp_69febabb9ba16@YoukiAndCo.fr', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-05-09 04:40:27', 'ad0lne8669ako1s2r499o40upp');

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
(174, 992, 51, 51, '2026-04-27 02:05:54', 'PayPal', '2026-05-02', 0, 18, 'en_attente_paiement', 'en_attente', NULL),
(175, 992, 51, 51, '2026-04-27 02:15:30', 'PayPal', '2026-05-02', 0, 63, 'payee', 'en_attente', NULL),
(176, 992, 51, 51, '2026-05-09 04:43:53', 'PayPal', '2026-05-14', 0, 87, 'payee', 'en_attente', NULL),
(177, 992, 51, 51, '2026-05-09 04:57:57', 'PayPal', '2026-05-14', 0, 120, 'payee', 'en_attente', NULL),
(178, 992, 51, 51, '2026-05-09 04:59:30', 'Carte Bancaire (PayPal)', '2026-05-14', 0, 120, 'payee', 'en_attente', NULL);

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
(221, 174, 2, 1, 18),
(222, 175, 3, 1, 45),
(223, 175, 2, 1, 18),
(224, 176, 1, 1, 24),
(225, 176, 2, 1, 18),
(226, 176, 3, 1, 45),
(227, 177, 1, 1, 24),
(228, 177, 2, 1, 18),
(229, 177, 3, 1, 45),
(230, 177, 4, 1, 32),
(231, 177, 5, 1, 1),
(232, 178, 1, 1, 24),
(233, 178, 2, 1, 18),
(234, 178, 3, 1, 45),
(235, 178, 4, 1, 32),
(236, 178, 5, 1, 1);

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
(546, 242, 2, 1, 18),
(547, 242, 1, 1, 24),
(548, 242, 5, 1, 1),
(558, 245, 2, 1, 18),
(559, 245, 4, 1, 32),
(560, 245, 5, 1, 1);

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
(5, '1 euro', 'Pièce de monnaie', 'img/euro.jpg', 1, NULL, NULL, 1);

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
(157, 175, '63.00', 'EUR', 'payee', 'PayPal', NULL, '2026-04-27 02:15:46', '2026-04-27 02:15:46'),
(158, 176, '87.00', 'EUR', 'payee', 'PayPal', NULL, '2026-05-09 04:44:59', '2026-05-09 04:44:59'),
(159, 176, '87.00', 'EUR', 'payee', 'PayPal', NULL, '2026-05-09 04:53:41', '2026-05-09 04:53:41'),
(160, 177, '120.00', 'EUR', 'payee', 'PayPal', NULL, '2026-05-09 04:58:24', '2026-05-09 04:58:24'),
(161, 178, '120.00', 'EUR', 'payee', 'Carte Bancaire (PayPal)', 'SIMU_WAMP_1778302822_178', '2026-05-09 05:00:22', '2026-05-09 05:00:22');

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
(239, 992, '2026-05-09 04:59:30'),
(242, 1026, '2026-05-09 03:48:21'),
(243, 1028, '2026-05-09 04:08:54'),
(244, 1029, '2026-05-09 04:20:45'),
(245, 1030, '2026-05-09 04:40:46');

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
-- Déchargement des données de la table `tokens_confirmation`
--

INSERT INTO `tokens_confirmation` (`id`, `token`, `email`, `id_client`, `expiration`, `utilise`) VALUES
(203, 'f54017de73ea120785944823b20a61cfa8eb14993e593b233f66e8f4d5e5f4e6', 'lhpp.philippe@gmail.com', 992, '2026-05-09 05:14:05', 1);

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
  MODIFY `idAdresse` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT pour la table `cartebancaire`
--
ALTER TABLE `cartebancaire`
  MODIFY `idCarteBancaire` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `Client`
--
ALTER TABLE `Client`
  MODIFY `idClient` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1034;

--
-- AUTO_INCREMENT pour la table `codeconfirmation`
--
ALTER TABLE `codeconfirmation`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `Commande`
--
ALTER TABLE `Commande`
  MODIFY `idCommande` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=179;

--
-- AUTO_INCREMENT pour la table `LigneCommande`
--
ALTER TABLE `LigneCommande`
  MODIFY `idLigneCommande` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=237;

--
-- AUTO_INCREMENT pour la table `LignePanier`
--
ALTER TABLE `LignePanier`
  MODIFY `idLignePanier` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=587;

--
-- AUTO_INCREMENT pour la table `Origami`
--
ALTER TABLE `Origami`
  MODIFY `idOrigami` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT pour la table `Paiement`
--
ALTER TABLE `Paiement`
  MODIFY `idPaiement` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=162;

--
-- AUTO_INCREMENT pour la table `Panier`
--
ALTER TABLE `Panier`
  MODIFY `idPanier` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=249;

--
-- AUTO_INCREMENT pour la table `tokens_confirmation`
--
ALTER TABLE `tokens_confirmation`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=204;

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
  ADD CONSTRAINT `LignePanier_idPanier_FK` FOREIGN KEY (`idPanier`) REFERENCES `Panier` (`idPanier`) ON DELETE CASCADE;

--
-- Contraintes pour la table `Panier`
--
ALTER TABLE `Panier`
  ADD CONSTRAINT `Panier_idClient_FK` FOREIGN KEY (`idClient`) REFERENCES `Client` (`idClient`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
