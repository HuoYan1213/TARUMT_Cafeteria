-- MySQL dump 10.13  Distrib 8.0.44, for Win64 (x86_64)
--
-- Host: localhost    Database: tarumtcafeteria
-- ------------------------------------------------------
-- Server version	8.0.44

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
-- Table structure for table `cart_products`
--

DROP TABLE IF EXISTS `cart_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cart_products` (
  `Cart_Product_ID` char(8) NOT NULL,
  `Cart_ID` int NOT NULL,
  `Product_ID` int NOT NULL,
  `Quantity` int NOT NULL,
  `Subtotal` decimal(6,2) NOT NULL,
  PRIMARY KEY (`Cart_Product_ID`),
  KEY `ck_product_id_cart_product_idx` (`Product_ID`),
  KEY `ck_cart_id_cart_product_idx` (`Cart_ID`),
  CONSTRAINT `ck_cart_id_cart_product` FOREIGN KEY (`Cart_ID`) REFERENCES `carts` (`Cart_ID`),
  CONSTRAINT `ck_product_id_cart_product` FOREIGN KEY (`Product_ID`) REFERENCES `products` (`Product_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart_products`
--

LOCK TABLES `cart_products` WRITE;
/*!40000 ALTER TABLE `cart_products` DISABLE KEYS */;
INSERT INTO `cart_products` VALUES ('25CP0001',1,1,1,11.90),('25CP002',2,1,1,9.90);
/*!40000 ALTER TABLE `cart_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `carts`
--

DROP TABLE IF EXISTS `carts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `carts` (
  `Cart_ID` int NOT NULL AUTO_INCREMENT,
  `User_ID` int NOT NULL,
  PRIMARY KEY (`Cart_ID`),
  KEY `fk_user_id_cart_idx` (`User_ID`),
  CONSTRAINT `fk_user_id_cart` FOREIGN KEY (`User_ID`) REFERENCES `users` (`User_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `carts`
--

LOCK TABLES `carts` WRITE;
/*!40000 ALTER TABLE `carts` DISABLE KEYS */;
INSERT INTO `carts` VALUES (1,1),(2,2),(3,3);
/*!40000 ALTER TABLE `carts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_products`
--

DROP TABLE IF EXISTS `order_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_products` (
  `Order_Product_ID` char(8) NOT NULL,
  `Order_ID` char(8) NOT NULL,
  `Product_ID` int NOT NULL,
  `Quantity` int NOT NULL,
  `Subtotal` decimal(6,2) NOT NULL,
  PRIMARY KEY (`Order_Product_ID`),
  KEY `ck_order_id_order_product_idx` (`Order_ID`),
  KEY `ck_product_id_order_product_idx` (`Product_ID`),
  CONSTRAINT `ck_order_id_order_product` FOREIGN KEY (`Order_ID`) REFERENCES `orders` (`Order_ID`),
  CONSTRAINT `ck_product_id_order_product` FOREIGN KEY (`Product_ID`) REFERENCES `products` (`Product_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_products`
--

LOCK TABLES `order_products` WRITE;
/*!40000 ALTER TABLE `order_products` DISABLE KEYS */;
INSERT INTO `order_products` VALUES ('25OPI001','25ORD003',1,1,11.90),('25OPI002','25ORD004',1,1,11.90),('25OPI003','25ORD005',1,1,11.90),('25OPI004','25ORD006',1,2,23.80),('25OPI005','25ORD007',1,1,11.90),('25OPI006','25ORD008',1,1,11.90),('25OPI007','25ORD009',1,1,11.90),('25OPI008','25ORD010',1,1,11.90),('25OPI009','25ORD011',1,1,11.90),('25OPI010','25ORD012',1,2,23.80),('25OPI011','25ORD013',1,1,11.90),('25OPI012','25ORD015',1,1,11.90),('25OPI013','25ORD016',1,1,11.90),('25OPI014','25ORD017',1,3,35.70),('25OPI015','25ORD018',1,1,11.90),('25OPI016','25ORD019',1,1,11.90),('25OPI017','25ORD021',5,1,3.00),('25OPI018','25ORD021',10,1,2.00),('25OPI019','25ORD022',10,1,2.00),('25OPI020','25ORD022',8,1,8.90),('25OPI021','25ORD022',9,1,8.90);
/*!40000 ALTER TABLE `order_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `Order_ID` char(8) NOT NULL,
  `Total_Amount` decimal(6,2) NOT NULL,
  `Delivery_Address` varchar(150) DEFAULT NULL,
  `Order_Type` enum('Dine-In','Take-Away','Delivery') NOT NULL DEFAULT 'Dine-In',
  `Created_At` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Status` enum('Pending','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `User_ID` int NOT NULL,
  PRIMARY KEY (`Order_ID`),
  KEY `fk_user_id_order_idx` (`User_ID`),
  CONSTRAINT `fk_user_id_order` FOREIGN KEY (`User_ID`) REFERENCES `users` (`User_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES ('25ORD001',12.61,NULL,'Dine-In','2025-12-06 13:55:18','Pending',2),('25ORD002',12.61,NULL,'Dine-In','2025-12-06 13:57:45','Pending',2),('25ORD003',12.61,NULL,'Dine-In','2025-12-06 13:59:40','Pending',2),('25ORD004',12.61,NULL,'Dine-In','2025-12-06 14:08:35','Pending',2),('25ORD005',12.61,NULL,'Dine-In','2025-12-06 14:50:21','Pending',2),('25ORD006',25.23,NULL,'Dine-In','2025-12-06 15:06:13','Pending',2),('25ORD007',12.61,NULL,'Dine-In','2025-12-06 15:12:07','Pending',2),('25ORD008',12.61,NULL,'Dine-In','2025-12-06 15:13:08','Pending',2),('25ORD009',12.61,NULL,'Dine-In','2025-12-07 11:31:19','Pending',2),('25ORD010',12.61,NULL,'Dine-In','2025-12-07 18:15:27','Pending',2),('25ORD011',12.61,NULL,'Dine-In','2025-12-07 18:35:36','Pending',2),('25ORD012',25.23,NULL,'Dine-In','2025-12-08 08:10:55','Pending',2),('25ORD013',12.61,NULL,'Dine-In','2025-12-08 08:25:25','Pending',2),('25ORD014',12.61,NULL,'Dine-In','2025-12-08 08:48:34','Pending',2),('25ORD015',12.61,NULL,'Dine-In','2025-12-08 08:51:35','Completed',2),('25ORD016',12.61,NULL,'Dine-In','2025-12-08 09:01:18','Completed',2),('25ORD017',37.84,NULL,'Dine-In','2025-12-08 11:01:44','Completed',3),('25ORD018',12.61,NULL,'Dine-In','2025-12-08 11:08:02','Completed',2),('25ORD019',12.61,NULL,'Dine-In','2025-12-08 11:20:02','Completed',2),('25ORD020',5.30,NULL,'Dine-In','2025-12-09 00:13:23','Pending',2),('25ORD021',5.30,NULL,'Dine-In','2025-12-09 00:13:23','Completed',2),('25ORD022',20.99,NULL,'Dine-In','2025-12-09 04:14:12','Completed',2),('25ORD023',10.49,NULL,'Dine-In','2025-12-09 12:40:01','Pending',2),('25ORD024',10.49,NULL,'Dine-In','2025-12-09 12:40:43','Pending',2);
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `Payment_ID` char(8) NOT NULL,
  `Payment_Method` enum('Online Banking','E-Wallet','Pay At Counter (Cash)') NOT NULL DEFAULT 'Online Banking',
  `Provider` varchar(50) NOT NULL,
  `Total_Amount` decimal(6,2) NOT NULL,
  `Status` enum('PENDING','COMPLETED','FAILED','REFUNDED') NOT NULL,
  `Created_At` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Order_ID` char(8) NOT NULL,
  PRIMARY KEY (`Payment_ID`),
  KEY `fk_order_id_payment_idx` (`Order_ID`),
  CONSTRAINT `fk_order_id_payment` FOREIGN KEY (`Order_ID`) REFERENCES `orders` (`Order_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `Product_ID` int NOT NULL AUTO_INCREMENT,
  `Product_Name` varchar(100) NOT NULL,
  `Description` varchar(200) NOT NULL,
  `Category` enum('Coffee','Tea','LightBite','HotMeal') NOT NULL DEFAULT 'Coffee',
  `Price` decimal(4,2) NOT NULL,
  `Image_Path` varchar(100) NOT NULL,
  `Best_Sale` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`Product_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'Cappuccino','Espresso topped with steamed milk and froth—creamy, balanced, and comforting.','Coffee',9.90,'cappuccino.jpeg',0),(2,'Daan Ji','Crispy toast filled with a perfectly fried egg and a touch of sauce—simple, comforting, and a Malaysian breakfast classic.','LightBite',5.90,'daanji.png',0),(3,'Honey Waffle','Golden, crispy waffles drizzled with sweet honey—perfectly soft on the inside and delightfully crunchy on the outside. A treat for any time of the day.','LightBite',10.90,'honeywaffle.jpg',0),(4,'Takoyaki','Crispy on the outside, soft and savory on the inside, filled with tender octopus and topped with savory sauces, mayo, and bonito flakes—a Japanese street food favorite!','LightBite',7.00,'takoyaki.jpg',0),(5,'Nasi Lemak','Steamed coconut rice served with spicy sambal, fried anchovies, peanuts, boiled egg, and cucumber. A Malaysian classic that warms the soul.','HotMeal',3.00,'nasilemak.jpg',1),(6,'Spaghetti Bolognese','Pasta tossed in a rich, meaty tomato sauce, topped with Parmesan. Simple, flavorful, and always a favorite.','HotMeal',12.90,'spaghetti.jpg',0),(7,'Fish & Chips','Crispy battered fish served with golden fries and tartar sauce. A classic that’s warm, crunchy, and delicious.','HotMeal',12.90,'fish&chips.jpeg',0),(8,'Espresso (Hot)','Rich and bold, a concentrated shot of pure coffee flavor—perfect for a quick energy boost.','Coffee',8.90,'espresso.webp',1),(9,'Latte (Hot)','Espresso with steamed milk, lightly sweet and smooth—ideal for any time of day.','Coffee',8.90,'latte.jpg',1),(10,'Green Tea (Hot)','Light and soothing, packed with antioxidants for a gentle pick-me-up.','Tea',2.00,'greentea.jpeg',2),(11,'Oolong Tea (Hot)','Partially fermented tea with a smooth, aromatic taste—rich and mellow.','Tea',2.90,'oolongtea.jpeg',0),(12,'English Breakfast Tea (Hot)','Classic black tea with a robust flavor—perfect for starting your day.','Tea',3.50,'englishtea.jpeg',0);
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `User_ID` int NOT NULL AUTO_INCREMENT,
  `User_Name` varchar(100) NOT NULL,
  `Password` varchar(200) NOT NULL,
  `Phone_Number` varchar(45) NOT NULL DEFAULT '^601[0-9]{8,9}$',
  `Email` varchar(100) NOT NULL,
  `Default_Address` varchar(150) DEFAULT NULL,
  `Image_Path` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`User_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'HuoYan','123456','0123456789','huoyan0928@gmail.com',NULL,NULL),(2,'Phon Mei Xin','$2y$12$MOJ3V3DY8XgahCArct0AD.quzKHCtg77xizZYx1My6nxlLN86670m','01123456789','2@gmail.com',NULL,NULL),(3,'Jack','$2y$12$qkveureF5AIJWpt7i.aDHeBPQ9haGr8WwpyCDxKpgrMSKywpgbFxW','0123456788','123@gmail.com',NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-12-10  0:21:17
