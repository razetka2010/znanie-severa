-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Хост: sql105.infinityfree.com
-- Время создания: Ноя 30 2025 г., 04:33
-- Версия сервера: 11.4.7-MariaDB
-- Версия PHP: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `if0_40537678_school_diary`
--

-- --------------------------------------------------------

--
-- Структура таблицы `academic_periods`
--

CREATE TABLE `academic_periods` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `lesson_date` date NOT NULL,
  `status` enum('present','absent','late') DEFAULT 'present',
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `grade_level` int(11) NOT NULL,
  `class_teacher_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `classes`
--

INSERT INTO `classes` (`id`, `school_id`, `name`, `grade_level`, `class_teacher_id`, `created_at`) VALUES
(4, 6, '9Б', 9, 16, '2025-11-22 10:57:19');

-- --------------------------------------------------------

--
-- Структура таблицы `class_curriculum`
--

CREATE TABLE `class_curriculum` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_name` varchar(255) NOT NULL,
  `hours_per_week` int(11) NOT NULL,
  `hours_per_year` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `curriculum`
--

CREATE TABLE `curriculum` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `grades` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Структура таблицы `grades`
--

CREATE TABLE `grades` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `grade_value` varchar(10) DEFAULT NULL,
  `lesson_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `grade_types`
--

CREATE TABLE `grade_types` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `weight` int(11) NOT NULL DEFAULT 10,
  `description` text DEFAULT NULL,
  `min_value` decimal(5,2) DEFAULT 0.00,
  `max_value` decimal(5,2) DEFAULT 5.00,
  `is_numeric` tinyint(1) DEFAULT 1,
  `color` varchar(7) DEFAULT '#667eea',
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `min_score` int(11) NOT NULL DEFAULT 0,
  `max_score` int(11) NOT NULL DEFAULT 5
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `grade_types`
--

INSERT INTO `grade_types` (`id`, `school_id`, `name`, `weight`, `description`, `min_value`, `max_value`, `is_numeric`, `color`, `display_order`, `is_active`, `created_at`, `updated_at`, `min_score`, `max_score`) VALUES
(1, 5, 'Самостоятельная работа', 20, 'Краткая проверочная работа на уроке', '0.00', '5.00', 1, '#667eea', 0, 1, '2025-11-19 20:25:49', '2025-11-20 05:08:47', 0, 5),
(2, 5, 'Контрольная работа', 30, 'Полноценная контрольная работа', '0.00', '5.00', 1, '#667eea', 0, 1, '2025-11-19 20:25:49', '2025-11-20 05:08:47', 0, 5),
(3, 5, 'Лабораторная работа', 20, 'Практическая лабораторная работа', '0.00', '5.00', 1, '#667eea', 0, 1, '2025-11-19 20:25:49', '2025-11-20 05:08:47', 0, 5),
(4, 5, 'Сочинение', 30, 'Письменная творческая работа', '0.00', '5.00', 1, '#667eea', 0, 1, '2025-11-19 20:25:49', '2025-11-20 05:08:47', 0, 5),
(5, 5, 'Изложение', 30, 'Письменная работа по тексту', '0.00', '5.00', 1, '#667eea', 0, 1, '2025-11-19 20:25:49', '2025-11-20 05:08:47', 0, 5),
(6, 5, 'Домашнее задание', 10, 'Регулярное домашнее задание', '0.00', '5.00', 1, '#667eea', 0, 1, '2025-11-19 20:25:49', '2025-11-20 05:08:47', 0, 5),
(7, 5, 'Ответ на уроке', 10, 'Устный ответ на уроке', '0.00', '5.00', 1, '#667eea', 0, 1, '2025-11-19 20:25:49', '2025-11-20 05:08:47', 0, 5);

-- --------------------------------------------------------

--
-- Структура таблицы `grade_weights`
--

CREATE TABLE `grade_weights` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `short_name` varchar(20) NOT NULL,
  `weight` int(11) NOT NULL DEFAULT 10,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#667eea',
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `homework`
--

CREATE TABLE `homework` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `attachment_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `homework_completion`
--

CREATE TABLE `homework_completion` (
  `id` int(11) NOT NULL,
  `homework_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `student_comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `parents`
--

CREATE TABLE `parents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `relationship` enum('mother','father','guardian') DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `parent_students`
--

CREATE TABLE `parent_students` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `relationship` varchar(50) DEFAULT 'родитель',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `report_files`
--

CREATE TABLE `report_files` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `permissions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `permissions`, `created_at`) VALUES
(1, 'super_admin', 'Главный администратор системы', '[\"view_dashboard\",\"manage_profile\",\"view_schools\",\"manage_schools\",\"view_users\",\"manage_users\",\"reset_passwords\",\"view_roles\",\"manage_roles\",\"view_curriculum\",\"manage_curriculum\",\"view_academic_periods\",\"manage_academic_periods\",\"view_classes\",\"manage_classes\",\"view_subjects\",\"manage_subjects\",\"view_students\",\"manage_grades\",\"manage_homework\",\"view_attendance\",\"manage_attendance\",\"view_reports\",\"generate_reports\",\"export_data\"]', '2025-11-16 20:44:48'),
(2, 'school_admin', 'Администратор школы', '[\"view_dashboard\",\"manage_profile\",\"view_schools\",\"manage_schools\",\"view_users\",\"manage_users\",\"reset_passwords\",\"view_roles\",\"manage_roles\",\"view_curriculum\",\"manage_curriculum\",\"view_academic_periods\",\"manage_academic_periods\",\"view_reports\",\"generate_reports\",\"export_data\"]', '2025-11-16 20:44:48'),
(3, 'teacher', 'Учитель', '[\"view_dashboard\",\"manage_profile\",\"view_students\",\"manage_grades\",\"manage_homework\",\"view_attendance\",\"manage_attendance\"]', '2025-11-16 20:44:48'),
(5, 'student', 'Ученик', '[\"view_dashboard\",\"manage_profile\"]', '2025-11-16 20:44:48'),
(24, 'parent', 'Родитель', NULL, '2025-11-19 20:38:18');

-- --------------------------------------------------------

--
-- Структура таблицы `schedule`
--

CREATE TABLE `schedule` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `lesson_date` date NOT NULL,
  `lesson_number` int(11) DEFAULT NULL,
  `room` varchar(20) DEFAULT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `schedule`
--

INSERT INTO `schedule` (`id`, `school_id`, `class_id`, `subject_id`, `teacher_id`, `lesson_date`, `lesson_number`, `room`, `is_completed`, `created_at`) VALUES
(2, 6, 4, 28, 16, '2025-12-01', 5, '205', 0, '2025-11-25 19:12:02'),
(3, 6, 4, 28, 16, '2025-12-03', 5, '205', 0, '2025-11-25 19:12:02'),
(4, 6, 4, 28, 16, '2025-12-05', 5, '205', 0, '2025-11-25 19:12:02'),
(5, 6, 4, 28, 16, '2025-12-08', 5, '205', 0, '2025-11-25 19:12:02'),
(6, 6, 4, 28, 16, '2025-12-10', 5, '205', 0, '2025-11-25 19:12:02'),
(7, 6, 4, 28, 16, '2025-12-12', 5, '205', 0, '2025-11-25 19:12:02'),
(8, 6, 4, 28, 16, '2025-12-15', 5, '205', 0, '2025-11-25 19:12:02'),
(9, 6, 4, 28, 16, '2025-12-17', 5, '205', 0, '2025-11-25 19:12:02'),
(10, 6, 4, 28, 16, '2025-12-19', 5, '205', 0, '2025-11-25 19:12:02'),
(11, 6, 4, 28, 16, '2025-12-22', 5, '205', 0, '2025-11-25 19:12:02'),
(12, 6, 4, 28, 16, '2025-12-24', 5, '205', 0, '2025-11-25 19:12:02'),
(13, 6, 4, 28, 16, '2025-12-26', 5, '205', 0, '2025-11-25 19:12:02'),
(14, 6, 4, 28, 16, '2025-12-29', 5, '205', 0, '2025-11-25 19:12:02'),
(15, 6, 4, 28, 16, '2025-12-31', 5, '205', 0, '2025-11-25 19:12:02'),
(16, 6, 4, 28, 16, '2025-12-02', 7, '205', 0, '2025-11-25 19:14:43'),
(17, 6, 4, 28, 16, '2025-12-03', 7, '205', 0, '2025-11-25 19:14:43'),
(18, 6, 4, 28, 16, '2025-12-05', 7, '205', 0, '2025-11-25 19:14:43'),
(19, 6, 4, 28, 16, '2025-12-09', 7, '205', 0, '2025-11-25 19:14:43'),
(20, 6, 4, 28, 16, '2025-12-10', 7, '205', 0, '2025-11-25 19:14:43'),
(21, 6, 4, 28, 16, '2025-12-12', 7, '205', 0, '2025-11-25 19:14:43'),
(22, 6, 4, 28, 16, '2025-12-16', 7, '205', 0, '2025-11-25 19:14:43'),
(23, 6, 4, 28, 16, '2025-12-17', 7, '205', 0, '2025-11-25 19:14:43'),
(24, 6, 4, 28, 16, '2025-12-19', 7, '205', 0, '2025-11-25 19:14:43'),
(25, 6, 4, 28, 16, '2025-12-23', 7, '205', 0, '2025-11-25 19:14:43'),
(26, 6, 4, 28, 16, '2025-12-24', 7, '205', 0, '2025-11-25 19:14:43'),
(27, 6, 4, 28, 16, '2025-12-26', 7, '205', 0, '2025-11-25 19:14:43'),
(28, 6, 4, 28, 16, '2025-12-30', 7, '205', 0, '2025-11-25 19:14:43'),
(29, 6, 4, 28, 16, '2025-12-31', 7, '205', 0, '2025-11-25 19:14:43');

-- --------------------------------------------------------

--
-- Структура таблицы `schools`
--

CREATE TABLE `schools` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `short_name` varchar(100) DEFAULT NULL,
  `inn` varchar(20) NOT NULL,
  `type` enum('общеобразовательная','гимназия','лицей','интернат') DEFAULT 'общеобразовательная',
  `status` enum('активная','неактивная','архив') DEFAULT 'активная',
  `legal_address` text DEFAULT NULL,
  `physical_address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `website` varchar(100) DEFAULT NULL,
  `director_name` varchar(100) DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `license_date` date DEFAULT NULL,
  `license_issued_by` varchar(255) DEFAULT NULL,
  `accreditation_number` varchar(50) DEFAULT NULL,
  `accreditation_date` date DEFAULT NULL,
  `accreditation_until` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `schools`
--

INSERT INTO `schools` (`id`, `full_name`, `short_name`, `inn`, `type`, `status`, `legal_address`, `physical_address`, `phone`, `email`, `website`, `director_name`, `license_number`, `license_date`, `license_issued_by`, `accreditation_number`, `accreditation_date`, `accreditation_until`, `created_at`, `updated_at`) VALUES
(6, 'Муниципальное бюджетное общеобразовательное учреждение \"Средняя школа №11\"', 'МБОУ СШ №11', '8904012195', 'общеобразовательная', 'активная', 'fuyi', 'fk', '+7 (953) 368-79-85', 'esqkpbv@no.vsmailpro.com', 'https://shkola11-nur.yanao.ru/', 'duk', '555', '2025-10-31', 'Департамент образования Ямало-Ненецкого автономного округа', '8888', '2025-11-20', '2025-11-13', '2025-11-22 08:07:25', '2025-11-22 08:07:25');

-- --------------------------------------------------------

--
-- Структура таблицы `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('male','female') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `student_info`
--

CREATE TABLE `student_info` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `student_parents`
--

CREATE TABLE `student_parents` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `relationship` varchar(50) DEFAULT 'parent',
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `study_periods`
--

CREATE TABLE `study_periods` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `academic_year` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `study_periods`
--

INSERT INTO `study_periods` (`id`, `school_id`, `name`, `start_date`, `end_date`, `is_active`, `academic_year`, `created_at`) VALUES
(1, 6, '1 четверть', '2025-09-01', '2025-10-31', 1, '2025-2026', '2025-11-27 15:36:30'),
(2, 6, '2 четверть', '2025-11-01', '2025-12-31', 0, '2025-2026', '2025-11-27 15:36:30'),
(3, 6, '3 четверть', '2026-01-09', '2026-03-22', 0, '2025-2026', '2025-11-27 15:36:30'),
(4, 6, '4 четверть', '2026-04-01', '2026-05-31', 0, '2025-2026', '2025-11-27 15:36:30');

-- --------------------------------------------------------

--
-- Структура таблицы `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `short_name` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `subjects`
--

INSERT INTO `subjects` (`id`, `school_id`, `name`, `short_name`, `description`, `is_active`, `created_at`) VALUES
(19, 6, 'Математика', 'Матем', NULL, 1, '2025-11-22 11:51:57'),
(20, 6, 'Русский язык', 'Рус яз', NULL, 1, '2025-11-22 11:51:57'),
(21, 6, 'Литература', 'Лит-ра', NULL, 1, '2025-11-22 11:51:57'),
(22, 6, 'История', 'Ист', NULL, 1, '2025-11-22 11:51:57'),
(23, 6, 'Обществознание', 'Общ', NULL, 1, '2025-11-22 11:51:57'),
(24, 6, 'География', 'Геогр', NULL, 1, '2025-11-22 11:51:57'),
(25, 6, 'Биология', 'Биол', NULL, 1, '2025-11-22 11:51:57'),
(26, 6, 'Физика', 'Физ', NULL, 1, '2025-11-22 11:51:57'),
(27, 6, 'Химия', 'Хим', NULL, 1, '2025-11-22 11:51:57'),
(28, 6, 'Английский язык', 'Англ', NULL, 1, '2025-11-22 11:51:57'),
(29, 6, 'Информатика', 'Инф', NULL, 1, '2025-11-22 11:51:57'),
(30, 6, 'Физкультура', 'Физ-ра', NULL, 1, '2025-11-22 11:51:57'),
(31, 6, 'Музыка', 'Муз', NULL, 1, '2025-11-22 11:51:57'),
(32, 6, 'ИЗО', 'ИЗО', NULL, 1, '2025-11-22 11:51:57'),
(33, 6, 'Технология', 'Техн', NULL, 1, '2025-11-22 11:51:57');

-- --------------------------------------------------------

--
-- Структура таблицы `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subjects` text DEFAULT NULL,
  `qualification` varchar(255) DEFAULT NULL,
  `experience_years` int(11) DEFAULT NULL,
  `education` text DEFAULT NULL,
  `specialization` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `teacher_events`
--

CREATE TABLE `teacher_events` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `event_type` enum('lesson','meeting','event','reminder','exam') DEFAULT 'event',
  `class_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `teaching_materials`
--

CREATE TABLE `teaching_materials` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `subject_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `school_id` int(11) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `login` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('male','female') DEFAULT NULL,
  `work_place` varchar(255) DEFAULT NULL,
  `passport_series` varchar(4) DEFAULT NULL,
  `passport_number` varchar(6) DEFAULT NULL,
  `snils` varchar(14) DEFAULT NULL,
  `iin` varchar(12) DEFAULT NULL,
  `parent_name` varchar(255) DEFAULT NULL,
  `parent_phone` varchar(20) DEFAULT NULL,
  `parent_email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `qualification` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `school_id`, `class_id`, `login`, `email`, `password_hash`, `full_name`, `position`, `birth_date`, `gender`, `work_place`, `passport_series`, `passport_number`, `snils`, `iin`, `parent_name`, `parent_phone`, `parent_email`, `address`, `qualification`, `phone`, `role_id`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(4, NULL, NULL, 'superadmin', 'superadmin@school.ru', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Главный Администратор', 'Системный администратор', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', 1, 1, '2025-11-20 06:14:28', '2025-11-16 21:33:01', '2025-11-20 06:14:28'),
(16, 6, NULL, 'test2', 'cmncmgog@no.vsmailpro', '$2y$10$NsywySJI/ezf9iJAokA1P.Ke6ChNpkeuZ2baGcPBTm/AXObF7QvhC', 'test2', 'test', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '+7 (734) 942-51-749', 1, 1, NULL, '2025-11-22 08:08:39', '2025-11-27 18:03:04'),
(19, 6, NULL, 'test1', 'ulmnq@comfythings.com', '$2y$10$9R6C/cX1wC6n0sZezVmMQOPeJxqOvD0.JqZcpnzmU./yasDU5gfTe', 'test1', 'test', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '+7 (734) 942-51-749', 3, 1, NULL, '2025-11-27 16:44:24', '2025-11-27 18:03:40'),
(20, 6, NULL, 'test3', '0gehp@comfythings.com', '$2y$10$GADzq8mDlGEh8gytaYA7s.Z92cYJUFYveF1AuP/62GMZ1f2Hxv.7S', 'test', 'test', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '9088647354', 5, 1, NULL, '2025-11-27 18:04:48', '2025-11-27 18:04:48'),
(21, 6, NULL, 'test4', 'cx653@comfythings.com', '$2y$10$Md5EWeYQztWqn5yP8xJcuelZzg09GORYjlZvBWYL2nn35.3pr0BBi', 'test', 'test', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '+7 (734) 942-51-749', 2, 1, NULL, '2025-11-27 18:05:55', '2025-11-27 18:05:55');

-- --------------------------------------------------------

--
-- Структура таблицы `user_logs`
--

CREATE TABLE `user_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `academic_periods`
--
ALTER TABLE `academic_periods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_academic_periods_school` (`school_id`),
  ADD KEY `idx_academic_periods_dates` (`start_date`,`end_date`),
  ADD KEY `idx_academic_periods_current` (`is_current`);

--
-- Индексы таблицы `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `class_id` (`class_id`);

--
-- Индексы таблицы `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `school_id` (`school_id`),
  ADD KEY `class_teacher_id` (`class_teacher_id`);

--
-- Индексы таблицы `class_curriculum`
--
ALTER TABLE `class_curriculum`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`);

--
-- Индексы таблицы `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `school_id` (`school_id`);

--
-- Индексы таблицы `grade_types`
--
ALTER TABLE `grade_types`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `grade_weights`
--
ALTER TABLE `grade_weights`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_school_weight_name` (`school_id`,`name`);

--
-- Индексы таблицы `homework`
--
ALTER TABLE `homework`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Индексы таблицы `homework_completion`
--
ALTER TABLE `homework_completion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_homework_student` (`homework_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Индексы таблицы `parents`
--
ALTER TABLE `parents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Индексы таблицы `parent_students`
--
ALTER TABLE `parent_students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_parent_student` (`parent_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Индексы таблицы `report_files`
--
ALTER TABLE `report_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Индексы таблицы `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Индексы таблицы `schedule`
--
ALTER TABLE `schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `school_id` (`school_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Индексы таблицы `schools`
--
ALTER TABLE `schools`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `inn` (`inn`);

--
-- Индексы таблицы `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `class_id` (`class_id`);

--
-- Индексы таблицы `student_info`
--
ALTER TABLE `student_info`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `student_parents`
--
ALTER TABLE `student_parents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_parent` (`student_id`,`parent_id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Индексы таблицы `study_periods`
--
ALTER TABLE `study_periods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `school_id` (`school_id`);

--
-- Индексы таблицы `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `school_id` (`school_id`);

--
-- Индексы таблицы `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_teacher_school` (`user_id`,`school_id`),
  ADD KEY `school_id` (`school_id`);

--
-- Индексы таблицы `teacher_events`
--
ALTER TABLE `teacher_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `class_id` (`class_id`);

--
-- Индексы таблицы `teaching_materials`
--
ALTER TABLE `teaching_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `login` (`login`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `school_id` (`school_id`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `class_id` (`class_id`);

--
-- Индексы таблицы `user_logs`
--
ALTER TABLE `user_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_sessions_user` (`user_id`),
  ADD KEY `idx_user_sessions_created` (`created_at`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `academic_periods`
--
ALTER TABLE `academic_periods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `class_curriculum`
--
ALTER TABLE `class_curriculum`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `curriculum`
--
ALTER TABLE `curriculum`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT для таблицы `grade_types`
--
ALTER TABLE `grade_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT для таблицы `grade_weights`
--
ALTER TABLE `grade_weights`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT для таблицы `homework`
--
ALTER TABLE `homework`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `homework_completion`
--
ALTER TABLE `homework_completion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `parents`
--
ALTER TABLE `parents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `parent_students`
--
ALTER TABLE `parent_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `report_files`
--
ALTER TABLE `report_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT для таблицы `schedule`
--
ALTER TABLE `schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT для таблицы `schools`
--
ALTER TABLE `schools`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `student_info`
--
ALTER TABLE `student_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `student_parents`
--
ALTER TABLE `student_parents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `study_periods`
--
ALTER TABLE `study_periods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT для таблицы `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `teacher_events`
--
ALTER TABLE `teacher_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `teaching_materials`
--
ALTER TABLE `teaching_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT для таблицы `user_logs`
--
ALTER TABLE `user_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `academic_periods`
--
ALTER TABLE `academic_periods`
  ADD CONSTRAINT `academic_periods_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `classes_ibfk_2` FOREIGN KEY (`class_teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `class_curriculum`
--
ALTER TABLE `class_curriculum`
  ADD CONSTRAINT `class_curriculum_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `grades_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `grades_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`);

--
-- Ограничения внешнего ключа таблицы `grade_weights`
--
ALTER TABLE `grade_weights`
  ADD CONSTRAINT `grade_weights_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `homework`
--
ALTER TABLE `homework`
  ADD CONSTRAINT `homework_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `homework_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `homework_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`);

--
-- Ограничения внешнего ключа таблицы `homework_completion`
--
ALTER TABLE `homework_completion`
  ADD CONSTRAINT `homework_completion_ibfk_1` FOREIGN KEY (`homework_id`) REFERENCES `homework` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `homework_completion_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `parents`
--
ALTER TABLE `parents`
  ADD CONSTRAINT `parents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `parents_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `parent_students`
--
ALTER TABLE `parent_students`
  ADD CONSTRAINT `parent_students_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `parent_students_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `report_files`
--
ALTER TABLE `report_files`
  ADD CONSTRAINT `report_files_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `schedule`
--
ALTER TABLE `schedule`
  ADD CONSTRAINT `schedule_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  ADD CONSTRAINT `schedule_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `schedule_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  ADD CONSTRAINT `schedule_ibfk_4` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`);

--
-- Ограничения внешнего ключа таблицы `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `student_info`
--
ALTER TABLE `student_info`
  ADD CONSTRAINT `student_info_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `student_parents`
--
ALTER TABLE `student_parents`
  ADD CONSTRAINT `student_parents_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_parents_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `study_periods`
--
ALTER TABLE `study_periods`
  ADD CONSTRAINT `study_periods_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`);

--
-- Ограничения внешнего ключа таблицы `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teachers_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `teacher_events`
--
ALTER TABLE `teacher_events`
  ADD CONSTRAINT `teacher_events_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `teacher_events_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`);

--
-- Ограничения внешнего ключа таблицы `teaching_materials`
--
ALTER TABLE `teaching_materials`
  ADD CONSTRAINT `teaching_materials_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `teaching_materials_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `teaching_materials_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`);

--
-- Ограничения внешнего ключа таблицы `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  ADD CONSTRAINT `users_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `user_logs`
--
ALTER TABLE `user_logs`
  ADD CONSTRAINT `user_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
