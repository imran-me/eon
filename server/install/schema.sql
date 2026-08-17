-- EON's own tables (MySQL 8 / MariaDB 10.4+). Run once:  mysql -u USER -p DBNAME < install/schema.sql
-- Everything EON writes lives here; the ERP tables are only ever read.

CREATE TABLE IF NOT EXISTS eon_settings (
  `key` VARCHAR(80) PRIMARY KEY,
  value JSON NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS eon_docs (
  doc_key VARCHAR(160) PRIMARY KEY,
  data JSON NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS eon_conversations (
  id VARCHAR(32) PRIMARY KEY,
  channel VARCHAR(16) NOT NULL DEFAULT 'text',
  title VARCHAR(200) NULL,
  started_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS eon_messages (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  conversation_id VARCHAR(32) NOT NULL,
  role VARCHAR(16) NOT NULL,
  text MEDIUMTEXT NOT NULL,
  meta JSON NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_conv (conversation_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS eon_decisions (
  day DATE NOT NULL,
  company_id INT NULL,
  payload JSON NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_day_company (day, company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS eon_actions (
  id VARCHAR(16) PRIMARY KEY,
  kind VARCHAR(24) NOT NULL,
  payload JSON NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'queued',
  actor VARCHAR(120) NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO eon_settings (`key`, value, updated_at) VALUES ('installed', JSON_OBJECT('version', '0.1.0'), NOW());

-- Recommended: a read-only MySQL user for EON on the ERP database
-- CREATE USER 'eon_readonly'@'localhost' IDENTIFIED BY '********';
-- GRANT SELECT ON epal_erp.* TO 'eon_readonly'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON epal_erp.eon_settings TO 'eon_readonly'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON epal_erp.eon_docs TO 'eon_readonly'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON epal_erp.eon_conversations TO 'eon_readonly'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON epal_erp.eon_messages TO 'eon_readonly'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON epal_erp.eon_decisions TO 'eon_readonly'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON epal_erp.eon_actions TO 'eon_readonly'@'localhost';
