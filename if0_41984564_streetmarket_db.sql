-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql310.infinityfree.com
-- Generation Time: Jun 22, 2026 at 10:45 AM
-- Server version: 11.4.12-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_41984564_streetmarket_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `admin_notification_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` varchar(10) DEFAULT 'No',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `admin_notifications`
--

INSERT INTO `admin_notifications` (`admin_notification_id`, `admin_id`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES
(5, 3, 'New User Message', 'Simon Moyela sent you a message.', 'admin-messages.php?user=8', 'No', '2026-06-19 14:13:00'),
(4, 3, 'New User Message', 'Simon Moyela sent you a message.', 'admin-messages.php?user=8', 'No', '2026-06-19 14:11:36'),
(6, 2, 'New Seller Verification', 'Simon Moyela submitted a seller verification request.', 'seller-verification.php', 'No', '2026-06-19 14:21:44'),
(7, 2, 'New Seller Verification', 'JOHN CENA submitted a seller verification request.', 'seller-verification.php', 'Yes', '2026-06-20 06:41:41'),
(8, 2, 'Buyer Confirmed Delivery', 'Tshepang Lebakeng confirmed receiving order group #SMG20260622101455321.', 'admin-orders.php', 'No', '2026-06-22 07:17:10'),
(9, 2, 'New Seller Verification', 'Tshepang Lebakeng submitted a seller verification request.', 'seller-verification.php', 'Yes', '2026-06-22 07:20:52'),
(10, 2, 'New Seller Verification', 'Tshepang Lebakeng submitted a seller verification request.', 'seller-verification.php', 'No', '2026-06-22 07:26:52'),
(11, 2, 'New Product Pending Approval', 'Tshepang Lebakeng added a new product called \'Iphone 17\'. Please review and approve or reject it.', 'manage-products.php', 'No', '2026-06-22 07:30:57');

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `admin_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `work_phone` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `status` enum('Active','Suspended') DEFAULT 'Active',
  `last_login` datetime DEFAULT NULL,
  `failed_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `last_activity` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`admin_id`, `full_name`, `email`, `password`, `role`, `created_at`, `work_phone`, `department`, `status`, `last_login`, `failed_attempts`, `locked_until`, `updated_at`, `last_activity`) VALUES
(2, 'Main Super Admin', 'kwanomag@streetmarket.com', '$2y$10$xTKppl38qN9rloN5EZjVEeENFW4Twd2GcqGZWeno2DLIjIbKNrVfS', 'super_admin', '2026-05-26 20:17:06', NULL, NULL, 'Active', '2026-06-22 07:21:55', 0, NULL, NULL, '2026-06-21 10:00:57'),
(3, 'Peter Mokaba', 'peter@streetmarket.com', '$2y$10$LePAHd6oYbvzrBfaJ.XLeOPNc16HZynl8vB6O2t7IwdTj881QfZIO', 'report_manager', '2026-06-05 12:03:30', NULL, NULL, 'Active', '2026-06-19 14:12:24', 0, NULL, '2026-06-22 07:34:31', '2026-06-19 14:22:07');

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `quantity` int(11) NOT NULL DEFAULT 1,
  `selected_size` varchar(100) DEFAULT NULL,
  `selected_color` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`cart_id`, `user_id`, `product_id`, `added_at`, `quantity`, `selected_size`, `selected_color`) VALUES
(21, 6, 15, '2026-06-11 13:51:52', 1, NULL, NULL),
(22, 6, 14, '2026-06-11 13:51:57', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `category`
--

CREATE TABLE `category` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `category`
--

INSERT INTO `category` (`category_id`, `category_name`) VALUES
(1, 'Electronics'),
(2, 'Fashion'),
(3, 'Furniture'),
(4, 'Vehicles'),
(5, 'Sports'),
(6, 'Beauty'),
(7, 'Books'),
(8, 'Home Appliances');

-- --------------------------------------------------------

--
-- Table structure for table `disputes`
--

CREATE TABLE `disputes` (
  `dispute_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `dispute_reason` varchar(255) DEFAULT NULL,
  `dispute_details` text DEFAULT NULL,
  `dispute_status` enum('Open','Investigating','Resolved') DEFAULT 'Open',
  `admin_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `product_id` int(11) DEFAULT NULL,
  `is_read` enum('Yes','No') DEFAULT 'No',
  `delivered_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `is_deleted` enum('No','Yes') DEFAULT 'No',
  `receiver_type` varchar(20) DEFAULT 'user',
  `sender_type` varchar(20) DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `message`, `sent_at`, `product_id`, `is_read`, `delivered_at`, `read_at`, `is_deleted`, `receiver_type`, `sender_type`) VALUES
(1, 7, 7, 'hey do you have nike tech fleece medium full outfit', '2026-06-04 19:20:06', 8, 'No', NULL, NULL, 'No', 'user', 'user'),
(2, 7, 7, 'hey do you have nike tech fleece medium full outfit', '2026-06-04 19:20:09', 8, 'No', NULL, NULL, 'No', 'user', 'user'),
(3, 7, 7, 'yes', '2026-06-04 19:20:28', 0, 'No', NULL, NULL, 'No', 'user', 'user'),
(4, 9, 7, 'hi', '2026-06-04 19:24:45', 8, 'Yes', NULL, '2026-06-05 05:09:45', 'No', 'user', 'user'),
(7, 9, 7, 'hi', '2026-06-04 19:38:42', 0, 'Yes', NULL, '2026-06-05 05:09:45', 'No', 'user', 'user'),
(9, 7, 9, 'hey', '2026-06-04 19:40:11', 0, 'Yes', NULL, '2026-06-05 05:12:40', 'No', 'user', 'user'),
(10, 7, 9, 'ho', '2026-06-04 19:41:58', 0, 'Yes', NULL, '2026-06-05 05:12:40', 'No', 'user', 'user'),
(11, 7, 9, 'ho', '2026-06-04 19:42:44', 0, 'Yes', NULL, '2026-06-05 05:12:40', 'No', 'user', 'user'),
(12, 7, 9, 'ho', '2026-06-04 19:43:06', 0, 'Yes', NULL, '2026-06-05 05:12:40', 'No', 'user', 'user'),
(13, 7, 9, 'ho', '2026-06-05 12:10:28', 0, 'Yes', '2026-06-05 05:10:28', '2026-06-05 05:12:40', 'No', 'user', 'user'),
(16, 9, 7, 'hi', '2026-06-05 12:20:19', 0, 'Yes', '2026-06-05 05:20:19', '2026-06-05 05:20:32', 'No', 'user', 'user'),
(17, 8, 9, 'hi', '2026-06-05 12:21:16', 0, 'Yes', '2026-06-05 05:21:16', NULL, 'No', 'user', 'user'),
(19, 7, 8, 'huii', '2026-06-05 12:32:58', 0, 'Yes', NULL, NULL, 'No', 'user', 'user'),
(20, 8, 7, 'dvd', '2026-06-05 12:34:33', 0, 'No', NULL, NULL, 'No', 'user', 'user'),
(21, 8, 7, 'dvfb', '2026-06-05 12:34:39', 0, 'No', NULL, NULL, 'No', 'user', 'user'),
(22, 8, 7, 'dvfbfb', '2026-06-05 12:34:42', 0, 'No', NULL, NULL, 'No', 'user', 'user'),
(23, 8, 7, 'dvfbfb', '2026-06-05 12:34:44', 0, 'No', NULL, NULL, 'No', 'user', 'user'),
(24, 8, 7, 'dvfbfb', '2026-06-05 12:34:45', 0, 'No', NULL, NULL, 'No', 'user', 'user'),
(25, 8, 7, 'dvfbfb', '2026-06-05 12:34:47', 0, 'No', NULL, NULL, 'No', 'user', 'user'),
(26, 8, 7, 'dvfbfb', '2026-06-05 12:34:49', 0, 'No', NULL, NULL, 'No', 'user', 'user'),
(27, 8, 7, 'dvfbfb', '2026-06-05 12:34:50', 0, 'No', NULL, NULL, 'No', 'user', 'user'),
(28, 8, 7, 'uku', '2026-06-05 12:46:07', 0, 'No', NULL, NULL, 'No', 'user', 'user'),
(29, 9, 7, 'nfn', '2026-06-05 12:46:30', 0, 'No', NULL, NULL, 'No', 'user', 'user'),
(30, 9, 8, 'gng', '2026-06-05 12:46:38', 0, 'Yes', NULL, NULL, 'No', 'user', 'user'),
(31, 8, 9, 'hmm', '2026-06-05 12:47:23', 0, 'Yes', NULL, NULL, 'No', 'user', 'user'),
(32, 8, 9, 'ngng', '2026-06-05 12:48:09', 0, 'Yes', NULL, NULL, 'No', 'user', 'user'),
(33, 8, 9, 'ngng', '2026-06-05 12:48:28', 0, 'Yes', NULL, NULL, 'No', 'user', 'user'),
(34, 9, 8, 'sfefe', '2026-06-05 12:59:19', 0, 'Yes', NULL, NULL, 'No', 'user', 'user'),
(35, 9, 8, 'cec', '2026-06-05 12:59:46', 0, 'Yes', NULL, NULL, 'No', 'user', 'user'),
(36, 9, 7, 'xcvcdv', '2026-06-05 13:13:02', 0, 'No', NULL, NULL, 'No', 'user', 'user'),
(37, 9, 7, 'xcvcdv', '2026-06-05 13:13:32', 0, 'No', NULL, NULL, 'No', 'user', 'user'),
(38, 9, 7, 'dd', '2026-06-05 13:13:39', 0, 'No', NULL, NULL, 'No', 'user', 'user'),
(39, 8, 9, 'efe', '2026-06-05 13:14:53', 0, 'Yes', NULL, '2026-06-05 06:15:03', 'No', 'user', 'user'),
(89, 9, 8, 'xs', '2026-06-05 13:23:39', 0, 'Yes', NULL, '2026-06-05 06:39:38', 'No', 'user', 'user'),
(93, 11, 8, 'Hi', '2026-06-07 15:53:18', 15, 'Yes', NULL, '2026-06-19 04:56:07', 'No', 'user', 'user'),
(103, 5, 7, 'hii', '2026-06-15 18:16:05', 16, 'No', NULL, NULL, 'No', 'user', 'user'),
(105, 5, 7, 'hello', '2026-06-19 11:45:02', 0, 'No', NULL, NULL, 'No', 'user', 'user'),
(108, 5, 3, 'hii', '2026-06-19 11:53:23', 0, 'No', NULL, NULL, 'No', 'admin', 'user'),
(109, 5, 8, 'This message was deleted.', '2026-06-19 11:53:58', 0, 'Yes', NULL, '2026-06-19 04:54:38', 'Yes', 'user', 'user'),
(110, 8, 5, 'hii', '2026-06-19 11:55:04', 0, 'Yes', NULL, '2026-06-19 04:55:09', 'No', 'user', 'user'),
(111, 5, 8, 'how are you', '2026-06-19 11:55:39', 0, 'Yes', NULL, '2026-06-19 04:55:39', 'No', 'user', 'user'),
(112, 8, 5, 'im good thanks how are you', '2026-06-19 11:55:54', 0, 'Yes', NULL, '2026-06-19 04:55:59', 'No', 'user', 'user'),
(113, 8, 3, 'hii', '2026-06-19 11:56:16', 0, 'Yes', NULL, '2026-06-19 14:12:37', 'No', 'admin', 'user'),
(114, 5, 8, 'hi what sizes and colors are available', '2026-06-19 14:51:20', 14, 'Yes', NULL, '2026-06-19 07:51:35', 'No', 'user', 'user'),
(115, 8, 5, 'we have size 4 to 10 and colors are only black and whit', '2026-06-19 14:52:06', 14, 'Yes', NULL, '2026-06-19 07:52:09', 'No', 'user', 'user'),
(116, 5, 8, 'okay im gonna place an order please make them black', '2026-06-19 14:52:24', 14, 'Yes', NULL, '2026-06-19 07:52:25', 'No', 'user', 'user'),
(122, 2, 1, 'hii', '2026-06-19 20:26:36', NULL, 'No', NULL, NULL, 'No', 'user', 'admin'),
(123, 2, 5, 'hi', '2026-06-19 20:26:51', NULL, 'Yes', NULL, '2026-06-20 07:12:50', 'No', 'user', 'admin'),
(124, 5, 8, 'ðŸ˜Š', '2026-06-19 20:27:35', 0, 'Yes', NULL, '2026-06-19 14:11:30', 'No', 'user', 'user'),
(125, 5, 3, 'ðŸ˜Š', '2026-06-19 20:27:41', 0, 'No', NULL, NULL, 'No', 'admin', 'user'),
(126, 2, 5, 'how are you', '2026-06-19 20:28:01', NULL, 'Yes', NULL, '2026-06-20 07:12:50', 'No', 'user', 'admin'),
(127, 2, 5, 'hi', '2026-06-19 21:11:04', NULL, 'Yes', NULL, '2026-06-20 07:12:50', 'No', 'user', 'admin'),
(128, 8, 3, 'hii', '2026-06-19 21:11:36', 0, 'Yes', NULL, '2026-06-19 14:12:37', 'No', 'admin', 'user'),
(129, 3, 8, 'hi', '2026-06-19 21:12:42', NULL, 'Yes', NULL, '2026-06-19 14:12:46', 'No', 'user', 'admin'),
(130, 8, 3, 'how are you', '2026-06-19 21:13:00', 0, 'Yes', NULL, '2026-06-19 14:13:02', 'No', 'admin', 'user'),
(131, 8, 5, 'hii', '2026-06-19 21:13:23', 19, 'Yes', NULL, '2026-06-19 14:17:01', 'No', 'user', 'user'),
(132, 5, 8, 'hii', '2026-06-19 21:17:17', 18, 'Yes', NULL, '2026-06-19 14:17:34', 'No', 'user', 'user'),
(133, 8, 5, 'hii do you have the orange one', '2026-06-20 14:08:20', 19, 'Yes', NULL, '2026-06-20 07:08:31', 'No', 'user', 'user'),
(134, 5, 8, 'yes', '2026-06-20 14:08:37', 19, 'Yes', NULL, '2026-06-20 07:08:39', 'No', 'user', 'user'),
(135, 13, 5, 'hiio do you ave the orange one', '2026-06-22 14:08:39', 19, 'Yes', NULL, '2026-06-22 07:08:49', 'No', 'user', 'user'),
(136, 13, 8, 'hii', '2026-06-22 14:11:13', 15, 'No', NULL, NULL, 'No', 'user', 'user');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` enum('Yes','No') DEFAULT 'No',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tittle` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `message`, `link`, `is_read`, `created_at`, `tittle`) VALUES
(6, 7, 'You have a new order. Amount: R7,524.99.', 'manage-deliveries.php', 'No', '2026-06-07 15:56:33', ''),
(8, 7, 'The buyer confirmed completion for Samsung A37 5G 256G 8GB(Color May Vary).', 'order-tracking.php?id=7', 'Yes', '2026-06-07 15:59:23', ''),
(16, 6, 'Your seller account has been verified. You can now upload and sell products on StreetMarket.', 'user-profile.php', 'No', '2026-06-11 12:45:27', ''),
(17, 6, 'Your seller account has been verified. You can now upload and sell products on StreetMarket.', 'user-profile.php', 'No', '2026-06-11 12:50:49', ''),
(19, 7, 'You have a new order. Amount: R7,524.99.', 'manage-deliveries.php', 'No', '2026-06-11 13:51:18', ''),
(20, 6, 'Your order for Nike Lightweight Running Gloves is now Delivered.', 'order-tracking.php?id=8', 'No', '2026-06-11 14:52:15', ''),
(21, 6, 'Your order for Nike Lightweight Running Gloves is now Processing.', 'order-tracking.php?id=8', 'No', '2026-06-11 14:54:35', ''),
(22, 6, 'Your order group #8 is now Delivered.', 'order-tracking.php?id=8', 'No', '2026-06-11 15:05:26', ''),
(25, 11, 'Your seller verification was rejected. Please review your business details and ID document, then submit again.', 'seller-verification.php', 'No', '2026-06-11 18:31:32', ''),
(26, 7, 'Kops Lerutlo sent you a new message.', 'messages.php?user=5&product=16', 'No', '2026-06-15 18:16:05', ''),
(27, 7, 'You have a new order. Amount: R7,524.99.', 'manage-deliveries.php', 'No', '2026-06-15 18:39:04', ''),
(28, 7, 'Kops Lerutlo sent you a new message.', 'messages.php?user=5', 'No', '2026-06-19 11:45:02', ''),
(30, 5, 'Simon Moyela sent you a new message.', 'messages.php?user=8', 'Yes', '2026-06-19 11:55:04', ''),
(32, 5, 'Simon Moyela sent you a new message.', 'messages.php?user=8', 'Yes', '2026-06-19 11:55:54', ''),
(33, 5, 'Your seller verification was rejected. Please review your business details and ID document, then submit again.', 'seller-verification.php', 'Yes', '2026-06-19 13:52:16', ''),
(34, 5, 'Your seller account has been verified. You can now upload and sell products on StreetMarket.', 'user-profile.php', 'Yes', '2026-06-19 14:00:45', ''),
(35, 6, 'Your seller verification was rejected. Please review your business details, ID document and proof of residence, then submit again.', 'seller-verification.php', 'No', '2026-06-19 14:21:02', ''),
(36, 5, 'Your product \'iPhone 17 Pro max\' has been removed from StreetMarket by admin.', 'my-listings.php', 'Yes', '2026-06-19 14:27:23', 'Product Removed'),
(37, 5, 'Your product \'iPhone 17 Pro max\' was rejected by admin. Please review the product details and upload a corrected listing if needed.', 'my-listings.php', 'Yes', '2026-06-19 14:27:32', 'Product Rejected'),
(39, 5, 'Your product \'iPhone 17 Pro max\' has been approved and is now available on StreetMarket.', 'my-listings.php', 'Yes', '2026-06-19 14:33:42', 'Product Approved'),
(40, 5, 'Your product \'iPhone 17 Pro max\' was rejected by admin. Reason: We think you dublicated your product addition', 'my-listings.php', 'Yes', '2026-06-19 14:34:38', 'Product Rejected'),
(41, 8, 'Your seller verification was rejected. Please review your business details, ID document and proof of residence, then submit again.', 'seller-verification.php', 'Yes', '2026-06-19 14:35:03', ''),
(42, 5, 'Your seller verification was rejected. Reason: Please provide valid proof of residence not older than 3 months Please review your business details, ID document and proof of residence, then submit again.', 'seller-verification.php', 'Yes', '2026-06-19 14:42:57', 'Seller Verification Rejected'),
(43, 5, 'Your seller account has been verified. You can now upload and sell products on StreetMarket.', 'user-profile.php', 'Yes', '2026-06-19 14:43:34', 'Seller Verification Approved'),
(44, 5, 'Your seller verification was rejected. Reason: jgio Please review your business details, ID document and proof of residence, then submit again.', 'seller-verification.php', 'Yes', '2026-06-19 14:44:12', 'Seller Verification Rejected'),
(45, 5, 'Your seller account has been verified. You can now upload and sell products on StreetMarket.', 'user-profile.php', 'Yes', '2026-06-19 14:45:01', 'Seller Verification Approved'),
(46, 8, 'Kops Lerutlo sent you a new message.', 'messages.php?user=5&product=14', 'Yes', '2026-06-19 14:51:20', ''),
(47, 5, 'Simon Moyela sent you a new message.', 'messages.php?user=8&product=14', 'Yes', '2026-06-19 14:52:06', ''),
(48, 8, 'Kops Lerutlo sent you a new message.', 'messages.php?user=5&product=14', 'Yes', '2026-06-19 14:52:24', ''),
(49, 8, 'You have received a new order from Kops Lerutlo. Order Group: SMG20260619115706344. Items: 1. Amount: R5,999.99. Please check your deliveries.', 'manage-deliveries.php', 'Yes', '2026-06-19 15:57:06', ''),
(50, 6, 'You have received a new order from Kops Lerutlo. Order Group: SMG20260619115706344. Items: 1. Amount: R799.98. Please check your deliveries.', 'manage-deliveries.php', 'No', '2026-06-19 15:57:06', ''),
(51, 7, 'You have received a new order from Kops Lerutlo. Order Group: SMG20260619115706344. Items: 1. Amount: R14,999.98. Please check your deliveries.', 'manage-deliveries.php', 'No', '2026-06-19 15:57:06', ''),
(52, 5, 'Your order was placed successfully. Order Group: SMG20260619115706344. You can view it under My Orders.', 'my-orders.php', 'Yes', '2026-06-19 15:57:06', ''),
(53, 6, 'A buyer cancelled an order group. Product stock has been restored.', 'manage-deliveries.php', 'No', '2026-06-19 16:04:53', ''),
(54, 5, 'You have received a new order from Simon Moyela. Order Group: SMG20260619123106944. Items: 1. Amount: R18,999.99. Please check your deliveries.', 'manage-deliveries.php', 'Yes', '2026-06-19 16:31:06', ''),
(55, 8, 'Your order was placed successfully. Order Group: SMG20260619123106944. You can view it under Orders.', 'orders.php', 'Yes', '2026-06-19 16:31:06', ''),
(56, 5, 'Your order group #SMG20260619115706344 is now Delivered.', 'order-tracking.php?id=12', 'Yes', '2026-06-19 19:10:57', ''),
(57, 8, 'The buyer confirmed completion for order group #SMG20260619115706344.', 'manage-deliveries.php', 'Yes', '2026-06-19 19:11:20', ''),
(58, 8, 'A buyer reviewed your product: Nike Tech Tracksuit.', 'product-details.php?id=18', 'Yes', '2026-06-19 19:12:40', ''),
(59, 8, 'A buyer submitted a report related to order group #SMG20260619115706344.', 'notifications.php', 'Yes', '2026-06-19 19:16:39', ''),
(60, 8, 'Your order group #SMG20260619123106944 is now Shipped.', 'order-tracking.php?id=15', 'Yes', '2026-06-19 19:33:24', 'Order Group Updated'),
(61, 8, 'Your order group #SMG20260619123106944 is now Delivered.', 'order-tracking.php?id=15', 'Yes', '2026-06-19 19:33:54', 'Order Group Updated'),
(62, 5, 'The buyer confirmed completion for order group #SMG20260619123106944.', 'manage-deliveries.php', 'Yes', '2026-06-19 19:34:01', 'Order Group Completed'),
(63, 5, 'Simon Moyela reviewed your product: iPhone 17 Pro max. Rating: 5/5. Comment: It\\\'s the actual iPhone 17 Pro Max, bro...can\\\'t believe it.', 'order-tracking.php?id=15', 'Yes', '2026-06-19 19:35:03', 'New Product Review'),
(64, 5, 'Simon Moyela submitted a report for order group #SMG20260619123106944. Reason: Wrong Product. Details: He made a mistake with the colors', 'order-tracking.php?id=15', 'Yes', '2026-06-19 19:35:43', 'New Report Submitted'),
(65, 2, 'Simon Moyela submitted a report against seller African Food for order group #SMG20260619123106944. Reason: Wrong Product. Details: He made a mistake with the colors', 'admin-reports.php', 'No', '2026-06-19 19:35:43', 'New Delivery Report'),
(66, 8, 'Your report for Order #15 has been resolved by StreetMarket.', 'notifications.php', 'Yes', '2026-06-19 19:37:30', ''),
(67, 5, 'A report related to Order #15 has been resolved by StreetMarket.', 'notifications.php', 'Yes', '2026-06-19 19:37:30', ''),
(68, 5, 'Your order group #SMG20260619115706344 was cancelled by admin and refunded.', 'orders.php', 'Yes', '2026-06-19 19:40:41', ''),
(69, 7, 'Admin cancelled order group #SMG20260619115706344. Product stock has been restored.', 'manage-deliveries.php', 'No', '2026-06-19 19:40:41', ''),
(70, 1, 'Main Super Admin sent you a message.', 'messages.php', 'No', '2026-06-19 20:26:36', 'New Message'),
(71, 5, 'Main Super Admin sent you a message.', 'messages.php', 'Yes', '2026-06-19 20:26:51', 'New Message'),
(72, 8, 'Kops Lerutlo sent you a new message.', 'messages.php?user=5', 'Yes', '2026-06-19 20:27:35', ''),
(73, 5, 'Main Super Admin sent you a message.', 'messages.php', 'Yes', '2026-06-19 20:28:01', 'New Message'),
(74, 5, 'Main Super Admin sent you a message.', 'messages.php?user=-2', 'Yes', '2026-06-19 21:11:04', 'New Message'),
(75, 8, 'Peter Mokaba sent you a message.', 'messages.php?user=-3', 'Yes', '2026-06-19 21:12:42', 'New Message'),
(76, 5, 'Simon Moyela sent you a new message.', 'messages.php?user=8&product=19', 'Yes', '2026-06-19 21:13:23', 'New Message'),
(77, 8, 'Kops Lerutlo sent you a new message.', 'messages.php?user=5&product=18', 'Yes', '2026-06-19 21:17:17', 'New Message'),
(78, 8, 'Your seller account has been verified. You can now upload and sell products on StreetMarket.', 'user-profile.php', 'Yes', '2026-06-19 21:22:57', 'Seller Verification Approved'),
(79, 12, 'Your seller account has been verified. You can now upload and sell products on StreetMarket.', 'user-profile.php', 'No', '2026-06-20 13:44:28', 'Seller Verification Approved'),
(80, 8, 'Your product \'Nike Air Force 1 Shoes\' was rejected by admin. Reason: bla bla', 'my-listings.php', 'No', '2026-06-20 14:04:54', 'Product Rejected'),
(81, 8, 'Your product \'Nike Air Force 1 Shoes\' has been approved and is now available on StreetMarket.', 'my-listings.php', 'No', '2026-06-20 14:06:00', 'Product Approved'),
(82, 5, 'Simon Moyela sent you a new message.', 'messages.php?user=8&product=19', 'No', '2026-06-20 14:08:20', 'New Message'),
(83, 8, 'Kops Lerutlo sent you a new message.', 'messages.php?user=5&product=19', 'No', '2026-06-20 14:08:37', 'New Message'),
(84, 5, 'Tshepang Lebakeng sent you a new message.', 'messages.php?user=13&product=19', 'No', '2026-06-22 14:08:39', 'New Message'),
(85, 8, 'Tshepang Lebakeng sent you a new message.', 'messages.php?user=13&product=15', 'No', '2026-06-22 14:11:13', 'New Message'),
(86, 5, 'You have received a new order from Tshepang Lebakeng. Order Group: SMG20260622101455321. Items: 1. Amount: R37,999.98. Please check your deliveries.', 'manage-deliveries.php', 'Yes', '2026-06-22 14:14:55', ''),
(87, 8, 'You have received a new order from Tshepang Lebakeng. Order Group: SMG20260622101455321. Items: 1. Amount: R1,499.97. Please check your deliveries.', 'manage-deliveries.php', 'No', '2026-06-22 14:14:55', ''),
(88, 13, 'Your order was placed successfully. Order Group: SMG20260622101455321. You can view it under Orders.', 'orders.php', 'No', '2026-06-22 14:14:55', ''),
(89, 13, 'Your order group #SMG20260622101455321 is now Delivered.', 'order-tracking.php?id=16', 'No', '2026-06-22 14:16:30', 'Order Group Updated'),
(90, 5, 'The buyer confirmed completion for order group #SMG20260622101455321.', 'manage-deliveries.php', 'No', '2026-06-22 14:17:10', 'Order Group Completed'),
(92, 5, 'Tshepang Lebakeng submitted a report for order group #SMG20260622101455321. Reason: Other. Details: The order took longer than expected', 'order-tracking.php?id=16', 'Yes', '2026-06-22 14:18:14', 'New Report Submitted'),
(93, 2, 'Tshepang Lebakeng submitted a report against seller African Food for order group #SMG20260622101455321. Reason: Other. Details: The order took longer than expected', 'admin-reports.php', 'No', '2026-06-22 14:18:14', 'New Delivery Report'),
(94, 13, 'Your seller verification was rejected. Reason: Please upload your profile image Please review your business details, ID document and proof of residence, then submit again.', 'seller-verification.php', 'Yes', '2026-06-22 14:26:17', 'Seller Verification Rejected'),
(95, 13, 'Your seller account has been verified. You can now upload and sell products on StreetMarket.', 'user-profile.php', 'No', '2026-06-22 14:27:54', 'Seller Verification Approved'),
(96, 13, 'Your product \'Iphone 17\' was rejected by admin. Reason: The product images are not clear or high quality', 'my-listings.php', 'Yes', '2026-06-22 14:31:47', 'Product Rejected');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `delivery_address` text NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'cash',
  `payment_status` enum('pending','paid','refunded') DEFAULT 'paid',
  `delivery_status` enum('processing','packed','shipped','out_for_delivery','delivered') DEFAULT 'processing',
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','completed','cancelled') DEFAULT 'completed',
  `buyer_confirmed` enum('Yes','No') DEFAULT 'No',
  `cancelled_by` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `delivery_method` varchar(30) DEFAULT NULL,
  `estimated_time` varchar(100) DEFAULT NULL,
  `order_group_id` varchar(50) DEFAULT NULL,
  `delivery_fee` decimal(10,2) DEFAULT 0.00,
  `selected_size` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `buyer_id`, `seller_id`, `product_id`, `delivery_address`, `total_amount`, `payment_method`, `payment_status`, `delivery_status`, `order_date`, `status`, `buyer_confirmed`, `cancelled_by`, `quantity`, `delivery_method`, `estimated_time`, `order_group_id`, `delivery_fee`, `selected_size`) VALUES
(2, 9, 7, 10, 'Redhouse Midrand\r\n27 Alsation Rd, Limpopo', '24024.98', 'card', 'refunded', 'processing', '2026-06-07 13:02:16', 'cancelled', 'No', 'buyer', 2, 'delivery', 'Estimated delivery: 45 minutes to 1 hour 30 minutes', NULL, '0.00', NULL),
(3, 9, 8, 14, 'Redhouse Midrand\r\n27 Alsation Rd, Limpopo', '2524.99', 'card', 'paid', 'delivered', '2026-06-07 15:19:29', 'completed', 'Yes', NULL, 1, 'delivery', 'Estimated delivery: 45 minutes to 1 hour 30 minutes', NULL, '0.00', NULL),
(4, 9, 7, 16, 'Redhouse Midrand\r\n27 Alsation Rd, Limpopo', '7524.99', 'card', 'paid', 'delivered', '2026-06-07 15:19:29', 'pending', 'No', NULL, 1, 'delivery', 'Estimated delivery: 45 minutes to 1 hour 30 minutes', NULL, '0.00', NULL),
(5, 10, 7, 10, '245 Loksion bieskop, Mpumalanga', '12024.99', 'card', 'paid', 'processing', '2026-06-07 15:31:00', 'pending', 'No', NULL, 1, 'delivery', 'Estimated delivery: 45 minutes to 1 hour 30 minutes', NULL, '0.00', NULL),
(6, 11, 8, 15, '25 Gloria hills, Parkway Gardens, Western Cape', '1524.97', 'card', 'paid', 'delivered', '2026-06-07 15:56:33', 'completed', 'Yes', NULL, 3, 'delivery', 'Estimated delivery: 45 minutes to 1 hour 30 minutes', NULL, '0.00', NULL),
(7, 11, 7, 16, '25 Gloria hills, Parkway Gardens, Western Cape', '7524.99', 'card', 'paid', 'delivered', '2026-06-07 15:56:33', 'completed', 'Yes', NULL, 1, 'delivery', 'Estimated delivery: 45 minutes to 1 hour 30 minutes', NULL, '0.00', NULL),
(8, 6, 8, 15, ', KwaZulu-Natal', '524.99', 'cash', 'pending', 'delivered', '2026-06-11 13:51:18', 'completed', 'Yes', NULL, 1, 'delivery', 'Estimated delivery: 45 minutes to 1 hour 30 minutes', NULL, '0.00', NULL),
(9, 6, 8, 14, ', KwaZulu-Natal', '2499.99', 'cash', 'pending', 'delivered', '2026-06-11 13:51:18', 'completed', 'Yes', NULL, 1, 'delivery', 'Estimated delivery: 45 minutes to 1 hour 30 minutes', NULL, '0.00', NULL),
(10, 6, 7, 16, ', KwaZulu-Natal', '7524.99', 'cash', 'pending', 'processing', '2026-06-11 13:51:18', 'pending', 'No', NULL, 1, 'delivery', 'Estimated delivery: 45 minutes to 1 hour 30 minutes', NULL, '0.00', NULL),
(11, 5, 7, 16, '25 Alsation Rd, Glen Austin, Limpopo', '7524.99', 'cash', 'pending', 'processing', '2026-06-15 18:39:04', 'pending', 'No', NULL, 1, 'delivery', 'Estimated delivery: 45 minutes to 1 hour 30 minutes', NULL, '0.00', NULL),
(12, 5, 8, 18, '25 Alsation Rd, Glen Austin, Limpopo', '6024.99', 'card', 'paid', 'delivered', '2026-06-19 15:57:06', 'completed', 'Yes', NULL, 1, 'delivery', 'Estimated delivery: 45 minutes', 'SMG20260619115706344', '25.00', 'M'),
(13, 5, 6, 17, '25 Alsation Rd, Glen Austin, Limpopo', '799.98', 'card', 'refunded', 'processing', '2026-06-19 15:57:06', 'cancelled', 'No', 'buyer', 2, 'delivery', 'Estimated delivery: 45 minutes to 1 hour 30 minutes', 'SMG20260619115706344', '0.00', ''),
(14, 5, 7, 16, '25 Alsation Rd, Glen Austin, Limpopo', '14999.98', 'card', 'refunded', '', '2026-06-19 15:57:06', 'cancelled', 'No', 'admin', 2, 'delivery', 'Estimated delivery: 45 minutes to 1 hour 30 minutes', 'SMG20260619115706344', '0.00', ''),
(15, 8, 5, 19, 'STAND NO 255\r\nGA MAHLOKWANE, Limpopo', '19024.99', 'card', 'paid', 'delivered', '2026-06-19 16:31:06', 'completed', 'Yes', NULL, 1, 'delivery', 'Estimated delivery: 45 minutes', 'SMG20260619123106944', '25.00', ''),
(16, 13, 5, 19, 'STAND NO 255\r\nGA MAHLOKWANE, Limpopo', '38024.98', 'card', 'paid', 'delivered', '2026-06-22 14:14:55', 'completed', 'Yes', NULL, 2, 'delivery', 'Delivered at 09:45am', 'SMG20260622101455321', '25.00', ''),
(17, 13, 8, 15, 'STAND NO 255\r\nGA MAHLOKWANE, Limpopo', '1499.97', 'card', 'paid', 'processing', '2026-06-22 14:14:55', 'pending', 'No', NULL, 3, 'delivery', 'Estimated delivery: 45 minutes to 1 hour 30 minutes', 'SMG20260622101455321', '0.00', '');

-- --------------------------------------------------------

--
-- Table structure for table `product`
--

CREATE TABLE `product` (
  `product_id` int(11) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `image` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `product_condition` varchar(100) DEFAULT NULL,
  `delivery_option` varchar(100) DEFAULT NULL,
  `status` enum('available','sold') DEFAULT 'available',
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `delivery_available` enum('Yes','No') DEFAULT 'Yes',
  `category` varchar(100) DEFAULT NULL,
  `moderation_status` enum('pending','approved','rejected') DEFAULT 'approved',
  `available_sizes` varchar(255) DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product`
--

INSERT INTO `product` (`product_id`, `product_name`, `description`, `price`, `quantity`, `image`, `location`, `product_condition`, `delivery_option`, `status`, `user_id`, `created_at`, `delivery_available`, `category`, `moderation_status`, `available_sizes`, `rejection_reason`) VALUES
(10, 'Samsung Neo QLED smart tv', 'it is the latest samsung smart tv....', '11999.99', 0, '1780602375_7_0_download (1).webp', 'Limpopo', 'New', 'Delivery Available', 'sold', 7, '2026-06-04 19:46:14', 'Yes', 'Electronics', 'approved', NULL, NULL),
(14, 'Nike Air Force 1 Shoes', 'We have all sizes and colors.', '2499.99', 3, '1780842776_8_0_nike - Copy.jpg', 'Limpopo', 'New', 'Delivery Available', 'available', 8, '2026-06-07 14:32:56', 'Yes', 'Fashion', 'approved', NULL, NULL),
(15, 'Nike Lightweight Running Gloves', 'We have all sizes and they are unisex', '499.99', 0, '1780842967_8_0_gloves.jpg', 'Limpopo', 'New', 'Delivery Available', 'sold', 8, '2026-06-07 14:36:07', 'Yes', 'Fashion', 'approved', NULL, NULL),
(16, 'Samsung A37 5G 256G 8GB(Color May Vary)', 'We have the green, black, and white colors of the Samsung A37 5G 286GB total storage and 8GB memory', '7499.99', 16, '1780843619_7_0_phone.jpg', 'Gauteng', 'New', 'Delivery Available', 'available', 7, '2026-06-07 14:46:58', 'Yes', 'Electronics', 'approved', NULL, NULL),
(17, '10 Piece Green Garden Tool set with case', 'It is a case with 10 pieces of gardening tools that are commonly used', '399.99', 10, '1781183135_6_0_g1.jpg', 'Gauteng', 'New', 'Delivery Available', 'available', 6, '2026-06-11 13:05:36', 'Yes', 'Home', 'approved', NULL, NULL),
(18, 'Nike Tech Tracksuit', 'We have 3 different colors which are Red, Blue, and grey.', '5999.99', 5, '1781871370_8_0_OIP1.webp', 'Gauteng', 'New', 'Delivery Available', 'available', 8, '2026-06-19 12:16:11', 'Yes', 'Fashion', 'approved', 'XS, S, M, L, XL', NULL),
(19, 'iPhone 17 Pro max', 'We have colors orange, peach black, and white for the Iphone 17 pro max', '18999.99', 3, '1781877942_5_0_i1.jpg', 'Limpopo, Burgesfort', 'New', 'Delivery Available', 'available', 5, '2026-06-19 14:05:42', 'Yes', 'Electronics', 'approved', '', NULL),
(20, 'iPhone 17 Pro max', 'We have colors orange, peach black, and white for the Iphone 17 pro max', '18999.99', 6, '1781878220_5_0_i1.jpg', 'Limpopo, Burgesfort', 'New', 'Delivery Available', '', 5, '2026-06-19 14:10:20', 'Yes', 'Electronics', 'rejected', '', NULL),
(21, 'Iphone 17', 'We have the 3 popular color orange, peach black, and white', '12000.00', 10, '1782138657_13_0_i3.jpg', 'Midrand, 32 Alsation Rd', 'New', 'Delivery Available', '', 13, '2026-06-22 14:30:57', 'Yes', 'Electronics', 'rejected', '', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `image_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_name` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`image_id`, `product_id`, `image_name`, `uploaded_at`) VALUES
(1, 6, '1779806320_5_0_i1).jpg', '2026-05-26 14:38:40'),
(2, 6, '1779806320_5_1_i2.jpg', '2026-05-26 14:38:40'),
(3, 6, '1779806320_5_2_i3.jpg', '2026-05-26 14:38:40'),
(27, 14, '1780842776_8_1_nike.jpg', '2026-06-07 14:32:56'),
(26, 14, '1780842776_8_0_nike - Copy.jpg', '2026-06-07 14:32:56'),
(7, 8, '1780600752_7_0_hoodie.jpg', '2026-06-04 19:19:12'),
(8, 8, '1780600752_7_1_OIP (1).webp', '2026-06-04 19:19:12'),
(9, 8, '1780600752_7_2_OIP.webp', '2026-06-04 19:19:12'),
(10, 9, '1780601432_9_0_download (1).webp', '2026-06-04 19:30:32'),
(11, 9, '1780601432_9_1_download (2).webp', '2026-06-04 19:30:32'),
(12, 9, '1780601432_9_2_download.jpg', '2026-06-04 19:30:32'),
(13, 10, '1780602375_7_0_download (1).webp', '2026-06-04 19:46:14'),
(14, 10, '1780602375_7_1_download (2).webp', '2026-06-04 19:46:14'),
(15, 10, '1780602375_7_2_download.jpg', '2026-06-04 19:46:14'),
(16, 11, '1780602543_8_0_OIP (1).webp', '2026-06-04 19:49:02'),
(17, 11, '1780602543_8_1_OIP (2).webp', '2026-06-04 19:49:02'),
(18, 11, '1780602543_8_2_OIP.webp', '2026-06-04 19:49:02'),
(19, 12, '1780662341_9_0_20260503_230044_mfnr.jpg', '2026-06-05 12:25:41'),
(20, 12, '1780662341_9_1_20260503_230155_mfnr.jpg', '2026-06-05 12:25:41'),
(21, 12, '1780662341_9_2_20260503_230200_mfnr.jpg', '2026-06-05 12:25:41'),
(22, 13, '1780665907_9_0_20260503_230044_mfnr.jpg', '2026-06-05 13:25:07'),
(23, 13, '1780665907_9_1_20260503_230155_mfnr.jpg', '2026-06-05 13:25:07'),
(24, 13, '1780665907_9_2_20260503_230200_mfnr.jpg', '2026-06-05 13:25:07'),
(25, 13, '1780665907_9_3_20260503_230202_mfnr.jpg', '2026-06-05 13:25:07'),
(28, 14, '1780842776_8_2_nike2 - Copy.jpg', '2026-06-07 14:32:56'),
(29, 15, '1780842967_8_0_gloves.jpg', '2026-06-07 14:36:07'),
(30, 15, '1780842967_8_1_gloves2.jpg', '2026-06-07 14:36:07'),
(31, 15, '1780842967_8_2_gloves3.jpg', '2026-06-07 14:36:07'),
(32, 16, '1780843619_7_0_phone.jpg', '2026-06-07 14:46:58'),
(33, 16, '1780843619_7_1_phone2.jpg', '2026-06-07 14:46:58'),
(34, 16, '1780843619_7_2_phone3.jpg', '2026-06-07 14:46:58'),
(35, 17, '1781183135_6_0_g1.jpg', '2026-06-11 13:05:36'),
(36, 17, '1781183135_6_1_g2.jpg', '2026-06-11 13:05:36'),
(37, 17, '1781183135_6_2_g3.jpg', '2026-06-11 13:05:36'),
(38, 18, '1781871370_8_0_OIP1.webp', '2026-06-19 12:16:11'),
(39, 18, '1781871370_8_1_OIP2.webp', '2026-06-19 12:16:11'),
(40, 18, '1781871370_8_2_OIP.webp', '2026-06-19 12:16:11'),
(41, 19, '1781877942_5_0_i1.jpg', '2026-06-19 14:05:42'),
(42, 19, '1781877942_5_1_i2.jpg', '2026-06-19 14:05:42'),
(43, 19, '1781877942_5_2_i3.jpg', '2026-06-19 14:05:42'),
(44, 20, '1781878220_5_0_i1.jpg', '2026-06-19 14:10:20'),
(45, 20, '1781878220_5_1_i2.jpg', '2026-06-19 14:10:20'),
(46, 20, '1781878220_5_2_i3.jpg', '2026-06-19 14:10:20'),
(47, 21, '1782138657_13_0_i3.jpg', '2026-06-22 14:30:57'),
(48, 21, '1782138657_13_1_i2.jpg', '2026-06-22 14:30:57'),
(49, 21, '1782138657_13_2_i1.jpg', '2026-06-22 14:30:57');

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `review_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `review` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `order_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_reviews`
--

INSERT INTO `product_reviews` (`review_id`, `product_id`, `user_id`, `rating`, `review`, `created_at`, `order_id`) VALUES
(4, 15, 11, 5, 'They are original', '2026-06-07 19:12:06', 6),
(5, 15, 6, 5, 'the product is original and high quality', '2026-06-11 17:05:41', 8),
(6, 18, 5, 5, 'I love the Nike Tech tracksuite', '2026-06-19 19:12:40', 12),
(7, 19, 8, 5, 'It\'s the actual iPhone 17 Pro Max, bro...can\'t believe it.', '2026-06-19 19:35:03', 15),
(8, 19, 13, 1, 'Th product took longer than expected to arrive', '2026-06-22 14:17:47', 16);

-- --------------------------------------------------------

--
-- Table structure for table `recently_viewed`
--

CREATE TABLE `recently_viewed` (
  `view_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recently_viewed`
--

INSERT INTO `recently_viewed` (`view_id`, `user_id`, `product_id`, `viewed_at`) VALUES
(3, 4, 5, '2026-05-24 16:32:42'),
(14, 7, 8, '2026-06-04 19:23:18'),
(17, 9, 8, '2026-06-04 19:25:21'),
(18, 9, 9, '2026-06-04 19:30:49'),
(29, 9, 11, '2026-06-07 11:49:25'),
(30, 9, 10, '2026-06-07 11:49:32'),
(31, 10, 11, '2026-06-07 12:01:50'),
(33, 10, 10, '2026-06-07 15:29:43'),
(36, 11, 15, '2026-06-07 15:53:34'),
(37, 11, 16, '2026-06-07 16:36:30'),
(40, 6, 16, '2026-06-11 17:09:32'),
(52, 5, 16, '2026-06-19 12:30:45'),
(54, 8, 15, '2026-06-19 13:26:06'),
(58, 5, 14, '2026-06-19 14:52:58'),
(59, 8, 18, '2026-06-19 19:15:35'),
(61, 5, 18, '2026-06-19 21:16:56'),
(65, 8, 19, '2026-06-20 14:10:09'),
(66, 8, 17, '2026-06-21 16:03:28'),
(68, 13, 19, '2026-06-22 14:07:20'),
(72, 13, 15, '2026-06-22 14:13:35');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `report_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reported_user` varchar(255) DEFAULT NULL,
  `report_reason` varchar(255) DEFAULT NULL,
  `report_details` text DEFAULT NULL,
  `report_status` enum('pending','reviewed','resolved') DEFAULT 'pending',
  `admin_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `seller_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`report_id`, `user_id`, `reported_user`, `report_reason`, `report_details`, `report_status`, `admin_response`, `created_at`, `order_id`, `product_id`, `seller_id`) VALUES
(1, 11, 'Drip Too Hard', 'Other', 'The order took too much time to arrive.', 'resolved', 'Report resolved/dismissed by StreetMarket admin.', '2026-06-07 18:26:19', 6, 15, 8),
(2, 5, 'Drip Too Hard', 'Fake Product', 'This product sizes are actually small even when it says the size is medium', 'pending', NULL, '2026-06-19 19:16:39', 12, NULL, 8),
(3, 8, 'African Food', 'Wrong Product', 'He made a mistake with the colors', 'resolved', 'The buyr Mr Moyela has been granted with the correct product', '2026-06-19 19:35:43', 15, NULL, 5),
(4, 13, 'African Food', 'Other', 'The order took longer than expected', 'pending', NULL, '2026-06-22 14:18:14', 16, NULL, 5);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `permissions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `permissions`) VALUES
(1, 'Super Admin', 'Full Access'),
(2, 'Moderator', 'Manage Products and Orders');

-- --------------------------------------------------------

--
-- Table structure for table `sandbox_accounts`
--

CREATE TABLE `sandbox_accounts` (
  `account_id` int(11) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_type` varchar(50) NOT NULL,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `sandbox_accounts`
--

INSERT INTO `sandbox_accounts` (`account_id`, `account_name`, `account_type`, `balance`) VALUES
(1, 'Buyer Sandbox Card', 'buyer', '5000.00'),
(2, 'StreetMarket Escrow', 'platform', '0.00'),
(3, 'Seller Payout Account', 'seller', '800.00');

-- --------------------------------------------------------

--
-- Table structure for table `sandbox_transactions`
--

CREATE TABLE `sandbox_transactions` (
  `transaction_id` int(11) NOT NULL,
  `from_account` varchar(100) NOT NULL,
  `to_account` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_note` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sandbox_wallets`
--

CREATE TABLE `sandbox_wallets` (
  `wallet_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT 0.00
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `full_name` varchar(200) NOT NULL,
  `sa_id` varchar(20) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `province` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Active','Suspended','Deleted') DEFAULT 'Active',
  `profile_image` varchar(255) DEFAULT NULL,
  `seller_verification_status` enum('Not Submitted','Pending','Verified','Rejected') DEFAULT 'Not Submitted',
  `id_document` varchar(255) DEFAULT NULL,
  `proof_of_residence` varchar(255) DEFAULT NULL,
  `seller_verified_at` datetime DEFAULT NULL,
  `last_seen` datetime DEFAULT NULL,
  `business_name` varchar(150) DEFAULT NULL,
  `business_type` varchar(100) DEFAULT NULL,
  `business_location` varchar(150) DEFAULT NULL,
  `business_profile` text DEFAULT NULL,
  `business_bank_name` varchar(100) DEFAULT NULL,
  `business_account_holder` varchar(150) DEFAULT NULL,
  `business_account_number` varchar(30) DEFAULT NULL,
  `business_branch_code` varchar(20) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `business_cvv` varchar(10) DEFAULT NULL,
  `last_activity` datetime DEFAULT NULL,
  `business_profile_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `full_name`, `sa_id`, `email`, `phone`, `province`, `address`, `password`, `created_at`, `status`, `profile_image`, `seller_verification_status`, `id_document`, `proof_of_residence`, `seller_verified_at`, `last_seen`, `business_name`, `business_type`, `business_location`, `business_profile`, `business_bank_name`, `business_account_holder`, `business_account_number`, `business_branch_code`, `gender`, `business_cvv`, `last_activity`, `business_profile_image`) VALUES
(1, '', '', 'Kwano Lucky', '9801015009087', 'kwan@gmail.com', '723331721', 'Eastern Cape', 'Johannesburg, Gauteng', '$2y$10$9yycpCNptWdFGDZ2kb68aOXkODGBH7jDoprnN1xmI0.svAnbcm17O', '2026-05-19 18:40:31', 'Deleted', NULL, 'Not Submitted', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, '', '', 'John Graig', '9201115009086', 'john@gmail.com', '794441729', 'Limpopo', 'Johannesburg, Gauteng', '$2y$10$7dxAYkkgf.CuCia4YKPh4OGiHKyuvofH5xhcfqMAUbdJ3KmQ/k9ye', '2026-05-19 18:59:15', 'Deleted', NULL, 'Not Submitted', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, '', '', 'Lucky Mahlangu', '0602275000087', 'lucky@gmail.com', '735358712', 'Gauteng', '26 gumgrass street, Riverside view', '$2y$10$2zQBKwHWBUB3eRBnk4NSP.HgB91PErRUHSAzx5jLXJUBd6b1BCPnu', '2026-05-22 14:25:25', 'Active', NULL, 'Not Submitted', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, '', '', 'Azizipho Nkunya', '7506025006086', 'aziz@gmail.com', '793123450', 'Gauteng', '', '$2y$10$5u7w41VOv3rFpPjUs2l7tO88gT.wxOipIrVTG57jQywllTIFGBHZy', '2026-05-24 13:59:37', 'Active', NULL, 'Not Submitted', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, '', '', 'Kops Lerutlo', '1507165006086', 'kops@gmail.com', '784481432', 'Limpopo', '25 Alsation Rd, Glen Austin', '$2y$10$f7g.nTHjpqc6pitLBJAVLe2O96aQYCzcF0Mhd.XtKR6KJfqk8cTjy', '2026-05-26 13:57:26', 'Active', NULL, 'Verified', 'id_5_1781876744.jpg', 'residence_5_1781876744.jpg', '2026-06-19 07:45:01', NULL, 'African Food', 'Food Seller', '257 Ga-Mahlokwane, Burgersfort, Dreikop 1129', NULL, 'FNB', 'K Lerutlo', '42424242424242424', '1158', NULL, '123', '2026-06-22 07:15:24', 'business_profile_5_1781876744.jpg'),
(6, '', '', 'Kgothalol Makzine', '1408205056086', 'kgothalol@gmail.com', '676666942', 'KwaZulu-Natal', '', '$2y$10$uhr0aSGeQcFgujl4xWvbN.khGG9hxGao1gTCmPEzBOZ00gaboRAje', '2026-05-26 17:46:01', 'Active', NULL, 'Rejected', 'id_6_1781182007.pdf', NULL, NULL, NULL, 'Home tool Market', 'Home Goods Seller', '257 Ga-Mahlokwane, Burgersfort, Dreikop 1129', 'It is a blah balh blah', 'Capitec', 'K Makzine', '42424242424242424', '1158', NULL, NULL, NULL, NULL),
(7, 'Elphas', 'Moroke', 'Elphas Moroke', '9806025006086', 'elphas@gmail.com', '676666942', 'Limpopo', 'STAND NO 255\r\nGA MAHLOKWANE', '$2y$10$4LxChnXnyVIkU9fyuDgssOOO.lZ3qLdJzLVhpQyud9CEApw57PCBK', '2026-06-04 17:44:19', 'Active', '1780598360_20260503_230155_mfnr.jpg', 'Verified', 'seller_id_7_1780598438.pdf', NULL, '2026-06-04 11:53:46', '2026-06-05 05:29:12', 'Incredible Electronics', 'Electronics Seller', '257 Ga-Mahlokwane, Burgersfort, Dreikop 1129', 'Always dealing with incredible and latest trending electronics', 'FNB', 'E Magabane', '4242424242424242', '1158', NULL, NULL, NULL, NULL),
(8, '', '', 'Simon Moyela', '9805225056087', 'simon@gmail.com', '676666942', 'Limpopo', 'STAND NO 255\r\nGA MAHLOKWANE', '$2y$10$xpq.J0Rc70wXZPfShvdya..Kkp4m5bwuuXVG5x3hzDuQWSIsboIKG', '2026-06-04 18:46:59', 'Active', NULL, 'Verified', 'id_8_1781904104.jpg', 'residence_8_1781904104.jpg', '2026-06-19 14:22:57', '2026-06-05 05:29:12', 'Drip Too Hard', 'Clothing Seller', 'STAND NO 255 GA MAHLOKWANE, Burgersfort, Dreikop', 'Drip Too Hard store is a store filled with drip clothing. You can go wrong.', 'FNB', 'S Moyela', '4242424242424242', '1158', NULL, '123', '2026-06-21 09:30:07', 'business_profile_8_1781904104.jpg'),
(9, '', '', 'Kwano Lucky', '0602275056086', 'kwanolucky@gmail.com', '735358712', 'Limpopo', 'Redhouse Midrand\r\n27 Alsation Rd', '$2y$10$jeXFWkU/JNXc0TQrwa6FeuP5I/H2WmmWErMYXlQ75xVcaYLVmYCmW', '2026-06-04 19:11:47', 'Active', NULL, 'Not Submitted', NULL, NULL, NULL, '2026-06-05 05:24:50', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(10, '', '', 'Zizi Mbombela', '0602275066086', 'zizi@gmail.com', '735358712', 'Mpumalanga', '245 Loksion bieskop', '$2y$10$penrwJL.0b2n5V0ISmlYke.W3qXBpAKzT3yX2q7xj1W6MjPpkjScS', '2026-06-07 11:14:19', 'Active', NULL, 'Not Submitted', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, '', '', 'Precious Nkabi', '8506015056086', 'precious@gmail.com', '785169510', 'Western Cape', '25 Gloria hills, Parkway Gardens', '$2y$10$6Y9bJC8KlkAWDsyxGHU0yOB3d8BwniEbVrI9hR.j8rl06ekYhX/o2', '2026-06-07 15:44:19', 'Active', NULL, 'Rejected', 'id_11_1780847345.pdf', NULL, NULL, NULL, 'P\'s Fish and Chips', 'Food Seller', '25 Gloria Hills, Western Cape', 'We sell fish, chips,russians, beverages, shakes, boiled eggs, atchar, and bread.', 'FNB', 'P Nkabi', '4242424242424242', '1158', 'Male', NULL, NULL, NULL),
(12, '', '', 'JOHN CENA', '0001015000902', 'johnc@gmail.com', '824481233', 'Limpopo', 'Eduvos Midrand, 32 Alsation RD', '$2y$10$ISOjdi3t6F7At2OXdIw0aO3ExcW/RwDJsUGvvkXupIl4OnC6rFcTq', '2026-06-20 13:33:13', 'Active', NULL, 'Verified', 'id_12_1781962901.jpg', 'residence_12_1781962901.jpg', '2026-06-20 06:44:28', NULL, 'Lewis', 'Furniture Seller', '25 Glorial Hills Western cape', NULL, 'FNB', 'S Moyela', '4242424242424242', '1158', 'Male', '123', NULL, 'business_profile_12_1781962901.png'),
(13, '', '', 'Tshepang Lebakeng', '0312030203086', 'tshepang@gmail.com', '786665432', 'Limpopo', 'STAND NO 255\r\nGA MAHLOKWANE', '$2y$10$xWw3.zycETqMg8Qrb3h.P.MM8Ms8pNdn2na5/d2wMLYq2CQGvnK/G', '2026-06-22 14:03:33', 'Active', '1782138440_20260503_230155_mfnr.jpg', 'Verified', 'id_13_1782138053.jpg', 'residence_13_1782138053.jpg', '2026-06-22 07:27:54', NULL, 'Lewis', 'Electronics Seller', '25 Glorial Hills Western cape', NULL, 'FNB', 'S Moyela', '5555555555554444', '1158', 'Female', '123', '2026-06-22 07:11:14', 'business_profile_13_1782138053.png');

-- --------------------------------------------------------

--
-- Table structure for table `user_typing`
--

CREATE TABLE `user_typing` (
  `typing_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT 0,
  `last_typing` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `user_typing`
--

INSERT INTO `user_typing` (`typing_id`, `sender_id`, `receiver_id`, `product_id`, `last_typing`) VALUES
(1, 5, 7, 0, '2026-06-19 04:44:59'),
(3, 5, -3, 0, '2026-06-19 04:53:21'),
(9, 5, 8, 0, '2026-06-19 04:55:36'),
(15, 8, 5, 0, '2026-06-19 04:55:52'),
(44, 8, -3, 0, '2026-06-19 14:12:58'),
(41, 5, 8, 14, '2026-06-19 07:52:21'),
(34, 8, 5, 14, '2026-06-19 07:52:05'),
(50, 8, 5, 19, '2026-06-20 07:08:18'),
(46, 5, 8, 18, '2026-06-19 14:17:03'),
(51, 5, 8, 19, '2026-06-20 07:08:33'),
(63, 13, 5, 19, '2026-06-22 07:08:37'),
(64, 13, 8, 15, '2026-06-22 07:11:10');

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `wishlist_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wishlist`
--

INSERT INTO `wishlist` (`wishlist_id`, `user_id`, `product_id`, `created_at`) VALUES
(1, 7, 8, '2026-06-04 19:23:03'),
(2, 11, 15, '2026-06-07 15:53:34'),
(3, 6, 16, '2026-06-11 17:09:32'),
(4, 5, 16, '2026-06-15 18:15:43');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`admin_notification_id`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `disputes`
--
ALTER TABLE `disputes`
  ADD PRIMARY KEY (`dispute_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `buyer_id` (`buyer_id`),
  ADD KEY `seller_id` (`seller_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `recently_viewed`
--
ALTER TABLE `recently_viewed`
  ADD PRIMARY KEY (`view_id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`report_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `sandbox_accounts`
--
ALTER TABLE `sandbox_accounts`
  ADD PRIMARY KEY (`account_id`),
  ADD UNIQUE KEY `account_name` (`account_name`);

--
-- Indexes for table `sandbox_transactions`
--
ALTER TABLE `sandbox_transactions`
  ADD PRIMARY KEY (`transaction_id`);

--
-- Indexes for table `sandbox_wallets`
--
ALTER TABLE `sandbox_wallets`
  ADD PRIMARY KEY (`wallet_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `sa_id` (`sa_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_typing`
--
ALTER TABLE `user_typing`
  ADD PRIMARY KEY (`typing_id`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`wishlist_id`),
  ADD UNIQUE KEY `unique_wishlist_item` (`user_id`,`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `admin_notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `category`
--
ALTER TABLE `category`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `disputes`
--
ALTER TABLE `disputes`
  MODIFY `dispute_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=137;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `product`
--
ALTER TABLE `product`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `recently_viewed`
--
ALTER TABLE `recently_viewed`
  MODIFY `view_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sandbox_accounts`
--
ALTER TABLE `sandbox_accounts`
  MODIFY `account_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sandbox_transactions`
--
ALTER TABLE `sandbox_transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sandbox_wallets`
--
ALTER TABLE `sandbox_wallets`
  MODIFY `wallet_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `user_typing`
--
ALTER TABLE `user_typing`
  MODIFY `typing_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `wishlist_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`seller_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `product`
--
ALTER TABLE `product`
  ADD CONSTRAINT `product_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `product_reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
