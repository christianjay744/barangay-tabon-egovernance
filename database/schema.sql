CREATE DATABASE IF NOT EXISTS barangay_blockchain;
USE barangay_blockchain;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('resident','admin') NOT NULL DEFAULT 'resident',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE residents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    address VARCHAR(255) NOT NULL,
    birth_date DATE NULL,
    contact_number VARCHAR(30) NULL,
    identity_status ENUM('Pending','Verified','Rejected') DEFAULT 'Pending',
    identity_reference VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE document_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_type ENUM('Barangay Clearance','Certificate of Residency','Certificate of Indigency','Other Certification') NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    requirements_text TEXT NULL,
    status ENUM('Pending','Approved','Rejected','Released','Revoked') DEFAULT 'Pending',
    admin_note TEXT NULL,
    document_number VARCHAR(80) UNIQUE NULL,
    document_hash CHAR(64) NULL,
    blockchain_tx VARCHAR(120) NULL,
    blockchain_status ENUM('Not Recorded','Pending','Recorded','Failed') DEFAULT 'Not Recorded',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    released_at TIMESTAMP NULL,
    revoked_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO users (full_name,email,password_hash,role)
VALUES ('Barangay Tabon Administrator','admin@barangaytabon.local',
'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCz3L7LkQYwY8X5QvO2',
'admin');
-- Password for the demo admin is: admin123
