-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 01, 2026 at 10:45 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `egipay`
--

-- --------------------------------------------------------

--
-- Table structure for table `api_keys`
--

CREATE TABLE `api_keys` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT 'Default Key',
  `key_type` enum('live','sandbox') NOT NULL DEFAULT 'sandbox',
  `client_key` varchar(100) NOT NULL,
  `server_key` varchar(100) NOT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `api_keys`
--

INSERT INTO `api_keys` (`id`, `user_id`, `name`, `key_type`, `client_key`, `server_key`, `last_used_at`, `is_active`, `created_at`) VALUES
(1, 2, 'Sandbox Key', 'sandbox', 'SB-Mid-client-xxxxxxxxxxxxxxxx', 'SB-Mid-server-xxxxxxxxxxxxxxxx', NULL, 1, '2026-02-22 15:31:27'),
(2, 2, 'Production Key', 'live', 'Mid-client-xxxxxxxxxxxxxxxx', 'Mid-server-xxxxxxxxxxxxxxxx', NULL, 1, '2026-02-22 15:31:27'),
(3, 3, 'Sandbox Key', 'sandbox', 'EGI-SB-997f326573d1cda44f39a5b2fcb428c2', 'EGI-SB-63225fd1d022e8703488c874b7dfe669', NULL, 1, '2026-02-22 22:31:52'),
(5, 7, 'Sandbox Key', 'sandbox', 'EGI-SB-e766a1c67bae68653baa10764d208b75', 'EGI-SB-f90b6771b544c81bdb8df1514891bdb3', NULL, 1, '2026-02-24 06:05:18'),
(6, 8, 'Sandbox Key', 'sandbox', 'EGI-SB-6e4f8e98e75fd8b87992c9d4ead8b6bf', 'EGI-SB-f9ccba6092b5918b50a05070cf593427', NULL, 1, '2026-02-24 06:16:56'),
(7, 9, 'Sandbox Key', 'sandbox', 'EGI-SB-a1325c48e8140eaa00d35de5878bea31', 'EGI-SB-47f6e5b4cd68dfc418016c2163bf555f', NULL, 1, '2026-02-24 23:33:09'),
(8, 10, 'Sandbox Key', 'sandbox', 'EGI-SB-0cf37d82745475c0135f8bde39a03211', 'EGI-SB-a0e699ab78e625479bfeb90ef00eeea0', NULL, 1, '2026-02-26 22:52:17'),
(9, 11, 'Sandbox Key', 'sandbox', 'EGI-SB-f1cb9ae6bf1882e59ed85a004e286db3', 'EGI-SB-d241d3b36d48fd7be9d78c00eea547cc', NULL, 1, '2026-02-26 22:57:54'),
(10, 12, 'Sandbox Key', 'sandbox', 'EGI-SB-2b63783cf88e9f8a80509ccaf906b5d5', 'EGI-SB-0f00963a929bc21cd1e3cc40dfc8e914', NULL, 1, '2026-02-26 23:30:27'),
(11, 13, 'Sandbox Key', 'sandbox', 'EGI-SB-d69bd3150d498c36dcb0fb59f36911da', 'EGI-SB-217260369117bc3a53a3223db72d8dfa', NULL, 1, '2026-02-28 13:22:51'),
(12, 14, 'Sandbox Key', 'sandbox', 'EGI-SB-6a11d48af1b541c47dc708c33ba4e994', 'EGI-SB-3f41692c06afa007d2f8dd3d36ebd0f2', NULL, 1, '2026-02-28 15:26:15'),
(13, 15, 'Sandbox Key', 'sandbox', 'EGI-SB-d4e429998dee075a9bbdeb00f1305222', 'EGI-SB-bd77e8c982ed8c92b92f9c1aa73698e5', NULL, 1, '2026-03-01 16:43:22');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(300) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 3, 'register', 'Akun baru: fattah@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 22:31:52'),
(2, 3, 'logout', 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 22:33:17'),
(3, 3, 'login', 'Login dari IP: ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 23:33:52'),
(4, 3, 'logout', 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 20:46:11'),
(5, NULL, 'register_initiated', 'Tagihan registrasi dibuat: ilham@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 21:23:48'),
(6, NULL, 'login_failed', 'Percobaan login gagal untuk: ilham@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 21:24:19'),
(7, NULL, 'login_failed', 'Percobaan login gagal untuk: admin@egipay.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:12:17'),
(8, NULL, 'login_failed', 'Percobaan login gagal untuk: admin@egipay.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:12:33'),
(9, NULL, 'login_failed', 'Percobaan login gagal untuk: admin@egipay.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:14:43'),
(10, 1, 'login', 'Login dari IP: ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:14:59'),
(11, 1, 'payment', 'Pembayaran TXN-E016B4A9 — 089664848974', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:17:28'),
(12, 1, 'logout', 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:19:14'),
(13, NULL, 'login_failed', 'Percobaan login gagal untuk: fattah@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:19:27'),
(14, NULL, 'login_failed', 'Percobaan login gagal untuk: fattah@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:19:49'),
(15, NULL, 'register_initiated', 'Tagihan registrasi dibuat: fattahal@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:23:56'),
(16, NULL, 'login_failed', 'Percobaan login gagal untuk: fattahal@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:25:28'),
(17, NULL, 'login_failed', 'Percobaan login gagal untuk: fattahal@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:38:09'),
(18, NULL, 'login_failed', 'Percobaan login gagal untuk: superadmin@egipay.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:46:00'),
(19, NULL, 'login_failed', 'Percobaan login gagal untuk: superadmin@egipay.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:46:14'),
(20, NULL, 'login_failed', 'Percobaan login gagal untuk: superadmin@egipay.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:49:16'),
(21, NULL, 'login_failed', 'Percobaan login gagal untuk: superadmin@egipay.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:49:33'),
(22, NULL, 'login_failed', 'Percobaan login gagal untuk: superadmin@egipay.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:50:18'),
(23, NULL, 'login_failed', 'Percobaan login gagal untuk: superadmin@egipay.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:50:47'),
(24, NULL, 'login_failed', 'Percobaan login gagal untuk: superadmin@egipay.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:50:59'),
(25, 4, 'login', 'Login dari IP: ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:55:22'),
(26, 4, 'admin_wdr_approve', 'WDR WDR-E5F6G7H8 disetujui', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:56:27'),
(27, 4, 'logout', 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 05:57:56'),
(28, NULL, 'register_initiated', 'Tagihan registrasi dibuat: ulum@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 06:05:12'),
(29, 7, 'register_paid', 'Pembayaran registrasi: ulum@gmail.com via BCA', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 06:05:18'),
(30, 7, 'logout', 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 06:15:51'),
(31, NULL, 'register_initiated', 'Tagihan registrasi dibuat: test@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 06:16:43'),
(32, 8, 'register_paid', 'Pembayaran registrasi: test@gmail.com via GOPAY', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 06:16:56'),
(33, 8, 'logout', 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 06:19:36'),
(34, NULL, 'login_failed', 'Percobaan login gagal untuk: superadmin@egipay.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 06:21:40'),
(35, 4, 'login', 'Login dari IP: ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 06:22:11'),
(36, 4, 'logout', 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 06:28:44'),
(37, NULL, 'login_failed', 'Percobaan login gagal untuk: test@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 06:29:04'),
(38, NULL, 'login_failed', 'Percobaan login gagal untuk: test@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 06:30:49'),
(39, NULL, 'login_failed', 'Percobaan login gagal untuk: superadmin@egipay', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 06:33:27'),
(40, 4, 'login', 'Login dari IP: ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 06:33:45'),
(41, 4, 'logout', 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 23:26:58'),
(42, NULL, 'register_initiated', 'Tagihan registrasi dibuat: testtest@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 23:32:32'),
(43, 9, 'register_paid', 'Pembayaran registrasi: testtest@gmail.com via QRIS', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 23:33:09'),
(44, NULL, 'login_failed', 'Percobaan login gagal untuk: superadmin@egipay.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 22:46:01'),
(45, 4, 'login', 'Login dari IP: ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 22:46:16'),
(46, 4, 'logout', 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 22:49:54'),
(47, NULL, 'register_initiated', 'Tagihan registrasi dibuat: alle@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 22:51:15'),
(48, 10, 'register_paid', 'Pembayaran registrasi: alle@gmail.com via GOPAY', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 22:52:17'),
(49, 10, 'logout', 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 22:56:40'),
(50, NULL, 'register_initiated', 'Tagihan registrasi dibuat: sisir@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 22:57:40'),
(51, 11, 'register_paid', 'Pembayaran registrasi: sisir@gmail.com via QRIS', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 22:57:54'),
(52, 11, 'logout', 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 23:10:38'),
(53, NULL, 'register_initiated', 'Tagihan registrasi dibuat: supriyanto@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 23:28:29'),
(54, 12, 'register_paid', 'Pembayaran registrasi: supriyanto@gmail.com via GOPAY', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 23:30:27'),
(55, 4, 'login', 'Login dari IP: ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', '2026-02-28 13:16:41'),
(56, 4, 'logout', 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', '2026-02-28 13:20:00'),
(57, NULL, 'register_initiated', 'Tagihan registrasi dibuat: test@nevipay.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', '2026-02-28 13:22:41'),
(58, 13, 'register_paid', 'Pembayaran registrasi: test@nevipay.com via GOPAY', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', '2026-02-28 13:22:51'),
(59, 13, 'logout', 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', '2026-02-28 13:25:18'),
(60, 14, 'register_success', 'Registrasi gratis berhasil: test1234@solusimu.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', '2026-02-28 15:26:15'),
(61, 14, 'logout', 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', '2026-02-28 15:27:39'),
(62, 13, 'login', 'Login dari IP: ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', '2026-03-01 00:26:23'),
(63, 13, 'logout', 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', '2026-03-01 00:28:08'),
(64, 13, 'login', 'Login dari IP: ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', '2026-03-01 00:34:29'),
(65, 13, 'logout', 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', '2026-03-01 00:45:32'),
(66, 13, 'login', 'Login dari IP: ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', '2026-03-01 00:49:38'),
(67, 13, 'logout', 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', '2026-03-01 00:50:30'),
(68, 13, 'login', 'Login dari IP: ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', '2026-03-01 00:55:04'),
(69, 13, 'logout', 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', '2026-03-01 00:56:02'),
(70, 13, 'login', 'Login dari IP: ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', '2026-03-01 00:56:06'),
(71, 13, 'logout', 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', '2026-03-01 16:42:40'),
(72, 15, 'register_success', 'Registrasi gratis berhasil: alley@solusimu.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', '2026-03-01 16:43:22'),
(73, 15, 'logout', 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', '2026-03-01 16:43:32'),
(74, 13, 'login', 'Login dari IP: ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', '2026-03-01 16:43:44'),
(75, 13, 'logout', 'Logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', '2026-03-01 16:44:56');

-- --------------------------------------------------------

--
-- Table structure for table `incentive_transfers`
--

CREATE TABLE `incentive_transfers` (
  `id` int(10) UNSIGNED NOT NULL,
  `ref_no` varchar(30) NOT NULL,
  `from_user_id` int(10) UNSIGNED NOT NULL,
  `to_user_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `fee` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Selalu 0 – transfer gratis',
  `note` text DEFAULT NULL,
  `status` enum('success','failed') NOT NULL DEFAULT 'success',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incentive_wallets`
--

CREATE TABLE `incentive_wallets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `locked` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Held for pending withdrawal',
  `total_received` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_transferred` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_withdrawn` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `incentive_wallets`
--

INSERT INTO `incentive_wallets` (`id`, `user_id`, `balance`, `locked`, `total_received`, `total_transferred`, `total_withdrawn`, `created_at`, `updated_at`) VALUES
(1, 2, 150000.00, 0.00, 150000.00, 0.00, 0.00, '2026-02-25 00:01:29', '2026-02-25 00:01:29'),
(2, 3, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-02-25 00:01:29', '2026-02-25 00:01:29'),
(3, 7, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-02-25 00:01:29', '2026-02-25 00:01:29'),
(4, 8, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-02-25 00:01:29', '2026-02-25 00:01:29'),
(5, 9, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-02-25 00:01:29', '2026-02-25 00:01:29'),
(6, 13, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-03-01 01:04:04', '2026-03-01 01:04:04');

-- --------------------------------------------------------

--
-- Table structure for table `incentive_withdrawals`
--

CREATE TABLE `incentive_withdrawals` (
  `id` int(10) UNSIGNED NOT NULL,
  `wdr_no` varchar(25) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL,
  `bank_name` varchar(60) NOT NULL,
  `bank_account_no` varchar(40) NOT NULL,
  `bank_account_name` varchar(100) NOT NULL,
  `note` text DEFAULT NULL,
  `scheduled_date` date NOT NULL COMMENT 'H (sebelum jam 12) atau H+1 (setelah jam 12)',
  `transfer_info` varchar(50) DEFAULT NULL COMMENT 'Hari ini / Besok',
  `status` enum('pending','processing','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_id` int(10) UNSIGNED DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'info',
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 'success', 'Pembayaran Berhasil', 'Transaksi TXN-8821AA sebesar Rp 250.000 berhasil diproses.', 0, '2026-02-22 15:31:27'),
(2, 2, 'info', 'API Key Baru', 'API Key sandbox Anda telah diaktifkan. Mulai testing sekarang!', 0, '2026-02-22 15:31:27'),
(3, 2, 'warning', 'Verifikasi KYC', 'Lengkapi verifikasi KYC Anda untuk meningkatkan limit transaksi.', 1, '2026-02-22 15:31:27'),
(4, 3, 'success', 'Selamat Datang di EgiPay!', 'Akun Anda berhasil dibuat. Mulai terima pembayaran sekarang!', 1, '2026-02-22 22:31:52'),
(5, 1, 'success', 'Pembayaran Berhasil', 'Transaksi TXN-E016B4A9 sebesar Rp 50.000 berhasil diproses.', 1, '2026-02-24 05:17:28'),
(6, 2, 'success', 'Penarikan Disetujui ✓', 'Penarikan WDR-E5F6G7H8 sebesar Rp 1.000.000 telah disetujui. Dana akan segera masuk ke rekening Anda.', 0, '2026-02-24 05:56:27'),
(8, 7, 'success', 'Selamat Datang di EgiPay!', 'Pendaftaran berhasil! Biaya registrasi Rp 12.000 telah diterima. Akun Anda siap digunakan.', 1, '2026-02-24 06:05:18'),
(9, 8, 'success', 'Selamat Datang di EgiPay!', 'Pendaftaran berhasil! Biaya registrasi Rp 12.000 telah diterima. Akun Anda siap digunakan.', 1, '2026-02-24 06:16:56'),
(10, 9, 'success', 'Selamat Datang di EgiPay!', 'Pendaftaran berhasil! Biaya registrasi Rp 12.000 telah diterima. Akun Anda siap digunakan.', 1, '2026-02-24 23:33:09'),
(11, 10, 'success', 'Selamat Datang di EgiPay!', 'Pendaftaran berhasil! Biaya registrasi Rp 12.000 telah diterima. Akun Anda siap digunakan.', 1, '2026-02-26 22:52:17'),
(12, 11, 'success', 'Selamat Datang di EgiPay!', 'Pendaftaran berhasil! Biaya registrasi Rp 12.000 telah diterima. Akun Anda siap digunakan.', 1, '2026-02-26 22:57:54'),
(13, 12, 'success', 'Selamat Datang di EgiPay!', 'Pendaftaran berhasil! Biaya registrasi Rp 12.000 telah diterima. Akun Anda siap digunakan.', 1, '2026-02-26 23:30:27'),
(14, 13, 'success', 'Selamat Datang di SolusiMu!', 'Pendaftaran berhasil! Biaya registrasi Rp 12.000 telah diterima. Akun Anda siap digunakan.', 1, '2026-02-28 13:22:51'),
(15, 14, 'success', 'Selamat Datang di SolusiMu!', 'Akun Anda berhasil dibuat. Mulai terima pembayaran sekarang!', 1, '2026-02-28 15:26:15'),
(16, 15, 'success', 'Selamat Datang di SolusiMu!', 'Akun Anda berhasil dibuat. Mulai terima pembayaran sekarang!', 1, '2026-03-01 16:43:22'),
(17, 13, 'success', 'Referral Berhasil! 🎉', 'alley baru saja mendaftar menggunakan link referral Anda. Total referral Anda: 1', 1, '2026-03-01 16:43:22');

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(60) NOT NULL,
  `type` enum('ewallet','bank_transfer','qris','credit_card','minimarket','paylater') NOT NULL,
  `icon_class` varchar(50) DEFAULT 'bi bi-credit-card',
  `color` varchar(10) DEFAULT '#6c63ff',
  `fee_percent` decimal(5,2) NOT NULL DEFAULT 1.90,
  `fee_flat` decimal(10,2) NOT NULL DEFAULT 0.00,
  `min_amount` decimal(12,2) NOT NULL DEFAULT 1000.00,
  `max_amount` decimal(12,2) NOT NULL DEFAULT 50000000.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `name`, `type`, `icon_class`, `color`, `fee_percent`, `fee_flat`, `min_amount`, `max_amount`, `is_active`, `sort_order`) VALUES
(1, 'QRIS', 'qris', 'bi bi-qr-code', '#6c63ff', 0.70, 0.00, 1000.00, 10000000.00, 1, 1),
(2, 'GoPay', 'ewallet', 'bi bi-phone', '#00d4ff', 1.50, 0.00, 1000.00, 10000000.00, 1, 2),
(3, 'OVO', 'ewallet', 'bi bi-phone-fill', '#a78bfa', 1.50, 0.00, 1000.00, 10000000.00, 1, 3),
(4, 'DANA', 'ewallet', 'bi bi-phone-vibrate', '#10b981', 1.50, 0.00, 1000.00, 10000000.00, 1, 4),
(5, 'ShopeePay', 'ewallet', 'bi bi-bag', '#f59e0b', 1.50, 0.00, 1000.00, 10000000.00, 1, 5),
(6, 'BCA', 'bank_transfer', 'bi bi-bank', '#f59e0b', 0.00, 4000.00, 10000.00, 50000000.00, 1, 6),
(7, 'Mandiri', 'bank_transfer', 'bi bi-bank2', '#ef4444', 0.00, 4000.00, 10000.00, 50000000.00, 1, 7),
(8, 'BNI', 'bank_transfer', 'bi bi-building', '#3b82f6', 0.00, 4000.00, 10000.00, 50000000.00, 1, 8),
(9, 'BRI', 'bank_transfer', 'bi bi-bank', '#60a5fa', 0.00, 4000.00, 10000.00, 50000000.00, 1, 9),
(10, 'Visa/Mastercard', 'credit_card', 'bi bi-credit-card', '#f72585', 2.90, 0.00, 10000.00, 50000000.00, 1, 10),
(11, 'Indomaret', 'minimarket', 'bi bi-shop', '#6c63ff', 0.00, 2500.00, 10000.00, 5000000.00, 1, 11),
(12, 'Alfamart', 'minimarket', 'bi bi-shop-window', '#00d4ff', 0.00, 2500.00, 10000.00, 5000000.00, 1, 12),
(13, 'Akulaku', 'paylater', 'bi bi-shield-check', '#a78bfa', 2.50, 0.00, 50000.00, 20000000.00, 1, 13),
(14, 'Kredivo', 'paylater', 'bi bi-credit-card-2-front', '#f72585', 2.50, 0.00, 50000.00, 20000000.00, 1, 14);

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

CREATE TABLE `referrals` (
  `id` int(10) UNSIGNED NOT NULL,
  `referrer_id` int(10) UNSIGNED NOT NULL,
  `referred_id` int(10) UNSIGNED NOT NULL,
  `referral_code` varchar(30) NOT NULL,
  `status` enum('pending','rewarded') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `referrals`
--

INSERT INTO `referrals` (`id`, `referrer_id`, `referred_id`, `referral_code`, `status`, `created_at`) VALUES
(1, 13, 15, 'SMU-107947', 'pending', '2026-03-01 16:43:22');

-- --------------------------------------------------------

--
-- Table structure for table `registration_payments`
--

CREATE TABLE `registration_payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `inv_no` varchar(30) NOT NULL,
  `token` varchar(64) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `plan` enum('starter','business','enterprise') NOT NULL DEFAULT 'starter',
  `referral_code` varchar(30) DEFAULT NULL,
  `referred_by` int(10) UNSIGNED DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 12000.00,
  `status` enum('pending','paid','expired') NOT NULL DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `registration_payments`
--

INSERT INTO `registration_payments` (`id`, `inv_no`, `token`, `name`, `email`, `phone`, `password_hash`, `plan`, `referral_code`, `referred_by`, `amount`, `status`, `payment_method`, `expires_at`, `paid_at`, `created_at`) VALUES
(1, 'INV-REG-AA453C07', '296754ed47c041ddc327558952f70f22c278333735a1bc38d9c371922630b0e6', 'ilham', 'ilham@gmail.com', '088888899999', '$2y$12$HUH/91tDzSMl0Zv7nfzJa.ROmq7phj9kyadqh0EfkWe.JDXVOrfU2', 'starter', NULL, NULL, 12000.00, 'expired', NULL, '2026-02-23 15:38:48', NULL, '2026-02-23 21:23:48'),
(2, 'INV-REG-0DC68960', '93acf58d2476033151f4e8751afd7cea26e58a4299d4117bfdff0d67777a3778', 'fattah', 'fattahal@gmail.com', '089664648974', '$2y$12$Z.yq/6wfz0REEfJH3C4urua9Zed8.rBLj2dQclibfWO.HFJjog8Mu', 'starter', NULL, NULL, 12000.00, 'expired', NULL, '2026-02-23 23:38:56', NULL, '2026-02-24 05:23:56'),
(4, 'INV-REG-EBA4BE61', '04223a37db9188e30d6aa0cac3d92f613943387b06ae66ec56445ea5df7db361', 'ulum', 'ulum@gmail.com', '08989898989', '$2y$12$JizlwK66M5nN7b5/1tA2QelJMsEx3.6eluG9r6Koik.ChYtH/Srgm', 'starter', NULL, NULL, 12000.00, 'paid', 'BCA', '2026-02-24 06:20:12', '2026-02-24 06:05:18', '2026-02-24 06:05:12'),
(5, 'INV-REG-3DBE045F', 'b896919b7d48f48eb7c27b3d9efccfc5394527a1786c4205eed5932f61838bbc', 'test', 'test@gmail.com', '09876546222', '$2y$12$H013cNOwOlAawgOxXkROSuZ6IkgKopCN8OFIPePxnHxE8J07b81ly', 'starter', NULL, NULL, 12000.00, 'paid', 'GOPAY', '2026-02-24 06:31:43', '2026-02-24 06:16:56', '2026-02-24 06:16:43'),
(6, 'INV-REG-94493CD8', 'c05e97c5f6fb64198c8aebcf88ea746557df3b55f9f2d1165d47bfd3bb638b43', 'testtest', 'testtest@gmail.com', 'testtt', '$2y$12$R5626EsCfWmxuDcLzN3M.O72pWUY9wbD9MjYZeZGu5Fg2/zkvFrF2', 'starter', NULL, NULL, 12000.00, 'paid', 'QRIS', '2026-02-24 23:47:32', '2026-02-24 23:33:09', '2026-02-24 23:32:32'),
(7, 'INV-REG-0D23D3B7', '780a9952f7808acafe000569d99c39a61e0297dcef7d2f4aca7e7b33b1149a42', 'aleredha', 'alle@gmail.com', '099899078389783', '$2y$12$e.R3E.cGlMsbtg2W0UCGBOwHJ4H9qY3JrFSkD9FI.MhJ/CMLltjtS', 'starter', NULL, NULL, 12000.00, 'paid', 'GOPAY', '2026-02-26 23:06:15', '2026-02-26 22:52:17', '2026-02-26 22:51:15'),
(8, 'INV-REG-F2D842F6', 'f2965099a19775c1f8b030570e303e9eb0a089857faa1ed3789f5ff4a24214d6', 'sisir', 'sisir@gmail.com', '123567890', '$2y$12$oKrU6aNO0qum0l4PJXs6teOYGGLnR.BCavm9d87yMADUT3bjPU7wK', 'starter', NULL, NULL, 12000.00, 'paid', 'QRIS', '2026-02-26 23:12:40', '2026-02-26 22:57:54', '2026-02-26 22:57:40'),
(9, 'INV-REG-3284C1CB', '8c16e4de0448d2f124bed7bd26b203fc5bd310f53c1a6b1edb0944913d48ead0', 'supriyanto', 'supriyanto@gmail.com', '123567890', '$2y$12$XIMMTYm3xacWBD1fkxtTVOFwjw09G4UPeus709rXC42Wza9mTA.Le', 'starter', NULL, NULL, 12000.00, 'paid', 'GOPAY', '2026-02-26 23:43:29', '2026-02-26 23:30:27', '2026-02-26 23:28:29'),
(10, 'INV-REG-AD71AF57', '8adc4565a700e95285d83a3b8e852c48f33ad3e26a6df4df18f262be455e4288', 'test', 'test@nevipay.com', '08123456789', '$2y$12$uZXPci7DSggKy0/CPYTph.d3tRByGzRDGpvbYRq08qC4qp8W1.Z5e', 'starter', NULL, NULL, 12000.00, 'paid', 'GOPAY', '2026-02-28 13:37:41', '2026-02-28 13:22:51', '2026-02-28 13:22:41');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `tx_id` varchar(30) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `payment_method_id` int(10) UNSIGNED DEFAULT NULL,
  `type` enum('payment','topup','withdrawal','refund') NOT NULL DEFAULT 'payment',
  `amount` decimal(15,2) NOT NULL,
  `fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'IDR',
  `recipient` varchar(150) DEFAULT NULL,
  `recipient_bank` varchar(100) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `status` enum('pending','success','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `snap_token` varchar(255) DEFAULT NULL COMMENT 'Midtrans snap token if used',
  `paid_at` datetime DEFAULT NULL,
  `expired_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `tx_id`, `user_id`, `payment_method_id`, `type`, `amount`, `fee`, `total`, `currency`, `recipient`, `recipient_bank`, `note`, `status`, `snap_token`, `paid_at`, `expired_at`, `created_at`, `updated_at`) VALUES
(1, 'TXN-8821AA', 2, 2, 'payment', 250000.00, 3750.00, 253750.00, 'IDR', 'GoPay: 08123456789', NULL, 'Belanja online', 'success', NULL, '2026-02-22 14:31:27', NULL, '2026-02-22 14:31:27', '2026-02-22 15:31:27'),
(2, 'TXN-8820BB', 2, 1, 'payment', 1500000.00, 10500.00, 1510500.00, 'IDR', 'Toko ABC', NULL, 'Pembelian produk', 'success', NULL, '2026-02-22 12:31:27', NULL, '2026-02-22 12:31:27', '2026-02-22 15:31:27'),
(3, 'TXN-8819CC', 2, 7, 'payment', 750000.00, 4000.00, 754000.00, 'IDR', 'Acc: 1234567890', NULL, 'Pembayaran jasa', 'pending', NULL, NULL, NULL, '2026-02-21 15:31:27', '2026-02-22 15:31:27'),
(4, 'TXN-8818DD', 2, 10, 'payment', 3200000.00, 92800.00, 3292800.00, 'IDR', 'PT Mitra Jaya', NULL, 'Invoice #INV-001', 'success', NULL, '2026-02-21 15:31:27', NULL, '2026-02-21 15:31:27', '2026-02-22 15:31:27'),
(5, 'TXN-8817EE', 2, 3, 'payment', 85000.00, 1275.00, 86275.00, 'IDR', 'OVO: 08987654321', NULL, 'Bayar tagihan', 'failed', NULL, NULL, NULL, '2026-02-20 15:31:27', '2026-02-22 15:31:27'),
(6, 'TXN-8816FF', 2, 4, 'payment', 420000.00, 6300.00, 426300.00, 'IDR', 'Ahmad Shop', NULL, 'Pembelian bahan baku', 'success', NULL, '2026-02-20 15:31:27', NULL, '2026-02-20 15:31:27', '2026-02-22 15:31:27'),
(7, 'TXN-8815GG', 2, 6, 'payment', 1100000.00, 4000.00, 1104000.00, 'IDR', 'Acc: 0987654321', NULL, 'Gaji freelancer', 'success', NULL, '2026-02-19 15:31:27', NULL, '2026-02-19 15:31:27', '2026-02-22 15:31:27'),
(8, 'TXN-8814HH', 2, 11, 'payment', 200000.00, 2500.00, 202500.00, 'IDR', 'Kode: 123456789', NULL, 'Tagihan listrik', 'pending', NULL, NULL, NULL, '2026-02-19 15:31:27', '2026-02-22 15:31:27'),
(9, 'TXN-8813II', 2, 1, 'payment', 500000.00, 3500.00, 503500.00, 'IDR', 'Toko Online', NULL, 'Flash sale', 'success', NULL, '2026-02-17 15:31:27', NULL, '2026-02-17 15:31:27', '2026-02-22 15:31:27'),
(10, 'TXN-8812JJ', 2, 2, 'topup', 2000000.00, 0.00, 2000000.00, 'IDR', 'EgiPay Topup', NULL, 'Top up saldo', 'success', NULL, '2026-02-16 15:31:27', NULL, '2026-02-16 15:31:27', '2026-02-22 15:31:27'),
(11, 'TXN-E016B4A9', 1, 1, 'payment', 50000.00, 350.00, 50350.00, 'IDR', '089664848974', NULL, NULL, 'success', NULL, '2026-02-24 05:17:28', NULL, '2026-02-24 05:17:28', '2026-02-24 05:17:28'),
(12, 'WDR-TX-E43C07', 2, NULL, 'withdrawal', 1000000.00, 6500.00, 993500.00, 'IDR', 'Demo Merchant · 9876543210', 'BNI', 'WDR-E5F6G7H8', 'success', NULL, '2026-02-24 05:56:27', NULL, '2026-02-24 05:56:27', '2026-02-24 05:56:27');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `member_code` varchar(20) DEFAULT NULL,
  `referral_code` varchar(30) DEFAULT NULL,
  `referred_by` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('superadmin','admin','merchant','customer') NOT NULL DEFAULT 'merchant',
  `plan` enum('starter','business','enterprise') NOT NULL DEFAULT 'starter',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `avatar` varchar(10) DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `member_code`, `referral_code`, `referred_by`, `name`, `email`, `password`, `phone`, `role`, `plan`, `status`, `avatar`, `email_verified_at`, `created_at`, `updated_at`) VALUES
(1, 'MU-000000001', 'SMU-88263F', NULL, 'Admin EgiPay', 'admin@egipay.com', '$2y$10$C4SxKih5n1/8DWhSkohzg.j/S7oStOWkTPMQ8Gb2UimmqIWZRIilO', '+62811000001', 'admin', 'enterprise', 'active', 'AE', '2026-02-22 15:31:27', '2026-02-22 15:31:27', '2026-03-01 16:00:40'),
(2, 'MU-000000002', 'SMU-6CA609', NULL, 'Demo Merchant', 'merchant@demo.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+62812000002', 'merchant', 'business', 'active', 'DM', '2026-02-22 15:31:27', '2026-02-22 15:31:27', '2026-03-01 16:00:40'),
(3, 'MU-000000003', 'SMU-796872', NULL, 'fattah', 'fattah@gmail.com', '$2y$12$9tH8vX433Qlv7FqDWD8GVOhsJL/VYJb8zDzXqZpw/Y.11PLAN110i', '08999999999', 'merchant', 'starter', 'active', 'F', '2026-02-22 22:31:52', '2026-02-22 22:31:52', '2026-03-01 16:00:40'),
(4, NULL, 'SMU-84FE08', NULL, 'Super Admin EgiPay', 'superadmin@egipay.com', '$2y$10$Xah3UIuf.RGGOdmP2MClBO2v1Bt.3JQwwv8kvGbUQBxEjiOmZgeoK', '+62800000001', 'superadmin', 'enterprise', 'active', 'SA', '2026-02-24 05:34:54', '2026-02-24 05:34:54', '2026-03-01 16:00:40'),
(7, 'MU-000000007', 'SMU-729A0C', NULL, 'ulum', 'ulum@gmail.com', '$2y$12$JizlwK66M5nN7b5/1tA2QelJMsEx3.6eluG9r6Koik.ChYtH/Srgm', '08989898989', 'merchant', 'starter', 'active', 'U', '2026-02-24 06:05:18', '2026-02-24 06:05:18', '2026-03-01 16:00:40'),
(8, 'MU-000000008', 'SMU-9119ED', NULL, 'test', 'test@gmail.com', '$2y$12$H013cNOwOlAawgOxXkROSuZ6IkgKopCN8OFIPePxnHxE8J07b81ly', '09876546222', 'merchant', 'starter', 'active', 'T', '2026-02-24 06:16:56', '2026-02-24 06:16:56', '2026-03-01 16:00:40'),
(9, 'MU-000000009', 'SMU-5F5970', NULL, 'testtest', 'testtest@gmail.com', '$2y$12$R5626EsCfWmxuDcLzN3M.O72pWUY9wbD9MjYZeZGu5Fg2/zkvFrF2', 'testtt', 'merchant', 'starter', 'active', 'T', '2026-02-24 23:33:09', '2026-02-24 23:33:09', '2026-03-01 16:00:40'),
(10, 'MU-000000010', 'SMU-FCFAD2', NULL, 'aleredha', 'alle@gmail.com', '$2y$12$e.R3E.cGlMsbtg2W0UCGBOwHJ4H9qY3JrFSkD9FI.MhJ/CMLltjtS', '099899078389783', 'merchant', 'starter', 'active', 'A', '2026-02-26 22:52:17', '2026-02-26 22:52:17', '2026-03-01 16:00:40'),
(11, 'MU-000000011', 'SMU-F705F1', NULL, 'sisir', 'sisir@gmail.com', '$2y$12$oKrU6aNO0qum0l4PJXs6teOYGGLnR.BCavm9d87yMADUT3bjPU7wK', '123567890', 'merchant', 'starter', 'active', 'S', '2026-02-26 22:57:54', '2026-02-26 22:57:54', '2026-03-01 16:00:40'),
(12, 'MU-000000012', 'SMU-851B9F', NULL, 'supriyanto', 'supriyanto@gmail.com', '$2y$12$XIMMTYm3xacWBD1fkxtTVOFwjw09G4UPeus709rXC42Wza9mTA.Le', '123567890', 'merchant', 'starter', 'active', 'S', '2026-02-26 23:30:27', '2026-02-26 23:30:27', '2026-03-01 16:00:40'),
(13, 'MU-000000013', 'SMU-107947', NULL, 'test', 'test@nevipay.com', '$2y$12$uZXPci7DSggKy0/CPYTph.d3tRByGzRDGpvbYRq08qC4qp8W1.Z5e', '08123456789', 'merchant', 'starter', 'active', 'T', '2026-02-28 13:22:51', '2026-02-28 13:22:51', '2026-03-01 16:00:40'),
(14, 'MU-000000014', 'SMU-6EB92A', NULL, 'test1234', 'test1234@solusimu.com', '$2y$12$bcYMM0AeAOwMSNNSRNeI1OGSyROPg7psXTrHTacp.VtjoZGhRyXLu', '123454321', 'merchant', '', 'active', 'T', '2026-02-28 15:26:15', '2026-02-28 15:26:15', '2026-03-01 16:00:40'),
(15, 'MU-000000015', 'SMU-C2A581', 13, 'alley', 'alley@solusimu.com', '$2y$12$YRQdqxjhhxz0aH8sWkAJ6u5AC0FXXk0KyY77ddDHPRxijX6RbDR9K', '08123454321', 'merchant', '', 'active', 'A', '2026-03-01 16:43:22', '2026-03-01 16:43:22', '2026-03-01 16:43:22');

-- --------------------------------------------------------

--
-- Table structure for table `wallets`
--

CREATE TABLE `wallets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `locked` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Pending/held amount',
  `total_in` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_out` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wallets`
--

INSERT INTO `wallets` (`id`, `user_id`, `balance`, `locked`, `total_in`, `total_out`, `created_at`, `updated_at`) VALUES
(1, 1, 99949650.00, 0.00, 100000000.00, 50350.00, '2026-02-22 15:31:27', '2026-02-24 05:17:28'),
(2, 2, 23821500.00, 0.00, 120000000.00, 96178500.00, '2026-02-22 15:31:27', '2026-02-24 05:56:27'),
(3, 3, 0.00, 0.00, 0.00, 0.00, '2026-02-22 22:31:52', '2026-02-22 22:31:52'),
(4, 4, 0.00, 0.00, 0.00, 0.00, '2026-02-24 05:34:54', '2026-02-24 05:34:54'),
(6, 7, 0.00, 0.00, 0.00, 0.00, '2026-02-24 06:05:18', '2026-02-24 06:05:18'),
(7, 8, 0.00, 0.00, 0.00, 0.00, '2026-02-24 06:16:56', '2026-02-24 06:16:56'),
(8, 9, 0.00, 0.00, 0.00, 0.00, '2026-02-24 23:33:09', '2026-02-24 23:33:09'),
(9, 10, 0.00, 0.00, 0.00, 0.00, '2026-02-26 22:52:17', '2026-02-26 22:52:17'),
(10, 11, 0.00, 0.00, 0.00, 0.00, '2026-02-26 22:57:54', '2026-02-26 22:57:54'),
(11, 12, 0.00, 0.00, 0.00, 0.00, '2026-02-26 23:30:27', '2026-02-26 23:30:27'),
(12, 13, 0.00, 0.00, 0.00, 0.00, '2026-02-28 13:22:51', '2026-02-28 13:22:51'),
(13, 14, 0.00, 0.00, 0.00, 0.00, '2026-02-28 15:26:15', '2026-02-28 15:26:15'),
(14, 15, 0.00, 0.00, 0.00, 0.00, '2026-03-01 16:43:22', '2026-03-01 16:43:22');

-- --------------------------------------------------------

--
-- Table structure for table `withdrawals`
--

CREATE TABLE `withdrawals` (
  `id` int(10) UNSIGNED NOT NULL,
  `wdr_no` varchar(25) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `fee` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Admin fee deducted',
  `net_amount` decimal(15,2) NOT NULL COMMENT 'amount - fee',
  `bank_name` varchar(60) NOT NULL,
  `bank_account_no` varchar(40) NOT NULL,
  `bank_account_name` varchar(100) NOT NULL,
  `note` text DEFAULT NULL COMMENT 'Member note',
  `status` enum('pending','processing','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_id` int(10) UNSIGNED DEFAULT NULL,
  `admin_note` text DEFAULT NULL COMMENT 'Admin reason / note',
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `withdrawals`
--

INSERT INTO `withdrawals` (`id`, `wdr_no`, `user_id`, `amount`, `fee`, `net_amount`, `bank_name`, `bank_account_no`, `bank_account_name`, `note`, `status`, `admin_id`, `admin_note`, `processed_at`, `created_at`, `updated_at`) VALUES
(1, 'WDR-A1B2C3D4', 2, 500000.00, 6500.00, 493500.00, 'BCA', '1234567890', 'Demo Merchant', 'Penarikan pertama', 'approved', 1, NULL, '2026-02-22 05:20:36', '2026-02-21 05:20:36', '2026-02-24 05:20:36'),
(2, 'WDR-E5F6G7H8', 2, 1000000.00, 6500.00, 993500.00, 'BNI', '9876543210', 'Demo Merchant', NULL, 'approved', 4, NULL, '2026-02-24 05:56:27', '2026-02-24 04:20:36', '2026-02-24 05:56:27'),
(3, 'WDR-I9J0K1L2', 2, 250000.00, 3750.00, 246250.00, 'GoPay', '081234567890', 'Demo Merchant', 'Tarik ke GoPay', 'rejected', 1, NULL, '2026-02-19 05:20:36', '2026-02-18 05:20:36', '2026-02-24 05:20:36');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `api_keys`
--
ALTER TABLE `api_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `client_key` (`client_key`),
  ADD UNIQUE KEY `server_key` (`server_key`),
  ADD KEY `fk_apikeys_user` (`user_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_action` (`action`);

--
-- Indexes for table `incentive_transfers`
--
ALTER TABLE `incentive_transfers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ref_no` (`ref_no`),
  ADD KEY `idx_inctrf_from` (`from_user_id`),
  ADD KEY `idx_inctrf_to` (`to_user_id`),
  ADD KEY `idx_inctrf_ref` (`ref_no`);

--
-- Indexes for table `incentive_wallets`
--
ALTER TABLE `incentive_wallets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_incwallet_user` (`user_id`);

--
-- Indexes for table `incentive_withdrawals`
--
ALTER TABLE `incentive_withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wdr_no` (`wdr_no`),
  ADD KEY `idx_incwdr_user` (`user_id`),
  ADD KEY `idx_incwdr_status` (`status`),
  ADD KEY `idx_incwdr_sched` (`scheduled_date`),
  ADD KEY `fk_incwdr_admin` (`admin_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_user` (`user_id`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `referrals`
--
ALTER TABLE `referrals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_referred` (`referred_id`),
  ADD KEY `idx_referrer` (`referrer_id`);

--
-- Indexes for table `registration_payments`
--
ALTER TABLE `registration_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `inv_no` (`inv_no`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_reg_email` (`email`),
  ADD KEY `idx_reg_token` (`token`),
  ADD KEY `idx_reg_status` (`status`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tx_id` (`tx_id`),
  ADD KEY `idx_tx_id` (`tx_id`),
  ADD KEY `idx_user_tx` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `fk_tx_method` (`payment_method_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `member_code` (`member_code`),
  ADD UNIQUE KEY `referral_code` (`referral_code`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_member_code` (`member_code`),
  ADD KEY `idx_referral_code` (`referral_code`),
  ADD KEY `fk_referred_by` (`referred_by`);

--
-- Indexes for table `wallets`
--
ALTER TABLE `wallets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wallet_user` (`user_id`);

--
-- Indexes for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wdr_no` (`wdr_no`),
  ADD KEY `idx_wdr_user` (`user_id`),
  ADD KEY `idx_wdr_status` (`status`),
  ADD KEY `idx_wdr_no` (`wdr_no`),
  ADD KEY `fk_wdr_admin` (`admin_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `api_keys`
--
ALTER TABLE `api_keys`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `incentive_transfers`
--
ALTER TABLE `incentive_transfers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incentive_wallets`
--
ALTER TABLE `incentive_wallets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `incentive_withdrawals`
--
ALTER TABLE `incentive_withdrawals`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `referrals`
--
ALTER TABLE `referrals`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `registration_payments`
--
ALTER TABLE `registration_payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `wallets`
--
ALTER TABLE `wallets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `withdrawals`
--
ALTER TABLE `withdrawals`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `api_keys`
--
ALTER TABLE `api_keys`
  ADD CONSTRAINT `fk_apikeys_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `incentive_transfers`
--
ALTER TABLE `incentive_transfers`
  ADD CONSTRAINT `fk_inctrf_from` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inctrf_to` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `incentive_wallets`
--
ALTER TABLE `incentive_wallets`
  ADD CONSTRAINT `fk_incwallet_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `incentive_withdrawals`
--
ALTER TABLE `incentive_withdrawals`
  ADD CONSTRAINT `fk_incwdr_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_incwdr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `referrals`
--
ALTER TABLE `referrals`
  ADD CONSTRAINT `fk_ref_referred` FOREIGN KEY (`referred_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ref_referrer` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_tx_method` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tx_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_referred_by` FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `wallets`
--
ALTER TABLE `wallets`
  ADD CONSTRAINT `fk_wallets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD CONSTRAINT `fk_wdr_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_wdr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
