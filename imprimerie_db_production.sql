-- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: imprimerie_db_clean
-- ------------------------------------------------------
-- Server version	10.4.28-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `article`
--

DROP TABLE IF EXISTS `article`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `article` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `quantite` int(11) NOT NULL,
  `seuil_alerte` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `sub_category_id` int(11) DEFAULT NULL,
  `machine_id` int(11) DEFAULT NULL,
  `alerte_declenchee` tinyint(4) NOT NULL DEFAULT 0,
  `date_reapprovisionnement` datetime DEFAULT NULL,
  `reapprovisionnement_en_cours` tinyint(4) NOT NULL DEFAULT 0,
  `date_signalement_alerte` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_23A0E6612469DE2` (`category_id`),
  KEY `IDX_23A0E66F7BFE87C` (`sub_category_id`),
  KEY `IDX_23A0E66F6B75B26` (`machine_id`),
  CONSTRAINT `FK_23A0E6612469DE2` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`),
  CONSTRAINT `FK_23A0E66F6B75B26` FOREIGN KEY (`machine_id`) REFERENCES `machine` (`id`),
  CONSTRAINT `FK_23A0E66F7BFE87C` FOREIGN KEY (`sub_category_id`) REFERENCES `sub_category` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `article`
--

LOCK TABLES `article` WRITE;
/*!40000 ALTER TABLE `article` DISABLE KEYS */;
/*!40000 ALTER TABLE `article` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `article_attribut`
--

DROP TABLE IF EXISTS `article_attribut`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `article_attribut` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dimension` varchar(255) DEFAULT NULL,
  `grammage` varchar(255) DEFAULT NULL,
  `couleur` varchar(255) DEFAULT NULL,
  `longueur` varchar(255) DEFAULT NULL,
  `kilogramme` varchar(255) DEFAULT NULL,
  `article_id` int(11) NOT NULL,
  `quantite` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_2F501C367294869C` (`article_id`),
  CONSTRAINT `FK_2F501C367294869C` FOREIGN KEY (`article_id`) REFERENCES `article` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `article_attribut`
--

LOCK TABLES `article_attribut` WRITE;
/*!40000 ALTER TABLE `article_attribut` DISABLE KEYS */;
/*!40000 ALTER TABLE `article_attribut` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bon_sortie`
--

DROP TABLE IF EXISTS `bon_sortie`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bon_sortie` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom_employe` varchar(255) NOT NULL,
  `departement` varchar(255) NOT NULL,
  `raison` longtext NOT NULL,
  `is_aller_sans_retour` tinyint(4) NOT NULL,
  `date_creation` datetime NOT NULL,
  `statut` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bon_sortie`
--

LOCK TABLES `bon_sortie` WRITE;
/*!40000 ALTER TABLE `bon_sortie` DISABLE KEYS */;
/*!40000 ALTER TABLE `bon_sortie` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `category`
--

DROP TABLE IF EXISTS `category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `category`
--

LOCK TABLES `category` WRITE;
/*!40000 ALTER TABLE `category` DISABLE KEYS */;
/*!40000 ALTER TABLE `category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `demande`
--

DROP TABLE IF EXISTS `demande`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `demande` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `departement` varchar(255) NOT NULL,
  `materiel` varchar(255) NOT NULL,
  `quantite` int(11) NOT NULL,
  `statut` varchar(50) NOT NULL,
  `destination` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `localisation` varchar(255) DEFAULT NULL,
  `article_id` int(11) DEFAULT NULL,
  `demandeur_id` int(11) DEFAULT NULL,
  `attribut_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_2694D7A57294869C` (`article_id`),
  KEY `IDX_2694D7A595A6EE59` (`demandeur_id`),
  KEY `IDX_2694D7A551383AF3` (`attribut_id`),
  CONSTRAINT `FK_2694D7A551383AF3` FOREIGN KEY (`attribut_id`) REFERENCES `article_attribut` (`id`),
  CONSTRAINT `FK_2694D7A57294869C` FOREIGN KEY (`article_id`) REFERENCES `article` (`id`),
  CONSTRAINT `FK_2694D7A595A6EE59` FOREIGN KEY (`demandeur_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `demande`
--

LOCK TABLES `demande` WRITE;
/*!40000 ALTER TABLE `demande` DISABLE KEYS */;
/*!40000 ALTER TABLE `demande` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `devis`
--

DROP TABLE IF EXISTS `devis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `devis` (
  `montant_initial` decimal(10,2) DEFAULT NULL,
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `description` varchar(255) NOT NULL,
  `montant` double NOT NULL,
  `created_at` datetime NOT NULL,
  `created_by` varchar(255) NOT NULL,
  `entete` varchar(255) NOT NULL,
  `statut` varchar(50) NOT NULL,
  `devise` varchar(3) NOT NULL,
  `date_decaissement` datetime DEFAULT NULL,
  `date_limite` date DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `date_decaissement_par` datetime DEFAULT NULL,
  `decaisse_par_id` int(11) DEFAULT NULL,
  `passage_dg` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `IDX_8B27C52BA76ED395` (`user_id`),
  KEY `IDX_8B27C52B52382D6A` (`decaisse_par_id`),
  CONSTRAINT `FK_8B27C52B52382D6A` FOREIGN KEY (`decaisse_par_id`) REFERENCES `user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_8B27C52BA76ED395` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `devis`
--

LOCK TABLES `devis` WRITE;
/*!40000 ALTER TABLE `devis` DISABLE KEYS */;
/*!40000 ALTER TABLE `devis` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `doctrine_migration_versions`
--

DROP TABLE IF EXISTS `doctrine_migration_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `doctrine_migration_versions` (
  `version` varchar(191) NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctrine_migration_versions`
--

LOCK TABLES `doctrine_migration_versions` WRITE;
/*!40000 ALTER TABLE `doctrine_migration_versions` DISABLE KEYS */;
INSERT INTO `doctrine_migration_versions` VALUES ('DoctrineMigrations\\Version20260522091739','2026-07-21 16:31:19',114),('DoctrineMigrations\\Version20260815115627',NULL,NULL),('DoctrineMigrations\\Version20260911153955','2026-09-24 12:42:36',135),('DoctrineMigrations\\Version20260915101919','2026-09-24 12:42:36',112),('DoctrineMigrations\\Version20260924010557',NULL,NULL),('DoctrineMigrations\\Version20260924104233','2026-09-24 13:00:54',278),('DoctrineMigrations\\Version20260925020306','2026-09-25 04:03:09',38),('DoctrineMigrations\\Version20260925031354','2026-09-25 05:14:50',348),('DoctrineMigrations\\Version20260926020432','2026-09-26 04:04:35',31),('DoctrineMigrations\\Version20261002093738','2026-10-02 11:37:48',596),('DoctrineMigrations\\Version20261002104644','2026-10-02 12:46:46',63),('DoctrineMigrations\\Version20261008134327','2026-10-08 15:43:29',84),('DoctrineMigrations\\Version20261009085143','2026-10-09 10:51:45',84);
/*!40000 ALTER TABLE `doctrine_migration_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historique_reapprovisionnement`
--

DROP TABLE IF EXISTS `historique_reapprovisionnement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `historique_reapprovisionnement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_action` varchar(50) NOT NULL,
  `commentaire` longtext DEFAULT NULL,
  `date_action` datetime NOT NULL,
  `ancienne_date_reappro` datetime DEFAULT NULL,
  `nouvelle_date_reappro` datetime DEFAULT NULL,
  `quantite_au_moment` int(11) DEFAULT NULL,
  `seuil_au_moment` int(11) DEFAULT NULL,
  `article_id` int(11) NOT NULL,
  `effectue_par_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_AC88B6DF7294869C` (`article_id`),
  KEY `IDX_AC88B6DFFAE26142` (`effectue_par_id`),
  CONSTRAINT `FK_AC88B6DF7294869C` FOREIGN KEY (`article_id`) REFERENCES `article` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_AC88B6DFFAE26142` FOREIGN KEY (`effectue_par_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historique_reapprovisionnement`
--

LOCK TABLES `historique_reapprovisionnement` WRITE;
/*!40000 ALTER TABLE `historique_reapprovisionnement` DISABLE KEYS */;
/*!40000 ALTER TABLE `historique_reapprovisionnement` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `machine`
--

DROP TABLE IF EXISTS `machine`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `machine` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `category_id` int(11) NOT NULL,
  `sub_category_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_1505DF8412469DE2` (`category_id`),
  KEY `IDX_1505DF84F7BFE87C` (`sub_category_id`),
  CONSTRAINT `FK_1505DF8412469DE2` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`),
  CONSTRAINT `FK_1505DF84F7BFE87C` FOREIGN KEY (`sub_category_id`) REFERENCES `sub_category` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `machine`
--

LOCK TABLES `machine` WRITE;
/*!40000 ALTER TABLE `machine` DISABLE KEYS */;
/*!40000 ALTER TABLE `machine` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messenger_messages`
--

DROP TABLE IF EXISTS `messenger_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messenger_messages` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `body` longtext NOT NULL,
  `headers` longtext NOT NULL,
  `queue_name` varchar(190) NOT NULL,
  `created_at` datetime NOT NULL,
  `available_at` datetime NOT NULL,
  `delivered_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750` (`queue_name`,`available_at`,`delivered_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messenger_messages`
--

LOCK TABLES `messenger_messages` WRITE;
/*!40000 ALTER TABLE `messenger_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `messenger_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mouvement_caisse`
--

DROP TABLE IF EXISTS `mouvement_caisse`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mouvement_caisse` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(10) NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `devise` varchar(3) NOT NULL,
  `provenance` varchar(255) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `categorie` varchar(50) DEFAULT NULL,
  `date_mouvement` date NOT NULL,
  `created_at` datetime NOT NULL,
  `devis_id` int(11) DEFAULT NULL,
  `effectue_par_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_C8E3DDFE41DEFADA` (`devis_id`),
  KEY `IDX_C8E3DDFEFAE26142` (`effectue_par_id`),
  CONSTRAINT `FK_C8E3DDFE41DEFADA` FOREIGN KEY (`devis_id`) REFERENCES `devis` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_C8E3DDFEFAE26142` FOREIGN KEY (`effectue_par_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mouvement_caisse`
--

LOCK TABLES `mouvement_caisse` WRITE;
/*!40000 ALTER TABLE `mouvement_caisse` DISABLE KEYS */;
/*!40000 ALTER TABLE `mouvement_caisse` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service`
--

DROP TABLE IF EXISTS `service`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service`
--

LOCK TABLES `service` WRITE;
/*!40000 ALTER TABLE `service` DISABLE KEYS */;
INSERT INTO `service` VALUES (1,'numérique'),(2,'infographie'),(3,'numérique');
/*!40000 ALTER TABLE `service` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sub_category`
--

DROP TABLE IF EXISTS `sub_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sub_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `category_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_BCE3F79812469DE2` (`category_id`),
  CONSTRAINT `FK_BCE3F79812469DE2` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sub_category`
--

LOCK TABLES `sub_category` WRITE;
/*!40000 ALTER TABLE `sub_category` DISABLE KEYS */;
/*!40000 ALTER TABLE `sub_category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(180) NOT NULL,
  `roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`roles`)),
  `password` varchar(255) NOT NULL,
  `localisation` varchar(255) DEFAULT NULL,
  `actif` tinyint(4) NOT NULL,
  `is_chef_service` tinyint(4) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `is_chef_comptable` tinyint(4) NOT NULL DEFAULT 0,
  `is_pdg` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_IDENTIFIER_EMAIL` (`email`),
  KEY `IDX_8D93D649ED5CA9E6` (`service_id`),
  CONSTRAINT `FK_8D93D649ED5CA9E6` FOREIGN KEY (`service_id`) REFERENCES `service` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` VALUES (4,'admin@imprimerie.com','[\"ROLE_ADMIN\"]','$2y$13$a1GEUeKLLqnA2.BP3mhtgOePF3Jr0kyisvp0uFzbenM6yrXUAsyOu',NULL,0,0,NULL,0,0),(8,'admin@dispromalt.com','[\"ROLE_ADMIN\"]','<LE_CODE_COPIÉ>',NULL,0,0,NULL,0,0);
/*!40000 ALTER TABLE `user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `visite`
--

DROP TABLE IF EXISTS `visite`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `visite` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom_visiteur` varchar(255) NOT NULL,
  `departement` varchar(255) NOT NULL,
  `motif` varchar(255) NOT NULL,
  `statut` varchar(50) NOT NULL,
  `date_arrivee` datetime DEFAULT NULL,
  `telephone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `piece_identite` varchar(255) DEFAULT NULL,
  `responsable_delegue_id` int(11) DEFAULT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'VISITE',
  `avec_dg` tinyint(4) NOT NULL DEFAULT 0,
  `motif_delegation` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_B09C8CBB36AA666F` (`responsable_delegue_id`),
  CONSTRAINT `FK_B09C8CBB36AA666F` FOREIGN KEY (`responsable_delegue_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `visite`
--

LOCK TABLES `visite` WRITE;
/*!40000 ALTER TABLE `visite` DISABLE KEYS */;
/*!40000 ALTER TABLE `visite` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-12 12:17:12
