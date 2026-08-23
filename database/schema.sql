-- =====================================================================
--  QuestScene — Step. Out. Stand Out.
--  MySQL / MariaDB schema  (Hostinger compatible: MySQL 5.7+ / MariaDB 10.3+)
--
--  How to use on Hostinger:
--    hPanel -> Databases -> phpMyAdmin -> select your DB -> Import -> schema.sql
--  Then open  https://yourdomain.com/install.php  to seed the admin + demo data.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Users
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name            VARCHAR(80)  NOT NULL,
  username        VARCHAR(40)  NOT NULL,
  email           VARCHAR(160) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  phone           VARCHAR(20)      NULL,
  city            VARCHAR(80)  NOT NULL DEFAULT 'Bangalore',
  bio             VARCHAR(400)     NULL,
  avatar          VARCHAR(255)     NULL,
  role            ENUM('user','organizer','admin') NOT NULL DEFAULT 'user',
  email_verified  TINYINT(1)   NOT NULL DEFAULT 0,
  phone_verified  TINYINT(1)   NOT NULL DEFAULT 0,
  id_verified     TINYINT(1)   NOT NULL DEFAULT 0,
  status          ENUM('active','suspended') NOT NULL DEFAULT 'active',
  rating_sum      INT UNSIGNED NOT NULL DEFAULT 0,
  rating_count    INT UNSIGNED NOT NULL DEFAULT 0,
  last_seen_at    DATETIME         NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_username (username),
  KEY ix_users_city (city),
  KEY ix_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Categories  (Running, Trekking, Football, ...)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug      VARCHAR(40)  NOT NULL,
  name      VARCHAR(60)  NOT NULL,
  emoji     VARCHAR(12)  NOT NULL DEFAULT '',
  sort      INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_interests (
  user_id     INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, category_id),
  CONSTRAINT fk_ui_user     FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
  CONSTRAINT fk_ui_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Communities
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS communities (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug          VARCHAR(120) NOT NULL,
  name          VARCHAR(120) NOT NULL,
  description   TEXT             NULL,
  city          VARCHAR(80)  NOT NULL DEFAULT 'Bangalore',
  category_id   INT UNSIGNED     NULL,
  cover         VARCHAR(255)     NULL,
  owner_id      INT UNSIGNED NOT NULL,
  verified      TINYINT(1)   NOT NULL DEFAULT 0,
  member_count  INT UNSIGNED NOT NULL DEFAULT 0,
  view_count    INT UNSIGNED NOT NULL DEFAULT 0,
  status        ENUM('active','hidden') NOT NULL DEFAULT 'active',
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_comm_slug (slug),
  KEY ix_comm_city (city),
  KEY ix_comm_owner (owner_id),
  CONSTRAINT fk_comm_owner    FOREIGN KEY (owner_id)    REFERENCES users(id)      ON DELETE CASCADE,
  CONSTRAINT fk_comm_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_members (
  community_id INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NOT NULL,
  role         ENUM('member','moderator','owner') NOT NULL DEFAULT 'member',
  joined_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (community_id, user_id),
  KEY ix_cm_user (user_id),
  CONSTRAINT fk_cm_comm FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
  CONSTRAINT fk_cm_user FOREIGN KEY (user_id)      REFERENCES users(id)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Plans  (a "plan" and an "event" are the same object; is_event flags
--         the curated/organiser-hosted ones so Discover can split them)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS plans (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug          VARCHAR(160) NOT NULL,
  title         VARCHAR(140) NOT NULL,
  description   TEXT             NULL,
  category_id   INT UNSIGNED     NULL,
  community_id  INT UNSIGNED     NULL,
  host_id       INT UNSIGNED NOT NULL,
  city          VARCHAR(80)  NOT NULL DEFAULT 'Bangalore',
  venue         VARCHAR(160)     NULL,
  map_url       VARCHAR(400)     NULL,
  starts_at     DATETIME     NOT NULL,
  ends_at       DATETIME         NULL,
  capacity      SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  price         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  cover         VARCHAR(255)     NULL,
  visibility    ENUM('public','community','invite') NOT NULL DEFAULT 'public',
  is_event      TINYINT(1)   NOT NULL DEFAULT 0,
  verified      TINYINT(1)   NOT NULL DEFAULT 0,
  going_count   INT UNSIGNED NOT NULL DEFAULT 0,
  view_count    INT UNSIGNED NOT NULL DEFAULT 0,
  status        ENUM('active','cancelled','hidden') NOT NULL DEFAULT 'active',
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_plans_slug (slug),
  KEY ix_plans_city_start (city, starts_at),
  KEY ix_plans_category (category_id),
  KEY ix_plans_host (host_id),
  KEY ix_plans_community (community_id),
  KEY ix_plans_status (status),
  CONSTRAINT fk_plans_host     FOREIGN KEY (host_id)      REFERENCES users(id)       ON DELETE CASCADE,
  CONSTRAINT fk_plans_category FOREIGN KEY (category_id)  REFERENCES categories(id)  ON DELETE SET NULL,
  CONSTRAINT fk_plans_comm     FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plan_participants (
  plan_id   INT UNSIGNED NOT NULL,
  user_id   INT UNSIGNED NOT NULL,
  status    ENUM('going','waitlist','left') NOT NULL DEFAULT 'going',
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (plan_id, user_id),
  KEY ix_pp_user (user_id),
  CONSTRAINT fk_pp_plan FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE,
  CONSTRAINT fk_pp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saved_plans (
  user_id  INT UNSIGNED NOT NULL,
  plan_id  INT UNSIGNED NOT NULL,
  saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, plan_id),
  CONSTRAINT fk_sp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_sp_plan FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Chat  (room_type = plan | community)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  room_type  ENUM('plan','community') NOT NULL,
  room_id    INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  body       VARCHAR(1000) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_msg_room (room_type, room_id, id),
  CONSTRAINT fk_msg_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Forum  (activity-oriented posts, not an Instagram feed)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS posts (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  category_id   INT UNSIGNED     NULL,
  community_id  INT UNSIGNED     NULL,
  city          VARCHAR(80)  NOT NULL DEFAULT 'Bangalore',
  body          TEXT         NOT NULL,
  intent        ENUM('looking','question','share') NOT NULL DEFAULT 'looking',
  interest_count INT UNSIGNED NOT NULL DEFAULT 0,
  reply_count   INT UNSIGNED NOT NULL DEFAULT 0,
  status        ENUM('active','hidden') NOT NULL DEFAULT 'active',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_posts_city (city, created_at),
  CONSTRAINT fk_posts_user FOREIGN KEY (user_id)      REFERENCES users(id)       ON DELETE CASCADE,
  CONSTRAINT fk_posts_cat  FOREIGN KEY (category_id)  REFERENCES categories(id)  ON DELETE SET NULL,
  CONSTRAINT fk_posts_comm FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_replies (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id    INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  body       VARCHAR(1000) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_pr_post (post_id, id),
  CONSTRAINT fk_pr_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_interests (
  post_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (post_id, user_id),
  CONSTRAINT fk_pi_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_pi_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Social graph
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS follows (
  follower_id  INT UNSIGNED NOT NULL,
  following_id INT UNSIGNED NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (follower_id, following_id),
  CONSTRAINT fk_fl_a FOREIGN KEY (follower_id)  REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_fl_b FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blocks (
  user_id    INT UNSIGNED NOT NULL,
  blocked_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, blocked_id),
  CONSTRAINT fk_bl_a FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_bl_b FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Trust & safety
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reports (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reporter_id INT UNSIGNED NOT NULL,
  target_type ENUM('plan','user','community','post','message') NOT NULL,
  target_id   INT UNSIGNED NOT NULL,
  reason      VARCHAR(60)  NOT NULL,
  details     VARCHAR(600)     NULL,
  status      ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_rep_status (status, created_at),
  CONSTRAINT fk_rep_user FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Notifications
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  icon       VARCHAR(12)  NOT NULL DEFAULT '',
  body       VARCHAR(300) NOT NULL,
  link       VARCHAR(300)     NULL,
  is_read    TINYINT(1)   NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_notif_user (user_id, is_read, id),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Play — game scores and the Daily Challenge
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS game_scores (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  game        VARCHAR(24)  NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  score       INT          NOT NULL DEFAULT 0,
  duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
  detail      VARCHAR(120)     NULL,
  daily_date  DATE             NULL,     -- set only for Daily Challenge runs
  city        VARCHAR(80)  NOT NULL DEFAULT 'Bangalore',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_gs_board  (game, score DESC),
  KEY ix_gs_user   (user_id, game, score DESC),
  KEY ix_gs_daily  (daily_date, score DESC),
  KEY ix_gs_city   (city, game, score DESC),
  CONSTRAINT fk_gs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Reference data
-- ---------------------------------------------------------------------
INSERT INTO categories (slug, name, emoji, sort) VALUES
  ('running',   'Running',    '🏃', 10),
  ('trekking',  'Trekking',   '🥾', 20),
  ('sports',    'Sports',     '⚽', 30),
  ('cycling',   'Cycling',    '🚴', 40),
  ('fitness',   'Fitness',    '💪', 50),
  ('hobbies',   'Hobbies',    '🎨', 60),
  ('music',     'Music',      '🎵', 70),
  ('gaming',    'Gaming',     '🎮', 80),
  ('learning',  'Learning',   '📚', 90),
  ('hangout',   'Hangouts',   '☕', 100),
  ('founders',  'Founders',   '🚀', 110),
  ('other',     'Other',      '✨', 120)
ON DUPLICATE KEY UPDATE name = VALUES(name), emoji = VALUES(emoji), sort = VALUES(sort);

SET FOREIGN_KEY_CHECKS = 1;
