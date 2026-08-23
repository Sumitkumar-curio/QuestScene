-- =====================================================================
--  QuestScene — migration 002: the Play section
--  Run this ONLY if you already imported schema.sql before games existed.
--  Fresh installs get these tables from schema.sql directly.
-- =====================================================================

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
