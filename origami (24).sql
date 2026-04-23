-- phpMyAdmin SQL Dump
-- version 5.1.1deb5ubuntu1
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:3306
-- Généré le : jeu. 23 avr. 2026 à 05:06
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
(863, 'temp_69cf37f8c311a@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-03 03:46:00', 'mv54bube6dr8s28tm2l2r5mpto'),
(864, 'temp_69cf3ec4c802b@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-03 04:15:00', 'v6t1ckpqa2rlmuhbm8a79dtdf3'),
(865, 'temp_69cfe827dd8b2@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-03 16:17:43', 'rnru5mqeb9negv1tou6dd8017e'),
(866, 'temp_69d0a3fc57e41@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-04 05:39:08', 'uuau9vhu8helqnnhrl2a6bbhcu'),
(867, 'temp_69d0c0aedbc16@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-04 07:41:34', 'klgsoscsh3g9ll1gstgf1c5gou'),
(868, 'temp_69d19e4f3be48@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-04 23:27:11', '9s7dh206ioqgtip80m5moipcmt'),
(869, 'temp_69d1a9f4eef1b@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-05 00:16:52', 'g52sggncil849pikjctk2jlkpe'),
(870, 'temp_69d1bbb4a560a@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-05 01:32:36', 'arnggcgbfsfni2amut74mdplbd'),
(871, 'temp_69d1cb42b33fa@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-05 02:38:58', 'ioc8l00rb349vp9jgj9umujrsm'),
(872, 'temp_69d20e8cd32d5@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-05 07:26:04', 'oknfhrd43fqvvuvuccomp1p333'),
(873, 'temp_69d243845e260@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-05 11:12:04', 'pddv01m2udhq151lg5g5d4n822'),
(874, 'temp_69d25d50e848c@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-05 13:02:08', 'otnufvlu773u77muvf3a6b7iqp'),
(875, 'temp_69d2b49f48d1a@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-05 19:14:39', 'bu49u5a2988p1gir1acuimdbc1'),
(876, 'temp_69d30998b2a8e@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-06 01:17:12', '51g6dolmsdmpaci44qlmvrigeg'),
(877, 'temp_69d3109fa7785@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-06 01:47:11', 'bcs1bpb0js46q0of0qfkculjin'),
(878, 'temp_69d31baf1b96e@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-06 02:34:23', 'ttpd3sdgom7pg57ohnvtp998j1'),
(879, 'temp_69d3425f6b291@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-06 05:19:27', 'hbnr6c3f5om0qunn4j2l2jr0u4'),
(880, 'temp_69d37238a93a6@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-06 08:43:36', 'kn4u9ifuo0e1itav9nnhq4o4q4'),
(881, 'temp_69d37240b20b3@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-06 08:43:44', '9fo6c7a8n13n8c3b8kom71n89o'),
(882, 'temp_69d489528ad80@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-07 04:34:26', 'p4ai349a7sulbll2fmltl9veib'),
(883, 'temp_69d4cb17b4461@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-07 09:15:03', '8aa9ukp4jts5rral839ufb29j7'),
(884, 'temp_69d4cb3deed2a@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-07 09:15:41', 'mnq7rluap723c6ueb3svevb30l'),
(885, 'temp_69d4e846b3e61@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-07 11:19:34', '86a8851o6lj2rc37ffd33ibi2m'),
(886, 'temp_69d518a5c12ba@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-07 14:45:57', 'inqqu31s51609n92dur3gocqjp'),
(887, 'temp_69d53f739093f@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-07 17:31:31', 'mckgc4ao0sjmdlqlvmlnnkc8td'),
(888, 'temp_69d5dae2a1500@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-08 04:34:42', '3lo7efpulbgdv6270spmqjofqd'),
(889, 'temp_69d64717621ff@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-08 12:16:23', 'le90i4h4m28rmd1p71ldegupt2'),
(890, 'temp_69d7e73374f6b@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-09 17:51:47', 'ugao05o1u8hep2uutfvhasefcd'),
(891, 'temp_69d8335ba3d31@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-09 23:16:43', 'bir0hdvvv173sto8si02jtcehb'),
(892, 'temp_69d8589e990e1@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-10 01:55:42', '4brmf2js36ah32nbo725qklahv'),
(893, 'temp_69d8f7636a072@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-10 13:13:07', 'gmh3vcun47525sl92r78113oig'),
(894, 'temp_69d8f76453283@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-10 13:13:08', '2tp2nhn7snkog6ligcbp078pc4'),
(895, 'temp_69d8f7647e626@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-10 13:13:08', 'fr00jpc1og3j5031kmeasacl0p'),
(896, 'temp_69d8f764f250e@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-10 13:13:08', 'k1tup0ucltssp5u8enbo3lom81'),
(897, 'temp_69d8f785385e3@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-10 13:13:41', 'v65gu7m6vjhl3m2jojnqap8icr'),
(898, 'temp_69d919f77db65@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-10 15:40:39', 'qv12uja65no1mmnklt7v9u17ub'),
(899, 'temp_69d9850b79c02@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-10 23:17:31', 'unh5v3p1gqv7tuj1a69lmdsml3'),
(900, 'temp_69d9c7a6a66ca@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-11 04:01:42', 'l5i66jmtf0m0q3sd466ve9thj1'),
(901, 'temp_69d9c7c5a4017@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-11 04:02:13', 'p2orv5vkqcmp1g2946jsrlr2nd'),
(902, 'temp_69da66103a615@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-11 15:17:36', 'ug1vs95uh41bmt96lpd0t8vbs6'),
(903, 'temp_69daf21beba22@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-12 01:15:07', '590d4vnpsss310pau1nqk3k6kc'),
(904, 'temp_69db39852bcbd@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-12 06:19:49', '7f2mhlplg5f7nskulkk47khanv'),
(905, 'temp_69db41fd6d1b1@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-12 06:55:57', 'mji8tekfm824llq3brj5829pbf'),
(906, 'temp_69db6977ef86c@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-12 09:44:23', 'pmv2vm0vaoihdhk0cf4k13vpbg'),
(907, 'temp_69dd16d6f0902@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-13 16:16:22', 'vc4teb27u2hnamind4sa62uqf4'),
(908, 'temp_69dd3d384429d@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-13 19:00:08', 'gm6ollpaq4rk5jono2bsvu63p8'),
(909, 'temp_69ddc47a78ca1@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-14 04:37:14', '2hjb4au5mqndtgg6ngf0dc2n26'),
(910, 'temp_69de2fb690eef@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-14 12:14:46', 'coa53976he1h0pi750g2jvvep6'),
(911, 'temp_69de2fb728e14@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-14 12:14:47', '3bdkniu5on7ml0pbfgu5isnqsa'),
(912, 'temp_69dea0473ee6f@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-14 20:15:03', 'gp5kl78kleo0tr897580feo1pp'),
(913, 'temp_69df0a6016f2a@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-15 03:47:44', 'q47fn5v2q5sot44n40bukqvuna'),
(914, 'temp_69e079c636a70@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-16 05:55:18', 'efg15ecgq45j01igpj8ad0iu7u'),
(915, 'temp_69e0df338f5f2@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-16 13:08:03', 'nna4qkd7n7k2ptv1jfio6886dl'),
(916, 'temp_69e1328bc26fe@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-16 19:03:39', '743uqvv8uogcpv5flru4g456gp'),
(917, 'temp_69e2e5585035d@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-18 01:58:48', 'te3d36jvmgjhjqtbqfj2n3utto'),
(918, 'temp_69e2e5593cc73@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-18 01:58:49', '9vihl8cp5j1c0knsn12n6ud4hj'),
(919, 'temp_69e65e0a10905@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-20 17:10:34', '464riishqah53v8fv6n5igrjne'),
(920, 'temp_69e65e0c2310b@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-20 17:10:36', 's6lv8vhodgg2khs3knil93rsra'),
(921, 'temp_69e65e249d0f4@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-20 17:11:00', '6rqtja9cvuha5puui8m25ovo42'),
(922, 'temp_69e78d9f35b27@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-21 14:45:51', '9ek5misdsvidja7jiug1dgnmrk'),
(923, 'temp_69e78da2ebc74@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-21 14:45:54', 'ecm7ptnem2rnnpmtnp6dcr64ji'),
(924, 'temp_69e7c35aeb67c@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-21 18:35:06', 'usfdp7vv93bthi11nkq83flodc'),
(925, 'temp_69e7d7918f032@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-21 20:01:21', 'cbomrdedk7ko5f7567c71kjmhr'),
(926, 'temp_69e886ed71284@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-22 08:29:33', 'lcbel51eidrsbs6jncrv9pv2kn'),
(927, 'temp_69e886f4897bb@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-22 08:29:40', 'ojnegisn2be8dp3t1aqqedk5g8'),
(928, 'temp_69e886f570e42@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-22 08:29:41', '28goh3mcdd0epo4f6n0qskmjg8'),
(929, 'temp_69e89d28aebe3@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-22 10:04:24', 'udgho40h9tk6neo5m6ukbok5kr'),
(930, 'temp_69e8c83dae5f3@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-22 13:08:13', '0qg462mrdgo7rganpmbvcgoa0n'),
(931, 'temp_69e921ac147a1@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-22 19:29:48', '0rsa1vphjjfjfjrv7gahjkhhju'),
(932, 'temp_69e9a41588084@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-23 04:46:13', '0rptkheo63qhvco8nd2g5oeh8b'),
(934, 'temp_69e9a874af85f@YoukiAndCO', NULL, 'Invité', 'Client', NULL, 0, NULL, NULL, 'temporaire', '2026-04-23 05:04:52', 'bkqtioq0lgj6na30149c2ohpra');

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
(170, 844, 50, 50, '2026-04-23 05:05:52', 'PayPal', '2026-04-28', 0, 1, 'en_attente_paiement', 'en_attente', NULL);

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
(213, 170, 5, 1, 1);

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
(419, 200, 3, 1, 45),
(420, 201, 1, 1, 24),
(421, 201, 2, 1, 18),
(423, 202, 2, 2, 18),
(424, 202, 3, 1, 45),
(434, 192, 5, 1, 1);

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
  `niveau_difficulte` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `Origami`
--

INSERT INTO `Origami` (`idOrigami`, `nom`, `description`, `photo`, `prixHorsTaxe`, `date_modification`, `niveau_difficulte`) VALUES
(1, 'La grue Élégante', 'Symbole de paix et de longévité, cette grue est pliée avec un papier washi traditionnel.', 'img/couple de sygnes.jpg', 24, NULL, NULL),
(2, 'Fleur de Cerisier', 'Inspirée des sakura japonais, cette fleur délicate apporte une touche de printemps éternel.', 'img/flower.jpg', 18, NULL, NULL),
(3, 'Dragon Majestueux', 'Une création complexe et impressionnante, symbole de puissance et de sagesse.', 'img/dragon.png', 45, NULL, NULL),
(4, 'Éventail Traditionnel', 'Accessoire élégant et fonctionnel, plié avec un papier aux motifs traditionnels.', 'img/eventail.jpg', 32, NULL, NULL),
(5, '1 euro', 'Pièce de monnaie', 'img/euro.jpg', 1, NULL, NULL);

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
(153, 169, '65.00', 'EUR', 'payee', 'PayPal', NULL, '2026-04-01 15:00:26', '2026-04-01 15:00:26');

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
(192, 844, '2026-04-23 05:06:16'),
(200, 863, '2026-04-03 03:50:09'),
(201, 864, '2026-04-03 04:15:27'),
(202, 882, '2026-04-07 04:34:57'),
(203, 932, '2026-04-23 04:59:46');

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
  MODIFY `idClient` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=935;

--
-- AUTO_INCREMENT pour la table `codeconfirmation`
--
ALTER TABLE `codeconfirmation`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `Commande`
--
ALTER TABLE `Commande`
  MODIFY `idCommande` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=171;

--
-- AUTO_INCREMENT pour la table `LigneCommande`
--
ALTER TABLE `LigneCommande`
  MODIFY `idLigneCommande` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=214;

--
-- AUTO_INCREMENT pour la table `LignePanier`
--
ALTER TABLE `LignePanier`
  MODIFY `idLignePanier` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=435;

--
-- AUTO_INCREMENT pour la table `Origami`
--
ALTER TABLE `Origami`
  MODIFY `idOrigami` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT pour la table `Paiement`
--
ALTER TABLE `Paiement`
  MODIFY `idPaiement` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=154;

--
-- AUTO_INCREMENT pour la table `Panier`
--
ALTER TABLE `Panier`
  MODIFY `idPanier` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=205;

--
-- AUTO_INCREMENT pour la table `tokens_confirmation`
--
ALTER TABLE `tokens_confirmation`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=194;

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
