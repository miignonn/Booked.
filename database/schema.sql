CREATE TABLE users(
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email CVARCHAR(150) NOT NULL UNIQUE,
    student_number VARCHAR (50) UNIQUE,
    institution VARCHAR (100),
    phone VARCHAR (20),
    role ENUM('student', 'user') DEFAULT 'student',
    status ENUM('active', 'suspended', 'banned') DEFAULT 'active',
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

);

CREATE TABBLE categories(
id INT AUTO_INCREMENT PRIMARY KEY,
name VARCHAR(100) NOT NULL,
slug VARCHAR(100) UNIQUE NOT NULL
);

CREATE TABLE listings(
id INT AUTO_INCREMENT PRIMARY KEY,
user_id INT NOT NULL,
category_id INT NOT NULL,
title VARCHAR(200) NOT NULL,
author VARCHAR (150) NOT NULL,
isbn VARCHAR (100),
institution VARCHAR (200) NOT NULL,
description TEXT,
price DECIMAL (5,2) NOT NULL,
condition ENUM('new', 'good', 'fair', 'poor') NOT NULL,
status ENUM ('active', 'sold', 'expired', 'flagged') DEFAULT 'active',
image VARCHAR (255),
expires_at TIMESTAMP NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

CREATE TABLE reports(
id INT AUTO_INCREMENT PRIMARY KEY,
listing_id INT NOT NULL,
reported_by INT NOT NULL,
reason TEXT NOT NULL,
status ENUM('pending', 'reviewed', 'dismissed') DEFAULT 'pending',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE
);