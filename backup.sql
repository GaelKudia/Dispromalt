-- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: dispromalt_db
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
-- Current Database: `dispromalt_db`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `dispromalt_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `dispromalt_db`;

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
  PRIMARY KEY (`id`),
  KEY `IDX_23A0E6612469DE2` (`category_id`),
  KEY `IDX_23A0E66F7BFE87C` (`sub_category_id`),
  KEY `IDX_23A0E66F6B75B26` (`machine_id`),
  CONSTRAINT `FK_23A0E6612469DE2` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`),
  CONSTRAINT `FK_23A0E66F6B75B26` FOREIGN KEY (`machine_id`) REFERENCES `machine` (`id`),
  CONSTRAINT `FK_23A0E66F7BFE87C` FOREIGN KEY (`sub_category_id`) REFERENCES `sub_category` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `article`
--

LOCK TABLES `article` WRITE;
/*!40000 ALTER TABLE `article` DISABLE KEYS */;
INSERT INTO `article` VALUES (1,'papier A4',5,5,NULL,NULL,NULL),(2,'vinyle',20,19,NULL,NULL,NULL),(3,'Gilet',47,5,NULL,NULL,NULL),(4,'YELLOW',17,5,NULL,1,1),(7,'vinyl',10,5,NULL,3,NULL),(8,'BLACK OPAQUE',20,5,NULL,2,NULL),(9,'POLYSTHER',5,2,NULL,3,NULL),(10,'POLYESTHER',59,8,NULL,3,NULL),(11,'POLYESTHER',5,3,NULL,3,NULL),(12,'POLYESTHER',8,3,NULL,3,NULL);
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
  PRIMARY KEY (`id`),
  KEY `IDX_2F501C367294869C` (`article_id`),
  CONSTRAINT `FK_2F501C367294869C` FOREIGN KEY (`article_id`) REFERENCES `article` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `article_attribut`
--

LOCK TABLES `article_attribut` WRITE;
/*!40000 ALTER TABLE `article_attribut` DISABLE KEYS */;
INSERT INTO `article_attribut` VALUES (1,NULL,NULL,'ROSE',NULL,NULL,10),(2,NULL,NULL,'BLANC',NULL,NULL,11),(3,NULL,NULL,'ROSE',NULL,NULL,12);
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bon_sortie`
--

LOCK TABLES `bon_sortie` WRITE;
/*!40000 ALTER TABLE `bon_sortie` DISABLE KEYS */;
INSERT INTO `bon_sortie` VALUES (1,'jea','impression','maintenance interne',0,'2026-05-22 14:16:20','Sortie Validée'),(2,'gael@dispromalt.cd','Impression','manger',0,'2026-05-27 11:44:13','Sortie Validée'),(3,'gael@dispromalt.cd','Impression','réunion',0,'2026-06-29 16:15:33','Validé Coordon'),(4,'gael@dispromalt.cd','Impression','réunion',0,'2026-07-03 03:17:09','Validé Coordon'),(5,'gael@dispromalt.cd','Impression','dd',0,'2026-07-30 11:56:21','Validé Coordon');
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `category`
--

LOCK TABLES `category` WRITE;
/*!40000 ALTER TABLE `category` DISABLE KEYS */;
INSERT INTO `category` VALUES (1,'MEDIA'),(2,'ENCRE');
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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `demande`
--

LOCK TABLES `demande` WRITE;
/*!40000 ALTER TABLE `demande` DISABLE KEYS */;
INSERT INTO `demande` VALUES (1,'Impression','papier A4',1,'Livré par le Stock',NULL,'0000-00-00 00:00:00',NULL,NULL,NULL,NULL),(2,'Impression','papier A4',3,'Livré par le Stock',NULL,'0000-00-00 00:00:00',NULL,NULL,NULL,NULL),(3,'Impression','papier A4',1,'Livré par le Stock',NULL,'0000-00-00 00:00:00',NULL,NULL,NULL,NULL),(4,'Impression','vinyle',3,'Livré par le Stock',NULL,'0000-00-00 00:00:00',NULL,NULL,NULL,NULL),(5,'Impression','vinyle',2,'Validé Compta',NULL,'0000-00-00 00:00:00',NULL,NULL,NULL,NULL),(6,'Impression','vinyle',2,'En attente','Bumbu','2026-05-28 12:13:31',NULL,NULL,NULL,NULL),(7,'Impression','vinyle',2,'En attente',NULL,'2026-05-28 12:38:56',NULL,NULL,NULL,NULL),(8,'Impression','gilet',20,'En attente','Lemba','2026-06-28 11:53:52','Direction',NULL,NULL,NULL),(9,'Impression','gilet',18,'En attente','Gombe','2026-06-29 16:09:39','Direction',NULL,NULL,NULL),(10,'Impression','gilet',3,'Validé Compta','Kintambo','2026-07-03 03:16:09',NULL,NULL,NULL,NULL),(11,'Impression','YELLOW',3,'Livré par le Stock',NULL,'2026-07-14 16:30:55',NULL,NULL,NULL,NULL),(12,'Impression','gilet',5,'En attente','Kintambo','2026-07-16 13:05:02',NULL,NULL,NULL,NULL),(13,'Impression','YELLOW',3,'En attente',NULL,'2026-07-16 13:05:58',NULL,NULL,NULL,NULL),(14,'Impression','POLYSTHER',1,'En attente',NULL,'2026-07-16 13:27:06',NULL,NULL,NULL,NULL),(15,'Impression','POLYSTHER',3,'En attente',NULL,'2026-07-28 15:59:10',NULL,NULL,NULL,NULL),(16,'Impression','POLYHESTER',6,'En attente',NULL,'2026-07-30 11:23:39',NULL,NULL,NULL,NULL),(17,'Impression','gilet',3,'En attente','Maluku','2026-07-30 11:36:12',NULL,NULL,NULL,NULL),(18,'Impression','POLYESTHER',3,'Livré par le Stock',NULL,'2026-07-30 11:38:08',NULL,NULL,NULL,NULL);
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `devis`
--

LOCK TABLES `devis` WRITE;
/*!40000 ALTER TABLE `devis` DISABLE KEYS */;
INSERT INTO `devis` VALUES (53000.00,1,'sssss',500,'2026-06-28 09:15:51','gael@dispromalt.cd','dddd','ARGENT_ENVOYE','USD','0000-00-00 00:00:00'),(50000.00,2,'sssss',50000,'2026-06-28 09:22:08','gael@dispromalt.cd','fffffsss','ARGENT_ENVOYE','CDF','0000-00-00 00:00:00'),(53000.00,3,'xcxxxx',53000,'2026-06-28 09:24:11','gael@dispromalt.cd','tttgtddd','ATTENTE_DIRECTEUR','USD','0000-00-00 00:00:00'),(50000.00,4,'ssss',50000,'2026-06-28 09:34:54','gael@dispromalt.cd','tttt','EN_ATTENTE_BON_SORTIE','USD','0000-00-00 00:00:00'),(55555.00,5,'aaaaa',55555,'2026-07-03 02:52:03','gael@dispromalt.cd','aaaaa','DECAISSE','USD','2026-07-03 03:09:21'),(65000.00,6,'hhhhh',65000,'2026-07-03 03:24:13','gael@dispromalt.cd','ggggg','DECAISSE','USD',NULL),(65000.00,7,'ttttt',65000,'2026-07-03 03:28:06','gael@dispromalt.cd','tttt','EN_ATTENTE_BON_SORTIE','USD',NULL),(50000.00,8,'dd',50000,'2026-07-17 14:21:41','gael@dispromalt.cd','tttgtddd','ATTENTE_TRAITEMENT','USD',NULL),(55555.00,9,'50 voiture pour le transport \n56 moto',55555,'2026-07-22 17:07:12','gael@dispromalt.cd','achat voiture','ATTENTE_TRAITEMENT','CDF',NULL),(50000.00,10,'qsss',50000,'2026-07-28 12:01:03','gael@dispromalt.cd','ddd','ATTENTE_DIRECTEUR','USD',NULL),(65000.00,11,'ss',65000,'2026-07-28 14:55:20','gael@dispromalt.cd','f','ATTENTE_CAISSE','USD',NULL);
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
/*!40000 ALTER TABLE `doctrine_migration_versions` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `machine`
--

LOCK TABLES `machine` WRITE;
/*!40000 ALTER TABLE `machine` DISABLE KEYS */;
INSERT INTO `machine` VALUES (1,'ANAPURNA',2,1),(2,'ANAPURNA',2,1);
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sub_category`
--

LOCK TABLES `sub_category` WRITE;
/*!40000 ALTER TABLE `sub_category` DISABLE KEYS */;
INSERT INTO `sub_category` VALUES (1,'DIGITAL',2),(2,'ENCRES SERIGRAPHIE',2),(3,'MEDIA',1);
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_IDENTIFIER_EMAIL` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` VALUES (1,'coordon@imprimerie.com','[\"ROLE_COORDON\"]','$2y$13$uGEXhbXxiPpFOWwqZlsO5eTTvHX2/nnLxI5WHYqq5maDCqBJWFkAK',NULL,1),(2,'guerite@imprimerie.com','[\"ROLE_GUERITE\"]','$2y$13$qAtwrq2dH1rf/0V1NSTckuKMJbcZJFO2xCopbmyou7tNlDObLUodu',NULL,1),(3,'compta@imprimerie.com','[\"ROLE_COMPTA\"]','$2y$13$4PBz2pKeGVUfeQJWcvAXv.J.jV94vKpZzzyt1KPG40Xd39TAzucqa',NULL,1),(4,'admin@imprimerie.com','[\"ROLE_ADMIN\"]','$2y$13$5NHwcGRHcCKdfVmZLeR83.9yuGJmdkCItZaaN9c/vUVkjaO607DWi',NULL,0),(5,'gael@dispromalt.cd','[]','$2y$13$dTaaySYVDqWOUODR7gTQDOpg8NMcPes./SV98sZCM0AkBn3rTpM7a',NULL,0),(6,'accueil@dispromalt.cd','[\"ROLE_RECEPTION\"]','$2y$13$IaZpLAHiedVL1BWFMxFBwe.PbB4VlanYWJzS9uikYWFJoet/6ITU6',NULL,1),(7,'depot@imprimerie.com','[\"ROLE_STOCK\"]','$2y$13$pslXeUEEiLYJIw5q1uKHNeC7Cq2gwcZ9EGBApVEyo2Hd9ikPmuv9y',NULL,1),(8,'admin@dispromalt.com','[\"ROLE_ADMIN\"]','<LE_CODE_COPIÉ>',NULL,0),(9,'directeur@dispromalt.cd','[\"ROLE_DG\"]','$2y$13$gkYHgx1zqOyE0bjzkvUWeOvYaWY1VI/VUQZtEOuZET0/HCho028.O',NULL,1),(10,'chefcompta@imprimerie.com','[\"ROLE_COMPTA\",\"ROLE_USER\",\"ROLE_CHEF_COMPTA\",\"ROLE_CAISSIER\"]','$2y$13$SY49SxhmWxZ5d3LFgVVx0OkitmLbjg4Mz69jOTUEfzXCmwa.IDfHC',NULL,1),(11,'axel@imprimerie.cd','[\"ROLE_USER\",\"ROLE_CAISSIER\"]','$2y$13$JKhsPiOP/zL1PhqPU4cvw.dcFsHQTXAMvNq3enX468Gc5eHgDz5jq',NULL,1),(12,'gael@comptable.cd','[\"ROLE_COMPTA\",\"ROLE_USER\",\"ROLE_CHEF_COMPTA\"]','$2y$13$0mZ7/ZzZhpeBpPipD5h.quRZzXVIV4LeAqkgWXyeu3WEwjbnbuisW',NULL,1);
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `visite`
--

LOCK TABLES `visite` WRITE;
/*!40000 ALTER TABLE `visite` DISABLE KEYS */;
INSERT INTO `visite` VALUES (1,'axel','numérique','achat','TERMINE',NULL,NULL,NULL,NULL),(2,'axel','impression','réunion','A_L_ACCUEIL',NULL,NULL,NULL,NULL),(3,'axel','numérique','réunion','RESERVE',NULL,NULL,NULL,NULL),(4,'gael','numérique','réunion','RESERVE',NULL,NULL,NULL,NULL),(5,'ff','fff','ddd','RESERVE',NULL,NULL,NULL,NULL),(6,'kudia gael','commercial','réunion','A_L_ACCUEIL',NULL,NULL,NULL,NULL),(7,'dd','ddd','ddd','RESERVE',NULL,NULL,NULL,NULL),(8,'dd','dd','dd','RESERVE',NULL,NULL,NULL,NULL);
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

-- Dump completed on 2026-08-14 10:37:43
