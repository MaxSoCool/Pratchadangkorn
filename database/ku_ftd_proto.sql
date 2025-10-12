-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 12, 2025 at 07:04 PM
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
  `building_pic` varchar(255) NOT NULL,
  `available` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `buildings`
--

INSERT INTO `buildings` (`building_id`, `building_name`, `building_pic`, `available`) VALUES
('1', 'อาคารบริหาร', 'images/buildings/6853f0c538bd4.jpg', 'yes'),
('10', 'โรงอาหารกลาง', 'images/buildings/6853f8c5ac540.jpg', 'yes'),
('11', 'โรงกรองน้ำและหอวิทยาเขต', 'images/buildings/68e21cc287757.jpg', 'yes'),
('12', 'อาคารสถามกีฬา', 'images/buildings/68e21ee8ee868.jpg', 'yes'),
('13', 'พิพิธภัณฑ์องค์ความรู้', 'images/buildings/68e21f4ea66d1.jpg', 'yes'),
('14', 'อาคารวิทยาเขตเฉลิมพระเกียรติ', 'images/buildings/68e21f6a4f71f.jpg', 'yes'),
('15', 'อาคารถิ่นมั่นในพุทธธรรม', 'images/buildings/68e21fac2dc7b.jpg', 'yes'),
('16', 'อาคารสถานพยาบาล', 'images/buildings/68e21fca6a97c.jpg', 'yes'),
('17', 'อาคารชุดพักอาศัยบุคลากร 2', 'images/buildings/68e22039cda06.jpg', 'yes'),
('18', 'หอพักนิสิตหญิงตาลฟ้า', 'images/buildings/68e2202f2a1a7.jpg', 'no'),
('19', 'อาคารปฏิบัติการด้านวิทยาศาสตร์การกีฬา', 'images/buildings/68e2208ea9298.jpg', 'yes'),
('2', 'อาคารเรียนรวม', 'images/buildings/6853f0dc5836b.jpg', 'yes'),
('20', 'อาคารปฏิบัติการด้านวิทยาศาสตร์สุขภาพ', 'images/buildings/68e225c596489.jpg', 'yes'),
('21', 'อาคารปฏิบัติการด้านทรัพยากรสิ่งแวดล้อม', 'images/buildings/68e271cf25f7b.jpg', 'yes'),
('3', 'อาคารชุดพักอาศัย', 'images/buildings/6853f0ef3f18f.jpg', 'no'),
('4', 'หอพักนิสิตชาย', 'images/buildings/6853f1062235d.jpg', 'yes'),
('5', 'หอพักนิสิตหญิง', 'images/buildings/6853f11611608.jpg', 'yes'),
('6', 'อาคารปฏิบัติการรวม', 'images/buildings/6853f1273f1ff.jpg', 'yes'),
('7', 'อาคารวิทยาศาสตร์และเทคโนโลยี', 'images/buildings/6853f13aef515.jpg', 'yes'),
('8', 'อาคารปฏิบัติการวิศวกรรมเครื่องกล/เทคโนโลยีอาหาร', 'images/buildings/6853f16679fe4.jpg', 'yes'),
('9', 'อาคารเทคโนโลยีสารสนเทศ', 'images/buildings/6853f176bf7b5.jpg', 'yes');

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
  `equip_pic` varchar(255) NOT NULL,
  `available` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipments`
--

INSERT INTO `equipments` (`equip_id`, `equip_name`, `quantity`, `measure`, `size`, `equip_pic`, `available`) VALUES
(1, 'โต๊ะพับ', 100, 'ตัว', 'กว้าง 40 ซม. x ยาว 80 ซม. x สูง 110 ซม.', 'images/equipments/6853f971b335e.jpg', 'yes'),
(2, 'โต๊ะยาว', 100, 'ตัว', 'กว้าง 60 ซม. x ยาว 140 ซม. x สูง 110 ซม.', 'images/equipments/6853fa59cf60c.jpg', 'yes'),
(3, 'เก้าอี้พลาสติก', 100, 'ตัว', 'กว้าง 24 ซม. x สูง 80 ซม.', 'images/equipments/6853fb24068b3.jpg', 'yes'),
(4, 'เก้าอี้ประชุม', 100, 'ตัว', 'กว้าง 24 ซม. x สูง 82 ซม.', 'images/equipments/6853fb3f0bc53.jpg', 'yes'),
(5, 'โซฟา', 100, 'ตัว', 'กว้าง 60 ซม. x ยาว 150 ซม. x สูง 80 ซม.', 'images/equipments/6853fb794f721.jpg', 'yes'),
(6, 'โต๊ะกระจกใส', 100, 'ตัว', 'กว้าง 30 ซม. x ยาว 80 ซม. x สูง 50 ซม.', 'images/equipments/6853fbcb1aaea.jpg', 'yes'),
(7, 'คูลเลอร์น้ำ 22 ลิตร', 100, 'เครื่อง', 'กว้าง 22 ซม. x สูง 35.5 ซม', 'images/equipments/6853fc66abb59.jpg', 'yes'),
(8, 'คูลเลอร์น้ำ 25.7 ลิตร', 100, 'เครื่อง', 'กว้าง 32 ซม. x สูง 44 ซม.', 'images/equipments/6853fc9cad37f.jpg', 'yes'),
(9, 'พัดลม 18 นิ้ว', 100, 'เครื่อง', 'กว้าง 37.5 ซม. x ยาว 60.5 ซม. x สูง 110 ซม.', 'images/equipments/6853fce908ba9.jpg', 'yes'),
(10, 'เสื่อ', 100, 'ผืน', 'กว้าง 90 ซม. x ยาว 180 ซม.', 'images/equipments/6853fd250d28a.jpg', 'yes');

-- --------------------------------------------------------

--
-- Table structure for table `equipments_requests`
--

CREATE TABLE `equipments_requests` (
  `equip_re_id` int(10) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
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

INSERT INTO `equipments_requests` (`equip_re_id`, `start_date`, `end_date`, `writed_status`, `agree`, `transport`, `request_date`, `approve`, `approve_date`, `quantity`, `approve_detail`, `project_id`, `equip_id`, `facility_id`, `staff_id`) VALUES
(14, '2025-10-14', '2025-10-14', 'ส่งคำร้องขอ', '1', '1', '2025-10-05 15:44:10.000000', NULL, NULL, 3, NULL, 27, 8, 9, NULL),
(15, '2025-10-14', '2025-10-14', 'ส่งคำร้องขอ', '1', '1', '2025-10-07 15:00:19.767245', 'อนุมัติ', '2025-10-07', 2, NULL, 29, 7, 12, 's5610003145'),
(16, '2025-10-19', '2025-10-20', 'ส่งคำร้องขอ', '0', '1', '2025-10-12 11:15:24.248189', 'อนุมัติ', '2025-10-12', 20, NULL, 33, 2, 12, 's5610003145'),
(17, '2025-10-18', '2025-10-19', 'ส่งคำร้องขอ', '0', '1', '2025-10-10 07:05:45.000000', NULL, NULL, 5, NULL, 30, 9, 9, NULL),
(18, '2025-10-18', '2025-10-19', 'ส่งคำร้องขอ', '0', '1', '2025-10-10 07:06:16.000000', NULL, NULL, 50, NULL, 30, 10, 9, NULL),
(19, '2025-10-30', '2025-10-31', 'ส่งคำร้องขอ', '0', '0', '2025-10-10 07:53:23.867130', NULL, NULL, 5, NULL, 34, 6, 19, NULL),
(20, '2025-10-30', '2025-10-31', 'ส่งคำร้องขอ', '0', '0', '2025-10-10 07:53:04.240982', NULL, NULL, 2, NULL, 34, 6, 19, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `facilities`
--

CREATE TABLE `facilities` (
  `facility_id` int(4) NOT NULL,
  `facility_name` varchar(100) NOT NULL,
  `facility_des` varchar(255) NOT NULL,
  `facility_pic` varchar(255) NOT NULL,
  `building_id` varchar(2) NOT NULL,
  `available` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facilities`
--

INSERT INTO `facilities` (`facility_id`, `facility_name`, `facility_des`, `facility_pic`, `building_id`, `available`) VALUES
(1, 'พื้นที่บริเวณอาคาร 1', 'พื้นที่ใช้สอยบริเวณอาคาร 1 ทั้งหมดจ้า', 'images/facilities/6853f749e11f6.jpg', '1', 'yes'),
(2, 'พื้นที่บริเวณอาคาร 2', 'พื้นที่ใช้สอยบริเวณอาคาร 2 ทั้งหมด', 'images/facilities/6853f78fd213f.jpg', '2', ''),
(3, 'พื้นที่บริเวณอาคาร 3', 'พื้นที่ใช้สอยบริเวณอาคาร 3 ทั้งหมด', 'images/facilities/6853f7abc394b.jpg', '3', ''),
(4, 'พื้นที่บริเวณหอพักนิสิตชาย', 'พื้นที่ใช้สอยบริเวณหอพักนิสิตชายทั้งหมด', 'images/facilities/6853f7cd45a56.jpg', '4', ''),
(5, 'พื้นที่บริเวณหอพักนิสิตหญิง', 'พื้นที่ใช้สอยบริเวณหอพักนิสิตหญิงทั้งหมด', 'images/facilities/6853f81412605.jpg', '5', ''),
(6, 'พื้นที่บริเวณอาคาร 6', 'พื้นที่ใช้สอยบริเวณอาคาร 6 ทั้งหมด', 'images/facilities/6853f82942b52.jpg', '6', ''),
(7, 'พื้นที่บริเวณอาคาร 7', 'พื้นที่ใช้สอยบริเวณอาคาร 7 ทั้งหมด', 'images/facilities/6853f83ab919f.jpg', '7', ''),
(8, 'พื้นที่บริเวณอาคาร 8', 'พื้นที่ใช้สอยบริเวณอาคาร 8 ทั้งหมด', 'images/facilities/6853f85ccacd8.jpg', '8', ''),
(9, 'พื้นที่บริเวณอาคาร 9', 'พื้นที่ใช้สอยบริเวณอาคาร 9 ทั้งหมด', 'images/facilities/6853f86e87333.jpg', '9', ''),
(10, 'พื้นที่บริเวณโรงอาหารกลาง', 'พื้นที่ใช้สอยบริเวณโรงอาหารกลางทั้งหมด', 'images/facilities/6853f8e4480e9.jpg', '10', ''),
(11, 'ห้องประชุมสัมมนาเฟื่องฟ้า', 'ห้องประชุมขนาดใหญ่ 100 ที่นั่ง อีกทั้งยังเป็นห้องเรียนระดับปริญญาโทและปริญญาเอกของวิทยาเขต', 'images/facilities/68b5c0d4a6413.jpg', '1', '0'),
(12, 'โถงไทสกล', 'ห้องโถงที่ทอดยาวระหว่างชั้นที่ 1 มีพื้นที่ใช้สอยมากมายสำหรับจัดกิจกรรมต่าง ๆ ได้', 'images/facilities/68e2398d9cd83.jpg', '7', 'yes'),
(18, 'พื้นที่บริเวณอาคาร 14', 'พื้นที่บริเวณอาคาร 14 ทั้งหมด', 'images/facilities/68e8acc812f9c.jpg', '14', '0'),
(19, 'หอประชุมวิโรจน์อิ่มพิทักษ์', 'หอประชุมขนาดใหญ่ รองรับผู้เข้าร่วมประชุมได้ 3,000 คน ใช้ประโยชน์ด้านการประชุมสัมมนา จัดกิจกรรมสำคัญ ๆ อาทิ การอบรมพัฒนาบัณฑิตยุคใหม่ การไหว้ครู การจัดแสดงนิทรรศการ พิธีในโอกาสสำคัญๆ ของมหาวิทยาลัย', 'images/facilities/68e8ad033b282.jpg', '14', 'yes');

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
  `writed_status` varchar(20) NOT NULL DEFAULT 'ร่างคำร้องขอ',
  `agree` varchar(20) DEFAULT NULL,
  `request_date` timestamp(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `approve` varchar(20) DEFAULT NULL,
  `approve_date` date DEFAULT NULL,
  `approve_detail` varchar(255) DEFAULT NULL,
  `project_id` int(10) NOT NULL,
  `facility_id` int(4) NOT NULL,
  `staff_id` varchar(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facilities_requests`
--

INSERT INTO `facilities_requests` (`facility_re_id`, `prepare_start_time`, `prepare_end_time`, `prepare_start_date`, `prepare_end_date`, `start_time`, `end_time`, `start_date`, `end_date`, `writed_status`, `agree`, `request_date`, `approve`, `approve_date`, `approve_detail`, `project_id`, `facility_id`, `staff_id`) VALUES
(21, '09:00:00', '20:00:00', '2025-08-10', '2025-08-10', '09:00:00', '20:00:00', '2025-08-12', '2025-08-13', 'สิ้นสุดดำเนินการ', '0', '2025-08-21 10:36:15.597203', 'อนุมัติ', '2025-08-13', NULL, 9, 10, 's5610003145'),
(23, '08:00:00', '12:00:00', '2025-08-20', '2025-08-20', '13:00:00', '17:00:00', '2025-08-20', '2025-08-20', 'สิ้นสุดดำเนินการ', '1', '2025-08-21 10:36:15.597203', 'อนุมัติ', '2025-08-14', 'ใช้ได้ถึง 20.00 ครับ', 15, 10, 's5610003145'),
(29, '09:00:00', '16:00:00', '2025-10-01', '2025-10-01', '17:00:00', '20:00:00', '2025-10-01', '2025-10-01', 'สิ้นสุดดำเนินการ', '0', '2025-10-02 07:33:33.016815', 'อนุมัติ', '2025-09-24', NULL, 24, 9, 's5610003145'),
(30, '09:00:00', '12:00:00', '2025-10-04', '2025-10-04', '13:00:00', '17:00:00', '2025-10-04', '2025-10-05', 'ยกเลิกคำร้องขอ', '0', '2025-09-24 14:49:47.795625', 'ยกเลิก', NULL, NULL, 26, 4, NULL),
(31, '09:00:00', '16:00:00', '2025-12-14', '2025-12-14', '09:00:00', '16:00:00', '2025-12-15', '2025-12-15', 'ส่งคำร้องขอ', '0', '2025-10-10 07:56:03.262766', NULL, NULL, NULL, 27, 9, NULL),
(32, '09:00:00', '16:00:00', '2025-12-14', '2025-12-14', '09:00:00', '16:00:00', '2025-12-15', '2025-12-15', 'ส่งคำร้องขอ', '0', '2025-10-10 07:56:32.731626', NULL, NULL, NULL, 27, 10, NULL),
(33, '09:00:00', '16:00:00', '2025-12-14', '2025-12-14', '09:00:00', '16:00:00', '2025-12-15', '2025-12-15', 'ไม่อนุมัติ', '0', '2025-10-12 15:45:57.016578', 'ไม่อนุมัติ', '2025-10-12', 'พื้นที่ถูกจองไว้เพื่', 27, 12, 's5610003145'),
(34, '08:00:00', '17:00:00', '2025-10-14', '2025-10-14', '09:00:00', '17:00:00', '2025-10-14', '2025-10-14', 'ส่งคำร้องขอ', '1', '2025-10-09 07:42:05.289341', 'อนุมัติ', '2025-10-09', 'ขอความอนุเคราะห์ช่วย', 29, 12, 's5610003145'),
(35, '13:00:00', '20:00:00', '2025-10-19', '2025-10-19', '09:00:00', '17:00:00', '2025-10-20', '2025-10-20', 'ส่งคำร้องขอ', '0', '2025-10-12 11:15:36.352062', 'อนุมัติ', '2025-10-12', NULL, 33, 12, 's5610003145'),
(36, '12:00:00', '18:00:00', '2025-10-18', '2025-10-18', '09:00:00', '17:00:00', '2025-10-19', '2025-10-19', 'ส่งคำร้องขอ', '1', '2025-10-10 06:53:07.000000', NULL, NULL, NULL, 30, 19, NULL),
(37, '12:00:00', '18:00:00', '2025-10-18', '2025-10-18', '09:00:00', '17:00:00', '2025-10-19', '2025-10-19', 'ส่งคำร้องขอ', '0', '2025-10-10 06:58:27.000000', NULL, NULL, NULL, 30, 9, NULL),
(38, '14:00:00', '20:00:00', '2025-10-23', '2025-10-23', '18:00:00', '22:00:00', '2025-10-24', '2025-10-24', 'ส่งคำร้องขอ', '0', '2025-10-10 06:59:38.000000', NULL, NULL, NULL, 31, 19, NULL),
(39, '15:00:00', '20:00:00', '2025-10-30', '2025-10-30', '18:00:00', '22:00:00', '2025-10-31', '2025-10-31', 'ส่งคำร้องขอ', '0', '2025-10-10 07:52:45.272575', NULL, NULL, NULL, 34, 19, NULL);

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
(3, 'ศิลปศาสตร์และวิทยาการจัดการ'),
(4, 'สาธารณสุขศาสตร์'),
(5, 'สำนักงานวิทยาเขต'),
(6, 'กองบริหารทั่วไป'),
(7, 'กองบริหารวิชาการและนิสิต'),
(8, 'กองบริหารการวิจัยและบริการวิชาการ'),
(9, 'กองบริหารกลาง');

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
(9, 'บายเนียร์', '2025-08-18', '2025-08-18', 'บายเนียร์พี่ปี 4', 'uploads/fi', '200', '0987654321', 'อาจารย์ศิริพร ทับทิม', '2025-08-21 09:28:59.145530', 'สิ้นสุดโครงการ', 'b6540201149', 2),
(15, 'แข่ง A-math', '2025-08-31', '2025-08-31', 'แข่ง A-math', 'uploads/files/689d54c3d653f.png', '100', '0987654321', 'ผศ.ศิริพร ทับทิม', '2025-09-01 07:07:05.685498', 'สิ้นสุดโครงการ', 'b6540201149', 2),
(17, 'ประกวดร้องเพลง', '2025-08-31', '2025-08-31', 'การประกวดร้องเพลงสุดหรรษา', 'uploads/files/68ac1a8e10f87.jpg', '100', '0987654321', 'ผศ.ศิริพร ทับทิม', '2025-09-01 07:07:05.685498', 'สิ้นสุดโครงการ', 'b6540201149', 2),
(24, 'งานดนตรี', '2025-10-01', '2025-10-02', 'งานดนตรีสากล', 'uploads/files/68d3917909b25.png', '100', '0933904819', 'ผู้ช่วยศาตราจารย์ศิริพร ทับทิม', '2025-10-07 08:01:55.998415', 'สิ้นสุดโครงการ', 'b6540201909', 2),
(26, 'การแข่งขันกินไก่ทอด', '2025-10-04', '2025-10-05', 'กินไก่ทอดแซ่บๆ', 'uploads/files/68d404a53350e.jpg', '100', '0987654321', 'A.J. JJ', '2025-10-07 07:45:11.915872', 'สิ้นสุดโครงการ', 'b6540201909', 2),
(27, 'วันข้าวแห่งชาติ', '2025-12-14', '2025-12-15', 'กิจกรรมสัมนาเกี่ยวกับวันข้าวแห่งประเทศไทย', '[\"uploads\\/files\\/68e215ca9863a.pdf\"]', '300', '0987654321', 'ผู้ช่วยศาสตราจารย์ เกรียงศักดิ์ พันธุ์ศิล', '2025-10-05 12:56:55.219466', 'ส่งโครงการ', 'b6640101307', 2),
(29, 'ค่าบอบรมการพัฒนาเว็ปแอปพลิเคชั่นด้วย AI', '2025-10-14', '2025-10-14', 'ค่าบอบรมการพัฒนาเว็ปแอปพลิเคชั่นด้วย Gemini เพื่อช่วยในการพัฒนาโปรเจคของนิสิตชั้นปีที่ 3 ภาควิชาวิทยาการคอมพิวเตอร์และสารสนเทศ', '[]', '60', '0912345678', NULL, '2025-10-07 08:46:52.000000', 'ส่งโครงการ', 'fsespt', 2),
(30, 'ค่ายฝึกอบรม อสม.', '2025-10-18', '2025-10-19', 'ค่ายฝึกอบรมความรู้แก่อสม.ประจำตำบลเชียงเครือ ณ มหาวิทยาลัยเกษตรศาสตร์ วิทยาเขตเฉลิมพระเกียรติสกลนคร', '[\"uploads\\/files\\/68e74a7140da9.jpg\",\"uploads\\/files\\/68e74a7140fe2.pdf\"]', '80', '0923456789', 'ศาสตราจารย์ดอกเตอร์กิติพงษ์ หารบัญชา', '2025-10-09 06:34:03.868332', 'ส่งโครงการ', 'b6540400417', 2),
(31, 'สศ. หมอลำเซิ้ง', '2025-10-23', '2025-10-24', 'กิจกรรมคอนเสิร์ตหมอลำซิ่งจากคณะประถมบันเทิงศิลป์เพื่อความสุขและผ่อนคลายความเครียดแก่นิสิตและบุคลากรภายในมหาวิทยาลัยเกษตรศาสตร์ วิทยาเขตเฉลิมพระเกียรติ จังหวัดสกลนคร', '[\"uploads\\/files\\/68e7514f2c57e.jpg\",\"uploads\\/files\\/68e7514f2cbe0.pdf\"]', '500', '0923456789', 'ผู้ช่วยศาสตราจารย์นงลักษณ์ หารบัญชา', '2025-10-09 06:34:24.245670', 'ส่งโครงการ', 'b6540400417', 2),
(33, 'การแข่งขันเจน Prompt AI ด้วย Google Gemini API', '2025-10-19', '2025-10-20', 'การแข่งขันการสร้าง Prompt เพื่อนำไปใช้กับ AI ให้มีประสิทธิภาพ', '[\"uploads\\/files\\/68e75ddd46b3b.jpg\",\"uploads\\/files\\/68e75ddd479a9.pdf\"]', '50', '0987654321', 'ผู้ช่วยศาสตราจารย์ศิริพร ทับทิม', '2025-10-09 07:01:49.000000', 'ส่งโครงการ', 'b6540201909', 1),
(34, 'คอนเสิร์ตผ่อนใจ โครงใจผ่อนรัก', '2025-10-30', '2025-10-31', 'คอนเสิร์ตดนตรีสากลจากคณะศิลปศาสตร์และวิทยาการจัดการ', '[\"uploads\\/files\\/68e8ba2bea327.pdf\"]', '300', '0944311718', 'ดร.ปิยพงษ์ ประถุมทม', '2025-10-10 07:52:25.923835', 'ส่งโครงการ', 'b6540303410', 2),
(35, 'ค่ายวว.บำเพ็ญ ประจำปี 2568', '2025-10-22', '2025-10-22', 'โครงการค่ายวว.บำเพ็ญ ประจำปี 2568 เพื่อให้นิสิตและบุคลากรภายในคณะวิทยาศาสตร์และวิศกรรมศาสตร์ ในการช่วยเหลือ แก้ไข ให้ความรู้ สู้ชุมชม บ้านหนองหอย', '[\"uploads\\/files\\/68eb52c4eac10.jpg\",\"uploads\\/files\\/68eb52c4eb116.pdf\"]', '500', '0923456789', 'ผู้ช่วยศาสตราจารย์เศรษฐกร โพนทอง', '2025-10-12 07:06:10.477087', 'ร่างโครงการ', 'b6540201909', 2);

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `staff_id` varchar(11) NOT NULL,
  `staff_name` varchar(100) NOT NULL,
  `staff_sur` varchar(100) NOT NULL,
  `user_pass` varchar(100) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `dept` varchar(100) DEFAULT NULL,
  `user_type_id` int(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`staff_id`, `staff_name`, `staff_sur`, `user_pass`, `position`, `dept`, `user_type_id`) VALUES
('s5610003145', 'สมศักดิ์', 'รักสเมียณ', '12345678', NULL, NULL, 3);

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `nontri_id` varchar(11) NOT NULL,
  `user_name` varchar(100) NOT NULL,
  `user_sur` varchar(100) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `dept` varchar(100) DEFAULT NULL,
  `user_type_id` int(2) NOT NULL,
  `fa_de_id` int(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`nontri_id`, `user_name`, `user_sur`, `position`, `dept`, `user_type_id`, `fa_de_id`) VALUES
('b6540200315', 'เกียรติสกุล', 'ไพยเสน', NULL, NULL, 1, 2),
('b6540201149', 'ทวีศักดิ์', 'สีอังรัตน์', NULL, NULL, 1, 2),
('b6540201180', 'ทินวุฒิ', 'พลบำรุง', NULL, NULL, 1, 2),
('b6540201487', 'ธิดารัตน์', 'จันทรประสาท', NULL, NULL, 1, 2),
('b6540201909', 'ปรัชชฎางค์กรณ์', 'แก้วมณีโชติ', '', '', 1, 2),
('b6540202410', 'พีระพงษ์', 'เทพประสิทธิ์', NULL, NULL, 1, 2),
('b6540202964', 'วัชรากร', 'เครือเนตร', '', '', 1, 2),
('b6540303410', 'อภิสิทธิ์', 'นามเกตุ', NULL, NULL, 1, 3),
('b6540400417', 'ศิริวรรณ', 'กุมพันธ์', NULL, NULL, 1, 4),
('b6640101307', 'ทักษิณ', 'ใจบานดง', NULL, NULL, 1, 1),
('csnarn', 'อัจฉรา', 'นามบุรี', 'ผู้ช่วยศาสตราจารย์', 'ภาควิชาวิทยาการคอมพิวเตอร์และสารสนเทศ', 2, 2),
('fsespt', 'ศิริพร', 'ทับทิม', 'ผู้ช่วยศาสตราจารย์', 'ภาควิชาวิทยาการคอมพิวเตอร์และสารสนเทศ', 2, 2),
('fsettl', 'เทพพิทักษ์', 'รักสงบ', 'ผู้ช่วยศาตราจารย์ดอกเตอร์', 'ภาควิชาวิทยาการคอมพิวเตอร์และสารสนเทศ', 2, 2);

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
(2, 'บุคลากร'),
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
  MODIFY `equip_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `equipments_requests`
--
ALTER TABLE `equipments_requests`
  MODIFY `equip_re_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `facilities`
--
ALTER TABLE `facilities`
  MODIFY `facility_id` int(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `facilities_requests`
--
ALTER TABLE `facilities_requests`
  MODIFY `facility_re_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `faculties_department`
--
ALTER TABLE `faculties_department`
  MODIFY `fa_de_id` int(2) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `project`
--
ALTER TABLE `project`
  MODIFY `project_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

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
-- Constraints for table `user`
--
ALTER TABLE `user`
  ADD CONSTRAINT `fa_de_foreign` FOREIGN KEY (`fa_de_id`) REFERENCES `faculties_department` (`fa_de_id`),
  ADD CONSTRAINT `user_type_foreign` FOREIGN KEY (`user_type_id`) REFERENCES `user_type` (`user_type_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
