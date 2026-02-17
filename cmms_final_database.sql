-- MySQL dump 10.13  Distrib 8.0.40, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: cmms
-- ------------------------------------------------------
-- Server version	5.5.5-10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `employee_details`
--

DROP TABLE IF EXISTS `employee_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_details` (
  `employee_detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `employee_code` varchar(50) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `work_center_id` int(11) DEFAULT NULL,
  `supervisor_user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`employee_detail_id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `employee_code` (`employee_code`),
  KEY `fk_ed_wc` (`work_center_id`),
  KEY `fk_ed_supervisor` (`supervisor_user_id`),
  CONSTRAINT `fk_ed_supervisor` FOREIGN KEY (`supervisor_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ed_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ed_wc` FOREIGN KEY (`work_center_id`) REFERENCES `work_center` (`work_center_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_details`
--

LOCK TABLES `employee_details` WRITE;
/*!40000 ALTER TABLE `employee_details` DISABLE KEYS */;
/*!40000 ALTER TABLE `employee_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipment`
--

DROP TABLE IF EXISTS `equipment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `equipment` (
  `equipment_id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_code` varchar(50) NOT NULL,
  `equipment_name` varchar(150) NOT NULL,
  `floc_id` int(11) DEFAULT NULL,
  `work_center_id` int(11) DEFAULT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `serial_no` varchar(100) DEFAULT NULL,
  `model_no` varchar(100) DEFAULT NULL,
  `install_date` date DEFAULT NULL,
  `criticality` enum('LOW','MEDIUM','HIGH') NOT NULL DEFAULT 'LOW',
  `status` enum('ACTIVE','INACTIVE','RETIRED') NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (`equipment_id`),
  UNIQUE KEY `uq_equipment_code` (`equipment_code`),
  KEY `fk_equipment_floc` (`floc_id`),
  KEY `fk_equipment_wc` (`work_center_id`),
  KEY `fk_equipment_vendor` (`vendor_id`),
  CONSTRAINT `fk_equipment_floc` FOREIGN KEY (`floc_id`) REFERENCES `functional_location` (`floc_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_equipment_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendor` (`vendor_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_equipment_wc` FOREIGN KEY (`work_center_id`) REFERENCES `work_center` (`work_center_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipment`
--

LOCK TABLES `equipment` WRITE;
/*!40000 ALTER TABLE `equipment` DISABLE KEYS */;
INSERT INTO `equipment` VALUES (1,'AC-B201','Air Conditioner',6,2,1,'AC-2024-B201','DAIKIN-FTKM25','2024-01-15','HIGH','ACTIVE'),(2,'AC-B202','Air Conditioner',7,2,1,'AC-2024-B202','DAIKIN-FTKM25','2024-01-15','HIGH','ACTIVE'),(3,'AC-B301','Air Conditioner',16,2,1,'AC-2024-B301','MITSU-MSY18','2024-01-20','HIGH','ACTIVE'),(4,'AC-C201','Air Conditioner',26,2,1,'AC-2024-C201','DAIKIN-FTKM25','2024-02-01','HIGH','ACTIVE'),(5,'AC-C301','Air Conditioner',36,2,1,'AC-2024-C301','MITSU-MSY18','2024-02-01','HIGH','ACTIVE'),(6,'AC-OLD-2F','Air Conditioner',2,2,1,'AC-2023-OLD2','CARRIER-CV20','2023-06-10','MEDIUM','ACTIVE'),(7,'AC-OLD-3F','Air Conditioner',3,2,1,'AC-2023-OLD3','CARRIER-CV20','2023-06-10','MEDIUM','ACTIVE'),(8,'AC-B206','Air Conditioner',11,2,1,'AC-2024-B206','DAIKIN-FTKM25','2024-01-25','HIGH','ACTIVE'),(9,'AC-C206','Air Conditioner',31,2,1,'AC-2024-C206','MITSU-MSY18','2024-02-05','HIGH','ACTIVE'),(10,'AC-B210','Air Conditioner',15,2,1,'AC-2024-B210','DAIKIN-FTKM35','2024-01-30','HIGH','ACTIVE'),(11,'MIC-B201','Wireless Microphone',6,4,2,'MIC-2024-B201','SHURE-BLX24','2024-03-01','MEDIUM','ACTIVE'),(12,'MIC-B301','Wireless Microphone',16,4,2,'MIC-2024-B301','SHURE-BLX24','2024-03-01','MEDIUM','ACTIVE'),(13,'MIC-C201','Wireless Microphone',26,4,2,'MIC-2024-C201','SENNHEISER-EW','2024-03-05','MEDIUM','ACTIVE'),(14,'MIC-C301','Wireless Microphone',36,4,2,'MIC-2024-C301','SENNHEISER-EW','2024-03-05','MEDIUM','ACTIVE'),(15,'MIC-B210','Conference Microphone',15,4,2,'MIC-2024-B210','SHURE-MXA310','2024-03-10','MEDIUM','ACTIVE'),(16,'MIC-C210','Conference Microphone',35,4,2,'MIC-2024-C210','SHURE-MXA310','2024-03-10','MEDIUM','ACTIVE'),(17,'MIC-OLD-1F','Wired Microphone',1,4,2,'MIC-2023-OLD1','SHURE-SM58','2023-05-15','LOW','ACTIVE'),(18,'MIC-LOBBY-B','PA Microphone',46,4,2,'MIC-2024-LOBB','BOSCH-LBB','2024-03-15','MEDIUM','ACTIVE'),(19,'CCTV-B2-CORR','CCTV Camera',46,5,3,'CAM-2024-B2C','HIKVISION-DS2','2024-01-10','HIGH','ACTIVE'),(20,'CCTV-B3-CORR','CCTV Camera',46,5,3,'CAM-2024-B3C','HIKVISION-DS2','2024-01-10','HIGH','ACTIVE'),(21,'CCTV-C2-CORR','CCTV Camera',47,5,3,'CAM-2024-C2C','HIKVISION-DS2','2024-01-15','HIGH','ACTIVE'),(22,'CCTV-C3-CORR','CCTV Camera',47,5,3,'CAM-2024-C3C','HIKVISION-DS2','2024-01-15','HIGH','ACTIVE'),(23,'CCTV-LOBBY-B','CCTV Camera',46,5,3,'CAM-2024-LOBB','DAHUA-IPC','2024-01-20','HIGH','ACTIVE'),(24,'CCTV-LOBBY-C','CCTV Camera',47,5,3,'CAM-2024-LOBC','DAHUA-IPC','2024-01-20','HIGH','ACTIVE'),(25,'CCTV-B201','CCTV Camera',6,5,3,'CAM-2024-B201','HIKVISION-DS2','2024-01-25','MEDIUM','ACTIVE'),(26,'CCTV-B301','CCTV Camera',16,5,3,'CAM-2024-B301','HIKVISION-DS2','2024-01-25','MEDIUM','ACTIVE'),(27,'CCTV-C201','CCTV Camera',26,5,3,'CAM-2024-C201','DAHUA-IPC','2024-02-01','MEDIUM','ACTIVE'),(28,'CCTV-C301','CCTV Camera',36,5,3,'CAM-2024-C301','DAHUA-IPC','2024-02-01','MEDIUM','ACTIVE'),(29,'CCTV-OLD-1F','CCTV Camera',1,5,3,'CAM-2023-OLD1','HIKVISION-DS1','2023-08-10','MEDIUM','ACTIVE'),(30,'CCTV-OLD-2F','CCTV Camera',2,5,3,'CAM-2023-OLD2','HIKVISION-DS1','2023-08-10','MEDIUM','ACTIVE'),(31,'FIRE-B2-CORR','Fire Alarm Panel',46,7,5,'FIRE-2024-B2C','NOTIFIER-NFS','2024-01-05','HIGH','ACTIVE'),(32,'FIRE-B3-CORR','Fire Alarm Panel',46,7,5,'FIRE-2024-B3C','NOTIFIER-NFS','2024-01-05','HIGH','ACTIVE'),(33,'FIRE-C2-CORR','Fire Alarm Panel',47,7,5,'FIRE-2024-C2C','NOTIFIER-NFS','2024-01-08','HIGH','ACTIVE'),(34,'FIRE-C3-CORR','Fire Alarm Panel',47,7,5,'FIRE-2024-C3C','NOTIFIER-NFS','2024-01-08','HIGH','ACTIVE'),(35,'FIRE-LOBBY-B','Fire Alarm Panel',46,7,5,'FIRE-2024-LOBB','SIMPLEX-4100','2024-01-10','HIGH','ACTIVE'),(36,'FIRE-LOBBY-C','Fire Alarm Panel',47,7,5,'FIRE-2024-LOBC','SIMPLEX-4100','2024-01-10','HIGH','ACTIVE'),(37,'FIRE-OLD-1F','Fire Alarm Panel',1,7,5,'FIRE-2023-OLD1','SIMPLEX-4002','2023-07-01','HIGH','ACTIVE'),(38,'FIRE-OLD-2F','Fire Alarm Panel',2,7,5,'FIRE-2023-OLD2','SIMPLEX-4002','2023-07-01','HIGH','ACTIVE'),(39,'FIRE-B206','Fire Alarm Panel',11,7,5,'FIRE-2024-B206','NOTIFIER-NFS','2024-01-12','HIGH','ACTIVE'),(40,'FIRE-C206','Fire Alarm Panel',31,7,5,'FIRE-2024-C206','NOTIFIER-NFS','2024-01-12','HIGH','ACTIVE'),(41,'ELEV-B','Elevator',46,6,4,'ELEV-2023-B','OTIS-GEN2','2023-12-01','HIGH','ACTIVE'),(42,'ELEV-C','Elevator',47,6,4,'ELEV-2023-C','OTIS-GEN2','2023-12-01','HIGH','ACTIVE'),(43,'ELEV-OLD-A','Elevator',1,6,4,'ELEV-2020-OA','KONE-MONO','2020-06-15','HIGH','ACTIVE'),(44,'ELEV-OLD-B','Elevator',1,6,4,'ELEV-2020-OB','KONE-MONO','2020-06-15','HIGH','ACTIVE'),(45,'SERV-ELEV-B','Elevator',46,6,4,'ELEV-2023-SB','OTIS-FREIGHT','2023-12-10','MEDIUM','ACTIVE'),(46,'SERV-ELEV-C','Elevator',47,6,4,'ELEV-2023-SC','OTIS-FREIGHT','2023-12-10','MEDIUM','ACTIVE'),(47,'MON-B201','Classroom Monitor',6,8,8,'MON-2024-B201','DELL-P2422H','2024-02-10','MEDIUM','ACTIVE'),(48,'MON-B202','Classroom Monitor',7,8,8,'MON-2024-B202','DELL-P2422H','2024-02-10','MEDIUM','ACTIVE'),(49,'MON-B301','Classroom Monitor',16,8,8,'MON-2024-B301','LG-24MP88','2024-02-15','MEDIUM','ACTIVE'),(50,'MON-C201','Classroom Monitor',26,8,8,'MON-2024-C201','DELL-P2422H','2024-02-20','MEDIUM','ACTIVE'),(51,'MON-C301','Classroom Monitor',36,8,8,'MON-2024-C301','LG-24MP88','2024-02-20','MEDIUM','ACTIVE'),(52,'MON-B206','Lab Monitor',11,8,8,'MON-2024-B206','ASUS-VG27','2024-03-01','MEDIUM','ACTIVE'),(53,'MON-B207','Lab Monitor',12,8,8,'MON-2024-B207','ASUS-VG27','2024-03-01','MEDIUM','ACTIVE'),(54,'MON-C206','Lab Monitor',31,8,8,'MON-2024-C206','ASUS-VG27','2024-03-05','MEDIUM','ACTIVE'),(55,'MON-C207','Lab Monitor',32,8,8,'MON-2024-C207','ASUS-VG27','2024-03-05','MEDIUM','ACTIVE'),(56,'MON-B210','Conference Monitor',15,8,8,'MON-2024-B210','SAMSUNG-55','2024-03-10','MEDIUM','ACTIVE'),(57,'MON-C210','Conference Monitor',35,8,8,'MON-2024-C210','SAMSUNG-55','2024-03-10','MEDIUM','ACTIVE'),(58,'MON-B208','Office Monitor',13,8,8,'MON-2024-B208','HP-E24','2024-03-15','LOW','ACTIVE'),(59,'MON-C208','Office Monitor',33,8,8,'MON-2024-C208','HP-E24','2024-03-15','LOW','ACTIVE'),(60,'MON-OLD-2F-A','Classroom Monitor',2,8,8,'MON-2023-OLD2A','DELL-P2219H','2023-09-01','LOW','ACTIVE'),(61,'MON-OLD-2F-B','Classroom Monitor',2,8,8,'MON-2023-OLD2B','DELL-P2219H','2023-09-01','LOW','ACTIVE'),(62,'PC-B206','Lab Computer',11,8,8,'PC-2024-B206','DELL-OPTIPLEX','2024-03-20','MEDIUM','ACTIVE'),(63,'PC-B207','Lab Computer',12,8,8,'PC-2024-B207','DELL-OPTIPLEX','2024-03-20','MEDIUM','ACTIVE'),(64,'PC-C206','Lab Computer',31,8,8,'PC-2024-C206','HP-PRODESK','2024-03-25','MEDIUM','ACTIVE'),(65,'PC-C207','Lab Computer',32,8,8,'PC-2024-C207','HP-PRODESK','2024-03-25','MEDIUM','ACTIVE'),(66,'PC-B208','Office Computer',13,8,8,'PC-2024-B208','LENOVO-M720','2024-04-01','MEDIUM','ACTIVE'),(67,'PC-C208','Office Computer',33,8,8,'PC-2024-C208','LENOVO-M720','2024-04-01','MEDIUM','ACTIVE'),(68,'PC-B210','Conference PC',15,8,8,'PC-2024-B210','DELL-OPTIPLEX','2024-04-05','LOW','ACTIVE'),(69,'PC-C210','Conference PC',35,8,8,'PC-2024-C210','DELL-OPTIPLEX','2024-04-05','LOW','ACTIVE'),(70,'PC-OLD-2F-A','Office Computer',2,8,8,'PC-2023-OLD2A','HP-ELITE','2023-10-01','LOW','ACTIVE'),(71,'PC-OLD-2F-B','Office Computer',2,8,8,'PC-2023-OLD2B','HP-ELITE','2023-10-01','LOW','ACTIVE'),(72,'PC-OLD-3F','Office Computer',3,8,8,'PC-2023-OLD3','LENOVO-M710','2023-10-05','LOW','ACTIVE'),(73,'TOILET-B-2M','Mens Toilet B 2F',46,9,7,'TOTO-B2M','TOTO-BASIN','2024-01-01','MEDIUM','ACTIVE'),(74,'TOILET-B-2F','Womens Toilet B 2F',46,9,7,'TOTO-B2F','TOTO-BASIN','2024-01-01','MEDIUM','ACTIVE'),(75,'TOILET-B-3M','Mens Toilet B 3F',46,9,7,'TOTO-B3M','TOTO-BASIN','2024-01-01','MEDIUM','ACTIVE'),(76,'TOILET-B-3F','Womens Toilet B 3F',46,9,7,'TOTO-B3F','TOTO-BASIN','2024-01-01','MEDIUM','ACTIVE'),(77,'TOILET-C-2M','Mens Toilet C 2F',47,9,7,'COTTO-C2M','COTTO-BASIN','2024-01-05','MEDIUM','ACTIVE'),(78,'TOILET-C-2F','Womens Toilet C 2F',47,9,7,'COTTO-C2F','COTTO-BASIN','2024-01-05','MEDIUM','ACTIVE'),(79,'TOILET-C-3M','Mens Toilet C 3F',47,9,7,'COTTO-C3M','COTTO-BASIN','2024-01-05','MEDIUM','ACTIVE'),(80,'TOILET-C-3F','Womens Toilet C 3F',47,9,7,'COTTO-C3F','COTTO-BASIN','2024-01-05','MEDIUM','ACTIVE'),(81,'BULB-B2-CORR','LED Lights B 2F Corridor',46,1,6,'LED-B2C-GRP','PHILIPS-LED20','2024-01-01','LOW','ACTIVE'),(82,'BULB-B3-CORR','LED Lights B 3F Corridor',46,1,6,'LED-B3C-GRP','PHILIPS-LED20','2024-01-01','LOW','ACTIVE'),(83,'BULB-C2-CORR','LED Lights C 2F Corridor',47,1,6,'LED-C2C-GRP','PHILIPS-LED20','2024-01-05','LOW','ACTIVE'),(84,'BULB-C3-CORR','LED Lights C 3F Corridor',47,1,6,'LED-C3C-GRP','PHILIPS-LED20','2024-01-05','LOW','ACTIVE'),(85,'BULB-B201','LED Lights B201',6,1,6,'LED-B201-GRP','PHILIPS-LED15','2024-01-10','LOW','ACTIVE'),(86,'BULB-B301','LED Lights B301',16,1,6,'LED-B301-GRP','PHILIPS-LED15','2024-01-10','LOW','ACTIVE'),(87,'BULB-C201','LED Lights C201',26,1,6,'LED-C201-GRP','PHILIPS-LED15','2024-01-15','LOW','ACTIVE'),(88,'BULB-C301','LED Lights C301',36,1,6,'LED-C301-GRP','PHILIPS-LED15','2024-01-15','LOW','ACTIVE'),(89,'BULB-LOBBY-B','LED Lights Lobby B',46,1,6,'LED-LOBB-GRP','OSRAM-LED30','2024-01-20','MEDIUM','ACTIVE'),(90,'BULB-LOBBY-C','LED Lights Lobby C',47,1,6,'LED-LOBC-GRP','OSRAM-LED30','2024-01-20','MEDIUM','ACTIVE'),(91,'DOOR-B-MAIN','Building B Main Door',46,10,10,'DOOR-B-MAIN','AUTO-SLIDE-1','2023-12-01','HIGH','ACTIVE'),(92,'DOOR-C-MAIN','Building C Main Door',47,10,10,'DOOR-C-MAIN','AUTO-SLIDE-1','2023-12-01','HIGH','ACTIVE'),(93,'DOOR-B201','Classroom Door B201',6,10,10,'DOOR-B201','WOOD-STANDARD','2024-01-01','LOW','ACTIVE'),(94,'DOOR-B301','Classroom Door B301',16,10,10,'DOOR-B301','WOOD-STANDARD','2024-01-01','LOW','ACTIVE'),(95,'DOOR-C201','Classroom Door C201',26,10,10,'DOOR-C201','WOOD-STANDARD','2024-01-05','LOW','ACTIVE'),(96,'DOOR-C301','Classroom Door C301',36,10,10,'DOOR-C301','WOOD-STANDARD','2024-01-05','LOW','ACTIVE'),(97,'DOOR-OLD-MAIN','Old Building Main Door',1,10,10,'DOOR-OLD-MAIN','MANUAL-SWING','2020-01-01','MEDIUM','ACTIVE'),(98,'DOOR-B210','Conference Door B210',15,10,10,'DOOR-B210','GLASS-SWING','2024-01-10','LOW','ACTIVE'),(99,'DOOR-C210','Conference Door C210',35,10,10,'DOOR-C210','GLASS-SWING','2024-01-10','LOW','ACTIVE'),(100,'DOOR-FIRE-B2','Fire Door B 2F',46,10,10,'DOOR-FIRE-B2','FIRE-RATED-2H','2024-01-01','HIGH','ACTIVE');
/*!40000 ALTER TABLE `equipment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipment_bom`
--

DROP TABLE IF EXISTS `equipment_bom`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `equipment_bom` (
  `eqbom_id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity` decimal(12,3) NOT NULL DEFAULT 1.000,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`eqbom_id`),
  UNIQUE KEY `uq_eqbom` (`equipment_id`,`material_id`),
  KEY `fk_eqbom_material` (`material_id`),
  CONSTRAINT `fk_eqbom_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`equipment_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_eqbom_material` FOREIGN KEY (`material_id`) REFERENCES `material` (`material_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipment_bom`
--

LOCK TABLES `equipment_bom` WRITE;
/*!40000 ALTER TABLE `equipment_bom` DISABLE KEYS */;
INSERT INTO `equipment_bom` VALUES (1,1,1,1.000,'Standard filter replacement'),(2,1,2,0.500,'Gas refill quantity'),(3,2,1,1.000,'Standard filter replacement'),(4,3,1,1.000,'Standard filter replacement'),(5,4,1,1.000,'Standard filter replacement'),(6,5,1,1.000,'Standard filter replacement'),(7,11,7,2.000,'Battery pack for wireless'),(8,12,7,2.000,'Battery pack for wireless'),(9,13,6,1.000,'XLR Cable'),(10,14,6,1.000,'XLR Cable'),(11,15,7,4.000,'Conference mic batteries'),(12,19,12,0.300,'Network cable per meter'),(13,20,12,0.300,'Network cable per meter'),(14,21,14,2.000,'RJ45 Connectors'),(15,22,14,2.000,'RJ45 Connectors'),(16,23,15,1.000,'Power supply'),(17,31,21,2.000,'Strobe bulb replacement'),(18,32,21,2.000,'Strobe bulb replacement'),(19,33,22,1.000,'Backup battery'),(20,34,22,1.000,'Backup battery'),(21,35,24,1.000,'Fire extinguisher'),(22,41,16,5.000,'Hydraulic oil in liters'),(23,42,16,5.000,'Hydraulic oil in liters'),(24,43,19,1.000,'Door sensor'),(25,44,19,1.000,'Door sensor'),(26,47,26,0.000,'No consumables - reference only'),(27,48,26,0.000,'No consumables - reference only'),(28,62,38,1.000,'RAM upgrade capacity'),(29,63,38,1.000,'RAM upgrade capacity'),(30,64,39,1.000,'SSD upgrade capacity'),(31,73,31,1.000,'Flush valve'),(32,74,31,1.000,'Flush valve'),(33,75,35,10.000,'Monthly toilet paper consumption'),(34,81,26,10.000,'LED bulb group'),(35,82,26,10.000,'LED bulb group'),(36,83,27,8.000,'LED tube group'),(37,91,41,3.000,'Heavy duty hinges'),(38,92,42,1.000,'Door lock'),(39,93,43,1.000,'Door handle set');
/*!40000 ALTER TABLE `equipment_bom` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `functional_location`
--

DROP TABLE IF EXISTS `functional_location`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `functional_location` (
  `floc_id` int(11) NOT NULL AUTO_INCREMENT,
  `floc_code` varchar(50) NOT NULL,
  `floc_name` varchar(100) NOT NULL,
  `parent_floc_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`floc_id`),
  UNIQUE KEY `uq_floc_code` (`floc_code`),
  KEY `fk_floc_parent` (`parent_floc_id`),
  CONSTRAINT `fk_floc_parent` FOREIGN KEY (`parent_floc_id`) REFERENCES `functional_location` (`floc_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `functional_location`
--

LOCK TABLES `functional_location` WRITE;
/*!40000 ALTER TABLE `functional_location` DISABLE KEYS */;
INSERT INTO `functional_location` VALUES (1,'OLD-1F','Old Building - 1st Floor',NULL,'Ground floor of old building',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(2,'OLD-2F','Old Building - 2nd Floor',NULL,'Second floor of old building',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(3,'OLD-3F','Old Building - 3rd Floor',NULL,'Third floor of old building',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(4,'OLD-4F','Old Building - 4th Floor',NULL,'Fourth floor of old building',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(5,'OLD-5F','Old Building - 5th Floor',NULL,'Fifth floor of old building',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(6,'ILC-B-201','Building B Room 201',NULL,'Classroom B201',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(7,'ILC-B-202','Building B Room 202',NULL,'Classroom B202',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(8,'ILC-B-203','Building B Room 203',NULL,'Classroom B203',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(9,'ILC-B-204','Building B Room 204',NULL,'Classroom B204',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(10,'ILC-B-205','Building B Room 205',NULL,'Classroom B205',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(11,'ILC-B-206','Building B Room 206',NULL,'Lab B206',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(12,'ILC-B-207','Building B Room 207',NULL,'Lab B207',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(13,'ILC-B-208','Building B Room 208',NULL,'Office B208',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(14,'ILC-B-209','Building B Room 209',NULL,'Storage B209',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(15,'ILC-B-210','Building B Room 210',NULL,'Conference B210',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(16,'ILC-B-301','Building B Room 301',NULL,'Classroom B301',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(17,'ILC-B-302','Building B Room 302',NULL,'Classroom B302',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(18,'ILC-B-303','Building B Room 303',NULL,'Classroom B303',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(19,'ILC-B-304','Building B Room 304',NULL,'Classroom B304',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(20,'ILC-B-305','Building B Room 305',NULL,'Classroom B305',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(21,'ILC-B-306','Building B Room 306',NULL,'Lab B306',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(22,'ILC-B-307','Building B Room 307',NULL,'Lab B307',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(23,'ILC-B-308','Building B Room 308',NULL,'Office B308',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(24,'ILC-B-309','Building B Room 309',NULL,'Storage B309',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(25,'ILC-B-310','Building B Room 310',NULL,'Conference B310',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(26,'ILC-C-201','Building C Room 201',NULL,'Classroom C201',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(27,'ILC-C-202','Building C Room 202',NULL,'Classroom C202',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(28,'ILC-C-203','Building C Room 203',NULL,'Classroom C203',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(29,'ILC-C-204','Building C Room 204',NULL,'Classroom C204',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(30,'ILC-C-205','Building C Room 205',NULL,'Classroom C205',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(31,'ILC-C-206','Building C Room 206',NULL,'Lab C206',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(32,'ILC-C-207','Building C Room 207',NULL,'Lab C207',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(33,'ILC-C-208','Building C Room 208',NULL,'Office C208',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(34,'ILC-C-209','Building C Room 209',NULL,'Storage C209',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(35,'ILC-C-210','Building C Room 210',NULL,'Conference C210',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(36,'ILC-C-301','Building C Room 301',NULL,'Classroom C301',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(37,'ILC-C-302','Building C Room 302',NULL,'Classroom C302',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(38,'ILC-C-303','Building C Room 303',NULL,'Classroom C303',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(39,'ILC-C-304','Building C Room 304',NULL,'Classroom C304',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(40,'ILC-C-305','Building C Room 305',NULL,'Classroom C305',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(41,'ILC-C-306','Building C Room 306',NULL,'Lab C306',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(42,'ILC-C-307','Building C Room 307',NULL,'Lab C307',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(43,'ILC-C-308','Building C Room 308',NULL,'Office C308',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(44,'ILC-C-309','Building C Room 309',NULL,'Storage C309',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(45,'ILC-C-310','Building C Room 310',NULL,'Conference C310',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(46,'ILC-LOBBY-B','Building B Lobby',NULL,'Main lobby Building B',1,'2025-10-19 12:41:32','2025-10-19 12:41:32'),(47,'ILC-LOBBY-C','Building C Lobby',NULL,'Main lobby Building C',1,'2025-10-19 12:41:32','2025-10-19 12:41:32');
/*!40000 ALTER TABLE `functional_location` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `maintenance_plan`
--

DROP TABLE IF EXISTS `maintenance_plan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `maintenance_plan` (
  `plan_id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_code` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `plan_type` enum('TIME','METER') NOT NULL DEFAULT 'TIME',
  `cycle_days` int(11) DEFAULT NULL,
  `cycle_meter` int(11) DEFAULT NULL,
  `equipment_id` int(11) DEFAULT NULL,
  `floc_id` int(11) DEFAULT NULL,
  `task_list_id` int(11) NOT NULL,
  `assigned_user_id` int(11) DEFAULT NULL,
  `last_gen_date` date DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`plan_id`),
  UNIQUE KEY `uq_plan_code` (`plan_code`),
  KEY `fk_mp_equipment` (`equipment_id`),
  KEY `fk_mp_floc` (`floc_id`),
  KEY `fk_mp_tl` (`task_list_id`),
  KEY `fk_mp_assigned_user` (`assigned_user_id`),
  CONSTRAINT `fk_mp_assigned_user` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mp_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`equipment_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_mp_floc` FOREIGN KEY (`floc_id`) REFERENCES `functional_location` (`floc_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_mp_tl` FOREIGN KEY (`task_list_id`) REFERENCES `task_list` (`task_list_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maintenance_plan`
--

LOCK TABLES `maintenance_plan` WRITE;
/*!40000 ALTER TABLE `maintenance_plan` DISABLE KEYS */;
INSERT INTO `maintenance_plan` VALUES (1,'PM-AC-MONTHLY','AC Monthly Maintenance','TIME',30,NULL,1,6,1,27,'2025-10-15','2025-11-14',1),(2,'PM-MIC-WEEKLY','Microphone Weekly Check','TIME',7,NULL,11,6,3,27,'2025-10-18','2025-10-25',1),(3,'PM-CCTV-MONTH','CCTV Monthly Inspection','TIME',30,NULL,19,46,4,27,'2025-10-10','2025-11-09',1),(4,'PM-FIRE-MONTH','Fire Alarm Monthly Test','TIME',30,NULL,31,46,5,27,'2025-10-12','2025-11-11',1),(5,'PM-ELEV-QUART','Elevator Quarterly Maintenance','TIME',90,NULL,41,46,6,27,'2025-10-01','2025-12-30',1),(6,'PM-MON-WEEK','Monitor Weekly Cleaning','TIME',7,NULL,47,6,7,24,'2025-10-17','2025-10-24',1),(7,'PM-PC-MONTH','Computer Monthly Update','TIME',30,NULL,62,11,8,24,'2025-10-05','2025-11-04',1),(8,'PM-TOILET-DAILY','Toilet Daily Cleaning','TIME',1,NULL,73,46,9,24,'2025-10-19','2025-10-20',1),(9,'PM-BULB-CHECK','LED Bulb Monthly Check','TIME',30,NULL,81,46,10,24,'2025-10-08','2025-11-07',1),(10,'PM-DOOR-QUART','Door Quarterly Inspection','TIME',90,NULL,91,46,11,24,'2025-10-02','2025-12-31',1);
/*!40000 ALTER TABLE `maintenance_plan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `material`
--

DROP TABLE IF EXISTS `material`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `material` (
  `material_id` int(11) NOT NULL AUTO_INCREMENT,
  `material_code` varchar(50) NOT NULL,
  `material_name` varchar(150) NOT NULL,
  `unit_of_measure` varchar(20) NOT NULL DEFAULT 'EA',
  `standard_cost` decimal(12,2) DEFAULT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `min_stock` int(11) NOT NULL DEFAULT 0,
  `on_hand_qty` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`material_id`),
  UNIQUE KEY `uq_material_code` (`material_code`),
  KEY `fk_material_vendor` (`vendor_id`),
  CONSTRAINT `fk_material_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendor` (`vendor_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `material`
--

LOCK TABLES `material` WRITE;
/*!40000 ALTER TABLE `material` DISABLE KEYS */;
INSERT INTO `material` VALUES (1,'MAT-AC-FILTER','AC Air Filter','EA',250.00,1,10,50,1),(2,'MAT-AC-GAS','AC Refrigerant Gas R32','KG',1200.00,1,5,20,1),(3,'MAT-AC-COMP','AC Compressor Belt','EA',350.00,1,3,15,1),(4,'MAT-AC-COIL','AC Evaporator Coil Cleaner','BTL',180.00,1,5,25,1),(5,'MAT-AC-THERM','AC Thermostat','EA',850.00,1,2,10,1),(6,'MAT-MIC-CABLE','Microphone XLR Cable 5m','EA',320.00,2,5,30,1),(7,'MAT-MIC-BATT','Wireless Mic Battery AA','PACK',120.00,2,10,80,1),(8,'MAT-MIC-FOAM','Microphone Foam Windscreen','EA',45.00,2,15,60,1),(9,'MAT-HDMI','HDMI Cable 3m','EA',280.00,2,8,40,1),(10,'MAT-SPEAKER','Ceiling Speaker 6inch','EA',1200.00,2,2,8,1),(11,'MAT-CCTV-CAM','IP Camera 4MP','EA',3500.00,3,2,10,1),(12,'MAT-CCTV-CABLE','CAT6 Network Cable','M',15.00,3,100,500,1),(13,'MAT-CCTV-HDD','Surveillance HDD 2TB','EA',2800.00,3,2,6,1),(14,'MAT-CCTV-CONN','RJ45 Connector','PACK',180.00,3,5,25,1),(15,'MAT-CCTV-PSU','CCTV Power Supply 12V','EA',450.00,3,3,12,1),(16,'MAT-ELEV-OIL','Elevator Hydraulic Oil','LTR',350.00,4,10,40,1),(17,'MAT-ELEV-ROPE','Elevator Steel Rope','M',280.00,4,5,20,1),(18,'MAT-ELEV-BTN','Elevator Push Button','EA',220.00,4,5,15,1),(19,'MAT-ELEV-SENSOR','Elevator Door Sensor','EA',1800.00,4,2,6,1),(20,'MAT-ELEV-BRAKE','Elevator Brake Pad','SET',2500.00,4,1,4,1),(21,'MAT-FIRE-BULB','Fire Alarm Strobe Bulb','EA',180.00,5,10,45,1),(22,'MAT-FIRE-BATT','Fire Alarm Backup Battery','EA',850.00,5,3,12,1),(23,'MAT-FIRE-SMOKE','Smoke Detector','EA',420.00,5,5,20,1),(24,'MAT-FIRE-EXT','Fire Extinguisher 5kg','EA',1200.00,5,10,35,1),(25,'MAT-FIRE-HORN','Fire Alarm Horn','EA',320.00,5,5,18,1),(26,'MAT-BULB-LED','LED Bulb 9W','EA',85.00,6,30,150,1),(27,'MAT-BULB-TUBE','LED Tube Light 18W','EA',180.00,6,20,80,1),(28,'MAT-SWITCH','Light Switch','EA',45.00,6,15,60,1),(29,'MAT-SOCKET','Power Socket','EA',65.00,6,15,55,1),(30,'MAT-BREAKER','Circuit Breaker 20A','EA',320.00,6,5,20,1),(31,'MAT-TOILET-FLUSH','Toilet Flush Valve','EA',450.00,7,3,12,1),(32,'MAT-TOILET-SEAT','Toilet Seat','EA',850.00,7,2,8,1),(33,'MAT-PIPE-PVC','PVC Pipe 4inch','M',120.00,7,10,35,1),(34,'MAT-FAUCET','Basin Faucet','EA',680.00,7,2,10,1),(35,'MAT-TOILET-PAPER','Toilet Paper Roll','PACK',180.00,7,20,100,1),(36,'MAT-MONITOR','24inch LED Monitor','EA',4500.00,8,2,15,1),(37,'MAT-KB-MOUSE','Keyboard and Mouse Set','SET',520.00,8,5,25,1),(38,'MAT-PC-RAM','8GB RAM DDR4','EA',1200.00,8,3,12,1),(39,'MAT-PC-SSD','256GB SSD','EA',1500.00,8,3,10,1),(40,'MAT-NETWORK','Network Switch 8-port','EA',1800.00,8,2,6,1),(41,'MAT-DOOR-HINGE','Door Hinge Heavy Duty','SET',280.00,10,5,20,1),(42,'MAT-DOOR-LOCK','Door Lock Cylinder','EA',650.00,10,3,15,1),(43,'MAT-DOOR-HANDLE','Door Handle Set','SET',420.00,10,5,18,1),(44,'MAT-PAINT','Interior Wall Paint','LTR',180.00,10,10,40,1),(45,'MAT-CEMENT','Portland Cement','BAG',150.00,10,10,35,1);
/*!40000 ALTER TABLE `material` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification`
--

DROP TABLE IF EXISTS `notification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `notif_no` varchar(50) NOT NULL,
  `reported_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('NEW','SCREENED','APPROVED','REJECTED','CLOSED') NOT NULL DEFAULT 'NEW',
  `priority` enum('LOW','MEDIUM','HIGH','URGENT') NOT NULL DEFAULT 'MEDIUM',
  `description` varchar(500) DEFAULT NULL,
  `reporter_name` varchar(120) DEFAULT NULL,
  `reporter_email` varchar(150) DEFAULT NULL,
  `equipment_id` int(11) DEFAULT NULL,
  `floc_id` int(11) DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`notification_id`),
  UNIQUE KEY `uq_notif_no` (`notif_no`),
  KEY `fk_notif_equipment` (`equipment_id`),
  KEY `fk_notif_floc` (`floc_id`),
  KEY `fk_notif_creator_user` (`created_by_user_id`),
  KEY `idx_notification_status` (`status`),
  KEY `idx_notification_priority` (`priority`),
  CONSTRAINT `fk_notif_creator_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_notif_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`equipment_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_notif_floc` FOREIGN KEY (`floc_id`) REFERENCES `functional_location` (`floc_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification`
--

LOCK TABLES `notification` WRITE;
/*!40000 ALTER TABLE `notification` DISABLE KEYS */;
INSERT INTO `notification` VALUES (1,'N-2025-001','2025-10-15 09:30:00','APPROVED','HIGH','AC not cooling properly in B201','John Smith','john.smith@university.edu',1,6,20),(2,'N-2025-002','2025-10-16 10:15:00','APPROVED','MEDIUM','Microphone feedback in B301','Mary Johnson','mary.j@university.edu',12,16,20),(3,'N-2025-003','2025-10-16 14:20:00','APPROVED','HIGH','CCTV camera not recording in corridor','Admin Office','admin@university.edu',19,46,25),(4,'N-2025-004','2025-10-17 08:45:00','APPROVED','URGENT','Fire alarm making beeping sound','Security Guard','security@university.edu',31,46,25),(5,'N-2025-005','2025-10-17 11:30:00','NEW','MEDIUM','Elevator door takes long to close','Jane Doe','jane.doe@university.edu',41,46,20),(6,'N-2025-006','2025-10-18 09:00:00','SCREENED','LOW','Monitor screen has dead pixels','Lab Technician','labtech@university.edu',47,6,20),(7,'N-2025-007','2025-10-18 13:45:00','APPROVED','MEDIUM','Computer running very slow','Teacher Bob','bob.teacher@university.edu',62,11,20),(8,'N-2025-008','2025-10-18 15:20:00','NEW','LOW','Toilet flush not working properly','Student Sarah','sarah.student@university.edu',73,46,25),(9,'N-2025-009','2025-10-19 08:30:00','SCREENED','LOW','LED light flickering in C201','Teacher Mike','mike.t@university.edu',87,26,20),(10,'N-2025-010','2025-10-19 10:00:00','NEW','MEDIUM','Classroom door hinge is loose','Janitor Team','janitor@university.edu',93,6,25),(11,'N-2025-011','2025-10-19 11:15:00','APPROVED','HIGH','AC making loud noise in C301','Department Head','depthead@university.edu',5,36,20),(12,'N-2025-012','2025-10-19 14:30:00','NEW','LOW','CCTV angle needs adjustment','Security Office','security@university.edu',20,46,25);
/*!40000 ALTER TABLE `notification` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_attachment`
--

DROP TABLE IF EXISTS `notification_attachment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_attachment` (
  `attachment_id` int(11) NOT NULL AUTO_INCREMENT,
  `notification_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `uploaded_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attachment_id`),
  KEY `fk_notif_att_notif` (`notification_id`),
  KEY `fk_notif_att_user` (`uploaded_by_user_id`),
  CONSTRAINT `fk_notif_att_notif` FOREIGN KEY (`notification_id`) REFERENCES `notification` (`notification_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notif_att_user` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_attachment`
--

LOCK TABLES `notification_attachment` WRITE;
/*!40000 ALTER TABLE `notification_attachment` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_attachment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_comment`
--

DROP TABLE IF EXISTS `notification_comment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_comment` (
  `comment_id` int(11) NOT NULL AUTO_INCREMENT,
  `notification_id` int(11) NOT NULL,
  `author_user_id` int(11) DEFAULT NULL,
  `comment_text` varchar(500) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`comment_id`),
  KEY `fk_nc_notif` (`notification_id`),
  KEY `fk_nc_author_user` (`author_user_id`),
  CONSTRAINT `fk_nc_author_user` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_nc_notif` FOREIGN KEY (`notification_id`) REFERENCES `notification` (`notification_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_comment`
--

LOCK TABLES `notification_comment` WRITE;
/*!40000 ALTER TABLE `notification_comment` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_comment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_list`
--

DROP TABLE IF EXISTS `task_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_list` (
  `task_list_id` int(11) NOT NULL AUTO_INCREMENT,
  `task_list_code` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `equipment_id` int(11) DEFAULT NULL,
  `work_center_id` int(11) DEFAULT NULL,
  `estimated_hours` decimal(6,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`task_list_id`),
  UNIQUE KEY `uq_task_list_code` (`task_list_code`),
  KEY `fk_tl_equipment` (`equipment_id`),
  KEY `fk_tl_wc` (`work_center_id`),
  KEY `fk_tl_creator_user` (`created_by_user_id`),
  CONSTRAINT `fk_tl_creator_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_tl_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`equipment_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_tl_wc` FOREIGN KEY (`work_center_id`) REFERENCES `work_center` (`work_center_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_list`
--

LOCK TABLES `task_list` WRITE;
/*!40000 ALTER TABLE `task_list` DISABLE KEYS */;
INSERT INTO `task_list` VALUES (1,'TL-AC-MAINT','AC Monthly Maintenance',1,2,2.00,1,26,'2025-10-19 12:48:24','2025-10-19 12:48:24'),(2,'TL-AC-FILTER','AC Filter Replacement',1,2,0.50,1,26,'2025-10-19 12:48:24','2025-10-19 12:48:24'),(3,'TL-MIC-CHECK','Microphone System Check',11,4,1.00,1,26,'2025-10-19 12:48:24','2025-10-19 12:48:24'),(4,'TL-CCTV-INSPECT','CCTV Camera Inspection',19,5,1.50,1,26,'2025-10-19 12:48:24','2025-10-19 12:48:24'),(5,'TL-FIRE-TEST','Fire Alarm Monthly Test',31,7,1.00,1,26,'2025-10-19 12:48:24','2025-10-19 12:48:24'),(6,'TL-ELEV-MAINT','Elevator Quarterly Maintenance',41,6,4.00,1,26,'2025-10-19 12:48:24','2025-10-19 12:48:24'),(7,'TL-MON-CLEAN','Monitor Cleaning',47,8,0.30,1,26,'2025-10-19 12:48:24','2025-10-19 12:48:24'),(8,'TL-PC-UPDATE','Computer Software Update',62,8,1.00,1,26,'2025-10-19 12:48:24','2025-10-19 12:48:24'),(9,'TL-TOILET-CLEAN','Toilet Deep Cleaning',73,9,2.00,1,26,'2025-10-19 12:48:24','2025-10-19 12:48:24'),(10,'TL-BULB-REPLACE','LED Bulb Replacement',85,1,0.25,1,26,'2025-10-19 12:48:24','2025-10-19 12:48:24'),(11,'TL-DOOR-INSPECT','Door Hardware Inspection',93,10,0.50,1,26,'2025-10-19 12:48:24','2025-10-19 12:48:24'),(12,'TL-AC-REPAIR','AC General Repair',1,2,3.00,1,26,'2025-10-19 12:48:24','2025-10-19 12:48:24');
/*!40000 ALTER TABLE `task_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_list_material`
--

DROP TABLE IF EXISTS `task_list_material`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_list_material` (
  `tlm_id` int(11) NOT NULL AUTO_INCREMENT,
  `task_list_id` int(11) NOT NULL,
  `op_seq` int(11) DEFAULT NULL,
  `material_id` int(11) NOT NULL,
  `quantity` decimal(12,3) NOT NULL DEFAULT 1.000,
  `op_seq_key` int(11) GENERATED ALWAYS AS (ifnull(`op_seq`,0)) STORED,
  PRIMARY KEY (`tlm_id`),
  UNIQUE KEY `uq_tlm` (`task_list_id`,`op_seq_key`,`material_id`),
  KEY `fk_tlm_step` (`task_list_id`,`op_seq`),
  KEY `fk_tlm_mat` (`material_id`),
  CONSTRAINT `fk_tlm_mat` FOREIGN KEY (`material_id`) REFERENCES `material` (`material_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_tlm_step` FOREIGN KEY (`task_list_id`, `op_seq`) REFERENCES `task_list_operation` (`task_list_id`, `op_seq`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tlm_tl` FOREIGN KEY (`task_list_id`) REFERENCES `task_list` (`task_list_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_list_material`
--

LOCK TABLES `task_list_material` WRITE;
/*!40000 ALTER TABLE `task_list_material` DISABLE KEYS */;
INSERT INTO `task_list_material` (`tlm_id`, `task_list_id`, `op_seq`, `material_id`, `quantity`) VALUES (1,1,10,1,1.000),(2,1,40,4,0.500),(3,2,20,1,1.000),(4,3,30,7,1.000),(5,3,10,8,1.000),(6,4,20,14,1.000),(7,5,20,21,0.100),(8,6,20,16,2.000),(9,7,NULL,26,1.000),(10,8,NULL,39,1.000),(11,9,50,35,2.000),(12,10,30,26,1.000),(13,11,NULL,41,1.000),(14,12,NULL,1,1.000),(15,12,NULL,2,0.500);
/*!40000 ALTER TABLE `task_list_material` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_list_operation`
--

DROP TABLE IF EXISTS `task_list_operation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_list_operation` (
  `task_list_id` int(11) NOT NULL,
  `op_seq` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `std_time_min` int(11) DEFAULT NULL,
  `safety_notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`task_list_id`,`op_seq`),
  CONSTRAINT `fk_tlop_tl` FOREIGN KEY (`task_list_id`) REFERENCES `task_list` (`task_list_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_list_operation`
--

LOCK TABLES `task_list_operation` WRITE;
/*!40000 ALTER TABLE `task_list_operation` DISABLE KEYS */;
INSERT INTO `task_list_operation` VALUES (1,10,'Check and clean air filter',20,'Turn off power before maintenance'),(1,20,'Inspect evaporator coil',30,'Wear safety gloves'),(1,30,'Check refrigerant level',25,'Ensure proper ventilation'),(1,40,'Clean condenser coil',30,'Use approved cleaning agents'),(1,50,'Test thermostat operation',15,'Follow electrical safety procedures'),(2,10,'Remove old filter',10,'Wear dust mask'),(2,20,'Install new filter',10,'Ensure correct orientation'),(2,30,'Test airflow',10,'Check for proper sealing'),(3,10,'Inspect microphone physical condition',15,'Handle with care'),(3,20,'Test audio quality',20,'Use headphones for testing'),(3,30,'Check battery level',10,'Replace if below 20%'),(3,40,'Inspect connections',15,'Check for wear and tear'),(4,10,'Check camera image quality',20,'Access from authorized location'),(4,20,'Clean camera lens',15,'Use microfiber cloth'),(4,30,'Verify recording functionality',25,'Check storage availability'),(4,40,'Test night vision',20,'Conduct in low light conditions'),(4,50,'Inspect cable connections',10,'Ensure weatherproofing'),(5,10,'Test fire alarm panel',15,'Notify security before testing'),(5,20,'Test smoke detectors',20,'Use smoke test spray'),(5,30,'Test alarm horns and strobes',15,'Wear hearing protection'),(5,40,'Check backup battery',10,'Record voltage reading'),(6,10,'Inspect elevator cables',45,'Lock out elevator before work'),(6,20,'Lubricate moving parts',40,'Use approved lubricants'),(6,30,'Test emergency brake',30,'Follow safety procedures'),(6,40,'Check door sensors',25,'Test multiple times'),(6,50,'Inspect cabin condition',20,'Document any damage'),(6,60,'Test emergency communication',20,'Coordinate with control room'),(7,10,'Power off monitor',3,'Unplug before cleaning'),(7,20,'Clean screen with microfiber cloth',12,'Use approved screen cleaner'),(7,30,'Clean monitor casing',10,'Avoid liquid near ports'),(7,40,'Power on and test',5,'Check for display issues'),(8,10,'Backup important data',20,'Verify backup completion'),(8,20,'Install Windows updates',25,'Ensure stable power supply'),(8,30,'Update antivirus software',10,'Run quick scan after update'),(8,40,'Restart and verify',5,'Check all systems running'),(9,10,'Prepare cleaning materials',10,'Wear protective gloves'),(9,20,'Clean and disinfect toilets',30,'Use approved disinfectants'),(9,30,'Clean sinks and mirrors',20,'Use appropriate cleaners'),(9,40,'Mop floors',25,'Place wet floor signs'),(9,50,'Restock supplies',15,'Check toilet paper and soap'),(9,60,'Final inspection',10,'Ensure all areas clean'),(10,10,'Turn off power',3,'Use lockout/tagout procedures'),(10,20,'Remove old bulb',5,'Let bulb cool if necessary'),(10,30,'Install new LED bulb',7,'Handle by base only'),(10,40,'Turn on and test',5,'Verify proper operation'),(11,10,'Inspect door hinges',10,'Check for loose screws'),(11,20,'Test door lock mechanism',10,'Ensure smooth operation'),(11,30,'Check door closer',10,'Adjust if necessary'),(12,10,'Diagnose the problem',30,'Use proper testing equipment'),(12,20,'Obtain necessary parts',15,'Verify part numbers'),(12,30,'Perform repair',90,'Follow manufacturer guidelines'),(12,40,'Test system operation',30,'Monitor for 30 minutes'),(12,50,'Document repair',15,'Update maintenance log');
/*!40000 ALTER TABLE `task_list_operation` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('planner','technician','reporter') NOT NULL,
  `user_type` enum('staff','teacher','student','external') NOT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (20,'seint','seint@gmail.com','$2y$10$mo0J549/kegZoC4hSpP2X.kz0.9J7iNNndDYU0Bvub5235PHG.U82','seint','seint',NULL,'reporter','teacher',NULL,1,'2025-10-18 06:37:57'),(21,'anchor','paing@gmai.com','$2y$10$v0KeASHEhsbevfm66v.vwewwBtUKdiOSJ7a8RofhkeVfoIumcJBRK','anchor','paing',NULL,'technician','staff',NULL,1,'2025-10-18 06:38:40'),(24,'paingpaing','paingpaing@gmail.com','$2y$10$IQi.Vb37aYR0vQjO987ADeCuBvZY5oZ567T/olpiF8raq5/9xmSBe','paing','paing',NULL,'technician','staff',NULL,1,'2025-10-18 07:22:14'),(25,'paing2','anchor@gmail.com','$2y$10$BKJn4MmHyOv3ArJJacqIg.Y8F2ELX2jymlrDpr77akHA4QEtU030y','paing','paing',NULL,'reporter','staff',NULL,1,'2025-10-18 12:14:59'),(26,'Mee','mee@gmail.com','mee123','Mee','Mee','15555556221','planner','staff','PLN-001',1,'2025-10-18 14:22:32'),(27,'christine','christine@gmail.com','$2y$10$iQ5VEkkp7/lx88tOQgiJOe0darSIQz0wsUz4JenoGo9TOSO57Dfyq','christine','mee',NULL,'technician','staff',NULL,1,'2025-10-18 15:06:12');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vendor`
--

DROP TABLE IF EXISTS `vendor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor` (
  `vendor_id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_code` varchar(50) NOT NULL,
  `vendor_name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`vendor_id`),
  UNIQUE KEY `uq_vendor_code` (`vendor_code`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vendor`
--

LOCK TABLES `vendor` WRITE;
/*!40000 ALTER TABLE `vendor` DISABLE KEYS */;
INSERT INTO `vendor` VALUES (1,'V-COOL','ChiangMai Cooling','sales@cmcooling.com','053-111-111','123 Huay Kaew Rd, Chiang Mai',1),(2,'V-AUDIO','Pro Audio Thailand','info@proaudio.co.th','053-222-222','456 Nimman Rd, Chiang Mai',1),(3,'V-SEC','SafeCam Security','contact@safecam.co.th','053-333-333','789 Chang Phuak Rd, Chiang Mai',1),(4,'V-ELEV','ThaiLift Services','service@thailift.com','053-444-444','321 Superhighway Rd, Chiang Mai',1),(5,'V-FIRE','FirePro Systems','sales@firepro.co.th','053-555-555','654 Canal Rd, Chiang Mai',1),(6,'V-ELEC','Electrical Supply Co','orders@elecsupply.com','053-666-666','987 Hang Dong Rd, Chiang Mai',1),(7,'V-PLUMB','Plumbing Solutions','info@plumbsol.co.th','053-777-777','147 Mae Rim Rd, Chiang Mai',1),(8,'V-IT','Tech World Thailand','support@techworld.co.th','053-888-888','258 Suthep Rd, Chiang Mai',1),(9,'V-CLEAN','Clean Team Services','info@cleanteam.co.th','053-999-999','369 Airport Plaza, Chiang Mai',1),(10,'V-BUILD','Building Materials Ltd','sales@buildmat.com','053-101-010','741 San Kamphaeng Rd, Chiang Mai',1);
/*!40000 ALTER TABLE `vendor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `work_center`
--

DROP TABLE IF EXISTS `work_center`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_center` (
  `work_center_id` int(11) NOT NULL AUTO_INCREMENT,
  `wc_code` varchar(50) NOT NULL,
  `wc_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`work_center_id`),
  UNIQUE KEY `uq_wc_code` (`wc_code`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_center`
--

LOCK TABLES `work_center` WRITE;
/*!40000 ALTER TABLE `work_center` DISABLE KEYS */;
INSERT INTO `work_center` VALUES (1,'WC-ELEC','Electrical','Electrical systems and power',1),(2,'WC-HVAC','HVAC','Heating, ventilation and air conditioning',1),(3,'WC-PLUMB','Plumbing','Water and drainage systems',1),(4,'WC-AV','Audio/Visual','Sound and presentation systems',1),(5,'WC-SEC','Security','CCTV and access control',1),(6,'WC-MECH','Mechanical','Elevators and moving parts',1),(7,'WC-FIRE','Fire Safety','Fire alarms and extinguishers',1),(8,'WC-IT','IT Support','Computers and network equipment',1),(9,'WC-CLEAN','Cleaning','Janitorial and sanitation',1),(10,'WC-CIVIL','Civil','Building structure and doors',1);
/*!40000 ALTER TABLE `work_center` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `work_order`
--

DROP TABLE IF EXISTS `work_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_order` (
  `work_order_id` int(11) NOT NULL AUTO_INCREMENT,
  `wo_no` varchar(50) NOT NULL,
  `source` enum('PM','NOTIFICATION','ADHOC') NOT NULL DEFAULT 'ADHOC',
  `notification_id` int(11) DEFAULT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `task_list_id` int(11) DEFAULT NULL,
  `equipment_id` int(11) DEFAULT NULL,
  `floc_id` int(11) DEFAULT NULL,
  `work_center_id` int(11) DEFAULT NULL,
  `planner_user_id` int(11) DEFAULT NULL,
  `assigned_user_id` int(11) DEFAULT NULL,
  `status` enum('CREATED','RELEASED','IN_PROGRESS','WAITING_PARTS','COMPLETED','CANCELLED') NOT NULL DEFAULT 'CREATED',
  `priority` enum('LOW','MEDIUM','HIGH','URGENT') NOT NULL DEFAULT 'MEDIUM',
  `requested_start` datetime DEFAULT NULL,
  `requested_end` datetime DEFAULT NULL,
  `actual_start` datetime DEFAULT NULL,
  `actual_end` datetime DEFAULT NULL,
  `problem_note` varchar(500) DEFAULT NULL,
  `resolution_note` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`work_order_id`),
  UNIQUE KEY `uq_wo_no` (`wo_no`),
  KEY `fk_wo_notif` (`notification_id`),
  KEY `fk_wo_plan` (`plan_id`),
  KEY `fk_wo_tl` (`task_list_id`),
  KEY `fk_wo_floc` (`floc_id`),
  KEY `fk_wo_planner_user` (`planner_user_id`),
  KEY `fk_wo_assigned_user` (`assigned_user_id`),
  KEY `idx_wo_status` (`status`),
  KEY `idx_wo_wc` (`work_center_id`),
  KEY `idx_wo_equipment` (`equipment_id`),
  KEY `idx_wo_due` (`requested_end`),
  CONSTRAINT `fk_wo_assigned_user` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_wo_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`equipment_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_wo_floc` FOREIGN KEY (`floc_id`) REFERENCES `functional_location` (`floc_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_wo_notif` FOREIGN KEY (`notification_id`) REFERENCES `notification` (`notification_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_wo_plan` FOREIGN KEY (`plan_id`) REFERENCES `maintenance_plan` (`plan_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_wo_planner_user` FOREIGN KEY (`planner_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_wo_tl` FOREIGN KEY (`task_list_id`) REFERENCES `task_list` (`task_list_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_wo_wc` FOREIGN KEY (`work_center_id`) REFERENCES `work_center` (`work_center_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_order`
--

LOCK TABLES `work_order` WRITE;
/*!40000 ALTER TABLE `work_order` DISABLE KEYS */;
INSERT INTO `work_order` VALUES (1,'WO2025-0001','NOTIFICATION',1,NULL,12,1,6,2,26,27,'COMPLETED','HIGH',NULL,'2025-10-18 23:59:59','2025-10-17 09:00:00','2025-10-17 12:30:00','AC not cooling properly in B201','Replaced compressor belt and recharged refrigerant','2025-10-19 12:53:06','2025-10-19 12:53:06'),(2,'WO2025-0002','NOTIFICATION',2,NULL,3,12,16,4,26,27,'COMPLETED','MEDIUM',NULL,'2025-10-19 23:59:59','2025-10-18 10:00:00','2025-10-18 10:45:00','Microphone feedback in B301','Adjusted mixer settings and replaced cable','2025-10-19 12:53:06','2025-10-19 12:53:06'),(3,'WO2025-0003','NOTIFICATION',3,NULL,4,19,46,5,26,24,'COMPLETED','HIGH',NULL,'2025-10-18 23:59:59','2025-10-18 15:00:00','2025-10-18 16:30:00','CCTV camera not recording','Reset NVR and formatted hard drive','2025-10-19 12:53:06','2025-10-19 12:53:06'),(4,'WO2025-0004','NOTIFICATION',4,NULL,5,31,46,7,26,24,'IN_PROGRESS','URGENT',NULL,'2025-10-19 23:59:59','2025-10-19 08:00:00',NULL,'Fire alarm making beeping sound',NULL,'2025-10-19 12:53:06','2025-10-19 12:53:06'),(5,'WO2025-0005','NOTIFICATION',7,NULL,8,62,11,8,26,27,'RELEASED','MEDIUM',NULL,'2025-10-21 23:59:59',NULL,NULL,'Computer running very slow',NULL,'2025-10-19 12:53:06','2025-10-19 12:53:06'),(6,'WO2025-0006','NOTIFICATION',11,NULL,12,5,36,2,26,27,'CREATED','HIGH',NULL,'2025-10-22 23:59:59',NULL,NULL,'AC making loud noise in C301',NULL,'2025-10-19 12:53:06','2025-10-19 12:53:06'),(7,'WO2025-0007','PM',NULL,1,1,1,6,2,26,27,'COMPLETED','MEDIUM',NULL,'2025-10-20 23:59:59','2025-10-19 14:00:00','2025-10-19 16:00:00','PM: AC Monthly Maintenance','Completed scheduled maintenance','2025-10-19 12:53:06','2025-10-19 12:53:06'),(8,'WO2025-0008','PM',NULL,2,3,11,6,4,26,27,'COMPLETED','LOW',NULL,'2025-10-21 23:59:59','2025-10-20 09:00:00','2025-10-20 10:00:00','PM: Microphone Weekly Check','All systems operational','2025-10-19 12:53:06','2025-10-19 12:53:06'),(9,'WO2025-0009','PM',NULL,3,4,19,46,5,26,24,'COMPLETED','LOW',NULL,'2025-10-15 23:59:59','2025-10-14 15:00:00','2025-10-14 16:30:00','PM: CCTV Monthly Inspection','Cameras cleaned and tested','2025-10-19 12:53:06','2025-10-19 12:53:06'),(10,'WO2025-0010','PM',NULL,4,5,31,46,7,26,24,'COMPLETED','MEDIUM',NULL,'2025-10-16 23:59:59','2025-10-15 10:00:00','2025-10-15 11:00:00','PM: Fire Alarm Monthly Test','All zones tested successfully','2025-10-19 12:53:06','2025-10-19 12:53:06'),(11,'WO2025-0011','PM',NULL,6,7,47,6,8,26,24,'COMPLETED','LOW',NULL,'2025-10-18 23:59:59','2025-10-18 16:00:00','2025-10-18 16:30:00','PM: Monitor Weekly Cleaning','Screens cleaned','2025-10-19 12:53:06','2025-10-19 12:53:06'),(12,'WO2025-0012','PM',NULL,8,9,73,46,9,26,24,'COMPLETED','LOW',NULL,'2025-10-19 23:59:59','2025-10-19 06:00:00','2025-10-19 08:00:00','PM: Toilet Daily Cleaning','Deep cleaning completed','2025-10-19 12:53:06','2025-10-19 12:53:06'),(13,'WO2025-0013','PM',NULL,1,1,2,7,2,26,27,'RELEASED','MEDIUM',NULL,'2025-10-23 23:59:59',NULL,NULL,'PM: AC Monthly Maintenance B202',NULL,'2025-10-19 12:53:06','2025-10-19 12:53:06'),(14,'WO2025-0014','PM',NULL,2,3,12,16,4,26,27,'CREATED','LOW',NULL,'2025-10-24 23:59:59',NULL,NULL,'PM: Microphone Weekly Check',NULL,'2025-10-19 12:53:06','2025-10-19 12:53:06'),(15,'WO2025-0015','PM',NULL,7,8,63,12,8,26,24,'CREATED','LOW',NULL,'2025-10-25 23:59:59',NULL,NULL,'PM: Computer Monthly Update',NULL,'2025-10-19 12:53:06','2025-10-19 12:53:06'),(16,'WO2025-0016','ADHOC',NULL,NULL,10,82,46,1,26,24,'COMPLETED','LOW',NULL,'2025-10-17 23:59:59','2025-10-16 14:00:00','2025-10-16 14:30:00','Replace burned out LED bulbs in corridor','Replaced 3 LED bulbs','2025-10-19 12:53:06','2025-10-19 12:53:06'),(17,'WO2025-0017','ADHOC',NULL,NULL,11,93,6,10,26,24,'COMPLETED','MEDIUM',NULL,'2025-10-18 23:59:59','2025-10-17 11:00:00','2025-10-17 11:45:00','Door closer needs adjustment','Adjusted door closer tension','2025-10-19 12:53:06','2025-10-19 12:53:06'),(18,'WO2025-0018','ADHOC',NULL,NULL,9,74,46,9,26,24,'IN_PROGRESS','HIGH',NULL,'2025-10-20 23:59:59','2025-10-19 13:00:00',NULL,'Emergency toilet repair - water leaking',NULL,'2025-10-19 12:53:06','2025-10-19 12:53:06');
/*!40000 ALTER TABLE `work_order` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `work_order_attachment`
--

DROP TABLE IF EXISTS `work_order_attachment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_order_attachment` (
  `attachment_id` int(11) NOT NULL AUTO_INCREMENT,
  `work_order_id` int(11) NOT NULL,
  `file_name` varchar(200) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_url` varchar(500) DEFAULT NULL,
  `uploaded_by_user_id` int(11) DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attachment_id`),
  KEY `fk_woatt_wo` (`work_order_id`),
  KEY `fk_woatt_uploaded_user` (`uploaded_by_user_id`),
  CONSTRAINT `fk_woatt_uploaded_user` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_woatt_wo` FOREIGN KEY (`work_order_id`) REFERENCES `work_order` (`work_order_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_order_attachment`
--

LOCK TABLES `work_order_attachment` WRITE;
/*!40000 ALTER TABLE `work_order_attachment` DISABLE KEYS */;
/*!40000 ALTER TABLE `work_order_attachment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `work_order_labor`
--

DROP TABLE IF EXISTS `work_order_labor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_order_labor` (
  `work_order_id` int(11) NOT NULL,
  `planned_hours` decimal(8,2) DEFAULT NULL,
  `actual_hours` decimal(8,2) DEFAULT NULL,
  `labor_cost` decimal(12,2) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`work_order_id`),
  CONSTRAINT `fk_wol_wo` FOREIGN KEY (`work_order_id`) REFERENCES `work_order` (`work_order_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_order_labor`
--

LOCK TABLES `work_order_labor` WRITE;
/*!40000 ALTER TABLE `work_order_labor` DISABLE KEYS */;
INSERT INTO `work_order_labor` VALUES (1,3.00,3.50,NULL,'2025-10-19 12:53:40'),(2,1.00,0.75,NULL,'2025-10-19 12:53:40'),(3,1.50,1.50,NULL,'2025-10-19 12:53:40'),(4,1.00,NULL,NULL,'2025-10-19 12:53:40'),(5,1.00,NULL,NULL,'2025-10-19 12:53:40'),(6,3.00,NULL,NULL,'2025-10-19 12:53:40'),(7,2.00,2.00,NULL,'2025-10-19 12:53:40'),(8,1.00,1.00,NULL,'2025-10-19 12:53:40'),(9,1.50,1.50,NULL,'2025-10-19 12:53:40'),(10,1.00,1.00,NULL,'2025-10-19 12:53:40'),(11,0.30,0.50,NULL,'2025-10-19 12:53:40'),(12,2.00,2.00,NULL,'2025-10-19 12:53:40'),(13,2.00,NULL,NULL,'2025-10-19 12:53:40'),(14,1.00,NULL,NULL,'2025-10-19 12:53:40'),(15,1.00,NULL,NULL,'2025-10-19 12:53:40'),(16,0.25,0.50,NULL,'2025-10-19 12:53:40'),(17,0.50,0.75,NULL,'2025-10-19 12:53:40'),(18,2.00,NULL,NULL,'2025-10-19 12:53:40');
/*!40000 ALTER TABLE `work_order_labor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `work_order_material`
--

DROP TABLE IF EXISTS `work_order_material`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_order_material` (
  `wom_id` int(11) NOT NULL AUTO_INCREMENT,
  `work_order_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `unit_cost` decimal(12,2) DEFAULT NULL,
  `issued_by_user_id` int(11) DEFAULT NULL,
  `issued_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`wom_id`),
  KEY `fk_wom_mat` (`material_id`),
  KEY `fk_wom_issued_by_user` (`issued_by_user_id`),
  KEY `idx_wom_wo` (`work_order_id`),
  CONSTRAINT `fk_wom_issued_by_user` FOREIGN KEY (`issued_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_wom_mat` FOREIGN KEY (`material_id`) REFERENCES `material` (`material_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_wom_wo` FOREIGN KEY (`work_order_id`) REFERENCES `work_order` (`work_order_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_order_material`
--

LOCK TABLES `work_order_material` WRITE;
/*!40000 ALTER TABLE `work_order_material` DISABLE KEYS */;
INSERT INTO `work_order_material` VALUES (1,1,2,0.500,1200.00,26,'2025-10-19 12:53:24'),(2,1,3,1.000,350.00,26,'2025-10-19 12:53:24'),(3,2,6,1.000,320.00,26,'2025-10-19 12:53:24'),(4,3,13,1.000,2800.00,26,'2025-10-19 12:53:24'),(5,5,38,1.000,1200.00,26,'2025-10-19 12:53:24'),(6,5,39,1.000,1500.00,26,'2025-10-19 12:53:24'),(7,7,1,1.000,250.00,26,'2025-10-19 12:53:24'),(8,7,4,0.500,180.00,26,'2025-10-19 12:53:24'),(9,8,7,2.000,120.00,26,'2025-10-19 12:53:24'),(10,10,21,1.000,180.00,26,'2025-10-19 12:53:24'),(11,11,26,0.000,0.00,26,'2025-10-19 12:53:24'),(12,12,35,2.000,180.00,26,'2025-10-19 12:53:24'),(13,16,26,3.000,85.00,26,'2025-10-19 12:53:24'),(14,17,41,1.000,280.00,26,'2025-10-19 12:53:24');
/*!40000 ALTER TABLE `work_order_material` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-10-19 13:11:33
