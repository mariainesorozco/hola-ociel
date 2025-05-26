/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.7.2-MariaDB, for osx10.19 (arm64)
--
-- Host: localhost    Database: hola_ociel
-- ------------------------------------------------------
-- Server version	11.7.2-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `chat_interactions`
--

DROP TABLE IF EXISTS `chat_interactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_interactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` varchar(100) DEFAULT NULL,
  `user_type` enum('student','employee','public') NOT NULL DEFAULT 'public',
  `department` varchar(100) DEFAULT NULL,
  `user_identification` varchar(100) DEFAULT NULL,
  `message` text NOT NULL,
  `response` text NOT NULL,
  `context_used` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context_used`)),
  `confidence` double(3,2) NOT NULL DEFAULT 0.00,
  `model_used` varchar(50) DEFAULT NULL,
  `response_time` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `channel` varchar(20) NOT NULL DEFAULT 'web',
  `was_helpful` tinyint(1) DEFAULT NULL,
  `feedback_comment` text DEFAULT NULL,
  `requires_human_follow_up` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chat_interactions_user_type_created_at_index` (`user_type`,`created_at`),
  KEY `chat_interactions_department_created_at_index` (`department`,`created_at`),
  KEY `chat_interactions_channel_created_at_index` (`channel`,`created_at`),
  KEY `chat_interactions_requires_human_follow_up_index` (`requires_human_follow_up`),
  KEY `chat_interactions_session_id_index` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_interactions`
--

LOCK TABLES `chat_interactions` WRITE;
/*!40000 ALTER TABLE `chat_interactions` DISABLE KEYS */;
INSERT INTO `chat_interactions` VALUES
(1,'e4e5e185-85f2-4e04-8a8c-f3c3d23ba545','student',NULL,NULL,'Hola Ociel','¡Hola! 👋 Soy Ociel, tu asistente virtual de la Universidad Autónoma de Nayarit. Estoy aquí para ayudarte con información sobre trámites, carreras, servicios y más. ¿En qué puedo asistirte hoy?',NULL,0.80,'knowledge_base',11,'127.0.0.1','web',NULL,NULL,0,'2025-05-26 23:48:05','2025-05-26 23:48:05'),
(2,'a922a30c-5801-4b26-ba37-57f905be9272','student',NULL,NULL,'¿Qué carreras ofrecen?','🎓 La UAN ofrece más de 40 programas de licenciatura en diversas áreas. Para información detallada sobre nuestra oferta educativa, contacta al 311-211-8800 o visita https://www.uan.edu.mx',NULL,0.80,'knowledge_base',3,'127.0.0.1','web',NULL,NULL,0,'2025-05-26 23:48:24','2025-05-26 23:48:24'),
(3,'2b0093a9-9fa3-4288-8902-9d15314d1869','student',NULL,NULL,'Información sobre inscripción','👋 ¡Hola! Soy Ociel, tu asistente de la UAN. Puedo ayudarte con información sobre carreras, trámites, servicios y más. Para consultas específicas, contacta al 311-211-8800 o visita https://www.uan.edu.mx ¿En qué más puedo ayudarte?',NULL,0.80,'knowledge_base',3,'127.0.0.1','web',NULL,NULL,0,'2025-05-26 23:48:35','2025-05-26 23:48:35'),
(4,'e880d263-6135-4e60-8f57-5e1c2bf9166b','student',NULL,NULL,'Información sobre inscripción','👋 ¡Hola! Soy Ociel, tu asistente de la UAN. Puedo ayudarte con información sobre carreras, trámites, servicios y más. Para consultas específicas, contacta al 311-211-8800 o visita https://www.uan.edu.mx ¿En qué más puedo ayudarte?',NULL,0.80,'knowledge_base',55,'127.0.0.1','web',NULL,NULL,0,'2025-05-27 00:46:53','2025-05-27 00:46:53'),
(5,'e880d263-6135-4e60-8f57-5e1c2bf9166b','student',NULL,NULL,'¿Qué carreras ofrecen?','🎓 La UAN ofrece más de 40 programas de licenciatura en diversas áreas. Para información detallada sobre nuestra oferta educativa, contacta al 311-211-8800 o visita https://www.uan.edu.mx',NULL,0.80,'knowledge_base',15,'127.0.0.1','web',NULL,NULL,0,'2025-05-27 00:47:08','2025-05-27 00:47:08'),
(6,'e880d263-6135-4e60-8f57-5e1c2bf9166b','student',NULL,NULL,'puedes decirme si ofrecen la carrera de medicina?','🎓 La UAN ofrece más de 40 programas de licenciatura en diversas áreas. Para información detallada sobre nuestra oferta educativa, contacta al 311-211-8800 o visita https://www.uan.edu.mx',NULL,0.80,'knowledge_base',2,'127.0.0.1','web',NULL,NULL,0,'2025-05-27 00:47:27','2025-05-27 00:47:27'),
(7,'0b5a85f8-9268-45a4-a398-aeac489b58de','student',NULL,NULL,'¿Qué carreras ofrecen?','🎓 La UAN ofrece más de 40 programas de licenciatura en diversas áreas. Para información detallada sobre nuestra oferta educativa, contacta al 311-211-8800 o visita https://www.uan.edu.mx',NULL,0.80,'knowledge_base',5,'127.0.0.1','web',NULL,NULL,0,'2025-05-27 00:49:40','2025-05-27 00:49:40'),
(8,'020bf6d6-228b-41f9-82e9-7af5d181fc4a','student',NULL,NULL,'¿Qué carreras ofrecen?','🎓 La UAN ofrece más de 40 programas de licenciatura en diversas áreas. Para información detallada sobre nuestra oferta educativa, contacta al 311-211-8800 o visita https://www.uan.edu.mx',NULL,0.80,'knowledge_base',4,'127.0.0.1','web',NULL,NULL,0,'2025-05-27 00:52:45','2025-05-27 00:52:45'),
(9,'020bf6d6-228b-41f9-82e9-7af5d181fc4a','student',NULL,NULL,'puedes listar las carreras dispobibles?','🎓 La UAN ofrece más de 40 programas de licenciatura en diversas áreas. Para información detallada sobre nuestra oferta educativa, contacta al 311-211-8800 o visita https://www.uan.edu.mx',NULL,0.80,'knowledge_base',2,'127.0.0.1','web',NULL,NULL,0,'2025-05-27 00:52:54','2025-05-27 00:52:54'),
(10,'020bf6d6-228b-41f9-82e9-7af5d181fc4a','student',NULL,NULL,'Información sobre inscripción','👋 ¡Hola! Soy Ociel, tu asistente de la UAN. Puedo ayudarte con información sobre carreras, trámites, servicios y más. Para consultas específicas, contacta al 311-211-8800 o visita https://www.uan.edu.mx ¿En qué más puedo ayudarte?',NULL,0.80,'knowledge_base',4,'127.0.0.1','web',NULL,NULL,0,'2025-05-27 00:53:17','2025-05-27 00:53:17'),
(11,'020bf6d6-228b-41f9-82e9-7af5d181fc4a','student',NULL,NULL,'Servicios de biblioteca','📚 La UAN cuenta con biblioteca central y bibliotecas especializadas con servicios de préstamo, consulta en línea y espacios de estudio. Más información: 311-211-8800 ext. 8600.',NULL,0.80,'knowledge_base',3,'127.0.0.1','web',NULL,NULL,0,'2025-05-27 00:53:22','2025-05-27 00:53:22'),
(12,'020bf6d6-228b-41f9-82e9-7af5d181fc4a','student',NULL,NULL,'Soporte técnico','📋 Basado en tu consulta, te comparto esta información: La Dirección General de Sistemas brinda soporte técnico a la comunidad universitaria: correo institucional, acceso a plataformas educativas, soporte de equipos, conectividad de red.\n\nSi necesitas más detalles, puedes contactar al 311-211-8800.',NULL,0.80,'knowledge_base',2,'127.0.0.1','web',NULL,NULL,0,'2025-05-27 00:53:33','2025-05-27 00:53:33'),
(13,'035193e0-1d05-4292-866f-ccf12a9efbfb','student',NULL,NULL,'Información sobre inscripción','¡Hola! Me alegra ayudarte con información sobre la inscripción en la Universidad Autónoma de Nayarit (UAN). 🎓\n\nPara obtener información sobre el proceso de inscripción, te recomiendo contactar a la Dirección General de Servicios Académicos (DGSA) de la UAN. Puedes hacerlo llamando al 311-211-8800 ext. 8530 o visitando su sitio web oficial para obtener más detalles.\n\nAdemás, es importante que revises los requisitos y plazas disponibles para cada programa de estudio que estés interesado en inscribirte. Puedes encontrar esta información en el sitio web de la UAN o contactar con el Departamento específico del área de interés.\n\nSi tienes alguna pregunta específica sobre el proceso de inscripción, no dudes en preguntar y haré todo lo posible para ayudarte. ¿Hay algo más que pueda hacer por ti? 😊\n\n💡 Te recomiendo verificar esta información contactando directamente a la dependencia correspondiente.','[]',0.50,'llama3.2:3b',11806,'127.0.0.1','web',NULL,NULL,0,'2025-05-27 02:29:24','2025-05-27 02:29:24'),
(14,'c785e7b1-57f1-460c-ace7-8e97f8cc79a2','student',NULL,NULL,'¿Qué carreras ofrecen?','¡Hola! Me alegra que estés interesado en saber qué carreras ofrece nuestra Universidad Autónoma de Nayarit (UAN). 📚👨‍🎓\n\nEn la UAN, contamos con una amplia variedad de programas académicos que abarcan más de 40 licenciaturas, 25 maestrías y 8 doctorados en diferentes áreas del conocimiento. Algunas de las carreras que ofrecemos son:\n\n- Ciencias Básicas e Ingenierías\n- Ciencias Sociales y Humanidades\n- Ciencias de la Salud\n- Ciencias Biológico Agropecuarias y Pesqueras\n\nQuieres saber más sobre una carrera específica? 🤔 Puedes preguntar sobre cualquier campo que te interese, y podré ayudarte a encontrar la información relevante.\n\n¿Necesitas ayuda para buscar una carrera que se adapte a tus intereses y habilidades? ¡Estoy aquí para ayudarte! 😊','[\"La UAN ofrece m\\u00e1s de 40 programas de licenciatura, 25 maestr\\u00edas y 8 doctorados en diversas \\u00e1reas del conocimiento: Ciencias B\\u00e1sicas e Ingenier\\u00edas, Ciencias Sociales y Humanidades, Ciencias de la Salud, Ciencias Biol\\u00f3gico Agropecuarias y Pesqueras.\"]',0.67,'llama3.2:3b',11326,'127.0.0.1','web',NULL,NULL,0,'2025-05-27 02:30:27','2025-05-27 02:30:27'),
(15,'c668c51f-704e-436d-9f93-25ebbffd5ec2','student',NULL,NULL,'Soporte técnico','🤔 Hola, soy Ociel, tu asistente virtual oficial de la Universidad Autónoma de Nayarit. Estoy aquí para ayudarte con cualquier pregunta o inquietud que tengas sobre la universidad.\n\nPuedes contactarme a través del correo institucional [soporte.técnico@uan.mx](mailto:soporte.técnico@uan.mx) o llamándome al 323 318 2000. Estoy aquí para ayudarte de lunes a viernes, de 9am a 5pm.\n\n¿En qué puedo ayudarte hoy? ¿Tienes algún problema con tu computadora, tu acceso a plataformas educativas o algo más? 🤓','[\"La Direcci\\u00f3n General de Sistemas brinda soporte t\\u00e9cnico a la comunidad universitaria: correo institucional, acceso a plataformas educativas, soporte de equipos, conectividad de red.\"]',0.63,'llama3.2:3b',8622,'127.0.0.1','web',NULL,NULL,0,'2025-05-27 02:37:45','2025-05-27 02:37:45'),
(16,'9a4d5e1d-fc77-4672-b48d-f9a5c38afc5b','student',NULL,NULL,'Información sobre inscripción','¡Hola! Me alegra que estés interesado en obtener información sobre la inscripción en la Universidad Autónoma de Nayarit (UAN). 🎓\n\nPara obtener más información sobre el proceso de inscripción, te recomiendo visitar nuestra página web oficial de la UAN y consultar la sección de \"Inscripciones\" o contactar directamente con nuestro Departamento General de Servicios Académicos (DGSA) al teléfono 311-211-8800 ext. 8530. 📞\n\nEn la página web, podrás encontrar información sobre los requisitos para la inscripción, los plazos y fechas límite, así como procedimientos específicos para diferentes tipos de estudiantes (matrícula regular, becas, etc.).\n\nSi tienes alguna pregunta o necesitas ayuda con el proceso de inscripción, no dudes en contactar a nuestro equipo de DGSA. Estamos aquí para ayudarte. 😊\n\n¿Necesitas ayuda con algo más? ¿Podemos saber qué tipo de información estás buscando sobre la inscripción? 🤔\n\n💡 Te recomiendo verificar esta información contactando directamente a la dependencia correspondiente.','[]',0.50,'llama3.2:3b',5892,'127.0.0.1','web',NULL,NULL,0,'2025-05-27 02:38:46','2025-05-27 02:38:46'),
(17,'9a4d5e1d-fc77-4672-b48d-f9a5c38afc5b','student',NULL,NULL,'Servicios de biblioteca','¡Hola! Me alegra ayudarte con tus preguntas sobre los servicios de biblioteca de la Universidad Autónoma de Nayarit (UAN). 📚\n\n¿En qué puedo ayudarte hoy? ¿Necesitas información sobre nuestros sistemas bibliotecarios, préstamos de libros, bases de datos o algo más?','[\"La UAN cuenta con un sistema bibliotecario que incluye biblioteca central y bibliotecas especializadas. Servicios: pr\\u00e9stamo de libros, consulta en l\\u00ednea, bases de datos, cub\\u00edculos de estudio, wifi gratuito.\"]',0.71,'llama3.2:3b',6920,'127.0.0.1','web',NULL,NULL,0,'2025-05-27 02:44:16','2025-05-27 02:44:16'),
(18,'9a4d5e1d-fc77-4672-b48d-f9a5c38afc5b','student',NULL,NULL,'hola','¡Hola! Me alegra que estés conectado conmigo. Soy Ociel, tu asistente virtual oficial en la Universidad Autónoma de Nayarit (UAN). ¿En qué puedo ayudarte hoy? 🤗 ¿Necesitas información sobre nuestros programas académicos, servicios o algo más?\n\n💡 Te recomiendo verificar esta información contactando directamente a la dependencia correspondiente.','[]',0.50,'llama3.2:3b',7541,'127.0.0.1','web',NULL,NULL,0,'2025-05-27 02:51:04','2025-05-27 02:51:04'),
(19,'9a4d5e1d-fc77-4672-b48d-f9a5c38afc5b','student',NULL,NULL,'Información sobre inscripción','¡Hola! 😊 Me alegra que estés interesado en obtener información sobre la inscripción en la Universidad Autónoma de Nayarit (UAN). Estoy aquí para ayudarte.\n\nLa inscripción en la UAN es un proceso sencillo y rápido. Aquí te presento los pasos generales:\n\n1. **Verifica tus credenciales**: Asegúrate de que tengas todos los documentos necesarios, como tu certificado de antecedentes penales, prueba de español, exámenes universitarios, entre otros.\n2. **Inicia sesión en el portal web de la UAN**: Visita nuestro sitio web ([www.uan.mx](http://www.uan.mx)) y crea tu cuenta si no la tienes ya.\n3. **Solicita inscripción**: En el portal web, busca la sección de \"Inscripciones\" y sigue las instrucciones para solicitar tu inscripción.\n4. **Presenta los documentos necesarios**: Una vez que hayas iniciado el proceso de inscripción, debes presentar todos los documentos requeridos en persona en la DGSA (Dirección General de Servicios Académicos) o a través de un representante autorizado.\n\nSi tienes alguna pregunta o necesitas ayuda durante el proceso de inscripción, no dudes en contactar con la DGSA al teléfono 311-211-8800 ext. 8530 o por correo electrónico a [dgsa@uan.mx](mailto:dgsa@uan.mx).\n\n¿Necesitas ayuda adicional? ¿Hay algún documento que debas presentar? Estoy aquí para ayudarte.\n\n💡 Te recomiendo verificar esta información contactando directamente a la dependencia correspondiente.','[]',0.50,'llama3.2:3b',8139,'127.0.0.1','web',NULL,NULL,0,'2025-05-27 02:52:47','2025-05-27 02:52:47');
/*!40000 ALTER TABLE `chat_interactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(200) NOT NULL,
  `short_name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `contact_email` varchar(150) DEFAULT NULL,
  `location` varchar(300) DEFAULT NULL,
  `schedule` varchar(150) DEFAULT NULL,
  `services` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`services`)),
  `head_name` varchar(100) DEFAULT NULL,
  `has_specialized_agent` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `departments_code_unique` (`code`),
  KEY `departments_type_index` (`type`),
  KEY `departments_is_active_index` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES
(1,'DGSA','Dirección General de Servicios Académicos','DGSA','Encargada de los servicios académicos institucionales','dependencia','311-211-8800 ext. 8530','dgsa@uan.edu.mx','Edificio de Rectoría, 2do piso','Lunes a Viernes de 8:00 a 15:00 hrs','[\"Servicios escolares\",\"Control escolar\",\"Titulaci\\u00f3n\",\"Intercambio acad\\u00e9mico\"]',NULL,1,1,'2025-05-23 05:22:59','2025-05-23 05:22:59'),
(2,'DGS','Dirección General de Sistemas','Sistemas','Responsable de la infraestructura tecnológica universitaria','dependencia','311-211-8800 ext. 8540','sistemas@uan.edu.mx','Edificio de Sistemas','Lunes a Viernes de 8:00 a 15:00 hrs','[\"Soporte t\\u00e9cnico\",\"Desarrollo de sistemas\",\"Infraestructura de red\",\"Correo electr\\u00f3nico institucional\"]',NULL,1,1,'2025-05-23 05:22:59','2025-05-23 05:22:59'),
(3,'SECRETARIA_GENERAL','Secretaría General','Secretaría General','Órgano de apoyo a la Rectoría','secretaria','311-211-8800 ext. 8510','secretaria.general@uan.edu.mx','Edificio de Rectoría, 1er piso','Lunes a Viernes de 8:00 a 15:00 hrs','[\"Tr\\u00e1mites administrativos\",\"Certificaciones\",\"Apoyo a \\u00f3rganos colegiados\"]',NULL,0,1,'2025-05-23 05:22:59','2025-05-23 05:22:59'),
(4,'GENERAL','Información General','General','Información general de la Universidad','general','311-211-8800','info@uan.edu.mx','Ciudad de la Cultura Amado Nervo','24/7 (Asistente Virtual)','[\"Informaci\\u00f3n general\",\"Oferta educativa\",\"Eventos y actividades\",\"Directorio institucional\"]',NULL,0,1,'2025-05-23 05:22:59','2025-05-23 05:22:59');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `knowledge_base`
--

DROP TABLE IF EXISTS `knowledge_base`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `knowledge_base` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `category` varchar(100) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `user_types` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`user_types`)),
  `keywords` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`keywords`)),
  `source_url` varchar(500) DEFAULT NULL,
  `contact_info` varchar(200) DEFAULT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `knowledge_base_category_is_active_index` (`category`,`is_active`),
  KEY `knowledge_base_department_is_active_index` (`department`,`is_active`),
  KEY `knowledge_base_priority_index` (`priority`),
  FULLTEXT KEY `knowledge_base_title_content_fulltext` (`title`,`content`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `knowledge_base`
--

LOCK TABLES `knowledge_base` WRITE;
/*!40000 ALTER TABLE `knowledge_base` DISABLE KEYS */;
INSERT INTO `knowledge_base` VALUES
(1,'Información General UAN','La Universidad Autónoma de Nayarit (UAN) es una institución pública de educación superior fundada el 25 de abril de 1969. Está ubicada en la Ciudad de la Cultura \"Amado Nervo\" en Tepic, Nayarit, México.','informacion_general','GENERAL','[\"student\",\"employee\",\"public\"]','[\"UAN\",\"universidad\",\"historia\",\"fundaci\\u00f3n\",\"Nayarit\"]',NULL,'Tel: 311-211-8800','high',1,NULL,NULL,NULL,NULL,'2025-05-23 05:26:50','2025-05-23 05:26:50'),
(2,'Inscripción a Licenciatura','Para inscribirte a una licenciatura en la UAN necesitas: 1) Certificado de bachillerato, 2) Aprobar el examen de admisión, 3) Realizar el proceso de inscripción en línea, 4) Presentar documentación completa.','tramites','DGSA','[\"student\",\"public\"]','[\"inscripci\\u00f3n\",\"licenciatura\",\"admisi\\u00f3n\",\"examen\",\"bachillerato\"]',NULL,'DGSA: 311-211-8800 ext. 8530','high',1,NULL,NULL,NULL,NULL,'2025-05-23 05:26:50','2025-05-23 05:26:50'),
(3,'Oferta Educativa','La UAN ofrece más de 40 programas de licenciatura, 25 maestrías y 8 doctorados en diversas áreas del conocimiento: Ciencias Básicas e Ingenierías, Ciencias Sociales y Humanidades, Ciencias de la Salud, Ciencias Biológico Agropecuarias y Pesqueras.','oferta_educativa','GENERAL','[\"student\",\"public\"]','[\"carreras\",\"licenciaturas\",\"maestr\\u00edas\",\"doctorados\",\"programas\"]',NULL,'Tel: 311-211-8800','high',1,NULL,NULL,NULL,NULL,'2025-05-23 05:26:50','2025-05-23 05:26:50'),
(4,'Servicios de Biblioteca','La UAN cuenta con un sistema bibliotecario que incluye biblioteca central y bibliotecas especializadas. Servicios: préstamo de libros, consulta en línea, bases de datos, cubículos de estudio, wifi gratuito.','servicios','GENERAL','[\"student\",\"employee\"]','[\"biblioteca\",\"libros\",\"consulta\",\"estudio\",\"wifi\"]',NULL,'Biblioteca Central: 311-211-8800 ext. 8600','medium',1,NULL,NULL,NULL,NULL,'2025-05-23 05:26:50','2025-05-23 05:26:50'),
(5,'Soporte Técnico de Sistemas','La Dirección General de Sistemas brinda soporte técnico a la comunidad universitaria: correo institucional, acceso a plataformas educativas, soporte de equipos, conectividad de red.','servicios','DGS','[\"student\",\"employee\"]','[\"soporte\",\"t\\u00e9cnico\",\"sistemas\",\"correo\",\"plataformas\"]',NULL,'DGS: 311-211-8800 ext. 8540, sistemas@uan.edu.mx','medium',1,NULL,NULL,NULL,NULL,'2025-05-23 05:26:50','2025-05-23 05:26:50'),
(6,'Proceso de Titulación','Modalidades de titulación: tesis, tesina, examen general de conocimientos, estudios de posgrado, experiencia profesional. Requisitos: 100% de créditos, servicio social liberado, sin adeudos.','tramites','DGSA','[\"student\"]','[\"titulaci\\u00f3n\",\"tesis\",\"tesina\",\"examen\",\"egreso\"]',NULL,'DGSA: 311-211-8800 ext. 8530','high',1,NULL,NULL,NULL,NULL,'2025-05-23 05:26:50','2025-05-23 05:26:50'),
(7,'Información General UAN','La Universidad Autónoma de Nayarit (UAN) es una institución pública de educación superior fundada el 25 de abril de 1969. Está ubicada en la Ciudad de la Cultura \"Amado Nervo\" en Tepic, Nayarit, México.','informacion_general','GENERAL','[\"student\",\"employee\",\"public\"]','[\"UAN\",\"universidad\",\"historia\",\"fundaci\\u00f3n\",\"Nayarit\"]',NULL,'Tel: 311-211-8800','high',1,NULL,NULL,NULL,NULL,'2025-05-23 05:28:04','2025-05-23 05:28:04'),
(8,'Inscripción a Licenciatura','Para inscribirte a una licenciatura en la UAN necesitas: 1) Certificado de bachillerato, 2) Aprobar el examen de admisión, 3) Realizar el proceso de inscripción en línea, 4) Presentar documentación completa.','tramites','DGSA','[\"student\",\"public\"]','[\"inscripci\\u00f3n\",\"licenciatura\",\"admisi\\u00f3n\",\"examen\",\"bachillerato\"]',NULL,'DGSA: 311-211-8800 ext. 8530','high',1,NULL,NULL,NULL,NULL,'2025-05-23 05:28:04','2025-05-23 05:28:04'),
(9,'Oferta Educativa','La UAN ofrece más de 40 programas de licenciatura, 25 maestrías y 8 doctorados en diversas áreas del conocimiento: Ciencias Básicas e Ingenierías, Ciencias Sociales y Humanidades, Ciencias de la Salud, Ciencias Biológico Agropecuarias y Pesqueras.','oferta_educativa','GENERAL','[\"student\",\"public\"]','[\"carreras\",\"licenciaturas\",\"maestr\\u00edas\",\"doctorados\",\"programas\"]',NULL,'Tel: 311-211-8800','high',1,NULL,NULL,NULL,NULL,'2025-05-23 05:28:04','2025-05-23 05:28:04'),
(10,'Servicios de Biblioteca','La UAN cuenta con un sistema bibliotecario que incluye biblioteca central y bibliotecas especializadas. Servicios: préstamo de libros, consulta en línea, bases de datos, cubículos de estudio, wifi gratuito.','servicios','GENERAL','[\"student\",\"employee\"]','[\"biblioteca\",\"libros\",\"consulta\",\"estudio\",\"wifi\"]',NULL,'Biblioteca Central: 311-211-8800 ext. 8600','medium',1,NULL,NULL,NULL,NULL,'2025-05-23 05:28:04','2025-05-23 05:28:04'),
(11,'Soporte Técnico de Sistemas','La Dirección General de Sistemas brinda soporte técnico a la comunidad universitaria: correo institucional, acceso a plataformas educativas, soporte de equipos, conectividad de red.','servicios','DGS','[\"student\",\"employee\"]','[\"soporte\",\"t\\u00e9cnico\",\"sistemas\",\"correo\",\"plataformas\"]',NULL,'DGS: 311-211-8800 ext. 8540, sistemas@uan.edu.mx','medium',1,NULL,NULL,NULL,NULL,'2025-05-23 05:28:04','2025-05-23 05:28:04'),
(12,'Proceso de Titulación','Modalidades de titulación: tesis, tesina, examen general de conocimientos, estudios de posgrado, experiencia profesional. Requisitos: 100% de créditos, servicio social liberado, sin adeudos.','tramites','DGSA','[\"student\"]','[\"titulaci\\u00f3n\",\"tesis\",\"tesina\",\"examen\",\"egreso\"]',NULL,'DGSA: 311-211-8800 ext. 8530','high',1,NULL,NULL,NULL,NULL,'2025-05-23 05:28:04','2025-05-23 05:28:04'),
(13,'Información General UAN','La Universidad Autónoma de Nayarit (UAN) es una institución pública de educación superior fundada el 25 de abril de 1969. Está ubicada en la Ciudad de la Cultura \"Amado Nervo\" en Tepic, Nayarit, México.','informacion_general','GENERAL','[\"student\",\"employee\",\"public\"]','[\"UAN\",\"universidad\",\"historia\",\"fundaci\\u00f3n\",\"Nayarit\"]',NULL,'Tel: 311-211-8800','high',1,NULL,NULL,NULL,NULL,'2025-05-27 00:40:36','2025-05-27 00:40:36'),
(14,'Inscripción a Licenciatura','Para inscribirte a una licenciatura en la UAN necesitas: 1) Certificado de bachillerato, 2) Aprobar el examen de admisión, 3) Realizar el proceso de inscripción en línea, 4) Presentar documentación completa.','tramites','DGSA','[\"student\",\"public\"]','[\"inscripci\\u00f3n\",\"licenciatura\",\"admisi\\u00f3n\",\"examen\",\"bachillerato\"]',NULL,'DGSA: 311-211-8800 ext. 8530','high',1,NULL,NULL,NULL,NULL,'2025-05-27 00:40:36','2025-05-27 00:40:36'),
(15,'Oferta Educativa','La UAN ofrece más de 40 programas de licenciatura, 25 maestrías y 8 doctorados en diversas áreas del conocimiento: Ciencias Básicas e Ingenierías, Ciencias Sociales y Humanidades, Ciencias de la Salud, Ciencias Biológico Agropecuarias y Pesqueras.','oferta_educativa','GENERAL','[\"student\",\"public\"]','[\"carreras\",\"licenciaturas\",\"maestr\\u00edas\",\"doctorados\",\"programas\"]',NULL,'Tel: 311-211-8800','high',1,NULL,NULL,NULL,NULL,'2025-05-27 00:40:36','2025-05-27 00:40:36'),
(16,'Servicios de Biblioteca','La UAN cuenta con un sistema bibliotecario que incluye biblioteca central y bibliotecas especializadas. Servicios: préstamo de libros, consulta en línea, bases de datos, cubículos de estudio, wifi gratuito.','servicios','GENERAL','[\"student\",\"employee\"]','[\"biblioteca\",\"libros\",\"consulta\",\"estudio\",\"wifi\"]',NULL,'Biblioteca Central: 311-211-8800 ext. 8600','medium',1,NULL,NULL,NULL,NULL,'2025-05-27 00:40:36','2025-05-27 00:40:36'),
(17,'Soporte Técnico de Sistemas','La Dirección General de Sistemas brinda soporte técnico a la comunidad universitaria: correo institucional, acceso a plataformas educativas, soporte de equipos, conectividad de red.','servicios','DGS','[\"student\",\"employee\"]','[\"soporte\",\"t\\u00e9cnico\",\"sistemas\",\"correo\",\"plataformas\"]',NULL,'DGS: 311-211-8800 ext. 8540, sistemas@uan.edu.mx','medium',1,NULL,NULL,NULL,NULL,'2025-05-27 00:40:36','2025-05-27 00:40:36'),
(18,'Proceso de Titulación','Modalidades de titulación: tesis, tesina, examen general de conocimientos, estudios de posgrado, experiencia profesional. Requisitos: 100% de créditos, servicio social liberado, sin adeudos.','tramites','DGSA','[\"student\"]','[\"titulaci\\u00f3n\",\"tesis\",\"tesina\",\"examen\",\"egreso\"]',NULL,'DGSA: 311-211-8800 ext. 8530','high',1,NULL,NULL,NULL,NULL,'2025-05-27 00:40:36','2025-05-27 00:40:36');
/*!40000 ALTER TABLE `knowledge_base` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES
(1,'2014_10_12_000000_create_users_table',1),
(2,'2014_10_12_100000_create_password_resets_table',1),
(3,'2019_08_19_000000_create_failed_jobs_table',1),
(4,'2019_12_14_000001_create_personal_access_tokens_table',1),
(5,'2025_05_22_221538_create_chat_interactions_table',1),
(6,'2025_05_22_221541_create_knowledge_base_table',1),
(7,'2025_05_22_221542_create_uan_configurations_table',1),
(8,'2025_05_22_221544_create_departments_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `uan_configurations`
--

DROP TABLE IF EXISTS `uan_configurations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `uan_configurations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` text NOT NULL,
  `category` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('string','json','boolean','integer','float') NOT NULL DEFAULT 'string',
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uan_configurations_key_unique` (`key`),
  KEY `uan_configurations_category_index` (`category`),
  KEY `uan_configurations_is_public_index` (`is_public`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `uan_configurations`
--

LOCK TABLES `uan_configurations` WRITE;
/*!40000 ALTER TABLE `uan_configurations` DISABLE KEYS */;
INSERT INTO `uan_configurations` VALUES
(1,'ai_model_primary','mistral:7b','ai_settings','Modelo de IA principal para respuestas','string',0,'2025-05-23 05:22:59','2025-05-23 05:22:59'),
(2,'uan_contact_phone','311-211-8800','contact_info','Teléfono principal de la UAN','string',1,'2025-05-23 05:22:59','2025-05-23 05:22:59'),
(3,'uan_address','Ciudad de la Cultura \"Amado Nervo\", Tepic, Nayarit, México','contact_info','Dirección principal de la UAN','string',1,'2025-05-23 05:22:59','2025-05-23 05:22:59'),
(4,'uan_website','https://www.uan.edu.mx','contact_info','Sitio web oficial de la UAN','string',1,'2025-05-23 05:22:59','2025-05-23 05:22:59'),
(5,'chat_max_tokens','1000','ai_settings','Máximo de tokens para respuestas de IA','integer',0,'2025-05-23 05:22:59','2025-05-23 05:22:59'),
(6,'ociel_welcome_message','¡Hola! Soy Ociel, tu asistente virtual de la Universidad Autónoma de Nayarit. ¿En qué puedo ayudarte hoy? 🎓','chat_settings','Mensaje de bienvenida de Ociel','string',1,'2025-05-23 05:22:59','2025-05-23 05:22:59');
/*!40000 ALTER TABLE `uan_configurations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2025-05-26 13:55:52
