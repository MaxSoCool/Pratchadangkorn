-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 03, 2025 at 07:31 PM
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
-- Table structure for table `activity_type`
--

CREATE TABLE `activity_type` (
  `activity_type_id` int(2) NOT NULL,
  `activity_type_NAME` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_type`
--

INSERT INTO `activity_type` (`activity_type_id`, `activity_type_NAME`) VALUES
(1, 'การเรียนการสอน (นอกตาราง)'),
(2, 'กิจกรรมพิเศษ');

-- --------------------------------------------------------

--
-- Table structure for table `buildings`
--

CREATE TABLE `buildings` (
  `building_id` varchar(2) NOT NULL,
  `building_name` varchar(255) NOT NULL,
  `building_pic` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `buildings`
--

INSERT INTO `buildings` (`building_id`, `building_name`, `building_pic`) VALUES
('1', 'อาคารบริหาร', 'images/buildings/6853f0c538bd4.jpg'),
('10', 'โรงอาหารกลาง', 'images/buildings/6853f8c5ac540.jpg'),
('2', 'อาคารเรียนรวม', 'images/buildings/6853f0dc5836b.jpg'),
('3', 'อาคารชุดพักอาศัย', 'images/buildings/6853f0ef3f18f.jpg'),
('4', 'หอพักนิสิตชาย', 'images/buildings/6853f1062235d.jpg'),
('5', 'หอพักนิสิตหญิง', 'images/buildings/6853f11611608.jpg'),
('6', 'อาคารปฏิบัติการรวม', 'images/buildings/6853f1273f1ff.jpg'),
('7', 'อาคารวิทยาศาสตร์และเทคโนโลยี', 'images/buildings/6853f13aef515.jpg'),
('8', 'อาคารปฏิบัติการวิศวกรรมเครื่องกล/เทคโนโลยีอาหาร', 'images/buildings/6853f16679fe4.jpg'),
('9', 'อาคารเทคโนโลยีสารสนเทศ', 'images/buildings/6853f176bf7b5.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `equipments`
--

CREATE TABLE `equipments` (
  `equip_id` int(10) NOT NULL,
  `equip_name` varchar(100) NOT NULL,
  `quantity` int(3) NOT NULL,
  `measure` varchar(50) NOT NULL,
  `size` varchar(100) NOT NULL,
  `equip_pic` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipments`
--

INSERT INTO `equipments` (`equip_id`, `equip_name`, `quantity`, `measure`, `size`, `equip_pic`) VALUES
(1, 'โต๊ะพับจ้า', 100, 'ตัว', 'กว้าง 40 ซม. x ยาว 80 ซม. x สูง 110 ซม.', 'images/equipments/6853f971b335e.jpg'),
(2, 'โต๊ะยาว', 100, 'ตัว', 'กว้าง 60 ซม. x ยาว 140 ซม. x สูง 110 ซม.', 'images/equipments/6853fa59cf60c.jpg'),
(3, 'เก้าอี้พลาสติก', 100, 'ตัว', 'กว้าง 24 ซม. x สูง 80 ซม.', 'images/equipments/6853fb24068b3.jpg'),
(4, 'เก้าอี้ประชุม', 100, 'ตัว', 'กว้าง 24 ซม. x สูง 82 ซม.', 'images/equipments/6853fb3f0bc53.jpg'),
(5, 'โซฟา', 100, 'ตัว', 'กว้าง 60 ซม. x ยาว 150 ซม. x สูง 80 ซม.', 'images/equipments/6853fb794f721.jpg'),
(6, 'โต๊ะกระจกใส', 100, 'ตัว', 'กว้าง 30 ซม. x ยาว 80 ซม. x สูง 50 ซม.', 'images/equipments/6853fbcb1aaea.jpg'),
(7, 'คูลเลอร์น้ำ 22 ลิตร', 100, 'เครื่อง', 'กว้าง 22 ซม. x สูง 35.5 ซม', 'images/equipments/6853fc66abb59.jpg'),
(8, 'คูลเลอร์น้ำ 25.7 ลิตร', 100, 'เครื่อง', 'กว้าง 32 ซม. x สูง 44 ซม.', 'images/equipments/6853fc9cad37f.jpg'),
(9, 'พัดลม 18 นิ้ว', 100, 'เครื่อง', 'กว้าง 37.5 ซม. x ยาว 60.5 ซม. x สูง 110 ซม.', 'images/equipments/6853fce908ba9.jpg'),
(10, 'เสื่อ', 100, 'ผืน', 'กว้าง 90 ซม. x ยาว 180 ซม.', 'images/equipments/6853fd250d28a.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `equipments_requests`
--

CREATE TABLE `equipments_requests` (
  `equip_re_id` int(10) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` varchar(20) NOT NULL,
  `writed_status` varchar(20) NOT NULL DEFAULT 'ร่างคำร้องขอ',
  `agree` varchar(4) DEFAULT NULL,
  `transport` varchar(20) DEFAULT NULL,
  `request_date` timestamp(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `approve` varchar(20) DEFAULT NULL,
  `approve_date` date DEFAULT NULL,
  `quantity` int(3) NOT NULL,
  `approve_detail` varchar(255) DEFAULT NULL,
  `project_id` int(10) NOT NULL,
  `equip_id` int(10) NOT NULL,
  `facility_id` int(4) NOT NULL,
  `staff_id` varchar(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipments_requests`
--

INSERT INTO `equipments_requests` (`equip_re_id`, `start_date`, `end_date`, `status`, `writed_status`, `agree`, `transport`, `request_date`, `approve`, `approve_date`, `quantity`, `approve_detail`, `project_id`, `equip_id`, `facility_id`, `staff_id`) VALUES
(1, '2025-08-29', '2025-08-30', '', 'สิ้นสุดดำเนินการ', '1', '1', '2025-09-03 16:24:38.027778', 'อนุมัติ', '2025-08-25', 20, NULL, 5, 6, 7, 's5610003145'),
(2, '2025-08-10', '2025-08-11', '', 'สิ้นสุดดำเนินการ', '1', '1', '2025-08-21 10:36:15.628496', NULL, NULL, 100, NULL, 9, 8, 10, NULL),
(3, '2025-08-10', '2025-08-11', '', 'สิ้นสุดดำเนินการ', '1', '1', '2025-08-21 10:36:15.628496', NULL, NULL, 5, NULL, 9, 9, 10, NULL),
(5, '2025-08-15', '2025-08-15', '', 'สิ้นสุดดำเนินการ', '1', '1', '2025-08-21 10:36:15.628496', 'อนุมัติ', '2025-08-13', 10, NULL, 5, 5, 5, NULL),
(6, '2025-08-14', '2025-08-15', '', 'สิ้นสุดดำเนินการ', '1', '1', '2025-08-21 10:36:15.628496', 'อนุมัติ', '2025-08-13', 5, NULL, 5, 5, 3, NULL),
(8, '2025-08-14', '2025-08-14', '', 'สิ้นสุดดำเนินการ', '0', '1', '2025-08-21 10:36:15.628496', NULL, NULL, 10, NULL, 5, 6, 4, NULL),
(9, '2025-08-30', '2025-08-31', '', 'สิ้นสุดดำเนินการ', '0', '1', '2025-09-03 16:24:38.027778', NULL, NULL, 10, NULL, 5, 7, 5, NULL),
(10, '2025-08-12', '2025-08-14', '', 'สิ้นสุดดำเนินการ', '0', '0', '2025-08-21 10:36:15.628496', 'อนุมัติ', NULL, 10, NULL, 6, 6, 9, NULL),
(11, '2025-08-20', '2025-08-20', '', 'สิ้นสุดดำเนินการ', '1', '1', '2025-08-21 10:36:15.628496', 'ไม่อนุมัติ', '2025-08-13', 10, 'ของไม่พอ', 14, 7, 10, 's5610003145'),
(12, '2025-08-20', '2025-08-21', '', 'สิ้นสุดดำเนินการ', '1', '0', '2025-08-25 07:53:42.475422', NULL, NULL, 100, NULL, 15, 9, 9, NULL),
(13, '2025-09-01', '2025-09-01', '', 'สิ้นสุดดำเนินการ', '1', '1', '2025-09-03 16:24:38.027778', 'ไม่อนุมัติ', '2025-09-01', 3, 'อุปกรณ์หมด', 19, 8, 7, 's5610003145');

-- --------------------------------------------------------

--
-- Table structure for table `facilities`
--

CREATE TABLE `facilities` (
  `facility_id` int(4) NOT NULL,
  `facility_name` varchar(100) NOT NULL,
  `facility_des` varchar(255) NOT NULL,
  `facility_pic` varchar(255) NOT NULL,
  `building_id` varchar(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facilities`
--

INSERT INTO `facilities` (`facility_id`, `facility_name`, `facility_des`, `facility_pic`, `building_id`) VALUES
(1, 'พื้นที่บริเวณอาคาร 1', 'พื้นที่ใช้สอยบริเวณอาคาร 1 ทั้งหมด', 'images/facilities/6853f749e11f6.jpg', '1'),
(2, 'พื้นที่บริเวณอาคาร 2', 'พื้นที่ใช้สอยบริเวณอาคาร 2 ทั้งหมด', 'images/facilities/6853f78fd213f.jpg', '2'),
(3, 'พื้นที่บริเวณอาคาร 3', 'พื้นที่ใช้สอยบริเวณอาคาร 3 ทั้งหมด', 'images/facilities/6853f7abc394b.jpg', '3'),
(4, 'พื้นที่บริเวณหอพักนิสิตชาย', 'พื้นที่ใช้สอยบริเวณหอพักนิสิตชายทั้งหมด', 'images/facilities/6853f7cd45a56.jpg', '4'),
(5, 'พื้นที่บริเวณหอพักนิสิตหญิง', 'พื้นที่ใช้สอยบริเวณหอพักนิสิตหญิงทั้งหมด', 'images/facilities/6853f81412605.jpg', '5'),
(6, 'พื้นที่บริเวณอาคาร 6', 'พื้นที่ใช้สอยบริเวณอาคาร 6 ทั้งหมด', 'images/facilities/6853f82942b52.jpg', '6'),
(7, 'พื้นที่บริเวณอาคาร 7', 'พื้นที่ใช้สอยบริเวณอาคาร 7 ทั้งหมด', 'images/facilities/6853f83ab919f.jpg', '7'),
(8, 'พื้นที่บริเวณอาคาร 8', 'พื้นที่ใช้สอยบริเวณอาคาร 8 ทั้งหมด', 'images/facilities/6853f85ccacd8.jpg', '8'),
(9, 'พื้นที่บริเวณอาคาร 9', 'พื้นที่ใช้สอยบริเวณอาคาร 9 ทั้งหมด', 'images/facilities/6853f86e87333.jpg', '9'),
(10, 'พื้นที่บริเวณโรงอาหารกลาง', 'พื้นที่ใช้สอยบริเวณโรงอาหารกลางทั้งหมด', 'images/facilities/6853f8e4480e9.jpg', '10'),
(11, 'ห้องประชุมสัมมนาเฟื่องฟ้า', 'ห้องประชุมขนาดใหญ่ 100 ที่นั่ง อีกทั้งยังเป็นห้องเรียนระดับปริญญาโทและปริญญาเอกของวิทยาเขต', 'images/facilities/68b5c0d4a6413.jpg', '1');

-- --------------------------------------------------------

--
-- Table structure for table `facilities_requests`
--

CREATE TABLE `facilities_requests` (
  `facility_re_id` int(10) NOT NULL,
  `prepare_start_time` time NOT NULL,
  `prepare_end_time` time NOT NULL,
  `prepare_start_date` date NOT NULL,
  `prepare_end_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` varchar(20) NOT NULL,
  `writed_status` varchar(20) NOT NULL DEFAULT 'ร่างคำร้องขอ',
  `agree` varchar(20) DEFAULT NULL,
  `request_date` timestamp(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `approve` varchar(20) DEFAULT NULL,
  `approve_date` date DEFAULT NULL,
  `approve_detail` varchar(20) DEFAULT NULL,
  `project_id` int(10) NOT NULL,
  `facility_id` int(4) NOT NULL,
  `staff_id` varchar(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facilities_requests`
--

INSERT INTO `facilities_requests` (`facility_re_id`, `prepare_start_time`, `prepare_end_time`, `prepare_start_date`, `prepare_end_date`, `start_time`, `end_time`, `start_date`, `end_date`, `status`, `writed_status`, `agree`, `request_date`, `approve`, `approve_date`, `approve_detail`, `project_id`, `facility_id`, `staff_id`) VALUES
(3, '10:30:00', '17:00:00', '2025-07-29', '2025-07-30', '17:00:00', '20:00:00', '2025-07-30', '0000-00-00', '', 'สิ้นสุดดำเนินการ', '1', '2025-08-13 08:55:06.494297', NULL, NULL, NULL, 5, 4, NULL),
(5, '09:00:00', '17:00:00', '2025-08-19', '2025-08-19', '09:00:00', '17:00:00', '2025-08-20', '2025-08-20', '', 'สิ้นสุดดำเนินการ', '1', '2025-08-21 10:36:15.597203', '', NULL, NULL, 5, 5, NULL),
(6, '08:00:00', '17:00:00', '2025-08-21', '2025-08-22', '09:00:00', '17:00:00', '2025-08-23', '2025-08-24', '', 'สิ้นสุดดำเนินการ', '1', '2025-08-25 07:53:42.434415', 'ไม่อนุมัติ', '2025-08-13', 'ไร้สาระเกินไป', 5, 8, 's5610003145'),
(9, '09:00:00', '17:00:00', '2025-08-09', '2025-08-09', '09:00:00', '20:00:00', '2025-08-10', '2025-08-11', '', 'สิ้นสุดดำเนินการ', '1', '2025-08-13 08:55:06.494297', NULL, NULL, NULL, 9, 9, NULL),
(10, '17:00:00', '20:00:00', '2025-08-09', '2025-08-09', '17:00:00', '20:00:00', '2025-08-10', '2025-08-11', '', 'สิ้นสุดดำเนินการ', '1', '2025-08-13 08:55:06.494297', '', NULL, NULL, 9, 10, NULL),
(13, '09:00:00', '17:00:00', '2025-08-14', '2025-08-14', '09:00:00', '17:00:00', '2025-08-15', '2025-08-16', '', 'สิ้นสุดดำเนินการ', '0', '2025-08-21 10:36:15.597203', NULL, NULL, NULL, 5, 5, NULL),
(14, '15:00:00', '20:00:00', '2025-08-20', '2025-08-20', '09:00:00', '17:00:00', '2025-08-21', '2025-08-22', '', 'สิ้นสุดดำเนินการ', '0', '2025-08-25 07:53:42.434415', NULL, NULL, NULL, 13, 5, NULL),
(15, '09:00:00', '20:00:00', '2025-09-01', '2025-09-01', '12:00:00', '20:00:00', '2025-09-12', '2025-09-13', '', 'ร่างคำร้องขอ', '0', '2025-08-12 11:13:44.017834', NULL, NULL, NULL, 13, 2, NULL),
(16, '12:00:00', '17:00:00', '2025-12-24', '2025-12-24', '17:00:00', '20:00:00', '2025-12-30', '2025-12-30', '', 'ร่างคำร้องขอ', '0', '2025-08-13 05:12:57.325501', NULL, NULL, NULL, 11, 10, NULL),
(17, '09:00:00', '17:32:00', '2025-09-20', '2025-09-20', '17:33:00', '20:00:00', '2025-09-21', '2025-09-22', '', 'ส่งคำร้องขอ', '0', '2025-08-12 10:33:21.000000', NULL, NULL, NULL, 13, 5, NULL),
(18, '09:00:00', '18:00:00', '2025-08-24', '2025-08-25', '09:00:00', '18:00:00', '2025-08-26', '2025-08-26', '', 'สิ้นสุดดำเนินการ', '0', '2025-08-31 09:51:29.880653', NULL, NULL, NULL, 13, 9, NULL),
(20, '16:11:00', '16:12:00', '2025-08-12', '2025-08-12', '16:12:00', '17:12:00', '2025-08-13', '2025-08-15', '', 'สิ้นสุดดำเนินการ', '0', '2025-08-21 10:36:15.597203', 'อนุมัติ', NULL, NULL, 5, 4, NULL),
(21, '09:00:00', '20:00:00', '2025-08-10', '2025-08-10', '09:00:00', '20:00:00', '2025-08-12', '2025-08-13', '', 'สิ้นสุดดำเนินการ', '0', '2025-08-21 10:36:15.597203', 'อนุมัติ', '2025-08-13', NULL, 9, 10, 's5610003145'),
(22, '15:00:00', '20:00:00', '2025-08-19', '2025-08-19', '12:00:00', '15:00:00', '2025-08-20', '2025-08-20', '', 'สิ้นสุดดำเนินการ', '0', '2025-08-21 10:36:15.597203', 'อนุมัติ', '2025-08-13', 'ขอให้สนุกนะจ๊ะ', 14, 10, 's5610003145'),
(23, '08:00:00', '12:00:00', '2025-08-20', '2025-08-20', '13:00:00', '17:00:00', '2025-08-20', '2025-08-20', '', 'สิ้นสุดดำเนินการ', '1', '2025-08-21 10:36:15.597203', 'อนุมัติ', '2025-08-14', 'ใช้ได้ถึง 20.00 ครับ', 15, 10, 's5610003145'),
(24, '09:00:00', '18:00:00', '2025-08-30', '2025-08-30', '12:00:00', '20:00:00', '2025-08-31', '2025-08-31', '', 'สิ้นสุดดำเนินการ', '0', '2025-09-01 07:34:42.668577', NULL, NULL, NULL, 17, 9, NULL),
(25, '12:00:00', '18:00:00', '2025-08-29', '2025-08-30', '12:00:00', '20:00:00', '2025-08-31', '2025-08-31', '', 'สิ้นสุดดำเนินการ', '0', '2025-09-01 07:34:42.668577', NULL, NULL, NULL, 17, 10, NULL),
(26, '09:00:00', '12:00:00', '2025-08-31', '2025-09-01', '13:00:00', '18:00:00', '2025-09-01', '2025-09-01', '', 'สิ้นสุดดำเนินการ', '0', '2025-09-03 15:02:36.330299', 'อนุมัติ', '2025-09-01', NULL, 19, 7, 's5610003145'),
(28, '09:00:00', '17:00:00', '2025-10-05', '2025-10-05', '17:00:00', '20:00:00', '2025-10-05', '2025-10-05', '', 'ยกเลิกคำร้องขอ', '0', '2025-09-03 16:37:49.436888', 'ยกเลิก', NULL, NULL, 21, 11, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `faculties_department`
--

CREATE TABLE `faculties_department` (
  `fa_de_id` int(2) NOT NULL,
  `fa_de_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faculties_department`
--

INSERT INTO `faculties_department` (`fa_de_id`, `fa_de_name`) VALUES
(1, 'ทรัพยากรธรรมชาติและอุตสาหกรรมเกษตร'),
(2, 'วิทยาศาสตร์และวิศวกรรมศาสตร์'),
(3, 'ศิลปกรรมศาสตร์และวิทยาการจัดการ'),
(4, 'สาธารณสุขศาสตร์');

-- --------------------------------------------------------

--
-- Table structure for table `project`
--

CREATE TABLE `project` (
  `project_id` int(10) NOT NULL,
  `project_name` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `project_des` varchar(255) NOT NULL,
  `files` varchar(255) DEFAULT NULL,
  `attendee` varchar(4) NOT NULL,
  `phone_num` varchar(10) NOT NULL,
  `advisor_name` varchar(255) DEFAULT NULL,
  `created_date` timestamp(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `writed_status` varchar(20) NOT NULL DEFAULT 'ร่างโครงการ',
  `nontri_id` varchar(11) NOT NULL,
  `activity_type_id` int(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project`
--

INSERT INTO `project` (`project_id`, `project_name`, `start_date`, `end_date`, `project_des`, `files`, `attendee`, `phone_num`, `advisor_name`, `created_date`, `writed_status`, `nontri_id`, `activity_type_id`) VALUES
(5, 'ทดสอบ', '2025-07-23', '2525-07-24', 'ฟหกฟหกหฟกฟหฟก', 'uploads/fi', '100', '0987654321', 'A.J. Jesus Christ', '2025-08-05 08:55:59.646405', 'เริ่มดำเนินการ', 'b6540201149', 1),
(6, 'wow', '2025-08-10', '2025-08-12', 'asdsadsadasdsadas', 'uploads/fi', '100', '0987654321', 'A.J. Juan', '2025-08-13 09:36:51.496328', 'สิ้นสุดโครงการ', 'b6540201149', 1),
(8, 'Deadby', '2025-08-05', '2025-08-06', 'jesus', 'uploads/fi', '1', '0987654321', 'อาจารย์ศิริพร ทับทิม', '2025-08-07 15:01:48.970335', 'สิ้นสุดโครงการ', 'b6540201180', 2),
(9, 'บายเนียร์', '2025-08-18', '2025-08-18', 'บายเนียร์พี่ปี 4', 'uploads/fi', '200', '0987654321', 'อาจารย์ศิริพร ทับทิม', '2025-08-21 09:28:59.145530', 'สิ้นสุดโครงการ', 'b6540201149', 2),
(11, 'ไหมไทย หัวใจเกินร้อย', '2025-08-20', '2025-08-21', 'เสียงอยู่ไสเสียง', 'uploads/files/689b05a3c5857.jpg', '1000', '0987654321', 'อาจารย์ไหม', '2025-08-25 06:41:49.322933', 'สิ้นสุดโครงการ', 'b6540201149', 2),
(12, 'ช่องเจ็ดสี', '2025-08-17', '2025-08-18', 'ทีวีเพื่อคุณ', 'uploads/files/689b1364b140c.png', '10', '0987654321', 'giuhuiihokhjkjhkjhkjhjkh', '2025-08-21 09:28:59.145530', 'สิ้นสุดโครงการ', 'b6540201180', 2),
(13, 'enzo เม็ดส้ม', '2025-08-17', '2025-08-18', 'asdasdsadasdasdas', 'uploads/files/689b138f4ed77.jpg', '10', '0987654321', 'asdasdasdasdsa', '2025-08-21 09:28:59.145530', 'สิ้นสุดโครงการ', 'b6540201180', 2),
(14, 'แข่งกินวิบาก', '2025-08-20', '2025-08-20', 'การแข่งขันกินจุ ใครจะเป็นเจ้าแห่งการกินจุประจำ มก.ฉกส', 'uploads/files/689ca788c0dfa.jpg', '100', '0987654321', 'ผศ.ศิริพร ทับทิม', '2025-08-21 09:28:59.145530', 'สิ้นสุดโครงการ', 'b6540201909', 2),
(15, 'แข่ง A-math', '2025-08-31', '2025-08-31', 'แข่ง A-math', 'uploads/files/689d54c3d653f.png', '100', '0987654321', 'ผศ.ศิริพร ทับทิม', '2025-09-01 07:07:05.685498', 'สิ้นสุดโครงการ', 'b6540201149', 2),
(16, 'adasdsa', '2025-08-20', '2025-08-21', 'asdsadasdsadas', 'uploads/files/689d57fb72a85.png', '100', '0987654321', 'asdsadsadsad', '2025-08-25 06:41:49.322933', 'สิ้นสุดโครงการ', 'b6540201149', 2),
(17, 'ประกวดร้องเพลง', '2025-08-31', '2025-08-31', 'การประกวดร้องเพลงสุดหรรษา', 'uploads/files/68ac1a8e10f87.jpg', '100', '0987654321', 'ผศ.ศิริพร ทับทิม', '2025-09-01 07:07:05.685498', 'สิ้นสุดโครงการ', 'b6540201149', 2),
(18, 'เก็บเอาไว้ในกายเธอ', '2025-08-26', '2025-08-27', 'asdsadsadsadsadsadsadsad', NULL, '100', '0987645321', 'asdsadadsadsa', '2025-08-31 09:51:29.811690', 'สิ้นสุดโครงการ', 'b6540201149', 1),
(19, 'โปรเจคตัวร้ายกับควายใส่แว่น', '2025-09-02', '2025-09-02', 'เมื่อไหร่จะหลุดพ้นเสียที', 'uploads/files/68acb44ca7152.jpg', '50', '0987654321', 'ผศ.ทินวุฒิ พลบำรุง', '2025-09-03 15:02:36.296003', 'สิ้นสุดโครงการ', 'b6540201909', 1),
(21, 'พระเจ้าจอร์จ มันยอดมาก', '2025-10-05', '2025-10-05', 'ว้าว', '', '100', '0912345678', 'ใครน้อ', '2025-09-03 16:37:49.437432', 'ยกเลิกโครงการ', 'b6540201149', 2),
(22, 'ดาว บ้านดอน', '2025-10-01', '2025-10-01', 'เป็นเหมือนดาว', NULL, '100', '0987654321', 'ดาว ขมิ้น', '2025-09-03 17:03:05.000000', 'ส่งโครงการ', 'b6540201149', 1),
(23, 'แม่เจ้า', '2025-09-06', '2025-10-07', 'จริงหรือนี่??', 'uploads/files/68b874f3cdd72.jpg', '100', '0987654321', 'asdasdsadsad', '2025-09-03 17:25:36.848397', 'ส่งโครงการ', 'b6540201149', 2);

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `staff_id` varchar(11) NOT NULL,
  `staff_THname` varchar(100) NOT NULL,
  `staff_THsur` varchar(100) NOT NULL,
  `staff_ENname` varchar(100) NOT NULL,
  `staff_ENsur` varchar(100) NOT NULL,
  `user_pass` varchar(100) NOT NULL,
  `user_type_id` int(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`staff_id`, `staff_THname`, `staff_THsur`, `staff_ENname`, `staff_ENsur`, `user_pass`, `user_type_id`) VALUES
('s5610003145', 'สมศักดิ์', 'รักสเมียณ', 'Somsuk', 'Raksameon', '12345678', 3);

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `nontri_id` varchar(11) NOT NULL,
  `user_THname` varchar(100) NOT NULL,
  `user_THsur` varchar(100) NOT NULL,
  `user_ENname` varchar(100) NOT NULL,
  `user_Ensur` varchar(100) NOT NULL,
  `user_pass` varchar(100) NOT NULL,
  `user_type_id` int(2) NOT NULL,
  `fa_de_id` int(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`nontri_id`, `user_THname`, `user_THsur`, `user_ENname`, `user_Ensur`, `user_pass`, `user_type_id`, `fa_de_id`) VALUES
('b6540200315', 'เกียรติสกุล', 'ไพยเสน', 'Kiatsakul', 'Paiyasen', '12345678', 1, 2),
('b6540201149', 'ทวีศักดิ์', 'สีอังรัตน์', 'Thaweesak', 'Sriangrat', '12345678', 1, 2),
('b6540201180', 'ทินวุฒิ', 'พลบำรุง', 'Thinnawut', 'Pholbumrung', '12345678', 1, 2),
('b6540201909', 'ปรัชชฎางค์กรณ์', 'แก้วมณีโชติ', 'Pratchadangkorn', 'Kaewmaneechot', '12345678', 1, 2),
('b6540202410', 'พีระพงษ์', 'เทพประสิทธิ์', 'Peeraphong', 'Thepprasit', '12345678', 1, 2),
('b6540202964', 'วัชรากร', 'เครือเนตร', 'Wacharakorn', 'Kruenet', '12345678', 1, 2),
('t4340200197', 'ศิริพร', 'ทับทิม', 'Siriporn', 'Thubtim', '12345678', 2, 2);

-- --------------------------------------------------------

--
-- Table structure for table `user_type`
--

CREATE TABLE `user_type` (
  `user_type_id` int(2) NOT NULL,
  `user_type_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_type`
--

INSERT INTO `user_type` (`user_type_id`, `user_type_name`) VALUES
(1, 'นิสิต'),
(2, 'อาจารย์และบุคลากร'),
(3, 'เจ้าหน้าที่');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_type`
--
ALTER TABLE `activity_type`
  ADD PRIMARY KEY (`activity_type_id`);

--
-- Indexes for table `buildings`
--
ALTER TABLE `buildings`
  ADD PRIMARY KEY (`building_id`);

--
-- Indexes for table `equipments`
--
ALTER TABLE `equipments`
  ADD PRIMARY KEY (`equip_id`);

--
-- Indexes for table `equipments_requests`
--
ALTER TABLE `equipments_requests`
  ADD PRIMARY KEY (`equip_re_id`,`project_id`,`equip_id`) USING BTREE,
  ADD KEY `fk_equip_2` (`equip_id`),
  ADD KEY `fk_project` (`project_id`),
  ADD KEY `fk_faci` (`facility_id`),
  ADD KEY `fk_staff_eqp` (`staff_id`);

--
-- Indexes for table `facilities`
--
ALTER TABLE `facilities`
  ADD PRIMARY KEY (`facility_id`),
  ADD KEY `building_fa_fk` (`building_id`);

--
-- Indexes for table `facilities_requests`
--
ALTER TABLE `facilities_requests`
  ADD PRIMARY KEY (`facility_re_id`,`project_id`,`facility_id`),
  ADD KEY `fk_facility_2` (`facility_id`),
  ADD KEY `fk_project_2` (`project_id`),
  ADD KEY `fk_staff_faci` (`staff_id`);

--
-- Indexes for table `faculties_department`
--
ALTER TABLE `faculties_department`
  ADD PRIMARY KEY (`fa_de_id`);

--
-- Indexes for table `project`
--
ALTER TABLE `project`
  ADD PRIMARY KEY (`project_id`),
  ADD KEY `fk_activity_type` (`activity_type_id`),
  ADD KEY `fk_nontri_id` (`nontri_id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`staff_id`),
  ADD KEY `fk_staff_user_type` (`user_type_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`nontri_id`),
  ADD KEY `user_type_foreign` (`user_type_id`),
  ADD KEY `fa_de_foreign` (`fa_de_id`);

--
-- Indexes for table `user_type`
--
ALTER TABLE `user_type`
  ADD PRIMARY KEY (`user_type_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_type`
--
ALTER TABLE `activity_type`
  MODIFY `activity_type_id` int(2) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `equipments`
--
ALTER TABLE `equipments`
  MODIFY `equip_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `equipments_requests`
--
ALTER TABLE `equipments_requests`
  MODIFY `equip_re_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `facilities`
--
ALTER TABLE `facilities`
  MODIFY `facility_id` int(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `facilities_requests`
--
ALTER TABLE `facilities_requests`
  MODIFY `facility_re_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `faculties_department`
--
ALTER TABLE `faculties_department`
  MODIFY `fa_de_id` int(2) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `project`
--
ALTER TABLE `project`
  MODIFY `project_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `user_type`
--
ALTER TABLE `user_type`
  MODIFY `user_type_id` int(2) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `equipments_requests`
--
ALTER TABLE `equipments_requests`
  ADD CONSTRAINT `fk_equip_2` FOREIGN KEY (`equip_id`) REFERENCES `equipments` (`equip_id`),
  ADD CONSTRAINT `fk_faci` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`facility_id`),
  ADD CONSTRAINT `fk_project` FOREIGN KEY (`project_id`) REFERENCES `project` (`project_id`),
  ADD CONSTRAINT `fk_staff_eqp` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`);

--
-- Constraints for table `facilities`
--
ALTER TABLE `facilities`
  ADD CONSTRAINT `building_fa_fk` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`building_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `facilities_requests`
--
ALTER TABLE `facilities_requests`
  ADD CONSTRAINT `fk_facility_2` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`facility_id`),
  ADD CONSTRAINT `fk_project_2` FOREIGN KEY (`project_id`) REFERENCES `project` (`project_id`),
  ADD CONSTRAINT `fk_staff_faci` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`);

--
-- Constraints for table `project`
--
ALTER TABLE `project`
  ADD CONSTRAINT `fk_activity_type` FOREIGN KEY (`activity_type_id`) REFERENCES `activity_type` (`activity_type_id`),
  ADD CONSTRAINT `fk_nontri_id` FOREIGN KEY (`nontri_id`) REFERENCES `user` (`nontri_id`);

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `fk_staff_user_type` FOREIGN KEY (`user_type_id`) REFERENCES `user_type` (`user_type_id`);

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
