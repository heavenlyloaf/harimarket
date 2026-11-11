-- HariMarket database schema (MySQL / MariaDB)
-- Import: mysql -u root -p < sql/database.sql
-- Contains structure and reference data only (categories, sample pick-up locations).
-- No user accounts are included. Create the first admin with: php tools/create_admin.php

CREATE DATABASE IF NOT EXISTS `harimarket` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `harimarket`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `reports`, `reviews`, `notifications`, `messages`, `order_items`, `orders`,
  `cart`, `products`, `categories`, `seller_profiles`, `pickup_locations`, `users`;

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `role` enum('buyer','seller','admin') NOT NULL DEFAULT 'buyer',
  `student_number` varchar(30) DEFAULT NULL,
  `avatar` varchar(255) NOT NULL DEFAULT 'img/elements/placeholder.png',
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `temp_password` varchar(255) DEFAULT NULL,
  `temp_password_expires` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB;

CREATE TABLE `pickup_locations` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pickup_name` (`name`)
) ENGINE=InnoDB;

CREATE TABLE `seller_profiles` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL,
  `store_name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `ser_file` varchar(255) DEFAULT NULL,
  `rating` decimal(3,2) NOT NULL DEFAULT 0.00,
  `approved` tinyint(1) NOT NULL DEFAULT 0,
  `pickup_location_id` int UNSIGNED DEFAULT NULL,
  `pickup_note` varchar(255) DEFAULT NULL,
  `pickup_hours` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_seller_user` (`user_id`),
  KEY `fk_sp_location` (`pickup_location_id`),
  CONSTRAINT `fk_sp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sp_location` FOREIGN KEY (`pickup_location_id`) REFERENCES `pickup_locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `categories` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_category_name` (`name`)
) ENGINE=InnoDB;

CREATE TABLE `products` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `seller_id` int UNSIGNED NOT NULL,
  `category_id` int UNSIGNED DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int UNSIGNED NOT NULL DEFAULT 0,
  `image` varchar(255) DEFAULT 'img/elements/placeholder.png',
  `rating` decimal(3,2) NOT NULL DEFAULT 0.00,
  `review_count` int UNSIGNED NOT NULL DEFAULT 0,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_products_seller` (`seller_id`),
  KEY `fk_products_category` (`category_id`),
  CONSTRAINT `fk_products_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `cart` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL,
  `product_id` int UNSIGNED NOT NULL,
  `quantity` int UNSIGNED NOT NULL DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cart_item` (`user_id`,`product_id`),
  KEY `fk_cart_product` (`product_id`),
  CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- One order per seller. The pick-up point is copied from the seller at checkout.
CREATE TABLE `orders` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `buyer_id` int UNSIGNED NOT NULL,
  `seller_id` int UNSIGNED NOT NULL,
  `pickup_location_id` int UNSIGNED DEFAULT NULL,
  `pickup_note` varchar(255) DEFAULT NULL,
  `pickup_code` varchar(16) NOT NULL,
  `status` enum('placed','preparing','ready_for_pickup','picked_up','cancelled') NOT NULL DEFAULT 'placed',
  `total` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_pickup_code` (`pickup_code`),
  KEY `fk_orders_buyer` (`buyer_id`),
  KEY `fk_orders_seller` (`seller_id`),
  KEY `fk_orders_location` (`pickup_location_id`),
  CONSTRAINT `fk_orders_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_orders_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_orders_location` FOREIGN KEY (`pickup_location_id`) REFERENCES `pickup_locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `order_items` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` int UNSIGNED NOT NULL,
  `product_id` int UNSIGNED NOT NULL,
  `quantity` int UNSIGNED NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_oi_order` (`order_id`),
  KEY `fk_oi_product` (`product_id`),
  CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `messages` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_id` int UNSIGNED NOT NULL,
  `receiver_id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED DEFAULT NULL,
  `product_id` int UNSIGNED DEFAULT NULL,
  `body` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_msg_sender` (`sender_id`),
  KEY `fk_msg_receiver` (`receiver_id`),
  KEY `fk_msg_order` (`order_id`),
  KEY `fk_msg_product` (`product_id`),
  CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_msg_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `notifications` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL,
  `message` varchar(255) NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_notif_user` (`user_id`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- A review is allowed only for a picked-up order that contains the product.
CREATE TABLE `reviews` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED DEFAULT NULL,
  `rating` tinyint UNSIGNED NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_review_once` (`product_id`,`user_id`),
  KEY `fk_rev_user` (`user_id`),
  KEY `fk_rev_order` (`order_id`),
  CONSTRAINT `chk_rating` CHECK (`rating` BETWEEN 1 AND 5),
  CONSTRAINT `fk_rev_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rev_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rev_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `reports` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `reporter_id` int UNSIGNED NOT NULL,
  `reported_user_id` int UNSIGNED DEFAULT NULL,
  `product_id` int UNSIGNED DEFAULT NULL,
  `reason` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `status` enum('pending','resolved','dismissed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_rep_reporter` (`reporter_id`),
  KEY `fk_rep_user` (`reported_user_id`),
  KEY `fk_rep_product` (`product_id`),
  CONSTRAINT `fk_rep_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rep_user` FOREIGN KEY (`reported_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rep_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- Reference data
INSERT INTO `categories` (`name`) VALUES
  ('Drinks'), ('Food'), ('Keychains & Jewelries'), ('Thrifts'), ('Pre-owned Gadgets');

-- SAMPLE pick-up locations. Replace these with real PLM spots (admin can also edit them in the app).
INSERT INTO `pickup_locations` (`name`, `description`, `is_active`) VALUES
  ('Main Gate', 'Beside the guard house', 1),
  ('Library steps', 'Front steps of the library', 1),
  ('Canteen', 'Near the canteen entrance', 1);
