-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 10, 2025 at 10:30 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fullcalendar`
--

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `description` varchar(191) DEFAULT NULL,
  `summary` varchar(191) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `holidays`
--

INSERT INTO `holidays` (`id`, `description`, `summary`, `start_date`, `end_date`, `created_at`, `updated_at`) VALUES
(1, 'Public holiday', 'President Moi Celebration of Life Day', '2020-02-11', '2020-02-12', '2025-04-24 01:06:07', '2025-04-24 01:06:07'),
(2, 'Public holiday', 'Jamhuri Day', '2020-12-12', '2020-12-13', '2025-04-24 01:06:07', '2025-04-24 01:06:07'),
(3, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'New Year\'s Eve', '2020-12-31', '2021-01-01', '2025-04-24 01:06:08', '2025-04-24 01:06:08'),
(4, 'Public holiday', 'Good Friday', '2021-04-02', '2021-04-03', '2025-04-24 01:06:08', '2025-04-24 01:06:08'),
(5, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Eid al-Adha', '2021-07-20', '2021-07-21', '2025-04-24 01:06:08', '2025-04-24 01:06:08'),
(6, 'Public holiday', 'Day off for Utamaduni Day', '2021-10-11', '2021-10-12', '2025-04-24 01:06:08', '2025-04-24 01:06:08'),
(7, 'Public holiday', 'Jamhuri Day', '2021-12-12', '2021-12-13', '2025-04-24 01:06:08', '2025-04-24 01:06:08'),
(8, 'Public holiday', 'Inauguration Day', '2022-09-13', '2022-09-14', '2025-04-24 01:06:08', '2025-04-24 01:06:08'),
(9, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Diwali', '2023-11-12', '2023-11-13', '2025-04-24 01:06:08', '2025-04-24 01:06:08'),
(10, 'Public holiday', 'National Tree Planting Day', '2023-11-13', '2023-11-14', '2025-04-24 01:06:08', '2025-04-24 01:06:08'),
(11, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Christmas Eve', '2023-12-24', '2023-12-25', '2025-04-24 01:06:08', '2025-04-24 01:06:08'),
(12, 'Public holiday', 'Madaraka Day', '2024-06-01', '2024-06-02', '2025-04-24 01:06:08', '2025-04-24 01:06:08'),
(13, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Christmas Eve', '2024-12-24', '2024-12-25', '2025-04-24 01:06:08', '2025-04-24 01:06:08'),
(14, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'New Year\'s Eve', '2024-12-31', '2025-01-01', '2025-04-24 01:06:08', '2025-04-24 01:06:08'),
(15, 'Public holiday', 'Christmas Day', '2025-12-25', '2025-12-26', '2025-04-24 01:06:09', '2025-04-24 01:06:09'),
(16, 'Public holiday', 'New Year\'s Day', '2026-01-01', '2026-01-02', '2025-04-24 01:06:09', '2025-04-24 01:06:09'),
(17, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Christmas Eve', '2026-12-24', '2026-12-25', '2025-04-24 01:06:09', '2025-04-24 01:06:09'),
(18, 'Public holiday', 'Day off for Mazingira Day', '2027-10-11', '2027-10-12', '2025-04-24 01:06:09', '2025-04-24 01:06:09'),
(19, 'Public holiday', 'Jamhuri Day', '2028-12-12', '2028-12-13', '2025-04-24 01:06:09', '2025-04-24 01:06:09'),
(20, 'Public holiday', 'New Year\'s Day', '2029-01-01', '2029-01-02', '2025-04-24 01:06:09', '2025-04-24 01:06:09'),
(21, 'Public holiday', 'Easter Monday', '2029-04-02', '2029-04-03', '2025-04-24 01:06:09', '2025-04-24 01:06:09'),
(22, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Mother\'s Day', '2020-05-10', '2020-05-11', '2025-04-24 01:06:10', '2025-04-24 01:06:10'),
(23, 'Public holiday', 'Idd ul-Fitr', '2020-05-24', '2020-05-25', '2025-04-24 01:06:10', '2025-04-24 01:06:10'),
(24, 'Public holiday', 'Day off for Idd ul-Fitr', '2020-05-25', '2020-05-26', '2025-04-24 01:06:10', '2025-04-24 01:06:10'),
(25, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Christmas Eve', '2020-12-24', '2020-12-25', '2025-04-24 01:06:10', '2025-04-24 01:06:10'),
(26, 'Public holiday', 'Easter Monday', '2021-04-05', '2021-04-06', '2025-04-24 01:06:11', '2025-04-24 01:06:11'),
(27, 'Public holiday', 'Day off for Huduma Day', '2021-10-11', '2021-10-12', '2025-04-24 01:06:11', '2025-04-24 01:06:11'),
(28, 'Public holiday', 'Boxing Day', '2021-12-26', '2021-12-27', '2025-04-24 01:06:11', '2025-04-24 01:06:11'),
(29, 'Public holiday', 'Day off for Boxing Day', '2021-12-27', '2021-12-28', '2025-04-24 01:06:11', '2025-04-24 01:06:11'),
(30, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Easter Sunday', '2022-04-17', '2022-04-18', '2025-04-24 01:06:11', '2025-04-24 01:06:11'),
(31, 'Public holiday', 'Idd ul-Fitr', '2022-05-02', '2022-05-03', '2025-04-24 01:06:11', '2025-04-24 01:06:11'),
(32, 'Public holiday', 'Election Day', '2022-08-09', '2022-08-10', '2025-04-24 01:06:11', '2025-04-24 01:06:11'),
(33, 'Public holiday', 'Day of Mourning for Queen Elizabeth II', '2022-09-10', '2022-09-11', '2025-04-24 01:06:12', '2025-04-24 01:06:12'),
(34, 'Public holiday', 'Day of Mourning for Queen Elizabeth II', '2022-09-12', '2022-09-13', '2025-04-24 01:06:12', '2025-04-24 01:06:12'),
(35, 'Public holiday', 'Mashujaa Day', '2022-10-20', '2022-10-21', '2025-04-24 01:06:12', '2025-04-24 01:06:12'),
(36, 'Public holiday', 'Jamhuri Day', '2022-12-12', '2022-12-13', '2025-04-24 01:06:12', '2025-04-24 01:06:12'),
(37, 'Public holiday', 'Christmas Day', '2022-12-25', '2022-12-26', '2025-04-24 01:06:12', '2025-04-24 01:06:12'),
(38, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'New Year\'s Eve', '2022-12-31', '2023-01-01', '2025-04-24 01:06:12', '2025-04-24 01:06:12'),
(39, 'Public holiday', 'Good Friday', '2023-04-07', '2023-04-08', '2025-04-24 01:06:12', '2025-04-24 01:06:12'),
(40, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Eid al-Adha', '2023-06-28', '2023-06-29', '2025-04-24 01:06:13', '2025-04-24 01:06:13'),
(41, 'Public holiday', 'Huduma Day', '2023-10-10', '2023-10-11', '2025-04-24 01:06:13', '2025-04-24 01:06:13'),
(42, 'Public holiday', 'Jamhuri Day', '2023-12-12', '2023-12-13', '2025-04-24 01:06:13', '2025-04-24 01:06:13'),
(43, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Easter Sunday', '2024-03-31', '2024-04-01', '2025-04-24 01:06:13', '2025-04-24 01:06:13'),
(44, 'Public holiday', 'National Tree Planting Day', '2024-05-10', '2024-05-11', '2025-04-24 01:06:13', '2025-04-24 01:06:13'),
(45, 'Public holiday', 'Boxing Day', '2024-12-26', '2024-12-27', '2025-04-24 01:06:13', '2025-04-24 01:06:13'),
(46, 'Public holiday', 'New Year\'s Day', '2025-01-01', '2025-01-02', '2025-04-24 01:06:14', '2025-04-24 01:06:14'),
(47, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Easter Sunday', '2025-04-20', '2025-04-21', '2025-04-24 01:06:14', '2025-04-24 01:06:14'),
(48, 'Public holiday', 'Easter Monday', '2025-04-21', '2025-04-22', '2025-04-24 01:06:14', '2025-04-24 01:06:14'),
(49, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'New Year\'s Eve', '2025-12-31', '2026-01-01', '2025-04-24 01:06:14', '2025-04-24 01:06:14'),
(50, 'Public holiday', 'Good Friday', '2026-04-03', '2026-04-04', '2025-04-24 01:06:14', '2025-04-24 01:06:14'),
(51, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Easter Sunday', '2026-04-05', '2026-04-06', '2025-04-24 01:06:15', '2025-04-24 01:06:15'),
(52, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Easter Sunday', '2027-03-28', '2027-03-29', '2025-04-24 01:06:15', '2025-04-24 01:06:15'),
(53, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Mother\'s Day', '2027-05-09', '2027-05-10', '2025-04-24 01:06:15', '2025-04-24 01:06:15'),
(54, 'Public holiday', 'Mazingira Day', '2027-10-10', '2027-10-11', '2025-04-24 01:06:15', '2025-04-24 01:06:15'),
(55, 'Public holiday', 'Mashujaa Day', '2027-10-20', '2027-10-21', '2025-04-24 01:06:15', '2025-04-24 01:06:15'),
(56, 'Public holiday', 'Jamhuri Day observed', '2027-12-13', '2027-12-14', '2025-04-24 01:06:15', '2025-04-24 01:06:15'),
(57, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Christmas Eve', '2027-12-24', '2027-12-25', '2025-04-24 01:06:16', '2025-04-24 01:06:16'),
(58, 'Public holiday', 'Christmas Day', '2027-12-25', '2027-12-26', '2025-04-24 01:06:16', '2025-04-24 01:06:16'),
(59, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Easter Sunday', '2028-04-16', '2028-04-17', '2025-04-24 01:06:16', '2025-04-24 01:06:16'),
(60, 'Public holiday', 'Boxing Day', '2028-12-26', '2028-12-27', '2025-04-24 01:06:16', '2025-04-24 01:06:16'),
(61, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Easter Sunday', '2029-04-01', '2029-04-02', '2025-04-24 01:06:16', '2025-04-24 01:06:16'),
(62, 'Public holiday', 'Mashujaa Day', '2029-10-20', '2029-10-21', '2025-04-24 01:06:16', '2025-04-24 01:06:16'),
(63, 'Public holiday', 'New Year\'s Day', '2020-01-01', '2020-01-02', '2025-04-24 01:06:16', '2025-04-24 01:06:16'),
(64, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Diwali', '2020-11-14', '2020-11-15', '2025-04-24 01:06:17', '2025-04-24 01:06:17'),
(65, 'Public holiday', 'Boxing Day', '2020-12-26', '2020-12-27', '2025-04-24 01:06:17', '2025-04-24 01:06:17'),
(66, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Easter Sunday', '2021-04-04', '2021-04-05', '2025-04-24 01:06:17', '2025-04-24 01:06:17'),
(67, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Mother\'s Day', '2021-05-09', '2021-05-10', '2025-04-24 01:06:17', '2025-04-24 01:06:17'),
(68, 'Public holiday', 'Utamaduni Day', '2021-10-10', '2021-10-11', '2025-04-24 01:06:17', '2025-04-24 01:06:17'),
(69, 'Public holiday', 'Jamhuri Day observed', '2021-12-13', '2021-12-14', '2025-04-24 01:06:17', '2025-04-24 01:06:17'),
(70, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Christmas Eve', '2021-12-24', '2021-12-25', '2025-04-24 01:06:18', '2025-04-24 01:06:18'),
(71, 'Public holiday', 'Good Friday', '2022-04-15', '2022-04-16', '2025-04-24 01:06:18', '2025-04-24 01:06:18'),
(72, 'Public holiday', 'Easter Monday', '2022-04-18', '2022-04-19', '2025-04-24 01:06:18', '2025-04-24 01:06:18'),
(73, 'Public holiday', 'Eid al-Adha', '2022-07-10', '2022-07-11', '2025-04-24 01:06:18', '2025-04-24 01:06:18'),
(74, 'Public holiday', 'Utamaduni Day', '2022-10-10', '2022-10-11', '2025-04-24 01:06:18', '2025-04-24 01:06:18'),
(75, 'Public holiday', 'New Year\'s Day', '2023-01-01', '2023-01-02', '2025-04-24 01:06:18', '2025-04-24 01:06:18'),
(76, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Ramadan Start', '2023-03-23', '2023-03-24', '2025-04-24 01:06:18', '2025-04-24 01:06:18'),
(77, 'Public holiday', 'Mashujaa Day', '2023-10-20', '2023-10-21', '2025-04-24 01:06:18', '2025-04-24 01:06:18'),
(78, 'Public holiday', 'Christmas Day', '2023-12-25', '2023-12-26', '2025-04-24 01:06:18', '2025-04-24 01:06:18'),
(79, 'Public holiday', 'New Year\'s Day', '2024-01-01', '2024-01-02', '2025-04-24 01:06:19', '2025-04-24 01:06:19'),
(80, 'Public holiday', 'Good Friday', '2024-03-29', '2024-03-30', '2025-04-24 01:06:19', '2025-04-24 01:06:19'),
(81, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Mother\'s Day', '2024-05-12', '2024-05-13', '2025-04-24 01:06:19', '2025-04-24 01:06:19'),
(82, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Diwali', '2024-11-01', '2024-11-02', '2025-04-24 01:06:19', '2025-04-24 01:06:19'),
(83, 'Public holiday', 'Jamhuri Day', '2024-12-12', '2024-12-13', '2025-04-24 01:06:19', '2025-04-24 01:06:19'),
(84, 'Public holiday', 'Good Friday', '2025-04-18', '2025-04-19', '2025-04-24 01:06:19', '2025-04-24 01:06:19'),
(85, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Mother\'s Day', '2025-05-11', '2025-05-12', '2025-04-24 01:06:19', '2025-04-24 01:06:19'),
(86, 'Public holiday', 'Madaraka Day', '2025-06-01', '2025-06-02', '2025-04-24 01:06:19', '2025-04-24 01:06:19'),
(87, 'Public holiday', 'Mazingira Day', '2025-10-10', '2025-10-11', '2025-04-24 01:06:20', '2025-04-24 01:06:20'),
(88, 'Public holiday', 'Jamhuri Day', '2025-12-12', '2025-12-13', '2025-04-24 01:06:20', '2025-04-24 01:06:20'),
(89, 'Public holiday', 'Boxing Day', '2026-12-26', '2026-12-27', '2025-04-24 01:06:20', '2025-04-24 01:06:20'),
(90, 'Public holiday', 'Boxing Day', '2027-12-26', '2027-12-27', '2025-04-24 01:06:20', '2025-04-24 01:06:20'),
(91, 'Public holiday', 'Day off for Boxing Day', '2027-12-27', '2027-12-28', '2025-04-24 01:06:20', '2025-04-24 01:06:20'),
(92, 'Public holiday', 'Easter Monday', '2028-04-17', '2028-04-18', '2025-04-24 01:06:20', '2025-04-24 01:06:20'),
(93, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Mother\'s Day', '2028-05-14', '2028-05-15', '2025-04-24 01:06:20', '2025-04-24 01:06:20'),
(94, 'Public holiday', 'Madaraka Day', '2028-06-01', '2028-06-02', '2025-04-24 01:06:20', '2025-04-24 01:06:20'),
(95, 'Public holiday', 'Mazingira Day', '2028-10-10', '2028-10-11', '2025-04-24 01:06:20', '2025-04-24 01:06:20'),
(96, 'Public holiday', 'Mashujaa Day', '2028-10-20', '2028-10-21', '2025-04-24 01:06:20', '2025-04-24 01:06:20'),
(97, 'Public holiday', 'Christmas Day', '2028-12-25', '2028-12-26', '2025-04-24 01:06:20', '2025-04-24 01:06:20'),
(98, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Mother\'s Day', '2029-05-13', '2029-05-14', '2025-04-24 01:06:21', '2025-04-24 01:06:21'),
(99, 'Public holiday', 'Mazingira Day', '2029-10-10', '2029-10-11', '2025-04-24 01:06:21', '2025-04-24 01:06:21'),
(100, 'Public holiday', 'Christmas Day', '2029-12-25', '2029-12-26', '2025-04-24 01:06:21', '2025-04-24 01:06:21'),
(101, 'Public holiday', 'Good Friday', '2020-04-10', '2020-04-11', '2025-04-24 01:06:21', '2025-04-24 01:06:21'),
(102, 'Public holiday', 'Madaraka Day', '2020-06-01', '2020-06-02', '2025-04-24 01:06:21', '2025-04-24 01:06:21'),
(103, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Eid al-Adha', '2020-07-31', '2020-08-01', '2025-04-24 01:06:21', '2025-04-24 01:06:21'),
(104, 'Public holiday', 'Huduma Day', '2020-10-10', '2020-10-11', '2025-04-24 01:06:21', '2025-04-24 01:06:21'),
(105, 'Public holiday', 'Mashujaa Day', '2020-10-20', '2020-10-21', '2025-04-24 01:06:21', '2025-04-24 01:06:21'),
(106, 'Public holiday', 'Christmas Day', '2020-12-25', '2020-12-26', '2025-04-24 01:06:21', '2025-04-24 01:06:21'),
(107, 'Public holiday', 'New Year\'s Day', '2021-01-01', '2021-01-02', '2025-04-24 01:06:21', '2025-04-24 01:06:21'),
(108, 'Public holiday', 'Idd ul-Fitr', '2021-05-14', '2021-05-15', '2025-04-24 01:06:21', '2025-04-24 01:06:21'),
(109, 'Public holiday', 'Madaraka Day', '2021-06-01', '2021-06-02', '2025-04-24 01:06:21', '2025-04-24 01:06:21'),
(110, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Diwali', '2021-11-04', '2021-11-05', '2025-04-24 01:06:21', '2025-04-24 01:06:21'),
(111, 'Public holiday', 'State Funeral for Former President Mwai Kibaki', '2022-04-29', '2022-04-30', '2025-04-24 01:06:21', '2025-04-24 01:06:21'),
(112, 'Public holiday', 'Idd ul-Fitr Holiday', '2022-05-03', '2022-05-04', '2025-04-24 01:06:21', '2025-04-24 01:06:21'),
(113, 'Public holiday', 'Day off for Eid al-Adha', '2022-07-11', '2022-07-12', '2025-04-24 01:06:21', '2025-04-24 01:06:21'),
(114, 'Public holiday', 'Day of Mourning for Queen Elizabeth II', '2022-09-11', '2022-09-12', '2025-04-24 01:06:21', '2025-04-24 01:06:21'),
(115, 'Public holiday', 'Huduma Day', '2022-10-10', '2022-10-11', '2025-04-24 01:06:22', '2025-04-24 01:06:22'),
(116, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Christmas Eve', '2022-12-24', '2022-12-25', '2025-04-24 01:06:22', '2025-04-24 01:06:22'),
(117, 'Public holiday', 'Boxing Day', '2022-12-26', '2022-12-27', '2025-04-24 01:06:22', '2025-04-24 01:06:22'),
(118, 'Public holiday', 'Day off for Christmas Day', '2022-12-27', '2022-12-28', '2025-04-24 01:06:22', '2025-04-24 01:06:22'),
(119, 'Public holiday', 'New Year\'s Day observed', '2023-01-02', '2023-01-03', '2025-04-24 01:06:22', '2025-04-24 01:06:22'),
(120, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Easter Sunday', '2023-04-09', '2023-04-10', '2025-04-24 01:06:22', '2025-04-24 01:06:22'),
(121, 'Public holiday', 'Easter Monday', '2023-04-10', '2023-04-11', '2025-04-24 01:06:22', '2025-04-24 01:06:22'),
(122, 'Public holiday', 'Idd ul-Fitr', '2023-04-21', '2023-04-22', '2025-04-24 01:06:22', '2025-04-24 01:06:22'),
(123, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Mother\'s Day', '2023-05-14', '2023-05-15', '2025-04-24 01:06:22', '2025-04-24 01:06:22'),
(124, 'Public holiday', 'Madaraka Day', '2023-06-01', '2023-06-02', '2025-04-24 01:06:22', '2025-04-24 01:06:22'),
(125, 'Public holiday', 'Boxing Day', '2023-12-26', '2023-12-27', '2025-04-24 01:06:22', '2025-04-24 01:06:22'),
(126, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'New Year\'s Eve', '2023-12-31', '2024-01-01', '2025-04-24 01:06:22', '2025-04-24 01:06:22'),
(127, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Ramadan Start', '2024-03-11', '2024-03-12', '2025-04-24 01:06:22', '2025-04-24 01:06:22'),
(128, 'Public holiday', 'Idd ul-Fitr', '2024-04-10', '2024-04-11', '2025-04-24 01:06:22', '2025-04-24 01:06:22'),
(129, 'Public holiday', 'Mashujaa Day observed', '2024-10-21', '2024-10-22', '2025-04-24 01:06:22', '2025-04-24 01:06:22'),
(130, 'Public holiday', 'Mashujaa Day', '2025-10-20', '2025-10-21', '2025-04-24 01:06:23', '2025-04-24 01:06:23'),
(131, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Christmas Eve', '2025-12-24', '2025-12-25', '2025-04-24 01:06:23', '2025-04-24 01:06:23'),
(132, 'Public holiday', 'Boxing Day', '2025-12-26', '2025-12-27', '2025-04-24 01:06:23', '2025-04-24 01:06:23'),
(133, 'Public holiday', 'Madaraka Day', '2026-06-01', '2026-06-02', '2025-04-24 01:06:23', '2025-04-24 01:06:23'),
(134, 'Public holiday', 'Mashujaa Day', '2026-10-20', '2026-10-21', '2025-04-24 01:06:23', '2025-04-24 01:06:23'),
(135, 'Public holiday', 'New Year\'s Day', '2027-01-01', '2027-01-02', '2025-04-24 01:06:23', '2025-04-24 01:06:23'),
(136, 'Public holiday', 'Good Friday', '2027-03-26', '2027-03-27', '2025-04-24 01:06:23', '2025-04-24 01:06:23'),
(137, 'Public holiday', 'Easter Monday', '2027-03-29', '2027-03-30', '2025-04-24 01:06:23', '2025-04-24 01:06:23'),
(138, 'Public holiday', 'Madaraka Day', '2027-06-01', '2027-06-02', '2025-04-24 01:06:23', '2025-04-24 01:06:23'),
(139, 'Public holiday', 'Jamhuri Day', '2027-12-12', '2027-12-13', '2025-04-24 01:06:23', '2025-04-24 01:06:23'),
(140, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'New Year\'s Eve', '2027-12-31', '2028-01-01', '2025-04-24 01:06:23', '2025-04-24 01:06:23'),
(141, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Christmas Eve', '2028-12-24', '2028-12-25', '2025-04-24 01:06:24', '2025-04-24 01:06:24'),
(142, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'New Year\'s Eve', '2028-12-31', '2029-01-01', '2025-04-24 01:06:24', '2025-04-24 01:06:24'),
(143, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Christmas Eve', '2029-12-24', '2029-12-25', '2025-04-24 01:06:24', '2025-04-24 01:06:24'),
(144, 'Public holiday', 'Boxing Day', '2029-12-26', '2029-12-27', '2025-04-24 01:06:24', '2025-04-24 01:06:24'),
(145, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Easter Sunday', '2020-04-12', '2020-04-13', '2025-04-24 01:06:24', '2025-04-24 01:06:24'),
(146, 'Public holiday', 'Easter Monday', '2020-04-13', '2020-04-14', '2025-04-24 01:06:24', '2025-04-24 01:06:24'),
(147, 'Public holiday', 'Huduma Day', '2021-10-10', '2021-10-11', '2025-04-24 01:06:24', '2025-04-24 01:06:24'),
(148, 'Public holiday', 'Mashujaa Day', '2021-10-20', '2021-10-21', '2025-04-24 01:06:24', '2025-04-24 01:06:24'),
(149, 'Public holiday', 'Christmas Day', '2021-12-25', '2021-12-26', '2025-04-24 01:06:24', '2025-04-24 01:06:24'),
(150, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'New Year\'s Eve', '2021-12-31', '2022-01-01', '2025-04-24 01:06:24', '2025-04-24 01:06:24'),
(151, 'Public holiday', 'New Year\'s Day', '2022-01-01', '2022-01-02', '2025-04-24 01:06:24', '2025-04-24 01:06:24'),
(152, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Mother\'s Day', '2022-05-08', '2022-05-09', '2025-04-24 01:06:24', '2025-04-24 01:06:24'),
(153, 'Public holiday', 'Madaraka Day', '2022-06-01', '2022-06-02', '2025-04-24 01:06:25', '2025-04-24 01:06:25'),
(154, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Diwali', '2022-10-24', '2022-10-25', '2025-04-24 01:06:25', '2025-04-24 01:06:25'),
(155, 'Public holiday', 'Easter Monday', '2024-04-01', '2024-04-02', '2025-04-24 01:06:25', '2025-04-24 01:06:25'),
(156, 'Public holiday', 'Mazingira Day', '2024-10-10', '2024-10-11', '2025-04-24 01:06:25', '2025-04-24 01:06:25'),
(157, 'Public holiday', 'Mashujaa Day', '2024-10-20', '2024-10-21', '2025-04-24 01:06:25', '2025-04-24 01:06:25'),
(158, 'Public holiday', 'Christmas Day', '2024-12-25', '2024-12-26', '2025-04-24 01:06:25', '2025-04-24 01:06:25'),
(159, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Diwali', '2025-10-20', '2025-10-21', '2025-04-24 01:06:25', '2025-04-24 01:06:25'),
(160, 'Public holiday', 'Easter Monday', '2026-04-06', '2026-04-07', '2025-04-24 01:06:25', '2025-04-24 01:06:25'),
(161, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Mother\'s Day', '2026-05-10', '2026-05-11', '2025-04-24 01:06:25', '2025-04-24 01:06:25'),
(162, 'Public holiday', 'Mazingira Day', '2026-10-10', '2026-10-11', '2025-04-24 01:06:25', '2025-04-24 01:06:25'),
(163, 'Public holiday', 'Jamhuri Day', '2026-12-12', '2026-12-13', '2025-04-24 01:06:25', '2025-04-24 01:06:25'),
(164, 'Public holiday', 'Christmas Day', '2026-12-25', '2026-12-26', '2025-04-24 01:06:25', '2025-04-24 01:06:25'),
(165, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'New Year\'s Eve', '2026-12-31', '2027-01-01', '2025-04-24 01:06:25', '2025-04-24 01:06:25'),
(166, 'Public holiday', 'New Year\'s Day', '2028-01-01', '2028-01-02', '2025-04-24 01:06:25', '2025-04-24 01:06:25'),
(167, 'Public holiday', 'Good Friday', '2028-04-14', '2028-04-15', '2025-04-24 01:06:25', '2025-04-24 01:06:25'),
(168, 'Public holiday', 'Good Friday', '2029-03-30', '2029-03-31', '2025-04-24 01:06:25', '2025-04-24 01:06:25'),
(169, 'Public holiday', 'Madaraka Day', '2029-06-01', '2029-06-02', '2025-04-24 01:06:25', '2025-04-24 01:06:25'),
(170, 'Public holiday', 'Jamhuri Day', '2029-12-12', '2029-12-13', '2025-04-24 01:06:26', '2025-04-24 01:06:26'),
(171, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'New Year\'s Eve', '2029-12-31', '2030-01-01', '2025-04-24 01:06:26', '2025-04-24 01:06:26'),
(172, 'Public holiday', 'Labour Day', '2020-05-01', '2020-05-02', '2025-04-24 01:06:26', '2025-04-24 01:06:26'),
(173, 'Public holiday', 'Labour Day', '2021-05-01', '2021-05-02', '2025-04-24 01:06:26', '2025-04-24 01:06:26'),
(174, 'Public holiday', 'Labour Day', '2022-05-01', '2022-05-02', '2025-04-24 01:06:26', '2025-04-24 01:06:26'),
(175, 'Public holiday', 'Labour Day observed', '2022-05-02', '2022-05-03', '2025-04-24 01:06:26', '2025-04-24 01:06:26'),
(176, 'Public holiday', 'Labour Day', '2023-05-01', '2023-05-02', '2025-04-24 01:06:26', '2025-04-24 01:06:26'),
(177, 'Public holiday', 'Labour Day', '2024-05-01', '2024-05-02', '2025-04-24 01:06:26', '2025-04-24 01:06:26'),
(178, 'Public holiday', 'Labour Day', '2025-05-01', '2025-05-02', '2025-04-24 01:06:26', '2025-04-24 01:06:26'),
(179, 'Public holiday', 'Labour Day', '2026-05-01', '2026-05-02', '2025-04-24 01:06:26', '2025-04-24 01:06:26'),
(180, 'Public holiday', 'Labour Day', '2027-05-01', '2027-05-02', '2025-04-24 01:06:26', '2025-04-24 01:06:26'),
(181, 'Public holiday', 'Labour Day', '2028-05-01', '2028-05-02', '2025-04-24 01:06:26', '2025-04-24 01:06:26'),
(182, 'Public holiday', 'Labour Day', '2029-05-01', '2029-05-02', '2025-04-24 01:06:27', '2025-04-24 01:06:27'),
(183, 'Public holiday', 'Day off for Eid al-Adha', '2024-06-17', '2024-06-18', '2025-04-24 01:06:27', '2025-04-24 01:06:27'),
(184, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya\nDate is tentative and may change.', 'Eid al-Adha (tentative)', '2025-06-07', '2025-06-08', '2025-04-24 01:06:27', '2025-04-24 01:06:27'),
(185, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya\nDate is tentative and may change.', 'Ramadan Start (tentative)', '2026-02-18', '2026-02-19', '2025-04-24 01:06:27', '2025-04-24 01:06:27'),
(186, 'Public holiday\nDate is tentative and may change.', 'Idd ul-Fitr (tentative)', '2026-03-20', '2026-03-21', '2025-04-24 01:06:27', '2025-04-24 01:06:27'),
(187, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya\nDate is tentative and may change.', 'Eid al-Adha (tentative)', '2026-05-27', '2026-05-28', '2025-04-24 01:06:27', '2025-04-24 01:06:27'),
(188, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya\nDate is tentative and may change.', 'Ramadan Start (tentative)', '2027-02-08', '2027-02-09', '2025-04-24 01:06:27', '2025-04-24 01:06:27'),
(189, 'Public holiday\nDate is tentative and may change.', 'Idd ul-Fitr (tentative)', '2027-03-10', '2027-03-11', '2025-04-24 01:06:28', '2025-04-24 01:06:28'),
(190, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya\nDate is tentative and may change.', 'Eid al-Adha (tentative)', '2027-05-17', '2027-05-18', '2025-04-24 01:06:28', '2025-04-24 01:06:28'),
(191, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya\nDate is tentative and may change.', 'Ramadan Start (tentative)', '2028-01-28', '2028-01-29', '2025-04-24 01:06:28', '2025-04-24 01:06:28'),
(192, 'Public holiday\nDate is tentative and may change.', 'Idd ul-Fitr (tentative)', '2028-02-27', '2028-02-28', '2025-04-24 01:06:28', '2025-04-24 01:06:28'),
(193, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya\nDate is tentative and may change.', 'Eid al-Adha (tentative)', '2028-05-05', '2028-05-06', '2025-04-24 01:06:28', '2025-04-24 01:06:28'),
(194, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya\nDate is tentative and may change.', 'Ramadan Start (tentative)', '2029-01-16', '2029-01-17', '2025-04-24 01:06:28', '2025-04-24 01:06:28'),
(195, 'Public holiday\nDate is tentative and may change.', 'Idd ul-Fitr (tentative)', '2029-02-15', '2029-02-16', '2025-04-24 01:06:28', '2025-04-24 01:06:28'),
(196, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya\nDate is tentative and may change.', 'Eid al-Adha (tentative)', '2029-04-24', '2029-04-25', '2025-04-24 01:06:28', '2025-04-24 01:06:28'),
(197, 'Public holiday', 'Day off for Madaraka Day', '2025-06-02', '2025-06-03', '2025-04-24 01:06:28', '2025-04-24 01:06:28'),
(198, 'Public holiday', 'New Year\'s Day', '2030-01-01', '2030-01-02', '2025-04-24 01:06:28', '2025-04-24 01:06:28'),
(199, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya\nDate is tentative and may change.', 'Ramadan Start (tentative)', '2030-01-06', '2030-01-07', '2025-04-24 01:06:28', '2025-04-24 01:06:28'),
(200, 'Public holiday\nDate is tentative and may change.', 'Idd ul-Fitr (tentative)', '2030-02-05', '2030-02-06', '2025-04-24 01:06:29', '2025-04-24 01:06:29'),
(201, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya\nDate is tentative and may change.', 'Eid al-Adha (tentative)', '2030-04-14', '2030-04-15', '2025-04-24 01:06:29', '2025-04-24 01:06:29'),
(202, 'Public holiday', 'Good Friday', '2030-04-19', '2030-04-20', '2025-04-24 01:06:29', '2025-04-24 01:06:29'),
(203, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Easter Sunday', '2030-04-21', '2030-04-22', '2025-04-24 01:06:29', '2025-04-24 01:06:29'),
(204, 'Public holiday', 'Easter Monday', '2030-04-22', '2030-04-23', '2025-04-24 01:06:29', '2025-04-24 01:06:29'),
(205, 'Public holiday', 'Labour Day', '2030-05-01', '2030-05-02', '2025-04-24 01:06:29', '2025-04-24 01:06:29'),
(206, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Mother\'s Day', '2030-05-12', '2030-05-13', '2025-04-24 01:06:29', '2025-04-24 01:06:29'),
(207, 'Public holiday', 'Madaraka Day', '2030-06-01', '2030-06-02', '2025-04-24 01:06:29', '2025-04-24 01:06:29'),
(208, 'Public holiday', 'Mazingira Day', '2030-10-10', '2030-10-11', '2025-04-24 01:06:29', '2025-04-24 01:06:29'),
(209, 'Public holiday', 'Mashujaa Day', '2030-10-20', '2030-10-21', '2025-04-24 01:06:29', '2025-04-24 01:06:29'),
(210, 'Public holiday', 'Mashujaa Day observed', '2030-10-21', '2030-10-22', '2025-04-24 01:06:30', '2025-04-24 01:06:30'),
(211, 'Public holiday', 'Jamhuri Day', '2030-12-12', '2030-12-13', '2025-04-24 01:06:30', '2025-04-24 01:06:30'),
(212, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Christmas Eve', '2030-12-24', '2030-12-25', '2025-04-24 01:06:30', '2025-04-24 01:06:30'),
(213, 'Public holiday', 'Christmas Day', '2030-12-25', '2030-12-26', '2025-04-24 01:06:30', '2025-04-24 01:06:30'),
(214, 'Public holiday', 'Boxing Day', '2030-12-26', '2030-12-27', '2025-04-24 01:06:30', '2025-04-24 01:06:30'),
(215, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'New Year\'s Eve', '2030-12-31', '2031-01-01', '2025-04-24 01:06:30', '2025-04-24 01:06:30'),
(216, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya', 'Ramadan Start', '2025-03-01', '2025-03-02', '2025-04-24 01:06:30', '2025-04-24 01:06:30'),
(217, 'Public holiday', 'Eid al-Adha', '2024-06-16', '2024-06-17', '2025-04-24 01:06:30', '2025-04-24 01:06:30'),
(218, 'Public holiday', 'Idd ul-Fitr', '2025-03-31', '2025-04-01', '2025-04-24 01:06:30', '2025-04-24 01:06:30'),
(219, 'Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Kenya\nDate is tentative and may change.', 'Ramadan Start (tentative)', '2030-12-26', '2030-12-27', '2025-04-24 01:06:30', '2025-04-24 01:06:30');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=220;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
