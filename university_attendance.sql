-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 30, 2025 at 03:49 PM
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
-- Database: `university_attendance`
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
(17, 5, 30, '2025-05-30', '12:50:00', '23:59:00', 'lecture room5', NULL),
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
(32, 'REG0032', 1, 1, 'January', '2025', 'active', 'Joze', '', 'uploads/students/student_32_1748514928.jpg', 1),
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
(3, 'student3', '$2y$10$14ZhNyEyAFC.zdl3eTIweegPfHujSrswXCW.1w.NOThNJui4bEZoG', 'student3@example.com', '256700000003', 'student', '[]', '2025-05-27 12:27:49', NULL, 'active', NULL),
(4, 'student4', '$2y$10$14ZhNyEyAFC.zdl3eTIweegPfHujSrswXCW.1w.NOThNJui4bEZoG', 'student4@example.com', '256700000004', 'student', '[]', '2025-05-27 12:27:49', NULL, 'active', NULL),
(29, 'admin', '$2y$10$14ZhNyEyAFC.zdl3eTIweegPfHujSrswXCW.1w.NOThNJui4bEZoG', 'admin@gmail.com', '0000000000', 'admin', '', '2025-05-27 09:14:58', NULL, 'active', NULL),
(30, 'lecturer', '$2y$10$F0UHD8vlGugDFHqpdBy82.Z6t91IXI3LdowuRgCYzF8GGdmsWDAwW', 'lecture@gmail.com', '0000000000', 'lecturer', '', '2025-05-27 09:15:50', NULL, 'active', NULL),
(31, 'milly', '$2y$10$z3qZZabrsXF5819EDxU2J.wDps.NsUGROEF8q3W30rAzh2vErIUkS', 'deliveredmilly@gmail.com', '0754532350', 'student', '[[-0.11931688338518143,0.05756991729140282,0.09357629716396332,-0.01771341823041439,0.08119747787714005,-0.12612320482730865,0.1291644424200058,-0.06304052472114563,0.17507930099964142,-0.0977541133761406,0.2416374683380127,-0.0377490408718586,-0.18652354180812836,-0.11753776669502258,0.08579978346824646,0.1252143234014511,-0.0971856564283371,-0.12946218252182007,-0.121944360435009,-0.1353478878736496,-0.011717049404978752,0.03011867217719555,0.0054445043206214905,0.11508645117282867,-0.09253818541765213,-0.24922379851341248,-0.04604541137814522,-0.17081181704998016,0.05069361999630928,-0.027134744450449944,0.034069571644067764,0.10999168455600739,-0.19855757057666779,-0.0145801343023777,-0.006997424177825451,0.037399113178253174,0.05441686511039734,-0.0014779774937778711,0.18226107954978943,0.026955293491482735,-0.15288454294204712,-0.13668623566627502,-0.03130912408232689,0.28267571330070496,0.13723988831043243,-0.08593383431434631,0.003937324974685907,0.00732476357370615,0.05301102250814438,-0.23881018161773682,0.01253487728536129,0.06570254266262054,0.060840848833322525,0.09805649518966675,0.012621970847249031,-0.12790630757808685,0.03618977591395378,0.018169451504945755,-0.21978473663330078,0.06912191957235336,0.05353087931871414,-0.18652524054050446,-0.13947485387325287,0.003930692560970783,0.22660763561725616,0.08161214739084244,-0.11976199597120285,-0.1381203979253769,0.17812003195285797,-0.20026656985282898,0.03123563341796398,0.18162916600704193,-0.0777963176369667,-0.11309999972581863,-0.24299007654190063,0.10648884624242783,0.3649711608886719,0.14138394594192505,-0.1833427995443344,0.007742930203676224,-0.19139981269836426,0.038518276065588,0.0853937566280365,0.055184341967105865,-0.023850586265325546,0.07046137750148773,-0.11156676709651947,0.017304247245192528,0.14332181215286255,0.012768053449690342,-0.058121003210544586,0.24032939970493317,-0.03515665605664253,-0.02840214967727661,0.01902882382273674,-0.004982530605047941,0.00007449111581081524,-0.07803632318973541,-0.10366444289684296,-0.028993217274546623,0.031039467081427574,-0.08406394720077515,-0.026155950501561165,0.05240906774997711,-0.192996546626091,0.13872656226158142,0.017432119697332382,-0.021832020953297615,0.012925313785672188,0.05358224734663963,-0.06980650126934052,-0.09136423468589783,0.11573520302772522,-0.21750442683696747,0.13066472113132477,0.14363238215446472,0.04039666801691055,0.13706572353839874,-0.0414545014500618,0.10453640669584274,-0.06905032694339752,-0.09977924078702927,-0.08713135868310928,-0.02621273510158062,0.07534809410572052,0.02178916521370411,0.011064126156270504,-0.02404952421784401],[-0.1511225700378418,0.01610308513045311,0.11104920506477356,-0.05174612998962402,0.02694443054497242,-0.0942208468914032,0.09582652151584625,-0.08529140800237656,0.19135968387126923,-0.09175311028957367,0.2459014356136322,-0.015658097341656685,-0.19077171385288239,-0.10148756206035614,0.03475421667098999,0.1584751307964325,-0.14233458042144775,-0.14799144864082336,-0.12462617456912994,-0.08652666956186295,-0.029776036739349365,0.009000175632536411,0.024711452424526215,0.052274804562330246,-0.12765321135520935,-0.3034704029560089,-0.054105665534734726,-0.13696350157260895,0.015913277864456177,-0.014531472697854042,0.03434702754020691,0.1353454291820526,-0.251432329416275,-0.007326554507017136,-0.006918934173882008,0.09336666762828827,0.04479730501770973,-0.03430735692381859,0.15385432541370392,0.007612796500325203,-0.18448998034000397,-0.10143253207206726,-0.011825229972600937,0.21242712438106537,0.175509974360466,-0.07063417136669159,-0.01663890853524208,-0.00857020914554596,0.04337889701128006,-0.2904447615146637,0.007724201772361994,0.06615246832370758,0.048339247703552246,0.07167445868253708,0.007966444827616215,-0.11211240291595459,0.034356437623500824,0.026133934035897255,-0.203565314412117,0.000337369303451851,0.003410868113860488,-0.1521177589893341,-0.12173755466938019,-0.014079910703003407,0.22318202257156372,0.12203779071569443,-0.11596393585205078,-0.08558549731969833,0.21441297233104706,-0.16119389235973358,0.045860711485147476,0.1322280317544937,-0.10163262486457825,-0.13625279068946838,-0.27311164140701294,0.0614720843732357,0.3995724320411682,0.11664555221796036,-0.19610591232776642,0.0048339893110096455,-0.18076056241989136,0.04098588600754738,0.027318378910422325,0.05094999447464943,-0.03553116321563721,0.07302762567996979,-0.10846396535634995,0.033338021486997604,0.18151476979255676,0.005739321932196617,-0.0458846241235733,0.26039978861808777,-0.020766038447618484,-0.013364122249186039,0.03189493715763092,0.0025284511502832174,0.030177975073456764,-0.11962904036045074,-0.14281822741031647,-0.06395597755908966,0.04340929538011551,-0.03321781009435654,-0.04320591688156128,0.10927087813615799,-0.1785157322883606,0.11479382961988449,0.01701485551893711,-0.03970468416810036,0.039526186883449554,0.021485254168510437,-0.01394098624587059,-0.11116672307252884,0.11007354408502579,-0.19017358124256134,0.12274985015392303,0.15977038443088531,-0.0047471593134105206,0.1426960825920105,-0.038323692977428436,0.08289902657270432,-0.0867256298661232,-0.07264164090156555,-0.09907054156064987,-0.02975173480808735,0.07392747700214386,0.008603234775364399,0.019894298166036606,-0.000599329941906035],[-0.1676015555858612,0.011438928544521332,0.11798104643821716,-0.03509317710995674,0.04446815326809883,-0.09071093797683716,0.08619260787963867,-0.08598547428846359,0.17868034541606903,-0.08316739648580551,0.22498531639575958,-0.022409511730074883,-0.15925654768943787,-0.13208001852035522,0.04966443404555321,0.15071611106395721,-0.12695054709911346,-0.1372227519750595,-0.12475526332855225,-0.1109655573964119,-0.033214021474123,0.028626620769500732,0.023161891847848892,0.03795066103339195,-0.1258189082145691,-0.2763068675994873,-0.06114392355084419,-0.16669432818889618,0.01708216778934002,-0.019423261284828186,0.05423138663172722,0.12543487548828125,-0.214858278632164,0.016990017145872116,-0.02473396807909012,0.08143714815378189,0.057132065296173096,-0.022186389192938805,0.1499999612569809,0.022919122129678726,-0.18106858432292938,-0.11928997933864594,-0.03561974689364433,0.22845260798931122,0.1804274320602417,-0.06793176382780075,-0.015159038826823235,0.016557171940803528,0.05835500359535217,-0.28854602575302124,-0.01816234178841114,0.06633954495191574,0.039620425552129745,0.05613658204674721,0.006177932024002075,-0.1024920791387558,0.02225526235997677,0.008891904726624489,-0.20777280628681183,0.01654438115656376,0.007779443636536598,-0.1708550602197647,-0.14237438142299652,-0.0077620865777134895,0.21995198726654053,0.13799822330474854,-0.1165725439786911,-0.10425892472267151,0.21471728384494781,-0.16128955781459808,0.03729141131043434,0.1469220668077469,-0.10673747211694717,-0.14149440824985504,-0.2594148814678192,0.07949651032686234,0.40882137417793274,0.11207491904497147,-0.19097664952278137,-0.004517497960478067,-0.18875151872634888,0.0572422631084919,0.03481314331293106,0.055547259747982025,-0.03189905360341072,0.07204185426235199,-0.11072764545679092,0.008966157212853432,0.17179428040981293,-0.022911179810762405,-0.04554081708192825,0.24170221388339996,-0.0282585471868515,-0.010100572369992733,0.002441491698846221,-0.012835354544222355,0.03845686838030815,-0.09096985310316086,-0.11877766251564026,-0.06317763030529022,0.06608163565397263,-0.033193573355674744,-0.011632348410785198,0.08703728020191193,-0.15815319120883942,0.13868997991085052,0.02438894845545292,-0.037580303847789764,0.02767324075102806,0.01762675680220127,-0.021687017753720284,-0.08596070110797882,0.13169671595096588,-0.17382346093654633,0.12149065732955933,0.16171453893184662,-0.0003924500779248774,0.153834730386734,-0.021755898371338844,0.1104205772280693,-0.09283383190631866,-0.05749351903796196,-0.08562375605106354,-0.03080929070711136,0.06385636329650879,0.025332460179924965,-0.002385193482041359,-0.0003559021570254117]]', '2025-05-27 09:22:02', NULL, 'active', NULL),
(32, 'joze', '$2y$10$22w8ctnh7wTee6Gph86JO.9jPPq6C84u9tjJn90rufSFYeOwy1FGi', 'josephoguti02@gmail.com', '0703181192', 'student', '[[-0.041624635457992554,0.10931292921304703,0.05024609714746475,0.016075991094112396,-0.13140827417373657,-0.0378451943397522,-0.08633484691381454,-0.03220955654978752,0.11576347798109055,-0.028405729681253433,0.22669704258441925,0.005176872946321964,-0.24446731805801392,-0.03569825366139412,-0.010470638051629066,0.12356438487768173,-0.14473120868206024,-0.0512322299182415,-0.13730564713478088,-0.1291332244873047,-0.00548098748549819,0.011582383885979652,-0.0003153053985442966,-0.04949256032705307,-0.13553760945796967,-0.2657935917377472,-0.09330137819051743,-0.11473780870437622,0.0882171094417572,-0.14523720741271973,0.007045892998576164,0.019434403628110886,-0.134476900100708,-0.06506817042827606,0.03523341193795204,0.01099887490272522,-0.011484349146485329,-0.07098472118377686,0.16122712194919586,-0.05519874766469002,-0.1248028427362442,0.019166333600878716,0.03544539213180542,0.19685280323028564,0.20190128684043884,0.04850589483976364,0.03244650736451149,-0.08222304284572601,0.08484610915184021,-0.2561599314212799,0.017695998772978783,0.14763665199279785,0.0373045839369297,0.13060419261455536,0.08232246339321136,-0.158107191324234,0.02532036229968071,0.14301826059818268,-0.15221107006072998,0.00944116897881031,0.021758265793323517,-0.05558173358440399,-0.05896544083952904,-0.07271620631217957,0.17718099057674408,0.0777902901172638,-0.0672086775302887,-0.13897259533405304,0.1481287181377411,-0.15365061163902283,-0.007513499818742275,0.10073264688253403,-0.09206629544496536,-0.16349132359027863,-0.23442891240119934,0.10115853697061539,0.4042626619338989,0.16912809014320374,-0.13225358724594116,0.025051457807421684,-0.06626420468091965,-0.06343948096036911,-0.0072168526239693165,-0.045125313103199005,-0.09604422003030777,-0.07205234467983246,-0.054613277316093445,0.06582026928663254,0.20724989473819733,-0.08010629564523697,0.0831288993358612,0.18455766141414642,-0.013679592870175838,0.012584850192070007,-0.0015966059872880578,0.03772711381316185,-0.09621609002351761,0.003473712829872966,-0.02777048945426941,-0.005504069849848747,0.1376088410615921,-0.1668083518743515,0.05427141860127449,0.08883146941661835,-0.186021625995636,0.10886994004249573,-0.03628480061888695,-0.04220477119088173,0.02137741446495056,0.015433835797011852,-0.02538139559328556,-0.01069771870970726,0.2418680191040039,-0.23687705397605896,0.2659115195274353,0.2158612608909607,-0.019220860674977303,0.12312698364257812,0.01653161086142063,0.12884613871574402,-0.06142176315188408,-0.03711877018213272,-0.10966318845748901,-0.0854254737496376,-0.00827082246541977,-0.04285408556461334,-0.04416040703654289,0.006360025145113468],[-0.11013546586036682,0.15940161049365997,0.162846177816391,-0.0033809414599090815,-0.04568846523761749,-0.020457759499549866,-0.05497264117002487,-0.08241748064756393,0.11398401111364365,-0.06667128205299377,0.2615090310573578,-0.017531458288431168,-0.1620893031358719,-0.0989440381526947,-0.041952453553676605,0.11530152708292007,-0.17066743969917297,-0.14299991726875305,-0.1495015174150467,-0.06833832710981369,0.01956624537706375,0.0486268624663353,0.017949450761079788,-0.06483664363622665,-0.13842882215976715,-0.3410661518573761,-0.0727221667766571,-0.08173201978206635,0.08049925416707993,-0.12110134959220886,-0.01005862932652235,0.02925177477300167,-0.17233946919441223,-0.039294399321079254,-0.025289826095104218,0.007928951643407345,0.03913024440407753,0.028989996761083603,0.14350013434886932,0.05972234532237053,-0.21729101240634918,0.0493440218269825,0.0031534938607364893,0.3230242431163788,0.24431094527244568,0.05872654914855957,-0.03310391306877136,-0.07832243293523788,0.023846276104450226,-0.21315054595470428,0.08088038116693497,0.14881418645381927,0.12314364314079285,0.03600933775305748,0.11497469246387482,-0.142491415143013,-0.025647945702075958,0.01717558689415455,-0.16543427109718323,0.01816345751285553,0.08089832961559296,-0.045132532715797424,-0.05865497887134552,-0.09242530167102814,0.20509977638721466,0.12275869399309158,-0.10569991916418076,-0.14744091033935547,0.17615486681461334,-0.10068895667791367,-0.030734151601791382,0.07774141430854797,-0.08538364619016647,-0.08973922580480576,-0.3137287497520447,0.03946865350008011,0.41508957743644714,0.06517612189054489,-0.26165926456451416,-0.020521337166428566,-0.09950581938028336,-0.009975085034966469,0.07318168133497238,0.04118206351995468,-0.1459130346775055,0.027034921571612358,-0.05407380312681198,-0.003098099259659648,0.11938362568616867,0.018076548352837563,-0.05934587121009827,0.16352418065071106,-0.018443968147039413,-0.03497358784079552,0.0760374367237091,0.013710449449717999,-0.07459671795368195,0.0008213905384764075,-0.15810313820838928,-0.014456985518336296,0.01200107578188181,-0.10940078645944595,-0.03143132105469704,0.11552208662033081,-0.15229226648807526,0.15084747970104218,0.04473403841257095,-0.020636944100260735,0.0073576983995735645,0.034086938947439194,-0.08612152934074402,-0.03486257046461105,0.18374225497245789,-0.2753482162952423,0.19235104322433472,0.18453095853328705,0.008023874834179878,0.10770475119352341,0.10280238837003708,0.09748879075050354,-0.06374803930521011,0.04309617355465889,-0.12319184839725494,-0.06154162064194679,0.01605944335460663,-0.10712557286024094,0.034447941929101944,0.04654787853360176],[-0.04462983086705208,0.10321633517742157,0.006311372853815556,-0.0198773592710495,-0.10899630188941956,-0.044761817902326584,0.005415112245827913,-0.07985976338386536,0.11355941742658615,-0.013314925134181976,0.23070074617862701,0.0032900888472795486,-0.21423766016960144,-0.12203703820705414,0.03453956916928291,0.05658422037959099,-0.1455109566450119,-0.08185781538486481,-0.1586441993713379,-0.11660066246986389,-0.0034807228948920965,0.04289649426937103,0.045043230056762695,-0.053599972277879715,-0.16103613376617432,-0.19881278276443481,-0.07550815492868423,-0.10915478318929672,0.11260271072387695,-0.15403039753437042,0.04770446568727493,0.017019905149936676,-0.21320006251335144,-0.06373091787099838,-0.01628190651535988,-0.008610532619059086,0.0033300784416496754,-0.04869070649147034,0.19321772456169128,0.0031090728007256985,-0.12254185974597931,0.04846404120326042,0.021998988464474678,0.19835489988327026,0.2511417269706726,-0.03109564259648323,-0.014393649064004421,-0.033317018300294876,0.08711404353380203,-0.25483423471450806,-0.009380246512591839,0.17833168804645538,0.0577983595430851,0.09625454992055893,0.04888902232050896,-0.11339983344078064,-0.013259821571409702,0.15776759386062622,-0.16918806731700897,0.049921900033950806,0.032513514161109924,-0.04640357941389084,-0.052795566618442535,-0.03438204154372215,0.16451776027679443,0.06421754509210587,-0.06861457973718643,-0.16271521151065826,0.1508743166923523,-0.1467599719762802,-0.04509025812149048,0.13657577335834503,-0.106735959649086,-0.13851191103458405,-0.22034744918346405,0.1224747821688652,0.33693772554397583,0.14422982931137085,-0.16844028234481812,0.014175203628838062,-0.15166454017162323,-0.033580318093299866,-0.03503718599677086,-0.06758037954568863,-0.050967324525117874,-0.06767760962247849,-0.11320245265960693,0.03420654311776161,0.20358626544475555,-0.055493906140327454,0.03666634485125542,0.24160508811473846,0.006141084246337414,-0.013701251707971096,0.006219486705958843,0.015455047599971294,-0.1037333607673645,-0.03187263011932373,-0.05845135450363159,-0.04126794636249542,0.10110434889793396,-0.17021414637565613,0.04152829200029373,0.141623854637146,-0.14704760909080505,0.15698224306106567,-0.03380328416824341,-0.04049813747406006,-0.02080625668168068,-0.01742779277265072,0.029479434713721275,0.00800078734755516,0.17871373891830444,-0.21980269253253937,0.2960030138492584,0.1657995879650116,-0.02648424729704857,0.09347772598266602,0.009782630018889904,0.1152903214097023,-0.07669748365879059,0.02832043170928955,-0.059308551251888275,-0.1107976883649826,-0.0456937812268734,-0.026799101382493973,-0.007541834842413664,0.02079561911523342]]', '2025-05-27 09:40:21', NULL, 'active', NULL),
(34, 'student', '', 'student@gmail.com', '', 'student', '[-0.22841738164424896,0.14302916824817657,0.14033347368240356,0.0008078113314695656,-0.05494210869073868,-0.052388764917850494,0.04820875823497772,-0.03495639190077782,0.1030249297618866,0.0034566442482173443,0.2543869614601135,-0.0647418275475502,-0.18666796386241913,-0.11285467445850372,0.07697298377752304,0.11708462238311768,-0.14835859835147858,-0.1214148998260498,-0.11289524286985397,-0.08004413545131683,-0.05447441712021828,0.020461900159716606,-0.06089402735233307,0.05557621642947197,-0.0815202072262764,-0.29014742374420166,-0.04996467009186745,-0.13343456387519836,0.1147797480225563,-0.028469150885939598,0.04654007405042648,0.057185009121894836,-0.21791857481002808,-0.037787459790706635,-0.02935216762125492,0.05950254574418068,0.053374774754047394,0.03301858901977539,0.15590965747833252,-0.02778523974120617,-0.13002930581569672,-0.0045056939125061035,0.02358250878751278,0.24297185242176056,0.14835843443870544,0.012193987146019936,0.03433968871831894,0.04837746545672417,0.013124761171638966,-0.19689184427261353,0.024322178214788437,0.13026036322116852,0.18338645994663239,0.06638529896736145,0.00740812998265028,-0.23893092572689056,-0.03324145823717117,0.048129644244909286,-0.15864868462085724,0.11224470287561417,0.04641760513186455,-0.09406066685914993,-0.1203644797205925,-0.02484595589339733,0.28220897912979126,0.08593738824129105,-0.11354277282953262,-0.2024042010307312,0.20920294523239136,-0.11098325252532959,-0.013755561783909798,0.15108302235603333,-0.11973807215690613,-0.08029534667730331,-0.2801026403903961,0.16959691047668457,0.3075579106807709,0.07520123571157455,-0.2620095908641815,-0.0016160050872713327,-0.1662622094154358,0.05117737874388695,-0.04554983600974083,0.027058415114879608,-0.08783714473247528,-0.02112148329615593,-0.12577059864997864,-0.05282163619995117,0.13958559930324554,0.03871301934123039,-0.01643507368862629,0.19186349213123322,0.029592033475637436,-0.017956482246518135,-0.03091813251376152,-0.06409965455532074,0.005736849270761013,-0.09481833875179291,-0.06999901682138443,-0.03351214528083801,0.04906461015343666,-0.09336483478546143,-0.04652475193142891,0.09543897211551666,-0.211915984749794,0.1543494015932083,0.008221033960580826,0.014852556400001049,-0.04996265470981598,0.07611985504627228,-0.06165437027812004,0.009725875221192837,0.20269125699996948,-0.2542456090450287,0.25734367966651917,0.17335867881774902,0.02104172110557556,0.1159757524728775,0.07326572388410568,0.09531527757644653,-0.028122879564762115,0.020631594583392143,-0.08780359476804733,-0.08909495919942856,0.05821326747536659,-0.1305650919675827,0.004889064934104681,0.08714370429515839]', '2025-05-27 11:30:23', NULL, 'active', NULL),
(37, 'jane.auma', '$2y$10$DummyHash1234567890', 'jane.auma@sun.ac.ug', '+256700000005', 'student', '[-0.2347458302974701,0.1726473569869995,0.13823926448822021,0.015077396295964718,-0.01639285869896412,-0.08868236839771271,0.04556988179683685,-0.07476203888654709,0.09344784170389175,-0.0178841520100832,0.2829623520374298,-0.04274289682507515,-0.1995050460100174,-0.10899021476507187,0.08567677438259125,0.12408735603094101,-0.15477627515792847,-0.1157245859503746,-0.14046107232570648,-0.13293799757957458,-0.05334765836596489,0.04727820307016373,-0.0238747987896204,0.07836458832025528,-0.06509193032979965,-0.2760922312736511,-0.03805389627814293,-0.13132071495056152,0.11751313507556915,-0.03257262706756592,0.046402931213378906,0.02794848196208477,-0.2046789824962616,-0.06792903691530228,-0.031759221106767654,0.03469081223011017,0.042356062680482864,0.035769395530223846,0.18316002190113068,0.011030135676264763,-0.12292005866765976,-0.00765805970877409,0.041667066514492035,0.2878614366054535,0.18498095870018005,0.021830374374985695,0.023628216236829758,0.03067421168088913,-0.0061419205740094185,-0.1829717457294464,0.018702127039432526,0.11582933366298676,0.1910172998905182,0.04376209154725075,0.030074385926127434,-0.20927388966083527,-0.09106700122356415,0.03452817723155022,-0.15165264904499054,0.10861604660749435,0.06722970306873322,-0.08425190299749374,-0.0970461517572403,-0.046180613338947296,0.2838785946369171,0.08744252473115921,-0.12686501443386078,-0.14794906973838806,0.2177874594926834,-0.12166529893875122,-0.04873942956328392,0.13995133340358734,-0.10272179543972015,-0.06894833594560623,-0.28559428453445435,0.13497485220432281,0.3061352074146271,0.07436580210924149,-0.20590992271900177,-0.029912415891885757,-0.16981087625026703,0.020804589614272118,-0.04251904413104057,0.034830644726753235,-0.09113810211420059,-0.02970714122056961,-0.07837586104869843,-0.02108280546963215,0.12847815454006195,0.06558013707399368,-0.03941291570663452,0.21481719613075256,0.010423249565064907,-0.02470240741968155,-0.03272441774606705,-0.04342416301369667,-0.02125903032720089,-0.08821135014295578,-0.09873191267251968,-0.04564396291971207,0.00775547232478857,-0.07390213012695312,0.015828527510166168,0.0964374840259552,-0.18889795243740082,0.10337421298027039,0.019860723987221718,0.026028955355286598,-0.027140818536281586,0.12488692998886108,-0.08582773059606552,-0.027354244142770767,0.18036694824695587,-0.22845761477947235,0.22680695354938507,0.18403767049312592,0.014038223773241043,0.0764794573187828,0.08025568723678589,0.0784958079457283,-0.015616737306118011,0.011801827698946,-0.09038849920034409,-0.07408051937818527,0.046446558088064194,-0.10678945481777191,0.04465711861848831,0.09194806218147278]', '2025-05-27 11:00:00', NULL, 'active', NULL),
(38, 'peter.okello', '$2y$10$DummyHash1234567891', 'peter.okello@sun.ac.ug', '+256700000006', 'lecturer', NULL, '2025-05-27 11:00:00', NULL, 'active', NULL),
(39, 'sarah.etyang', '$2y$10$DummyHash1234567892', 'sarah.etyang@sun.ac.ug', '+256700000007', 'lecturer', NULL, '2025-05-27 11:00:00', NULL, 'active', NULL),
(40, 'john.emoit', '$2y$10$DummyHash1234567893', 'john.emoit@sun.ac.ug', '+256700000008', 'lecturer', NULL, '2025-05-27 11:00:00', NULL, 'active', NULL),
(41, 'mary.achiro', '$2y$10$DummyHash1234567894', 'mary.achiro@sun.ac.ug', '+256700000009', 'lecturer', NULL, '2025-05-27 11:00:00', NULL, 'active', NULL),
(42, 'david.ocen', '$2y$10$DummyHash1234567895', 'david.ocen@sun.ac.ug', '+256700000010', 'lecturer', NULL, '2025-05-27 11:00:00', NULL, 'active', NULL),
(43, 'grace.amuge', '$2y$10$DummyHash1234567896', 'grace.amuge@sun.ac.ug', '+256700000011', 'lecturer', NULL, '2025-05-27 11:00:00', NULL, 'active', NULL),
(44, 'esther.apio', '$2y$10$DummyHash1234567897', 'esther.apio@sun.ac.ug', '+256700000012', 'lecturer', NULL, '2025-05-27 11:00:00', NULL, 'active', NULL),
(45, 'samuel.ekolu', '$2y$10$DummyHash1234567898', 'samuel.ekolu@sun.ac.ug', '+256700000013', 'lecturer', NULL, '2025-05-27 11:00:00', NULL, 'active', NULL),
(46, 'lillian.ogweta', '$2y$10$DummyHash1234567899', 'lillian.ogweta@sun.ac.ug', '+256700000014', 'lecturer', NULL, '2025-05-27 11:00:00', NULL, 'active', NULL),
(47, 'john.ekwaro', '$2y$10$DummyHash1234567900', 'john.ekwaro@sun.ac.ug', '+256700000015', 'student', '[]', '2025-05-27 11:01:00', NULL, 'active', NULL),
(48, 'mary.opio', '$2y$10$DummyHash1234567901', 'mary.opio@sun.ac.ug', '+256700000016', 'student', '[]', '2025-05-27 11:01:00', NULL, 'active', NULL),
(49, 'paul.ogwang', '$2y$10$DummyHash1234567902', 'paul.ogwang@sun.ac.ug', '+256700000017', 'student', '[]', '2025-05-27 11:01:00', NULL, 'active', NULL),
(50, 'susan.adeke', '$2y$10$DummyHash1234567903', 'susan.adeke@sun.ac.ug', '+256700000018', 'student', '[]', '2025-05-27 11:01:00', NULL, 'active', NULL),
(51, 'david.ongom', '$2y$10$DummyHash1234567904', 'david.ongom@sun.ac.ug', '+256700000019', 'student', '[]', '2025-05-27 11:01:00', NULL, 'active', NULL),
(52, 'emma.achiro', '$2y$10$DummyHash1234567905', 'emma.achiro@sun.ac.ug', '+256700000020', 'student', '[]', '2025-05-27 11:01:00', NULL, 'active', NULL),
(53, 'ruth.ekolu', '$2y$10$DummyHash1234567906', 'ruth.ekolu@sun.ac.ug', '+256700000021', 'student', '[]', '2025-05-27 11:01:00', NULL, 'active', NULL),
(54, 'joseph.olem', '$2y$10$DummyHash1234567907', 'joseph.olem@sun.ac.ug', '+256700000022', 'student', '[]', '2025-05-27 11:01:00', NULL, 'active', NULL),
(55, 'grace.ogweta', '$2y$10$DummyHash1234567908', 'grace.ogweta@sun.ac.ug', '+256700000023', 'student', '[]', '2025-05-27 11:01:00', NULL, 'active', NULL),
(56, 'peter.opio', '$2y$10$DummyHash1234567909', 'peter.opio@sun.ac.ug', '+256700000024', 'student', '[]', '2025-05-27 11:01:00', NULL, 'active', NULL),
(57, 'registrar.admin', '$2y$10$DummyHash1234567910', 'registrar@sun.ac.ug', '+256700000025', 'admin', NULL, '2025-05-27 11:02:00', NULL, 'active', NULL),
(58, 'qwerty', '$2y$10$s6OC2p5hvO29.dv2V75QResd0WPah4DI3ESSWO9IT2ca9jz9D1Npu', 'qwerty@qwerty.qwerty', '1234567890', '', '', '2025-05-27 16:41:08', NULL, 'deleted', NULL),
(60, 'lecturer3', '$2y$10$SX7kOpXANF202RQTSLxaEOaFpFRIbsnEUUR0ZXK5g2NRKD4WIrrV.', 'lec2@mail.com', '0000000000', 'lecturer', '', '2025-05-28 12:07:11', NULL, 'active', NULL),
(61, 'alice.mutai', '$2y$10$DummyHash1234567890', 'alice.mutai@sun.ac.ug', '+256700000026', 'student', '[]', '2025-05-29 13:00:00', NULL, 'active', NULL),
(62, 'benard.oyoo', '$2y$10$DummyHash1234567890', 'benard.oyoo@sun.ac.ug', '+256700000027', 'student', '[]', '2025-05-29 13:00:00', NULL, 'active', NULL),
(63, 'clara.owino', '$2y$10$DummyHash1234567890', 'clara.owino@sun.ac.ug', '+256700000028', 'student', '[]', '2025-05-29 13:00:00', NULL, 'active', NULL),
(64, 'daniel.kioko', '$2y$10$DummyHash1234567890', 'daniel.kioko@sun.ac.ug', '+256700000029', 'student', '[]', '2025-05-29 13:00:00', NULL, 'active', NULL),
(65, 'esther.kimani', '$2y$10$DummyHash1234567890', 'esther.kimani@sun.ac.ug', '+256700000030', 'student', '[]', '2025-05-29 13:00:00', NULL, 'active', NULL),
(66, 'frank.mwangi', '$2y$10$DummyHash1234567890', 'frank.mwangi@sun.ac.ug', '+256700000031', 'student', '[]', '2025-05-29 13:00:00', NULL, 'active', NULL),
(67, 'gloria.ndege', '$2y$10$DummyHash1234567890', 'gloria.ndege@sun.ac.ug', '+256700000032', 'student', '[]', '2025-05-29 13:00:00', NULL, 'active', NULL),
(68, 'henry.ongoro', '$2y$10$DummyHash1234567890', 'henry.ongoro@sun.ac.ug', '+256700000033', 'student', '[]', '2025-05-29 13:00:00', NULL, 'active', NULL),
(69, 'irene.atieno', '$2y$10$DummyHash1234567890', 'irene.atieno@sun.ac.ug', '+256700000034', 'student', '[]', '2025-05-29 13:00:00', NULL, 'active', NULL),
(70, 'james.mutua', '$2y$10$DummyHash1234567890', 'james.mutua@sun.ac.ug', '+256700000035', 'student', '[]', '2025-05-29 13:00:00', NULL, 'active', NULL),
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
-- Constraints for table `assignments`
--
ALTER TABLE `assignments`
  ADD CONSTRAINT `assignments_ibfk_1` FOREIGN KEY (`unit_id`) REFERENCES `course_units` (`unit_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assignments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `lecturers` (`lecturer_id`);

--
-- Constraints for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `attendance_records_ibfk_2` FOREIGN KEY (`session_id`) REFERENCES `class_sessions` (`session_id`);

--
-- Constraints for table `class_sessions`
--
ALTER TABLE `class_sessions`
  ADD CONSTRAINT `class_sessions_ibfk_1` FOREIGN KEY (`unit_id`) REFERENCES `course_units` (`unit_id`),
  ADD CONSTRAINT `class_sessions_ibfk_2` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`lecturer_id`);

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`);

--
-- Constraints for table `course_units`
--
ALTER TABLE `course_units`
  ADD CONSTRAINT `course_units_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`);

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`school_id`);

--
-- Constraints for table `lecturers`
--
ALTER TABLE `lecturers`
  ADD CONSTRAINT `fk_lecturer_user` FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_lecturers_user_id` FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `lecturers_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`);

--
-- Constraints for table `lecturer_assignments`
--
ALTER TABLE `lecturer_assignments`
  ADD CONSTRAINT `lecturer_assignments_ibfk_1` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`lecturer_id`),
  ADD CONSTRAINT `lecturer_assignments_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `course_units` (`unit_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `course_units` (`unit_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notifications_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD CONSTRAINT `notification_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notification_preferences_ibfk_2` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`notification_id`) ON DELETE CASCADE;

--
-- Constraints for table `semesters`
--
ALTER TABLE `semesters`
  ADD CONSTRAINT `semesters_ibfk_1` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`academic_year_id`);

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_student_user` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_students_academic_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`academic_year_id`),
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`);

--
-- Constraints for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  ADD CONSTRAINT `student_enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `student_enrollments_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `course_units` (`unit_id`);

--
-- Constraints for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
