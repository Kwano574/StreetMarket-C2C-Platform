-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql310.infinityfree.com
-- Generation Time: Jun 12, 2026 at 08:33 AM
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
(1, 2, 'Report Resolved', 'Report #1 has been resolved/dismissed.', 'admin-reports.php', 'No', '2026-06-07 11:35:34');

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
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`admin_id`, `full_name`, `email`, `password`, `role`, `created_at`, `work_phone`, `department`, `status`, `last_login`, `failed_attempts`, `locked_until`, `updated_at`) VALUES
(2, 'Main Super Admin', 'kwanomag@streetmarket.com', '$2y$10$xTKppl38qN9rloN5EZjVEeENFW4Twd2GcqGZWeno2DLIjIbKNrVfS', 'super_admin', '2026-05-26 20:17:06', NULL, NULL, 'Active', '2026-06-11 11:04:51', 0, NULL, NULL),
(3, 'Peter Mokaba', 'peter@streetmarket.com', '$2y$10$LePAHd6oYbvzrBfaJ.XLeOPNc16HZynl8vB6O2t7IwdTj881QfZIO', 'support_admin', '2026-06-05 12:03:30', NULL, NULL, 'Active', '2026-06-11 10:58:27', 0, NULL, '2026-06-11 11:12:50');

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `quantity` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`cart_id`, `user_id`, `product_id`, `added_at`, `quantity`) VALUES
(21, 6, 15, '2026-06-11 13:51:52', 1),
(22, 6, 14, '2026-06-11 13:51:57', 1);

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
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `message`, `sent_at`, `product_id`, `is_read`, `delivered_at`, `read_at`) VALUES
(1, 7, 7, 'hey do you have nike tech fleece medium full outfit', '2026-06-04 19:20:06', 8, 'No', NULL, NULL),
(2, 7, 7, 'hey do you have nike tech fleece medium full outfit', '2026-06-04 19:20:09', 8, 'No', NULL, NULL),
(3, 7, 7, 'yes', '2026-06-04 19:20:28', 0, 'No', NULL, NULL),
(4, 9, 7, 'hi', '2026-06-04 19:24:45', 8, 'Yes', NULL, '2026-06-05 05:09:45'),
(7, 9, 7, 'hi', '2026-06-04 19:38:42', 0, 'Yes', NULL, '2026-06-05 05:09:45'),
(9, 7, 9, 'hey', '2026-06-04 19:40:11', 0, 'Yes', NULL, '2026-06-05 05:12:40'),
(10, 7, 9, 'ho', '2026-06-04 19:41:58', 0, 'Yes', NULL, '2026-06-05 05:12:40'),
(11, 7, 9, 'ho', '2026-06-04 19:42:44', 0, 'Yes', NULL, '2026-06-05 05:12:40'),
(12, 7, 9, 'ho', '2026-06-04 19:43:06', 0, 'Yes', NULL, '2026-06-05 05:12:40'),
(13, 7, 9, 'ho', '2026-06-05 12:10:28', 0, 'Yes', '2026-06-05 05:10:28', '2026-06-05 05:12:40'),
(16, 9, 7, 'hi', '2026-06-05 12:20:19', 0, 'Yes', '2026-06-05 05:20:19', '2026-06-05 05:20:32'),
(17, 8, 9, 'hi', '2026-06-05 12:21:16', 0, 'Yes', '2026-06-05 05:21:16', NULL),
(19, 7, 8, 'huii', '2026-06-05 12:32:58', 0, 'Yes', NULL, NULL),
(20, 8, 7, 'dvd', '2026-06-05 12:34:33', 0, 'No', NULL, NULL),
(21, 8, 7, 'dvfb', '2026-06-05 12:34:39', 0, 'No', NULL, NULL),
(22, 8, 7, 'dvfbfb', '2026-06-05 12:34:42', 0, 'No', NULL, NULL),
(23, 8, 7, 'dvfbfb', '2026-06-05 12:34:44', 0, 'No', NULL, NULL),
(24, 8, 7, 'dvfbfb', '2026-06-05 12:34:45', 0, 'No', NULL, NULL),
(25, 8, 7, 'dvfbfb', '2026-06-05 12:34:47', 0, 'No', NULL, NULL),
(26, 8, 7, 'dvfbfb', '2026-06-05 12:34:49', 0, 'No', NULL, NULL),
(27, 8, 7, 'dvfbfb', '2026-06-05 12:34:50', 0, 'No', NULL, NULL),
(28, 8, 7, 'uku', '2026-06-05 12:46:07', 0, 'No', NULL, NULL),
(29, 9, 7, 'nfn', '2026-06-05 12:46:30', 0, 'No', NULL, NULL),
(30, 9, 8, 'gng', '2026-06-05 12:46:38', 0, 'Yes', NULL, NULL),
(31, 8, 9, 'hmm', '2026-06-05 12:47:23', 0, 'Yes', NULL, NULL),
(32, 8, 9, 'ngng', '2026-06-05 12:48:09', 0, 'Yes', NULL, NULL),
(33, 8, 9, 'ngng', '2026-06-05 12:48:28', 0, 'Yes', NULL, NULL),
(34, 9, 8, 'sfefe', '2026-06-05 12:59:19', 0, 'Yes', NULL, NULL),
(35, 9, 8, 'cec', '2026-06-05 12:59:46', 0, 'Yes', NULL, NULL),
(36, 9, 7, 'xcvcdv', '2026-06-05 13:13:02', 0, 'No', NULL, NULL),
(37, 9, 7, 'xcvcdv', '2026-06-05 13:13:32', 0, 'No', NULL, NULL),
(38, 9, 7, 'dd', '2026-06-05 13:13:39', 0, 'No', NULL, NULL),
(39, 8, 9, 'efe', '2026-06-05 13:14:53', 0, 'Yes', NULL, '2026-06-05 06:15:03'),
(89, 9, 8, 'xs', '2026-06-05 13:23:39', 0, 'Yes', NULL, '2026-06-05 06:39:38'),
(93, 11, 8, 'Hi', '2026-06-07 15:53:18', 15, 'No', NULL, NULL);

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
(4, 8, 'Precious Nkabi sent you a new message.', 'messages.php?user=11&product=15', 'No', '2026-06-07 15:53:18', ''),
(5, 8, 'You have a new order. Amount: R1,524.97.', 'manage-deliveries.php', 'No', '2026-06-07 15:56:33', ''),
(6, 7, 'You have a new order. Amount: R7,524.99.', 'manage-deliveries.php', 'No', '2026-06-07 15:56:33', ''),
(8, 7, 'The buyer confirmed completion for Samsung A37 5G 256G 8GB(Color May Vary).', 'order-tracking.php?id=7', 'Yes', '2026-06-07 15:59:23', ''),
(10, 8, 'The buyer confirmed completion for Nike Lightweight Running Gloves.', 'order-tracking.php?id=6', 'No', '2026-06-07 16:35:05', ''),
(12, 8, 'A buyer submitted a report related to Order #6.', 'notifications.php', 'No', '2026-06-07 18:26:19', ''),
(14, 8, 'A report related to Order #6 has been resolved by StreetMarket.', 'notifications.php', 'No', '2026-06-07 18:35:34', ''),
(15, 8, 'A buyer reviewed your product: Nike Lightweight Running Gloves.', 'product-details.php?id=15', 'No', '2026-06-07 19:12:06', ''),
(16, 6, 'Your seller account has been verified. You can now upload and sell products on StreetMarket.', 'user-profile.php', 'No', '2026-06-11 12:45:27', ''),
(17, 6, 'Your seller account has been verified. You can now upload and sell products on StreetMarket.', 'user-profile.php', 'No', '2026-06-11 12:50:49', ''),
(18, 8, 'You have a new order. Amount: R3,024.98.', 'manage-deliveries.php', 'No', '2026-06-11 13:51:18', ''),
(19, 7, 'You have a new order. Amount: R7,524.99.', 'manage-deliveries.php', 'No', '2026-06-11 13:51:18', ''),
(20, 6, 'Your order for Nike Lightweight Running Gloves is now Delivered.', 'order-tracking.php?id=8', 'No', '2026-06-11 14:52:15', ''),
(21, 6, 'Your order for Nike Lightweight Running Gloves is now Processing.', 'order-tracking.php?id=8', 'No', '2026-06-11 14:54:35', ''),
(22, 6, 'Your order group #8 is now Delivered.', 'order-tracking.php?id=8', 'No', '2026-06-11 15:05:26', ''),
(23, 8, 'The buyer confirmed completion for order group #8.', 'manage-deliveries.php', 'No', '2026-06-11 15:05:50', ''),
(24, 8, 'A buyer reviewed your product: Nike Lightweight Running Gloves.', 'product-details.php?id=15', 'No', '2026-06-11 17:05:41', ''),
(25, 11, 'Your seller verification was rejected. Please review your business details and ID document, then submit again.', 'seller-verification.php', 'No', '2026-06-11 18:31:32', '');

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
  `estimated_time` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `buyer_id`, `seller_id`, `product_id`, `delivery_address`, `total_amount`, `payment_method`, `payment_status`, `delivery_status`, `order_date`, `status`, `buyer_confirmed`, `cancelled_by`, `quantity`, `delivery_method`, `estimated_time`) VALUES
(1, 9, 8, 11, 'Redhouse Midrand\r\n27 Alsation Rd, Limpopo', '6024.99', 'card', 'refunded', 'processing', '2026-06-07 13:02:16', 'cancelled', 'No', 'buyer', 1, 'delivery', 'Estimated delivery: 45 minutes to 1 hour 30 minutes'),
(2, 9, 7, 10, 'Redhouse Midrand\r\n27 Alsation Rd, Limpopo', '24024.98', 'card', 'refunded', 'processing', '2026-06-07 13:02:16', 'cancelled', 'No', 'buyer', 2, 'delivery', 'Estimated delivery: 45 minutes to 1 hour 30 minutes'),
(3, 9, 8, 14, 'Redhouse Midrand\r\n27 Alsation Rd, Limpopo', '2524.99', 'card', 'paid', 'delivered', '2026-06-07 15:19:29', 'completed', 'Yes', NULL, 1, 'delivery', 'Estimated delivery: 45 minutes to 1 hour 30 minutes'),
(4, 9, 7, 16, 'Redhouse Midrand\r\n27 Alsation Rd, Limpopo', '7524.99', 'card', 'paid', 'delivered', '2026-06-07 15:19:29', 'pending', 'No', NULL, 1, 'delivery', 'Estimated delivery: 45 minutes to 1 hour 30 minutes'),
(5, 10, 7, 10, '245 Loksion bieskop, Mpumalanga', '12024.99', 'card', 'paid', 'processing', '2026-06-07 15:31:00', 'pending', 'No', NULL, 1, 'delivery', 'Estimated delivery: 45 minutes to 1 hour 30 minutes'),
(6, 11, 8, 15, '25 Gloria hills, Parkway Gardens, Western Cape', '1524.97', 'card', 'paid', 'delivered', '2026-06-07 15:56:33', 'completed', 'Yes', NULL, 3, 'delivery', 'Estimated delivery: 45 minutes to 1 hour 30 minutes'),
(7, 11, 7, 16, '25 Gloria hills, Parkway Gardens, Western Cape', '7524.99', 'card', 'paid', 'delivered', '2026-06-07 15:56:33', 'completed', 'Yes', NULL, 1, 'delivery', 'Estimated delivery: 45 minutes to 1 hour 30 minutes'),
(8, 6, 8, 15, ', KwaZulu-Natal', '524.99', 'cash', 'pending', 'delivered', '2026-06-11 13:51:18', 'completed', 'Yes', NULL, 1, 'delivery', 'Estimated delivery: 45 minutes to 1 hour 30 minutes'),
(9, 6, 8, 14, ', KwaZulu-Natal', '2499.99', 'cash', 'pending', 'delivered', '2026-06-11 13:51:18', 'completed', 'Yes', NULL, 1, 'delivery', 'Estimated delivery: 45 minutes to 1 hour 30 minutes'),
(10, 6, 7, 16, ', KwaZulu-Natal', '7524.99', 'cash', 'pending', 'processing', '2026-06-11 13:51:18', 'pending', 'No', NULL, 1, 'delivery', 'Estimated delivery: 45 minutes to 1 hour 30 minutes');

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
  `moderation_status` enum('pending','approved','rejected') DEFAULT 'approved'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product`
--

INSERT INTO `product` (`product_id`, `product_name`, `description`, `price`, `quantity`, `image`, `location`, `product_condition`, `delivery_option`, `status`, `user_id`, `created_at`, `delivery_available`, `category`, `moderation_status`) VALUES
(10, 'Samsung Neo QLED smart tv', 'it is the latest samsung smart tv....', '11999.99', 0, '1780602375_7_0_download (1).webp', 'Limpopo', 'New', 'Delivery Available', 'sold', 7, '2026-06-04 19:46:14', 'Yes', 'Electronics', 'approved'),
(11, 'Samsung Neo QLED smart tv', 'it is the new Nike tech fleece...', '5999.99', 2, '1780602543_8_0_OIP (1).webp', 'Limpopo', 'New', 'Delivery Available', 'available', 8, '2026-06-04 19:49:02', 'Yes', 'Fashion', 'approved'),
(14, 'Nike Air Force 1 Shoes', 'We have all sizes and colors.', '2499.99', 3, '1780842776_8_0_nike - Copy.jpg', 'Limpopo', 'New', 'Delivery Available', 'available', 8, '2026-06-07 14:32:56', 'Yes', 'Fashion', 'approved'),
(15, 'Nike Lightweight Running Gloves', 'We have all sizes and they are unisex', '499.99', 3, '1780842967_8_0_gloves.jpg', 'Limpopo', 'New', 'Delivery Available', 'available', 8, '2026-06-07 14:36:07', 'Yes', 'Fashion', 'approved'),
(16, 'Samsung A37 5G 256G 8GB(Color May Vary)', 'We have the green, black, and white colors of the Samsung A37 5G 286GB total storage and 8GB memory', '7499.99', 17, '1780843619_7_0_phone.jpg', 'Gauteng', 'New', 'Delivery Available', 'available', 7, '2026-06-07 14:46:58', 'Yes', 'Electronics', 'approved'),
(17, '10 Piece Green Garden Tool set with case', 'It is a case with 10 pieces of gardening tools that are commonly used', '399.99', 10, '1781183135_6_0_g1.jpg', 'Gauteng', 'New', 'Delivery Available', 'available', 6, '2026-06-11 13:05:36', 'Yes', 'Home', 'pending');

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
(37, 17, '1781183135_6_2_g3.jpg', '2026-06-11 13:05:36');

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
(5, 15, 6, 5, 'the product is original and high quality', '2026-06-11 17:05:41', 8);

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
(40, 6, 16, '2026-06-11 17:09:32');

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
(1, 11, 'Drip Too Hard', 'Other', 'The order took too much time to arrive.', 'resolved', 'Report resolved/dismissed by StreetMarket admin.', '2026-06-07 18:26:19', 6, 15, 8);

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
  `gender` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `full_name`, `sa_id`, `email`, `phone`, `province`, `address`, `password`, `created_at`, `status`, `profile_image`, `seller_verification_status`, `id_document`, `seller_verified_at`, `last_seen`, `business_name`, `business_type`, `business_location`, `business_profile`, `business_bank_name`, `business_account_holder`, `business_account_number`, `business_branch_code`, `gender`) VALUES
(1, '', '', 'Kwano Lucky', '9801015009087', 'kwan@gmail.com', '723331721', 'Eastern Cape', 'Johannesburg, Gauteng', '$2y$10$9yycpCNptWdFGDZ2kb68aOXkODGBH7jDoprnN1xmI0.svAnbcm17O', '2026-05-19 18:40:31', 'Deleted', NULL, 'Not Submitted', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, '', '', 'John Graig', '9201115009086', 'john@gmail.com', '794441729', 'Limpopo', 'Johannesburg, Gauteng', '$2y$10$7dxAYkkgf.CuCia4YKPh4OGiHKyuvofH5xhcfqMAUbdJ3KmQ/k9ye', '2026-05-19 18:59:15', 'Deleted', NULL, 'Not Submitted', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, '', '', 'Lucky Mahlangu', '0602275000087', 'lucky@gmail.com', '735358712', 'Gauteng', '26 gumgrass street, Riverside view', '$2y$10$2zQBKwHWBUB3eRBnk4NSP.HgB91PErRUHSAzx5jLXJUBd6b1BCPnu', '2026-05-22 14:25:25', 'Active', NULL, 'Not Submitted', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, '', '', 'Azizipho Nkunya', '7506025006086', 'aziz@gmail.com', '793123450', 'Gauteng', '', '$2y$10$5u7w41VOv3rFpPjUs2l7tO88gT.wxOipIrVTG57jQywllTIFGBHZy', '2026-05-24 13:59:37', 'Active', NULL, 'Not Submitted', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, '', '', 'Kops Lerutlo', '1507165006086', 'kops@gmail.com', '784481432', 'Limpopo', '', '$2y$10$f7g.nTHjpqc6pitLBJAVLe2O96aQYCzcF0Mhd.XtKR6KJfqk8cTjy', '2026-05-26 13:57:26', 'Active', NULL, 'Not Submitted', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, '', '', 'Kgothalol Makzine', '1408205056086', 'kgothalol@gmail.com', '676666942', 'KwaZulu-Natal', '', '$2y$10$uhr0aSGeQcFgujl4xWvbN.khGG9hxGao1gTCmPEzBOZ00gaboRAje', '2026-05-26 17:46:01', 'Active', NULL, 'Verified', 'id_6_1781182007.pdf', '2026-06-11 05:50:49', NULL, 'Home tool Market', 'Home Goods Seller', '257 Ga-Mahlokwane, Burgersfort, Dreikop 1129', 'It is a blah balh blah', 'Capitec', 'K Makzine', '42424242424242424', '1158', NULL),
(7, 'Elphas', 'Moroke', 'Elphas Moroke', '9806025006086', 'elphas@gmail.com', '676666942', 'Limpopo', 'STAND NO 255\r\nGA MAHLOKWANE', '$2y$10$4LxChnXnyVIkU9fyuDgssOOO.lZ3qLdJzLVhpQyud9CEApw57PCBK', '2026-06-04 17:44:19', 'Active', '1780598360_20260503_230155_mfnr.jpg', 'Verified', 'seller_id_7_1780598438.pdf', '2026-06-04 11:53:46', '2026-06-05 05:29:12', 'Incredible Electronics', 'Electronics Seller', '257 Ga-Mahlokwane, Burgersfort, Dreikop 1129', 'Always dealing with incredible and latest trending electronics', 'FNB', 'E Magabane', '4242424242424242', '1158', NULL),
(8, '', '', 'Simon Moyela', '9805225056087', 'simon@gmail.com', '676666942', 'Limpopo', 'STAND NO 255\r\nGA MAHLOKWANE', '$2y$10$P2NjgHTh7qG7WH4X83iw4ufyGa8oDQcBI2wXYcca2JAl7GOTozl1K', '2026-06-04 18:46:59', 'Active', NULL, 'Verified', 'seller_id_8_1780599126.pdf', '2026-06-07 08:18:04', '2026-06-05 05:29:12', 'Drip Too Hard', 'Clothing Seller', 'STAND NO 255 GA MAHLOKWANE, Burgersfort, Dreikop', 'Drip Too Hard store is a store filled with drip clothing. You can go wrong.', 'FNB', 'S Moyela', '4242424242424242', '1158', NULL),
(9, '', '', 'Kwano Lucky', '0602275056086', 'kwanolucky@gmail.com', '735358712', 'Limpopo', 'Redhouse Midrand\r\n27 Alsation Rd', '$2y$10$jeXFWkU/JNXc0TQrwa6FeuP5I/H2WmmWErMYXlQ75xVcaYLVmYCmW', '2026-06-04 19:11:47', 'Active', NULL, 'Not Submitted', NULL, NULL, '2026-06-05 05:24:50', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(10, '', '', 'Zizi Mbombela', '0602275066086', 'zizi@gmail.com', '735358712', 'Mpumalanga', '245 Loksion bieskop', '$2y$10$penrwJL.0b2n5V0ISmlYke.W3qXBpAKzT3yX2q7xj1W6MjPpkjScS', '2026-06-07 11:14:19', 'Active', NULL, 'Not Submitted', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, '', '', 'Precious Nkabi', '8506015056086', 'precious@gmail.com', '785169510', 'Western Cape', '25 Gloria hills, Parkway Gardens', '$2y$10$6Y9bJC8KlkAWDsyxGHU0yOB3d8BwniEbVrI9hR.j8rl06ekYhX/o2', '2026-06-07 15:44:19', 'Active', NULL, 'Rejected', 'id_11_1780847345.pdf', NULL, NULL, 'P\'s Fish and Chips', 'Food Seller', '25 Gloria Hills, Western Cape', 'We sell fish, chips,russians, beverages, shakes, boiled eggs, atchar, and bread.', 'FNB', 'P Nkabi', '4242424242424242', '1158', 'Male');

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
(3, 6, 16, '2026-06-11 17:09:32');

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
  MODIFY `admin_notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

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
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `product`
--
ALTER TABLE `product`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `recently_viewed`
--
ALTER TABLE `recently_viewed`
  MODIFY `view_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sandbox_wallets`
--
ALTER TABLE `sandbox_wallets`
  MODIFY `wallet_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `wishlist_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
