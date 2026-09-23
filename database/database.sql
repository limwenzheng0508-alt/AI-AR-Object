CREATE DATABASE IF NOT EXISTS ai_ar_scanner
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ai_ar_scanner;

CREATE TABLE IF NOT EXISTS scan_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  object_label VARCHAR(120) NOT NULL DEFAULT '',
  product_name VARCHAR(255) NOT NULL DEFAULT '',
  manufacturer VARCHAR(120) NOT NULL DEFAULT '',
  specification VARCHAR(500) NOT NULL DEFAULT '',
  description TEXT NULL,
  confidence DECIMAL(5,4) NOT NULL DEFAULT 0,
  provider VARCHAR(40) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_created (created_at),
  KEY idx_product (product_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
