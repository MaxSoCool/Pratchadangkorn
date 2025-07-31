-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 31, 2025 at 08:49 PM
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
(1, 'โต๊ะพับ', 100, 'ตัว', 'กว้าง 40 ซม. x ยาว 80 ซม. x สูง 110 ซม.', 'images/equipments/6853f971b335e.jpg'),
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
  `writed_status` varchar(20) NOT NULL,
  `agree` varchar(4) DEFAULT NULL,
  `transport` varchar(20) DEFAULT NULL,
  `request_date` date NOT NULL,
  `appprove` varchar(20) DEFAULT NULL,
  `approve_date` date DEFAULT NULL,
  `quantity` int(3) NOT NULL,
  `approve_detail` varchar(255) DEFAULT NULL,
  `project_id` int(10) NOT NULL,
  `equip_id` int(10) NOT NULL,
  `facility_id` int(4) NOT NULL,
  `staff_id` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(10, 'พื้นที่บริเวณโรงอาหารกลาง', 'พื้นที่ใช้สอยบริเวณโรงอาหารกลางทั้งหมด', 'images/facilities/6853f8e4480e9.jpg', '10');

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
  `writed_status` varchar(20) NOT NULL,
  `agree` varchar(20) DEFAULT NULL,
  `request_date` date NOT NULL,
  `approve` varchar(20) DEFAULT NULL,
  `approve_date` date DEFAULT NULL,
  `approve_detail` varchar(20) DEFAULT NULL,
  `project_id` int(10) NOT NULL,
  `facility_id` int(4) NOT NULL,
  `staff_id` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `files` varchar(10) NOT NULL,
  `attendee` varchar(4) NOT NULL,
  `phone_num` varchar(10) NOT NULL,
  `advisor_name` varchar(255) DEFAULT NULL,
  `nontri_id` int(10) NOT NULL,
  `activity_type_id` int(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `staff_id` int(10) NOT NULL,
  `staff_THname` varchar(100) NOT NULL,
  `staff_THsur` varchar(100) NOT NULL,
  `staff_ENname` varchar(100) NOT NULL,
  `staff_ENsur` varchar(100) NOT NULL,
  `user_id` varchar(11) NOT NULL,
  `user_pass` varchar(100) NOT NULL,
  `user_type_id` int(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`staff_id`, `staff_THname`, `staff_THsur`, `staff_ENname`, `staff_ENsur`, `user_id`, `user_pass`, `user_type_id`) VALUES
(1, 'สมศักดิ์', 'รักสเมียณ', 'Somsuk', 'Raksameon', 's5610003145', '12345678', 3);

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `nontri_id` int(11) NOT NULL,
  `user_THname` varchar(100) NOT NULL,
  `user_THsur` varchar(100) NOT NULL,
  `user_ENname` varchar(100) NOT NULL,
  `user_Ensur` varchar(100) NOT NULL,
  `user_id` varchar(11) NOT NULL,
  `user_pass` varchar(100) NOT NULL,
  `user_type_id` int(2) NOT NULL,
  `fa_de_id` int(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`nontri_id`, `user_THname`, `user_THsur`, `user_ENname`, `user_Ensur`, `user_id`, `user_pass`, `user_type_id`, `fa_de_id`) VALUES
(1, 'ทวีศักดิ์', 'สีอังรัตน์', 'Thaweesak', 'Sriangrat', 'b6540201149', '12345678', 1, 2);

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
  ADD PRIMARY KEY (`equip_re_id`,`project_id`,`equip_id`,`start_date`),
  ADD KEY `fk_equip_2` (`equip_id`),
  ADD KEY `fk_project` (`project_id`);

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
  ADD KEY `fk_project_2` (`project_id`),
  ADD KEY `fk_facility_2` (`facility_id`);

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
  ADD KEY `fk_user` (`nontri_id`),
  ADD KEY `fk_activity_type` (`activity_type_id`);

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
  MODIFY `equip_re_id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `facilities`
--
ALTER TABLE `facilities`
  MODIFY `facility_id` int(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `facilities_requests`
--
ALTER TABLE `facilities_requests`
  MODIFY `facility_re_id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faculties_department`
--
ALTER TABLE `faculties_department`
  MODIFY `fa_de_id` int(2) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `project`
--
ALTER TABLE `project`
  MODIFY `project_id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `nontri_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2147483648;

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
  ADD CONSTRAINT `fk_equip_2` FOREIGN KEY (`equip_id`) REFERENCES `equipments` (`equip_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_project` FOREIGN KEY (`project_id`) REFERENCES `project` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `facilities`
--
ALTER TABLE `facilities`
  ADD CONSTRAINT `building_fa_fk` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`building_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `facilities_requests`
--
ALTER TABLE `facilities_requests`
  ADD CONSTRAINT `fk_facility_2` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`facility_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_project_2` FOREIGN KEY (`project_id`) REFERENCES `project` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `project`
--
ALTER TABLE `project`
  ADD CONSTRAINT `fk_activity_type` FOREIGN KEY (`activity_type_id`) REFERENCES `activity_type` (`activity_type_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user` FOREIGN KEY (`nontri_id`) REFERENCES `user` (`nontri_id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
