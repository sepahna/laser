SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS services (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  price_irr BIGINT UNSIGNED NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_settings (
  id TINYINT UNSIGNED PRIMARY KEY,
  opening_time TIME NOT NULL DEFAULT '12:00:00',
  closing_time TIME NOT NULL DEFAULT '22:00:00',
  default_duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  deposit_percent TINYINT UNSIGNED NOT NULL DEFAULT 30,
  hold_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  bank_account_holder VARCHAR(160) NULL,
  bank_card_number VARCHAR(32) NULL,
  bank_iban VARCHAR(40) NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO business_settings (id) VALUES (1);

CREATE TABLE IF NOT EXISTS closures (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  closed_date DATE NOT NULL UNIQUE,
  reason VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tracking_code VARCHAR(24) NOT NULL UNIQUE,
  customer_name VARCHAR(160) NOT NULL,
  customer_phone VARCHAR(20) NOT NULL,
  gender VARCHAR(20) NOT NULL,
  appointment_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL,
  total_irr BIGINT UNSIGNED NOT NULL,
  deposit_irr BIGINT UNSIGNED NOT NULL,
  status ENUM('pending_transfer_review','confirmed','cancelled','expired') NOT NULL DEFAULT 'pending_transfer_review',
  hold_expires_at DATETIME NULL,
  admin_note TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_booking_date_status (appointment_date,status),
  INDEX idx_booking_phone (customer_phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_services (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  service_name VARCHAR(120) NOT NULL,
  price_irr BIGINT UNSIGNED NOT NULL,
  CONSTRAINT fk_bs_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_bs_service FOREIGN KEY (service_id) REFERENCES services(id),
  UNIQUE KEY uq_booking_service (booking_id,service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id BIGINT UNSIGNED NOT NULL,
  method ENUM('bank_transfer') NOT NULL DEFAULT 'bank_transfer',
  amount_irr BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  receipt_path VARCHAR(500) NOT NULL,
  verified_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payment_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  INDEX idx_payment_status (status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO services (name,price_irr,sort_order) VALUES
('صورت کامل',180000,1),('پشت لب',60000,2),('چانه',70000,3),('گردن',100000,4),
('زیر بغل',150000,5),('دست کامل',300000,6),('نیم‌دست',180000,7),('پا کامل',500000,8),
('نیم‌پا',280000,9),('بیکینی',220000,10),('خط بیکینی',130000,11),('شکم',180000,12),
('کمر',220000,13),('سینه',180000,14),('باسن',200000,15);
