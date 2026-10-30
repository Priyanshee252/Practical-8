CREATE DATABASE IF NOT EXISTS techmart_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE techmart_db;

CREATE TABLE IF NOT EXISTS products (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  product_id   VARCHAR(20)   NOT NULL UNIQUE,
  product_name VARCHAR(100)  NOT NULL,
  category     VARCHAR(50)   NOT NULL,
  price        DECIMAL(10,2) NOT NULL,
  quantity     INT UNSIGNED  NOT NULL DEFAULT 0,
  description  VARCHAR(500)  NULL,
  created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);