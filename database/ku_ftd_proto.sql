-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 11, 2025 at 05:31 PM
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
-- Database: `ku_ftd_proto`
--

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `nontri_id` varchar(11) NOT NULL,
  `user_THname` varchar(100) NOT NULL,
  `user_THsur` varchar(100) NOT NULL,
  `user_ENname` varchar(100) DEFAULT NULL,
  `user_Ensur` varchar(100) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `user_type_id` int(2) NOT NULL,
  `fa_de_id` int(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`nontri_id`, `user_THname`, `user_THsur`, `user_ENname`, `user_Ensur`, `position`, `user_type_id`, `fa_de_id`) VALUES
('b6540200315', 'เกียรติสกุล', 'ไพยเสน', 'Kiatsakul', 'Paiyasen', NULL, 1, 2),
('b6540201149', 'ทวีศักดิ์', 'สีอังรัตน์', 'Thaweesak', 'Sriangrat', NULL, 1, 2),
('b6540201180', 'ทินวุฒิ', 'พลบำรุง', 'Thinnawut', 'Pholbumrung', NULL, 1, 2),
('b6540202410', 'พีระพงษ์', 'เทพประสิทธิ์', 'Peeraphong', 'Thepprasit', NULL, 1, 2),
('b6540202964', 'วัชรากร', 'เครือเนตร', 'Wacharakorn', 'Kruenet', NULL, 1, 2),
('t4340200197', 'ศิริพร', 'ทับทิม', 'Siriporn', 'Thubtim', NULL, 2, 2);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`nontri_id`),
  ADD KEY `user_type_foreign` (`user_type_id`),
  ADD KEY `fa_de_foreign` (`fa_de_id`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `user`
--
ALTER TABLE `user`
  ADD CONSTRAINT `fa_de_foreign` FOREIGN KEY (`fa_de_id`) REFERENCES `faculties_department` (`fa_de_id`),
  ADD CONSTRAINT `user_type_foreign` FOREIGN KEY (`user_type_id`) REFERENCES `user_type` (`user_type_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
