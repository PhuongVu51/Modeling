-- Tạo Database: soul_sync_db
CREATE DATABASE IF NOT EXISTS soul_sync_db;
USE soul_sync_db;

-- 1. Bảng Users (Thông tin đăng nhập & liên hệ)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Bảng Profiles (Thông tin cá nhân & Gu tìm kiếm)
CREATE TABLE IF NOT EXISTS profiles (
    user_id INT PRIMARY KEY,
    full_name VARCHAR(255),
    nickname VARCHAR(100),
    dob DATE,
    gender ENUM('Male', 'Female', 'Other'),
    occupation VARCHAR(255),
    interested_in ENUM('Male', 'Female', 'Anyone'),
    age_range VARCHAR(50),
    bio TEXT, -- Tin nhắn gửi đối phương
    avatar VARCHAR(255), -- Đường dẫn ảnh đại diện
    photo_1 VARCHAR(255),
    photo_2 VARCHAR(255),
    photo_3 VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. Bảng Interests (Danh mục sở thích)
CREATE TABLE IF NOT EXISTS interests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE
);

-- 4. Bảng User_Interests (Liên kết người dùng với sở thích)
CREATE TABLE IF NOT EXISTS user_interests (
    user_id INT,
    interest_id INT,
    PRIMARY KEY (user_id, interest_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (interest_id) REFERENCES interests(id) ON DELETE CASCADE
);

-- Chèn một số sở thích mẫu
INSERT IGNORE INTO interests (name) VALUES 
('Music'), ('Travel'), ('Coffee'), ('Reading'), ('Gym'), 
('Pets'), ('Movies'), ('Cooking'), ('Gaming'), ('Art'), ('Photography');