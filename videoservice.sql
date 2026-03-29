-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1
-- Время создания: Мар 29 2026 г., 16:09
-- Версия сервера: 10.4.32-MariaDB
-- Версия PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `videoservice`
--

-- --------------------------------------------------------

--
-- Структура таблицы `auth_tokens`
--

CREATE TABLE `auth_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `selector` varchar(255) NOT NULL,
  `hashed_validator` varchar(255) NOT NULL,
  `expires` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `categories`
--

CREATE TABLE `categories` (
  `category_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `categories`
--

INSERT INTO `categories` (`category_id`, `name`) VALUES
(2, 'Видеоигры'),
(6, 'Курсы'),
(7, 'Мода и красота'),
(1, 'Музыка'),
(4, 'Новости'),
(8, 'Общее'),
(5, 'Спорт'),
(3, 'Фильмы и сериалы');

-- --------------------------------------------------------

--
-- Структура таблицы `comments`
--

CREATE TABLE `comments` (
  `comment_id` int(10) UNSIGNED NOT NULL,
  `video_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `parent_comment_id` int(10) UNSIGNED DEFAULT NULL,
  `text` text NOT NULL,
  `like_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `dislike_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `comments`
--

INSERT INTO `comments` (`comment_id`, `video_id`, `user_id`, `parent_comment_id`, `text`, `like_count`, `dislike_count`, `is_pinned`, `created_at`) VALUES
(6, 28, 32, NULL, 'опа', 0, 0, 0, '2025-11-06 20:43:22'),
(7, 28, 32, NULL, 'мгриолтдл', 0, 0, 0, '2025-11-06 21:15:25'),
(8, 36, 32, NULL, '<scritp> alert 0 </script>', 0, 0, 0, '2025-11-15 15:55:03'),
(9, 32, 35, NULL, 'мммммт', 0, 0, 0, '2025-11-29 19:57:36'),
(10, 38, 35, NULL, 'счмчс', 0, 0, 0, '2025-11-29 20:59:24'),
(11, 30, 35, NULL, 'мсисмисми', 0, 0, 0, '2025-11-29 21:30:01'),
(12, 41, 35, NULL, '123', 1, 0, 0, '2025-11-29 21:31:59'),
(13, 36, 32, NULL, 'hjghjgjhgjhg', 1, 0, 0, '2025-12-26 12:02:31'),
(16, 33, 32, NULL, 'Привет', 0, 0, 0, '2026-01-08 21:31:46'),
(17, 36, 32, 13, 'ура!!', 0, 1, 0, '2026-01-08 21:32:50'),
(18, 32, 32, NULL, 'привет', 1, 0, 0, '2026-01-08 21:39:27'),
(19, 32, 32, NULL, 'ура', 1, 0, 0, '2026-01-08 21:40:09');

-- --------------------------------------------------------

--
-- Структура таблицы `comment_likes`
--

CREATE TABLE `comment_likes` (
  `id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `comment_id` int(10) UNSIGNED NOT NULL,
  `type` tinyint(1) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Дамп данных таблицы `comment_likes`
--

INSERT INTO `comment_likes` (`id`, `user_id`, `comment_id`, `type`, `created_at`) VALUES
(1, 32, 13, 1, '2026-01-09 00:32:39'),
(2, 32, 17, -1, '2026-01-09 00:32:52'),
(4, 32, 18, 1, '2026-01-09 00:39:46'),
(5, 32, 19, 1, '2026-01-09 00:40:11'),
(6, 32, 12, 1, '2026-03-29 10:15:41');

-- --------------------------------------------------------

--
-- Структура таблицы `hidden_videos`
--

CREATE TABLE `hidden_videos` (
  `hidden_video_id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `video_id` int(10) UNSIGNED NOT NULL,
  `hidden_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `history`
--

CREATE TABLE `history` (
  `history_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `video_id` int(10) UNSIGNED NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `history`
--

INSERT INTO `history` (`history_id`, `user_id`, `video_id`, `viewed_at`) VALUES
(413, 34, 32, '2025-11-04 17:55:04'),
(718, 34, 41, '2025-11-23 18:10:03'),
(854, 44, 41, '2026-01-07 16:01:59'),
(857, 47, 49, '2026-01-07 17:26:25'),
(918, 39, 34, '2026-01-09 15:25:12'),
(931, 32, 46, '2026-03-29 13:42:47'),
(932, 32, 41, '2026-03-29 13:42:50'),
(933, 32, 50, '2026-03-29 13:42:53'),
(934, 32, 35, '2026-03-29 13:42:55'),
(935, 32, 36, '2026-03-29 13:42:58'),
(936, 32, 31, '2026-03-29 13:43:04');

-- --------------------------------------------------------

--
-- Структура таблицы `likes`
--

CREATE TABLE `likes` (
  `like_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `video_id` int(10) UNSIGNED NOT NULL,
  `like_type` tinyint(4) NOT NULL COMMENT '1 = like, -1 = dislike',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `likes`
--

INSERT INTO `likes` (`like_id`, `user_id`, `video_id`, `like_type`, `created_at`) VALUES
(23, 31, 27, 1, '2025-10-24 16:52:40'),
(24, 31, 28, 1, '2025-10-24 16:54:28'),
(26, 33, 29, 1, '2025-10-24 16:57:44'),
(35, 33, 31, 1, '2025-11-01 17:31:52'),
(37, 31, 32, 1, '2025-11-02 20:19:31'),
(47, 32, 30, 1, '2025-11-14 19:14:56'),
(49, 32, 35, 1, '2025-11-14 19:24:13'),
(50, 32, 33, 1, '2025-11-14 19:24:14'),
(51, 32, 36, 1, '2025-11-14 19:24:15'),
(52, 32, 29, 1, '2025-11-14 19:24:17'),
(53, 32, 44, 1, '2025-11-21 09:21:30'),
(55, 35, 32, 1, '2025-11-29 20:15:17'),
(56, 35, 38, 1, '2025-11-29 20:59:21'),
(57, 35, 30, 1, '2025-11-29 21:29:58'),
(58, 35, 41, 1, '2025-11-29 21:31:56'),
(59, 32, 38, 1, '2025-11-29 21:34:41'),
(60, 32, 39, 1, '2025-11-29 22:18:19'),
(68, 32, 31, -1, '2026-01-08 20:08:56'),
(69, 32, 50, 1, '2026-01-08 20:12:06'),
(73, 35, 46, 1, '2026-01-09 15:26:17'),
(74, 32, 41, 1, '2026-03-29 07:15:49');

-- --------------------------------------------------------

--
-- Структура таблицы `linked_accounts`
--

CREATE TABLE `linked_accounts` (
  `link_id` int(11) NOT NULL,
  `link_group_id` varchar(255) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `linked_accounts`
--

INSERT INTO `linked_accounts` (`link_id`, `link_group_id`, `user_id`) VALUES
(42, 'link_69060b5f084fe2.37913981', 31),
(44, 'link_69060b5f084fe2.37913981', 34);

-- --------------------------------------------------------

--
-- Структура таблицы `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `actor_user_id` int(10) UNSIGNED DEFAULT NULL,
  `video_id` int(10) UNSIGNED DEFAULT NULL,
  `target_id` int(10) UNSIGNED DEFAULT NULL,
  `type` enum('subscription','comment','reply','like','system','review_reply','report_update') NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `actor_user_id`, `video_id`, `target_id`, `type`, `is_read`, `created_at`) VALUES
(1, 32, 35, NULL, 38, 'like', 1, '2025-11-29 20:59:21'),
(2, 32, 35, NULL, 38, 'comment', 0, '2025-11-29 20:59:24'),
(3, 32, 35, NULL, 30, 'like', 1, '2025-11-29 21:29:58'),
(4, 32, 35, NULL, 11, 'comment', 1, '2025-11-29 21:30:01'),
(5, 32, 35, NULL, NULL, 'subscription', 1, '2025-11-29 21:39:28'),
(6, 33, 32, NULL, 32, 'like', 0, '2025-11-29 22:26:01'),
(7, 31, 35, NULL, 33, 'like', 0, '2025-12-01 14:13:31'),
(8, 31, 35, NULL, 29, 'like', 0, '2025-12-02 13:55:59'),
(9, 31, 35, NULL, 33, 'like', 0, '2025-12-15 15:41:49'),
(10, 34, 32, NULL, 13, 'comment', 0, '2025-12-26 12:02:31'),
(11, 34, 32, NULL, 36, 'like', 0, '2025-12-26 12:02:33'),
(14, 33, 32, NULL, 31, 'like', 0, '2026-01-08 20:08:56'),
(15, 31, 32, NULL, 16, 'comment', 0, '2026-01-08 21:31:46'),
(16, 34, 32, NULL, 36, 'like', 0, '2026-01-08 21:32:42'),
(17, 33, 32, NULL, 32, 'like', 0, '2026-01-08 21:38:39'),
(18, 33, 32, NULL, 32, 'like', 0, '2026-01-08 21:39:18'),
(19, 33, 32, NULL, 32, 'like', 0, '2026-01-08 21:39:21'),
(20, 33, 32, NULL, 18, 'comment', 0, '2026-01-08 21:39:27'),
(21, 33, 32, NULL, 32, 'like', 0, '2026-01-08 21:40:03'),
(22, 33, 32, NULL, 19, 'comment', 0, '2026-01-08 21:40:09'),
(23, 32, 35, NULL, 46, 'like', 1, '2026-01-09 15:26:17');

-- --------------------------------------------------------

--
-- Структура таблицы `not_interested_videos`
--

CREATE TABLE `not_interested_videos` (
  `interest_id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `video_id` int(10) UNSIGNED NOT NULL,
  `marked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `not_interested_videos`
--

INSERT INTO `not_interested_videos` (`interest_id`, `user_id`, `video_id`, `marked_at`) VALUES
(1, 33, 32, '2025-11-03 20:11:51'),
(2, 33, 27, '2025-11-03 20:28:08'),
(3, 33, 30, '2025-11-03 21:11:19'),
(4, 34, 31, '2025-11-04 11:39:32'),
(5, 32, 29, '2025-11-06 19:28:18'),
(6, 32, 34, '2025-12-26 12:03:16'),
(7, 32, 32, '2026-03-29 07:27:37');

-- --------------------------------------------------------

--
-- Структура таблицы `playlists`
--

CREATE TABLE `playlists` (
  `playlist_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `visibility` enum('public','unlisted','private') NOT NULL DEFAULT 'public',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `playlists`
--

INSERT INTO `playlists` (`playlist_id`, `user_id`, `title`, `description`, `visibility`, `created_at`, `updated_at`) VALUES
(18, 33, 'Новый', '', 'private', '2025-10-24 16:59:05', '2026-02-06 20:25:52'),
(21, 33, 'Открытость', 'Пам парам парам', 'public', '2025-11-02 20:15:14', '2026-02-06 20:25:52'),
(23, 34, 'рома лох', '', 'private', '2025-11-04 15:58:18', '2026-02-06 20:25:52'),
(24, 32, 'мурка', '', 'private', '2025-11-05 10:40:19', '2026-02-06 20:25:52'),
(28, 32, 'аэробика', 'прикинь да', 'public', '2025-11-07 13:50:25', '2026-02-06 20:25:52'),
(30, 31, 'апвпвап', '', 'private', '2025-11-22 11:32:48', '2026-02-06 20:25:52'),
(31, 31, '1233', '', 'public', '2025-11-22 11:39:35', '2026-02-06 20:25:52'),
(37, 32, '1', '', 'private', '2025-11-23 18:30:41', '2026-02-06 20:25:52');

-- --------------------------------------------------------

--
-- Структура таблицы `playlist_videos`
--

CREATE TABLE `playlist_videos` (
  `playlist_video_id` int(10) UNSIGNED NOT NULL,
  `playlist_id` int(10) UNSIGNED NOT NULL,
  `video_id` int(10) UNSIGNED NOT NULL,
  `video_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `playlist_videos`
--

INSERT INTO `playlist_videos` (`playlist_video_id`, `playlist_id`, `video_id`, `video_order`) VALUES
(13, 18, 27, 0),
(14, 18, 28, 0),
(15, 18, 29, 0),
(18, 18, 30, 0),
(20, 18, 31, 0),
(21, 21, 31, 0),
(22, 18, 32, 0),
(24, 23, 31, 0),
(25, 24, 35, 0),
(33, 28, 36, 0),
(35, 24, 30, 0),
(41, 24, 38, 0),
(42, 24, 33, 0),
(45, 24, 28, 0),
(47, 28, 41, 0),
(48, 28, 44, 0),
(50, 24, 44, 0),
(54, 24, 41, 0),
(65, 28, 33, 0),
(66, 24, 29, 0),
(67, 37, 33, 0),
(68, 37, 29, 0),
(69, 37, 36, 0),
(70, 37, 34, 0),
(71, 37, 41, 0),
(72, 37, 31, 0),
(73, 37, 47, 0),
(74, 28, 50, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `recommendation_sections`
--

CREATE TABLE `recommendation_sections` (
  `section_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(100) NOT NULL DEFAULT 'Рекомендуемые каналы',
  `display_order` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `recommendation_sections`
--

INSERT INTO `recommendation_sections` (`section_id`, `user_id`, `title`, `display_order`) VALUES
(10, 33, 'Пельмени', 0),
(16, 32, 'Пельмени', 0);

-- --------------------------------------------------------

--
-- Структура таблицы `recommended_channels`
--

CREATE TABLE `recommended_channels` (
  `recommendation_id` int(10) UNSIGNED NOT NULL,
  `section_id` int(10) UNSIGNED NOT NULL,
  `recommended_channel_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `recommended_channels`
--

INSERT INTO `recommended_channels` (`recommendation_id`, `section_id`, `recommended_channel_id`) VALUES
(19, 10, 31),
(20, 10, 32);

-- --------------------------------------------------------

--
-- Структура таблицы `reports`
--

CREATE TABLE `reports` (
  `report_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `target_type` enum('video','comment') NOT NULL,
  `target_id` int(10) UNSIGNED NOT NULL,
  `reason` text NOT NULL,
  `status` enum('open','resolved','dismissed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `reports`
--

INSERT INTO `reports` (`report_id`, `user_id`, `target_type`, `target_id`, `reason`, `status`, `created_at`) VALUES
(7, 32, 'video', 38, 'Спам', 'open', '2025-11-28 10:35:10'),
(8, 32, 'video', 28, 'Вредные или опасные действия', 'resolved', '2025-12-01 10:07:15'),
(9, 35, 'video', 27, 'Жестокое обращение с детьми', 'open', '2025-12-03 09:16:23'),
(10, 32, 'video', 46, 'Спам', 'open', '2025-12-26 12:06:46'),
(13, 32, 'video', 46, 'Вредные или опасные действия', 'open', '2026-01-08 20:05:14'),
(14, 35, 'video', 49, 'Содержание сексуального характера', 'open', '2026-01-09 15:23:54'),
(15, 35, 'video', 33, 'Жестокое или отталкивающее содержание', 'open', '2026-01-09 15:24:08'),
(16, 39, 'video', 34, 'Дискриминационные высказывания и оскорбления', 'open', '2026-01-09 15:25:29'),
(17, 32, 'video', 31, 'Вредные или опасные действия', 'open', '2026-03-29 07:07:06'),
(18, 35, 'video', 36, 'Вредные или опасные действия', 'resolved', '2026-03-29 14:02:08');

-- --------------------------------------------------------

--
-- Структура таблицы `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL COMMENT 'Оценка сервиса, например, от 1 до 5',
  `subject` varchar(255) DEFAULT NULL COMMENT 'Тема или заголовок отзыва',
  `content` text NOT NULL COMMENT 'Содержание отзыва',
  `admin_response` text DEFAULT NULL,
  `allow_publication` tinyint(1) NOT NULL DEFAULT 1,
  `helpful_count` int(11) NOT NULL DEFAULT 0,
  `unhelpful_count` int(11) NOT NULL DEFAULT 0,
  `status` enum('в обработке','рассмотрен','отклонен') NOT NULL DEFAULT 'в обработке',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Таблица для отзывов и обратной связи о сервисе';

--
-- Дамп данных таблицы `reviews`
--

INSERT INTO `reviews` (`review_id`, `user_id`, `rating`, `subject`, `content`, `admin_response`, `allow_publication`, `helpful_count`, `unhelpful_count`, `status`, `created_at`) VALUES
(33, 32, 4, 'Работа видео', 'вообще супер', NULL, 1, 0, 0, 'в обработке', '2025-11-06 09:56:35'),
(34, 35, 4, 'Работа видео', 'впааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааавпааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааавпааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааавпааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааавпааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааавпааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааавпааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааавпааааааааааааааааааааааааааааааааааааааааааа', NULL, 1, 0, 0, 'в обработке', '2025-11-29 22:33:24'),
(35, 35, 4, '1', '1', NULL, 0, 0, 0, 'в обработке', '2025-11-30 20:38:25'),
(36, 35, 3, '2', '2', NULL, 1, 0, 0, '', '2025-11-30 20:55:19'),
(37, 35, 3, 'Работа видео', 'ап', NULL, 1, 0, 0, '', '2025-12-01 12:36:44'),
(39, 44, 4, 'Разработка', 'Поставил бы 5, но сегодня не вайб', NULL, 1, 0, 0, 'в обработке', '2026-01-07 16:04:21'),
(40, 32, 4, 'САМЫЙ ЛУЧШИЙ ОНЛАЙН СЕРВИС', 'класс', NULL, 0, 0, 0, 'в обработке', '2026-01-08 19:52:26');

-- --------------------------------------------------------

--
-- Структура таблицы `review_reactions`
--

CREATE TABLE `review_reactions` (
  `reaction_id` int(11) NOT NULL,
  `review_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `reaction_type` enum('helpful','unhelpful') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `search_history`
--

CREATE TABLE `search_history` (
  `id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `query` varchar(255) NOT NULL,
  `searched_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `search_history`
--

INSERT INTO `search_history` (`id`, `user_id`, `query`, `searched_at`) VALUES
(1, 32, 'я', '2025-11-05 11:17:30'),
(2, 32, 'т', '2025-11-05 12:39:10'),
(3, 32, 'м', '2025-11-05 12:46:51'),
(4, 32, 'н', '2025-11-05 12:46:53'),
(5, 32, 'н', '2025-11-05 13:12:08'),
(6, 32, 'Мурка Мурка', '2025-11-05 13:46:22'),
(7, 32, 'пельмени', '2025-11-05 13:46:25'),
(8, 32, 'пельмени', '2025-11-05 13:46:31'),
(9, 32, 'пельмени', '2025-11-05 13:46:35'),
(10, 32, 'Мурка', '2025-11-05 13:50:55'),
(11, 32, 'пельмени', '2025-11-05 13:50:58'),
(12, 32, 'мурка', '2025-11-05 13:53:31'),
(13, 32, 'мурка', '2025-11-05 13:53:35'),
(14, 32, 'опа', '2025-11-05 14:20:19'),
(15, 32, 'пельмени', '2025-11-05 14:22:04'),
(16, 32, 'мурка', '2025-11-05 18:35:43'),
(17, 32, 'пельмени', '2025-11-05 18:35:47'),
(18, 32, 'апввпа', '2025-11-05 18:35:48'),
(19, 32, 'а', '2025-11-05 18:35:50'),
(20, 32, 'у', '2025-11-05 18:35:55'),
(21, 32, 'мурка', '2025-11-05 18:36:08'),
(22, 32, 'мурка', '2025-11-05 19:00:54'),
(23, 32, 'мукра', '2025-11-05 19:01:46'),
(24, 32, 'мурка', '2025-11-05 19:02:59'),
(25, 32, 'мурка', '2025-11-05 19:38:41'),
(26, 32, 'мурка', '2025-11-05 19:40:46'),
(27, 32, 'мурка', '2025-11-05 19:41:32'),
(28, 32, 'мурка', '2025-11-05 19:58:04'),
(29, 32, 'мурка', '2025-11-06 07:19:10'),
(30, 32, 'мурка', '2025-11-06 07:30:05'),
(31, 32, 'мурчик', '2025-11-07 11:14:03'),
(32, 32, 'чмо ебаное чмо ебаное', '2025-11-21 09:27:02'),
(33, 32, 'Мурка', '2025-11-21 09:27:18'),
(34, 32, 'ман', '2025-11-21 09:29:40'),
(35, 32, 'мурка', '2025-11-22 11:15:08'),
(36, 32, 'мурка', '2025-11-22 11:24:07'),
(37, 32, 'мурка', '2025-11-22 11:24:38'),
(38, 32, 'мурка', '2025-11-22 11:27:34'),
(39, 32, 'рпрп', '2025-11-23 18:01:25'),
(40, 32, 'прпр', '2025-11-23 18:20:40'),
(41, 35, 'быстро', '2025-11-27 21:52:27'),
(42, 32, 'мурка', '2025-12-16 12:27:44'),
(43, 44, 'топовый', '2026-01-07 16:03:02'),
(44, 35, 'Мурка', '2026-02-06 20:28:23'),
(45, 32, 'мяу', '2026-03-29 07:27:43'),
(46, 32, 'писька писька', '2026-03-29 07:27:47');

-- --------------------------------------------------------

--
-- Структура таблицы `subscriptions`
--

CREATE TABLE `subscriptions` (
  `subscription_id` int(10) UNSIGNED NOT NULL,
  `subscriber_id` int(10) UNSIGNED NOT NULL,
  `channel_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `subscriptions`
--

INSERT INTO `subscriptions` (`subscription_id`, `subscriber_id`, `channel_id`, `created_at`) VALUES
(20, 33, 32, '2025-11-02 19:34:34'),
(62, 34, 32, '2025-11-23 18:09:57'),
(64, 35, 33, '2025-11-29 19:57:27'),
(68, 35, 32, '2025-11-29 21:39:28'),
(69, 32, 31, '2025-12-26 12:02:51');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `public_user_id` varchar(20) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `channel_name` varchar(100) DEFAULT NULL,
  `channel_handle` varchar(30) DEFAULT NULL,
  `subscriber_count` int(11) NOT NULL DEFAULT 0,
  `avatar_url` varchar(255) DEFAULT 'images/default_avatar.png',
  `banner_image_url` varchar(255) DEFAULT NULL COMMENT 'URL шапки (баннера) канала',
  `about_text` text DEFAULT NULL COMMENT 'Текстовое описание канала',
  `business_email` varchar(255) DEFAULT NULL COMMENT 'Публичный email для коммерческих запросов',
  `role` enum('user','admin') DEFAULT 'user',
  `last_login` datetime DEFAULT NULL,
  `channel_name_last_changed` datetime DEFAULT NULL,
  `username_last_changed` datetime DEFAULT NULL,
  `history_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `notify_on_comment` tinyint(1) NOT NULL DEFAULT 1,
  `notify_on_new_subscriber` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','banned','deleted') NOT NULL DEFAULT 'active' COMMENT 'Статус аккаунта',
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notify_on_like` tinyint(1) DEFAULT 1,
  `notify_on_reply` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`user_id`, `public_user_id`, `username`, `email`, `password_hash`, `channel_name`, `channel_handle`, `subscriber_count`, `avatar_url`, `banner_image_url`, `about_text`, `business_email`, `role`, `last_login`, `channel_name_last_changed`, `username_last_changed`, `history_enabled`, `notify_on_comment`, `notify_on_new_subscriber`, `status`, `registration_date`, `notify_on_like`, `notify_on_reply`) VALUES
(31, 'N4XxL0HphD0', 'kirya', 'kirya@gmail.com', '$2y$10$iYKqBSCIV/28ujrtligjZ.n/G5XQz1ZqxRmdHSkjZ54Ma13mDbgWW', 'Ромчик', NULL, 1, 'uploads/avatars/avatar_31_1761324704.jpg', NULL, NULL, NULL, 'user', '2025-11-23 21:02:58', NULL, NULL, 1, 1, 0, 'active', '2025-10-24 16:50:35', 1, 1),
(32, 'nBCOLKF2yUE', 'opex', 'orex@gmail.com', '$2y$10$v8FPmBjSyWGtb6sYetmlcOPlcq1t8eX786A.Eu6XQ26HL5nNuNAni', 'Никита', 'opex1', 3, 'uploads/avatars/user_32_691e2a20c57aa.png', 'uploads/banners/user_32_690dcf7615115.jpg', 'мурмурмурмур мур мур мур ум умуму мруру ру', 'botbetq@gmail.com', 'user', '2026-01-08 23:34:10', '2025-11-30 00:54:20', NULL, 1, 1, 1, 'active', '2025-10-24 16:54:55', 1, 1),
(33, 'LV01na_9SLc', 'Omagad123', 'Omagad@gmail.com', '$2y$10$G/WOYAw033Fua/F/OW/RnuClgrQGnTRlw0PEI6d/Td6LNYyGVvp2.', 'Рыбка', NULL, 1, 'uploads/avatars/user_33_69079b3f9d0a9.jpg', 'uploads/banners/user_33_69064462dd39b.jpg', 'апап', 'botbetq@gmail.com', 'user', '2025-11-04 21:09:16', '2025-10-27 23:32:03', '2025-10-30 21:17:38', 1, 1, 0, 'active', '2025-10-24 16:57:26', 1, 1),
(34, 'i1ybJx6DBB0', 'ilya', 'ilya@gmail.com', '$2y$10$WBUBK.njWrrEv74MdCXkoeRR4guIVuqDZ//12z6808Ij5Vvp7464.', 'Омагад', NULL, 0, 'uploads/avatars/avatar_34_1761664171.jpg', 'uploads/banners/user_34_6900e4f609fc2.jpg', '', NULL, 'user', '2025-11-23 21:09:50', NULL, NULL, 1, 1, 0, 'active', '2025-10-24 19:05:38', 1, 1),
(35, 'nIk5BYQdFW8', 'Admin', 'Administrator@gmail.com', '$2y$10$Ezc6/LuKddy.fi/CcPv83e1BrEce4slvmyyLqIy42u9HPZRplSCPu', 'Артемка', NULL, 0, 'profile/avatar_6928be1d524e1.png', NULL, '', '', 'admin', NULL, NULL, NULL, 0, 1, 0, 'active', '2025-11-27 21:09:49', 1, 1),
(36, '1-yJlJWK92D', 'Test1', 'Test1@gmail.com', '$2y$10$lXrsh5YhJ2MjD/GbdAxsi.BGjfYJWloH5BRWaeIQixJUMMZW//zjm', NULL, NULL, 0, 'profile/avatar_692d9e83e8de9.png', NULL, NULL, NULL, 'user', NULL, NULL, NULL, 1, 1, 0, 'active', '2025-12-01 13:56:19', 1, 1),
(38, 'h744qHaSupV', 'romka', 'rjejjd@gmai.com', '$2y$10$BhRiKCEEBhJV4jVxpQUFeeSgN/DijSr2PTs.T6d1R4mljnGGp2/ea', NULL, NULL, 0, 'profile/avatar_695e725463349.png', NULL, NULL, NULL, 'user', NULL, NULL, NULL, 1, 1, 0, 'active', '2026-01-07 14:48:52', 1, 1),
(39, '0mM9vctvCr9', 'krufkrug', 'krug@gmail.com', '$2y$10$iKmkSYqZA36hSo6zw1MXvu16eeCobjVNbbFd2zf9zzyzyra9W2Exe', 'Ацвй', NULL, 1, 'uploads/avatars/user_39_695e798c4bf30.jpg', 'uploads/banners/user_39_695e798c4c24d.jpg', '', '', 'user', NULL, NULL, NULL, 1, 1, 0, 'active', '2026-01-07 14:51:01', 1, 1),
(40, 'atCERf39371', 'romkA11', 'eheuhehe@gmail.com', '$2y$10$Ef397LYw3P.pntfNUDVPRueRmJQj/nVTM0nQUrVxfMId4lu2mJtTy', 'Я и лера', NULL, 0, 'profile/avatar_695e738661d2a.png', NULL, NULL, NULL, 'user', NULL, NULL, NULL, 1, 1, 0, 'active', '2026-01-07 14:53:58', 1, 1),
(43, 'uKzI0FYXcjJ', 'b0roDaa', 'koloboksir@gmail.com', '$2y$10$FcrR8B5GWikKv/0beBuvkeKV5.lqv1orGzHo9spGYVSU1GWXp/tSq', NULL, NULL, 0, 'profile/avatar_695e7b03ec595.png', NULL, NULL, NULL, 'user', NULL, NULL, NULL, 1, 1, 0, 'active', '2026-01-07 15:25:55', 1, 1),
(44, '87kA_HL5vk1', 'SHOBLA', 'vgfbg@gmail.com', '$2y$10$Ka2aSwGWGuPe4ZSnW9WoZO0LpjxRXoqfsPYFJtx20WEmhiPouDC/a', NULL, NULL, 0, 'profile/avatar_695e83655761d.png', NULL, NULL, NULL, 'user', NULL, NULL, NULL, 1, 1, 0, 'active', '2026-01-07 16:01:41', 1, 1),
(45, 'ffopFYlEsPS', 'krutoy812', 'aaa1@gmail.com', '$2y$10$x0umNomGmTcbUAkDVbIXje1VETTpbcYqxCFbNnqhLsmNHcBNK6YrG', NULL, NULL, 0, 'profile/avatar_695e96da9b523.png', NULL, NULL, NULL, 'user', NULL, NULL, NULL, 1, 1, 0, 'active', '2026-01-07 17:24:42', 1, 1),
(46, '0kILMefw6x-', 'krutoy813', 'aaa2@gmail.com', '$2y$10$i1BVXj1sNYAK5a6O.1nHzuaxjcE9i27NPouyivTcVualmhOxwiH2u', NULL, NULL, 0, 'profile/avatar_695e96e311144.png', NULL, NULL, NULL, 'user', NULL, NULL, NULL, 1, 1, 0, 'active', '2026-01-07 17:24:51', 1, 1),
(47, '8rR8c3ZrdTp', 'krutoy', 'fdgdgb@gmail.com', '$2y$10$aQGhcqLiu.RL1cNlj5dzROioIMqCoIgSc6nPI4jsNuHmjxKCvOc9W', 'Никита12', NULL, 0, 'uploads/avatars/avatar_47_1767806751.png', NULL, NULL, NULL, 'user', NULL, NULL, NULL, 1, 1, 0, 'active', '2026-01-07 17:25:06', 1, 1);

-- --------------------------------------------------------

--
-- Структура таблицы `user_links`
--

CREATE TABLE `user_links` (
  `link_id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `link_type` varchar(50) NOT NULL DEFAULT 'website' COMMENT 'Тип ссылки для иконки',
  `link_title` varchar(100) DEFAULT NULL COMMENT 'Необязательный заголовок',
  `link_url` varchar(512) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `user_links`
--

INSERT INTO `user_links` (`link_id`, `user_id`, `link_type`, `link_title`, `link_url`) VALUES
(6, 33, 'website', 'тг', 't.me/kvlnkln'),
(17, 32, 'website', 'вк', 'https://vk.com/ivlevchef'),
(18, 32, 'website', 'мемчик', 'https://www.youtube.com/watch?v=x-DeipNSYYs');

-- --------------------------------------------------------

--
-- Структура таблицы `videos`
--

CREATE TABLE `videos` (
  `video_id` int(10) UNSIGNED NOT NULL,
  `public_video_id` varchar(20) DEFAULT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `thumbnail_url` varchar(255) DEFAULT 'images/default_thumb.png',
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `visibility` enum('public','unlisted','private') NOT NULL DEFAULT 'public' COMMENT 'Кто может видеть видео',
  `status` enum('processing','published','failed','deleted') NOT NULL DEFAULT 'processing' COMMENT 'Статус обработки и публикации',
  `deleted_at` datetime DEFAULT NULL,
  `allow_comments` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Разрешены ли комментарии',
  `duration_seconds` int(10) UNSIGNED DEFAULT NULL COMMENT 'Длительность видео в секундах',
  `views_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `videos`
--

INSERT INTO `videos` (`video_id`, `public_video_id`, `user_id`, `title`, `description`, `file_path`, `thumbnail_url`, `upload_date`, `visibility`, `status`, `deleted_at`, `allow_comments`, `duration_seconds`, `views_count`) VALUES
(27, '_cAA2eexw-A', 31, 'Самое первое видео', 'Это самое первое видео на сайте', 'uploads/videos/f9b5860bd992a5885281d4113f1fb09a.mp4', 'uploads/thumbnails/bc632bea46519658d7bded222daa9d11.jpg', '2025-10-24 16:52:35', 'public', '', NULL, 1, 16, 40),
(28, '-eVa6SS4NB4', 31, 'Самое второе видео', 'Вот так вот', 'uploads/videos/0f6dcb057322eb9cf9f698750f80dbfc.mp4', 'uploads/thumbnails/default.png', '2025-10-24 16:52:59', 'private', 'deleted', NULL, 1, 18, 97),
(29, '-o3EdnGZA1g', 31, 'Привет', '', 'uploads/videos/e0836cd4a3711baecb6362f8e4a95a8d.mp4', 'uploads/thumbnails/e456bc77d49f863487edd9dd79460ea9.jpg', '2025-10-24 16:54:19', 'public', 'published', NULL, 1, 22, 99),
(30, 'ZrP8P9RwMMY', 32, 'Я', '', 'uploads/videos/ef5abb8493c64b64e973bd04646945b6.mp4', 'uploads/thumbnails/default.png', '2025-10-24 16:55:27', 'private', 'deleted', '2026-01-08 22:45:20', 1, 59, 93),
(31, 'BMaU1ovDC_Y', 33, 'Мурка', 'Вот мяу', 'uploads/videos/2bec33034713399b4106963295238bcb.mp4', 'uploads/thumbnails/18fc8be0c517ffdbb46499b3fd775a69.jpg', '2025-11-01 17:31:48', 'public', 'published', NULL, 0, 22, 68),
(32, '8S5HEO2jjMU', 33, 'прям щя прям тут', '', 'uploads/videos/d138f8e526699008452a91f319e8f486.mov', 'uploads/thumbnails/f4c27a92c0d6a348fd2c3aa8ab7d231d.jpg', '2025-11-02 20:16:52', 'public', 'published', NULL, 1, 3, 61),
(33, 'JMrOIhHoc2A', 31, 'Мурка', 'мур мур', 'uploads/videos/5f5af890bcb8324e753d042628dd3d90.mov', 'uploads/thumbnails/a67ce2abf544d565b72364302230e555.jpg', '2025-11-04 15:20:42', 'private', 'deleted', NULL, 1, 8, 33),
(34, '7Idof8jKISY', 34, 'я русский', '', 'uploads/videos/d38c7df97c398297e5a738d33e728a2b.mp4', 'uploads/thumbnails/default.png', '2025-11-04 15:39:51', 'private', 'deleted', NULL, 1, 28, 34),
(35, 'GjquYvCtDYk', 34, 'саня с др', '', 'uploads/videos/a125b850c1b0b41fe7d89f989f785465.mp4', 'uploads/thumbnails/default.png', '2025-11-04 15:40:05', 'public', 'published', NULL, 1, 11, 79),
(36, 'j0RCoFfy6Gk', 34, 'умеете', '', 'uploads/videos/834c488fdaa0931e8e249e257dd389ae.mov', 'uploads/thumbnails/ac02eab4e4f14660d92d07516c879e7b.jpg', '2025-11-04 15:40:54', 'private', 'deleted', NULL, 1, 7, 60),
(38, 'Slnx-9Wy2qW', 32, 'мурчик', '', 'uploads/videos/59e1208f44d02085f3a8ce6ce6e6532b.mp4', 'uploads/thumbnails/9d0dfef2b69e7adf12b833c84bca8c2e.gif', '2025-11-07 09:48:17', 'public', '', NULL, 1, 16, 43),
(39, '6C04IWuDCvP', 32, 'Ячмень', '', 'uploads/videos/9fad9a07aaa653b8e73102e520607c7e.mov', 'uploads/thumbnails/10d6b71e3045b4177167e30246b44e68.jpg', '2025-11-21 07:54:52', 'private', 'deleted', '2025-12-26 15:05:03', 1, 11, 6),
(40, 'kE-34DRPLNt', 32, 'мангал', '', 'uploads/videos/e068cae3fe853d2c64c7462bbb9a88aa.mov', 'uploads/thumbnails/38f6d01e12a84081cf39a6f6af87a7c9.png', '2025-11-21 07:56:35', 'private', 'deleted', NULL, 1, 10, 3),
(41, 'q0ULvmkUdLu', 32, 'рома мангал', '', 'uploads/videos/917301f6e784a719bea3d91ebd45c877.mov', 'uploads/thumbnails/468b7d4e90718b14462ee086c6c8132b.jpg', '2025-11-21 07:57:33', 'public', 'published', NULL, 1, 10, 34),
(42, 'wNKqUkf92ck', 32, 'в мае', '', 'uploads/videos/7be777dd66b7c0a1f296286d3bfefa4c.mp4', 'uploads/thumbnails/9d274b513e2e552842f992da050650be.jpg', '2025-11-21 07:58:23', 'private', 'deleted', '2025-11-23 21:01:54', 1, 11, 3),
(43, '0EkJsBXXdGI', 32, 'рпрп', '', 'uploads/videos/3218e998757f1bedb3898b5f93cc1ef3.mp4', 'uploads/thumbnails/4ff5cbe27e057dcc62b1288d4e11b2f4.jpg', '2025-11-21 08:45:50', 'private', 'deleted', '2025-11-23 21:01:18', 1, 4, 1),
(44, 'aA2qyhe3T_a', 32, 'ваапрпрпа', 'парпапар', 'uploads/videos/9880a8cb86c7624b08402427a2cbf016.mov', 'uploads/thumbnails/192fbde1168208827bd22685aa6e5709.png', '2025-11-21 09:21:27', 'private', 'deleted', NULL, 0, 8, 3),
(46, 'M8GlrRZCTYV', 32, 'апр', '', 'uploads/videos/f4617b9de3e9e6f3609cb618dbd1535f.mp4', 'uploads/thumbnails/ef506c80b2a5e1fd3f95538b6336dcf8.png', '2025-12-03 09:16:00', 'public', 'published', NULL, 1, 1, 35),
(47, 'sEM1hhQjPW7', 32, 'орех', '', 'uploads/videos/d610a006bad2161ae562602ca812dc8d.mp4', 'uploads/thumbnails/bb323f34b69dcb667069e1bf1e4853c0.png', '2025-12-03 09:39:20', 'private', 'deleted', '2025-12-03 12:40:36', 0, 4, 4),
(49, '3N-QAg8oJga', 47, 'пельмени', 'смииссмим', 'uploads/videos/bcf8bace02ef9ccd1955b7352f910c63.mp4', 'uploads/thumbnails/default.png', '2026-01-07 17:26:25', 'private', 'deleted', NULL, 1, 4, 12),
(50, 'Kma-fID9IUX', 32, '123', '', 'uploads/videos/f21f1e0b71fef3fe7f49b5ed8b76f3c5.mov', 'uploads/thumbnails/9740fef5c9e9a78479bff4cbf490d8ee.jpg', '2026-01-08 19:47:48', 'public', 'published', NULL, 0, 3, 18);

-- --------------------------------------------------------

--
-- Структура таблицы `video_categories`
--

CREATE TABLE `video_categories` (
  `video_category_id` int(10) UNSIGNED NOT NULL,
  `video_id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `video_categories`
--

INSERT INTO `video_categories` (`video_category_id`, `video_id`, `category_id`) VALUES
(3, 27, 8),
(4, 28, 1),
(5, 29, 8),
(6, 30, 8),
(7, 31, 5),
(8, 32, 3),
(9, 33, 8),
(10, 34, 8),
(11, 35, 8),
(12, 36, 8),
(14, 38, 8),
(15, 39, 1),
(16, 40, 8),
(17, 41, 1),
(18, 42, 1),
(19, 43, 1),
(20, 44, 8),
(22, 46, 8),
(23, 47, 6),
(25, 49, 8),
(26, 50, 3);

-- --------------------------------------------------------

--
-- Структура таблицы `watch_later`
--

CREATE TABLE `watch_later` (
  `watch_later_id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `video_id` int(10) UNSIGNED NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `watch_later`
--

INSERT INTO `watch_later` (`watch_later_id`, `user_id`, `video_id`, `display_order`, `added_at`) VALUES
(141, 33, 28, 0, '2025-11-03 19:57:51'),
(199, 32, 28, 0, '2025-11-20 10:03:56'),
(218, 32, 42, 0, '2025-11-23 18:13:07'),
(220, 32, 33, 0, '2025-11-23 18:37:20'),
(223, 35, 29, 0, '2025-12-02 13:55:39'),
(224, 32, 34, 0, '2025-12-26 12:03:05');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_auth_tokens_user_id` (`user_id`),
  ADD KEY `idx_selector` (`selector`);

--
-- Индексы таблицы `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Индексы таблицы `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `video_id` (`video_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `parent_comment_id` (`parent_comment_id`);

--
-- Индексы таблицы `comment_likes`
--
ALTER TABLE `comment_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_like` (`user_id`,`comment_id`),
  ADD KEY `comment_id` (`comment_id`);

--
-- Индексы таблицы `hidden_videos`
--
ALTER TABLE `hidden_videos`
  ADD PRIMARY KEY (`hidden_video_id`),
  ADD UNIQUE KEY `user_video_unique` (`user_id`,`video_id`),
  ADD KEY `hidden_videos_ibfk_2` (`video_id`);

--
-- Индексы таблицы `history`
--
ALTER TABLE `history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `video_id` (`video_id`);

--
-- Индексы таблицы `likes`
--
ALTER TABLE `likes`
  ADD PRIMARY KEY (`like_id`),
  ADD UNIQUE KEY `ux_user_video` (`user_id`,`video_id`),
  ADD KEY `video_id` (`video_id`);

--
-- Индексы таблицы `linked_accounts`
--
ALTER TABLE `linked_accounts`
  ADD PRIMARY KEY (`link_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `link_group_id` (`link_group_id`);

--
-- Индексы таблицы `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `fk_notifications_actor_user` (`actor_user_id`),
  ADD KEY `fk_notifications_video` (`video_id`);

--
-- Индексы таблицы `not_interested_videos`
--
ALTER TABLE `not_interested_videos`
  ADD PRIMARY KEY (`interest_id`),
  ADD UNIQUE KEY `user_video_unique` (`user_id`,`video_id`),
  ADD KEY `fk_not_interested_video` (`video_id`);

--
-- Индексы таблицы `playlists`
--
ALTER TABLE `playlists`
  ADD PRIMARY KEY (`playlist_id`),
  ADD KEY `fk_playlists_user` (`user_id`);

--
-- Индексы таблицы `playlist_videos`
--
ALTER TABLE `playlist_videos`
  ADD PRIMARY KEY (`playlist_video_id`),
  ADD UNIQUE KEY `uq_playlist_video` (`playlist_id`,`video_id`),
  ADD KEY `fk_playlistvideos_video` (`video_id`);

--
-- Индексы таблицы `recommendation_sections`
--
ALTER TABLE `recommendation_sections`
  ADD PRIMARY KEY (`section_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `recommended_channels`
--
ALTER TABLE `recommended_channels`
  ADD PRIMARY KEY (`recommendation_id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `recommended_channel_id` (`recommended_channel_id`);

--
-- Индексы таблицы `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `fk_reviews_user` (`user_id`);

--
-- Индексы таблицы `review_reactions`
--
ALTER TABLE `review_reactions`
  ADD PRIMARY KEY (`reaction_id`),
  ADD UNIQUE KEY `user_review_reaction` (`user_id`,`review_id`),
  ADD KEY `review_id` (`review_id`);

--
-- Индексы таблицы `search_history`
--
ALTER TABLE `search_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Индексы таблицы `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`subscription_id`),
  ADD UNIQUE KEY `ux_subscriber_channel` (`subscriber_id`,`channel_id`),
  ADD KEY `channel_id` (`channel_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `public_user_id_unique` (`public_user_id`),
  ADD UNIQUE KEY `channel_handle` (`channel_handle`);

--
-- Индексы таблицы `user_links`
--
ALTER TABLE `user_links`
  ADD PRIMARY KEY (`link_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `videos`
--
ALTER TABLE `videos`
  ADD PRIMARY KEY (`video_id`),
  ADD UNIQUE KEY `public_video_id_unique` (`public_video_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `video_categories`
--
ALTER TABLE `video_categories`
  ADD PRIMARY KEY (`video_category_id`),
  ADD UNIQUE KEY `ux_video_category` (`video_id`,`category_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Индексы таблицы `watch_later`
--
ALTER TABLE `watch_later`
  ADD PRIMARY KEY (`watch_later_id`),
  ADD UNIQUE KEY `user_video` (`user_id`,`video_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `video_id` (`video_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `auth_tokens`
--
ALTER TABLE `auth_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT для таблицы `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `comments`
--
ALTER TABLE `comments`
  MODIFY `comment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT для таблицы `comment_likes`
--
ALTER TABLE `comment_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `hidden_videos`
--
ALTER TABLE `hidden_videos`
  MODIFY `hidden_video_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `history`
--
ALTER TABLE `history`
  MODIFY `history_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=945;

--
-- AUTO_INCREMENT для таблицы `likes`
--
ALTER TABLE `likes`
  MODIFY `like_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT для таблицы `linked_accounts`
--
ALTER TABLE `linked_accounts`
  MODIFY `link_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT для таблицы `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT для таблицы `not_interested_videos`
--
ALTER TABLE `not_interested_videos`
  MODIFY `interest_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `playlists`
--
ALTER TABLE `playlists`
  MODIFY `playlist_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT для таблицы `playlist_videos`
--
ALTER TABLE `playlist_videos`
  MODIFY `playlist_video_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT для таблицы `recommendation_sections`
--
ALTER TABLE `recommendation_sections`
  MODIFY `section_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT для таблицы `recommended_channels`
--
ALTER TABLE `recommended_channels`
  MODIFY `recommendation_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT для таблицы `reports`
--
ALTER TABLE `reports`
  MODIFY `report_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT для таблицы `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT для таблицы `review_reactions`
--
ALTER TABLE `review_reactions`
  MODIFY `reaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `search_history`
--
ALTER TABLE `search_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT для таблицы `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `subscription_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT для таблицы `user_links`
--
ALTER TABLE `user_links`
  MODIFY `link_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT для таблицы `videos`
--
ALTER TABLE `videos`
  MODIFY `video_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT для таблицы `video_categories`
--
ALTER TABLE `video_categories`
  MODIFY `video_category_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT для таблицы `watch_later`
--
ALTER TABLE `watch_later`
  MODIFY `watch_later_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=226;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD CONSTRAINT `fk_auth_tokens_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`video_id`) REFERENCES `videos` (`video_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_3` FOREIGN KEY (`parent_comment_id`) REFERENCES `comments` (`comment_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `comment_likes`
--
ALTER TABLE `comment_likes`
  ADD CONSTRAINT `comment_likes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comment_likes_ibfk_2` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`comment_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `hidden_videos`
--
ALTER TABLE `hidden_videos`
  ADD CONSTRAINT `hidden_videos_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `hidden_videos_ibfk_2` FOREIGN KEY (`video_id`) REFERENCES `videos` (`video_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `history`
--
ALTER TABLE `history`
  ADD CONSTRAINT `history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `history_ibfk_2` FOREIGN KEY (`video_id`) REFERENCES `videos` (`video_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `likes`
--
ALTER TABLE `likes`
  ADD CONSTRAINT `likes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `likes_ibfk_2` FOREIGN KEY (`video_id`) REFERENCES `videos` (`video_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `linked_accounts`
--
ALTER TABLE `linked_accounts`
  ADD CONSTRAINT `fk_linked_accounts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_actor_user` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notifications_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`video_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `not_interested_videos`
--
ALTER TABLE `not_interested_videos`
  ADD CONSTRAINT `fk_not_interested_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_not_interested_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`video_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `playlists`
--
ALTER TABLE `playlists`
  ADD CONSTRAINT `fk_playlists_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `playlist_videos`
--
ALTER TABLE `playlist_videos`
  ADD CONSTRAINT `fk_playlistvideos_playlist` FOREIGN KEY (`playlist_id`) REFERENCES `playlists` (`playlist_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_playlistvideos_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`video_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `recommendation_sections`
--
ALTER TABLE `recommendation_sections`
  ADD CONSTRAINT `recommendation_sections_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `recommended_channels`
--
ALTER TABLE `recommended_channels`
  ADD CONSTRAINT `recommended_channels_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `recommendation_sections` (`section_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recommended_channels_ibfk_2` FOREIGN KEY (`recommended_channel_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `review_reactions`
--
ALTER TABLE `review_reactions`
  ADD CONSTRAINT `review_reactions_ibfk_1` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`review_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `review_reactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `search_history`
--
ALTER TABLE `search_history`
  ADD CONSTRAINT `fk_search_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`subscriber_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subscriptions_ibfk_2` FOREIGN KEY (`channel_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `user_links`
--
ALTER TABLE `user_links`
  ADD CONSTRAINT `fk_user_links_to_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `videos`
--
ALTER TABLE `videos`
  ADD CONSTRAINT `videos_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `video_categories`
--
ALTER TABLE `video_categories`
  ADD CONSTRAINT `video_categories_ibfk_1` FOREIGN KEY (`video_id`) REFERENCES `videos` (`video_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `video_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `watch_later`
--
ALTER TABLE `watch_later`
  ADD CONSTRAINT `fk_watch_later_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_watch_later_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`video_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
