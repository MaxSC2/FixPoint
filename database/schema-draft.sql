-- FixPoint initial schema draft (MySQL 8, InnoDB, utf8mb4)
CREATE DATABASE IF NOT EXISTS fixpoint CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fixpoint;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(32) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('customer','technician','admin') NOT NULL DEFAULT 'customer',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE service_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE services (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  short_description VARCHAR(255) NULL,
  description TEXT NULL,
  price_from DECIMAL(10,2) NULL,
  estimated_minutes INT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_services_category FOREIGN KEY(category_id) REFERENCES service_categories(id)
) ENGINE=InnoDB;

CREATE TABLE repair_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_number VARCHAR(32) NOT NULL UNIQUE,
  customer_id BIGINT UNSIGNED NOT NULL,
  technician_id BIGINT UNSIGNED NULL,
  service_id BIGINT UNSIGNED NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'new',
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  device_type VARCHAR(100) NOT NULL,
  device_brand VARCHAR(100) NULL,
  device_model VARCHAR(160) NULL,
  problem_description TEXT NOT NULL,
  diagnostic_result TEXT NULL,
  estimated_cost DECIMAL(10,2) NULL,
  final_cost DECIMAL(10,2) NULL,
  estimated_ready_at DATETIME NULL,
  customer_contact VARCHAR(80) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  INDEX idx_requests_customer_created(customer_id, created_at),
  INDEX idx_requests_technician_status(technician_id, status),
  INDEX idx_requests_status_created(status, created_at),
  CONSTRAINT fk_requests_customer FOREIGN KEY(customer_id) REFERENCES users(id),
  CONSTRAINT fk_requests_technician FOREIGN KEY(technician_id) REFERENCES users(id),
  CONSTRAINT fk_requests_service FOREIGN KEY(service_id) REFERENCES services(id)
) ENGINE=InnoDB;

CREATE TABLE request_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(40) NULL,
  to_status VARCHAR(40) NOT NULL,
  reason VARCHAR(500) NULL,
  changed_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_history_request_created(request_id, created_at),
  CONSTRAINT fk_history_request FOREIGN KEY(request_id) REFERENCES repair_requests(id),
  CONSTRAINT fk_history_user FOREIGN KEY(changed_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE request_comments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id BIGINT UNSIGNED NOT NULL,
  author_id BIGINT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  is_internal TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_comments_request_created(request_id, created_at),
  CONSTRAINT fk_comments_request FOREIGN KEY(request_id) REFERENCES repair_requests(id),
  CONSTRAINT fk_comments_author FOREIGN KEY(author_id) REFERENCES users(id)
) ENGINE=InnoDB;
