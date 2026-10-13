-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : ven. 02 oct. 2026 à 08:34
-- Version du serveur : 8.4.7
-- Version de PHP : 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `fonctionnaire_ministere`
--

-- --------------------------------------------------------

--
-- Structure de la table `affectation`
--

DROP TABLE IF EXISTS `affectation`;
CREATE TABLE IF NOT EXISTS `affectation` (
  `id_affec` int NOT NULL AUTO_INCREMENT,
  `tit_post` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `gra_post` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vil_affec` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sta_post` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_affec`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `affectation`
--

INSERT INTO `affectation` (`id_affec`, `tit_post`, `gra_post`, `vil_affec`, `sta_post`) VALUES
(12, 'Consultant', 'A2', 'Paris', 'International (P)'),
(13, 'Conseiller Technique', 'H7', 'Canada', 'International (P)'),
(14, 'Stagiaire', 'I8', 'Abidjan', 'Consultant');

-- --------------------------------------------------------

--
-- Structure de la table `competences`
--

DROP TABLE IF EXISTS `competences`;
CREATE TABLE IF NOT EXISTS `competences` (
  `id_comp` int NOT NULL AUTO_INCREMENT,
  `libelle_comp` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_comp`),
  UNIQUE KEY `libelle_comp` (`libelle_comp`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `competences`
--

INSERT INTO `competences` (`id_comp`, `libelle_comp`, `date_creation`) VALUES
(5, 'expert en laravel', '2026-05-05 23:08:48');

-- --------------------------------------------------------

--
-- Structure de la table `detenir`
--

DROP TABLE IF EXISTS `detenir`;
CREATE TABLE IF NOT EXISTS `detenir` (
  `id_fonc` int NOT NULL,
  `id_comp` int NOT NULL,
  `niveau` enum('Debutant','Intermediaire','Avance','Expert') COLLATE utf8mb4_unicode_ci NOT NULL,
  `justificatif_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_fonc`,`id_comp`),
  KEY `id_comp` (`id_comp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `detenir`
--

INSERT INTO `detenir` (`id_fonc`, `id_comp`, `niveau`, `justificatif_path`) VALUES
(52, 5, 'Avance', 'competences/comp_52_1781186499.pdf'),
(55, 5, 'Intermediaire', 'comp_55_1781182120.pdf');

-- --------------------------------------------------------

--
-- Structure de la table `diplome`
--

DROP TABLE IF EXISTS `diplome`;
CREATE TABLE IF NOT EXISTS `diplome` (
  `id_dipl` int NOT NULL AUTO_INCREMENT,
  `lib_dipl` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `niv_etu` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fich_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_dipl`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `diplome`
--

INSERT INTO `diplome` (`id_dipl`, `lib_dipl`, `niv_etu`, `fich_url`) VALUES
(12, 'BTS en INFORMATIQUE DEVELOPPEUR D\'APPLICATION', 'BAC+2', 'diplome_52_1781186574.pdf'),
(13, 'Master en ressources humaines', 'BAC+5', 'dip_53_1781174934.jpg'),
(14, 'Master en ressources humaines', 'BAC+5', 'dip_55_1781181940.pdf'),
(15, 'Bachelors en IA', 'Bachelors', 'dip_55_1781184421.pdf');

-- --------------------------------------------------------

--
-- Structure de la table `document`
--

DROP TABLE IF EXISTS `document`;
CREATE TABLE IF NOT EXISTS `document` (
  `id_doc` int NOT NULL AUTO_INCREMENT,
  `lib_doc` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fich_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_enre` date NOT NULL,
  `id_fonc` int NOT NULL,
  `id_dipl` int DEFAULT NULL,
  PRIMARY KEY (`id_doc`),
  KEY `fk_doc_fonc` (`id_fonc`),
  KEY `fk_doc_diplome` (`id_dipl`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `document`
--

INSERT INTO `document` (`id_doc`, `lib_doc`, `fich_url`, `date_enre`, `id_fonc`, `id_dipl`) VALUES
(16, 'Diplôme : BTS en INFORMATIQUE DEVELOPPEUR D\'APPLICATION', 'dip_52_1778022285.png', '2026-05-05', 52, 12),
(18, 'Diplôme : Master en ressources humaines', 'dip_55_1781181940.pdf', '2026-06-11', 55, 14),
(19, 'Diplôme : Bachelors en IA', 'dip_55_1781184421.pdf', '2026-06-11', 55, 15);

-- --------------------------------------------------------

--
-- Structure de la table `fonctionnaire`
--

DROP TABLE IF EXISTS `fonctionnaire`;
CREATE TABLE IF NOT EXISTS `fonctionnaire` (
  `id_fonc` int NOT NULL AUTO_INCREMENT,
  `matri` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_fonc` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom_fonc` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cont` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_naiss` date NOT NULL,
  `gen` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_perso` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_debut` date DEFAULT NULL,
  `photo_fonc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'default.png',
  PRIMARY KEY (`id_fonc`),
  UNIQUE KEY `matri` (`matri`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `fonctionnaire`
--

INSERT INTO `fonctionnaire` (`id_fonc`, `matri`, `nom_fonc`, `prenom_fonc`, `cont`, `date_naiss`, `gen`, `email_perso`, `adre`, `date_debut`, `photo_fonc`) VALUES
(49, '0172170E', 'KOUAME', 'Ami', '+2250500791113', '2023-05-14', 'F', 'Awak37257@gmail.com', 'Abobo', '2026-05-05', 'IMG_0172170E.jpg'),
(51, '441840S', 'KOUAME', 'Ami', '+2250500791113', '2026-05-20', 'M', 'Awak37257@gmail.com', 'Abobo', '2026-05-05', 'IMG_441840S.jpg'),
(52, '045183A', 'KONE', 'Awa', '+2250500791113', '2026-05-08', 'F', 'Awak37257@gmail.com', 'Abobo', '2026-05-05', 'default.png'),
(55, '678543C', 'KOBENAN', 'Ruth', '+2250172170904', '2026-06-24', 'F', 'Awak37257@gmail.com', 'Bingerville', '2026-06-11', 'default.png');

-- --------------------------------------------------------

--
-- Structure de la table `gestionnaire`
--

DROP TABLE IF EXISTS `gestionnaire`;
CREATE TABLE IF NOT EXISTS `gestionnaire` (
  `id_gest` int NOT NULL AUTO_INCREMENT,
  `nom_gest` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom_gest` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone_gest` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_user` int DEFAULT NULL,
  `email_gest` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_gest`),
  KEY `id_user` (`id_user`)
) ENGINE=MyISAM AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `gestionnaire`
--

INSERT INTO `gestionnaire` (`id_gest`, `nom_gest`, `prenom_gest`, `telephone_gest`, `id_user`, `email_gest`) VALUES
(25, 'BETIBOUO', 'Laurent', '0500791113', 59, 'awak37257@gmail.com'),
(20, 'KONE', 'GRACE', '0172170904', 61, 'awak37257@gmail.com'),
(24, 'CISSE', 'mohamed', '0500791113', 69, 'awak37257@gmail.com'),
(19, 'AKA', 'ami', '0172170904', 60, 'awak37257@gmail.com');

-- --------------------------------------------------------

--
-- Structure de la table `historique`
--

DROP TABLE IF EXISTS `historique`;
CREATE TABLE IF NOT EXISTS `historique` (
  `id_hist` int NOT NULL AUTO_INCREMENT,
  `id_user` int DEFAULT NULL,
  `action_desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `dat_hist` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_hist`),
  KEY `fk_logs_user` (`id_user`)
) ENGINE=InnoDB AUTO_INCREMENT=118 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `historique`
--

INSERT INTO `historique` (`id_hist`, `id_user`, `action_desc`, `dat_hist`) VALUES
(3, NULL, 'Connexion à la plateforme', '2026-04-27 12:27:23'),
(4, NULL, 'Correction du profil de l\'agent : KOUAME (Matricule: 0172170E)', '2026-04-27 12:27:32'),
(5, 1, 'Connexion à la plateforme', '2026-04-27 12:28:01'),
(6, NULL, 'Connexion à la plateforme', '2026-04-27 13:13:07'),
(7, NULL, 'Connexion à la plateforme', '2026-04-27 13:17:52'),
(8, NULL, 'Connexion à la plateforme', '2026-04-27 13:19:07'),
(9, NULL, 'Connexion à la plateforme', '2026-04-27 13:19:29'),
(10, NULL, 'Connexion à la plateforme', '2026-04-27 13:19:41'),
(11, NULL, 'Connexion à la plateforme', '2026-04-27 13:32:51'),
(12, NULL, 'Connexion à la plateforme', '2026-04-27 13:35:18'),
(13, 1, 'Connexion à la plateforme', '2026-04-28 13:23:59'),
(14, 1, 'Connexion à la plateforme', '2026-04-29 10:42:56'),
(15, NULL, 'Connexion à la plateforme', '2026-04-29 10:58:53'),
(16, NULL, 'Connexion à la plateforme', '2026-04-29 11:02:16'),
(17, NULL, 'Connexion à la plateforme', '2026-04-29 11:05:49'),
(18, 1, 'Connexion à la plateforme', '2026-04-29 12:09:14'),
(19, NULL, 'Connexion à la plateforme', '2026-04-29 12:09:36'),
(20, NULL, 'Connexion à la plateforme', '2026-04-29 12:09:44'),
(21, 1, 'Connexion à la plateforme', '2026-04-29 12:12:15'),
(22, NULL, 'Connexion à la plateforme', '2026-04-29 12:14:13'),
(23, NULL, 'Agent a modifié le profil : KOUAME', '2026-04-29 12:15:41'),
(24, NULL, 'Agent a modifié le profil : KOUAME', '2026-04-29 12:47:44'),
(25, NULL, 'Agent a modifié le profil : KOUAME', '2026-04-29 14:33:40'),
(26, NULL, 'Agent a modifié le profil : KOUAME', '2026-04-29 14:36:12'),
(27, NULL, 'Connexion à la plateforme', '2026-04-29 14:48:41'),
(28, NULL, 'Agent a modifié le profil : KOFFI', '2026-04-29 14:49:04'),
(29, NULL, 'Connexion à la plateforme', '2026-04-29 14:49:18'),
(30, NULL, 'Connexion à la plateforme', '2026-04-30 19:57:47'),
(31, 1, 'Connexion à la plateforme', '2026-04-30 19:57:57'),
(32, NULL, 'Connexion à la plateforme', '2026-04-30 19:58:14'),
(33, 1, 'Connexion à la plateforme', '2026-04-30 20:00:46'),
(34, 1, 'Connexion à la plateforme', '2026-04-30 22:36:53'),
(35, NULL, 'Connexion à la plateforme', '2026-04-30 22:42:18'),
(36, NULL, 'Connexion à la plateforme', '2026-04-30 22:58:10'),
(37, NULL, 'Connexion à la plateforme', '2026-04-30 22:58:44'),
(38, 1, 'Connexion à la plateforme', '2026-04-30 23:04:43'),
(39, 1, 'Connexion à la plateforme', '2026-05-04 11:18:36'),
(40, NULL, 'Connexion à la plateforme', '2026-05-04 11:45:51'),
(41, 1, 'Connexion à la plateforme', '2026-05-04 12:15:49'),
(42, NULL, 'Connexion à la plateforme', '2026-05-04 12:16:31'),
(43, NULL, 'Connexion à la plateforme', '2026-05-04 12:20:51'),
(44, NULL, 'Connexion à la plateforme', '2026-05-04 12:26:20'),
(45, 1, 'Connexion à la plateforme', '2026-05-04 12:26:51'),
(46, 54, 'Connexion à la plateforme', '2026-05-05 14:30:21'),
(47, 1, 'Connexion à la plateforme', '2026-05-05 15:25:36'),
(48, 54, 'Connexion à la plateforme', '2026-05-05 15:26:45'),
(49, 54, 'Connexion à la plateforme', '2026-05-05 20:53:53'),
(50, NULL, 'Connexion à la plateforme', '2026-05-05 20:54:07'),
(51, NULL, 'Connexion à la plateforme', '2026-05-05 20:54:21'),
(52, NULL, 'Connexion à la plateforme', '2026-05-05 20:54:31'),
(53, 1, 'Connexion à la plateforme', '2026-05-05 20:54:54'),
(54, 59, 'Connexion à la plateforme', '2026-05-05 21:25:47'),
(55, 61, 'Connexion à la plateforme', '2026-05-05 21:25:59'),
(56, 54, 'Connexion à la plateforme', '2026-05-05 21:34:27'),
(57, 54, 'Connexion à la plateforme', '2026-05-05 21:35:08'),
(58, 1, 'Connexion à la plateforme', '2026-05-05 21:40:22'),
(59, 63, 'Connexion à la plateforme', '2026-05-05 21:42:18'),
(60, 1, 'Connexion à la plateforme', '2026-05-05 23:09:29'),
(61, 1, 'Connexion à la plateforme', '2026-05-06 10:56:34'),
(62, 1, 'Connexion à la plateforme', '2026-05-08 10:28:57'),
(63, 1, 'Connexion à la plateforme', '2026-06-10 09:41:54'),
(64, 60, 'Connexion à la plateforme', '2026-06-10 09:50:15'),
(65, 63, 'Connexion à la plateforme', '2026-06-10 09:50:56'),
(66, 1, 'Connexion à la plateforme', '2026-06-10 15:00:40'),
(67, 1, 'Connexion à la plateforme', '2026-06-11 10:34:53'),
(68, NULL, 'Connexion à la plateforme', '2026-06-11 10:41:47'),
(69, NULL, 'Connexion à la plateforme', '2026-06-11 10:45:26'),
(70, 1, 'Connexion à la plateforme', '2026-06-11 10:45:39'),
(71, NULL, 'Connexion à la plateforme', '2026-06-11 10:47:29'),
(72, 1, 'Connexion à la plateforme', '2026-06-11 10:49:42'),
(73, NULL, 'Connexion à la plateforme', '2026-06-11 10:51:58'),
(74, 1, 'Connexion à la plateforme', '2026-06-11 11:54:26'),
(75, 1, 'Connexion à la plateforme', '2026-06-11 12:14:37'),
(76, 1, 'Connexion à la plateforme', '2026-06-11 12:20:55'),
(77, NULL, 'Connexion à la plateforme', '2026-06-11 12:22:05'),
(78, 1, 'Connexion à la plateforme', '2026-06-11 12:27:58'),
(79, NULL, 'Connexion à la plateforme', '2026-06-11 12:35:24'),
(80, 70, 'Connexion à la plateforme', '2026-06-11 12:41:41'),
(81, NULL, 'Connexion à la plateforme', '2026-06-11 12:52:34'),
(82, 61, 'Connexion à la plateforme', '2026-06-11 12:53:16'),
(83, 1, 'Connexion à la plateforme', '2026-06-11 13:12:28'),
(84, NULL, 'Connexion à la plateforme', '2026-06-11 13:18:39'),
(85, 60, 'Connexion à la plateforme', '2026-06-11 13:19:10'),
(86, 60, 'Admin a modifié le profil : KONE', '2026-06-11 14:34:39'),
(87, 1, 'Connexion à la plateforme', '2026-06-15 11:42:03'),
(88, 63, 'Connexion à la plateforme', '2026-06-17 11:18:48'),
(89, 1, 'Connexion à la plateforme', '2026-06-17 11:19:06'),
(90, 60, 'Connexion à la plateforme', '2026-06-17 11:25:44'),
(91, 1, 'Connexion à la plateforme', '2026-06-17 11:49:11'),
(92, 54, 'Connexion à la plateforme', '2026-06-17 11:50:52'),
(93, 1, 'Connexion à la plateforme', '2026-06-17 11:51:29'),
(94, 1, 'Connexion à la plateforme', '2026-06-24 10:02:08'),
(95, 59, 'Connexion à la plateforme', '2026-06-24 10:48:33'),
(96, 63, 'Connexion à la plateforme', '2026-06-24 10:48:44'),
(97, 1, 'Connexion à la plateforme', '2026-06-24 10:49:52'),
(98, 59, 'Connexion à la plateforme', '2026-06-24 10:52:31'),
(99, 1, 'Connexion à la plateforme', '2026-06-24 10:52:44'),
(100, NULL, 'Connexion à la plateforme', '2026-06-24 10:55:01'),
(101, 1, 'Connexion à la plateforme', '2026-06-24 10:56:08'),
(102, 1, 'Connexion à la plateforme', '2026-07-07 11:35:30'),
(103, 1, 'Connexion à la plateforme', '2026-07-07 11:38:25'),
(104, 1, 'Connexion à la plateforme', '2026-07-07 11:47:53'),
(105, 54, 'Connexion à la plateforme', '2026-07-07 11:54:08'),
(106, 63, 'Connexion à la plateforme', '2026-07-07 11:54:26'),
(107, 1, 'Connexion à la plateforme', '2026-08-04 10:22:41'),
(108, 1, 'Connexion à la plateforme', '2026-08-04 10:52:07'),
(109, 59, 'Connexion à la plateforme', '2026-08-04 10:52:20'),
(110, 59, 'Admin a modifié le profil : KOUAME', '2026-08-04 10:52:49'),
(111, 1, 'Connexion à la plateforme', '2026-08-04 10:52:56'),
(112, 1, 'Connexion à la plateforme', '2026-08-05 18:52:27'),
(113, 1, 'Connexion à la plateforme', '2026-09-07 12:19:34'),
(114, 60, 'Connexion à la plateforme', '2026-09-07 12:48:46'),
(115, 63, 'Connexion à la plateforme', '2026-09-07 12:49:29'),
(116, 1, 'Connexion à la plateforme', '2026-09-07 14:02:30'),
(117, 1, 'Connexion à la plateforme', '2026-09-22 08:57:21');

-- --------------------------------------------------------

--
-- Structure de la table `obtenir`
--

DROP TABLE IF EXISTS `obtenir`;
CREATE TABLE IF NOT EXISTS `obtenir` (
  `id_fonc` int NOT NULL,
  `id_dipl` int NOT NULL,
  `ann_obte` year NOT NULL,
  PRIMARY KEY (`id_fonc`,`id_dipl`),
  KEY `fk_obt_dip` (`id_dipl`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `obtenir`
--

INSERT INTO `obtenir` (`id_fonc`, `id_dipl`, `ann_obte`) VALUES
(52, 12, '2023'),
(55, 14, '2026'),
(55, 15, '2026');

-- --------------------------------------------------------

--
-- Structure de la table `organisation`
--

DROP TABLE IF EXISTS `organisation`;
CREATE TABLE IF NOT EXISTS `organisation` (
  `id_org` int NOT NULL AUTO_INCREMENT,
  `nom_org` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sigle_org` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `siege_soci` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id_org`),
  UNIQUE KEY `sigle_org` (`sigle_org`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `organisation`
--

INSERT INTO `organisation` (`id_org`, `nom_org`, `sigle_org`, `siege_soci`) VALUES
(15, 'BANQUE AFRICAINE DE DEVELOPPEMENT', 'BAD', 'Côte d\'ivoire - Abidjan'),
(16, 'ORGANISATION DES NATIONS UNIES', 'ONU', 'NEW-YORK'),
(17, 'Organisation des Nations Unies pour l\'éducation, la science et la culture', 'UNESCO', 'ABUJA'),
(19, 'Programme des Nations Unies pour le Développement', 'PNUD', 'Canada');

-- --------------------------------------------------------

--
-- Structure de la table `recevoir`
--

DROP TABLE IF EXISTS `recevoir`;
CREATE TABLE IF NOT EXISTS `recevoir` (
  `id_rec` int NOT NULL AUTO_INCREMENT,
  `id_fonc` int NOT NULL,
  `id_org` int NOT NULL,
  `id_affec` int NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date DEFAULT NULL,
  `motif` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ville_affec` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_rec`),
  KEY `fk_rec_fonc` (`id_fonc`),
  KEY `fk_rec_org` (`id_org`),
  KEY `fk_recevoir_affectation` (`id_affec`)
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `recevoir`
--

INSERT INTO `recevoir` (`id_rec`, `id_fonc`, `id_org`, `id_affec`, `date_debut`, `date_fin`, `motif`, `ville_affec`) VALUES
(40, 49, 15, 12, '2026-05-05', NULL, 'Affectation initiale', NULL),
(41, 51, 17, 12, '2026-05-05', NULL, 'Affectation initiale', NULL),
(42, 52, 15, 14, '2026-05-05', '2026-05-05', 'Affectation initiale', NULL),
(45, 52, 16, 13, '2026-05-05', NULL, 'nkknlk,', 'Abuda'),
(49, 55, 19, 13, '2026-06-11', '2026-06-11', 'Affectation initiale', 'Canada'),
(50, 55, 17, 12, '2026-06-11', NULL, 'Promotion', 'France');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

DROP TABLE IF EXISTS `utilisateurs`;
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `id_user` int NOT NULL AUTO_INCREMENT,
  `iden` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mot_de_pas` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_fonc` int DEFAULT NULL,
  `id_org` int DEFAULT NULL,
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `iden` (`iden`),
  KEY `fk_u_fonc` (`id_fonc`),
  KEY `fk_u_org` (`id_org`)
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id_user`, `iden`, `mot_de_pas`, `rol`, `id_fonc`, `id_org`) VALUES
(1, 'admin_awa', '$2y$10$Z8/YA.eZ2fzpzMtMmsgOBuH7noG9G.s0a5Om.z44.Gpp8nk7Gam.S', 'admin', NULL, NULL),
(54, '0172170E', '$2y$10$z76y4hWSKASbPVZHW6vSvee2VHCavxuxgiyUnkHG1CCxCXZyDs0gq', 'AGENT', 49, 15),
(59, 'gestionnaire_BAD', '$2y$10$KAbe16c/ZiJrUzLYyIimd.tX7eqjX2rM4lSiCxftg3/mjh3hFG8.m', 'admin_local', NULL, 15),
(60, 'gestionnaire_ONU', '$2y$10$ml5CNrsXxkKwLQpa5JYJ1OxymNIm9kc8q3cA3WUNfVmtpnoBrFsEC', 'admin_local', NULL, 16),
(61, 'gestionnaire_unesco', '$2y$10$jgqh0elL/dgOBFL0.Q5/IuzuYegrBwp1Rp0Ua90NI3PmulI.WilC2', 'admin_local', NULL, 17),
(62, '441840S', '$2y$10$ZJKCMgUYvid1/aoGrxzhXuYsgdk8yTnu20x9CXNSt4E3QP5grUIr.', 'AGENT', 51, 17),
(63, '045183A', '$2y$10$PD2lMcnKYFwmldliP/TTaumeSM9dPNFM.XtRrnp.ELeH8VfXtI0WC', 'AGENT', 52, 15),
(70, '678543C', '$2y$10$1JoMyxTR4Gdi3SV3tn978.Trp1mZsnxFTzXbW284BEGwpdUdvZaq2', 'AGENT', 55, 19);

CREATE TABLE IF NOT EXISTS `notifications` (
  `id_notification` int NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL,
  `id_fonc` int NOT NULL,
  `type_element` enum('diplome','competence','mutation') COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_element` int NOT NULL,
  `id_org` int DEFAULT NULL,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('a_verifier','verifie','supprime') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'a_verifier',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_traitement` datetime DEFAULT NULL,
  PRIMARY KEY (`id_notification`),
  KEY `idx_notifications_user_statut` (`id_user`,`statut`,`date_creation`),
  KEY `idx_notifications_element` (`type_element`,`id_element`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `detenir`
--
ALTER TABLE `detenir`
  ADD CONSTRAINT `detenir_ibfk_1` FOREIGN KEY (`id_fonc`) REFERENCES `fonctionnaire` (`id_fonc`) ON DELETE CASCADE,
  ADD CONSTRAINT `detenir_ibfk_2` FOREIGN KEY (`id_comp`) REFERENCES `competences` (`id_comp`) ON DELETE CASCADE;

--
-- Contraintes pour la table `document`
--
ALTER TABLE `document`
  ADD CONSTRAINT `fk_doc_diplome` FOREIGN KEY (`id_dipl`) REFERENCES `diplome` (`id_dipl`),
  ADD CONSTRAINT `fk_doc_fonc` FOREIGN KEY (`id_fonc`) REFERENCES `fonctionnaire` (`id_fonc`) ON DELETE CASCADE;

--
-- Contraintes pour la table `historique`
--
ALTER TABLE `historique`
  ADD CONSTRAINT `fk_logs_user` FOREIGN KEY (`id_user`) REFERENCES `utilisateurs` (`id_user`) ON DELETE SET NULL;

--
-- Contraintes pour la table `obtenir`
--
ALTER TABLE `obtenir`
  ADD CONSTRAINT `fk_obt_dip` FOREIGN KEY (`id_dipl`) REFERENCES `diplome` (`id_dipl`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_obt_fonc` FOREIGN KEY (`id_fonc`) REFERENCES `fonctionnaire` (`id_fonc`) ON DELETE CASCADE;

--
-- Contraintes pour la table `recevoir`
--
ALTER TABLE `recevoir`
  ADD CONSTRAINT `fk_rec_fonc` FOREIGN KEY (`id_fonc`) REFERENCES `fonctionnaire` (`id_fonc`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rec_org` FOREIGN KEY (`id_org`) REFERENCES `organisation` (`id_org`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_recevoir_affectation` FOREIGN KEY (`id_affec`) REFERENCES `affectation` (`id_affec`);

--
-- Contraintes pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD CONSTRAINT `fk_u_fonc` FOREIGN KEY (`id_fonc`) REFERENCES `fonctionnaire` (`id_fonc`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_u_org` FOREIGN KEY (`id_org`) REFERENCES `organisation` (`id_org`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
