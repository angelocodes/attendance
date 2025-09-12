-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 12, 2025 at 01:17 PM
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
CREATE DATABASE IF NOT EXISTS `uni_at` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `uni_at`;

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
(5, '2025', '2026', 'Academic Year 2025/2026'),
(7, '2023', '2024', 'Academic Year 2023/2024'),
(9, '2022', '2023', 'Academic Year 2022/2023'),
(10, '2024', '2025', 'Academic Year 2024/25'),
(11, '2026', '2027', 'Academic Year 2026/2027');

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
(17, 5, 30, '2025-08-30', '21:00:00', '21:59:00', 'lecture room5', NULL),
(19, 1, 30, '2025-05-29', '15:05:00', '18:19:00', 'Hall V', NULL),
(20, 5, 30, '2025-05-28', '15:34:00', '15:36:00', 'hall 4', NULL),
(21, 1, 30, '2025-05-29', '00:39:00', '21:44:00', 'rydks', NULL),
(24, 5, 30, '2025-05-29', '11:43:00', '23:59:00', 'Hall C', 'Introduction to Algorithms'),
(25, 5, 30, '2025-05-29', '15:00:00', '17:30:00', 'Reading room', NULL),
(26, 5, 30, '2025-05-29', '20:00:00', '21:00:00', 'Online', 'Face Recognition Test'),
(27, 3, 81, '2025-08-31', '16:07:00', '18:07:00', 'hall', NULL),
(28, 3, 81, '2025-09-01', '10:15:00', '11:30:00', 'hall', NULL);

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
(17, 'Bachelor of Laws', 'LLB', 10, 6, 4);

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
(30, 'Criminal Law', 'LAW201', 17, 1, 2, 4),
(127, 'General Education', 'GEC-1101', 14, 1, 1, 3);

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
(2, 'Electronics department', 1, 'Dr. Excellence Favour'),
(3, 'Nursing', 2, 'Dr. Jane Auma'),
(4, 'Biomedical Sciences', 2, 'Dr. Peter Okello'),
(5, 'Education', 5, 'Dr. Sarah Etyang'),
(6, 'Mechanical and Aeronautical Engineering', 1, 'Dr. John Emoit'),
(7, 'Physics Engineering', 1, 'Dr. David Ocen'),
(8, 'Pharmacy', 2, 'Dr. Grace Amuge'),
(9, 'Gastronomy and Culinary Sciences', 5, 'Dr. Esther Apio'),
(10, 'Law Dept', 6, 'Dr. Kabwigu');

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
  `rank` varchar(50) DEFAULT NULL,
  `school_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lecturers`
--

INSERT INTO `lecturers` (`lecturer_id`, `department_id`, `staff_number`, `first_name`, `last_name`, `rank`, `school_id`) VALUES
(2, 1, 'LECT123', 'Lecturer2', NULL, 'Senior Lecturer', 1),
(30, 1, 'STAFF030', 'Lecturer', NULL, 'Lecturer', 5),
(37, 3, 'LECT001', 'Jane', 'Auma', 'Senior Lecturer', 2),
(38, 4, 'LECT002', 'Peter', 'Okello', 'Associate Professor', 1),
(39, 5, 'LECT003', 'Sarah', 'Etyang', 'Lecturer', 6),
(40, 6, 'LECT004', 'John', 'Emoit', 'Senior Lecturer', 5),
(41, 1, 'LECT005', 'Mary', 'Achiro', 'Lecturer', 1),
(42, 7, 'LECT006', 'David', 'Ocen', 'Associate Professor', 1),
(43, 8, 'LECT007', 'Grace', 'Amuge', 'Lecturer', 1),
(44, 9, 'LECT008', 'Esther', 'Apio', 'Senior Lecturer', 2),
(45, 2, 'LECT009', 'Samuel', 'Ekolu', 'Lecturer', 6),
(46, 4, 'LECT010', 'Lillian', 'Ogweta', 'Associate Professor', 6),
(60, 1, 'STAFF0060', 'Lecturer3', NULL, 'Lecturer', 2),
(81, 1, 'STAFF0081', 'lect', 'urer', 'Lecturer', 1);

-- --------------------------------------------------------

--
-- Table structure for table `lecturer_assignments`
--

CREATE TABLE `lecturer_assignments` (
  `assignment_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `lecturer_id` int(11) DEFAULT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `semester` int(11) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lecturer_assignments`
--

INSERT INTO `lecturer_assignments` (`assignment_id`, `school_id`, `lecturer_id`, `unit_id`, `semester`, `academic_year`) VALUES
(1, 1, 2, 1, 1, '2024/2025'),
(2, 1, 2, 2, 1, '2024/2025'),
(3, 1, 2, 1, 2, '2024/2026'),
(4, 2, 37, 11, 1, '2024/2025'),
(5, 2, 37, 13, 2, '2024/2025'),
(6, 2, 38, 15, 1, '2024/2025'),
(7, 2, 38, 21, 1, '2024/2025'),
(8, 5, 39, 45, 1, '2024/2025'),
(9, 1, 40, 42, 1, '2024/2025'),
(10, 1, 41, 30, 2, '2024/2025'),
(11, 1, 42, 33, 1, '2024/2025'),
(12, 2, 43, 24, 1, '2024/2025'),
(13, 2, 43, 27, 1, '2024/2025'),
(14, 5, 44, 48, 1, '2024/2025'),
(15, 1, 45, 36, 1, '2024/2025'),
(16, 2, 46, 5, 1, '2024/2025'),
(17, 1, 41, 2, 2, '2024/2025'),
(18, 2, 43, 5, 1, '2024/2025'),
(19, 1, 30, 5, 1, '2024/2025'),
(20, 1, 30, 1, 1, '1'),
(28, 1, 42, 2, 2, '5'),
(29, 2, 44, 5, 1, '5'),
(30, 2, 37, 5, 2, '5'),
(31, 2, 37, 5, 1, '5'),
(32, 6, 45, 30, 1, '5'),
(33, 1, 81, 3, 1, '7');

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
('theme_color', '#29410c'),
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
  `academic_year_id` int(11) DEFAULT NULL,
  `school_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `registration_number`, `course_id`, `year_of_study`, `intake_month`, `intake_year`, `status`, `first_name`, `last_name`, `photo_path`, `academic_year_id`, `school_id`) VALUES
(3, 'SU2022/1234', 1, 2, 'August', '2022', 'active', 'Student3', NULL, NULL, 1, 1),
(4, 'SU2022/1235', 1, 2, 'August', '2022', 'active', 'Student4', NULL, NULL, 1, 5),
(31, 'REG0031', 1, 1, 'January', '2025', 'active', 'Milly', '', NULL, 1, 1),
(32, 'REG0032', 1, 1, 'January', '2025', 'active', 'Oguti', 'Joseph', 'uploads/students/student_32_1748514928.jpg', 1, NULL),
(34, 'REG0034', 1, 1, 'January', '2025', 'active', 'Student', NULL, NULL, 1, 1),
(37, 'REG0037', 1, 1, 'January', '2025', 'active', 'Jane', 'Auma', NULL, 1, 1),
(47, 'SU2024/1001', 2, 1, 'August', '2024', 'active', 'John', 'Ekwaro', NULL, 1, 1),
(48, 'SU2024/1002', 4, 1, 'August', '2024', 'active', 'Mary', 'Opio', NULL, 1, 5),
(49, 'SU2024/1003', 1, 1, 'August', '2024', 'active', 'Paul', 'Ogwang', NULL, 1, 5),
(50, 'SU2024/1004', 14, 1, 'August', '2024', 'active', 'Susan', 'Adeke', NULL, 1, 1),
(51, 'SU2024/1005', 9, 0, 'August', '2024', 'active', 'David', 'Ongom', NULL, 1, NULL),
(52, 'SU2024/1006', 10, 1, 'August', '2024', 'active', 'Emma', 'Achiro', NULL, 1, NULL),
(53, 'SU2024/1007', 11, 1, 'August', '2024', 'active', 'Ruth', 'Ekolu', NULL, 1, NULL),
(54, 'SU2024/1008', 12, 1, 'August', '2024', 'active', 'Joseph', 'Olem', NULL, 1, NULL),
(55, 'SU2024/1009', 13, 1, 'August', '2024', 'active', 'Grace', 'Ogweta', NULL, 1, NULL),
(56, 'SU2024/1010', 15, 1, 'August', '2024', 'active', 'Peter', 'Opio', NULL, 1, NULL),
(61, 'SU2025/1011', 1, 1, 'August', '2025', 'active', 'Alice', 'Mutai', NULL, 5, NULL),
(62, 'SU2025/1012', 10, 1, 'August', '2025', 'active', 'Benard', 'Oyoo', NULL, 5, NULL),
(63, 'SU2025/1013', 11, 1, 'August', '2025', 'active', 'Clara', 'Owino', NULL, 5, NULL),
(64, 'SU2025/1014', 12, 1, 'January', '2025', 'active', 'Daniel', 'Kioko', NULL, 5, NULL),
(65, 'SU2025/1015', 13, 1, 'January', '2025', 'active', 'Esther', 'Kimani', NULL, 5, NULL),
(66, 'SU2025/1016', 2, 1, 'August', '2025', 'active', 'Frank', 'Mwangi', NULL, 5, NULL),
(67, 'SU2025/1017', 4, 1, 'August', '2025', 'active', 'Gloria', 'Ndege', NULL, 5, NULL),
(68, 'SU2025/1018', 5, 1, 'January', '2025', 'active', 'Henry', 'Ongoro', NULL, 5, NULL),
(69, 'SU2025/1019', 6, 1, 'January', '2025', 'active', 'Irene', 'Atieno', NULL, 5, NULL),
(70, 'SU2025/1020', 7, 1, 'August', '2025', 'active', 'James', 'Mutua', NULL, 5, NULL),
(71, 'SU2025/1021', 16, 1, 'January', '2025', 'active', 'Kevin', 'Mutiso', NULL, 5, NULL),
(72, 'SU2025/1022', 16, 1, 'January', '2025', 'active', 'Lilian', 'Wanjiku', NULL, 5, NULL),
(73, 'SU2025/1023', 17, 1, 'January', '2025', 'active', 'Moses', 'Kamau', NULL, 5, NULL),
(74, 'SU2025/1024', 17, 1, 'January', '2025', 'active', 'Nancy', 'Owino', NULL, 5, NULL),
(75, 'SU2025/1025', 1, 1, 'August', '2025', 'active', 'Oscar', 'Ndolo', NULL, 5, NULL),
(76, 'SU2025/1026', 4, 1, 'August', '2025', 'active', 'Patricia', 'Oyoo', NULL, 5, NULL),
(77, 'SU2025/1027', 14, 1, 'August', '2025', 'active', 'Quinton', 'Ekolu', NULL, 5, NULL),
(78, 'SU2025/1028', 15, 1, 'August', '2025', 'active', 'Rose', 'Atieno', NULL, 5, NULL),
(79, 'SU2025/1029', 16, 1, 'August', '2025', 'active', 'Samuel', 'Olem', NULL, 5, NULL),
(80, 'SU2025/1030', 17, 1, 'August', '2025', 'active', 'Tina', 'Ogweta', NULL, 5, NULL),
(82, 'REG0082', 1, 1, 'January', '2025', 'active', 'Oguti', 'Joseph', NULL, NULL, NULL),
(83, 'REG0083', 1, 1, 'January', '2025', 'active', 'Oguti', 'Joseph', NULL, NULL, NULL),
(84, 'REG0084', 13, 1, 'January', '2025', 'active', 'kb', 'bj', NULL, NULL, 1);

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
(49, 'paul.ogwang', '$2y$10$DummyHash1234567902', 'paul.ogwang@sun.ac.ug', '+256700000017', 'student', NULL, '2025-05-27 11:01:00', NULL, 'deleted', NULL),
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
(76, 'patricia.oyoo', '$2y$10$hashedpassword6', 'patricia.oyoo@university.edu', '+256700000076', 'student', NULL, '2025-05-29 14:39:00', NULL, 'deleted', NULL),
(77, 'quinton.ekolu', '$2y$10$hashedpassword7', 'quinton.ekolu@university.edu', '+256700000077', 'student', NULL, '2025-05-29 14:39:00', NULL, 'active', NULL),
(78, 'rose.atieno', '$2y$10$hashedpassword8', 'rose.atieno@university.edu', '+256700000078', 'student', NULL, '2025-05-29 14:39:00', NULL, 'active', NULL),
(79, 'samuel.olem', '$2y$10$hashedpassword9', 'samuel.olem@university.edu', '+256700000079', 'student', NULL, '2025-05-29 14:39:00', NULL, 'active', NULL),
(80, 'tina.ogweta', '$2y$10$hashedpassword10', 'tina.ogweta@university.edu', '+256700000080', 'student', NULL, '2025-05-29 14:39:00', NULL, 'active', NULL),
(81, 'lect', '$2y$10$/WIU5d8bX2lvekATG9M2Deo9rQVvO1ZlPqRD16MVI0zXlGWAupKM2', 'lecturer@gmail.com', '0703181192', 'lecturer', NULL, '2025-08-25 10:44:30', NULL, 'active', NULL),
(82, '345678', '$2y$10$pDQJmfoQ8hgNJfX.3SHGNe46rOsciviwEeL1jJDC1hkohASE//dGq', 'josephoguti02@il.com', '+256703181153', 'student', '[[-0.20850364863872528,0.11336217820644379,0.06201041489839554,-0.048796750605106354,-0.05546301230788231,-0.07947984337806702,0.05208321660757065,-0.12036637216806412,0.12786348164081573,-0.07318424433469772,0.28451234102249146,-0.0038577497471123934,-0.25777414441108704,-0.11046915501356125,0.04203490912914276,0.11081681400537491,-0.15140850841999054,-0.1407867670059204,-0.12087342143058777,-0.15885032713413239,-0.0027245073579251766,-0.011413314379751682,-0.005902878940105438,0.03066670335829258,-0.09062519669532776,-0.23354266583919525,-0.07384499907493591,-0.13204680383205414,0.0737958550453186,-0.0956745520234108,0.0748201534152031,-0.021563192829489708,-0.2411668449640274,-0.06012629345059395,-0.03895573690533638,0.0172868762165308,0.06928280740976334,-0.003318669041618705,0.17124003171920776,-0.00675294641405344,-0.11781642585992813,-0.039523568004369736,0.029480328783392906,0.23513133823871613,0.12731674313545227,0.012871389277279377,0.028086327016353607,0.03855235502123833,0.02940743789076805,-0.21629750728607178,0.009841985069215298,0.0767817422747612,0.1612437516450882,0.022632703185081482,0.024712933227419853,-0.19185160100460052,-0.0648738369345665,0.0539996437728405,-0.12685154378414154,0.10244126617908478,0.06676497310400009,-0.1026730090379715,-0.12419351190328598,-0.044758390635252,0.2679680287837982,0.12166327238082886,-0.11988705396652222,-0.1323307901620865,0.22468404471874237,-0.12025218456983566,-0.05573558434844017,0.1035299226641655,-0.12335802614688873,-0.06287191808223724,-0.2597838044166565,0.09935145825147629,0.3798922002315521,0.07054684311151505,-0.1628907322883606,-0.04959740862250328,-0.16131000220775604,0.0638255849480629,-0.06401404738426208,0.0037405441980808973,-0.08689061552286148,0.028720403090119362,-0.08467268943786621,0.03748869523406029,0.1752905249595642,0.01729593425989151,-0.05877538397908211,0.1873195916414261,-0.025253552943468094,0.01490884181112051,-0.018492847681045532,-0.012371950782835484,-0.01185715664178133,-0.11087589710950851,-0.043775949627161026,-0.013954181224107742,0.04358363524079323,-0.11736083030700684,0.012637351639568806,0.11868029087781906,-0.16943420469760895,0.0824429839849472,-0.02810809575021267,-0.01224230881780386,-0.06941356509923935,0.1275111734867096,-0.02626989036798477,-0.009931694716215134,0.1286972612142563,-0.21535611152648926,0.26984962821006775,0.2295677661895752,-0.024892425164580345,0.07684274017810822,-0.0020628718193620443,0.06080889701843262,-0.022757725790143013,0.024783432483673096,-0.07936154305934906,-0.14613504707813263,-0.01058875396847725,-0.06815795600414276,0.05791880190372467,0.047351542860269547]]', '2025-08-27 10:23:17', NULL, 'active', NULL),
(83, '2101', '$2y$10$9.HqC43nIqNgGsBFB8jDH.1438whxBId3cUUZ7Df2oBFaPfEI5tU2', 'admin@sundev.com', '0752654589', 'student', '[[-0.09603772312402725,0.08964832872152328,0.10780223459005356,-0.08025974035263062,-0.06305792182683945,-0.08405211567878723,0.06365521252155304,-0.003549944143742323,0.1174376830458641,-0.019732724875211716,0.23268058896064758,-0.04972783103585243,-0.2663063108921051,-0.1405535489320755,0.04375186562538147,0.11006432771682739,-0.09289589524269104,-0.06578976660966873,-0.12461205571889877,-0.12374290078878403,-0.010588572360575199,0.048081669956445694,-0.03804175555706024,-0.002940602833405137,-0.053812846541404724,-0.267312616109848,-0.050469864159822464,-0.07723046839237213,-0.008881939575076103,-0.09011513739824295,0.03079979494214058,0.09070798009634018,-0.22166244685649872,-0.12134392559528351,-0.012693110853433609,0.05343863368034363,-0.036891352385282516,0.01283394917845726,0.10047801584005356,0.01194671355187893,-0.09421088546514511,-0.025079665705561638,0.022805867716670036,0.25743523240089417,0.18707957863807678,0.02467913180589676,-0.026482032611966133,-0.022039176896214485,0.058174166828393936,-0.21702207624912262,0.029601864516735077,0.1301727443933487,0.16627761721611023,0.08278899639844894,0.07036719471216202,-0.10983332246541977,0.034922707825899124,0.05732095241546631,-0.18570345640182495,0.020873157307505608,0.07608338445425034,-0.13888952136039734,-0.09863205254077911,-0.10864671319723129,0.19215865433216095,0.03944212943315506,-0.06383689492940903,-0.10654006153345108,0.21938981115818024,-0.09739676117897034,-0.05277843028306961,0.06953022629022598,-0.0866660624742508,-0.09718635678291321,-0.24465468525886536,0.11562390625476837,0.30753281712532043,0.16755729913711548,-0.23158499598503113,-0.012528219260275364,-0.16496147215366364,0.03793676197528839,0.024116741493344307,-0.054887983947992325,-0.05329934507608414,-0.01617751643061638,-0.09805341064929962,0.00984252244234085,0.11268692463636398,-0.04423139616847038,-0.01815585233271122,0.1625794619321823,0.018360372632741928,-0.0028960080817341805,0.038292959332466125,-0.08721032738685608,-0.04385394975543022,-0.0026926803402602673,-0.14475494623184204,-0.019952833652496338,0.0502767451107502,-0.1955881416797638,0.001826406572945416,0.07083909213542938,-0.17090490460395813,0.17721529304981232,0.05280134081840515,-0.016987379640340805,-0.02389209344983101,0.03020610846579075,-0.0630846917629242,-0.0045996359549462795,0.16211219131946564,-0.2145661860704422,0.2644035816192627,0.14393725991249084,-0.017173605039715767,0.13928940892219543,0.024053456261754036,0.14421701431274414,-0.08744257688522339,-0.07953178137540817,-0.08168531954288483,-0.12295883148908615,0.056600701063871384,0.007157640531659126,0.06984859704971313,0.059489984065294266]]', '2025-08-27 16:03:45', NULL, 'active', NULL),
(84, '45637829', '$2y$10$SdElDIAX52CIVR/Q09TeQOqouf2FPfD2wdSg4YsO92jnwN9ZJlCi.', 'admin@sundevs.comm', '0545154554', 'student', '[[-0.19646501541137695,0.17965422570705414,0.11032038182020187,0.026781398802995682,0.0040169330313801765,-0.07099505513906479,0.051440075039863586,-0.05595715343952179,0.09987137466669083,-0.00796271301805973,0.31746169924736023,0.006827238947153091,-0.23338648676872253,-0.16936464607715607,0.06589032709598541,0.1021530032157898,-0.15706391632556915,-0.07366479933261871,-0.12861628830432892,-0.15270648896694183,0.020460739731788635,0.05456920340657234,-0.002848007483407855,0.016909996047616005,-0.09686636924743652,-0.2458902895450592,-0.00892256386578083,-0.1770167350769043,0.16226482391357422,-0.1055978462100029,0.0071042426861822605,-0.01472953986376524,-0.1854894906282425,-0.07621072232723236,-0.028898904100060463,0.03446174040436745,0.062412410974502563,0.02955273538827896,0.17383930087089539,0.012745159678161144,-0.10164377093315125,-0.0023468737490475178,-0.008277217857539654,0.27120035886764526,0.16309115290641785,-0.01723688654601574,0.04595354199409485,0.04303790256381035,0.032378844916820526,-0.19145753979682922,-0.020374948158860207,0.08994798362255096,0.21824491024017334,0.03831593319773674,0.03397582098841667,-0.20680785179138184,-0.11192381381988525,0.04693009331822395,-0.1436714231967926,0.12175532430410385,0.0352533794939518,-0.13950082659721375,-0.11512550711631775,-0.024222172796726227,0.2723509967327118,0.0673876479268074,-0.15154942870140076,-0.11487121134996414,0.19567084312438965,-0.10185768455266953,-0.04487694054841995,0.1230473518371582,-0.09276077896356583,-0.03232291713356972,-0.26048702001571655,0.11731754243373871,0.3606606721878052,0.05226220190525055,-0.23540812730789185,-0.015248209238052368,-0.2085019201040268,0.036274563521146774,-0.049545735120773315,0.05547570064663887,-0.08000747114419937,0.030473535880446434,-0.08606075495481491,-0.012804750353097916,0.11983698606491089,0.013783833011984825,-0.038299620151519775,0.17767180502414703,-0.01635771244764328,0.05971388518810272,-0.018824823200702667,-0.06642844527959824,-0.05163261666893959,-0.1358649581670761,-0.012936439365148544,-0.05890599265694618,0.00554081704467535,-0.16196121275424957,0.01769162155687809,0.08367455005645752,-0.171400785446167,0.10637333244085312,0.021573210135102272,0.032356634736061096,0.001338911592029035,0.11992823332548141,-0.004961375612765551,-0.0012760366080328822,0.15047861635684967,-0.24876631796360016,0.28740158677101135,0.21068216860294342,-0.011981637217104435,0.09882219135761261,0.04775624722242355,0.06756091862916946,-0.02819814719259739,0.04151741787791252,-0.06368651241064072,-0.11885618418455124,-0.009491978213191032,-0.10274294018745422,0.052810169756412506,0.02371959201991558]]', '2025-08-27 16:44:09', NULL, 'active', NULL);

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
(1, 'academic_calendar', 'visible'),
(1, 'student_overview', 'visible'),
(1, 'system_notifications', 'visible'),
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
  ADD KEY `department_id` (`department_id`),
  ADD KEY `school_id` (`school_id`);

--
-- Indexes for table `lecturer_assignments`
--
ALTER TABLE `lecturer_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `lecturer_id` (`lecturer_id`),
  ADD KEY `unit_id` (`unit_id`),
  ADD KEY `fk_la_school` (`school_id`);

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
  ADD KEY `fk_students_academic_year` (`academic_year_id`),
  ADD KEY `school_id` (`school_id`);

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
  MODIFY `academic_year_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

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
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `course_units`
--
ALTER TABLE `course_units`
  MODIFY `unit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=128;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `lecturer_assignments`
--
ALTER TABLE `lecturer_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

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
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

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

--
-- Constraints for table `lecturers`
--
ALTER TABLE `lecturers`
  ADD CONSTRAINT `fk_lecturers_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`school_id`);

--
-- Constraints for table `lecturer_assignments`
--
ALTER TABLE `lecturer_assignments`
  ADD CONSTRAINT `fk_la_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`school_id`);

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`school_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
