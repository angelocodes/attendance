-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 23, 2025 at 08:09 PM
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
-- Database: `uni_at`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--

CREATE TABLE `academic_years` (
  `academic_year_id` int(11) NOT NULL,
  `start_year` year(4) NOT NULL,
  `end_year` year(4) NOT NULL,
  `description` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_years`
--

INSERT INTO `academic_years` (`academic_year_id`, `start_year`, `end_year`, `description`) VALUES
(1, '2024', '2026', 'Academic Year 2024/2026'),
(3, '2021', '2025', 'Academic Year 2021/2025'),
(4, '2023', '2024', 'Academic Year 2023/2024'),
(5, '2025', '2026', 'Academic Year 2025/2026'),
(7, '2023', '2024', 'Academic Year 2023/2024'),
(9, '2022', '2023', 'Academic Year 2022/2023');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `first_name`, `last_name`, `role`) VALUES
(29, 'Admin', NULL, 'System Admin'),
(57, 'Registrar', 'Admin', 'Academic Registrar');

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `assignment_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` datetime NOT NULL,
  `created_by` int(11) NOT NULL COMMENT 'Lecturer who created the assignment',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assignments`
--

INSERT INTO `assignments` (`assignment_id`, `unit_id`, `title`, `description`, `due_date`, `created_by`, `created_at`) VALUES
(1, 1, 'Digital Systems Project', 'Complete the circuit design project', '2025-06-05 23:59:00', 2, '2025-05-27 12:17:22'),
(2, 2, 'Programming Assignment 1', 'Write a Python program for sorting algorithms', '2025-06-10 23:59:00', 2, '2025-05-27 12:17:22'),
(3, 5, 'Anatomy Case Study', 'Analyze a clinical anatomy case', '2025-06-15 23:59:00', 46, '2025-05-27 11:03:00'),
(4, 11, 'Nursing Care Plan', 'Develop a patient care plan', '2025-06-20 23:59:00', 37, '2025-05-27 11:03:00'),
(5, 30, 'Algorithm Implementation', 'Implement sorting algorithms in C++', '2025-06-25 23:59:00', 41, '2025-05-27 11:03:00'),
(6, 45, 'Teaching Strategy Report', 'Design a lesson plan', '2025-06-30 23:59:00', 39, '2025-05-27 11:03:00'),
(7, 27, 'Pharmacology Quiz', 'Complete a quiz on drug interactions', '2025-06-15 23:59:00', 43, '2025-05-27 11:03:00'),
(8, 48, 'Food Science Project', 'Analyze food composition', '2025-06-20 23:59:00', 44, '2025-05-27 11:03:00');

--
-- Triggers `assignments`
--
DELIMITER $$
CREATE TRIGGER `before_assignment_insert` BEFORE INSERT ON `assignments` FOR EACH ROW BEGIN
  IF NEW.due_date <= NOW() THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Due date must be in the future';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_records`
--

CREATE TABLE `attendance_records` (
  `attendance_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `session_id` int(11) DEFAULT NULL,
  `status` enum('Present','Absent','Late') NOT NULL,
  `marked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_records`
--

INSERT INTO `attendance_records` (`attendance_id`, `student_id`, `session_id`, `status`, `marked_at`) VALUES
(1, 3, 1, 'Present', '2025-05-20 09:41:04'),
(2, 4, 1, 'Absent', '2025-05-20 09:41:04'),
(3, 3, 2, 'Present', '2025-05-20 09:41:04'),
(4, 4, 2, 'Late', '2025-05-20 09:41:04'),
(5, 47, 3, 'Present', '2024-09-10 05:30:00'),
(6, 48, 4, 'Present', '2024-09-10 07:30:00'),
(7, 49, 5, 'Late', '2024-09-11 11:15:00'),
(8, 50, 6, 'Present', '2024-09-12 05:30:00'),
(9, 51, 7, 'Absent', '2024-09-12 07:30:00'),
(10, 48, 8, 'Present', '2024-09-13 11:30:00'),
(11, 52, 9, 'Present', '2024-09-14 05:30:00'),
(12, 53, 10, 'Late', '2024-09-14 07:15:00'),
(13, 54, 11, 'Present', '2024-09-15 05:30:00'),
(14, 55, 12, 'Present', '2024-09-15 11:30:00'),
(15, 56, 13, 'Absent', '2024-09-16 07:30:00'),
(16, 47, 17, 'Present', '2025-05-28 12:00:54'),
(17, 47, 15, 'Present', '2025-05-28 07:43:51'),
(18, 47, 14, 'Present', '2025-05-28 07:44:31'),
(19, 53, 17, 'Present', '2025-05-28 12:00:54'),
(20, 4, 19, 'Present', '2025-05-28 12:23:12'),
(21, 31, 16, 'Present', '2025-05-28 12:33:00'),
(22, 47, 16, 'Present', '2025-05-28 12:33:00'),
(23, 31, 20, 'Present', '2025-05-28 15:07:46'),
(24, 53, 20, 'Present', '2025-05-28 15:07:46'),
(25, 32, 25, 'Present', '2025-05-29 12:35:55'),
(26, 32, 24, 'Present', '2025-05-29 15:36:29');

-- --------------------------------------------------------

--
-- Table structure for table `class_sessions`
--

CREATE TABLE `class_sessions` (
  `session_id` int(11) NOT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `lecturer_id` int(11) DEFAULT NULL,
  `session_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `venue` varchar(100) DEFAULT NULL,
  `session_topic` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class_sessions`
--

INSERT INTO `class_sessions` (`session_id`, `unit_id`, `lecturer_id`, `session_date`, `start_time`, `end_time`, `venue`, `session_topic`) VALUES
(1, 1, 2, '2025-05-19', '08:00:00', '10:00:00', 'Lab 1', NULL),
(2, 2, 2, '2025-05-20', '10:00:00', '12:00:00', 'Lab 2', NULL),
(3, 5, 46, '2024-09-10', '08:00:00', '10:00:00', 'Anatomy Lab', NULL),
(4, 11, 37, '2024-09-10', '10:00:00', '12:00:00', 'Nursing Lecture Hall', NULL),
(5, 30, 41, '2024-09-11', '14:00:00', '16:00:00', 'Computer Lab 1', NULL),
(6, 45, 39, '2024-09-12', '08:00:00', '10:00:00', 'Education Lecture Room', NULL),
(7, 27, 43, '2024-09-12', '10:00:00', '12:00:00', 'Pharmacy Lab', NULL),
(8, 12, 37, '2024-09-13', '14:00:00', '16:00:00', 'Nursing Lecture Hall', NULL),
(9, 33, 42, '2024-09-14', '08:00:00', '10:00:00', 'Physics Lab', NULL),
(10, 36, 45, '2024-09-14', '10:00:00', '12:00:00', 'Electronics Lab', NULL),
(11, 39, 40, '2024-09-15', '08:00:00', '10:00:00', 'Engineering Workshop', NULL),
(12, 42, 40, '2024-09-15', '14:00:00', '16:00:00', 'Aeronautical Lab', NULL),
(13, 48, 44, '2024-09-16', '10:00:00', '12:00:00', 'Culinary Lab', NULL),
(14, 5, 30, '2025-05-30', '02:31:00', '03:35:00', 'Online', NULL),
(15, 5, 30, '2025-05-28', '02:00:00', '04:00:00', 'anywheer', NULL),
(16, 5, 30, '2025-05-30', '06:00:00', '07:59:00', 'online', NULL),
(17, 5, 30, '2025-05-31', '07:50:00', '23:59:00', 'lecture room5', NULL),
(19, 1, 30, '2025-05-29', '15:05:00', '18:19:00', 'Hall V', NULL),
(20, 5, 30, '2025-05-28', '15:34:00', '15:36:00', 'hall 4', NULL),
(21, 1, 30, '2025-05-29', '00:39:00', '21:44:00', 'rydks', NULL),
(24, 5, 30, '2025-05-29', '11:43:00', '23:59:00', 'Hall C', 'Introduction to Algorithms'),
(25, 5, 30, '2025-05-29', '15:00:00', '17:30:00', 'Reading room', NULL),
(26, 5, 30, '2025-05-29', '20:00:00', '21:00:00', 'Online', 'Face Recognition Test');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `course_name` varchar(255) NOT NULL,
  `course_code` varchar(50) NOT NULL,
  `department_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `duration_years` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `course_name`, `course_code`, `department_id`, `school_id`, `duration_years`) VALUES
(1, 'Bachelor of Science in Computer Engineering', 'BSC-CEN', 1, 1, 4),
(2, 'Medicine', 'MBCHB', 2, 2, 5),
(4, 'Bachelor of Nursing Science', 'BNS', 3, 2, 4),
(5, 'BSc Biomedical Sciences', 'BSC-BMS', 4, 2, 4),
(6, 'BSc Biomedical Laboratory Technology', 'BSC-BLT', 4, 2, 4),
(7, 'BSc Biomedical Engineering', 'BSC-BME', 4, 2, 4),
(8, 'Bachelor of Pharmacy', 'BPHARM', 8, 2, 4),
(9, 'Diploma in Pharmacy', 'DPHARM', 8, 2, 2),
(10, 'BSc Physics Engineering', 'BSC-PHYE', 7, 1, 4),
(11, 'BSc Electrical and Electronic Engineering', 'BSC-EEE', 2, 1, 4),
(12, 'BSc Mechanical Engineering', 'BSC-ME', 6, 1, 4),
(13, 'BSc Engineering in Aeronautical Engineering', 'BSC-BAE', 6, 1, 4),
(14, 'BSc with Education', 'BSC-EDU', 5, 5, 3),
(15, 'BSc Gastronomy and Culinary Sciences', 'BSC-GCS', 9, 5, 3),
(16, 'Bachelor of Commerce in Finance', 'BCOM-FIN', 4, 4, 3),
(17, 'Bachelor of Laws', 'LLB', 6, 6, 4);

-- --------------------------------------------------------

--
-- Table structure for table `course_units`
--

CREATE TABLE `course_units` (
  `unit_id` int(11) NOT NULL,
  `unit_name` varchar(100) DEFAULT NULL,
  `unit_code` varchar(20) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `semester` int(11) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `credit_units` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_units`
--

INSERT INTO `course_units` (`unit_id`, `unit_name`, `unit_code`, `course_id`, `semester`, `year`, `credit_units`) VALUES
(1, 'Digital Systems', 'CEN201', 1, 1, 2, 3),
(2, 'Programming Fundamentals', 'CEN101', 1, 1, 1, 3),
(3, 'Control systems', 'EEE401', 1, 1, 4, 4),
(5, 'Human Anatomy', 'MBCHB101', 2, 1, 1, 4),
(6, 'Physiology', 'MBCHB102', 2, 1, 1, 4),
(7, 'Pathology', 'MBCHB201', 2, 2, 2, 3),
(8, 'Clinical Medicine', 'MBCHB301', 2, 1, 3, 4),
(9, 'Pharmacology', 'MBCHB302', 2, 2, 3, 4),
(10, 'Surgery', 'MBCHB401', 2, 1, 4, 4),
(11, 'Fundamentals of Nursing', 'BNS101', 4, 1, 1, 3),
(12, 'Nursing Ethics', 'BNS102', 4, 2, 1, 3),
(13, 'Community Nursing', 'BNS201', 4, 2, 2, 3),
(14, 'Critical Care Nursing', 'BNS401', 4, 1, 4, 4),
(15, 'Biochemistry', 'BMS101', 5, 1, 1, 3),
(16, 'Microbiology', 'BMS201', 5, 1, 2, 3),
(17, 'Immunology', 'BMS301', 5, 1, 3, 4),
(18, 'Laboratory Techniques', 'BLT101', 6, 1, 1, 3),
(19, 'Hematology', 'BLT201', 6, 2, 2, 3),
(20, 'Clinical Chemistry', 'BLT301', 6, 1, 3, 4),
(21, 'Biomechanics', 'BME201', 7, 1, 2, 3),
(22, 'Medical Instrumentation', 'BME301', 7, 1, 3, 4),
(23, 'Biomedical Signal Processing', 'BME401', 7, 1, 4, 4),
(24, 'Pharmacology', 'BPHARM101', 8, 1, 1, 3),
(25, 'Pharmaceutical Chemistry', 'BPHARM201', 8, 1, 2, 3),
(26, 'Pharmaceutics', 'BPHARM301', 8, 1, 3, 4),
(27, 'Financial Management', 'FIN101', 16, 1, 1, 3),
(28, 'Investment Analysis', 'FIN201', 16, 1, 2, 3),
(29, 'Constitutional Law', 'LAW101', 17, 1, 1, 4),
(30, 'Criminal Law', 'LAW201', 17, 1, 2, 4);

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `school_id` int(11) DEFAULT NULL,
  `head_of_department` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `department_name`, `school_id`, `head_of_department`) VALUES
(1, 'Computer Engineering', 1, 'Dr. Ouma Isaac'),
(2, 'Electronics department', 1, 'Dr.Excellence Favour'),
(3, 'Nursing', 2, 'Dr. Jane Auma'),
(4, 'Biomedical Sciences', 2, 'Dr. Peter Okello'),
(5, 'Education', 5, 'Dr. Sarah Etyang'),
(6, 'Mechanical and Aeronautical Engineering', 1, 'Dr. John Emoit'),
(7, 'Physics Engineering', 1, 'Dr. David Ocen'),
(8, 'Pharmacy', 2, 'Dr. Grace Amuge'),
(9, 'Gastronomy and Culinary Sciences', 5, 'Dr. Esther Apio');

-- --------------------------------------------------------

--
-- Table structure for table `lecturers`
--

CREATE TABLE `lecturers` (
  `lecturer_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `staff_number` varchar(50) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `rank` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lecturers`
--

INSERT INTO `lecturers` (`lecturer_id`, `department_id`, `staff_number`, `first_name`, `last_name`, `rank`) VALUES
(2, 1, 'LECT123', 'Lecturer2', NULL, 'Senior Lecturer'),
(30, 1, 'STAFF030', 'Lecturer', NULL, 'Lecturer'),
(37, 3, 'LECT001', 'Jane', 'Auma', 'Senior Lecturer'),
(38, 4, 'LECT002', 'Peter', 'Okello', 'Associate Professor'),
(39, 5, 'LECT003', 'Sarah', 'Etyang', 'Lecturer'),
(40, 6, 'LECT004', 'John', 'Emoit', 'Senior Lecturer'),
(41, 1, 'LECT005', 'Mary', 'Achiro', 'Lecturer'),
(42, 7, 'LECT006', 'David', 'Ocen', 'Associate Professor'),
(43, 8, 'LECT007', 'Grace', 'Amuge', 'Lecturer'),
(44, 9, 'LECT008', 'Esther', 'Apio', 'Senior Lecturer'),
(45, 2, 'LECT009', 'Samuel', 'Ekolu', 'Lecturer'),
(46, 4, 'LECT010', 'Lillian', 'Ogweta', 'Associate Professor'),
(60, 1, 'STAFF0060', 'Lecturer3', NULL, 'Lecturer');

-- --------------------------------------------------------

--
-- Table structure for table `lecturer_assignments`
--

CREATE TABLE `lecturer_assignments` (
  `assignment_id` int(11) NOT NULL,
  `lecturer_id` int(11) DEFAULT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `semester` int(11) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lecturer_assignments`
--

INSERT INTO `lecturer_assignments` (`assignment_id`, `lecturer_id`, `unit_id`, `semester`, `academic_year`) VALUES
(1, 2, 1, 1, '2024/2025'),
(2, 2, 2, 1, '2024/2025'),
(3, 2, 1, 2, '2024/2026'),
(4, 37, 11, 1, '2024/2025'),
(5, 37, 13, 2, '2024/2025'),
(6, 38, 15, 1, '2024/2025'),
(7, 38, 21, 1, '2024/2025'),
(8, 39, 45, 1, '2024/2025'),
(9, 40, 42, 1, '2024/2025'),
(10, 41, 30, 2, '2024/2025'),
(11, 42, 33, 1, '2024/2025'),
(12, 43, 24, 1, '2024/2025'),
(13, 43, 27, 1, '2024/2025'),
(14, 44, 48, 1, '2024/2025'),
(15, 45, 36, 1, '2024/2025'),
(16, 46, 5, 1, '2024/2025'),
(17, 41, 2, 2, '2024/2025'),
(18, 43, 5, 1, '2024/2025'),
(19, 30, 5, 1, '2024/2025'),
(20, 30, 1, 1, '1');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'Recipient (NULL for all users)',
  `unit_id` int(11) DEFAULT NULL COMMENT 'NULL for general notifications',
  `message` text NOT NULL,
  `created_by` int(11) NOT NULL COMMENT 'Admin or lecturer who created the notification',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  `user_type` enum('admin','lecturer','student') NOT NULL DEFAULT 'lecturer'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `unit_id`, `message`, `created_by`, `created_at`, `is_read`, `user_type`) VALUES
(6, 3, NULL, 'Exam timetable published.', 29, '2025-05-27 12:27:49', 0, 'lecturer'),
(7, NULL, 5, 'Human Anatomy lecture notes uploaded.', 46, '2025-05-27 11:04:00', 1, 'lecturer'),
(8, 47, 5, 'Reminder: Submit Anatomy Case Study by June 15.', 46, '2025-05-27 11:04:00', 0, 'lecturer'),
(9, NULL, 11, 'Nursing Care Plan assignment posted.', 37, '2025-05-27 11:04:00', 1, 'lecturer'),
(10, 49, 30, 'Data Structures lab session rescheduled.', 41, '2025-05-27 11:04:00', 0, 'lecturer'),
(11, NULL, NULL, 'Semester 1 exam timetable released.', 57, '2025-05-27 11:04:00', 1, 'lecturer'),
(12, 56, 48, 'Food Science project guidelines updated.', 44, '2025-05-27 11:04:00', 0, 'lecturer');

-- --------------------------------------------------------

--
-- Table structure for table `notification_preferences`
--

CREATE TABLE `notification_preferences` (
  `user_id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `is_hidden` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_preferences`
--

INSERT INTO `notification_preferences` (`user_id`, `notification_id`, `is_hidden`, `created_at`) VALUES
(60, 7, 1, '2025-05-28 17:05:54');

-- --------------------------------------------------------

--
-- Table structure for table `schools`
--

CREATE TABLE `schools` (
  `school_id` int(11) NOT NULL,
  `school_name` varchar(100) NOT NULL,
  `school_code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schools`
--

INSERT INTO `schools` (`school_id`, `school_name`, `school_code`, `description`) VALUES
(1, 'School of Engineering and Technology', 'SET', 'Covers engineering and computer science programs'),
(2, 'School of Heath Sciences', 'SHS', 'Covers health related programs'),
(4, 'finance', 'fca', ''),
(5, 'School of Applied Sciences and Education', 'SASE', 'Covers applied sciences, education, and gastronomy programs'),
(6, 'School of Law', 'SoL', '');

-- --------------------------------------------------------

--
-- Table structure for table `semesters`
--

CREATE TABLE `semesters` (
  `semester_id` int(11) NOT NULL,
  `semester_name` varchar(50) DEFAULT NULL,
  `academic_year_id` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `semesters`
--

INSERT INTO `semesters` (`semester_id`, `semester_name`, `academic_year_id`, `start_date`, `end_date`) VALUES
(1, 'Semester 1', 1, '2024-08-01', '2024-12-15'),
(2, 'Semester 2', 1, '2025-01-10', '2025-05-30'),
(3, 'Semester 1', 3, '2021-08-01', '2021-12-15'),
(4, 'Semester 2', 3, '2022-01-10', '2022-05-30'),
(5, 'Semester 1', 9, '2022-08-01', '2022-12-15'),
(6, 'Semester 2', 9, '2023-01-10', '2023-05-30');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('academic_calendar_url', 'https://sun.ac.ug/academic-calendar'),
('admission_deadline', '2025-07-31'),
('contact_email', 'admin@university.edu'),
('contact_phone', '+256700000000'),
('max_credits_per_semester', '24'),
('site_name', 'My University Attendance System'),
('theme_color', '#4a6529'),
('timezone', 'Africa/Kampala'),
('university_address', 'P.O. Box 211, Plot 50/51, Arapai, Soroti, Uganda'),
('website', 'https://sun.ac.ug');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `registration_number` varchar(50) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `year_of_study` int(11) DEFAULT NULL,
  `intake_month` varchar(20) DEFAULT NULL,
  `intake_year` year(4) DEFAULT NULL,
  `status` enum('active','suspended','withdrawn') DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `photo_path` text DEFAULT NULL,
  `academic_year_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `registration_number`, `course_id`, `year_of_study`, `intake_month`, `intake_year`, `status`, `first_name`, `last_name`, `photo_path`, `academic_year_id`) VALUES
(3, 'SU2022/1234', 1, 2, 'August', '2022', 'active', 'Student3', NULL, NULL, 1),
(4, 'SU2022/1235', 1, 2, 'August', '2022', 'active', 'Student4', NULL, NULL, 1),
(31, 'REG0031', 1, 1, 'January', '2025', 'active', 'Milly', '', NULL, 1),
(32, 'REG0032', 1, 1, 'January', '2025', 'active', 'Oguti', 'Joseph', 'uploads/students/student_32_1748514928.jpg', 1),
(34, 'REG0034', 1, 1, 'January', '2025', 'active', 'Student', NULL, NULL, 1),
(37, 'REG0037', 1, 1, 'January', '2025', 'active', 'Jane', 'Auma', NULL, 1),
(47, 'SU2024/1001', 2, 1, 'August', '2024', 'active', 'John', 'Ekwaro', NULL, 1),
(48, 'SU2024/1002', 4, 1, 'August', '2024', 'active', 'Mary', 'Opio', NULL, 1),
(49, 'SU2024/1003', 1, 1, 'August', '2024', 'active', 'Paul', 'Ogwang', NULL, 1),
(50, 'SU2024/1004', 14, 1, 'August', '2024', 'active', 'Susan', 'Adeke', NULL, 1),
(51, 'SU2024/1005', 9, 1, 'August', '2024', 'active', 'David', 'Ongom', NULL, 1),
(52, 'SU2024/1006', 10, 1, 'August', '2024', 'active', 'Emma', 'Achiro', NULL, 1),
(53, 'SU2024/1007', 11, 1, 'August', '2024', 'active', 'Ruth', 'Ekolu', NULL, 1),
(54, 'SU2024/1008', 12, 1, 'August', '2024', 'active', 'Joseph', 'Olem', NULL, 1),
(55, 'SU2024/1009', 13, 1, 'August', '2024', 'active', 'Grace', 'Ogweta', NULL, 1),
(56, 'SU2024/1010', 15, 1, 'August', '2024', 'active', 'Peter', 'Opio', NULL, 1),
(61, 'SU2025/1011', 1, 1, 'August', '2025', 'active', 'Alice', 'Mutai', NULL, 5),
(62, 'SU2025/1012', 10, 1, 'August', '2025', 'active', 'Benard', 'Oyoo', NULL, 5),
(63, 'SU2025/1013', 11, 1, 'August', '2025', 'active', 'Clara', 'Owino', NULL, 5),
(64, 'SU2025/1014', 12, 1, 'January', '2025', 'active', 'Daniel', 'Kioko', NULL, 5),
(65, 'SU2025/1015', 13, 1, 'January', '2025', 'active', 'Esther', 'Kimani', NULL, 5),
(66, 'SU2025/1016', 2, 1, 'August', '2025', 'active', 'Frank', 'Mwangi', NULL, 5),
(67, 'SU2025/1017', 4, 1, 'August', '2025', 'active', 'Gloria', 'Ndege', NULL, 5),
(68, 'SU2025/1018', 5, 1, 'January', '2025', 'active', 'Henry', 'Ongoro', NULL, 5),
(69, 'SU2025/1019', 6, 1, 'January', '2025', 'active', 'Irene', 'Atieno', NULL, 5),
(70, 'SU2025/1020', 7, 1, 'August', '2025', 'active', 'James', 'Mutua', NULL, 5),
(71, 'SU2025/1021', 16, 1, 'January', '2025', 'active', 'Kevin', 'Mutiso', NULL, 5),
(72, 'SU2025/1022', 16, 1, 'January', '2025', 'active', 'Lilian', 'Wanjiku', NULL, 5),
(73, 'SU2025/1023', 17, 1, 'January', '2025', 'active', 'Moses', 'Kamau', NULL, 5),
(74, 'SU2025/1024', 17, 1, 'January', '2025', 'active', 'Nancy', 'Owino', NULL, 5),
(75, 'SU2025/1025', 1, 1, 'August', '2025', 'active', 'Oscar', 'Ndolo', NULL, 5),
(76, 'SU2025/1026', 4, 1, 'August', '2025', 'active', 'Patricia', 'Oyoo', NULL, 5),
(77, 'SU2025/1027', 14, 1, 'August', '2025', 'active', 'Quinton', 'Ekolu', NULL, 5),
(78, 'SU2025/1028', 15, 1, 'August', '2025', 'active', 'Rose', 'Atieno', NULL, 5),
(79, 'SU2025/1029', 16, 1, 'August', '2025', 'active', 'Samuel', 'Olem', NULL, 5),
(80, 'SU2025/1030', 17, 1, 'August', '2025', 'active', 'Tina', 'Ogweta', NULL, 5);

-- --------------------------------------------------------

--
-- Table structure for table `student_enrollments`
--

CREATE TABLE `student_enrollments` (
  `enrollment_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `semester` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_enrollments`
--

INSERT INTO `student_enrollments` (`enrollment_id`, `student_id`, `unit_id`, `academic_year`, `semester`) VALUES
(1, 3, 1, '2024/2025', 1),
(2, 3, 2, '2024/2025', 1),
(3, 4, 1, '2024/2025', 1),
(4, 4, 2, '2024/2025', 1),
(5, 47, 5, '2024/2025', 1),
(6, 47, 6, '2024/2025', 1),
(7, 48, 11, '2024/2025', 1),
(8, 48, 12, '2024/2025', 2),
(9, 49, 30, '2024/2025', 2),
(10, 49, 31, '2024/2025', 2),
(11, 50, 45, '2024/2025', 1),
(12, 50, 46, '2024/2025', 1),
(13, 51, 27, '2024/2025', 1),
(14, 51, 28, '2024/2025', 2),
(15, 52, 33, '2024/2025', 1),
(16, 53, 36, '2024/2025', 1),
(17, 54, 39, '2024/2025', 1),
(18, 55, 42, '2024/2025', 1),
(19, 56, 48, '2024/2025', 1),
(20, 56, 49, '2024/2025', 2),
(23, 56, 15, '2024/2025', 1),
(24, 50, 23, '2024/2025', 1),
(25, 31, 5, '2024/2025', 1),
(26, 32, 5, '2024/2025', 1),
(27, 53, 5, '2024/2025', 2),
(28, 32, 5, '2024/2025', 2),
(29, 37, 5, '2024/2025', 1),
(30, 34, 1, '2024/2025', 1),
(31, 67, 13, '2025/2026', 2),
(32, 67, 11, '2025/2026', 2),
(33, 67, 11, '2025/2026', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `user_type` enum('admin','lecturer','student') NOT NULL,
  `face_encoding` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'active',
  `face_features` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `email`, `phone_number`, `user_type`, `face_encoding`, `created_at`, `deleted_at`, `status`, `face_features`) VALUES
(2, 'lecturer2', '$2y$10$F0UHD8vlGugDFHqpdBy82.Z6t91IXI3LdowuRgCYzF8GGdmsWDAwW', 'lecturer2@example.com', '256700000002', 'lecturer', NULL, '2025-05-27 12:27:49', NULL, 'active', NULL),
(3, 'student3', '$2y$10$14ZhNyEyAFC.zdl3eTIweegPfHujSrswXCW.1w.NOThNJui4bEZoG', 'student3@example.com', '256700000003', 'student', NULL, '2025-05-27 12:27:49', NULL, 'active', NULL),
(4, 'student4', '$2y$10$14ZhNyEyAFC.zdl3eTIweegPfHujSrswXCW.1w.NOThNJui4bEZoG', 'student4@example.com', '256700000004', 'student', NULL, '2025-05-27 12:27:49', NULL, 'active', NULL),
(29, 'admin', '$2y$10$14ZhNyEyAFC.zdl3eTIweegPfHujSrswXCW.1w.NOThNJui4bEZoG', 'admin@gmail.com', '0000000000', 'admin', '', '2025-05-27 09:14:58', NULL, 'active', NULL),
(30, 'lecturer', '$2y$10$F0UHD8vlGugDFHqpdBy82.Z6t91IXI3LdowuRgCYzF8GGdmsWDAwW', 'lecture@gmail.com', '0000000000', 'lecturer', '', '2025-05-27 09:15:50', NULL, 'active', NULL),
(31, 'milly', '$2y$10$z3qZZabrsXF5819EDxU2J.wDps.NsUGROEF8q3W30rAzh2vErIUkS', 'deliveredmilly@gmail.com', '0754532350', 'student', NULL, '2025-05-27 09:22:02', NULL, 'active', NULL),
(32, 'joze', '$2y$10$22w8ctnh7wTee6Gph86JO.9jPPq6C84u9tjJn90rufSFYeOwy1FGi', 'josephoguti02@gmail.com', '0703181192', 'student', NULL, '2025-05-27 09:40:21', NULL, 'active', NULL),
(34, 'student', '', 'student@gmail.com', '', 'student', NULL, '2025-05-27 11:30:23', NULL, 'active', NULL),
(37, 'jane.auma', '$2y$10$DummyHash1234567890', 'jane.auma@sun.ac.ug', '+256700000005', 'student', NULL, '2025-05-27 11:00:00', NULL, 'active', NULL),
(38, 'peter.okello', '$2y$10$DummyHash1234567891', 'peter.okello@sun.ac.ug', '+256700000006', 'lecturer', NULL, '2025-05-27 11:00:00', NULL, 'active', NULL),
(39, 'sarah.etyang', '$2y$10$DummyHash1234567892', 'sarah.etyang@sun.ac.ug', '+256700000007', 'lecturer', NULL, '2025-05-27 11:00:00', NULL, 'active', NULL),
(40, 'john.emoit', '$2y$10$DummyHash1234567893', 'john.emoit@sun.ac.ug', '+256700000008', 'lecturer', NULL, '2025-05-27 11:00:00', NULL, 'active', NULL),
(41, 'mary.achiro', '$2y$10$DummyHash1234567894', 'mary.achiro@sun.ac.ug', '+256700000009', 'lecturer', NULL, '2025-05-27 11:00:00', NULL, 'active', NULL),
(42, 'david.ocen', '$2y$10$DummyHash1234567895', 'david.ocen@sun.ac.ug', '+256700000010', 'lecturer', NULL, '2025-05-27 11:00:00', NULL, 'active', NULL),
(43, 'grace.amuge', '$2y$10$DummyHash1234567896', 'grace.amuge@sun.ac.ug', '+256700000011', 'lecturer', NULL, '2025-05-27 11:00:00', NULL, 'active', NULL),
(44, 'esther.apio', '$2y$10$DummyHash1234567897', 'esther.apio@sun.ac.ug', '+256700000012', 'lecturer', NULL, '2025-05-27 11:00:00', NULL, 'active', NULL),
(45, 'samuel.ekolu', '$2y$10$DummyHash1234567898', 'samuel.ekolu@sun.ac.ug', '+256700000013', 'lecturer', NULL, '2025-05-27 11:00:00', NULL, 'active', NULL),
(46, 'lillian.ogweta', '$2y$10$DummyHash1234567899', 'lillian.ogweta@sun.ac.ug', '+256700000014', 'lecturer', NULL, '2025-05-27 11:00:00', NULL, 'active', NULL),
(47, 'john.ekwaro', '$2y$10$DummyHash1234567900', 'john.ekwaro@sun.ac.ug', '+256700000015', 'student', NULL, '2025-05-27 11:01:00', NULL, 'active', NULL),
(48, 'mary.opio', '$2y$10$DummyHash1234567901', 'mary.opio@sun.ac.ug', '+256700000016', 'student', NULL, '2025-05-27 11:01:00', NULL, 'active', NULL),
(49, 'paul.ogwang', '$2y$10$DummyHash1234567902', 'paul.ogwang@sun.ac.ug', '+256700000017', 'student', NULL, '2025-05-27 11:01:00', NULL, 'active', NULL),
(50, 'susan.adeke', '$2y$10$DummyHash1234567903', 'susan.adeke@sun.ac.ug', '+256700000018', 'student', NULL, '2025-05-27 11:01:00', NULL, 'active', NULL),
(51, 'david.ongom', '$2y$10$DummyHash1234567904', 'david.ongom@sun.ac.ug', '+256700000019', 'student', NULL, '2025-05-27 11:01:00', NULL, 'deleted', NULL),
(52, 'emma.achiro', '$2y$10$DummyHash1234567905', 'emma.achiro@sun.ac.ug', '+256700000020', 'student', NULL, '2025-05-27 11:01:00', NULL, 'active', NULL),
(53, 'ruth.ekolu', '$2y$10$DummyHash1234567906', 'ruth.ekolu@sun.ac.ug', '+256700000021', 'student', NULL, '2025-05-27 11:01:00', NULL, 'active', NULL),
(54, 'joseph.olem', '$2y$10$DummyHash1234567907', 'joseph.olem@sun.ac.ug', '+256700000022', 'student', NULL, '2025-05-27 11:01:00', NULL, 'active', NULL),
(55, 'grace.ogweta', '$2y$10$DummyHash1234567908', 'grace.ogweta@sun.ac.ug', '+256700000023', 'student', NULL, '2025-05-27 11:01:00', NULL, 'active', NULL),
(56, 'peter.opio', '$2y$10$DummyHash1234567909', 'peter.opio@sun.ac.ug', '+256700000024', 'student', NULL, '2025-05-27 11:01:00', NULL, 'active', NULL),
(57, 'registrar.admin', '$2y$10$DummyHash1234567910', 'registrar@sun.ac.ug', '+256700000025', 'admin', NULL, '2025-05-27 11:02:00', NULL, 'active', NULL),
(58, 'qwerty', '$2y$10$s6OC2p5hvO29.dv2V75QResd0WPah4DI3ESSWO9IT2ca9jz9D1Npu', 'qwerty@qwerty.qwerty', '1234567890', '', '', '2025-05-27 16:41:08', NULL, 'deleted', NULL),
(60, 'lecturer3', '$2y$10$SX7kOpXANF202RQTSLxaEOaFpFRIbsnEUUR0ZXK5g2NRKD4WIrrV.', 'lec2@mail.com', '0000000000', 'lecturer', '', '2025-05-28 12:07:11', NULL, 'active', NULL),
(61, 'alice.mutai', '$2y$10$DummyHash1234567890', 'alice.mutai@sun.ac.ug', '+256700000026', 'student', NULL, '2025-05-29 13:00:00', NULL, 'active', NULL),
(62, 'benard.oyoo', '$2y$10$DummyHash1234567890', 'benard.oyoo@sun.ac.ug', '+256700000027', 'student', NULL, '2025-05-29 13:00:00', NULL, 'active', NULL),
(63, 'clara.owino', '$2y$10$DummyHash1234567890', 'clara.owino@sun.ac.ug', '+256700000028', 'student', NULL, '2025-05-29 13:00:00', NULL, 'active', NULL),
(64, 'daniel.kioko', '$2y$10$DummyHash1234567890', 'daniel.kioko@sun.ac.ug', '+256700000029', 'student', NULL, '2025-05-29 13:00:00', NULL, 'active', NULL),
(65, 'esther.kimani', '$2y$10$DummyHash1234567890', 'esther.kimani@sun.ac.ug', '+256700000030', 'student', NULL, '2025-05-29 13:00:00', NULL, 'active', NULL),
(66, 'frank.mwangi', '$2y$10$DummyHash1234567890', 'frank.mwangi@sun.ac.ug', '+256700000031', 'student', NULL, '2025-05-29 13:00:00', NULL, 'active', NULL),
(67, 'gloria.ndege', '$2y$10$DummyHash1234567890', 'gloria.ndege@sun.ac.ug', '+256700000032', 'student', NULL, '2025-05-29 13:00:00', NULL, 'active', NULL),
(68, 'henry.ongoro', '$2y$10$DummyHash1234567890', 'henry.ongoro@sun.ac.ug', '+256700000033', 'student', NULL, '2025-05-29 13:00:00', NULL, 'active', NULL),
(69, 'irene.atieno', '$2y$10$DummyHash1234567890', 'irene.atieno@sun.ac.ug', '+256700000034', 'student', NULL, '2025-05-29 13:00:00', NULL, 'active', NULL),
(70, 'james.mutua', '$2y$10$DummyHash1234567890', 'james.mutua@sun.ac.ug', '+256700000035', 'student', NULL, '2025-05-29 13:00:00', NULL, 'active', NULL),
(71, 'kevin.mutiso', '$2y$10$hashedpassword1', 'kevin.mutiso@university.edu', '+256700000071', 'student', NULL, '2025-05-29 14:39:00', NULL, 'active', NULL),
(72, 'lilian.wanjiku', '$2y$10$hashedpassword2', 'lilian.wanjiku@university.edu', '+256700000072', 'student', NULL, '2025-05-29 14:39:00', NULL, 'active', NULL),
(73, 'moses.kamau', '$2y$10$hashedpassword3', 'moses.kamau@university.edu', '+256700000073', 'student', NULL, '2025-05-29 14:39:00', NULL, 'active', NULL),
(74, 'nancy.owino', '$2y$10$hashedpassword4', 'nancy.owino@university.edu', '+256700000074', 'student', NULL, '2025-05-29 14:39:00', NULL, 'active', NULL),
(75, 'oscar.ndolo', '$2y$10$hashedpassword5', 'oscar.ndolo@university.edu', '+256700000075', 'student', NULL, '2025-05-29 14:39:00', NULL, 'active', NULL),
(76, 'patricia.oyoo', '$2y$10$hashedpassword6', 'patricia.oyoo@university.edu', '+256700000076', 'student', NULL, '2025-05-29 14:39:00', NULL, 'active', NULL),
(77, 'quinton.ekolu', '$2y$10$hashedpassword7', 'quinton.ekolu@university.edu', '+256700000077', 'student', NULL, '2025-05-29 14:39:00', NULL, 'active', NULL),
(78, 'rose.atieno', '$2y$10$hashedpassword8', 'rose.atieno@university.edu', '+256700000078', 'student', NULL, '2025-05-29 14:39:00', NULL, 'active', NULL),
(79, 'samuel.olem', '$2y$10$hashedpassword9', 'samuel.olem@university.edu', '+256700000079', 'student', NULL, '2025-05-29 14:39:00', NULL, 'active', NULL),
(80, 'tina.ogweta', '$2y$10$hashedpassword10', 'tina.ogweta@university.edu', '+256700000080', 'student', NULL, '2025-05-29 14:39:00', NULL, 'active', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_preferences`
--

CREATE TABLE `user_preferences` (
  `user_id` int(11) NOT NULL,
  `widget` varchar(50) NOT NULL,
  `setting_value` varchar(20) DEFAULT 'visible'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_preferences`
--

INSERT INTO `user_preferences` (`user_id`, `widget`, `setting_value`) VALUES
(2, 'attendance_summary', '1'),
(2, 'notifications', '1'),
(2, 'quick_actions', '1'),
(2, 'recent_activity', '1'),
(2, 'sessions_chart', '1'),
(2, 'teaching_overview', '1'),
(30, 'attendance_summary', '1'),
(30, 'notifications', '1'),
(30, 'quick_actions', '1'),
(30, 'recent_activity', '1'),
(30, 'sessions_chart', '1'),
(30, 'teaching_overview', '1');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_years`
--
ALTER TABLE `academic_years`
  ADD PRIMARY KEY (`academic_year_id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_unit_id` (`unit_id`);

--
-- Indexes for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `session_id` (`session_id`);

--
-- Indexes for table `class_sessions`
--
ALTER TABLE `class_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `unit_id` (`unit_id`),
  ADD KEY `lecturer_id` (`lecturer_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`),
  ADD UNIQUE KEY `course_code` (`course_code`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `course_units`
--
ALTER TABLE `course_units`
  ADD PRIMARY KEY (`unit_id`),
  ADD UNIQUE KEY `unit_code` (`unit_code`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`),
  ADD KEY `school_id` (`school_id`);

--
-- Indexes for table `lecturers`
--
ALTER TABLE `lecturers`
  ADD PRIMARY KEY (`lecturer_id`),
  ADD UNIQUE KEY `staff_number` (`staff_number`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `lecturer_assignments`
--
ALTER TABLE `lecturer_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `lecturer_id` (`lecturer_id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `unit_id` (`unit_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD PRIMARY KEY (`user_id`,`notification_id`),
  ADD KEY `notification_id` (`notification_id`);

--
-- Indexes for table `schools`
--
ALTER TABLE `schools`
  ADD PRIMARY KEY (`school_id`),
  ADD UNIQUE KEY `school_code` (`school_code`);

--
-- Indexes for table `semesters`
--
ALTER TABLE `semesters`
  ADD PRIMARY KEY (`semester_id`),
  ADD KEY `academic_year_id` (`academic_year_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `registration_number` (`registration_number`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `fk_students_academic_year` (`academic_year_id`);

--
-- Indexes for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`user_id`,`widget`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_years`
--
ALTER TABLE `academic_years`
  MODIFY `academic_year_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `class_sessions`
--
ALTER TABLE `class_sessions`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `course_units`
--
ALTER TABLE `course_units`
  MODIFY `unit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `lecturer_assignments`
--
ALTER TABLE `lecturer_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `schools`
--
ALTER TABLE `schools`
  MODIFY `school_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `semesters`
--
ALTER TABLE `semesters`
  MODIFY `semester_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admins`
--
ALTER TABLE `admins`
  ADD CONSTRAINT `admins_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_admins_user_id` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `attendance_records_ibfk_2` FOREIGN KEY (`session_id`) REFERENCES `class_sessions` (`session_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
5e04afb5-cda2-40a8-a8be-7f4f1a4a8dcd