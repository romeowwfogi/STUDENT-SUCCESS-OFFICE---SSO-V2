-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 13, 2025 at 05:02 PM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u337253893_PLPasigSSO`
--

-- --------------------------------------------------------

--
-- Table structure for table `acc_locking`
--

CREATE TABLE `acc_locking` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `attempt_left` int(11) DEFAULT 5,
  `last_attempt_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `acc_locking`
--

INSERT INTO `acc_locking` (`id`, `user_id`, `attempt_left`, `last_attempt_at`) VALUES
(1, 1, 5, '2025-10-17 16:10:01'),
(2, 2, 5, '2025-11-03 04:31:28'),
(3, 3, 5, '2025-11-07 20:34:12'),
(4, 4, 5, '2025-11-11 02:49:55'),
(5, 5, 5, '2025-11-11 02:54:03'),
(11, 11, 5, '2025-11-12 06:17:59'),
(19, 19, 5, '2025-11-13 14:48:37');

-- --------------------------------------------------------

--
-- Table structure for table `admission_controller`
--

CREATE TABLE `admission_controller` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `can_apply` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admission_controller`
--

INSERT INTO `admission_controller` (`id`, `user_id`, `can_apply`) VALUES
(1, 5, 0),
(2, 11, 0),
(3, 1, 0),
(4, 1, 0),
(5, 19, 0);

-- --------------------------------------------------------

--
-- Table structure for table `admission_cycles`
--

CREATE TABLE `admission_cycles` (
  `id` int(11) NOT NULL,
  `admission_date_time_start` datetime NOT NULL,
  `admission_date_time_end` datetime NOT NULL,
  `academic_year_start` year(4) DEFAULT NULL,
  `academic_year_end` year(4) DEFAULT NULL,
  `is_automatically_open_closed` tinyint(1) NOT NULL DEFAULT 0,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admission_cycles`
--

INSERT INTO `admission_cycles` (`id`, `admission_date_time_start`, `admission_date_time_end`, `academic_year_start`, `academic_year_end`, `is_automatically_open_closed`, `is_archived`) VALUES
(1, '2025-11-10 06:32:00', '2025-11-17 06:32:00', '2025', '2026', 0, 0),
(2, '2025-11-10 12:58:00', '2025-11-17 12:58:00', '2025', '2026', 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `announcement`
--

CREATE TABLE `announcement` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `tag` varchar(100) DEFAULT NULL,
  `date_added` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcement_tag`
--

CREATE TABLE `announcement_tag` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `hex_color` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `api_list`
--

CREATE TABLE `api_list` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `api_url` varchar(1000) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `api_list`
--

INSERT INTO `api_list` (`id`, `name`, `api_url`, `description`, `created_at`, `updated_at`) VALUES
(1, 'UPDATE_GENERAL_IMAGES', 'https://gold-lion-549609.hostingersite.com/update-general-upload.php', 'handles updating existing image assets in the general image system. It replaces or refreshes stored images with new versions, ensuring metadata consistency, optimized file storage, and proper linkage across dependent modules.', '2025-10-29 22:21:49', '2025-10-29 22:47:26'),
(2, 'SSO_EMAIL_SEND', 'https://gold-lion-549609.hostingersite.com/email-api.php', 'represents an API action that handles emails.', '2025-10-29 22:21:49', '2025-10-30 21:41:50'),
(3, 'EXAM_PERMIT_GENERATOR', 'https://gold-lion-549609.hostingersite.com/exam-permit.php', 'generator of an exam permit', '2025-10-29 22:21:49', '2025-10-30 21:41:50'),
(4, 'UPLOAD_REQUIREMENTS_IMAGES', 'https://gold-lion-549609.hostingersite.com/upload.php', 'handles updating existing image assets in the requirements image system.', '2025-10-29 22:21:49', '2025-11-02 00:08:28'),
(5, 'UPDATE_REQUIREMENTS_API', 'https://gold-lion-549609.hostingersite.com/update-requirement.php', 'handles image assets in the requirements image system.', '2025-10-29 22:21:49', '2025-11-02 20:25:30'),
(6, 'UPLOAD_REQUIREMENTS_BASE_URL', 'https://gold-lion-549609.hostingersite.com/', 'handles image assets in the requirements image system.', '2025-10-29 22:21:49', '2025-11-02 20:24:08'),
(7, 'PREVIEW_REQUIREMENTS_URL', 'https://gold-lion-549609.hostingersite.com/preview-requirement.php', 'handles image assets in the requirements image system.', '2025-10-29 22:21:49', '2025-11-02 20:24:08'),
(9, 'LANDING_PAGE_URL', 'https://plpasig-admission.icu/', 'appears to be a web address for an online admission or enrollment portal, possibly related to Pamantasan ng Lungsod ng Pasig (PLPasig). It likely hosts information or forms for student applications and admissions.', '2025-11-13 14:57:37', '2025-11-13 14:57:37');

-- --------------------------------------------------------

--
-- Table structure for table `applicant_number_prefix`
--

CREATE TABLE `applicant_number_prefix` (
  `id` int(11) NOT NULL,
  `prefix` varchar(255) NOT NULL,
  `date_added` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `applicant_number_prefix`
--

INSERT INTO `applicant_number_prefix` (`id`, `prefix`, `date_added`) VALUES
(1, 'PLPPasig', '2025-10-31 21:04:45'),
(2, 'Admission2025', '2025-10-31 21:04:45');

-- --------------------------------------------------------

--
-- Table structure for table `applicant_types`
--

CREATE TABLE `applicant_types` (
  `id` int(11) NOT NULL,
  `admission_cycle_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applicant_types`
--

INSERT INTO `applicant_types` (`id`, `admission_cycle_id`, `name`, `is_active`) VALUES
(1, 1, 'SHS Graduate', 1),
(2, 1, 'Transferee', 1),
(3, 1, 'On-Going Grade 12', 1),
(4, 2, 'Testing', 1),
(5, 1, 'Testing', 0);

-- --------------------------------------------------------

--
-- Table structure for table `application_permit`
--

CREATE TABLE `application_permit` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `applicant_number` varchar(250) NOT NULL,
  `status` enum('pending','used') NOT NULL DEFAULT 'pending',
  `date_sent` timestamp NOT NULL DEFAULT current_timestamp(),
  `admission_officer` varchar(128) DEFAULT NULL,
  `applicant_name` varchar(128) DEFAULT NULL,
  `exam_date` date DEFAULT NULL,
  `exam_time` time DEFAULT NULL,
  `room_no` varchar(64) DEFAULT NULL,
  `exam_venue` varchar(128) DEFAULT NULL,
  `application_period_start` date DEFAULT NULL,
  `application_period_end` date DEFAULT NULL,
  `application_period_text` varchar(128) DEFAULT NULL,
  `accent_color` char(7) NOT NULL DEFAULT '#18a558',
  `qr_text` varchar(255) DEFAULT NULL,
  `download_url` text DEFAULT NULL,
  `email_subject` varchar(255) DEFAULT NULL,
  `email_body` mediumtext DEFAULT NULL,
  `email_status` enum('queued','sent','failed') DEFAULT 'queued',
  `email_sent_at` datetime DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `application_permit`
--

INSERT INTO `application_permit` (`id`, `user_id`, `applicant_number`, `status`, `date_sent`, `admission_officer`, `applicant_name`, `exam_date`, `exam_time`, `room_no`, `exam_venue`, `application_period_start`, `application_period_end`, `application_period_text`, `accent_color`, `qr_text`, `download_url`, `email_subject`, `email_body`, `email_status`, `email_sent_at`, `used_at`, `updated_at`) VALUES
(17, 5, 'PLPPasig-10000000', 'pending', '2025-11-11 19:44:16', 'Arlene Daniel', 'Mark Andrie Datus', '2025-11-17', '04:28:00', 'Room 301', '3rd Floor • Room 301', '2025-11-10', '2025-11-17', 'November 10 to November 17, 2025', '#f00000', 'PLPPasig-10000000', 'https://gold-lion-549609.hostingersite.com/permit.php?permit_no=PLPPasig-10000000', 'PLP - Student Success Office [Exam Permit]', '<!DOCTYPE html>\n<html>\n\n<head>\n    <meta charset=\"UTF-8\">\n    <title>{{subject}}</title>\n    <link href=\"https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap\" rel=\"stylesheet\">\n    <style>\n        body {\n            font-family: \'Poppins\', Arial, sans-serif;\n            background-color: #f4f4f4;\n            padding: 20px;\n            margin: 0;\n        }\n\n        .email-container {\n            max-width: 600px;\n            margin: 0 auto;\n            background: #ffffff;\n            border-radius: 8px;\n            overflow: hidden;\n            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);\n        }\n\n        /* Header */\n        .header-table {\n            width: 100%;\n            background: #ffffff;\n            padding: 10px 15px;\n        }\n\n        .header-table img {\n            max-height: 70px;\n            vertical-align: middle;\n            margin: 0 5px;\n        }\n\n        .header-text {\n            text-align: center;\n            padding-top: 5px;\n            padding-bottom: 10px;\n        }\n\n        .header-text .school-name {\n            background: #0c326f;\n            color: white;\n            font-weight: 600;\n            font-size: 12px;\n            padding: 10px;\n            display: inline-block;\n            border-radius: 15px 0 0 15px;\n            margin-bottom: 4px;\n        }\n\n        .header-text .college-name {\n            font-size: 14px;\n            font-weight: 600;\n            color: #000;\n        }\n\n        .header-text .address {\n            font-size: 12px;\n            color: #333;\n        }\n\n        /* Content */\n        .content {\n            padding: 20px;\n            font-size: 14px;\n            line-height: 1.6;\n            color: #333;\n        }\n\n        .content p {\n            margin: 0 0 15px;\n        }\n\n        .button {\n            display: inline-block;\n            background: #004aad;\n            color: #fff !important;\n            padding: 10px 15px;\n            text-decoration: none;\n            border-radius: 4px;\n            margin-top: 10px;\n        }\n\n        .notice {\n            font-size: 13px;\n            color: #555;\n            background: #f8f8f8;\n            padding: 10px;\n            border-radius: 6px;\n            margin-top: 20px;\n            border-left: 4px solid #004aad;\n        }\n\n        .official-email {\n            text-align: center;\n            font-size: 14px;\n            color: #004aad;\n            font-weight: 600;\n            margin-top: 5px;\n        }\n\n        .footer {\n            font-size: 12px;\n            color: #888;\n            text-align: center;\n            padding: 15px;\n            background: #f9f9f9;\n        }\n\n        /* ✅ Responsive Header for Mobile */\n        @media only screen and (max-width: 480px) {\n            .header-table td {\n                display: block !important;\n                width: 100% !important;\n                text-align: center !important;\n            }\n\n            .header-table img {\n                display: inline-block !important;\n                max-height: 60px !important;\n                margin: 5px 3px !important;\n            }\n\n            .header-table td[align=\"right\"] {\n                text-align: center !important;\n                padding-top: 10px !important;\n            }\n        }\n    </style>\n</head>\n\n<body>\n    <div class=\"email-container\">\n        <!-- HEADER -->\n        <table class=\"header-table\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">\n            <tr>\n                <!-- LOGOS -->\n                <td align=\"left\" valign=\"middle\" style=\"white-space:nowrap;\">\n                    <img src=\"https://gcdnb.pbrd.co/images/Pni5FEz4UOEJ.png?o=1\" alt=\"PLP Logo\">\n                    <img src=\"https://gcdnb.pbrd.co/images/EFxDeFIopVQN.png?o=1\" alt=\"SSO Logo\">\n                </td>\n\n                <!-- TEXT -->\n                <td align=\"right\" valign=\"middle\" style=\"text-align:right;\">\n                    <div style=\"background:#0c326f; color:#fff; font-weight:600; font-size:12px; padding:5px 10px; display:inline-block; border-radius:15px 0 0 15px; margin-bottom:4px;\">\n                        PAMANTASAN NG LUNGSOD NG PASIG\n                    </div><br>\n                    <div style=\"font-size:14px; font-weight:600; color:#000;\">\n                        Student Success Office\n                    </div>\n                    <div style=\"font-size:12px; color:#333;\">\n                        Alkalde Jose St. Kapasigan Pasig City, Philippines 1600\n                    </div>\n                </td>\n            </tr>\n        </table>\n\n        <!-- EMAIL CONTENT -->\n        <div class=\"content\">\n            <p>Hello <strong>Mark Andrie Datus</strong>!</p>\n\n            <p>\n                This is to inform you that your admission for\n                <strong>2025 - 2026</strong>-<strong>Testing</strong> has been updated by the\n                <strong>Pamantasan ng Lungsod ng Pasig – Student Success Office (SSO)</strong>.\n            </p>\n\n            <table style=\"width: 100%; border-collapse: collapse; margin: 20px 0;\">\n                <tr>\n                    <td style=\"padding: 8px; border: 1px solid #ddd; font-weight: bold;\">Applicant #</td>\n                    <td style=\"padding: 8px; border: 1px solid #ddd;\">PLPPasig-10000000</td>\n                </tr>\n                <tr>\n                    <td style=\"padding: 8px; border: 1px solid #ddd; font-weight: bold;\">Venue</td>\n                    <td style=\"padding: 8px; border: 1px solid #ddd;\">Room 301 - 3rd Floor</td>\n                </tr>\n                <tr>\n                    <td style=\"padding: 8px; border: 1px solid #ddd; font-weight: bold;\">Start-End Date Time</td>\n                    <td style=\"padding: 8px; border: 1px solid #ddd; color: #004aad; font-weight: bold;\">2025-11-17-04:28:00</td>\n                </tr>\n            </table>\n\n            <p style=\"text-align: center; margin: 25px 0;\">\n                <a href=\"https://gold-lion-549609.hostingersite.com/permit.php?permit_no=PLPPasig-10000000\"\n                    style=\"background-color: #004aad; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 5px; display: inline-block; font-weight: bold; margin-right:10px;\">\n                    Download Exam Permit\n                </a>\n                <a href=\"https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=http%3A%2F%2Flocalhost%2FSTUDENT+SUCCESS+OFFICE+-+SSO+V2%2Fvalidate_exam_permit.php%3Fqr_text%3DPLPPasig-10000000\"\n                    style=\"background-color: #004aad; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 5px; display: inline-block; font-weight: bold;\">\n                    Download QR Code\n                </a>\n            </p>\n\n            <div class=\"notice\">\n                This is an automated notification from the\n                <strong>Student Success Office (SSO)</strong>.\n                For security and authenticity, please note that the <strong>only official sender email address</strong> from our office is:\n                <div class=\"official-email\">plpasig.sso@gmail.com</div>\n                Any other email claiming to represent the SSO should be considered unauthorized.\n            </div>\n\n            <p style=\"margin-top: 20px;\">\n                Best regards,<br>\n                <strong>Pamantasan ng Lungsod ng Pasig<br>\n                    Student Success Office</strong>\n            </p>\n        </div>\n\n\n        <!-- FOOTER -->\n        <div class=\"footer\">\n            <p>Pamantasan ng Lungsod ng Pasig - Student Success Office</p>\n        </div>\n    </div>\n</body>\n\n</html>', 'sent', '2025-11-13 14:07:06', NULL, '2025-11-13 06:07:08'),
(18, 1, 'PLPPasig-00000018', 'pending', '2025-11-13 05:47:08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '#18a558', 'PLPPasig-00000018', NULL, 'PLP - Student Success Office [Exam Permit]', '<!DOCTYPE html>\n<html>\n\n<head>\n    <meta charset=\"UTF-8\">\n    <title>{{subject}}</title>\n    <link href=\"https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap\" rel=\"stylesheet\">\n    <style>\n        body {\n            font-family: \'Poppins\', Arial, sans-serif;\n            background-color: #f4f4f4;\n            padding: 20px;\n            margin: 0;\n        }\n\n        .email-container {\n            max-width: 600px;\n            margin: 0 auto;\n            background: #ffffff;\n            border-radius: 8px;\n            overflow: hidden;\n            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);\n        }\n\n        /* Header */\n        .header-table {\n            width: 100%;\n            background: #ffffff;\n            padding: 10px 15px;\n        }\n\n        .header-table img {\n            max-height: 70px;\n            vertical-align: middle;\n            margin: 0 5px;\n        }\n\n        .header-text {\n            text-align: center;\n            padding-top: 5px;\n            padding-bottom: 10px;\n        }\n\n        .header-text .school-name {\n            background: #0c326f;\n            color: white;\n            font-weight: 600;\n            font-size: 12px;\n            padding: 10px;\n            display: inline-block;\n            border-radius: 15px 0 0 15px;\n            margin-bottom: 4px;\n        }\n\n        .header-text .college-name {\n            font-size: 14px;\n            font-weight: 600;\n            color: #000;\n        }\n\n        .header-text .address {\n            font-size: 12px;\n            color: #333;\n        }\n\n        /* Content */\n        .content {\n            padding: 20px;\n            font-size: 14px;\n            line-height: 1.6;\n            color: #333;\n        }\n\n        .content p {\n            margin: 0 0 15px;\n        }\n\n        .button {\n            display: inline-block;\n            background: #004aad;\n            color: #fff !important;\n            padding: 10px 15px;\n            text-decoration: none;\n            border-radius: 4px;\n            margin-top: 10px;\n        }\n\n        .notice {\n            font-size: 13px;\n            color: #555;\n            background: #f8f8f8;\n            padding: 10px;\n            border-radius: 6px;\n            margin-top: 20px;\n            border-left: 4px solid #004aad;\n        }\n\n        .official-email {\n            text-align: center;\n            font-size: 14px;\n            color: #004aad;\n            font-weight: 600;\n            margin-top: 5px;\n        }\n\n        .footer {\n            font-size: 12px;\n            color: #888;\n            text-align: center;\n            padding: 15px;\n            background: #f9f9f9;\n        }\n\n        /* ✅ Responsive Header for Mobile */\n        @media only screen and (max-width: 480px) {\n            .header-table td {\n                display: block !important;\n                width: 100% !important;\n                text-align: center !important;\n            }\n\n            .header-table img {\n                display: inline-block !important;\n                max-height: 60px !important;\n                margin: 5px 3px !important;\n            }\n\n            .header-table td[align=\"right\"] {\n                text-align: center !important;\n                padding-top: 10px !important;\n            }\n        }\n    </style>\n</head>\n\n<body>\n    <div class=\"email-container\">\n        <!-- HEADER -->\n        <table class=\"header-table\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">\n            <tr>\n                <!-- LOGOS -->\n                <td align=\"left\" valign=\"middle\" style=\"white-space:nowrap;\">\n                    <img src=\"https://gcdnb.pbrd.co/images/Pni5FEz4UOEJ.png?o=1\" alt=\"PLP Logo\">\n                    <img src=\"https://gcdnb.pbrd.co/images/EFxDeFIopVQN.png?o=1\" alt=\"SSO Logo\">\n                </td>\n\n                <!-- TEXT -->\n                <td align=\"right\" valign=\"middle\" style=\"text-align:right;\">\n                    <div style=\"background:#0c326f; color:#fff; font-weight:600; font-size:12px; padding:5px 10px; display:inline-block; border-radius:15px 0 0 15px; margin-bottom:4px;\">\n                        PAMANTASAN NG LUNGSOD NG PASIG\n                    </div><br>\n                    <div style=\"font-size:14px; font-weight:600; color:#000;\">\n                        Student Success Office\n                    </div>\n                    <div style=\"font-size:12px; color:#333;\">\n                        Alkalde Jose St. Kapasigan Pasig City, Philippines 1600\n                    </div>\n                </td>\n            </tr>\n        </table>\n\n        <!-- EMAIL CONTENT -->\n        <div class=\"content\">\n            <p>Hello <strong>Romeo John Ador</strong>!</p>\n\n            <p>\n                This is to inform you that your admission for\n                <strong>2025 - 2026</strong>-<strong>On-Going Grade 12</strong> has been updated by the\n                <strong>Pamantasan ng Lungsod ng Pasig – Student Success Office (SSO)</strong>.\n            </p>\n\n            <table style=\"width: 100%; border-collapse: collapse; margin: 20px 0;\">\n                <tr>\n                    <td style=\"padding: 8px; border: 1px solid #ddd; font-weight: bold;\">Applicant #</td>\n                    <td style=\"padding: 8px; border: 1px solid #ddd;\">PLPPasig-00000018</td>\n                </tr>\n                <tr>\n                    <td style=\"padding: 8px; border: 1px solid #ddd; font-weight: bold;\">Venue</td>\n                    <td style=\"padding: 8px; border: 1px solid #ddd;\"> - </td>\n                </tr>\n                <tr>\n                    <td style=\"padding: 8px; border: 1px solid #ddd; font-weight: bold;\">Start-End Date Time</td>\n                    <td style=\"padding: 8px; border: 1px solid #ddd; color: #004aad; font-weight: bold;\">-</td>\n                </tr>\n            </table>\n\n            <p style=\"text-align: center; margin: 25px 0;\">\n                <a href=\"{{exam_permit_download_link}}\"\n                    style=\"background-color: #004aad; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 5px; display: inline-block; font-weight: bold; margin-right:10px;\">\n                    Download Exam Permit\n                </a>\n                <a href=\"https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=http%3A%2F%2Flocalhost%2FSTUDENT+SUCCESS+OFFICE+-+SSO+V2%2Fvalidate_exam_permit.php%3Fqr_text%3DPLPPasig-00000018\"\n                    style=\"background-color: #004aad; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 5px; display: inline-block; font-weight: bold;\">\n                    Download QR Code\n                </a>\n            </p>\n\n            <div class=\"notice\">\n                This is an automated notification from the\n                <strong>Student Success Office (SSO)</strong>.\n                For security and authenticity, please note that the <strong>only official sender email address</strong> from our office is:\n                <div class=\"official-email\">plpasig.sso@gmail.com</div>\n                Any other email claiming to represent the SSO should be considered unauthorized.\n            </div>\n\n            <p style=\"margin-top: 20px;\">\n                Best regards,<br>\n                <strong>Pamantasan ng Lungsod ng Pasig<br>\n                    Student Success Office</strong>\n            </p>\n        </div>\n\n\n        <!-- FOOTER -->\n        <div class=\"footer\">\n            <p>Pamantasan ng Lungsod ng Pasig - Student Success Office</p>\n        </div>\n    </div>\n</body>\n\n</html>', 'sent', '2025-11-13 14:06:55', NULL, '2025-11-13 06:06:58'),
(19, 11, 'PLPPasig-00000019', 'pending', '2025-11-13 05:47:14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '#18a558', 'PLPPasig-00000019', NULL, 'PLP - Student Success Office [Exam Permit]', '<!DOCTYPE html>\n<html>\n\n<head>\n    <meta charset=\"UTF-8\">\n    <title>{{subject}}</title>\n    <link href=\"https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap\" rel=\"stylesheet\">\n    <style>\n        body {\n            font-family: \'Poppins\', Arial, sans-serif;\n            background-color: #f4f4f4;\n            padding: 20px;\n            margin: 0;\n        }\n\n        .email-container {\n            max-width: 600px;\n            margin: 0 auto;\n            background: #ffffff;\n            border-radius: 8px;\n            overflow: hidden;\n            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);\n        }\n\n        /* Header */\n        .header-table {\n            width: 100%;\n            background: #ffffff;\n            padding: 10px 15px;\n        }\n\n        .header-table img {\n            max-height: 70px;\n            vertical-align: middle;\n            margin: 0 5px;\n        }\n\n        .header-text {\n            text-align: center;\n            padding-top: 5px;\n            padding-bottom: 10px;\n        }\n\n        .header-text .school-name {\n            background: #0c326f;\n            color: white;\n            font-weight: 600;\n            font-size: 12px;\n            padding: 10px;\n            display: inline-block;\n            border-radius: 15px 0 0 15px;\n            margin-bottom: 4px;\n        }\n\n        .header-text .college-name {\n            font-size: 14px;\n            font-weight: 600;\n            color: #000;\n        }\n\n        .header-text .address {\n            font-size: 12px;\n            color: #333;\n        }\n\n        /* Content */\n        .content {\n            padding: 20px;\n            font-size: 14px;\n            line-height: 1.6;\n            color: #333;\n        }\n\n        .content p {\n            margin: 0 0 15px;\n        }\n\n        .button {\n            display: inline-block;\n            background: #004aad;\n            color: #fff !important;\n            padding: 10px 15px;\n            text-decoration: none;\n            border-radius: 4px;\n            margin-top: 10px;\n        }\n\n        .notice {\n            font-size: 13px;\n            color: #555;\n            background: #f8f8f8;\n            padding: 10px;\n            border-radius: 6px;\n            margin-top: 20px;\n            border-left: 4px solid #004aad;\n        }\n\n        .official-email {\n            text-align: center;\n            font-size: 14px;\n            color: #004aad;\n            font-weight: 600;\n            margin-top: 5px;\n        }\n\n        .footer {\n            font-size: 12px;\n            color: #888;\n            text-align: center;\n            padding: 15px;\n            background: #f9f9f9;\n        }\n\n        /* ✅ Responsive Header for Mobile */\n        @media only screen and (max-width: 480px) {\n            .header-table td {\n                display: block !important;\n                width: 100% !important;\n                text-align: center !important;\n            }\n\n            .header-table img {\n                display: inline-block !important;\n                max-height: 60px !important;\n                margin: 5px 3px !important;\n            }\n\n            .header-table td[align=\"right\"] {\n                text-align: center !important;\n                padding-top: 10px !important;\n            }\n        }\n    </style>\n</head>\n\n<body>\n    <div class=\"email-container\">\n        <!-- HEADER -->\n        <table class=\"header-table\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">\n            <tr>\n                <!-- LOGOS -->\n                <td align=\"left\" valign=\"middle\" style=\"white-space:nowrap;\">\n                    <img src=\"https://gcdnb.pbrd.co/images/Pni5FEz4UOEJ.png?o=1\" alt=\"PLP Logo\">\n                    <img src=\"https://gcdnb.pbrd.co/images/EFxDeFIopVQN.png?o=1\" alt=\"SSO Logo\">\n                </td>\n\n                <!-- TEXT -->\n                <td align=\"right\" valign=\"middle\" style=\"text-align:right;\">\n                    <div style=\"background:#0c326f; color:#fff; font-weight:600; font-size:12px; padding:5px 10px; display:inline-block; border-radius:15px 0 0 15px; margin-bottom:4px;\">\n                        PAMANTASAN NG LUNGSOD NG PASIG\n                    </div><br>\n                    <div style=\"font-size:14px; font-weight:600; color:#000;\">\n                        Student Success Office\n                    </div>\n                    <div style=\"font-size:12px; color:#333;\">\n                        Alkalde Jose St. Kapasigan Pasig City, Philippines 1600\n                    </div>\n                </td>\n            </tr>\n        </table>\n\n        <!-- EMAIL CONTENT -->\n        <div class=\"content\">\n            <p>Hello <strong>E Fernandez</strong>!</p>\n\n            <p>\n                This is to inform you that your admission for\n                <strong>2025 - 2026</strong>-<strong>On-Going Grade 12</strong> has been updated by the\n                <strong>Pamantasan ng Lungsod ng Pasig – Student Success Office (SSO)</strong>.\n            </p>\n\n            <table style=\"width: 100%; border-collapse: collapse; margin: 20px 0;\">\n                <tr>\n                    <td style=\"padding: 8px; border: 1px solid #ddd; font-weight: bold;\">Applicant #</td>\n                    <td style=\"padding: 8px; border: 1px solid #ddd;\">PLPPasig-00000019</td>\n                </tr>\n                <tr>\n                    <td style=\"padding: 8px; border: 1px solid #ddd; font-weight: bold;\">Venue</td>\n                    <td style=\"padding: 8px; border: 1px solid #ddd;\"> - </td>\n                </tr>\n                <tr>\n                    <td style=\"padding: 8px; border: 1px solid #ddd; font-weight: bold;\">Start-End Date Time</td>\n                    <td style=\"padding: 8px; border: 1px solid #ddd; color: #004aad; font-weight: bold;\">-</td>\n                </tr>\n            </table>\n\n            <p style=\"text-align: center; margin: 25px 0;\">\n                <a href=\"{{exam_permit_download_link}}\"\n                    style=\"background-color: #004aad; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 5px; display: inline-block; font-weight: bold; margin-right:10px;\">\n                    Download Exam Permit\n                </a>\n                <a href=\"https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=http%3A%2F%2Flocalhost%2FSTUDENT+SUCCESS+OFFICE+-+SSO+V2%2Fvalidate_exam_permit.php%3Fqr_text%3DPLPPasig-00000019\"\n                    style=\"background-color: #004aad; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 5px; display: inline-block; font-weight: bold;\">\n                    Download QR Code\n                </a>\n            </p>\n\n            <div class=\"notice\">\n                This is an automated notification from the\n                <strong>Student Success Office (SSO)</strong>.\n                For security and authenticity, please note that the <strong>only official sender email address</strong> from our office is:\n                <div class=\"official-email\">plpasig.sso@gmail.com</div>\n                Any other email claiming to represent the SSO should be considered unauthorized.\n            </div>\n\n            <p style=\"margin-top: 20px;\">\n                Best regards,<br>\n                <strong>Pamantasan ng Lungsod ng Pasig<br>\n                    Student Success Office</strong>\n            </p>\n        </div>\n\n\n        <!-- FOOTER -->\n        <div class=\"footer\">\n            <p>Pamantasan ng Lungsod ng Pasig - Student Success Office</p>\n        </div>\n    </div>\n</body>\n\n</html>', 'sent', '2025-11-13 14:07:00', NULL, '2025-11-13 06:07:03');

-- --------------------------------------------------------

--
-- Table structure for table `contact_support`
--

CREATE TABLE `contact_support` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_support`
--

INSERT INTO `contact_support` (`id`, `name`, `email`, `subject`, `message`, `created_at`, `updated_at`) VALUES
(1, 'Romeo John Ador', 'adorromeojohn0105@gmail.com', 'test sub', 'test issue', '2025-11-02 18:26:18', '2025-11-02 18:26:18'),
(2, 'Romeo John Ador', 'adorromeojohn0105@gmail.com', 'test sub', 'test issue', '2025-11-02 18:27:43', '2025-11-02 18:27:43'),
(3, 'Romeo John Ador', 'adorromeojohn0105@gmail.com', 'test', 'test', '2025-11-02 18:28:00', '2025-11-02 18:28:00'),
(4, 'Romeo John Ador', 'adorromeojohn0105@gmail.com', 'test sub', 'test email', '2025-11-02 18:28:14', '2025-11-02 18:28:14');

-- --------------------------------------------------------

--
-- Table structure for table `downloadable_forms`
--

CREATE TABLE `downloadable_forms` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `file_url` varchar(1000) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `download_count` int(11) NOT NULL DEFAULT 0,
  `version` varchar(32) DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_template`
--

CREATE TABLE `email_template` (
  `id` int(50) NOT NULL,
  `title` varchar(250) NOT NULL,
  `subject` varchar(250) NOT NULL,
  `html_code` longtext NOT NULL,
  `date_added` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_template`
--

INSERT INTO `email_template` (`id`, `title`, `subject`, `html_code`, `date_added`, `is_active`) VALUES
(1, 'Account Registration', 'PLP - Student Success Office [Account Registration]', '<!DOCTYPE html>\n<html>\n\n<head>\n    <meta charset=\"UTF-8\">\n    <title>{{subject}}</title>\n    <link href=\"https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap\" rel=\"stylesheet\">\n    <style>\n        body {\n            font-family: \'Poppins\', Arial, sans-serif;\n            background-color: #f4f4f4;\n            padding: 20px;\n            margin: 0;\n        }\n\n        .email-container {\n            max-width: 600px;\n            margin: 0 auto;\n            background: #ffffff;\n            border-radius: 8px;\n            overflow: hidden;\n            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);\n        }\n\n        /* Header */\n        .header-table {\n            width: 100%;\n            background: #ffffff;\n            padding: 10px 15px;\n        }\n\n        .header-table img {\n            max-height: 70px;\n            vertical-align: middle;\n            margin: 0 5px;\n        }\n\n        .header-text {\n            text-align: center;\n            padding-top: 5px;\n            padding-bottom: 10px;\n        }\n\n        .header-text .school-name {\n            background: #0c326f;\n            color: white;\n            font-weight: 600;\n            font-size: 12px;\n            padding: 10px;\n            display: inline-block;\n            border-radius: 15px 0 0 15px;\n            margin-bottom: 4px;\n        }\n\n        .header-text .college-name {\n            font-size: 14px;\n            font-weight: 600;\n            color: #000;\n        }\n\n        .header-text .address {\n            font-size: 12px;\n            color: #333;\n        }\n\n        /* Content */\n        .content {\n            padding: 20px;\n            font-size: 14px;\n            line-height: 1.6;\n            color: #333;\n        }\n\n        .content p {\n            margin: 0 0 15px;\n        }\n\n        .button {\n            display: inline-block;\n            background: #004aad;\n            color: #fff !important;\n            padding: 10px 15px;\n            text-decoration: none;\n            border-radius: 4px;\n            margin-top: 10px;\n        }\n\n        .notice {\n            font-size: 13px;\n            color: #555;\n            background: #f8f8f8;\n            padding: 10px;\n            border-radius: 6px;\n            margin-top: 20px;\n            border-left: 4px solid #004aad;\n        }\n\n        .official-email {\n            text-align: center;\n            font-size: 14px;\n            color: #004aad;\n            font-weight: 600;\n            margin-top: 5px;\n        }\n\n        .footer {\n            font-size: 12px;\n            color: #888;\n            text-align: center;\n            padding: 15px;\n            background: #f9f9f9;\n        }\n\n        /* ✅ Responsive Header for Mobile */\n        @media only screen and (max-width: 480px) {\n            .header-table td {\n                display: block !important;\n                width: 100% !important;\n                text-align: center !important;\n            }\n\n            .header-table img {\n                display: inline-block !important;\n                max-height: 60px !important;\n                margin: 5px 3px !important;\n            }\n\n            .header-table td[align=\"right\"] {\n                text-align: center !important;\n                padding-top: 10px !important;\n            }\n        }\n    </style>\n</head>\n\n<body>\n    <div class=\"email-container\">\n        <!-- HEADER -->\n        <table class=\"header-table\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">\n            <tr>\n                <!-- LOGOS -->\n                <td align=\"left\" valign=\"middle\" style=\"white-space:nowrap;\">\n                    <img src=\"https://gcdnb.pbrd.co/images/Pni5FEz4UOEJ.png?o=1\" alt=\"PLP Logo\">\n                    <img src=\"https://gcdnb.pbrd.co/images/EFxDeFIopVQN.png?o=1\" alt=\"SSO Logo\">\n                </td>\n\n                <!-- TEXT -->\n                <td align=\"right\" valign=\"middle\" style=\"text-align:right;\">\n                    <div style=\"background:#0c326f; color:#fff; font-weight:600; font-size:12px; padding:5px 10px; display:inline-block; border-radius:15px 0 0 15px; margin-bottom:4px;\">\n                        PAMANTASAN NG LUNGSOD NG PASIG\n                    </div><br>\n                    <div style=\"font-size:14px; font-weight:600; color:#000;\">\n                        Student Success Office\n                    </div>\n                    <div style=\"font-size:12px; color:#333;\">\n                        Alkalde Jose St. Kapasigan Pasig City, Philippines 1600\n                    </div>\n                </td>\n            </tr>\n        </table>\n\n        <!-- EMAIL CONTENT -->\n        <div class=\"content\">\n            <p>{{greetings}},</p>\n            <p>Thank you for registering with the <strong>Pamantasan ng Lungsod ng Pasig - Student Success Office (SSO)</strong>. To complete your verification process, please click the button below:</p>\n\n            <p style=\"text-align: center; margin: 25px 0;\">\n                <a href=\"{{verification_link}}\"\n                    style=\"background-color: #004aad; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 5px; display: inline-block; font-weight: bold;\">\n                    Verify Account\n                </a>\n            </p>\n\n            <p>This link will remain valid until <strong>{{expire_at}}</strong>. If the link expires, you will need to request a new verification email through our system.</p>\n\n            <div class=\"notice\">\n                If you did not initiate this password reset request, you can safely ignore this email. For your security, please note that the <strong>only legitimate sender email address</strong> from our office is:\n                <div class=\"official-email\">plpasig.sso@gmail.com</div>\n                Any other email addresses claiming to represent the SSO should be considered unauthorized.\n            </div>\n\n            <p style=\"margin-top: 20px;\">Best regards,<br>\n                <strong>Pamantasan ng Lungsod ng Pasig<br>\n                    Student Success Office</strong>\n            </p>\n        </div>\n\n        <!-- FOOTER -->\n        <div class=\"footer\">\n            <p>Pamantasan ng Lungsod ng Pasig - Student Success Office</p>\n        </div>\n    </div>\n</body>\n\n</html>', '2025-10-03 10:29:28', 1),
(2, 'Login Account With OTP', 'PLP - Student Success Office [Login Account With OTP]', '<!DOCTYPE html>\n<html>\n\n<head>\n    <meta charset=\"UTF-8\">\n    <title>{{subject}}</title>\n    <link href=\"https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap\" rel=\"stylesheet\">\n    <style>\n        body {\n            font-family: \'Poppins\', Arial, sans-serif;\n            background-color: #f4f4f4;\n            padding: 20px;\n            margin: 0;\n        }\n\n        .email-container {\n            max-width: 600px;\n            margin: 0 auto;\n            background: #ffffff;\n            border-radius: 8px;\n            overflow: hidden;\n            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);\n        }\n\n        /* Header */\n        .header-table {\n            width: 100%;\n            background: #ffffff;\n            padding: 10px 15px;\n        }\n\n        .header-table img {\n            max-height: 70px;\n            vertical-align: middle;\n            margin: 0 5px;\n        }\n\n        .header-text {\n            text-align: center;\n            padding-top: 5px;\n            padding-bottom: 10px;\n        }\n\n        .header-text .school-name {\n            background: #0c326f;\n            color: white;\n            font-weight: 600;\n            font-size: 12px;\n            padding: 10px;\n            display: inline-block;\n            border-radius: 15px 0 0 15px;\n            margin-bottom: 4px;\n        }\n\n        .header-text .college-name {\n            font-size: 14px;\n            font-weight: 600;\n            color: #000;\n        }\n\n        .header-text .address {\n            font-size: 12px;\n            color: #333;\n        }\n\n        /* Content */\n        .content {\n            padding: 20px;\n            font-size: 14px;\n            line-height: 1.6;\n            color: #333;\n        }\n\n        .content p {\n            margin: 0 0 15px;\n        }\n\n        .button {\n            display: inline-block;\n            background: #004aad;\n            color: #fff !important;\n            padding: 10px 15px;\n            text-decoration: none;\n            border-radius: 4px;\n            margin-top: 10px;\n        }\n\n        .notice {\n            font-size: 13px;\n            color: #555;\n            background: #f8f8f8;\n            padding: 10px;\n            border-radius: 6px;\n            margin-top: 20px;\n            border-left: 4px solid #004aad;\n        }\n\n        .official-email {\n            text-align: center;\n            font-size: 14px;\n            color: #004aad;\n            font-weight: 600;\n            margin-top: 5px;\n        }\n\n        .footer {\n            font-size: 12px;\n            color: #888;\n            text-align: center;\n            padding: 15px;\n            background: #f9f9f9;\n        }\n\n        /* ✅ Responsive Header for Mobile */\n        @media only screen and (max-width: 480px) {\n            .header-table td {\n                display: block !important;\n                width: 100% !important;\n                text-align: center !important;\n            }\n\n            .header-table img {\n                display: inline-block !important;\n                max-height: 60px !important;\n                margin: 5px 3px !important;\n            }\n\n            .header-table td[align=\"right\"] {\n                text-align: center !important;\n                padding-top: 10px !important;\n            }\n        }\n    </style>\n</head>\n\n<body>\n    <div class=\"email-container\">\n        <!-- HEADER -->\n        <table class=\"header-table\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">\n            <tr>\n                <!-- LOGOS -->\n                <td align=\"left\" valign=\"middle\" style=\"white-space:nowrap;\">\n                    <img src=\"https://gcdnb.pbrd.co/images/Pni5FEz4UOEJ.png?o=1\" alt=\"PLP Logo\">\n                    <img src=\"https://gcdnb.pbrd.co/images/EFxDeFIopVQN.png?o=1\" alt=\"SSO Logo\">\n                </td>\n\n                <!-- TEXT -->\n                <td align=\"right\" valign=\"middle\" style=\"text-align:right;\">\n                    <div style=\"background:#0c326f; color:#fff; font-weight:600; font-size:12px; padding:5px 10px; display:inline-block; border-radius:15px 0 0 15px; margin-bottom:4px;\">\n                        PAMANTASAN NG LUNGSOD NG PASIG\n                    </div><br>\n                    <div style=\"font-size:14px; font-weight:600; color:#000;\">\n                        Student Success Office\n                    </div>\n                    <div style=\"font-size:12px; color:#333;\">\n                        Alkalde Jose St. Kapasigan Pasig City, Philippines 1600\n                    </div>\n                </td>\n            </tr>\n        </table>\n\n        <!-- EMAIL CONTENT -->\n        <div class=\"content\">\n            <p>{{greetings}},</p>\n            <p>Thank you for logging in to the <strong>Pamantasan ng Lungsod ng Pasig - Student Success Office (SSO)</strong>. To continue, please use the One-Time Password (OTP) provided below:</p>\n\n            <p style=\"text-align: center; margin: 25px 0;\">\n                <a href=\"\"\n                    style=\"background-color: #004aad; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 5px; display: inline-block; font-weight: bold;\">\n                    {{otp_code}}\n                </a>\n            </p>\n\n            <p>This code will remain valid until <strong>{{expire_at}}</strong>. If the link expires, you will need to request a new verification email through our system.</p>\n\n            <div class=\"notice\">\n                If you did not initiate this password reset request, you can safely ignore this email. For your security, please note that the <strong>only legitimate sender email address</strong> from our office is:\n                <div class=\"official-email\">plpasig.sso@gmail.com</div>\n                Any other email addresses claiming to represent the SSO should be considered unauthorized.\n            </div>\n\n            <p style=\"margin-top: 20px;\">Best regards,<br>\n                <strong>Pamantasan ng Lungsod ng Pasig<br>\n                    Student Success Office</strong>\n            </p>\n        </div>\n\n        <!-- FOOTER -->\n        <div class=\"footer\">\n            <p>Pamantasan ng Lungsod ng Pasig - Student Success Office</p>\n        </div>\n    </div>\n</body>\n\n</html>', '2025-10-06 19:52:52', 1),
(3, 'Reset Password With Link', 'PLP - Student Success Office [Reset Password]', '<!DOCTYPE html>\n<html>\n\n<head>\n    <meta charset=\"UTF-8\">\n    <title>{{subject}}</title>\n    <link href=\"https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap\" rel=\"stylesheet\">\n    <style>\n        body {\n            font-family: \'Poppins\', Arial, sans-serif;\n            background-color: #f4f4f4;\n            padding: 20px;\n            margin: 0;\n        }\n\n        .email-container {\n            max-width: 600px;\n            margin: 0 auto;\n            background: #ffffff;\n            border-radius: 8px;\n            overflow: hidden;\n            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);\n        }\n\n        /* Header */\n        .header-table {\n            width: 100%;\n            background: #ffffff;\n            padding: 10px 15px;\n        }\n\n        .header-table img {\n            max-height: 70px;\n            vertical-align: middle;\n            margin: 0 5px;\n        }\n\n        .header-text {\n            text-align: center;\n            padding-top: 5px;\n            padding-bottom: 10px;\n        }\n\n        .header-text .school-name {\n            background: #0c326f;\n            color: white;\n            font-weight: 600;\n            font-size: 12px;\n            padding: 10px;\n            display: inline-block;\n            border-radius: 15px 0 0 15px;\n            margin-bottom: 4px;\n        }\n\n        .header-text .college-name {\n            font-size: 14px;\n            font-weight: 600;\n            color: #000;\n        }\n\n        .header-text .address {\n            font-size: 12px;\n            color: #333;\n        }\n\n        /* Content */\n        .content {\n            padding: 20px;\n            font-size: 14px;\n            line-height: 1.6;\n            color: #333;\n        }\n\n        .content p {\n            margin: 0 0 15px;\n        }\n\n        .button {\n            display: inline-block;\n            background: #004aad;\n            color: #fff !important;\n            padding: 10px 15px;\n            text-decoration: none;\n            border-radius: 4px;\n            margin-top: 10px;\n        }\n\n        .notice {\n            font-size: 13px;\n            color: #555;\n            background: #f8f8f8;\n            padding: 10px;\n            border-radius: 6px;\n            margin-top: 20px;\n            border-left: 4px solid #004aad;\n        }\n\n        .official-email {\n            text-align: center;\n            font-size: 14px;\n            color: #004aad;\n            font-weight: 600;\n            margin-top: 5px;\n        }\n\n        .footer {\n            font-size: 12px;\n            color: #888;\n            text-align: center;\n            padding: 15px;\n            background: #f9f9f9;\n        }\n\n        /* ✅ Responsive Header for Mobile */\n        @media only screen and (max-width: 480px) {\n            .header-table td {\n                display: block !important;\n                width: 100% !important;\n                text-align: center !important;\n            }\n\n            .header-table img {\n                display: inline-block !important;\n                max-height: 60px !important;\n                margin: 5px 3px !important;\n            }\n\n            .header-table td[align=\"right\"] {\n                text-align: center !important;\n                padding-top: 10px !important;\n            }\n        }\n    </style>\n</head>\n\n<body>\n    <div class=\"email-container\">\n        <!-- HEADER -->\n        <table class=\"header-table\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">\n            <tr>\n                <!-- LOGOS -->\n                <td align=\"left\" valign=\"middle\" style=\"white-space:nowrap;\">\n                    <img src=\"https://gcdnb.pbrd.co/images/Pni5FEz4UOEJ.png?o=1\" alt=\"PLP Logo\">\n                    <img src=\"https://gcdnb.pbrd.co/images/EFxDeFIopVQN.png?o=1\" alt=\"SSO Logo\">\n                </td>\n\n                <!-- TEXT -->\n                <td align=\"right\" valign=\"middle\" style=\"text-align:right;\">\n                    <div style=\"background:#0c326f; color:#fff; font-weight:600; font-size:12px; padding:5px 10px; display:inline-block; border-radius:15px 0 0 15px; margin-bottom:4px;\">\n                        PAMANTASAN NG LUNGSOD NG PASIG\n                    </div><br>\n                    <div style=\"font-size:14px; font-weight:600; color:#000;\">\n                        Student Success Office\n                    </div>\n                    <div style=\"font-size:12px; color:#333;\">\n                        Alkalde Jose St. Kapasigan Pasig City, Philippines 1600\n                    </div>\n                </td>\n            </tr>\n        </table>\n\n        <!-- EMAIL CONTENT -->\n        <div class=\"content\">\n            <p>{{greetings}},</p>\n            <p>We received a request to reset the password for your <strong>Pamantasan ng Lungsod ng Pasig - Student Success Office (SSO)</strong> account. To proceed, please click the button below to reset your password:</p>\n\n            <p style=\"text-align: center; margin: 25px 0;\">\n                <a href=\"{{reset_link}}\"\n                    style=\"background-color: #004aad; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 5px; display: inline-block; font-weight: bold;\">\n                    Reset Password\n                </a>\n            </p>\n\n            <p>This link will remain valid until <strong>{{expire_at}}</strong>. If it expires, simply request a new password reset link through the <strong>Forgot Password</strong> page.</p>\n\n            <div class=\"notice\">\n                If you did not initiate this password reset request, you can safely ignore this email. For your security, please note that the <strong>only legitimate sender email address</strong> from our office is:\n                <div class=\"official-email\">plpasig.sso@gmail.com</div>\n                Any other email addresses claiming to represent the SSO should be considered unauthorized.\n            </div>\n\n            <p style=\"margin-top: 20px;\">Best regards,<br>\n                <strong>Pamantasan ng Lungsod ng Pasig<br>\n                    Student Success Office</strong>\n            </p>\n        </div>\n\n        <!-- FOOTER -->\n        <div class=\"footer\">\n            <p>Pamantasan ng Lungsod ng Pasig - Student Success Office</p>\n        </div>\n    </div>\n</body>\n\n</html>', '2025-10-08 20:54:58', 1),
(4, 'Account Reactivation', 'PLP - Student Success Office [Reactivate Account]', '<!DOCTYPE html>\n<html>\n\n<head>\n    <meta charset=\"UTF-8\">\n    <title>{{subject}}</title>\n    <link href=\"https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap\" rel=\"stylesheet\">\n    <style>\n        body {\n            font-family: \'Poppins\', Arial, sans-serif;\n            background-color: #f4f4f4;\n            padding: 20px;\n            margin: 0;\n        }\n\n        .email-container {\n            max-width: 600px;\n            margin: 0 auto;\n            background: #ffffff;\n            border-radius: 8px;\n            overflow: hidden;\n            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);\n        }\n\n        /* Header */\n        .header-table {\n            width: 100%;\n            background: #ffffff;\n            padding: 10px 15px;\n        }\n\n        .header-table img {\n            max-height: 70px;\n            vertical-align: middle;\n            margin: 0 5px;\n        }\n\n        .header-text {\n            text-align: center;\n            padding-top: 5px;\n            padding-bottom: 10px;\n        }\n\n        .header-text .school-name {\n            background: #0c326f;\n            color: white;\n            font-weight: 600;\n            font-size: 12px;\n            padding: 10px;\n            display: inline-block;\n            border-radius: 15px 0 0 15px;\n            margin-bottom: 4px;\n        }\n\n        .header-text .college-name {\n            font-size: 14px;\n            font-weight: 600;\n            color: #000;\n        }\n\n        .header-text .address {\n            font-size: 12px;\n            color: #333;\n        }\n\n        /* Content */\n        .content {\n            padding: 20px;\n            font-size: 14px;\n            line-height: 1.6;\n            color: #333;\n        }\n\n        .content p {\n            margin: 0 0 15px;\n        }\n\n        .button {\n            display: inline-block;\n            background: #004aad;\n            color: #fff !important;\n            padding: 10px 15px;\n            text-decoration: none;\n            border-radius: 4px;\n            margin-top: 10px;\n        }\n\n        .notice {\n            font-size: 13px;\n            color: #555;\n            background: #f8f8f8;\n            padding: 10px;\n            border-radius: 6px;\n            margin-top: 20px;\n            border-left: 4px solid #004aad;\n        }\n\n        .official-email {\n            text-align: center;\n            font-size: 14px;\n            color: #004aad;\n            font-weight: 600;\n            margin-top: 5px;\n        }\n\n        .footer {\n            font-size: 12px;\n            color: #888;\n            text-align: center;\n            padding: 15px;\n            background: #f9f9f9;\n        }\n\n        /* ✅ Responsive Header for Mobile */\n        @media only screen and (max-width: 480px) {\n            .header-table td {\n                display: block !important;\n                width: 100% !important;\n                text-align: center !important;\n            }\n\n            .header-table img {\n                display: inline-block !important;\n                max-height: 60px !important;\n                margin: 5px 3px !important;\n            }\n\n            .header-table td[align=\"right\"] {\n                text-align: center !important;\n                padding-top: 10px !important;\n            }\n        }\n    </style>\n</head>\n\n<body>\n    <div class=\"email-container\">\n        <!-- HEADER -->\n        <table class=\"header-table\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">\n            <tr>\n                <!-- LOGOS -->\n                <td align=\"left\" valign=\"middle\" style=\"white-space:nowrap;\">\n                    <img src=\"https://gcdnb.pbrd.co/images/Pni5FEz4UOEJ.png?o=1\" alt=\"PLP Logo\">\n                    <img src=\"https://gcdnb.pbrd.co/images/EFxDeFIopVQN.png?o=1\" alt=\"SSO Logo\">\n                </td>\n\n                <!-- TEXT -->\n                <td align=\"right\" valign=\"middle\" style=\"text-align:right;\">\n                    <div style=\"background:#0c326f; color:#fff; font-weight:600; font-size:12px; padding:5px 10px; display:inline-block; border-radius:15px 0 0 15px; margin-bottom:4px;\">\n                        PAMANTASAN NG LUNGSOD NG PASIG\n                    </div><br>\n                    <div style=\"font-size:14px; font-weight:600; color:#000;\">\n                        Student Success Office\n                    </div>\n                    <div style=\"font-size:12px; color:#333;\">\n                        Alkalde Jose St. Kapasigan Pasig City, Philippines 1600\n                    </div>\n                </td>\n            </tr>\n        </table>\n\n        <!-- EMAIL CONTENT -->\n        <div class=\"content\">\n            <p>{{greetings}},</p>\n            <p>We noticed that your account with the <strong>Pamantasan ng Lungsod ng Pasig - Student Success Office (SSO)</strong> has been deactivated. To reactivate your account and regain access, please click the button below:</p>\n\n            <p style=\"text-align: center; margin: 25px 0;\">\n                <a href=\"{{activation_link}}\"\n                    style=\"background-color: #004aad; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 5px; display: inline-block; font-weight: bold;\">\n                    Reactivate Account\n                </a>\n            </p>\n\n            <p>This link will remain valid until <strong>{{expire_at}}</strong></p>\n\n            <div class=\"notice\">\n                If you did not request this reactivation, please ignore this email, you can safely ignore this email. For your security, please note that the <strong>only legitimate sender email address</strong> from our office is:\n                <div class=\"official-email\">plpasig.sso@gmail.com</div>\n                Any other email addresses claiming to represent the SSO should be considered unauthorized.\n            </div>\n\n            <p style=\"margin-top: 20px;\">Best regards,<br>\n                <strong>Pamantasan ng Lungsod ng Pasig<br>\n                    Student Success Office</strong>\n            </p>\n        </div>\n\n        <!-- FOOTER -->\n        <div class=\"footer\">\n            <p>Pamantasan ng Lungsod ng Pasig - Student Success Office</p>\n        </div>\n    </div>\n</body>\n\n</html>', '2025-10-15 10:42:26', 1),
(5, 'Admission Update', 'PLP - Student Success Office [Admission Update]', '<!DOCTYPE html>\n<html>\n\n<head>\n    <meta charset=\"UTF-8\">\n    <title>{{subject}}</title>\n    <link href=\"https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap\" rel=\"stylesheet\">\n    <style>\n        body {\n            font-family: \'Poppins\', Arial, sans-serif;\n            background-color: #f4f4f4;\n            padding: 20px;\n            margin: 0;\n        }\n\n        .email-container {\n            max-width: 600px;\n            margin: 0 auto;\n            background: #ffffff;\n            border-radius: 8px;\n            overflow: hidden;\n            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);\n        }\n\n        /* Header */\n        .header-table {\n            width: 100%;\n            background: #ffffff;\n            padding: 10px 15px;\n        }\n\n        .header-table img {\n            max-height: 70px;\n            vertical-align: middle;\n            margin: 0 5px;\n        }\n\n        .header-text {\n            text-align: center;\n            padding-top: 5px;\n            padding-bottom: 10px;\n        }\n\n        .header-text .school-name {\n            background: #0c326f;\n            color: white;\n            font-weight: 600;\n            font-size: 12px;\n            padding: 10px;\n            display: inline-block;\n            border-radius: 15px 0 0 15px;\n            margin-bottom: 4px;\n        }\n\n        .header-text .college-name {\n            font-size: 14px;\n            font-weight: 600;\n            color: #000;\n        }\n\n        .header-text .address {\n            font-size: 12px;\n            color: #333;\n        }\n\n        /* Content */\n        .content {\n            padding: 20px;\n            font-size: 14px;\n            line-height: 1.6;\n            color: #333;\n        }\n\n        .content p {\n            margin: 0 0 15px;\n        }\n\n        .button {\n            display: inline-block;\n            background: #004aad;\n            color: #fff !important;\n            padding: 10px 15px;\n            text-decoration: none;\n            border-radius: 4px;\n            margin-top: 10px;\n        }\n\n        .notice {\n            font-size: 13px;\n            color: #555;\n            background: #f8f8f8;\n            padding: 10px;\n            border-radius: 6px;\n            margin-top: 20px;\n            border-left: 4px solid #004aad;\n        }\n\n        .official-email {\n            text-align: center;\n            font-size: 14px;\n            color: #004aad;\n            font-weight: 600;\n            margin-top: 5px;\n        }\n\n        .footer {\n            font-size: 12px;\n            color: #888;\n            text-align: center;\n            padding: 15px;\n            background: #f9f9f9;\n        }\n\n        /* ✅ Responsive Header for Mobile */\n        @media only screen and (max-width: 480px) {\n            .header-table td {\n                display: block !important;\n                width: 100% !important;\n                text-align: center !important;\n            }\n\n            .header-table img {\n                display: inline-block !important;\n                max-height: 60px !important;\n                margin: 5px 3px !important;\n            }\n\n            .header-table td[align=\"right\"] {\n                text-align: center !important;\n                padding-top: 10px !important;\n            }\n        }\n    </style>\n</head>\n\n<body>\n    <div class=\"email-container\">\n        <!-- HEADER -->\n        <table class=\"header-table\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">\n            <tr>\n                <!-- LOGOS -->\n                <td align=\"left\" valign=\"middle\" style=\"white-space:nowrap;\">\n                    <img src=\"https://gcdnb.pbrd.co/images/Pni5FEz4UOEJ.png?o=1\" alt=\"PLP Logo\">\n                    <img src=\"https://gcdnb.pbrd.co/images/EFxDeFIopVQN.png?o=1\" alt=\"SSO Logo\">\n                </td>\n\n                <!-- TEXT -->\n                <td align=\"right\" valign=\"middle\" style=\"text-align:right;\">\n                    <div style=\"background:#0c326f; color:#fff; font-weight:600; font-size:12px; padding:5px 10px; display:inline-block; border-radius:15px 0 0 15px; margin-bottom:4px;\">\n                        PAMANTASAN NG LUNGSOD NG PASIG\n                    </div><br>\n                    <div style=\"font-size:14px; font-weight:600; color:#000;\">\n                        Student Success Office\n                    </div>\n                    <div style=\"font-size:12px; color:#333;\">\n                        Alkalde Jose St. Kapasigan Pasig City, Philippines 1600\n                    </div>\n                </td>\n            </tr>\n        </table>\n\n        <!-- EMAIL CONTENT -->\n        <!-- EMAIL CONTENT -->\n        <div class=\"content\">\n            <p>Good day!</p>\n\n            <p>\n                We hope you are doing well. We are writing to inform you that your admission status at\n                <strong>Pamantasan ng Lungsod ng Pasig</strong> has been updated.\n            </p>\n\n            <div style=\"text-align:center; margin: 25px 0;\">\n                <div style=\"background-color: #004aad; color: #ffffff; padding: 14px 20px; border-radius: 6px; display: inline-block; font-weight: 600; font-size: 15px; letter-spacing: 0.5px;\">\n                    APPLICATION STATUS: {{status}}\n                </div>\n            </div>\n\n            <div style=\"text-align:center; margin: 20px 0;\">\n                <div style=\"background-color: #f1f5ff; color: #004aad; border: 1px solid #004aad; padding: 12px 18px; border-radius: 6px; display: inline-block; font-weight: 500; font-size: 14px;\">\n                    REMARKS: {{remarks}}\n                </div>\n            </div>\n\n            <p>\n                To view more details or take the next steps, please visit your applicant portal.\n            </p>\n\n            <div class=\"notice\">\n                <strong>Important Reminder:</strong><br>\n                For your security, please note that the <strong>only legitimate sender email address</strong> from our office is:\n                <div class=\"official-email\">plpasig.sso@gmail.com</div>\n                Any other email addresses claiming to represent the SSO should be considered unauthorized.\n            </div>\n\n            <p style=\"margin-top: 25px;\">\n                Best regards,<br>\n                <strong>Pamantasan ng Lungsod ng Pasig<br>\n                    Student Success Office</strong>\n            </p>\n        </div>\n\n\n        <!-- FOOTER -->\n        <div class=\"footer\">\n            <p>Pamantasan ng Lungsod ng Pasig - Student Success Office</p>\n        </div>\n    </div>\n</body>\n\n</html>', '2025-10-15 10:42:26', 1),
(6, 'Exam Schedule', 'PLP - Student Success Office [Exam Schedule]', '<!DOCTYPE html>\n<html>\n\n<head>\n    <meta charset=\"UTF-8\">\n    <title>{{subject}}</title>\n    <link href=\"https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap\" rel=\"stylesheet\">\n    <style>\n        body {\n            font-family: \'Poppins\', Arial, sans-serif;\n            background-color: #f4f4f4;\n            padding: 20px;\n            margin: 0;\n        }\n\n        .email-container {\n            max-width: 600px;\n            margin: 0 auto;\n            background: #ffffff;\n            border-radius: 8px;\n            overflow: hidden;\n            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);\n        }\n\n        /* Header */\n        .header-table {\n            width: 100%;\n            background: #ffffff;\n            padding: 10px 15px;\n        }\n\n        .header-table img {\n            max-height: 70px;\n            vertical-align: middle;\n            margin: 0 5px;\n        }\n\n        .header-text {\n            text-align: center;\n            padding-top: 5px;\n            padding-bottom: 10px;\n        }\n\n        .header-text .school-name {\n            background: #0c326f;\n            color: white;\n            font-weight: 600;\n            font-size: 12px;\n            padding: 10px;\n            display: inline-block;\n            border-radius: 15px 0 0 15px;\n            margin-bottom: 4px;\n        }\n\n        .header-text .college-name {\n            font-size: 14px;\n            font-weight: 600;\n            color: #000;\n        }\n\n        .header-text .address {\n            font-size: 12px;\n            color: #333;\n        }\n\n        /* Content */\n        .content {\n            padding: 20px;\n            font-size: 14px;\n            line-height: 1.6;\n            color: #333;\n        }\n\n        .content p {\n            margin: 0 0 15px;\n        }\n\n        .button {\n            display: inline-block;\n            background: #004aad;\n            color: #fff !important;\n            padding: 10px 15px;\n            text-decoration: none;\n            border-radius: 4px;\n            margin-top: 10px;\n        }\n\n        .notice {\n            font-size: 13px;\n            color: #555;\n            background: #f8f8f8;\n            padding: 10px;\n            border-radius: 6px;\n            margin-top: 20px;\n            border-left: 4px solid #004aad;\n        }\n\n        .official-email {\n            text-align: center;\n            font-size: 14px;\n            color: #004aad;\n            font-weight: 600;\n            margin-top: 5px;\n        }\n\n        .footer {\n            font-size: 12px;\n            color: #888;\n            text-align: center;\n            padding: 15px;\n            background: #f9f9f9;\n        }\n\n        /* ✅ Responsive Header for Mobile */\n        @media only screen and (max-width: 480px) {\n            .header-table td {\n                display: block !important;\n                width: 100% !important;\n                text-align: center !important;\n            }\n\n            .header-table img {\n                display: inline-block !important;\n                max-height: 60px !important;\n                margin: 5px 3px !important;\n            }\n\n            .header-table td[align=\"right\"] {\n                text-align: center !important;\n                padding-top: 10px !important;\n            }\n        }\n    </style>\n</head>\n\n<body>\n    <div class=\"email-container\">\n        <!-- HEADER -->\n        <table class=\"header-table\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">\n            <tr>\n                <!-- LOGOS -->\n                <td align=\"left\" valign=\"middle\" style=\"white-space:nowrap;\">\n                    <img src=\"https://gcdnb.pbrd.co/images/Pni5FEz4UOEJ.png?o=1\" alt=\"PLP Logo\">\n                    <img src=\"https://gcdnb.pbrd.co/images/EFxDeFIopVQN.png?o=1\" alt=\"SSO Logo\">\n                </td>\n\n                <!-- TEXT -->\n                <td align=\"right\" valign=\"middle\" style=\"text-align:right;\">\n                    <div style=\"background:#0c326f; color:#fff; font-weight:600; font-size:12px; padding:5px 10px; display:inline-block; border-radius:15px 0 0 15px; margin-bottom:4px;\">\n                        PAMANTASAN NG LUNGSOD NG PASIG\n                    </div><br>\n                    <div style=\"font-size:14px; font-weight:600; color:#000;\">\n                        Student Success Office\n                    </div>\n                    <div style=\"font-size:12px; color:#333;\">\n                        Alkalde Jose St. Kapasigan Pasig City, Philippines 1600\n                    </div>\n                </td>\n            </tr>\n        </table>\n\n        <!-- EMAIL CONTENT -->\n        <div class=\"content\">\n            <p>Good day!</p>\n\n            <p>\n                We are pleased to inform you that your <strong>entrance examination schedule</strong> has been set.\n                Please review the details of your exam below carefully.\n            </p>\n\n            <div\n                style=\"background-color: #f1f5ff; border-left: 4px solid #004aad; padding: 20px; border-radius: 6px; margin: 25px 0;\">\n                <table\n                    width=\"100%\"\n                    cellpadding=\"5\"\n                    cellspacing=\"0\"\n                    style=\"font-size: 14px; color: #333;\">\n                    <tr>\n                        <td style=\"font-weight: 600; width: 35%; vertical-align: top;\">\n                            <svg\n                                xmlns=\"http://www.w3.org/2000/svg\"\n                                width=\"14\"\n                                height=\"14\"\n                                fill=\"#004aad\"\n                                viewBox=\"0 0 24 24\"\n                                style=\"vertical-align: middle; margin-right: 6px;\">\n                                <path\n                                    d=\"M19 4h-1V2h-2v2H8V2H6v2H5C3.9 4 3 4.9 3 6v14c0 \n              1.1.9 2 2 2h14c1.1 0 2-.9 \n              2-2V6c0-1.1-.9-2-2-2zm0 \n              16H5V10h14v10zm0-12H5V6h14v2z\" />\n                            </svg>\n                            Exam Date:\n                        </td>\n                        <td>{{exam_date}}</td>\n                    </tr>\n\n                    <tr>\n                        <td style=\"font-weight: 600; vertical-align: top;\">\n                            <svg\n                                xmlns=\"http://www.w3.org/2000/svg\"\n                                width=\"14\"\n                                height=\"14\"\n                                fill=\"#004aad\"\n                                viewBox=\"0 0 24 24\"\n                                style=\"vertical-align: middle; margin-right: 6px;\">\n                                <path\n                                    d=\"M12 1a11 11 0 1 0 11 11A11 11 0 0 0 12 \n              1zm0 20a9 9 0 1 1 9-9a9 9 0 0 1-9 \n              9zm.5-9.79V6h-1v6h6v-1h-5z\" />\n                            </svg>\n                            Time:\n                        </td>\n                        <td>{{exam_time}}</td>\n                    </tr>\n\n                    <tr>\n                        <td style=\"font-weight: 600; vertical-align: top;\">\n                            <svg\n                                xmlns=\"http://www.w3.org/2000/svg\"\n                                width=\"14\"\n                                height=\"14\"\n                                fill=\"#004aad\"\n                                viewBox=\"0 0 24 24\"\n                                style=\"vertical-align: middle; margin-right: 6px;\">\n                                <path\n                                    d=\"M12 2a7 7 0 0 0-7 \n              7c0 5.25 7 13 7 13s7-7.75 \n              7-13a7 7 0 0 0-7-7zm0 9.5a2.5 \n              2.5 0 1 1 2.5-2.5A2.5 2.5 0 0 1 12 \n              11.5z\" />\n                            </svg>\n                            Venue:\n                        </td>\n                        <td>{{exam_venue}}</td>\n                    </tr>\n                </table>\n            </div>\n\n            <div class=\"notice\">\n                <p style=\"margin-top: 10px;\">\n                    For your security, please note that the\n                    <strong>only legitimate sender email address</strong> from our office is:\n                </p>\n                <div class=\"official-email\">plpasig.sso@gmail.com</div>\n                Any other email addresses claiming to represent the SSO should be considered unauthorized.\n            </div>\n\n            <p style=\"margin-top: 25px;\">\n                Best regards,<br />\n                <strong>Pamantasan ng Lungsod ng Pasig<br />Student Success Office</strong>\n            </p>\n        </div>\n\n        <!-- FOOTER -->\n        <div class=\"footer\">\n            <p>Pamantasan ng Lungsod ng Pasig - Student Success Office</p>\n        </div>\n    </div>\n</body>\n\n</html>', '2025-10-15 10:42:26', 1);
INSERT INTO `email_template` (`id`, `title`, `subject`, `html_code`, `date_added`, `is_active`) VALUES
(7, 'Exam Permit', 'PLP - Student Success Office [Exam Permit]', '<!DOCTYPE html>\n<html>\n\n<head>\n    <meta charset=\"UTF-8\">\n    <title>{{subject}}</title>\n    <link href=\"https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap\" rel=\"stylesheet\">\n    <style>\n        body {\n            font-family: \'Poppins\', Arial, sans-serif;\n            background-color: #f4f4f4;\n            padding: 20px;\n            margin: 0;\n        }\n\n        .email-container {\n            max-width: 600px;\n            margin: 0 auto;\n            background: #ffffff;\n            border-radius: 8px;\n            overflow: hidden;\n            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);\n        }\n\n        /* Header */\n        .header-table {\n            width: 100%;\n            background: #ffffff;\n            padding: 10px 15px;\n        }\n\n        .header-table img {\n            max-height: 70px;\n            vertical-align: middle;\n            margin: 0 5px;\n        }\n\n        .header-text {\n            text-align: center;\n            padding-top: 5px;\n            padding-bottom: 10px;\n        }\n\n        .header-text .school-name {\n            background: #0c326f;\n            color: white;\n            font-weight: 600;\n            font-size: 12px;\n            padding: 10px;\n            display: inline-block;\n            border-radius: 15px 0 0 15px;\n            margin-bottom: 4px;\n        }\n\n        .header-text .college-name {\n            font-size: 14px;\n            font-weight: 600;\n            color: #000;\n        }\n\n        .header-text .address {\n            font-size: 12px;\n            color: #333;\n        }\n\n        /* Content */\n        .content {\n            padding: 20px;\n            font-size: 14px;\n            line-height: 1.6;\n            color: #333;\n        }\n\n        .content p {\n            margin: 0 0 15px;\n        }\n\n        .button {\n            display: inline-block;\n            background: #004aad;\n            color: #fff !important;\n            padding: 10px 15px;\n            text-decoration: none;\n            border-radius: 4px;\n            margin-top: 10px;\n        }\n\n        .notice {\n            font-size: 13px;\n            color: #555;\n            background: #f8f8f8;\n            padding: 10px;\n            border-radius: 6px;\n            margin-top: 20px;\n            border-left: 4px solid #004aad;\n        }\n\n        .official-email {\n            text-align: center;\n            font-size: 14px;\n            color: #004aad;\n            font-weight: 600;\n            margin-top: 5px;\n        }\n\n        .footer {\n            font-size: 12px;\n            color: #888;\n            text-align: center;\n            padding: 15px;\n            background: #f9f9f9;\n        }\n\n        /* ✅ Responsive Header for Mobile */\n        @media only screen and (max-width: 480px) {\n            .header-table td {\n                display: block !important;\n                width: 100% !important;\n                text-align: center !important;\n            }\n\n            .header-table img {\n                display: inline-block !important;\n                max-height: 60px !important;\n                margin: 5px 3px !important;\n            }\n\n            .header-table td[align=\"right\"] {\n                text-align: center !important;\n                padding-top: 10px !important;\n            }\n        }\n    </style>\n</head>\n\n<body>\n    <div class=\"email-container\">\n        <!-- HEADER -->\n        <table class=\"header-table\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">\n            <tr>\n                <!-- LOGOS -->\n                <td align=\"left\" valign=\"middle\" style=\"white-space:nowrap;\">\n                    <img src=\"https://gcdnb.pbrd.co/images/Pni5FEz4UOEJ.png?o=1\" alt=\"PLP Logo\">\n                    <img src=\"https://gcdnb.pbrd.co/images/EFxDeFIopVQN.png?o=1\" alt=\"SSO Logo\">\n                </td>\n\n                <!-- TEXT -->\n                <td align=\"right\" valign=\"middle\" style=\"text-align:right;\">\n                    <div style=\"background:#0c326f; color:#fff; font-weight:600; font-size:12px; padding:5px 10px; display:inline-block; border-radius:15px 0 0 15px; margin-bottom:4px;\">\n                        PAMANTASAN NG LUNGSOD NG PASIG\n                    </div><br>\n                    <div style=\"font-size:14px; font-weight:600; color:#000;\">\n                        Student Success Office\n                    </div>\n                    <div style=\"font-size:12px; color:#333;\">\n                        Alkalde Jose St. Kapasigan Pasig City, Philippines 1600\n                    </div>\n                </td>\n            </tr>\n        </table>\n\n        <!-- EMAIL CONTENT -->\n        <div class=\"content\">\n            <p>Hello <strong>{{registered_fullname}}</strong>!</p>\n\n            <p>\n                This is to inform you that your admission for\n                <strong>{{academic_year}}</strong>-<strong>{{applicant_type}}</strong> has been updated by the\n                <strong>Pamantasan ng Lungsod ng Pasig – Student Success Office (SSO)</strong>.\n            </p>\n\n            <table style=\"width: 100%; border-collapse: collapse; margin: 20px 0;\">\n                <tr>\n                    <td style=\"padding: 8px; border: 1px solid #ddd; font-weight: bold;\">Applicant #</td>\n                    <td style=\"padding: 8px; border: 1px solid #ddd;\">{{applicant_number}}</td>\n                </tr>\n                <tr>\n                    <td style=\"padding: 8px; border: 1px solid #ddd; font-weight: bold;\">Venue</td>\n                    <td style=\"padding: 8px; border: 1px solid #ddd;\">{{room_number}} - {{floor}}</td>\n                </tr>\n                <tr>\n                    <td style=\"padding: 8px; border: 1px solid #ddd; font-weight: bold;\">Start-End Date Time</td>\n                    <td style=\"padding: 8px; border: 1px solid #ddd; color: #004aad; font-weight: bold;\">{{start_date}}-{{start_time}}</td>\n                </tr>\n            </table>\n\n            <p style=\"text-align: center; margin: 25px 0;\">\n                <a href=\"{{exam_permit_download_link}}\"\n                    style=\"background-color: #004aad; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 5px; display: inline-block; font-weight: bold; margin-right:10px;\">\n                    Download Exam Permit\n                </a>\n                <a href=\"{{qr_download_link}}\"\n                    style=\"background-color: #004aad; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 5px; display: inline-block; font-weight: bold;\">\n                    Download QR Code\n                </a>\n            </p>\n\n            <div class=\"notice\">\n                This is an automated notification from the\n                <strong>Student Success Office (SSO)</strong>.\n                For security and authenticity, please note that the <strong>only official sender email address</strong> from our office is:\n                <div class=\"official-email\">plpasig.sso@gmail.com</div>\n                Any other email claiming to represent the SSO should be considered unauthorized.\n            </div>\n\n            <p style=\"margin-top: 20px;\">\n                Best regards,<br>\n                <strong>Pamantasan ng Lungsod ng Pasig<br>\n                    Student Success Office</strong>\n            </p>\n        </div>\n\n\n        <!-- FOOTER -->\n        <div class=\"footer\">\n            <p>Pamantasan ng Lungsod ng Pasig - Student Success Office</p>\n        </div>\n    </div>\n</body>\n\n</html>', '2025-10-15 10:42:26', 1),
(8, 'Student Support Services - Reset Password', 'PLP - Student Support Services - Reset Password', '<!DOCTYPE html>\r\n<html>\r\n\r\n<head>\r\n    <meta charset=\"UTF-8\">\r\n    <title>{{subject}}</title>\r\n    <link href=\"https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap\" rel=\"stylesheet\">\r\n    <style>\r\n        body {\r\n            font-family: \'Poppins\', Arial, sans-serif;\r\n            background-color: #f4f4f4;\r\n            padding: 20px;\r\n            margin: 0;\r\n        }\r\n\r\n        .email-container {\r\n            max-width: 600px;\r\n            margin: 0 auto;\r\n            background: #ffffff;\r\n            border-radius: 8px;\r\n            overflow: hidden;\r\n            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);\r\n        }\r\n\r\n        /* Header */\r\n        .header-table {\r\n            width: 100%;\r\n            background: #ffffff;\r\n            padding: 10px 15px;\r\n        }\r\n\r\n        .header-table img {\r\n            max-height: 70px;\r\n            vertical-align: middle;\r\n            margin: 0 5px;\r\n        }\r\n\r\n        .header-text {\r\n            text-align: center;\r\n            padding-top: 5px;\r\n            padding-bottom: 10px;\r\n        }\r\n\r\n        .header-text .school-name {\r\n            background: #0c326f;\r\n            color: white;\r\n            font-weight: 600;\r\n            font-size: 12px;\r\n            padding: 10px;\r\n            display: inline-block;\r\n            border-radius: 15px 0 0 15px;\r\n            margin-bottom: 4px;\r\n        }\r\n\r\n        .header-text .college-name {\r\n            font-size: 14px;\r\n            font-weight: 600;\r\n            color: #000;\r\n        }\r\n\r\n        .header-text .address {\r\n            font-size: 12px;\r\n            color: #333;\r\n        }\r\n\r\n        /* Content */\r\n        .content {\r\n            padding: 20px;\r\n            font-size: 14px;\r\n            line-height: 1.6;\r\n            color: #333;\r\n        }\r\n\r\n        .content p {\r\n            margin: 0 0 15px;\r\n        }\r\n\r\n        .button {\r\n            display: inline-block;\r\n            background: #004aad;\r\n            color: #fff !important;\r\n            padding: 10px 15px;\r\n            text-decoration: none;\r\n            border-radius: 4px;\r\n            margin-top: 10px;\r\n        }\r\n\r\n        .notice {\r\n            font-size: 13px;\r\n            color: #555;\r\n            background: #f8f8f8;\r\n            padding: 10px;\r\n            border-radius: 6px;\r\n            margin-top: 20px;\r\n            border-left: 4px solid #004aad;\r\n        }\r\n\r\n        .official-email {\r\n            text-align: center;\r\n            font-size: 14px;\r\n            color: #004aad;\r\n            font-weight: 600;\r\n            margin-top: 5px;\r\n        }\r\n\r\n        .footer {\r\n            font-size: 12px;\r\n            color: #888;\r\n            text-align: center;\r\n            padding: 15px;\r\n            background: #f9f9f9;\r\n        }\r\n\r\n        /* ✅ Responsive Header for Mobile */\r\n        @media only screen and (max-width: 480px) {\r\n            .header-table td {\r\n                display: block !important;\r\n                width: 100% !important;\r\n                text-align: center !important;\r\n            }\r\n\r\n            .header-table img {\r\n                display: inline-block !important;\r\n                max-height: 60px !important;\r\n                margin: 5px 3px !important;\r\n            }\r\n\r\n            .header-table td[align=\"right\"] {\r\n                text-align: center !important;\r\n                padding-top: 10px !important;\r\n            }\r\n        }\r\n    </style>\r\n</head>\r\n\r\n<body>\r\n    <div class=\"email-container\">\r\n        <!-- HEADER -->\r\n        <table class=\"header-table\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">\r\n            <tr>\r\n                <!-- LOGOS -->\r\n                <td align=\"left\" valign=\"middle\" style=\"white-space:nowrap;\">\r\n                    <img src=\"https://gcdnb.pbrd.co/images/Pni5FEz4UOEJ.png?o=1\" alt=\"PLP Logo\">\r\n                    <img src=\"https://gcdnb.pbrd.co/images/EFxDeFIopVQN.png?o=1\" alt=\"SSO Logo\">\r\n                </td>\r\n\r\n                <!-- TEXT -->\r\n                <td align=\"right\" valign=\"middle\" style=\"text-align:right;\">\r\n                    <div style=\"background:#0c326f; color:#fff; font-weight:600; font-size:12px; padding:5px 10px; display:inline-block; border-radius:15px 0 0 15px; margin-bottom:4px;\">\r\n                        PAMANTASAN NG LUNGSOD NG PASIG\r\n                    </div><br>\r\n                    <div style=\"font-size:14px; font-weight:600; color:#000;\">\r\n                        Student Success Office\r\n                    </div>\r\n                    <div style=\"font-size:12px; color:#333;\">\r\n                        Alkalde Jose St. Kapasigan Pasig City, Philippines 1600\r\n                    </div>\r\n                </td>\r\n            </tr>\r\n        </table>\r\n\r\n        <!-- EMAIL CONTENT -->\r\n        <div class=\"content\">\r\n            <p>Hello!</p>\r\n\r\n            <p>\r\n                We received a request to reset your password for the\r\n                <strong>Pamantasan ng Lungsod ng Pasig – Student Success Office (SSO)</strong> account.\r\n                To continue, please use the verification code provided below:\r\n            </p>\r\n\r\n            <p style=\"text-align: center; margin: 25px 0;\">\r\n                <a\r\n                    style=\"background-color: #004aad; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 5px; display: inline-block; font-weight: bold;\">\r\n                    {{otp_code}}\r\n                </a>\r\n            </p>\r\n\r\n            <p>\r\n                This verification code will remain valid until <strong>{{expire_at}}</strong>.\r\n                If it expires, please request a new password reset through our system.\r\n            </p>\r\n\r\n            <div class=\"notice\">\r\n                If you did not request this password reset, you may safely ignore this email.\r\n                For your security, please note that the <strong>only official sender email address</strong> from our office is:\r\n                <div class=\"official-email\">plpasig.sso@gmail.com</div>\r\n                Any other email claiming to represent the SSO should be considered unauthorized.\r\n            </div>\r\n\r\n            <p style=\"margin-top: 20px;\">\r\n                Best regards,<br>\r\n                <strong>Pamantasan ng Lungsod ng Pasig<br>\r\n                    Student Success Office</strong>\r\n            </p>\r\n        </div>\r\n\r\n        <!-- FOOTER -->\r\n        <div class=\"footer\">\r\n            <p>Pamantasan ng Lungsod ng Pasig - Student Success Office</p>\r\n        </div>\r\n    </div>\r\n</body>\r\n\r\n</html>', '2025-10-06 19:52:52', 1),
(9, 'Student Support Services - Register Account', 'PLP - Student Support Services - Register Account', '<!DOCTYPE html>\r\n<html>\r\n\r\n<head>\r\n    <meta charset=\"UTF-8\">\r\n    <title>{{subject}}</title>\r\n    <link href=\"https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap\" rel=\"stylesheet\">\r\n    <style>\r\n        body {\r\n            font-family: \'Poppins\', Arial, sans-serif;\r\n            background-color: #f4f4f4;\r\n            padding: 20px;\r\n            margin: 0;\r\n        }\r\n\r\n        .email-container {\r\n            max-width: 600px;\r\n            margin: 0 auto;\r\n            background: #ffffff;\r\n            border-radius: 8px;\r\n            overflow: hidden;\r\n            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);\r\n        }\r\n\r\n        /* Header */\r\n        .header-table {\r\n            width: 100%;\r\n            background: #ffffff;\r\n            padding: 10px 15px;\r\n        }\r\n\r\n        .header-table img {\r\n            max-height: 70px;\r\n            vertical-align: middle;\r\n            margin: 0 5px;\r\n        }\r\n\r\n        .header-text {\r\n            text-align: center;\r\n            padding-top: 5px;\r\n            padding-bottom: 10px;\r\n        }\r\n\r\n        .header-text .school-name {\r\n            background: #0c326f;\r\n            color: white;\r\n            font-weight: 600;\r\n            font-size: 12px;\r\n            padding: 10px;\r\n            display: inline-block;\r\n            border-radius: 15px 0 0 15px;\r\n            margin-bottom: 4px;\r\n        }\r\n\r\n        .header-text .college-name {\r\n            font-size: 14px;\r\n            font-weight: 600;\r\n            color: #000;\r\n        }\r\n\r\n        .header-text .address {\r\n            font-size: 12px;\r\n            color: #333;\r\n        }\r\n\r\n        /* Content */\r\n        .content {\r\n            padding: 20px;\r\n            font-size: 14px;\r\n            line-height: 1.6;\r\n            color: #333;\r\n        }\r\n\r\n        .content p {\r\n            margin: 0 0 15px;\r\n        }\r\n\r\n        .button {\r\n            display: inline-block;\r\n            background: #004aad;\r\n            color: #fff !important;\r\n            padding: 10px 15px;\r\n            text-decoration: none;\r\n            border-radius: 4px;\r\n            margin-top: 10px;\r\n        }\r\n\r\n        .notice {\r\n            font-size: 13px;\r\n            color: #555;\r\n            background: #f8f8f8;\r\n            padding: 10px;\r\n            border-radius: 6px;\r\n            margin-top: 20px;\r\n            border-left: 4px solid #004aad;\r\n        }\r\n\r\n        .official-email {\r\n            text-align: center;\r\n            font-size: 14px;\r\n            color: #004aad;\r\n            font-weight: 600;\r\n            margin-top: 5px;\r\n        }\r\n\r\n        .footer {\r\n            font-size: 12px;\r\n            color: #888;\r\n            text-align: center;\r\n            padding: 15px;\r\n            background: #f9f9f9;\r\n        }\r\n\r\n        /* ✅ Responsive Header for Mobile */\r\n        @media only screen and (max-width: 480px) {\r\n            .header-table td {\r\n                display: block !important;\r\n                width: 100% !important;\r\n                text-align: center !important;\r\n            }\r\n\r\n            .header-table img {\r\n                display: inline-block !important;\r\n                max-height: 60px !important;\r\n                margin: 5px 3px !important;\r\n            }\r\n\r\n            .header-table td[align=\"right\"] {\r\n                text-align: center !important;\r\n                padding-top: 10px !important;\r\n            }\r\n        }\r\n    </style>\r\n</head>\r\n\r\n<body>\r\n    <div class=\"email-container\">\r\n        <!-- HEADER -->\r\n        <table class=\"header-table\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">\r\n            <tr>\r\n                <!-- LOGOS -->\r\n                <td align=\"left\" valign=\"middle\" style=\"white-space:nowrap;\">\r\n                    <img src=\"https://gcdnb.pbrd.co/images/Pni5FEz4UOEJ.png?o=1\" alt=\"PLP Logo\">\r\n                    <img src=\"https://gcdnb.pbrd.co/images/EFxDeFIopVQN.png?o=1\" alt=\"SSO Logo\">\r\n                </td>\r\n\r\n                <!-- TEXT -->\r\n                <td align=\"right\" valign=\"middle\" style=\"text-align:right;\">\r\n                    <div style=\"background:#0c326f; color:#fff; font-weight:600; font-size:12px; padding:5px 10px; display:inline-block; border-radius:15px 0 0 15px; margin-bottom:4px;\">\r\n                        PAMANTASAN NG LUNGSOD NG PASIG\r\n                    </div><br>\r\n                    <div style=\"font-size:14px; font-weight:600; color:#000;\">\r\n                        Student Success Office\r\n                    </div>\r\n                    <div style=\"font-size:12px; color:#333;\">\r\n                        Alkalde Jose St. Kapasigan Pasig City, Philippines 1600\r\n                    </div>\r\n                </td>\r\n            </tr>\r\n        </table>\r\n\r\n        <!-- EMAIL CONTENT -->\r\n        <div class=\"content\">\r\n            <p>Hello!</p>\r\n\r\n            <p>\r\n                Welcome to the <strong>Pamantasan ng Lungsod ng Pasig – Student Success Office (SSO)</strong> portal!\r\n                To complete your registration and verify your account, please use the verification code provided below:\r\n            </p>\r\n\r\n            <p style=\"text-align: center; margin: 25px 0;\">\r\n                <a\r\n                    style=\"background-color: #004aad; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 5px; display: inline-block; font-weight: bold;\">\r\n                    {{otp_code}}\r\n                </a>\r\n            </p>\r\n\r\n            <p>\r\n                This verification code will remain valid until <strong>{{expire_at}}</strong>.\r\n                If it expires, please request a new password reset through our system.\r\n            </p>\r\n\r\n            <div class=\"notice\">\r\n                If you did not request this password reset, you may safely ignore this email.\r\n                For your security, please note that the <strong>only official sender email address</strong> from our office is:\r\n                <div class=\"official-email\">plpasig.sso@gmail.com</div>\r\n                Any other email claiming to represent the SSO should be considered unauthorized.\r\n            </div>\r\n\r\n            <p style=\"margin-top: 20px;\">\r\n                Best regards,<br>\r\n                <strong>Pamantasan ng Lungsod ng Pasig<br>\r\n                    Student Success Office</strong>\r\n            </p>\r\n        </div>\r\n\r\n        <!-- FOOTER -->\r\n        <div class=\"footer\">\r\n            <p>Pamantasan ng Lungsod ng Pasig - Student Success Office</p>\r\n        </div>\r\n    </div>\r\n</body>\r\n\r\n</html>', '2025-10-06 19:52:52', 1),
(10, 'Student Support Services - Service Request', 'PLP - Student Support Services - Service Request', '<!DOCTYPE html>\n<html>\n\n<head>\n    <meta charset=\"UTF-8\">\n    <title>{{subject}}</title>\n    <link href=\"https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap\" rel=\"stylesheet\">\n    <style>\n        body {\n            font-family: \'Poppins\', Arial, sans-serif;\n            background-color: #f4f4f4;\n            padding: 20px;\n            margin: 0;\n        }\n\n        .email-container {\n            max-width: 600px;\n            margin: 0 auto;\n            background: #ffffff;\n            border-radius: 8px;\n            overflow: hidden;\n            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);\n        }\n\n        /* Header */\n        .header-table {\n            width: 100%;\n            background: #ffffff;\n            padding: 10px 15px;\n        }\n\n        .header-table img {\n            max-height: 70px;\n            vertical-align: middle;\n            margin: 0 5px;\n        }\n\n        .header-text {\n            text-align: center;\n            padding-top: 5px;\n            padding-bottom: 10px;\n        }\n\n        .header-text .school-name {\n            background: #0c326f;\n            color: white;\n            font-weight: 600;\n            font-size: 12px;\n            padding: 10px;\n            display: inline-block;\n            border-radius: 15px 0 0 15px;\n            margin-bottom: 4px;\n        }\n\n        .header-text .college-name {\n            font-size: 14px;\n            font-weight: 600;\n            color: #000;\n        }\n\n        .header-text .address {\n            font-size: 12px;\n            color: #333;\n        }\n\n        /* Content */\n        .content {\n            padding: 20px;\n            font-size: 14px;\n            line-height: 1.6;\n            color: #333;\n        }\n\n        .content p {\n            margin: 0 0 15px;\n        }\n\n        .button {\n            display: inline-block;\n            background: #004aad;\n            color: #fff !important;\n            padding: 10px 15px;\n            text-decoration: none;\n            border-radius: 4px;\n            margin-top: 10px;\n        }\n\n        .notice {\n            font-size: 13px;\n            color: #555;\n            background: #f8f8f8;\n            padding: 10px;\n            border-radius: 6px;\n            margin-top: 20px;\n            border-left: 4px solid #004aad;\n        }\n\n        .official-email {\n            text-align: center;\n            font-size: 14px;\n            color: #004aad;\n            font-weight: 600;\n            margin-top: 5px;\n        }\n\n        .footer {\n            font-size: 12px;\n            color: #888;\n            text-align: center;\n            padding: 15px;\n            background: #f9f9f9;\n        }\n\n        /* ✅ Responsive Header for Mobile */\n        @media only screen and (max-width: 480px) {\n            .header-table td {\n                display: block !important;\n                width: 100% !important;\n                text-align: center !important;\n            }\n\n            .header-table img {\n                display: inline-block !important;\n                max-height: 60px !important;\n                margin: 5px 3px !important;\n            }\n\n            .header-table td[align=\"right\"] {\n                text-align: center !important;\n                padding-top: 10px !important;\n            }\n        }\n    </style>\n</head>\n\n<body>\n    <div class=\"email-container\">\n        <!-- HEADER -->\n        <table class=\"header-table\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">\n            <tr>\n                <!-- LOGOS -->\n                <td align=\"left\" valign=\"middle\" style=\"white-space:nowrap;\">\n                    <img src=\"https://gcdnb.pbrd.co/images/Pni5FEz4UOEJ.png?o=1\" alt=\"PLP Logo\">\n                    <img src=\"https://gcdnb.pbrd.co/images/EFxDeFIopVQN.png?o=1\" alt=\"SSO Logo\">\n                </td>\n\n                <!-- TEXT -->\n                <td align=\"right\" valign=\"middle\" style=\"text-align:right;\">\n                    <div style=\"background:#0c326f; color:#fff; font-weight:600; font-size:12px; padding:5px 10px; display:inline-block; border-radius:15px 0 0 15px; margin-bottom:4px;\">\n                        PAMANTASAN NG LUNGSOD NG PASIG\n                    </div><br>\n                    <div style=\"font-size:14px; font-weight:600; color:#000;\">\n                        Student Success Office\n                    </div>\n                    <div style=\"font-size:12px; color:#333;\">\n                        Alkalde Jose St. Kapasigan Pasig City, Philippines 1600\n                    </div>\n                </td>\n            </tr>\n        </table>\n\n        <!-- EMAIL CONTENT -->\n        <div class=\"content\">\n            <p>Hello <strong>{{registered_fullname}}</strong>!</p>\n\n            <p>\n                This is to inform you that your service request for\n                <strong>{{service_name}}</strong> has been updated by the\n                <strong>Pamantasan ng Lungsod ng Pasig – Student Success Office (SSO)</strong>.\n            </p>\n\n            <table style=\"width: 100%; border-collapse: collapse; margin: 20px 0;\">\n                <tr>\n                    <td style=\"padding: 8px; border: 1px solid #ddd; font-weight: bold;\">Request ID</td>\n                    <td style=\"padding: 8px; border: 1px solid #ddd;\">{{request_id}}</td>\n                </tr>\n                <tr>\n                    <td style=\"padding: 8px; border: 1px solid #ddd; font-weight: bold;\">Service</td>\n                    <td style=\"padding: 8px; border: 1px solid #ddd;\">{{service_name}}</td>\n                </tr>\n                <tr>\n                    <td style=\"padding: 8px; border: 1px solid #ddd; font-weight: bold;\">Status</td>\n                    <td style=\"padding: 8px; border: 1px solid #ddd; color: #004aad; font-weight: bold;\">{{status}}</td>\n                </tr>\n                <tr>\n                    <td style=\"padding: 8px; border: 1px solid #ddd; font-weight: bold;\">Remarks</td>\n                    <td style=\"padding: 8px; border: 1px solid #ddd;\">{{remarks}}</td>\n                </tr>\n            </table>\n\n            <p>\n                You may log in to your SSO account to view more details or take further action if required.\n            </p>\n\n            <div class=\"notice\">\n                This is an automated notification from the\n                <strong>Student Success Office (SSO)</strong>.\n                For security and authenticity, please note that the <strong>only official sender email address</strong> from our office is:\n                <div class=\"official-email\">plpasig.sso@gmail.com</div>\n                Any other email claiming to represent the SSO should be considered unauthorized.\n            </div>\n\n            <p style=\"margin-top: 20px;\">\n                Best regards,<br>\n                <strong>Pamantasan ng Lungsod ng Pasig<br>\n                    Student Success Office</strong>\n            </p>\n        </div>\n\n\n        <!-- FOOTER -->\n        <div class=\"footer\">\n            <p>Pamantasan ng Lungsod ng Pasig - Student Success Office</p>\n        </div>\n    </div>\n</body>\n\n</html>', '2025-10-06 19:52:52', 1);

-- --------------------------------------------------------

--
-- Table structure for table `ExamRegistrations`
--

CREATE TABLE `ExamRegistrations` (
  `registration_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `schedule_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ExamRegistrations`
--

INSERT INTO `ExamRegistrations` (`registration_id`, `user_id`, `schedule_id`) VALUES
(1, 5, 1);

-- --------------------------------------------------------

--
-- Table structure for table `ExamSchedules`
--

CREATE TABLE `ExamSchedules` (
  `schedule_id` int(11) NOT NULL,
  `floor` varchar(50) DEFAULT NULL,
  `room` varchar(50) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `start_date_and_time` timestamp NOT NULL,
  `status` enum('Open','Full') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ExamSchedules`
--

INSERT INTO `ExamSchedules` (`schedule_id`, `floor`, `room`, `capacity`, `start_date_and_time`, `status`) VALUES
(1, '3rd Floor', 'Room 301', 30, '2025-11-17 16:28:00', 'Open');

-- --------------------------------------------------------

--
-- Table structure for table `expiration_config`
--

CREATE TABLE `expiration_config` (
  `id` int(11) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `interval_value` int(11) DEFAULT NULL,
  `interval_unit` enum('MINUTE','HOUR','DAY','MONTH') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expiration_config`
--

INSERT INTO `expiration_config` (`id`, `type`, `interval_value`, `interval_unit`) VALUES
(1, 'activation_account', 1, 'DAY'),
(2, 'password_reset', 1, 'DAY'),
(3, 'login_otp', 10, 'MINUTE'),
(4, 'session', 7, 'DAY');

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `id` int(11) NOT NULL,
  `question` text NOT NULL,
  `answer` text NOT NULL,
  `read_count` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `faqs`
--

INSERT INTO `faqs` (`id`, `question`, `answer`, `read_count`, `status`, `created_at`, `updated_at`) VALUES
(1, 'What are the eligibility criteria for undergraduate admission?', 'Applicants must have completed high school or equivalent with a minimum required percentage as specified by the college.', 3, 'active', '2025-11-02 16:02:23', '2025-11-02 16:44:47'),
(2, 'How can I apply for admission?', 'You can apply online through our official admission portal by filling out the application form and submitting the required documents.', 9, 'active', '2025-11-02 16:02:23', '2025-11-12 03:20:09'),
(3, 'What documents are required during the admission process?', 'You will need your high school transcripts, passport-size photographs, ID proof, transfer certificate, and entrance exam scorecard (if applicable).', 6, 'active', '2025-11-02 16:02:23', '2025-11-12 03:20:13'),
(4, 'Is there an entrance exam for admission?', 'Yes, certain courses require applicants to appear for an entrance exam. Please check the course details for more information.', 5, 'active', '2025-11-02 16:02:23', '2025-11-02 22:11:42'),
(5, 'What is the application fee?', 'The application fee is specified on the admission portal and varies based on the course. Payments can be made online via credit/debit card or net banking.', 1, 'active', '2025-11-02 16:02:23', '2025-11-02 16:43:33'),
(6, 'Can I apply for multiple courses?', 'Yes, you can apply for multiple courses, but separate application forms and fees are required for each course.', 1, 'active', '2025-11-02 16:02:23', '2025-11-02 16:43:33'),
(7, 'Do you offer scholarships or financial aid?', 'Yes, scholarships and financial aid are available based on academic performance, entrance exam scores, and economic background.', 1, 'active', '2025-11-02 16:02:23', '2025-11-02 16:43:34'),
(8, 'How can I check my application status?', 'You can check your application status by logging into your account on the admission portal using your registered email and password.', 1, 'active', '2025-11-02 16:02:23', '2025-11-02 16:43:34'),
(9, 'What is the last date to submit the application form?', 'The last date for application submission is mentioned on the admission notification and official website. Late submissions may not be accepted.', 0, 'inactive', '2025-11-02 16:02:23', '2025-11-02 16:02:23'),
(10, 'Can international students apply for admission?', 'Yes, international students are welcome to apply. They must submit equivalent academic documents and a valid student visa.', 2, 'active', '2025-11-02 16:02:23', '2025-11-02 16:44:45');

-- --------------------------------------------------------

--
-- Table structure for table `form_fields`
--

CREATE TABLE `form_fields` (
  `id` int(11) NOT NULL,
  `step_id` int(11) NOT NULL,
  `name` text NOT NULL,
  `label` text NOT NULL,
  `input_type` varchar(50) NOT NULL,
  `placeholder_text` varchar(255) DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `field_order` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `allowed_file_types` varchar(255) DEFAULT NULL,
  `max_file_size_mb` int(11) DEFAULT NULL,
  `visible_when_field_id` int(11) DEFAULT NULL,
  `visible_when_value` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `form_fields`
--

INSERT INTO `form_fields` (`id`, `step_id`, `name`, `label`, `input_type`, `placeholder_text`, `is_archived`, `is_required`, `field_order`, `notes`, `allowed_file_types`, `max_file_size_mb`, `visible_when_field_id`, `visible_when_value`) VALUES
(1, 1, 'email_address', 'Email Address', 'email', 'Email Address', 0, 1, 1, '', NULL, NULL, NULL, NULL),
(2, 1, 'i_hereby_certify_that_all_the_information_and_documents_attached_herein_are_true_and_correct_to_the_best_of_my_knowledge_and_belief_i_am_fully_aware_that_submitting_fake_or_tampered_documents_or_any_form_of_dishonesty_are_grounds_for_disqualification_for_admission_and_possible_criminal_offense_subject_to_pertinent_law', 'I hereby certify that all the information and documents attached herein are TRUE and CORRECT to the best of my knowledge and belief. I am fully aware that submitting fake or tampered documents or any form of dishonesty are grounds for disqualification for admission and possible criminal offense subject to pertinent law.', 'radio', '', 0, 1, 2, '', NULL, NULL, NULL, NULL),
(3, 2, 'lrn_number', 'LRN Number', 'number', 'LRN Number', 0, 0, 1, '', NULL, NULL, NULL, NULL),
(4, 2, 'sex_kasarian', 'Sex (Kasarian)', 'radio', '', 0, 1, 2, '', NULL, NULL, NULL, NULL),
(5, 2, 'surname_apelyido', 'Surname (Apelyido)', 'text', 'Surname (Apelyido)', 0, 1, 4, '', NULL, NULL, NULL, NULL),
(6, 2, 'middle_name_gitnang_pangalan', 'Middle Name (Gitnang Pangalan)', 'text', 'Middle Name (Gitnang Pangalan)', 0, 0, 5, '', NULL, NULL, NULL, NULL),
(7, 2, 'given_name_pangalan', 'Given Name (Pangalan)', 'text', '', 0, 1, 6, '', NULL, NULL, NULL, NULL),
(8, 2, 'contact_number', 'Contact Number', 'number', 'eg. 09123456789', 0, 1, 7, '', NULL, NULL, NULL, NULL),
(9, 2, 'alternative_email_address', 'Alternative Email Address', 'email', 'Alternative Email Address', 0, 1, 8, 'Regularly check your email (inbox and spam folder) for application updates, as we will send all notifications there. Kindly inform the admissions office immediately of any change to your email address to prevent difficulties.', NULL, NULL, NULL, NULL),
(10, 2, 'are_you_a_member_of_the_lgbtqia', 'Are you a member of the LGBTQIA+', 'radio', '', 0, 1, 9, '', NULL, NULL, NULL, NULL),
(11, 2, 'are_you_a_person_with_disability', 'Are you a person with disability?', 'radio', '', 0, 1, 10, '', NULL, NULL, NULL, NULL),
(12, 2, 'kindly_specify_your_disability', 'kindly specify your disability', 'text', 'specify your disability', 0, 1, 11, '', NULL, NULL, 11, 'Yes'),
(13, 2, 'do_you_have_any_relative_s_working_in_the_city_government_of_pasig_in_pamantasan_ng_lungsod_ng_pasig_and_or_affiliated_companies_subsidiaries_within_the_second_degree_of_consanguinity_or_affinity_e_g_spouse_parents_son_daughter_brother_sister_grandparents_grandchild_in_laws_etc', 'Do you have any relative/s working in the City Government of Pasig, in Pamantasan ng Lungsod ng Pasig, and/or affiliated companies/subsidiaries within the second degree of consanguinity or affinity?(e.g, spouse, parents, son, daughter, brother, sister, grandparents, grandchild, in-laws, etc.)', 'radio', '', 0, 1, 12, '', NULL, NULL, NULL, NULL),
(14, 2, 'full_name_of_the_relative', 'Full name of the relative', 'text', 'eg. Juan B. Dela Cruz', 0, 1, 13, '', NULL, NULL, 13, 'Yes'),
(15, 2, 'designation_position', 'Designation/Position', 'text', 'Designation/Position', 0, 1, 14, '', NULL, NULL, 13, 'Yes'),
(16, 2, 'relationship_to_the_applicant', 'Relationship to the applicant', 'text', '', 0, 1, 15, '', NULL, NULL, 13, 'Yes'),
(17, 3, 'residency_status', 'Residency Status', 'radio', '', 0, 1, 1, '', NULL, NULL, NULL, NULL),
(18, 3, 'district', 'District', 'radio', '', 0, 1, 2, '', NULL, NULL, 17, 'Pasig Resident'),
(19, 3, 'barangay', 'Barangay', 'select', '', 0, 1, 3, '', NULL, NULL, 18, 'District 1'),
(20, 3, 'barangay', 'Barangay', 'select', '', 0, 1, 4, '', NULL, NULL, 18, 'District 2'),
(21, 3, 'address_house_number_unit_building_street_subdivision_village', 'Address (House Number/Unit/Building, Street, Subdivision/Village)', 'text', '', 0, 1, 5, '', NULL, NULL, 17, 'Pasig Resident'),
(22, 3, 'address_house_number_unit_building_street_subdivision_village_barangay_city', 'Address (House Number/Unit/Building, Street, Subdivision/Village, Barangay, City)', 'text', '', 0, 1, 6, '', NULL, NULL, 17, 'Non-Pasig Resident'),
(23, 2, 'date_of_birth', 'Date of Birth', 'date', '', 0, 1, 3, '', NULL, NULL, NULL, NULL),
(24, 4, 'type_of_school', 'Type of School', 'select', '', 0, 1, 1, '', NULL, NULL, NULL, NULL),
(25, 4, 'last_school_attended', 'Last School Attended', 'text', 'Last School Attended', 0, 1, 2, '', NULL, NULL, NULL, NULL),
(26, 5, 'general_average_in_filipino', 'General Average in Filipino', 'number', 'General Average in Filipino', 0, 1, 1, 'To get the average, kindly sum up all your Filipino subjects (e.g. Panitikan, Komunikasyon at Pananaliksik, etc.)from 1st and 2nd semester, divided by the total no. of all your Filipino Subjects', NULL, NULL, NULL, NULL),
(27, 5, 'general_average_in_english', 'General Average in English', 'number', 'General Average in English', 0, 1, 2, 'To get the average, kindly sum up all your English subjects (e.g. Literature, Reading and Writing Skills, etc.) from Ist and 2nd semester divided by the total no, of all your English subjects', NULL, NULL, NULL, NULL),
(28, 5, 'general_average_in_mathematics', 'General Average in Mathematics', 'number', 'General Average in Mathematics', 0, 1, 3, 'To get the average, kindly sum up all your Math subjects(General Mathematics,Statistics Calculus, Trigonometry, etc.)from Ist and 2nd semester divided by the total no, of all your Math subjects', NULL, NULL, NULL, NULL),
(29, 5, 'general_average_in_science', 'General Average in Science', 'number', 'General Average in Science', 0, 1, 4, 'To get the average, kindly sum up all your Science subjects (e.g. Earth and Life Science, Physical Science, Biology, Chemistry, etc.) from 1st and 2nd semester, divided by the total no. of all your Science subjects)', NULL, NULL, NULL, NULL),
(30, 5, 'overall_general_average_gwa', 'Overall General Average (GWA)', 'number', 'Overall General Average (GWA)', 0, 1, 5, 'To get the average, kindly sum up your General Average from Ist and 2nd semester, divided by 2', NULL, NULL, NULL, NULL),
(31, 2, 'marginalized_applicant', 'Marginalized Applicant', 'checkbox', '', 0, 0, 16, 'For the purpose of admission, marginalized applicants refer to individuals belonging to disadvantaged sectors as recognized under the Republic Act 8425 (Social Reform and Poverty Alleviation Act) and related laws, with primary focus on students from low-income families. (check whether you are a beneficiary or belong to the following)', NULL, NULL, NULL, NULL),
(32, 6, 'psa_birth_certificate', 'PSA Birth Certificate', 'file', '', 0, 1, 1, 'Include the second page notation if necessary', '.jpg,.png', 10, NULL, NULL),
(33, 6, 'psa_marriage_certificate_if_married', 'PSA Marriage Certificate (If Married)', 'file', '', 0, 0, 2, 'Include the second page notation if necessary', '.jpg,.png', 10, NULL, NULL),
(34, 6, 'certified_true_copy_verified_copy_of_form_137_with_remarks_for_evaluation_purposes_only', 'Certified True Copy / Verified Copy of Form 137 (With Remarks \"For Evaluation Purposes Only\")', 'file', '', 0, 1, 3, 'Kindly upload the full copy (front and back)', '.pdf,.jpg,.png', 20, NULL, NULL),
(35, 6, 'any_two_2_government_issued_id_school_id_of_applicant_that_has_birthdate_and_address', 'Any two (2) government issued ID / School ID of applicant that has birthdate and address.', 'file', '', 0, 1, 4, '', '.pdf,.jpg,.png', 30, NULL, NULL),
(36, 6, 'barangay_residence_certificate', 'Barangay Residence Certificate', 'file', '', 0, 1, 5, '', '.pdf,.jpg,.png', 10, NULL, NULL),
(37, 6, 'two_2_passport_size_picture_white_background_with_digital_nameplate', 'TWO (2) PASSPORT SIZE picture, WHITE BACKGROUND with DIGITAL NAMEPLATE.', 'file', '', 0, 1, 6, '', '.pdf,.jpg,.png', 25, NULL, NULL),
(38, 6, 'notarized_affidavit_of_guardianship_for_applicants_with_guardian', 'Notarized Affidavit of guardianship.(for applicants with guardian)', 'file', '', 0, 0, 7, '', '.pdf,.jpg,.png', 10, NULL, NULL),
(39, 7, 'check_whether_you_have_the_following_requirements', 'Check whether you have the following requirements', 'select', '', 0, 1, 1, '', NULL, NULL, NULL, NULL),
(40, 8, 'email_address', 'Email Address', 'email', 'Email Address', 0, 1, 1, NULL, NULL, NULL, NULL, NULL),
(41, 8, 'i_hereby_certify_that_all_the_information_and_documents_attached_herein_are_true_and_correct_to_the_best_of_my_knowledge_and_belief_i_am_fully_aware_that_submitting_fake_or_tampered_documents_or_any_form_of_dishonesty_are_grounds_for_disqualification_for_admission_and_possible_criminal_offense_subject_to_pertinent_law', 'I hereby certify that all the information and documents attached herein are TRUE and CORRECT to the best of my knowledge and belief. I am fully aware that submitting fake or tampered documents or any form of dishonesty are grounds for disqualification for admission and possible criminal offense subject to pertinent law.', 'radio', '', 0, 1, 2, NULL, NULL, NULL, NULL, NULL),
(42, 9, 'lrn_number', 'LRN Number', 'number', 'LRN Number', 0, 0, 1, NULL, NULL, NULL, NULL, NULL),
(43, 9, 'sex_kasarian', 'Sex (Kasarian)', 'radio', '', 0, 1, 2, NULL, NULL, NULL, NULL, NULL),
(44, 9, 'date_of_birth', 'Date of Birth', 'date', '', 0, 1, 3, NULL, NULL, NULL, NULL, NULL),
(45, 9, 'surname_apelyido', 'Surname (Apelyido)', 'text', 'Surname (Apelyido)', 0, 1, 4, NULL, NULL, NULL, NULL, NULL),
(46, 9, 'middle_name_gitnang_pangalan', 'Middle Name (Gitnang Pangalan)', 'text', 'Middle Name (Gitnang Pangalan)', 0, 0, 5, NULL, NULL, NULL, NULL, NULL),
(47, 9, 'given_name_pangalan', 'Given Name (Pangalan)', 'text', '', 0, 1, 6, NULL, NULL, NULL, NULL, NULL),
(48, 9, 'contact_number', 'Contact Number', 'number', 'eg. 09123456789', 0, 1, 7, NULL, NULL, NULL, NULL, NULL),
(49, 9, 'alternative_email_address', 'Alternative Email Address', 'email', 'Alternative Email Address', 0, 1, 8, NULL, NULL, NULL, NULL, NULL),
(50, 9, 'are_you_a_member_of_the_lgbtqia', 'Are you a member of the LGBTQIA+', 'radio', '', 0, 1, 9, NULL, NULL, NULL, NULL, NULL),
(51, 9, 'are_you_a_person_with_disability', 'Are you a person with disability?', 'radio', '', 0, 1, 10, NULL, NULL, NULL, NULL, NULL),
(52, 9, 'kindly_specify_your_disability', 'kindly specify your disability', 'text', 'specify your disability', 0, 1, 11, NULL, NULL, NULL, NULL, NULL),
(53, 9, 'do_you_have_any_relative_s_working_in_the_city_government_of_pasig_in_pamantasan_ng_lungsod_ng_pasig_and_or_affiliated_companies_subsidiaries_within_the_second_degree_of_consanguinity_or_affinity_e_g_spouse_parents_son_daughter_brother_sister_grandparents_grandchild_in_laws_etc', 'Do you have any relative/s working in the City Government of Pasig, in Pamantasan ng Lungsod ng Pasig, and/or affiliated companies/subsidiaries within the second degree of consanguinity or affinity?(e.g, spouse, parents, son, daughter, brother, sister, grandparents, grandchild, in-laws, etc.)', 'radio', '', 0, 1, 12, NULL, NULL, NULL, NULL, NULL),
(54, 9, 'full_name_of_the_relative', 'Full name of the relative', 'text', 'eg. Juan B. Dela Cruz', 0, 1, 13, NULL, NULL, NULL, NULL, NULL),
(55, 9, 'designation_position', 'Designation/Position', 'text', 'Designation/Position', 0, 1, 14, NULL, NULL, NULL, NULL, NULL),
(56, 9, 'relationship_to_the_applicant', 'Relationship to the applicant', 'text', '', 0, 1, 15, NULL, NULL, NULL, NULL, NULL),
(57, 9, 'marginalized_applicant', 'Marginalized Applicant', 'checkbox', '', 0, 0, 16, NULL, NULL, NULL, NULL, NULL),
(58, 10, 'residency_status', 'Residency Status', 'radio', '', 0, 1, 1, NULL, NULL, NULL, NULL, NULL),
(59, 10, 'district', 'District', 'radio', '', 0, 1, 2, NULL, NULL, NULL, NULL, NULL),
(60, 10, 'barangay', 'Barangay', 'select', '', 0, 1, 3, NULL, NULL, NULL, NULL, NULL),
(61, 10, 'barangay', 'Barangay', 'select', '', 0, 1, 4, NULL, NULL, NULL, NULL, NULL),
(62, 10, 'address_house_number_unit_building_street_subdivision_village', 'Address (House Number/Unit/Building, Street, Subdivision/Village)', 'text', '', 0, 1, 5, NULL, NULL, NULL, NULL, NULL),
(63, 10, 'address_house_number_unit_building_street_subdivision_village_barangay_city', 'Address (House Number/Unit/Building, Street, Subdivision/Village, Barangay, City)', 'text', '', 0, 1, 6, NULL, NULL, NULL, NULL, NULL),
(64, 11, 'type_of_school', 'Type of School', 'select', '', 0, 1, 1, NULL, NULL, NULL, NULL, NULL),
(65, 11, 'last_school_attended', 'Last School Attended', 'text', 'Last School Attended', 0, 1, 2, NULL, NULL, NULL, NULL, NULL),
(66, 11, 'previous_school_program_course_attended', 'Previous School Program (COURSE) Attended', 'select', '', 0, 1, 3, '', NULL, NULL, NULL, NULL),
(67, 12, 'psa_birth_certificate', 'PSA Birth Certificate', 'file', '', 0, 1, 1, NULL, NULL, NULL, NULL, NULL),
(68, 12, 'psa_marriage_certificate_if_married', 'PSA Marriage Certificate (If Married)', 'file', '', 0, 0, 2, NULL, NULL, NULL, NULL, NULL),
(69, 12, 'transcript_of_record', 'Transcript of Record', 'file', '', 0, 1, 3, '', '.pdf,.jpg,.png', 10, NULL, NULL),
(70, 12, 'any_two_2_government_issued_id_school_id_of_applicant_that_has_birthdate_and_address', 'Any two (2) government issued ID / School ID of applicant that has birthdate and address.', 'file', '', 0, 1, 4, NULL, NULL, NULL, NULL, NULL),
(71, 12, 'barangay_residence_certificate', 'Barangay Residence Certificate', 'file', '', 0, 1, 5, NULL, NULL, NULL, NULL, NULL),
(72, 12, 'two_2_passport_size_picture_white_background_with_digital_nameplate', 'TWO (2) PASSPORT SIZE picture, WHITE BACKGROUND with DIGITAL NAMEPLATE.', 'file', '', 0, 1, 6, NULL, NULL, NULL, NULL, NULL),
(73, 12, 'notarized_affidavit_of_guardianship_for_applicants_with_guardian', 'Notarized Affidavit of guardianship.(for applicants with guardian)', 'file', '', 0, 0, 7, NULL, NULL, NULL, NULL, NULL),
(74, 13, 'check_whether_you_have_the_following_requirements', 'Check whether you have the following requirements', 'select', '', 0, 1, 1, NULL, NULL, NULL, NULL, NULL),
(75, 14, 'email_address', 'Email Address', 'email', 'Email Address', 0, 1, 1, NULL, NULL, NULL, NULL, NULL),
(76, 14, 'i_hereby_certify_that_all_the_information_and_documents_attached_herein_are_true_and_correct_to_the_best_of_my_knowledge_and_belief_i_am_fully_aware_that_submitting_fake_or_tampered_documents_or_any_form_of_dishonesty_are_grounds_for_disqualification_for_admission_and_possible_criminal_offense_subject_to_pertinent_law', 'I hereby certify that all the information and documents attached herein are TRUE and CORRECT to the best of my knowledge and belief. I am fully aware that submitting fake or tampered documents or any form of dishonesty are grounds for disqualification for admission and possible criminal offense subject to pertinent law.', 'radio', '', 0, 1, 2, NULL, NULL, NULL, NULL, NULL),
(77, 15, 'lrn_number', 'LRN Number', 'number', 'LRN Number', 0, 0, 1, NULL, NULL, NULL, NULL, NULL),
(78, 15, 'sex_kasarian', 'Sex (Kasarian)', 'radio', '', 0, 1, 2, NULL, NULL, NULL, NULL, NULL),
(79, 15, 'date_of_birth', 'Date of Birth', 'date', '', 0, 1, 3, NULL, NULL, NULL, NULL, NULL),
(80, 15, 'surname_apelyido', 'Surname (Apelyido)', 'text', 'Surname (Apelyido)', 0, 1, 4, NULL, NULL, NULL, NULL, NULL),
(81, 15, 'middle_name_gitnang_pangalan', 'Middle Name (Gitnang Pangalan)', 'text', 'Middle Name (Gitnang Pangalan)', 0, 0, 5, NULL, NULL, NULL, NULL, NULL),
(82, 15, 'given_name_pangalan', 'Given Name (Pangalan)', 'text', '', 0, 1, 6, NULL, NULL, NULL, NULL, NULL),
(83, 15, 'contact_number', 'Contact Number', 'number', 'eg. 09123456789', 0, 1, 7, NULL, NULL, NULL, NULL, NULL),
(84, 15, 'alternative_email_address', 'Alternative Email Address', 'email', 'Alternative Email Address', 0, 1, 8, NULL, NULL, NULL, NULL, NULL),
(85, 15, 'are_you_a_member_of_the_lgbtqia', 'Are you a member of the LGBTQIA+', 'radio', '', 0, 1, 9, NULL, NULL, NULL, NULL, NULL),
(86, 15, 'are_you_a_person_with_disability', 'Are you a person with disability?', 'radio', '', 0, 1, 10, NULL, NULL, NULL, NULL, NULL),
(87, 15, 'kindly_specify_your_disability', 'kindly specify your disability', 'text', 'specify your disability', 0, 1, 11, '', NULL, NULL, 86, 'Yes'),
(88, 15, 'do_you_have_any_relative_s_working_in_the_city_government_of_pasig_in_pamantasan_ng_lungsod_ng_pasig_and_or_affiliated_companies_subsidiaries_within_the_second_degree_of_consanguinity_or_affinity_e_g_spouse_parents_son_daughter_brother_sister_grandparents_grandchild_in_laws_etc', 'Do you have any relative/s working in the City Government of Pasig, in Pamantasan ng Lungsod ng Pasig, and/or affiliated companies/subsidiaries within the second degree of consanguinity or affinity?(e.g, spouse, parents, son, daughter, brother, sister, grandparents, grandchild, in-laws, etc.)', 'radio', '', 0, 1, 12, NULL, NULL, NULL, NULL, NULL),
(89, 15, 'full_name_of_the_relative', 'Full name of the relative', 'text', 'eg. Juan B. Dela Cruz', 0, 1, 13, '', NULL, NULL, 88, 'Yes'),
(90, 15, 'designation_position', 'Designation/Position', 'text', 'Designation/Position', 0, 1, 14, '', NULL, NULL, 88, 'Yes'),
(91, 15, 'relationship_to_the_applicant', 'Relationship to the applicant', 'text', '', 0, 1, 15, '', NULL, NULL, 88, 'Yes'),
(92, 15, 'marginalized_applicant', 'Marginalized Applicant', 'checkbox', '', 0, 0, 16, '', NULL, NULL, 88, 'Yes'),
(93, 16, 'residency_status', 'Residency Status', 'radio', '', 0, 1, 1, NULL, NULL, NULL, NULL, NULL),
(94, 16, 'district', 'District', 'radio', '', 0, 1, 2, '', NULL, NULL, 93, 'Pasig Resident'),
(95, 16, 'barangay_district_1', 'Barangay (District #1)', 'select', '', 0, 1, 3, '', NULL, NULL, 94, 'District 1'),
(96, 16, 'barangay_district_2', 'Barangay (District #2)', 'select', '', 0, 1, 4, '', NULL, NULL, 94, 'District 2'),
(97, 16, 'address_house_number_unit_building_street_subdivision_village', 'Address (House Number/Unit/Building, Street, Subdivision/Village)', 'text', '', 0, 1, 5, '', NULL, NULL, 93, 'Pasig Resident'),
(98, 16, 'address_house_number_unit_building_street_subdivision_village_barangay_city', 'Address (House Number/Unit/Building, Street, Subdivision/Village, Barangay, City)', 'text', '', 0, 1, 6, '', NULL, NULL, 93, 'Non-Pasig Resident'),
(99, 17, 'type_of_school', 'Type of School', 'select', '', 0, 1, 1, NULL, NULL, NULL, NULL, NULL),
(100, 17, 'last_school_attended', 'Last School Attended', 'text', 'Last School Attended', 0, 1, 2, NULL, NULL, NULL, NULL, NULL),
(101, 18, 'general_average_in_filipino', 'General Average in Filipino', 'number', 'General Average in Filipino', 0, 1, 1, NULL, NULL, NULL, NULL, NULL),
(102, 18, 'general_average_in_english', 'General Average in English', 'number', 'General Average in English', 0, 1, 2, NULL, NULL, NULL, NULL, NULL),
(103, 18, 'general_average_in_mathematics', 'General Average in Mathematics', 'number', 'General Average in Mathematics', 0, 1, 3, NULL, NULL, NULL, NULL, NULL),
(104, 18, 'general_average_in_science', 'General Average in Science', 'number', 'General Average in Science', 0, 1, 4, NULL, NULL, NULL, NULL, NULL),
(105, 18, 'overall_general_average_gwa', 'Overall General Average (GWA)', 'number', 'Overall General Average (GWA)', 0, 1, 5, NULL, NULL, NULL, NULL, NULL),
(106, 19, 'psa_birth_certificate', 'PSA Birth Certificate', 'file', '', 0, 1, 1, NULL, NULL, NULL, NULL, NULL),
(107, 19, 'psa_marriage_certificate_if_married', 'PSA Marriage Certificate (If Married)', 'file', '', 0, 0, 2, NULL, NULL, NULL, NULL, NULL),
(108, 19, 'certified_true_copy_verified_copy_of_form_138', 'Certified True Copy / Verified Copy of Form 138', 'file', '', 0, 1, 3, '', '.pdf,.jpg,.png', 10, NULL, NULL),
(109, 19, 'any_two_2_government_issued_id_school_id_of_applicant_that_has_birthdate_and_address', 'Any two (2) government issued ID / School ID of applicant that has birthdate and address.', 'file', '', 0, 1, 4, NULL, NULL, NULL, NULL, NULL),
(110, 19, 'barangay_residence_certificate', 'Barangay Residence Certificate', 'file', '', 0, 1, 5, NULL, NULL, NULL, NULL, NULL),
(111, 19, 'two_2_passport_size_picture_white_background_with_digital_nameplate', 'TWO (2) PASSPORT SIZE picture, WHITE BACKGROUND with DIGITAL NAMEPLATE.', 'file', '', 0, 1, 6, NULL, NULL, NULL, NULL, NULL),
(112, 19, 'notarized_affidavit_of_guardianship_for_applicants_with_guardian', 'Notarized Affidavit of guardianship.(for applicants with guardian)', 'file', '', 0, 0, 7, NULL, NULL, NULL, NULL, NULL),
(113, 20, 'check_whether_you_have_the_following_requirements', 'Check whether you have the following requirements', 'checkbox', '', 0, 1, 1, '', NULL, NULL, NULL, NULL),
(115, 22, 'testing_file', 'Testing File', 'file', '', 0, 1, 1, '', '.pdf,.jpg,.png', 10, NULL, NULL),
(116, 22, 'testing_input', 'Testing Input', 'text', '', 0, 1, 2, '', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `form_field_options`
--

CREATE TABLE `form_field_options` (
  `id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `option_label` varchar(255) NOT NULL,
  `option_value` varchar(255) NOT NULL,
  `option_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `form_field_options`
--

INSERT INTO `form_field_options` (`id`, `field_id`, `option_label`, `option_value`, `option_order`) VALUES
(3, 2, 'Agree', 'Agree', 1),
(4, 4, 'Male', 'Male', 1),
(5, 10, 'Yes', 'Yes', 1),
(6, 10, 'No', 'No', 2),
(7, 10, 'Prefer not to say', 'Prefer not to say', 3),
(8, 11, 'Yes', 'Yes', 1),
(9, 11, 'No', 'No', 2),
(10, 13, 'Yes', 'Yes', 1),
(11, 13, 'No', 'No', 2),
(12, 17, 'Pasig Resident', 'Pasig Resident', 1),
(13, 17, 'Non-Pasig Resident', 'Non-Pasig Resident', 2),
(14, 18, 'District 1', 'District 1', 1),
(15, 18, 'District 2', 'District 2', 2),
(16, 19, 'Bagong Ilog', 'Bagong Ilog', 1),
(17, 19, 'Bagong Katipunan', 'Bagong Katipunan', 2),
(18, 19, 'Bambang', 'Bambang', 3),
(19, 19, 'Buting', 'Buting', 4),
(20, 19, 'Caniogan', 'Caniogan', 5),
(21, 19, 'Kalawaan', 'Kalawaan', 6),
(22, 19, 'Kapasigan', 'Kapasigan', 7),
(24, 19, 'Kapitolyo', 'Kapitolyo', 8),
(25, 19, 'Malinao', 'Malinao', 9),
(26, 19, 'Oranbo', 'Oranbo', 10),
(27, 19, 'Palatiw', 'Palatiw', 11),
(28, 19, 'Pineda', 'Pineda', 12),
(29, 19, 'Sagad', 'Sagad', 13),
(31, 19, 'San Antonio', 'San Antonio', 14),
(32, 19, 'San Joaquin', 'San Joaquin', 15),
(33, 19, 'San Jose', 'San Jose', 16),
(34, 19, 'San Nicolas', 'San Nicolas', 17),
(35, 19, 'Santa Cruz', 'Santa Cruz', 18),
(36, 19, 'Santa Rosa', 'Santa Rosa', 19),
(38, 19, 'Santo Tomas', 'Santo Tomas', 20),
(39, 19, 'Sumilang', 'Sumilang', 21),
(40, 19, 'Ugong', 'Ugong', 22),
(41, 20, 'Dela Paz', 'Dela Paz', 1),
(42, 20, 'Manggahan', 'Manggahan', 2),
(43, 20, 'Maybunga', 'Maybunga', 3),
(44, 20, 'Pinagbuhatan', 'Pinagbuhatan', 4),
(45, 20, 'Rosario', 'Rosario', 5),
(46, 20, 'San Miguel', 'San Miguel', 6),
(47, 20, 'Santa Lucia', 'Santa Lucia', 7),
(48, 20, 'Santolan', 'Santolan', 8),
(49, 24, 'Public', 'Public', 1),
(50, 24, 'Private', 'Private', 2),
(51, 31, 'A beneficiary of DSWD Listahanan or Pantawid Pamilyang Pilipino Program (4Ps)', 'A beneficiary of DSWD Listahanan or Pantawid Pamilyang Pilipino Program (4Ps)', 1),
(52, 31, 'A children of migrant workers (OFWs) with OWWA Certification', 'A children of migrant workers (OFWs) with OWWA Certification', 2),
(53, 31, 'Indigenous Peoples(IPs) as certified by the NCIP?', 'Indigenous Peoples(IPs) as certified by the NCIP?', 3),
(54, 31, 'A Resident of Geographically Isolated and Disadvantaged Areas (GIDAs) in Pasig', 'A Resident of Geographically Isolated and Disadvantaged Areas (GIDAs) in Pasig', 4),
(55, 31, 'Other vulnerable groups may be identified by law or the local government', 'Other vulnerable groups may be identified by law or the local government', 5),
(56, 39, 'Certified True Copy (CTC) of Form 137 with remarks \"For Evaluation Purposes only\"', 'Certified True Copy (CTC) of Form 137 with remarks \"For Evaluation Purposes only\"', 1),
(57, 39, 'Any Two (2) Government Issued ID / School D of applicant thattasbirthidate and address', 'Any Two (2) Government Issued ID / School D of applicant thattasbirthidate and address', 2),
(58, 39, 'PSA Birth Certificate', 'PSA Birth Certificate', 3),
(59, 39, 'Two Passport size picture, white background with nameplate', 'Two Passport size picture, white background with nameplate', 4),
(60, 39, 'Notarized Affidavit of guardianship (for applicants with guardian)', 'Notarized Affidavit of guardianship (for applicants with guardian)', 5),
(61, 39, 'Barangay Residence Certificate', 'Barangay Residence Certificate', 6),
(62, 41, 'Agree', 'Agree', 1),
(63, 43, 'Male', 'Male', 1),
(64, 50, 'Yes', 'Yes', 1),
(65, 50, 'No', 'No', 2),
(66, 50, 'Prefer not to say', 'Prefer not to say', 3),
(67, 51, 'Yes', 'Yes', 1),
(68, 51, 'No', 'No', 2),
(69, 53, 'Yes', 'Yes', 1),
(70, 53, 'No', 'No', 2),
(71, 57, 'A beneficiary of DSWD Listahanan or Pantawid Pamilyang Pilipino Program (4Ps)', 'A beneficiary of DSWD Listahanan or Pantawid Pamilyang Pilipino Program (4Ps)', 1),
(72, 57, 'A children of migrant workers (OFWs) with OWWA Certification', 'A children of migrant workers (OFWs) with OWWA Certification', 2),
(73, 57, 'Indigenous Peoples(IPs) as certified by the NCIP?', 'Indigenous Peoples(IPs) as certified by the NCIP?', 3),
(74, 57, 'A Resident of Geographically Isolated and Disadvantaged Areas (GIDAs) in Pasig', 'A Resident of Geographically Isolated and Disadvantaged Areas (GIDAs) in Pasig', 4),
(75, 57, 'Other vulnerable groups may be identified by law or the local government', 'Other vulnerable groups may be identified by law or the local government', 5),
(76, 58, 'Pasig Resident', 'Pasig Resident', 1),
(77, 58, 'Non-Pasig Resident', 'Non-Pasig Resident', 2),
(78, 59, 'District 1', 'District 1', 1),
(79, 59, 'District 2', 'District 2', 2),
(80, 60, 'Bagong Ilog', 'Bagong Ilog', 1),
(81, 60, 'Bagong Katipunan', 'Bagong Katipunan', 2),
(82, 60, 'Bambang', 'Bambang', 3),
(83, 60, 'Buting', 'Buting', 4),
(84, 60, 'Caniogan', 'Caniogan', 5),
(85, 60, 'Kalawaan', 'Kalawaan', 6),
(86, 60, 'Kapasigan', 'Kapasigan', 7),
(87, 60, 'Kapitolyo', 'Kapitolyo', 8),
(88, 60, 'Malinao', 'Malinao', 9),
(89, 60, 'Oranbo', 'Oranbo', 10),
(90, 60, 'Palatiw', 'Palatiw', 11),
(91, 60, 'Pineda', 'Pineda', 12),
(92, 60, 'Sagad', 'Sagad', 13),
(93, 60, 'San Antonio', 'San Antonio', 14),
(94, 60, 'San Joaquin', 'San Joaquin', 15),
(95, 60, 'San Jose', 'San Jose', 16),
(96, 60, 'San Nicolas', 'San Nicolas', 17),
(97, 60, 'Santa Cruz', 'Santa Cruz', 18),
(98, 60, 'Santa Rosa', 'Santa Rosa', 19),
(99, 60, 'Santo Tomas', 'Santo Tomas', 20),
(100, 60, 'Sumilang', 'Sumilang', 21),
(101, 60, 'Ugong', 'Ugong', 22),
(102, 61, 'Dela Paz', 'Dela Paz', 1),
(103, 61, 'Manggahan', 'Manggahan', 2),
(104, 61, 'Maybunga', 'Maybunga', 3),
(105, 61, 'Pinagbuhatan', 'Pinagbuhatan', 4),
(106, 61, 'Rosario', 'Rosario', 5),
(107, 61, 'San Miguel', 'San Miguel', 6),
(108, 61, 'Santa Lucia', 'Santa Lucia', 7),
(109, 61, 'Santolan', 'Santolan', 8),
(110, 64, 'Public', 'Public', 1),
(111, 64, 'Private', 'Private', 2),
(112, 66, 'BACHELOR OF SCIENCE IN ACCOUNTANCY (BSA)', 'BACHELOR OF SCIENCE IN ACCOUNTANCY (BSA)', 1),
(113, 66, 'BACHELOR OF SCIENCE IN BUSINESS ADMINISTRATION (BSBA)', 'BACHELOR OF SCIENCE IN BUSINESS ADMINISTRATION (BSBA)', 2),
(114, 66, 'BACHELOR OF SCIENCE IN ENTREPRENEURSHIP (BSENT)', 'BACHELOR OF SCIENCE IN ENTREPRENEURSHIP (BSENT)', 3),
(115, 66, 'BACHELOR OF SCIENCE IN HOSPITALITY MANAGEMENT (BSHM)', 'BACHELOR OF SCIENCE IN HOSPITALITY MANAGEMENT (BSHM)', 4),
(116, 66, 'BACHELOR OF SECONDARY EDUCATION MAJOR IN FILIPINO (BSED-FIL)', 'BACHELOR OF SECONDARY EDUCATION MAJOR IN FILIPINO (BSED-FIL)', 5),
(117, 74, 'Transcript of Record', 'Transcript of Record', 1),
(118, 74, 'Any Two (2) Government Issued ID / School D of applicant thattasbirthidate and address', 'Any Two (2) Government Issued ID / School D of applicant thattasbirthidate and address', 2),
(119, 74, 'PSA Birth Certificate', 'PSA Birth Certificate', 3),
(120, 74, 'Two Passport size picture, white background with nameplate', 'Two Passport size picture, white background with nameplate', 4),
(121, 74, 'Notarized Affidavit of guardianship (for applicants with guardian)', 'Notarized Affidavit of guardianship (for applicants with guardian)', 5),
(122, 74, 'Barangay Residence Certificate', 'Barangay Residence Certificate', 6),
(123, 76, 'Agree', 'Agree', 1),
(124, 78, 'Male', 'Male', 1),
(125, 85, 'Yes', 'Yes', 1),
(126, 85, 'No', 'No', 2),
(127, 85, 'Prefer not to say', 'Prefer not to say', 3),
(128, 86, 'Yes', 'Yes', 1),
(129, 86, 'No', 'No', 2),
(130, 88, 'Yes', 'Yes', 1),
(131, 88, 'No', 'No', 2),
(132, 92, 'A beneficiary of DSWD Listahanan or Pantawid Pamilyang Pilipino Program (4Ps)', 'A beneficiary of DSWD Listahanan or Pantawid Pamilyang Pilipino Program (4Ps)', 1),
(133, 92, 'A children of migrant workers (OFWs) with OWWA Certification', 'A children of migrant workers (OFWs) with OWWA Certification', 2),
(134, 92, 'Indigenous Peoples(IPs) as certified by the NCIP?', 'Indigenous Peoples(IPs) as certified by the NCIP?', 3),
(135, 92, 'A Resident of Geographically Isolated and Disadvantaged Areas (GIDAs) in Pasig', 'A Resident of Geographically Isolated and Disadvantaged Areas (GIDAs) in Pasig', 4),
(136, 92, 'Other vulnerable groups may be identified by law or the local government', 'Other vulnerable groups may be identified by law or the local government', 5),
(137, 93, 'Pasig Resident', 'Pasig Resident', 1),
(138, 93, 'Non-Pasig Resident', 'Non-Pasig Resident', 2),
(139, 94, 'District 1', 'District 1', 1),
(140, 94, 'District 2', 'District 2', 2),
(141, 95, 'Bagong Ilog', 'Bagong Ilog', 1),
(142, 95, 'Bagong Katipunan', 'Bagong Katipunan', 2),
(143, 95, 'Bambang', 'Bambang', 3),
(144, 95, 'Buting', 'Buting', 4),
(145, 95, 'Caniogan', 'Caniogan', 5),
(146, 95, 'Kalawaan', 'Kalawaan', 6),
(147, 95, 'Kapasigan', 'Kapasigan', 7),
(148, 95, 'Kapitolyo', 'Kapitolyo', 8),
(149, 95, 'Malinao', 'Malinao', 9),
(150, 95, 'Oranbo', 'Oranbo', 10),
(151, 95, 'Palatiw', 'Palatiw', 11),
(152, 95, 'Pineda', 'Pineda', 12),
(153, 95, 'Sagad', 'Sagad', 13),
(154, 95, 'San Antonio', 'San Antonio', 14),
(155, 95, 'San Joaquin', 'San Joaquin', 15),
(156, 95, 'San Jose', 'San Jose', 16),
(157, 95, 'San Nicolas', 'San Nicolas', 17),
(158, 95, 'Santa Cruz', 'Santa Cruz', 18),
(159, 95, 'Santa Rosa', 'Santa Rosa', 19),
(160, 95, 'Santo Tomas', 'Santo Tomas', 20),
(161, 95, 'Sumilang', 'Sumilang', 21),
(162, 95, 'Ugong', 'Ugong', 22),
(163, 96, 'Dela Paz', 'Dela Paz', 1),
(164, 96, 'Manggahan', 'Manggahan', 2),
(165, 96, 'Maybunga', 'Maybunga', 3),
(166, 96, 'Pinagbuhatan', 'Pinagbuhatan', 4),
(167, 96, 'Rosario', 'Rosario', 5),
(168, 96, 'San Miguel', 'San Miguel', 6),
(169, 96, 'Santa Lucia', 'Santa Lucia', 7),
(170, 96, 'Santolan', 'Santolan', 8),
(171, 99, 'Public', 'Public', 1),
(172, 99, 'Private', 'Private', 2),
(173, 113, 'Certified True Copy (CTC) of Form 137 with remarks \"For Evaluation Purposes only\"', 'Certified True Copy (CTC) of Form 137 with remarks \"For Evaluation Purposes only\"', 1),
(174, 113, 'Any Two (2) Government Issued ID / School D of applicant thattasbirthidate and address', 'Any Two (2) Government Issued ID / School D of applicant thattasbirthidate and address', 2),
(175, 113, 'PSA Birth Certificate', 'PSA Birth Certificate', 3),
(176, 113, 'Two Passport size picture, white background with nameplate', 'Two Passport size picture, white background with nameplate', 4),
(177, 113, 'Notarized Affidavit of guardianship (for applicants with guardian)', 'Notarized Affidavit of guardianship (for applicants with guardian)', 5),
(178, 113, 'Barangay Residence Certificate', 'Barangay Residence Certificate', 6),
(179, 4, 'Female', 'Female', 2),
(180, 78, 'Female', 'Female', 2);

-- --------------------------------------------------------

--
-- Table structure for table `form_steps`
--

CREATE TABLE `form_steps` (
  `id` int(11) NOT NULL,
  `applicant_type_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `step_order` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `form_steps`
--

INSERT INTO `form_steps` (`id`, `applicant_type_id`, `title`, `description`, `is_archived`, `step_order`) VALUES
(1, 1, 'Pamantasan ng Lungsod ng Pasig Admission', 'This online application form intends to gather pertinent information about you as an applicant to Pamantasan ng Lungsod ng Pasig for the Academic Year 2026-2027\r\n\r\nWe assure you that we will treat the information gathered with the strictest confidentiality in compliance with the Data Privacy Act of 2012\r\n\r\nNOTE: You can only complete this online application ONCE. Attempting to fill out or sign up for the online application multiple times using different emails but the same account/application will NULLIFY your admission registration.\r\n\r\nRegularly check your email(inbox and spam folder)for application updates, as we will send all notifications there. Kindly inform the admissions office immediately of any change to your email address to prevent difficulties.\r\n\r\nPLEASE MAKE SURE ALL YOUR ENTRIES ARE CORRECT BEFORE SUBMITTING YOUR APPLICATION.', 0, 1),
(2, 1, 'Step 1: Personal Information', '', 0, 2),
(3, 1, 'Step 2: Address Information', '', 0, 3),
(4, 1, 'Step 3: Previous Education Information', '', 0, 4),
(5, 1, 'Step 4: General Average', '', 0, 5),
(6, 1, 'Step 5: Documentary Requirements', 'When uploading the REQUIRED DOCUMENTS, please ensure that the scanned copies or screenshots are CLEAR, LEGIBLE, and FREE FROM ANY ALTERATIONS or DIGITAL MANIPULATION.\r\n\r\nApplicants are reminded that ONLY THOSE with COMPLETE REQUIREMENTS will be entertained and scheduled for VALIDATION.\r\n\r\nQualifying applicants shall take the admission exam. The schedule of examination will be posted on the PLP Official Facebook Page.', 0, 6),
(7, 1, 'Step 6: Document Checklist', '', 0, 7),
(8, 2, 'Pamantasan ng Lungsod ng Pasig Admission', 'This online application form intends to gather pertinent information about you as an applicant to Pamantasan ng Lungsod ng Pasig for the Academic Year 2026-2027\r\n\r\nWe assure you that we will treat the information gathered with the strictest confidentiality in compliance with the Data Privacy Act of 2012\r\n\r\nNOTE: You can only complete this online application ONCE. Attempting to fill out or sign up for the online application multiple times using different emails but the same account/application will NULLIFY your admission registration.\r\n\r\nRegularly check your email(inbox and spam folder)for application updates, as we will send all notifications there. Kindly inform the admissions office immediately of any change to your email address to prevent difficulties.\r\n\r\nPLEASE MAKE SURE ALL YOUR ENTRIES ARE CORRECT BEFORE SUBMITTING YOUR APPLICATION.', 0, 1),
(9, 2, 'Step 1: Personal Information', '', 0, 2),
(10, 2, 'Step 2: Address Information', '', 0, 3),
(11, 2, 'Step 3: Previous Education Information', '', 0, 4),
(12, 2, 'Step 4: Documentary Requirements', 'When uploading the REQUIRED DOCUMENTS, please ensure that the scanned copies or screenshots are CLEAR, LEGIBLE, and FREE FROM ANY ALTERATIONS or DIGITAL MANIPULATION.\r\n\r\nApplicants are reminded that ONLY THOSE with COMPLETE REQUIREMENTS will be entertained and scheduled for VALIDATION.\r\n\r\nQualifying applicants shall take the admission exam. The schedule of examination will be posted on the PLP Official Facebook Page.', 0, 5),
(13, 2, 'Step 6: Document Checklist', '', 0, 6),
(14, 3, 'Pamantasan ng Lungsod ng Pasig Admission', 'This online application form intends to gather pertinent information about you as an applicant to Pamantasan ng Lungsod ng Pasig for the Academic Year 2026-2027\r\n\r\nWe assure you that we will treat the information gathered with the strictest confidentiality in compliance with the Data Privacy Act of 2012\r\n\r\nNOTE: You can only complete this online application ONCE. Attempting to fill out or sign up for the online application multiple times using different emails but the same account/application will NULLIFY your admission registration.\r\n\r\nRegularly check your email(inbox and spam folder)for application updates, as we will send all notifications there. Kindly inform the admissions office immediately of any change to your email address to prevent difficulties.\r\n\r\nPLEASE MAKE SURE ALL YOUR ENTRIES ARE CORRECT BEFORE SUBMITTING YOUR APPLICATION.', 0, 1),
(15, 3, 'Step 1: Personal Information', '', 0, 2),
(16, 3, 'Step 2: Address Information', '', 0, 3),
(17, 3, 'Step 3: Previous Education Information', '', 0, 4),
(18, 3, 'Step 4: General Average', '', 0, 5),
(19, 3, 'Step 5: Documentary Requirements', 'When uploading the REQUIRED DOCUMENTS, please ensure that the scanned copies or screenshots are CLEAR, LEGIBLE, and FREE FROM ANY ALTERATIONS or DIGITAL MANIPULATION.\r\n\r\nApplicants are reminded that ONLY THOSE with COMPLETE REQUIREMENTS will be entertained and scheduled for VALIDATION.\r\n\r\nQualifying applicants shall take the admission exam. The schedule of examination will be posted on the PLP Official Facebook Page.', 0, 6),
(20, 3, 'Step 6: Document Checklist', '', 0, 7),
(22, 5, 'Step 1: Testing', '', 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `general_uploads`
--

CREATE TABLE `general_uploads` (
  `id` int(11) NOT NULL,
  `title` varchar(250) NOT NULL,
  `file_url` varchar(1024) NOT NULL COMMENT 'Full URL to the uploaded file',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `date_upload` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `general_uploads`
--

INSERT INTO `general_uploads` (`id`, `title`, `file_url`, `status`, `date_upload`, `date_modified`) VALUES
(1, 'Footer Image', 'https://gold-lion-549609.hostingersite.com/uploads/general/690153f9a724_3d_footer1.png', 'active', '2025-10-28 23:38:33', '2025-10-28 23:38:33'),
(2, 'PLP Logo', 'https://gold-lion-549609.hostingersite.com/uploads/general/69015463d5fa_Inang_pamantasan_logo.png', 'active', '2025-10-28 23:40:19', '2025-10-28 23:40:19'),
(3, 'SSO Logo', 'https://gold-lion-549609.hostingersite.com/uploads/general/69015492e8be_SSO_LOGO.png', 'active', '2025-10-28 23:41:06', '2025-10-28 23:41:06'),
(4, 'Officer Cover', 'https://gold-lion-549609.hostingersite.com/uploads/general/690154e5afc8_wall_of_officer.png', 'active', '2025-10-28 23:42:29', '2025-10-28 23:42:29'),
(5, 'Transferee Cover', 'https://gold-lion-549609.hostingersite.com/uploads/general/690155036bc2_transferee_cover2.png', 'active', '2025-10-28 23:42:59', '2025-10-28 23:42:59'),
(6, 'Pasig Logo white', 'https://gold-lion-549609.hostingersite.com/uploads/general/6901560b0dc8_Pasig_word_White_color.png', 'active', '2025-10-28 23:47:23', '2025-10-28 23:47:23'),
(7, 'Pasig Logo Original', 'https://gold-lion-549609.hostingersite.com/uploads/general/69015642684e_Pasig_word_blue_color.png', 'active', '2025-10-28 23:48:18', '2025-10-28 23:48:18'),
(8, 'ID Replacement Cover', 'https://gold-lion-549609.hostingersite.com/uploads/general/69015673f2fe_ID_replacement.png', 'active', '2025-10-28 23:49:07', '2025-10-28 23:49:07'),
(9, 'Banner Images', 'https://gold-lion-549609.hostingersite.com/uploads/general/690156e6b4d1_banner_images.png', 'active', '2025-10-28 23:51:02', '2025-10-28 23:51:02'),
(10, 'Document Example Images', 'https://gold-lion-549609.hostingersite.com/uploads/general/69015706c966_Example_document_form.png', 'active', '2025-10-28 23:51:34', '2025-10-28 23:51:34'),
(11, 'Admission Cover', 'https://gold-lion-549609.hostingersite.com/uploads/general/690157c735a9_12.png', 'active', '2025-10-28 23:54:47', '2025-10-28 23:54:47'),
(12, 'ID Replacement Cover', 'https://gold-lion-549609.hostingersite.com/uploads/general/690157f3d94c_10.png', 'active', '2025-10-28 23:55:31', '2025-10-28 23:55:31'),
(13, 'Type Of Applicant Cover', 'https://gold-lion-549609.hostingersite.com/uploads/general/690158675e99_type_of_applicant.png', 'active', '2025-10-28 23:57:27', '2025-10-28 23:57:27'),
(14, 'Ador Images', 'https://gold-lion-549609.hostingersite.com/uploads/general/690158a3dcca_dev_ador.png', 'active', '2025-10-28 23:58:27', '2025-10-28 23:58:27'),
(15, 'Abo Images', 'https://gold-lion-549609.hostingersite.com/uploads/general/690158b2cd24_dev_abo.png', 'active', '2025-10-28 23:58:42', '2025-10-28 23:58:42'),
(16, 'Datus Images', 'https://gold-lion-549609.hostingersite.com/uploads/general/690158c546b6_dev_datus.png', 'active', '2025-10-28 23:59:01', '2025-10-28 23:59:01'),
(17, 'Goodmoral Cover', 'https://gold-lion-549609.hostingersite.com/uploads/general/6901ade3e087_Good_moral_card_cover1.png', 'active', '2025-10-29 06:02:11', '2025-10-29 06:02:11'),
(18, 'System Logo', 'https://gold-lion-549609.hostingersite.com/uploads/general/6901f146879a_Student_Servoice-removebg-preview.png', 'active', '2025-10-29 10:49:42', '2025-10-29 10:49:42'),
(20, 'Contact Us Banner', 'https://gold-lion-549609.hostingersite.com/uploads/general/69021a10cfcb_things_i_wanted_to_say_but_never_did.png', 'active', '2025-10-29 13:43:44', '2025-10-29 13:43:44'),
(21, 'Admission Applicant Background', 'https://gold-lion-549609.hostingersite.com/uploads/general/69021ca59dd8_layered-waves-haikei.png', 'active', '2025-10-29 13:54:45', '2025-10-29 13:54:45'),
(22, 'Banner Header', 'https://gold-lion-549609.hostingersite.com/uploads/general/690222154c1c_banner_images.png', 'active', '2025-10-29 14:17:57', '2025-10-29 14:19:45'),
(23, 'Testing123', 'http://localhost/STUDENT SUCCESS OFFICE - api/uploads/general/6902860a7301_things_i_wanted_to_say_but_never_did.png', 'active', '2025-10-29 14:36:01', '2025-10-29 21:24:29'),
(24, 'test', 'http://localhost/STUDENT SUCCESS OFFICE - api/uploads/general/69028839a261_things_i_wanted_to_say_but_never_did.png', 'active', '2025-10-29 14:41:17', '2025-10-29 21:33:48'),
(25, 'Tstng', 'http://localhost/STUDENT SUCCESS OFFICE - api/uploads/general/690285e88727_things_i_wanted_to_say_but_never_did.png', 'active', '2025-10-29 20:50:04', '2025-10-29 21:23:55'),
(26, 'things i wanted to say but never did', 'https://gold-lion-549609.hostingersite.com/uploads/general/69029a8d0cc9_Untitled_Diagram.drawio.png', 'active', '2025-10-29 21:17:40', '2025-10-29 22:51:57'),
(27, 'testing', 'https://gold-lion-549609.hostingersite.com/uploads/general/69066ef537c6_exam_permit_2025001__6_.pdf', 'active', '2025-11-01 20:35:04', '2025-11-01 20:35:04'),
(28, 'Testing', 'https://gold-lion-549609.hostingersite.com/uploads/general/6907bd2b2631_applicants_export_cycle_1_2025-10-30_21-40-58.xlsx', 'active', '2025-11-02 20:20:59', '2025-11-02 20:20:59'),
(29, 'exam_permit_2025001 (8)', 'http://localhost/STUDENT SUCCESS OFFICE - api/uploads/general/6908badb77d2_exam_permit_2025001__8_.pdf', 'active', '2025-11-03 14:23:25', '2025-11-03 14:23:25');

-- --------------------------------------------------------

--
-- Table structure for table `msg_config`
--

CREATE TABLE `msg_config` (
  `id` int(11) NOT NULL,
  `name` varchar(250) NOT NULL,
  `value` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `msg_config`
--

INSERT INTO `msg_config` (`id`, `name`, `value`, `created_at`, `is_active`) VALUES
(1, 'REQUEST_METHOD_POST', 'To interact with this endpoint, be sure to send a POST request — other methods aren’t supported.', '2025-10-14 17:33:41', 1),
(2, 'EMPTY_EMAIL_PASS', 'To continue, please make sure you’ve entered both your email address and password.', '2025-10-14 18:05:54', 1),
(3, 'INVALID_EMAIL', 'Hmm... that doesn\'t look like a valid email address.', '2025-10-14 18:09:54', 1),
(4, 'ALREADY_REGISTERED', 'Looks like this email is already registered. Try logging in instead.', '2025-10-14 18:11:42', 1),
(5, 'REGISTER_SUCCESS', 'Your account has been created successfully! Please check your email for a verification link.', '2025-10-14 18:28:55', 1),
(6, 'INVALID_VERIFY_LINK', 'Oops — it looks like your verification link isn’t valid anymore. Try requesting a new link to verify your account.', '2025-10-14 20:12:58', 1),
(7, 'EXPIRED_VERIFY_LINK', 'This verification link has expired for security reasons. Please request a new link to verify your account.', '2025-10-14 20:19:53', 1),
(8, 'ALREADY_VERIFIED', 'It looks like your account has already been verified. If you’re having trouble signing in, try resetting your password or contacting support.', '2025-10-14 20:21:38', 1),
(9, 'USER_NOT_FOUND', 'We couldn’t find an account with this verification link. Please check your information and try again.', '2025-10-14 20:23:00', 1),
(10, 'NOT_REGISTER', 'We couldn’t find an account with that email address. Please check and try again.', '2025-10-14 20:31:15', 1),
(11, 'NOT_VERIFIED_ACCOUNT', 'Your account hasn’t been verified yet. Please check your inbox (and spam folder) for the verification link.', '2025-10-14 20:35:36', 1),
(12, 'ACCOUNT_BANNED_DELETED', 'It looks like your account has been deactivated or suspended. If this is unexpected, please contact our support team to restore access.', '2025-10-14 21:00:37', 1),
(13, 'INVALID_PASSWORD', 'Login unsuccessful — the password entered is incorrect. You can try again or reset your password to regain access.', '2025-10-15 07:50:24', 1),
(14, 'LOGIN_SUCCESS', 'Login successful! Your account is now active and ready to use.', '2025-10-15 07:52:58', 1),
(15, 'VERIFICATION_ACCOUNT_LINK_SUCCESS', 'A new verification link has been sent to your email address. Please check your inbox to continue.', '2025-10-15 09:20:56', 1),
(16, 'VERIFICATION_ACCOUNT_LINK_FAILED', 'We couldn’t send the verification link due to a system issue or invalid email address. Please double-check your information and try again later. If the issue persists, contact support.', '2025-10-15 09:21:33', 1),
(17, 'REACTIVATION_ACCOUNT_SUCCESS', 'A reactivation link has been sent to your email. Please check your inbox to reactivate your account.', '2025-10-15 10:44:25', 1),
(18, 'REACTIVATION_ACCOUNT_FAILED', 'Something went wrong while activation your account. Please try again.', '2025-10-15 10:45:31', 1),
(19, 'RESET_PASSWORD_SEND_SUCCESS', 'A password reset link has been sent to your email. Please check your inbox and reset your password within 24 hours to complete the process.', '2025-10-15 13:20:45', 1),
(20, 'RESET_PASSWORD_SEND_FAILED', 'We couldn’t send the password reset link due to a system issue or invalid email address. Please double-check your information and try again later. If the issue persists, contact support.', '2025-10-15 13:21:03', 1),
(21, 'RESET_PASSWORD_LINK_INVALID', 'We couldn’t find an account with this reset password link. Please check your reset password link and try again.', '2025-10-15 14:23:48', 1),
(22, 'RESET_PASSWORD_LINK_EXPIRED', 'This reset password link has expired for security reasons. Please request a new link to verify your account.', '2025-10-15 14:24:32', 1),
(23, 'CHANGE_PASSWORD_SUCCESS', 'Your password has been updated successfully! You can now log in with your new password.', '2025-10-15 14:59:00', 1),
(24, 'CHANGE_PASSWORD_FAILED', 'Hmm, we couldn\'t update your password. Please try again in a few moments.', '2025-10-15 14:59:49', 1),
(25, 'OTP_SEND_FAILED', 'We couldn’t send the OTP CODE due to a system issue or invalid email address. Please double-check your information and try again later. If the issue persists, contact support.', '2025-10-15 20:59:11', 1),
(26, 'OTP_SEND_SUCCESS', 'We’ve sent a 6-digit code to your registered email. Please check your inbox and enter the code to continue.', '2025-10-15 21:00:05', 1),
(27, 'INVALID_OTP', 'Hmm... that doesn\'t look like a valid OTP.', '2025-10-17 10:09:11', 1),
(28, 'OTP_ALREADY_USED', 'This verification code has already been used. Please request a new code by clicking <b>Resend OTP Code</b>.', '2025-10-17 11:11:56', 1),
(29, 'OTP_ALREADY_USED', 'This verification code has already been used. Please request a new code by clicking <b>Resend OTP Code</b>.', '2025-10-17 11:12:58', 1),
(30, 'OTP_EXPIRED', 'Your verification code has expired. Please request a new code by clicking <b>Resend OTP Code</b> and use the updated code sent to your email to proceed.', '2025-10-17 11:12:58', 1),
(31, 'OTP_VERIFICATION_SUCCESS', 'Verification successful! You can now proceed with your login.', '2025-10-17 11:12:58', 1),
(32, 'OTP_UPDATE_ERROR', 'An error occurred while processing your request. Please try again.', '2025-10-17 11:12:58', 1),
(33, 'OTP_INVALID_CODE', 'It looks like the code you entered is incorrect. Please double-check the code sent to your email and try again.<br><br>Click <b>Try Again</b> to re-enter your code, or click <b>Resend OTP Code</b> to request a new one.', '2025-10-17 11:12:58', 1),
(34, 'OTP_NOT_FOUND', 'No verification code found for this account. Please request a new code by clicking <b>Resend OTP Code</b>.', '2025-10-17 11:12:58', 1),
(35, 'LOGIN_ERROR', 'We couldn’t login your account due to a system issue. Please double-check your information and try again later. If the issue persists, contact support.', '2025-10-15 07:52:58', 1),
(36, 'SESSION_EXPIRED', 'Your session has expired or you’re not logged in. Please sign in to regain access.', '2025-10-17 20:15:16', 1),
(37, 'UNAUTHENTICATED', 'Authentication required. Please log in to access this resource.', '2025-10-17 20:20:29', 1),
(38, 'INVALID_FIRST_NAME', 'To continue, please make sure you’ve entered a valid first name.', '2025-10-19 20:51:46', 1),
(39, 'INVALID_LAST_NAME', 'To continue, please make sure you’ve entered a valid last name.', '2025-10-19 20:52:03', 1),
(40, 'SET_PROFILE_FAILED', 'We couldn’t update your profile at this time. Please check your input and try again. If the issue persists, contact support for help.', '2025-10-19 21:17:55', 1),
(41, 'SET_PROFILE_SUCCESS', 'Your profile has been set up successfully.', '2025-10-19 21:18:04', 1),
(42, 'INVALID_APPLICATION_TYPE', 'The provided application type is invalid or not supported.', '2025-10-21 11:36:08', 1);

-- --------------------------------------------------------

--
-- Table structure for table `otp_user`
--

CREATE TABLE `otp_user` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `value` text NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `otp_user`
--

INSERT INTO `otp_user` (`id`, `user_id`, `value`, `is_used`, `expires_at`, `created_at`) VALUES
(1, 1, '450430', 0, '2025-11-03 07:13:47', '2025-10-17 18:49:05');

-- --------------------------------------------------------

--
-- Table structure for table `remark_templates`
--

CREATE TABLE `remark_templates` (
  `id` int(11) NOT NULL,
  `remark_text` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `remark_templates`
--

INSERT INTO `remark_templates` (`id`, `remark_text`, `is_active`) VALUES
(1, 'Missing required document: [Document Name]', 1),
(2, 'Information provided is unclear. Please clarify: [Specific Detail]', 1),
(3, 'Application approved pending final review.', 1),
(4, 'Application does not meet minimum requirements.', 1),
(5, 'Duplicate submission detected.', 1);

-- --------------------------------------------------------

--
-- Table structure for table `services_answers`
--

CREATE TABLE `services_answers` (
  `answer_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `answer_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services_answers`
--

INSERT INTO `services_answers` (`answer_id`, `request_id`, `field_id`, `answer_value`) VALUES
(1, 1, 11, 'Datusass'),
(2, 1, 12, 'Mark Andrieass'),
(3, 1, 13, 'Dalisayaaaass'),
(4, 1, 14, 'School Admission'),
(5, 2, 11, 'Renheart'),
(6, 2, 12, 'Dela Vega'),
(7, 2, 13, 'Lopez'),
(8, 2, 14, 'School Admission'),
(9, 3, 11, 'Romeo John'),
(10, 3, 12, 'Gonzales'),
(11, 3, 13, 'Ador'),
(12, 3, 14, 'School Admission'),
(13, 4, 10, 'https://beige-jellyfish-618097.hostingersite.com/uploads/service_requests/691190c88f23a-EXAM_PERMIT_PLPPasig-00000001.pdf'),
(14, 4, 6, 'Romeo John'),
(15, 4, 7, 'Gonzales'),
(16, 4, 8, 'Ador'),
(17, 4, 9, 'Yes'),
(18, 1, 15, 'aaaa'),
(19, 5, 11, 'jerseyrrr'),
(20, 5, 12, 'garciarrr'),
(21, 5, 13, 'tekikorrr'),
(22, 5, 14, 'Other'),
(23, 5, 15, '444'),
(24, 6, 11, 'Tricia Nicole'),
(25, 6, 12, 'Delgado'),
(26, 6, 13, 'De Asis'),
(27, 6, 14, 'Scholarship Application'),
(28, 7, 11, 'Amerel'),
(29, 7, 12, 'Mangontra'),
(30, 7, 13, 'Disomnong'),
(31, 7, 14, 'Scholarship Application'),
(32, 8, 10, 'https://beige-jellyfish-618097.hostingersite.com/uploads/service_requests/69131c84add47-IMG_20251023_214145_365.jpg'),
(33, 8, 6, 'Tricia Nicole'),
(34, 8, 7, 'Delgado'),
(35, 8, 8, 'De Asis'),
(36, 8, 9, 'Yes'),
(37, 9, 10, 'https://beige-jellyfish-618097.hostingersite.com/uploads/service_requests/69131ca5bbc94-17628601851157468256710879873320.jpg'),
(38, 9, 6, 'Amerel'),
(39, 9, 7, 'Mangontra'),
(40, 9, 8, 'Disomnong'),
(41, 9, 9, 'Yes'),
(42, 10, 10, 'https://beige-jellyfish-618097.hostingersite.com/uploads/service_requests/691364509f072-DFDUpdated.png'),
(43, 10, 6, 'Gerrald'),
(44, 10, 7, 'Aquino'),
(45, 10, 8, 'Abo'),
(46, 10, 9, 'No'),
(47, 11, 11, 'testng'),
(48, 11, 12, 'testing'),
(49, 11, 13, 'testing'),
(50, 11, 14, 'School Admission'),
(51, 12, 11, 'testing'),
(52, 12, 16, '20-00215'),
(53, 12, 12, 'hhqhq'),
(54, 12, 13, 'qhhq'),
(55, 12, 14, 'Other'),
(56, 12, 15, 'hemployment'),
(57, 12, 17, 'Graduated'),
(58, 13, 11, 'datus'),
(59, 13, 16, 'mark'),
(60, 13, 12, 'andrie'),
(61, 13, 13, '013011'),
(62, 13, 14, '2nd Year'),
(63, 13, 17, 'Graduated'),
(64, 13, 21, 'Scholarship Application'),
(65, 14, 11, 'Abo'),
(66, 14, 16, 'Gerrald'),
(67, 14, 12, 'Aquino'),
(68, 14, 13, '23-00270'),
(69, 14, 14, '2nd Year'),
(70, 14, 17, '1st Year'),
(71, 14, 21, 'On-The-Job Training (OJT)'),
(72, 15, 10, 'https://plpasig-student-services.icu/uploads/service_requests/6915fa2b4ad48-archi-framework.png'),
(73, 15, 6, 'Gerrald'),
(74, 15, 7, 'Aquino'),
(75, 15, 8, 'Abo'),
(76, 15, 9, 'No'),
(77, 16, 10, 'https://plpasig-student-services.icu/uploads/service_requests/6915fabc9f3d1-newArchi.png'),
(78, 16, 6, 'Gerrald'),
(79, 16, 7, 'Aquino'),
(80, 16, 8, 'Abo'),
(81, 16, 9, 'No'),
(82, 17, 23, 'Bote'),
(83, 17, 24, 'Bote sIya'),
(84, 17, 25, 'Gerrald Abo'),
(85, 17, 26, '2003-08-24');

-- --------------------------------------------------------

--
-- Table structure for table `services_email_otp_codes`
--

CREATE TABLE `services_email_otp_codes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `six_digit` varchar(50) NOT NULL,
  `purpose` enum('login','register','reset_password') NOT NULL DEFAULT 'login',
  `sent_to` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `max_attempts` int(11) NOT NULL DEFAULT 5,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services_email_otp_codes`
--

INSERT INTO `services_email_otp_codes` (`id`, `user_id`, `six_digit`, `purpose`, `sent_to`, `expires_at`, `consumed_at`, `attempts`, `max_attempts`, `created_at`) VALUES
(13, 14, '367239', 'register', 'adorromeojohn0105@gmail.com', '2025-11-05 04:39:19', '2025-11-03 20:39:54', 0, 5, '2025-11-03 20:18:12'),
(17, 14, '950569', 'reset_password', 'adorromeojohn0105@gmail.com', '2025-11-09 17:24:50', NULL, 0, 5, '2025-11-08 08:31:58'),
(18, 15, '690887', 'register', 'batfish31723@aminating.com', '2025-11-09 17:33:14', NULL, 0, 5, '2025-11-08 09:30:30'),
(19, 16, '191658', 'register', 'anaconda28553@mailshan.com', '2025-11-09 17:33:39', NULL, 0, 5, '2025-11-08 09:33:27'),
(20, 17, '846842', 'register', 'hyena07539@aminating.com', '2025-11-09 17:35:25', NULL, 0, 5, '2025-11-08 09:35:05'),
(21, 18, '112855', 'register', 'wombat15934@aminating.com', '2025-11-09 17:45:03', NULL, 0, 5, '2025-11-08 09:45:04'),
(24, 21, '873231', 'register', 'adorromeojohn05@gmail.com', '2025-11-09 17:51:57', '2025-11-08 09:52:25', 0, 5, '2025-11-08 09:48:18'),
(25, 22, '955858', 'register', 'renheartimpulsement@gmail.com', '2025-11-09 19:13:11', '2025-11-08 11:13:39', 0, 5, '2025-11-08 11:13:11'),
(26, 23, '302043', 'register', 'datus_markandrie@plpasig.edu.ph', '2025-11-09 20:36:39', '2025-11-08 12:37:57', 1, 5, '2025-11-08 12:36:40'),
(27, 24, '941628', 'register', 'ador_romeojohn@plpasig.edu.ph', '2025-11-11 06:36:28', '2025-11-09 22:36:56', 0, 5, '2025-11-09 22:36:28'),
(28, 25, '924852', 'register', 'santosclyde867@gmail.com', '2025-11-12 18:29:34', '2025-11-11 10:30:15', 0, 5, '2025-11-11 10:29:34'),
(29, 26, '918422', 'register', 'datusmarkandrei@gmail.com', '2025-11-12 18:37:38', '2025-11-11 10:38:36', 0, 5, '2025-11-11 10:37:41'),
(30, 27, '845731', 'register', 'triciadeasis7@gmail.com', '2025-11-12 19:19:52', '2025-11-11 11:20:25', 0, 5, '2025-11-11 11:19:52'),
(31, 28, '772410', 'register', 'amerel82@gmail.com', '2025-11-12 19:20:21', '2025-11-11 11:20:57', 0, 5, '2025-11-11 11:20:21'),
(32, 29, '792798', 'register', 'jomar.aguirre.566@gmail.com', '2025-11-12 19:32:04', '2025-11-11 14:20:09', 0, 5, '2025-11-11 11:30:19'),
(33, 30, '124727', 'register', 'Bangcore_angel@plpasig.edu.ph', '2025-11-12 21:51:51', '2025-11-11 13:52:38', 0, 5, '2025-11-11 13:51:51'),
(34, 31, '383867', 'register', 'kthscenery30@gmail.com', '2025-11-12 21:58:55', '2025-11-11 14:00:43', 0, 5, '2025-11-11 13:58:55'),
(35, 32, '677906', 'register', 'francispeter.bundalian@my.jru.edu', '2025-11-12 22:31:06', '2025-11-11 14:31:38', 0, 5, '2025-11-11 14:31:06'),
(36, 33, '397718', 'register', 'codera_isabela@plpasig.edu.ph', '2025-11-12 22:35:24', '2025-11-11 14:37:17', 0, 5, '2025-11-11 14:35:24'),
(37, 34, '839447', 'register', 'andreyaa932@gmail.com', '2025-11-12 23:12:23', NULL, 0, 5, '2025-11-11 15:11:28'),
(38, 35, '355705', 'register', 'ahgomez6949ant@student.fatima.edu.ph', '2025-11-12 23:29:42', '2025-11-11 15:30:05', 0, 5, '2025-11-11 15:29:42'),
(39, 36, '966099', 'register', 'abogerrald2403@gmail.com', '2025-11-12 23:47:25', '2025-11-11 15:48:45', 0, 5, '2025-11-11 15:47:25'),
(40, 37, '921850', 'register', 'doumamatsuno@gmail.com', '2025-11-12 23:50:06', NULL, 0, 5, '2025-11-11 15:50:07'),
(41, 38, '863579', 'register', 'gerraldabo24@gmail.com', '2025-11-12 23:51:05', NULL, 0, 5, '2025-11-11 15:51:05'),
(42, 39, '661448', 'register', 'heraldogray@gmail.com', '2025-11-12 23:51:37', NULL, 0, 5, '2025-11-11 15:51:37'),
(43, 40, '340808', 'register', 'nosib74615@fermiro.com', '2025-11-12 23:54:02', NULL, 0, 5, '2025-11-11 15:54:02'),
(44, 41, '117132', 'register', 'cicel17015@gyknife.com', '2025-11-13 00:08:16', '2025-11-11 16:11:42', 0, 5, '2025-11-11 16:08:16'),
(45, 41, '115750', 'reset_password', 'cicel17015@gyknife.com', '2025-11-13 00:15:26', '2025-11-11 16:18:20', 0, 5, '2025-11-11 16:15:26'),
(46, 24, '511831', 'reset_password', 'ador_romeojohn@plpasig.edu.ph', '2025-11-13 17:24:34', NULL, 0, 5, '2025-11-12 09:24:34'),
(47, 42, '881160', 'register', 'pjgvaldez1008@gmail.com', '2025-11-13 20:44:14', '2025-11-12 12:44:47', 0, 5, '2025-11-12 12:44:14'),
(48, 43, '312101', 'register', 'gipanep625@canvect.com', '2025-11-14 08:26:35', NULL, 0, 5, '2025-11-13 00:23:44'),
(49, 44, '559855', 'register', 'doyaw45859@chaineor.com', '2025-11-14 08:28:11', NULL, 0, 5, '2025-11-13 00:28:12'),
(50, 45, '980206', 'register', 'gamboa.khinandrei@gmail.com', '2025-11-14 10:53:49', '2025-11-13 02:54:16', 0, 5, '2025-11-13 02:53:49'),
(51, 26, '713906', 'reset_password', 'datusmarkandrei@gmail.com', '2025-11-14 23:01:18', '2025-11-13 15:02:09', 0, 5, '2025-11-13 14:59:49'),
(52, 46, '538637', 'register', 'nasogeh333@fermiro.com', '2025-11-14 22:59:58', '2025-11-13 15:00:18', 0, 5, '2025-11-13 14:59:51'),
(53, 47, '211343', 'register', 'keveh19414@canvect.com', '2025-11-14 23:02:31', '2025-11-13 15:02:48', 0, 5, '2025-11-13 15:02:25'),
(54, 47, '536569', 'reset_password', 'keveh19414@canvect.com', '2025-11-15 00:05:07', '2025-11-13 16:18:06', 0, 5, '2025-11-13 16:05:00');

-- --------------------------------------------------------

--
-- Table structure for table `services_fields`
--

CREATE TABLE `services_fields` (
  `field_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `label` text NOT NULL,
  `field_type` varchar(250) NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) NOT NULL,
  `allowed_file_types` varchar(255) DEFAULT NULL,
  `visible_when_value` varchar(255) DEFAULT NULL,
  `visible_when_option_id` int(11) DEFAULT NULL,
  `max_file_size_mb` int(11) DEFAULT NULL
) ;

--
-- Dumping data for table `services_fields`
--

INSERT INTO `services_fields` (`field_id`, `service_id`, `label`, `field_type`, `is_required`, `display_order`, `allowed_file_types`, `visible_when_value`, `visible_when_option_id`, `max_file_size_mb`) VALUES
(6, 2, 'Given Name', 'text', 1, 1, NULL, NULL, NULL, NULL),
(7, 2, 'Middle Name', 'text', 1, 1, NULL, NULL, NULL, NULL),
(8, 2, 'Surname', 'text', 1, 3, NULL, NULL, NULL, NULL),
(9, 2, 'Loss ID?', 'radio', 1, 4, NULL, NULL, NULL, NULL),
(10, 2, 'Notarized Affidavit of Loss', 'file', 1, 6, NULL, NULL, NULL, 5),
(11, 1, 'Surname', 'text', 1, 1, NULL, NULL, NULL, NULL),
(12, 1, 'Middle Name', 'text', 0, 3, NULL, NULL, NULL, NULL),
(13, 1, 'Student #', 'text', 1, 4, NULL, NULL, NULL, NULL),
(14, 1, 'Year', 'select', 1, 5, NULL, NULL, NULL, NULL),
(15, 1, 'Course (1st Year)', 'select', 1, 5, NULL, NULL, 26, NULL),
(16, 1, 'Given Name', 'text', 1, 2, NULL, NULL, NULL, NULL),
(17, 1, 'Course (2nd Year)', 'select', 1, 6, NULL, NULL, 27, NULL),
(18, 1, 'Course (3rd Year)', 'select', 1, 7, NULL, NULL, 28, NULL),
(19, 1, 'Course (4th Year)', 'select', 1, 8, NULL, NULL, 29, NULL),
(20, 1, 'Year Graduated', 'number', 1, 9, NULL, NULL, 30, NULL),
(21, 1, 'Purpose', 'select', 1, 10, NULL, NULL, NULL, NULL),
(22, 1, 'Please Specify Purpose', 'text', 1, 11, NULL, NULL, 34, NULL),
(23, 4, 'Item Name', 'text', 1, 1, NULL, NULL, NULL, NULL),
(24, 4, 'Description', 'textarea', 1, 2, NULL, NULL, NULL, NULL),
(25, 4, 'Name of Claimnant', 'text', 1, 3, NULL, NULL, NULL, NULL),
(26, 4, 'Date Loss', 'date', 1, 4, NULL, NULL, NULL, NULL),
(27, 5, 'Item Name', 'text', 1, 1, NULL, NULL, NULL, NULL),
(28, 5, 'Description', 'textarea', 1, 2, NULL, NULL, NULL, NULL),
(29, 5, 'Surrender By', 'text', 1, 3, NULL, NULL, NULL, NULL),
(30, 5, 'Date Found', 'date', 1, 4, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `services_field_options`
--

CREATE TABLE `services_field_options` (
  `option_id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `option_label` varchar(255) NOT NULL,
  `option_value` varchar(255) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services_field_options`
--

INSERT INTO `services_field_options` (`option_id`, `field_id`, `option_label`, `option_value`, `display_order`) VALUES
(4, 6, 'School Admission', 'School Admission', 1),
(5, 6, 'Employment Application', 'Employment Application', 2),
(6, 6, 'Scholarship Application', 'Scholarship Application', 3),
(7, 6, 'Study Abroad or Visa Application', 'Study Abroad or Visa Application', 4),
(8, 6, 'Internship or Training', 'Internship or Training', 5),
(9, 6, 'Transfer to Another School', 'Transfer to Another School', 6),
(10, 6, 'Other', 'Other', 7),
(11, 9, 'Yes', 'Yes', 1),
(12, 9, 'No', 'No', 2),
(19, 17, '1st Year', '1st Year', 1),
(20, 17, '2nd Year', '2nd Year', 2),
(21, 17, '3rd Year', '3rd Year', 3),
(22, 17, '4th Year', '4th Year', 4),
(23, 17, 'Graduated', 'Graduated', 5),
(24, 18, 'BSIT', 'BSIT', 1),
(25, 18, 'BSCS', 'BSCS', 2),
(26, 14, '1st Year', '1st Year', 1),
(27, 14, '2nd Year', '2nd Year', 2),
(28, 14, '3rd Year', '3rd Year', 3),
(29, 14, '4th Year', '4th Year', 4),
(30, 14, 'Graduated', 'Graduated', 5),
(31, 21, 'Scholarship Application', 'Scholarship Application', 1),
(32, 21, 'On-The-Job Training (OJT)', 'On-The-Job Training (OJT)', 2),
(33, 21, 'Transfer to Another School', 'Transfer to Another School', 3),
(34, 21, 'Other', 'Other', 4);

-- --------------------------------------------------------

--
-- Table structure for table `services_list`
--

CREATE TABLE `services_list` (
  `service_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` text DEFAULT NULL,
  `button_text` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services_list`
--

INSERT INTO `services_list` (`service_id`, `name`, `description`, `icon`, `button_text`, `is_active`) VALUES
(1, 'Good Moral Request', 'A formal request to obtain a certification confirming an individual\'s good moral character and ethical conduct.', '<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\" class=\"lucide lucide-file-text-icon lucide-file-text\"><path d=\"M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z\"/><path d=\"M14 2v5a1 1 0 0 0 1 1h5\"/><path d=\"M10 9H8\"/><path d=\"M16 13H8\"/><path d=\"M16 17H8\"/></svg>', 'Request', 1),
(2, 'ID Replacement', 'ID Replacement is the process of issuing a new identification card to replace one that has been lost, stolen, damaged, or contains outdated information.', '<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\" class=\"lucide lucide-id-card-lanyard-icon lucide-id-card-lanyard\"><path d=\"M13.5 8h-3\"/><path d=\"m15 2-1 2h3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h3\"/><path d=\"M16.899 22A5 5 0 0 0 7.1 22\"/><path d=\"m9 2 3 6\"/><circle cx=\"12\" cy=\"15\" r=\"3\"/></svg>', 'Request', 1),
(4, 'Lost Item', 'If you’ve lost something, use this service to provide details. This information allows our team to help locate your item.', '<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\" class=\"lucide lucide-message-square-warning-icon lucide-message-square-warning\"><path d=\"M22 17a2 2 0 0 1-2 2H6.828a2 2 0 0 0-1.414.586l-2.202 2.202A.71.71 0 0 1 2 21.286V5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2z\"/><path d=\"M12 15h.01\"/><path d=\"M12 7v4\"/></svg>', 'Report Lost Item', 1),
(5, 'Report Found Item', 'If you’ve found an item, use this service to provide details including a description, where and when it was found. This helps reunite lost items with their rightful owners quickly.', '<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\" class=\"lucide lucide-search-check-icon lucide-search-check\"><path d=\"m8 11 2 2 4-4\"/><circle cx=\"11\" cy=\"11\" r=\"8\"/><path d=\"m21 21-4.3-4.3\"/></svg>', 'Report Found Item', 1);

-- --------------------------------------------------------

--
-- Table structure for table `services_requests`
--

CREATE TABLE `services_requests` (
  `request_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `status_id` int(11) NOT NULL DEFAULT 1,
  `admin_remarks` text DEFAULT 'Just a quick note to let you know your request is currently under admin review. We’ll get back to you as soon as it’s approved — thanks for your patience!',
  `can_update` tinyint(1) NOT NULL DEFAULT 0,
  `requested_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services_requests`
--

INSERT INTO `services_requests` (`request_id`, `service_id`, `user_id`, `status_id`, `admin_remarks`, `can_update`, `requested_at`) VALUES
(1, 1, 23, 1, 'Just a quick note to let you know your request is currently under admin review. We’ll get back to you as soon as it’s approved — thanks for your patience!', 0, '2025-11-08 14:51:17'),
(2, 1, 22, 4, 'Your request was rejected.', 1, '2025-11-09 01:01:28'),
(3, 1, 24, 1, 'Just a quick note to let you know your request is currently under admin review. We’ll get back to you as soon as it’s approved — thanks for your patience!', 0, '2025-11-09 22:38:27'),
(4, 2, 24, 1, 'Just a quick note to let you know your request is currently under admin review. We’ll get back to you as soon as it’s approved — thanks for your patience!', 0, '2025-11-10 07:14:16'),
(5, 1, 26, 1, 'Just a quick note to let you know your request is currently under admin review. We’ll get back to you as soon as it’s approved — thanks for your patience!', 0, '2025-11-11 10:40:21'),
(6, 1, 27, 1, 'Just a quick note to let you know your request is currently under admin review. We’ll get back to you as soon as it’s approved — thanks for your patience!', 0, '2025-11-11 11:21:24'),
(7, 1, 28, 1, 'Just a quick note to let you know your request is currently under admin review. We’ll get back to you as soon as it’s approved — thanks for your patience!', 0, '2025-11-11 11:22:38'),
(8, 2, 27, 1, 'Just a quick note to let you know your request is currently under admin review. We’ll get back to you as soon as it’s approved — thanks for your patience!', 0, '2025-11-11 11:22:44'),
(9, 2, 28, 1, 'Just a quick note to let you know your request is currently under admin review. We’ll get back to you as soon as it’s approved — thanks for your patience!', 0, '2025-11-11 11:23:17'),
(10, 2, 41, 1, 'Just a quick note to let you know your request is currently under admin review. We’ll get back to you as soon as it’s approved — thanks for your patience!', 0, '2025-11-11 16:29:04'),
(11, 1, 26, 3, 'kunin mo na ito', 0, '2025-11-12 08:08:55'),
(12, 1, 26, 3, 'Your request is complete.', 0, '2025-11-12 09:17:01'),
(13, 1, 26, 1, 'Just a quick note to let you know your request is currently under admin review. We’ll get back to you as soon as it’s approved — thanks for your patience!', 0, '2025-11-13 15:03:49'),
(14, 1, 47, 1, 'Just a quick note to let you know your request is currently under admin review. We’ll get back to you as soon as it’s approved — thanks for your patience!', 0, '2025-11-13 15:27:02'),
(15, 2, 47, 1, 'Just a quick note to let you know your request is currently under admin review. We’ll get back to you as soon as it’s approved — thanks for your patience!', 0, '2025-11-13 15:32:51'),
(16, 2, 47, 1, 'Just a quick note to let you know your request is currently under admin review. We’ll get back to you as soon as it’s approved — thanks for your patience!', 0, '2025-11-13 15:35:16'),
(17, 4, 47, 1, 'Just a quick note to let you know your request is currently under admin review. We’ll get back to you as soon as it’s approved — thanks for your patience!', 0, '2025-11-13 15:36:29');

-- --------------------------------------------------------

--
-- Table structure for table `services_request_statuses`
--

CREATE TABLE `services_request_statuses` (
  `status_id` int(11) NOT NULL,
  `status_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `color_hex` varchar(7) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services_request_statuses`
--

INSERT INTO `services_request_statuses` (`status_id`, `status_name`, `description`, `color_hex`) VALUES
(1, 'Pending', 'Waiting for admin review.', '#FFC107'),
(2, 'In Progress', 'Your request is being processed.', '#007BFF'),
(3, 'Completed', 'Your request is complete.', '#28A745'),
(4, 'Rejected', 'Your request was rejected.', '#DC3545'),
(5, 'Needs Resubmission', 'Missing information or files.', '#FD7E14'),
(6, 'Testing Status', 'Custom status', '#c10dd9');

-- --------------------------------------------------------

--
-- Table structure for table `services_users`
--

CREATE TABLE `services_users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `email_verified_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services_users`
--

INSERT INTO `services_users` (`id`, `email`, `first_name`, `middle_name`, `last_name`, `suffix`, `password_hash`, `email_verified`, `email_verified_at`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES
(14, 'adorromeojohn0105@gmail.com', 'Romeo', 'Gonzales', 'Adore', '', '$2y$10$8ziiRf41Pv32inQ71q9zmOCMeMtx9HWJ46YsXkfsUrWQrENufeuJ.', 1, '2025-11-03 20:39:54', 1, '2025-11-13 15:31:12', '2025-11-03 20:18:11', '2025-11-13 15:31:12'),
(15, 'batfish31723@aminating.com', NULL, NULL, NULL, NULL, '$2y$10$n6Wr0g6Vn4l0zob8VSLMw.O5s6UJ8DwuXTCuitNly3fEkb9NE7bRW', 0, NULL, 1, NULL, '2025-11-08 09:30:29', '2025-11-08 09:30:29'),
(16, 'anaconda28553@mailshan.com', NULL, NULL, NULL, NULL, '$2y$10$LLZxFlrqS4oVZllJGt2a.ebYl3jkZy.hEdLwrd/UPwYixkZbddNS.', 0, NULL, 1, NULL, '2025-11-08 09:33:26', '2025-11-08 09:33:26'),
(17, 'hyena07539@aminating.com', NULL, NULL, NULL, NULL, '$2y$10$plK/s6MpbIku28Up4pFH.OM85MLtrl4Eesh/wL1J.Ae6tAf/JCV4O', 0, NULL, 1, NULL, '2025-11-08 09:35:04', '2025-11-08 09:35:04'),
(18, 'wombat15934@aminating.com', NULL, NULL, NULL, NULL, '$2y$10$a5Iq2eLKzqxjNkfoNrkhoOxbTtEsVO4CsgFe4NFpSWgIEO9ULvxS6', 0, NULL, 1, NULL, '2025-11-08 09:45:03', '2025-11-08 09:45:03'),
(21, 'adorromeojohn05@gmail.com', 'Mark Andrie', '', 'Datus', '', '$2y$10$phJwAq7kzUfzChHbA0kroeer9U0PS/3tY.f6fiQJurwcEVNpUgpDC', 1, '2025-11-08 09:52:26', 1, '2025-11-08 10:04:21', '2025-11-08 09:48:17', '2025-11-08 10:04:21'),
(22, 'renheartimpulsement@gmail.com', 'Renheart', '', 'Lopez', '', '$2y$10$UjqFkQC0PqbNiZblWL8pFO8CvVf4p29hUXa/POcZ3CMjshTkWRQ42', 1, '2025-11-08 11:13:39', 1, '2025-11-12 11:06:23', '2025-11-08 11:13:11', '2025-11-12 11:06:23'),
(23, 'datus_markandrie@plpasig.edu.ph', 'Mark', 'Dalisay', 'Datus', '', '$2y$10$OmdgNO3nbo1lt7OHBoKqgOKNicfg9uKFaJa5DPJsUBw5wcChGL0c2', 1, '2025-11-08 12:37:57', 1, '2025-11-13 14:42:42', '2025-11-08 12:36:39', '2025-11-13 14:42:42'),
(24, 'ador_romeojohn@plpasig.edu.ph', 'Romeo John', '', 'Ador', '', '$2y$10$z.lNOILGHBqpRlghwAs/LekIulh.oF6LNruoYFLh20AGE6mVpjVJW', 1, '2025-11-09 22:36:56', 1, '2025-11-10 07:13:02', '2025-11-09 22:36:28', '2025-11-10 07:13:02'),
(25, 'santosclyde867@gmail.com', 'Isshika', 'kishiel', 'Clamohoy', 'Yutuc', '$2y$10$jyixoQCUDNepRkWqKex0t.8fGHmHNVyyGhcmeG.Yd30xkkVbo4/YG', 1, '2025-11-11 10:30:15', 1, '2025-11-11 10:33:18', '2025-11-11 10:29:34', '2025-11-11 10:34:14'),
(26, 'datusmarkandrei@gmail.com', 'Student', 'one', 'datus', 'junior', '$2y$10$c3b3w/O0MaajAMXQvA/UY.y1gbv.AhiqDwASWXfcnbGq1PrhHmI0K', 1, '2025-11-11 10:38:36', 1, '2025-11-13 15:02:43', '2025-11-11 10:37:40', '2025-11-13 15:07:27'),
(27, 'triciadeasis7@gmail.com', 'Tricia', 'Delgado', 'De Asis', '', '$2y$10$X8oa7Pjo/d03TEG55vdxiu8HRheu/N1OAXsjBylp4g.DfxueAuIqC', 1, '2025-11-11 11:20:25', 1, '2025-11-11 11:20:46', '2025-11-11 11:19:52', '2025-11-11 11:21:03'),
(28, 'amerel82@gmail.com', 'Amerel', '', 'Disomnong', '', '$2y$10$HjOtP1pqPxOx/xEiUkbIReZLEiDc5zslel745lUTVCilqNvsDadTq', 1, '2025-11-11 11:20:57', 1, '2025-11-11 11:21:10', '2025-11-11 11:20:21', '2025-11-11 11:21:31'),
(29, 'jomar.aguirre.566@gmail.com', 'Jomar', 'Anibo', 'Aguirre', '', '$2y$10$gbKrwPRbl4mcSNa2OeI9GO3gvTH/AkrA8KJA4CpcwyoFLmKnCuFQK', 1, '2025-11-11 14:20:09', 1, '2025-11-11 14:20:27', '2025-11-11 11:30:19', '2025-11-11 14:20:49'),
(30, 'Bangcore_angel@plpasig.edu.ph', 'Angel', 'Rubio', 'Bangcore', '', '$2y$10$IwyDCe7a6PsDEHIO8.BJQe58CosHgj43qO9cqpuY1fPMcF4fAxQg.', 1, '2025-11-11 13:52:38', 1, '2025-11-11 13:54:10', '2025-11-11 13:51:51', '2025-11-11 13:54:27'),
(31, 'kthscenery30@gmail.com', 'Hiraeth', '', 'Hiraeth', '', '$2y$10$.sJVnL51TP7J4p9ypAFfv.6GCfwpegFB0w/qqV2WJf48IqOV5K6YG', 1, '2025-11-11 14:00:43', 1, '2025-11-11 14:01:06', '2025-11-11 13:58:55', '2025-11-11 14:01:20'),
(32, 'francispeter.bundalian@my.jru.edu', 'Francis', '', 'Bundalian', '', '$2y$10$0EKPLBXz3/GOtqy6cvxHSu4tgYwTAKoEeIeRWQJDMcslbE9oWWore', 1, '2025-11-11 14:31:38', 1, '2025-11-11 14:31:55', '2025-11-11 14:31:06', '2025-11-11 14:32:08'),
(33, 'codera_isabela@plpasig.edu.ph', 'Isabela', '', 'Codera', '', '$2y$10$5Pm7GewM0etGq2E1aZ3WLeiEEi2froMhZgoe5a62c3/enmmT3pwzC', 1, '2025-11-11 14:37:17', 1, '2025-11-11 14:37:39', '2025-11-11 14:35:24', '2025-11-11 14:38:04'),
(34, 'andreyaa932@gmail.com', NULL, NULL, NULL, NULL, '$2y$10$v32prR6vzM9d5ubNRss/NOlYYeQ0IMzipHvdpHxudIgwKA4agRJSG', 0, NULL, 1, NULL, '2025-11-11 15:11:28', '2025-11-11 15:11:28'),
(35, 'ahgomez6949ant@student.fatima.edu.ph', 'Andrea', 'Hiyao', 'Gomez', '', '$2y$10$yPbAxFXnY88of4XcsdiTPuCOrBJwbrc2NVOQOrvRx.jnE8nEVlp4i', 1, '2025-11-11 15:30:06', 1, '2025-11-11 15:30:17', '2025-11-11 15:29:42', '2025-11-11 15:30:32'),
(36, 'abogerrald2403@gmail.com', NULL, NULL, NULL, NULL, '$2y$10$.A81b3ZqjEFIo8GVNbIOTeUR1pRthUvx5fulXfutj3h/hEMKB.JMC', 1, '2025-11-11 15:48:45', 1, NULL, '2025-11-11 15:47:25', '2025-11-11 15:48:45'),
(37, 'doumamatsuno@gmail.com', NULL, NULL, NULL, NULL, '$2y$10$oT5oELLKtBxEHfp2TliET.urLF5HSdPOmbNyLqN6o.zPLmnBk995S', 0, NULL, 1, NULL, '2025-11-11 15:50:06', '2025-11-11 15:50:06'),
(38, 'gerraldabo24@gmail.com', NULL, NULL, NULL, NULL, '$2y$10$kXXryEA0.OFMfEZtB/q2muW5EUp5WJWfNPsRn8hVKDOplGXm8t3cW', 0, NULL, 1, NULL, '2025-11-11 15:51:05', '2025-11-11 15:51:05'),
(39, 'heraldogray@gmail.com', NULL, NULL, NULL, NULL, '$2y$10$wYN8HIYo4D2gwtXD0IWzve.xPNgyTq4Y8mW6VGr6yVmu0foaPrUM.', 0, NULL, 1, NULL, '2025-11-11 15:51:36', '2025-11-11 15:51:36'),
(40, 'nosib74615@fermiro.com', NULL, NULL, NULL, NULL, '$2y$10$Jlzi4yaF2cmfFDmxeN0BXe/kDJ8hzT2aPys..eM4BFRkcUywuRa7G', 0, NULL, 1, NULL, '2025-11-11 15:54:01', '2025-11-11 15:54:01'),
(41, 'cicel17015@gyknife.com', 'Gerrald', 'Aquino', 'Abo', '', '$2y$10$cGxuYgdFn9m5gh92ImtLDOvA.N3kLNVx5SUrLlPjNWftTZwskR.jC', 1, '2025-11-11 16:11:42', 1, '2025-11-13 14:48:24', '2025-11-11 16:08:16', '2025-11-13 14:48:24'),
(42, 'pjgvaldez1008@gmail.com', 'PJ', '', 'Valdez', '', '$2y$10$w4grGc0ZTeN7ZDh.9EGsLOIjRQ0adDuwdKH1b7yVaBuRV1SoiFcja', 1, '2025-11-12 12:44:47', 1, '2025-11-12 12:45:03', '2025-11-12 12:44:14', '2025-11-12 12:45:16'),
(43, 'gipanep625@canvect.com', NULL, NULL, NULL, NULL, '$2y$10$knBc5sv7PKYty9LnpGe5UebYQipDT9JXn0SdhNxdDu/U7Z2s6Ryse', 0, NULL, 1, NULL, '2025-11-13 00:23:44', '2025-11-13 00:23:44'),
(44, 'doyaw45859@chaineor.com', NULL, NULL, NULL, NULL, '$2y$10$OXZwjVvJ23O6azgVkiE3z.9qKNcW9B6WUKdT1qYW06/xlRXWugjKu', 0, NULL, 1, NULL, '2025-11-13 00:28:11', '2025-11-13 00:28:11'),
(45, 'gamboa.khinandrei@gmail.com', 'Khin Andrei', 'Roque', 'Gamboa', '', '$2y$10$u3hIX62WsSTwDJYTw/Gn0utisz4jLwunZ0Ka6fD9ujMs4M7hS9zxu', 1, '2025-11-13 02:54:16', 1, '2025-11-13 02:54:53', '2025-11-13 02:53:49', '2025-11-13 02:55:19'),
(46, 'nasogeh333@fermiro.com', 'Gerrald', 'Aquino', 'Abo', '', '$2y$10$/gbmBLwobmNyyVpq/IPcd.sglgzpQF84P7lyd3eT1My4KhT9z7Kg2', 1, '2025-11-13 15:00:20', 1, '2025-11-13 15:01:11', '2025-11-13 14:59:50', '2025-11-13 15:01:34'),
(47, 'keveh19414@canvect.com', 'Gerrald', 'Aquino', 'Abo', '', '$2y$10$v7hMbITzAudMuaNPBnihpupe.L0Han63bzCwYw/GwKlBQFNDj69ZO', 1, '2025-11-13 15:02:49', 1, '2025-11-13 16:18:31', '2025-11-13 15:02:21', '2025-11-13 16:18:31');

-- --------------------------------------------------------

--
-- Table structure for table `smtp_config`
--

CREATE TABLE `smtp_config` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'inactive'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `smtp_config`
--

INSERT INTO `smtp_config` (`id`, `username`, `password`, `address`, `name`, `status`) VALUES
(1, 'plpasig.sso@gmail.com', 'npla ugmc iafq mvaf', 'smtp.gmail.com', 'Pamantasan ng Lungsod ng Pasig - Student Success Office', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `sso_officers`
--

CREATE TABLE `sso_officers` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `suffix` varchar(50) DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `profile_image_url` varchar(255) DEFAULT NULL,
  `status` enum('active','archived') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sso_officers`
--

INSERT INTO `sso_officers` (`id`, `first_name`, `middle_name`, `last_name`, `suffix`, `title`, `email`, `profile_image_url`, `status`) VALUES
(1, 'Arlene', NULL, 'Daniel', NULL, 'Student Success Office Director', 'sample@plpasig.edu.ph', NULL, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `sso_user`
--

CREATE TABLE `sso_user` (
  `id` int(11) NOT NULL,
  `first_name` varchar(250) NOT NULL,
  `middle_name` varchar(250) DEFAULT NULL,
  `last_name` varchar(250) NOT NULL,
  `suffix` varchar(250) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('active','archived') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sso_user`
--

INSERT INTO `sso_user` (`id`, `first_name`, `middle_name`, `last_name`, `suffix`, `email`, `password`, `status`, `created_at`) VALUES
(1, 'ROMEO JOHN', NULL, 'ADOR', NULL, 'testuser@example.com', 'some_password_hash', 'active', '2025-10-23 19:57:01'),
(2, 'Romeo John', '', 'Ador', '', 'admin@sso.edu', '$2y$10$SfQTD0pmghZ1rjlZrTiMguI7Vp7ORdw83IyekWLTEcj8CkEWAXHLy', 'active', '2025-10-26 21:33:22'),
(3, '', NULL, '', NULL, 'admin@sso.edu.ph', '$2y$10$gsh6vf8hlqBdU4T.Ao.Mpuv1xWcMDV/jGobuXwpSgpafqn5L8WL1u', 'active', '2025-10-27 07:43:37'),
(4, 'John', 'Michael', 'Doe', 'Jr.', 'admin@test.com', '$2y$10$BuOzHpV3B8Ran5YlHL7O4.PA23HydDsJSiMN1HUawyX2UCt.i2EwO', 'active', '2025-10-27 13:43:56'),
(5, 'Mark Andrie', '', 'Dalisay', '', 'mark_andrie@sso.plpasig', '$2y$10$3Otsk94a9Dbq1HtqT5i1y.FvrmMKiZYafbXA9vbCIWc3j8mebpop2', 'active', '2025-11-13 05:04:52'),
(6, 'Gerrald', '', 'Abo', '', 'gerrald_abo@sso.plpasig', '$2y$10$oKzzfPgB4VaXX0leuzN/MediggOvuf7OGo.S2dFm8052e5AQYfGme', 'active', '2025-11-13 05:12:15');

-- --------------------------------------------------------

--
-- Table structure for table `statuses`
--

CREATE TABLE `statuses` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL COMMENT 'e.g., Pending, Accepted',
  `remarks` text NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `hex_color` varchar(250) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `statuses`
--

INSERT INTO `statuses` (`id`, `name`, `remarks`, `is_default`, `hex_color`) VALUES
(1, 'Pending', 'Thank you for your interest in applying for admission at Pamantasan ng Lungsod ng Pasig. We have received your reply. Please wait for an email regarding the status of your application. For more information, please visit our FB page Pamantasan ng Lungsod ng Pasig... Daluyan ng Pag-asa https://www.facebook.com/Pamantasan-ng-Lungsod-ng. Pasig-108294641685841/  \n\nNOTE: You can only complete this online application ONCE. Attempting to fill out or sign up for the online application multiple times using different emails but the same account/application will NULLIFY your admission registration.  \n\nRegularly check your email(inbox and spam folder) for application updates, as we will send all notifications there. Kindly inform the admissions office immediately of any change to your email address to prevent difficulties  \n\nPLEASE MAKE SURE ALL YOUR ENTRIES ARE CORRECT BEFORE SUBMITTING YOUR APPLICATION', 1, '#FACC15'),
(2, 'Document Need to Review Face to Face', 'Please visit the campus for a quick face-to-face document review. Our staff will gladly assist you.', 0, '#FFA500'),
(3, 'Missing Documents', 'Some required documents are still missing (e.g., Transcript of Records, Form 137, or Birth Certificate). Kindly submit the remaining items to complete your application.', 0, '#FF4C4C'),
(4, 'Examination', 'Your entrance examination is scheduled. We wish you the best of luck!', 0, '#1E90FF'),
(5, 'Rejected', 'We appreciate your interest. Unfortunately, this application did not meet our current admission requirements.', 0, '#808080'),
(6, 'Dean Interview', 'Your application is progressing well. Please prepare for your interview with the Dean.', 0, '#8A2BE2'),
(7, 'Passed', 'Congratulations! You’ve successfully met all admission requirements. Welcome to our college community!', 0, '#28A745');

-- --------------------------------------------------------

--
-- Table structure for table `submissions`
--

CREATE TABLE `submissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `applicant_type_id` int(11) NOT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `remarks` text DEFAULT 'Thank you for your submission. Your application is still being processed, and additional time is required to complete the review.',
  `can_update` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `submissions`
--

INSERT INTO `submissions` (`id`, `user_id`, `applicant_type_id`, `submitted_at`, `status`, `remarks`, `can_update`) VALUES
(17, 5, 5, '2025-11-12 01:18:26', 'Pending', 'Thank you for your interest in applying for admission at Pamantasan ng Lungsod ng Pasig. We have received your reply. Please wait for an email regarding the status of your application. For more information, please visit our FB page Pamantasan ng Lungsod ng Pasig... Daluyan ng Pag-asa https://www.facebook.com/Pamantasan-ng-Lungsod-ng. Pasig-108294641685841/  \n\nNOTE: You can only complete this online application ONCE. Attempting to fill out or sign up for the online application multiple times using different emails but the same account/application will NULLIFY your admission registration.  \n\nRegularly check your email(inbox and spam folder) for application updates, as we will send all notifications there. Kindly inform the admissions office immediately of any change to your email address to prevent difficulties  \n\nPLEASE MAKE SURE ALL YOUR ENTRIES ARE CORRECT BEFORE SUBMITTING YOUR APPLICATION', 0),
(18, 11, 3, '2025-11-12 15:41:34', 'Pending', 'Thank you for your interest in applying for admission at Pamantasan ng Lungsod ng Pasig. We have received your reply. Please wait for an email regarding the status of your application. For more information, please visit our FB page Pamantasan ng Lungsod ng Pasig... Daluyan ng Pag-asa https://www.facebook.com/Pamantasan-ng-Lungsod-ng. Pasig-108294641685841/  \n\nNOTE: You can only complete this online application ONCE. Attempting to fill out or sign up for the online application multiple times using different emails but the same account/application will NULLIFY your admission registration.  \n\nRegularly check your email(inbox and spam folder) for application updates, as we will send all notifications there. Kindly inform the admissions office immediately of any change to your email address to prevent difficulties  \n\nPLEASE MAKE SURE ALL YOUR ENTRIES ARE CORRECT BEFORE SUBMITTING YOUR APPLICATION', 0),
(19, 1, 3, '2025-11-12 18:22:19', 'Pending', 'Thank you for your interest in applying for admission at Pamantasan ng Lungsod ng Pasig. We have received your reply. Please wait for an email regarding the status of your application. For more information, please visit our FB page Pamantasan ng Lungsod ng Pasig... Daluyan ng Pag-asa https://www.facebook.com/Pamantasan-ng-Lungsod-ng. Pasig-108294641685841/  \n\nNOTE: You can only complete this online application ONCE. Attempting to fill out or sign up for the online application multiple times using different emails but the same account/application will NULLIFY your admission registration.  \n\nRegularly check your email(inbox and spam folder) for application updates, as we will send all notifications there. Kindly inform the admissions office immediately of any change to your email address to prevent difficulties  \n\nPLEASE MAKE SURE ALL YOUR ENTRIES ARE CORRECT BEFORE SUBMITTING YOUR APPLICATION', 0),
(21, 19, 1, '2025-11-13 14:55:04', 'Pending', 'Thank you for your interest in applying for admission at Pamantasan ng Lungsod ng Pasig. We have received your reply. Please wait for an email regarding the status of your application. For more information, please visit our FB page Pamantasan ng Lungsod ng Pasig... Daluyan ng Pag-asa https://www.facebook.com/Pamantasan-ng-Lungsod-ng. Pasig-108294641685841/  \n\nNOTE: You can only complete this online application ONCE. Attempting to fill out or sign up for the online application multiple times using different emails but the same account/application will NULLIFY your admission registration.  \n\nRegularly check your email(inbox and spam folder) for application updates, as we will send all notifications there. Kindly inform the admissions office immediately of any change to your email address to prevent difficulties  \n\nPLEASE MAKE SURE ALL YOUR ENTRIES ARE CORRECT BEFORE SUBMITTING YOUR APPLICATION', 0);

-- --------------------------------------------------------

--
-- Table structure for table `submission_data`
--

CREATE TABLE `submission_data` (
  `id` int(11) NOT NULL,
  `submission_id` int(11) NOT NULL,
  `field_name` varchar(255) NOT NULL,
  `field_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `submission_data`
--

INSERT INTO `submission_data` (`id`, `submission_id`, `field_name`, `field_value`) VALUES
(1, 17, 'testing_input', 'Testing input Updated'),
(2, 18, 'email_address', 'adorromeojohn0105@gmail.com'),
(3, 18, 'i_hereby_certify_that_all_the_information_and_documents_attached_herein_are_true_and_correct_to_the_best_of_my_knowledge_and_belief_i_am_fully_aware_that_submitting_fake_or_tampered_documents_or_any_form_of_dishonesty_are_grounds_for_disqualification_for_', 'Agree'),
(4, 18, 'lrn_number', ''),
(5, 18, 'sex_kasarian', 'Male'),
(6, 18, 'date_of_birth', '2004-01-05'),
(7, 18, 'surname_apelyido', 'Ador'),
(8, 18, 'middle_name_gitnang_pangalan', ''),
(9, 18, 'given_name_pangalan', 'Romeo John'),
(10, 18, 'contact_number', '09516154757'),
(11, 18, 'alternative_email_address', 'adorromeojohn05@gmail.com'),
(12, 18, 'are_you_a_member_of_the_lgbtqia', 'No'),
(13, 18, 'are_you_a_person_with_disability', 'No'),
(14, 18, 'do_you_have_any_relative_s_working_in_the_city_government_of_pasig_in_pamantasan_ng_lungsod_ng_pasig_and_or_affiliated_companies_subsidiaries_within_the_second_degree_of_consanguinity_or_affinity_e_g_spouse_parents_son_daughter_brother_sister_grandparents', 'No'),
(15, 18, 'residency_status', 'Non-Pasig Resident'),
(16, 18, 'address_house_number_unit_building_street_subdivision_village_barangay_city', 'House 123 D makita st. nakatago city'),
(17, 18, 'type_of_school', 'Public'),
(18, 18, 'last_school_attended', 'Last Senior High School '),
(19, 18, 'general_average_in_filipino', '99'),
(20, 18, 'general_average_in_english', '99'),
(21, 18, 'general_average_in_mathematics', '99'),
(22, 18, 'general_average_in_science', '99'),
(23, 18, 'overall_general_average_gwa', '99'),
(24, 18, 'check_whether_you_have_the_following_requirements', 'Certified True Copy (CTC) of Form 137 with remarks \"For Evaluation Purposes only\", Any Two (2) Government Issued ID / School D of applicant thattasbirthidate and address, PSA Birth Certificate, Two Passport size picture, white background with nameplate, Notarized Affidavit of guardianship (for applicants with guardian), Barangay Residence Certificate'),
(26, 19, 'i_hereby_certify_that_all_the_information_and_documents_attached_herein_are_true_and_correct_to_the_best_of_my_knowledge_and_belief_i_am_fully_aware_that_submitting_fake_or_tampered_documents_or_any_form_of_dishonesty_are_grounds_for_disqualification_for_', 'Agree'),
(38, 19, 'do_you_have_any_relative_s_working_in_the_city_government_of_pasig_in_pamantasan_ng_lungsod_ng_pasig_and_or_affiliated_companies_subsidiaries_within_the_second_degree_of_consanguinity_or_affinity_e_g_spouse_parents_son_daughter_brother_sister_grandparents', 'No'),
(51, 19, 'email_address', 'datusmarkandrei@gmail.com'),
(52, 19, 'i_hereby_certify_that_all_the_information_and_documents_attached_herein_are_true_and_correct_to_the_best_of_my_knowledge_and_belief_i_am_fully_aware_that_submitting_fake_or_tampered_documents_or_any_form_of_dishonesty_are_grounds_for_disqualification_for_', 'Agree'),
(53, 19, 'lrn_number', ''),
(54, 19, 'sex_kasarian', 'Female'),
(55, 19, 'date_of_birth', '2004-06-12'),
(56, 19, 'surname_apelyido', 'alvarez'),
(57, 19, 'middle_name_gitnang_pangalan', 'datus'),
(58, 19, 'given_name_pangalan', 'juliana'),
(59, 19, 'contact_number', '09354826798'),
(60, 19, 'alternative_email_address', 'julianaalvarez@gmail.com'),
(61, 19, 'are_you_a_member_of_the_lgbtqia', 'No'),
(62, 19, 'are_you_a_person_with_disability', 'Yes'),
(63, 19, 'kindly_specify_your_disability', 'heartbroken'),
(64, 19, 'do_you_have_any_relative_s_working_in_the_city_government_of_pasig_in_pamantasan_ng_lungsod_ng_pasig_and_or_affiliated_companies_subsidiaries_within_the_second_degree_of_consanguinity_or_affinity_e_g_spouse_parents_son_daughter_brother_sister_grandparents', 'No'),
(65, 19, 'residency_status', 'Pasig Resident'),
(66, 19, 'district', 'District 2'),
(67, 19, 'barangay_district_2', 'Rosario'),
(68, 19, 'address_house_number_unit_building_street_subdivision_village', '14-A mamerto district rosario pasig city'),
(69, 19, 'type_of_school', 'Public'),
(70, 19, 'last_school_attended', 'rhs'),
(71, 19, 'general_average_in_filipino', '55'),
(72, 19, 'general_average_in_english', '55'),
(73, 19, 'general_average_in_mathematics', '55'),
(74, 19, 'general_average_in_science', '55'),
(75, 19, 'overall_general_average_gwa', '55'),
(76, 19, 'check_whether_you_have_the_following_requirements', 'Certified True Copy (CTC) of Form 137 with remarks \"For Evaluation Purposes only\", Any Two (2) Government Issued ID / School D of applicant thattasbirthidate and address, PSA Birth Certificate, Two Passport size picture, white background with nameplate, Notarized Affidavit of guardianship (for applicants with guardian), Barangay Residence Certificate'),
(77, 21, 'email_address', 'datusmarkandrei@gmail.com'),
(78, 21, 'i_hereby_certify_that_all_the_information_and_documents_attached_herein_are_true_and_correct_to_the_best_of_my_knowledge_and_belief_i_am_fully_aware_that_submitting_fake_or_tampered_documents_or_any_form_of_dishonesty_are_grounds_for_disqualification_for_', 'Agree'),
(79, 21, 'lrn_number', '225501902932'),
(80, 21, 'sex_kasarian', 'Male'),
(81, 21, 'date_of_birth', '2004-09-08'),
(82, 21, 'surname_apelyido', 'Datus'),
(83, 21, 'middle_name_gitnang_pangalan', 'Cruz'),
(84, 21, 'given_name_pangalan', 'Mark Andrie'),
(85, 21, 'contact_number', '097777788833'),
(86, 21, 'alternative_email_address', 'Datusmarkandrie@gmail.com'),
(87, 21, 'are_you_a_member_of_the_lgbtqia', 'No'),
(88, 21, 'are_you_a_person_with_disability', 'No'),
(89, 21, 'do_you_have_any_relative_s_working_in_the_city_government_of_pasig_in_pamantasan_ng_lungsod_ng_pasig_and_or_affiliated_companies_subsidiaries_within_the_second_degree_of_consanguinity_or_affinity_e_g_spouse_parents_son_daughter_brother_sister_grandparents', 'No'),
(90, 21, 'marginalized_applicant', 'A beneficiary of DSWD Listahanan or Pantawid Pamilyang Pilipino Program (4Ps), A children of migrant workers (OFWs) with OWWA Certification'),
(91, 21, 'residency_status', 'Pasig Resident'),
(92, 21, 'district', 'District 2'),
(93, 21, 'barangay', 'Rosario'),
(94, 21, 'address_house_number_unit_building_street_subdivision_village', '14-A mamerto district rosario pasig city'),
(95, 21, 'type_of_school', 'Public'),
(96, 21, 'last_school_attended', 'rhs'),
(97, 21, 'general_average_in_filipino', '99'),
(98, 21, 'general_average_in_english', '99'),
(99, 21, 'general_average_in_mathematics', '99'),
(100, 21, 'general_average_in_science', '99'),
(101, 21, 'overall_general_average_gwa', '99'),
(102, 21, 'check_whether_you_have_the_following_requirements', 'PSA Birth Certificate');

-- --------------------------------------------------------

--
-- Table structure for table `submission_files`
--

CREATE TABLE `submission_files` (
  `id` int(11) NOT NULL,
  `submission_id` int(11) NOT NULL,
  `field_name` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_path` varchar(512) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `submission_files`
--

INSERT INTO `submission_files` (`id`, `submission_id`, `field_name`, `original_filename`, `file_path`) VALUES
(7, 17, 'testing_file', 'SSO-Dashboard-Report-services-month.pdf', 'http://localhost/pages/src/media/private/b4e5f657e58dcb1f_SSO-Dashboard-Report-services-month.pdf'),
(8, 18, 'psa_birth_certificate', 'System Evaluation Tool.docx.pdf', 'http://localhost/pages/src/media/private/b6db78e50842462e_System_Evaluation_Tool.docx.pdf'),
(9, 18, 'psa_marriage_certificate_if_married', 'System Evaluation Tool.docx.pdf', 'http://localhost/pages/src/media/private/214e634b44d5dd2c_System_Evaluation_Tool.docx.pdf'),
(10, 18, 'certified_true_copy_verified_copy_of_form_138', 'SSO-Dashboard-Report-services-month.pdf', 'http://localhost/pages/src/media/private/f2b801d6b8b2795b_SSO-Dashboard-Report-services-month.pdf'),
(11, 18, 'any_two_2_government_issued_id_school_id_of_applicant_that_has_birthdate_and_address', 'SSO-Dashboard-Report-month.pdf', 'http://localhost/pages/src/media/private/02c09f0e76c39d61_SSO-Dashboard-Report-month.pdf'),
(12, 18, 'barangay_residence_certificate', 'SSO-Dashboard-Report-services-month.pdf', 'http://localhost/pages/src/media/private/fbf0fe727f1013cf_SSO-Dashboard-Report-services-month.pdf'),
(13, 18, 'two_2_passport_size_picture_white_background_with_digital_nameplate', 'SSO-Dashboard-Report-services-month.pdf', 'http://localhost/pages/src/media/private/43372833af04fc3d_SSO-Dashboard-Report-services-month.pdf');

-- --------------------------------------------------------

--
-- Table structure for table `tokenization`
--

CREATE TABLE `tokenization` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(250) NOT NULL,
  `value` text NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tokenization`
--

INSERT INTO `tokenization` (`id`, `user_id`, `name`, `value`, `is_used`, `expires_at`, `created_at`) VALUES
(1, 1, 'VERIFY_ACCOUNT', 'c995ffe3064f9defbaebceb941aafe4239318fc1ef26e90b866b1cfcfeac3803', 1, '2025-10-18 16:11:24', '2025-10-17 16:10:01'),
(2, 1, 'RESET_PASSWORD', '2070763c96b1b1a7ac478c0e47df97724163f3690f8785a41408b633d264b8f1', 0, '2025-11-17 18:48:00', '2025-10-17 18:40:04'),
(3, 1, 'SESSION', '9c489f2b917e7c9f02ef2047787726cf2dfc346ee0c9a1b3b6aeba31fddda062', 0, '2025-11-20 14:02:46', '2025-10-17 20:26:02'),
(4, 2, 'VERIFY_ACCOUNT', 'ad31fffc6cff947fc9b46aebdf9e5fe137fa1832bcb11f56e81e827f9e316fd5', 0, '2025-11-04 07:08:19', '2025-11-03 04:31:29'),
(5, 3, 'VERIFY_ACCOUNT', '2032feed40430a491a13d49a617daccca67fbc9ae56479fedb58e5c3c8a8b4cb', 0, '2025-11-12 02:48:56', '2025-11-07 20:34:12'),
(6, 4, 'VERIFY_ACCOUNT', '740cebb3471fa7ad26afd176fa967de7c6100d933bcea29f1a50a1a82a4d549d', 0, '2025-11-12 02:55:41', '2025-11-11 02:49:56'),
(7, 5, 'VERIFY_ACCOUNT', '1c225fc8f1bf09f1adcfd1fc555105a3f24f69eb43cb4fb8dbfda8dfdd153bda', 1, '2025-11-12 02:54:05', '2025-11-11 02:54:05'),
(8, 5, 'SESSION', 'ee25f9e98cc9169825715ffe13e3b4b14a21dd065c978a84f103bd9dec4091eb', 0, '2025-11-19 06:03:16', '2025-11-11 02:54:53'),
(14, 11, 'VERIFY_ACCOUNT', 'e5d90e7779b0009008b7ea6cce841279980dd0d74adc8ca72905210926873855', 1, '2025-11-13 06:18:00', '2025-11-12 06:18:00'),
(16, 11, 'SESSION', 'b08d847887548f687241c178ece8764270257f41bff44dab0be19b0254d76d57', 0, '2025-11-19 10:20:05', '2025-11-12 06:21:28'),
(23, 19, 'VERIFY_ACCOUNT', '79e584b3e3593dd72bcbf680fdeeae4d2978850be90ea1e87bb76c0d672b3e8e', 1, '2025-11-14 14:48:38', '2025-11-13 14:48:38'),
(24, 19, 'SESSION', 'a7b0538ab9e2858eabc68d64f0db37572c349583cbe728dc6d67c7cb9154a921', 0, '2025-11-20 14:50:12', '2025-11-13 14:50:12');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `role` enum('student','applicant','sso','admin') NOT NULL,
  `acc_type` enum('admission','main') NOT NULL,
  `acc_status` enum('not_verified','banned','deleted','active','deactivated','locked') DEFAULT 'not_verified',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `role`, `acc_type`, `acc_status`, `created_at`, `updated_at`) VALUES
(1, 'Mc+Mcxig2rdxnixLZkgbFUZib24yTStwUThXRHNGcklrd0w1MklLUFJCL2IrVENzc0h6a2hodVVyM2M9', '$2y$10$qqkbJr8jYNemgO.L1poJAudVVatRG1J7AU7lFmGN/49VenmXC6EPm', 'applicant', 'admission', 'active', '2025-10-17 16:10:01', '2025-11-13 05:39:11'),
(2, 'zmsCoQ2CE3SGf2BbhhvtW3k4ZUczbTg0WHI1bXA1TUdmSzZGbDB1L1ptZExPaEF6TFo4cFF0OWs1S0E9', '$2y$10$vs7pye8l7AWgAXQjBBLegOTJBrFFaXjS9nMoBSu8XtigsektxtSKy', 'applicant', 'admission', 'not_verified', '2025-11-03 04:31:27', '2025-11-03 04:31:27'),
(3, 'BwkLzX0Of7DS7MNTUKo06nhkOHprcy9aTm5RQzV6UzNGV1lzdEY5U2FweFE3NW9BaXZaNEtzb3FwdmM9', '$2y$10$SPl5OAHoBxu916gu/alTbeHQn8GuOgGWrvYSKd/EyqrQlu4JXTo2m', 'applicant', 'admission', 'not_verified', '2025-11-07 20:34:11', '2025-11-07 20:34:11'),
(4, 'mXxQwIrdtyg7UWuc3In6WXVQNjNiUlh3TjI1STBGT3pNTm10T2dhUFRocVNmSWZWbDNsYTRzcjVMS0E9', '$2y$10$QxVtiRXkjS1WQCeNVd0w7.zCoxNGP1PEr1br17P.Ug6MKwYwfgF4m', 'applicant', 'admission', 'not_verified', '2025-11-11 02:49:55', '2025-11-11 02:49:55'),
(5, 'kTXjbu2P9Sgh/QVZooZcvkliZ0JFcERJUllNeVBrdjE5TTVoODQzeThBYjltOFA1L2xzdkI5N1Nxc1k9', '$2y$10$WDiSJ/TljpvYzRFAmUtx9O9aRxVEsOtJBgNsYK/ylDjqjKAwKpXtW', 'applicant', 'admission', 'active', '2025-11-11 02:54:02', '2025-11-11 02:54:37'),
(11, 'dMgRrbuGcszL2kVYi3KKhjNsb1VqbWpoS2tidGlhU0NjT2ZpOGZjTzRJLzRVMlFqUndnRTZzZncvdFE9', '$2y$10$RJ6vfhDoWvSgO6/wcgMyTeylgKqrRLnbUyYg7E4xJPgUELRpCobfC', 'applicant', 'admission', 'active', '2025-11-12 06:17:59', '2025-11-12 12:48:01'),
(19, '2uVmFcbW/HQSa2Pr4xIUtENOeXI5dVlSUHZuNWg5V2psTDhWL3ZRUVRJbUJNdXIrTGtlanBiRmZsMFE9', '$2y$10$n6Qx.9PAenD8bF0lFpBITuuXR/MmMR/8G2yepabrkCjn9F.m3IkAG', 'applicant', 'admission', 'active', '2025-11-13 14:48:36', '2025-11-13 14:49:48');

-- --------------------------------------------------------

--
-- Table structure for table `user_fullname`
--

CREATE TABLE `user_fullname` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(250) NOT NULL,
  `middle_name` varchar(250) DEFAULT NULL,
  `last_name` varchar(250) NOT NULL,
  `suffix` varchar(250) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_fullname`
--

INSERT INTO `user_fullname` (`id`, `user_id`, `first_name`, `middle_name`, `last_name`, `suffix`, `last_updated`) VALUES
(1, 1, 'Romeo John', '', 'Ador', '', '2025-10-19 21:58:51'),
(2, 5, 'Mark Andrie', '', 'Datus', '', '2025-11-11 03:16:53'),
(3, 11, 'E', '', 'Fernandez', '', '2025-11-12 06:21:50'),
(4, 19, 'Mark Andrie', 'Cruz', 'Datus', '', '2025-11-13 14:50:43');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `acc_locking`
--
ALTER TABLE `acc_locking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `admission_controller`
--
ALTER TABLE `admission_controller`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admission_controller_fk_user` (`user_id`);

--
-- Indexes for table `admission_cycles`
--
ALTER TABLE `admission_cycles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_archived` (`is_archived`);

--
-- Indexes for table `announcement`
--
ALTER TABLE `announcement`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `announcement_tag`
--
ALTER TABLE `announcement_tag`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `api_list`
--
ALTER TABLE `api_list`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `applicant_number_prefix`
--
ALTER TABLE `applicant_number_prefix`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `applicant_types`
--
ALTER TABLE `applicant_types`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admission_cycle_id` (`admission_cycle_id`);

--
-- Indexes for table `application_permit`
--
ALTER TABLE `application_permit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_application_user` (`user_id`);

--
-- Indexes for table `contact_support`
--
ALTER TABLE `contact_support`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `downloadable_forms`
--
ALTER TABLE `downloadable_forms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_slug` (`slug`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `email_template`
--
ALTER TABLE `email_template`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ExamRegistrations`
--
ALTER TABLE `ExamRegistrations`
  ADD PRIMARY KEY (`registration_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `schedule_id` (`schedule_id`);

--
-- Indexes for table `ExamSchedules`
--
ALTER TABLE `ExamSchedules`
  ADD PRIMARY KEY (`schedule_id`);

--
-- Indexes for table `expiration_config`
--
ALTER TABLE `expiration_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `type` (`type`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `form_fields`
--
ALTER TABLE `form_fields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `step_id` (`step_id`),
  ADD KEY `idx_archived` (`is_archived`);

--
-- Indexes for table `form_field_options`
--
ALTER TABLE `form_field_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `field_id` (`field_id`);

--
-- Indexes for table `form_steps`
--
ALTER TABLE `form_steps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `applicant_type_id` (`applicant_type_id`),
  ADD KEY `idx_archived` (`is_archived`);

--
-- Indexes for table `general_uploads`
--
ALTER TABLE `general_uploads`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `msg_config`
--
ALTER TABLE `msg_config`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `otp_user`
--
ALTER TABLE `otp_user`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `remark_templates`
--
ALTER TABLE `remark_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `services_answers`
--
ALTER TABLE `services_answers`
  ADD PRIMARY KEY (`answer_id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `field_id` (`field_id`);

--
-- Indexes for table `services_email_otp_codes`
--
ALTER TABLE `services_email_otp_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email_otp_code` (`six_digit`),
  ADD KEY `idx_email_otp_user` (`user_id`);

--
-- Indexes for table `services_fields`
--
ALTER TABLE `services_fields`
  ADD PRIMARY KEY (`field_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `fk_visible_option` (`visible_when_option_id`);

--
-- Indexes for table `services_field_options`
--
ALTER TABLE `services_field_options`
  ADD PRIMARY KEY (`option_id`),
  ADD KEY `field_id` (`field_id`);

--
-- Indexes for table `services_list`
--
ALTER TABLE `services_list`
  ADD PRIMARY KEY (`service_id`);

--
-- Indexes for table `services_requests`
--
ALTER TABLE `services_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status_id` (`status_id`);

--
-- Indexes for table `services_request_statuses`
--
ALTER TABLE `services_request_statuses`
  ADD PRIMARY KEY (`status_id`),
  ADD UNIQUE KEY `status_name` (`status_name`);

--
-- Indexes for table `services_users`
--
ALTER TABLE `services_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`);

--
-- Indexes for table `smtp_config`
--
ALTER TABLE `smtp_config`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sso_officers`
--
ALTER TABLE `sso_officers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sso_user`
--
ALTER TABLE `sso_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `statuses`
--
ALTER TABLE `statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_type` (`user_id`,`applicant_type_id`),
  ADD UNIQUE KEY `uniq_user` (`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `applicant_type_id` (`applicant_type_id`);

--
-- Indexes for table `submission_data`
--
ALTER TABLE `submission_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `submission_id` (`submission_id`);

--
-- Indexes for table `submission_files`
--
ALTER TABLE `submission_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `submission_id` (`submission_id`);

--
-- Indexes for table `tokenization`
--
ALTER TABLE `tokenization`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_fullname`
--
ALTER TABLE `user_fullname`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `acc_locking`
--
ALTER TABLE `acc_locking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `admission_controller`
--
ALTER TABLE `admission_controller`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `admission_cycles`
--
ALTER TABLE `admission_cycles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `announcement`
--
ALTER TABLE `announcement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcement_tag`
--
ALTER TABLE `announcement_tag`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `api_list`
--
ALTER TABLE `api_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `applicant_number_prefix`
--
ALTER TABLE `applicant_number_prefix`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `applicant_types`
--
ALTER TABLE `applicant_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `application_permit`
--
ALTER TABLE `application_permit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `contact_support`
--
ALTER TABLE `contact_support`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `downloadable_forms`
--
ALTER TABLE `downloadable_forms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_template`
--
ALTER TABLE `email_template`
  MODIFY `id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `expiration_config`
--
ALTER TABLE `expiration_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `form_fields`
--
ALTER TABLE `form_fields`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- AUTO_INCREMENT for table `form_field_options`
--
ALTER TABLE `form_field_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=181;

--
-- AUTO_INCREMENT for table `form_steps`
--
ALTER TABLE `form_steps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `general_uploads`
--
ALTER TABLE `general_uploads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `msg_config`
--
ALTER TABLE `msg_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `otp_user`
--
ALTER TABLE `otp_user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `remark_templates`
--
ALTER TABLE `remark_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `services_answers`
--
ALTER TABLE `services_answers`
  MODIFY `answer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `services_email_otp_codes`
--
ALTER TABLE `services_email_otp_codes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `services_fields`
--
ALTER TABLE `services_fields`
  MODIFY `field_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services_field_options`
--
ALTER TABLE `services_field_options`
  MODIFY `option_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `services_list`
--
ALTER TABLE `services_list`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `services_requests`
--
ALTER TABLE `services_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `services_request_statuses`
--
ALTER TABLE `services_request_statuses`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `services_users`
--
ALTER TABLE `services_users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `smtp_config`
--
ALTER TABLE `smtp_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sso_officers`
--
ALTER TABLE `sso_officers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sso_user`
--
ALTER TABLE `sso_user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `statuses`
--
ALTER TABLE `statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `submissions`
--
ALTER TABLE `submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `submission_data`
--
ALTER TABLE `submission_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `submission_files`
--
ALTER TABLE `submission_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `tokenization`
--
ALTER TABLE `tokenization`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `user_fullname`
--
ALTER TABLE `user_fullname`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `acc_locking`
--
ALTER TABLE `acc_locking`
  ADD CONSTRAINT `acc_locking_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `admission_controller`
--
ALTER TABLE `admission_controller`
  ADD CONSTRAINT `admission_controller_fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `applicant_types`
--
ALTER TABLE `applicant_types`
  ADD CONSTRAINT `applicant_types_ibfk_1` FOREIGN KEY (`admission_cycle_id`) REFERENCES `admission_cycles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `application_permit`
--
ALTER TABLE `application_permit`
  ADD CONSTRAINT `fk_application_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `ExamRegistrations`
--
ALTER TABLE `ExamRegistrations`
  ADD CONSTRAINT `ExamRegistrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `ExamRegistrations_ibfk_2` FOREIGN KEY (`schedule_id`) REFERENCES `ExamSchedules` (`schedule_id`);

--
-- Constraints for table `form_fields`
--
ALTER TABLE `form_fields`
  ADD CONSTRAINT `form_fields_ibfk_1` FOREIGN KEY (`step_id`) REFERENCES `form_steps` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `form_field_options`
--
ALTER TABLE `form_field_options`
  ADD CONSTRAINT `form_field_options_ibfk_1` FOREIGN KEY (`field_id`) REFERENCES `form_fields` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `form_steps`
--
ALTER TABLE `form_steps`
  ADD CONSTRAINT `form_steps_ibfk_1` FOREIGN KEY (`applicant_type_id`) REFERENCES `applicant_types` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `otp_user`
--
ALTER TABLE `otp_user`
  ADD CONSTRAINT `otp_user_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `services_answers`
--
ALTER TABLE `services_answers`
  ADD CONSTRAINT `services_answers_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `services_requests` (`request_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `services_answers_ibfk_2` FOREIGN KEY (`field_id`) REFERENCES `services_fields` (`field_id`) ON DELETE CASCADE;

--
-- Constraints for table `services_email_otp_codes`
--
ALTER TABLE `services_email_otp_codes`
  ADD CONSTRAINT `fk_services_email_otp_user` FOREIGN KEY (`user_id`) REFERENCES `services_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `services_fields`
--
ALTER TABLE `services_fields`
  ADD CONSTRAINT `fk_visible_option` FOREIGN KEY (`visible_when_option_id`) REFERENCES `services_field_options` (`option_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `services_fields_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services_list` (`service_id`) ON DELETE CASCADE;

--
-- Constraints for table `services_field_options`
--
ALTER TABLE `services_field_options`
  ADD CONSTRAINT `services_field_options_ibfk_1` FOREIGN KEY (`field_id`) REFERENCES `services_fields` (`field_id`) ON DELETE CASCADE;

--
-- Constraints for table `services_requests`
--
ALTER TABLE `services_requests`
  ADD CONSTRAINT `services_requests_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services_list` (`service_id`),
  ADD CONSTRAINT `services_requests_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `services_users` (`id`),
  ADD CONSTRAINT `services_requests_ibfk_3` FOREIGN KEY (`status_id`) REFERENCES `services_request_statuses` (`status_id`);

--
-- Constraints for table `submissions`
--
ALTER TABLE `submissions`
  ADD CONSTRAINT `submissions_fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `submissions_ibfk_2` FOREIGN KEY (`applicant_type_id`) REFERENCES `applicant_types` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `submission_data`
--
ALTER TABLE `submission_data`
  ADD CONSTRAINT `submission_data_ibfk_1` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `submission_files`
--
ALTER TABLE `submission_files`
  ADD CONSTRAINT `submission_files_ibfk_1` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tokenization`
--
ALTER TABLE `tokenization`
  ADD CONSTRAINT `tokenization_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;