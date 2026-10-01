-- Drive Leadership database schema
-- Select your DirectAdmin-created database in phpMyAdmin before running this file.

CREATE TABLE IF NOT EXISTS attendee_applications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(150) NOT NULL,
    organisation VARCHAR(200) NOT NULL,
    employment_status ENUM(
        'Employed',
        'Student',
        'Entrepreneur',
        'Unemployed',
        'Other'
    ) NOT NULL,
    employment_status_other VARCHAR(100) NULL,
    email VARCHAR(254) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    is_network_member ENUM('Yes', 'No') NOT NULL,
    referral_source ENUM(
        'Social Media',
        'Referral',
        'Email Invitation',
        'Other'
    ) NOT NULL,
    referral_source_other VARCHAR(150) NULL,
    expectations TEXT NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_attendee_email (email),
    INDEX idx_attendee_status (status),
    INDEX idx_attendee_submitted_at (submitted_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sender_name VARCHAR(150) NOT NULL,
    sender_email VARCHAR(254) NOT NULL,
    interest ENUM(
        'Executive coaching',
        'Emerging leaders programme',
        'Corporate workshop',
        'Speaking engagement'
    ) NOT NULL,
    message TEXT NULL,
    status ENUM('Unread', 'Read', 'Replied') NOT NULL DEFAULT 'Unread',
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    replied_at TIMESTAMP NULL DEFAULT NULL,

    PRIMARY KEY (id),
    INDEX idx_contact_sender_email (sender_email),
    INDEX idx_contact_status (status),
    INDEX idx_contact_submitted_at (submitted_at),
    INDEX idx_contact_interest (interest)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_email (email),
    INDEX idx_admin_active (is_active)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
