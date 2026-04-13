-- 1. Xóa các bảng cũ để tránh xung đột cấu trúc (Lưu ý: Thao tác này sẽ xóa mọi dữ liệu cũ)
DROP TABLE IF EXISTS user_interests;
DROP TABLE IF EXISTS profiles;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS interests;

-- 2. Tạo bảng Users (Thông tin đăng nhập & liên hệ cơ bản)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Tạo bảng Profiles (Thông tin chi tiết và 4 ảnh để khớp với code register.php)
CREATE TABLE profiles (
    user_id INT PRIMARY KEY,
    full_name VARCHAR(255),
    nickname VARCHAR(100),
    dob DATE,
    gender ENUM('Male', 'Female', 'Other'),
    interested_in ENUM('Male', 'Female', 'Anyone') DEFAULT 'Anyone',
    bio TEXT,
    avatar VARCHAR(255),    -- Ảnh đại diện chính
    photo_1 VARCHAR(255),   -- Ảnh profile phụ 1
    photo_2 VARCHAR(255),   -- Ảnh profile phụ 2
    photo_3 VARCHAR(255),   -- Ảnh profile phụ 3
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 4. Tạo bảng Interests (Danh mục sở thích)
CREATE TABLE interests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE
);

-- 5. Tạo bảng trung gian User_Interests (Liên kết người dùng với sở thích cho Matching AI)
CREATE TABLE user_interests (
    user_id INT,
    interest_id INT,
    PRIMARY KEY (user_id, interest_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (interest_id) REFERENCES interests(id) ON DELETE CASCADE
);

-- 6. Chèn danh sách 15 mục sở thích chuẩn để hệ thống phân tích
INSERT INTO interests (name) VALUES 
('Music'), ('Travel'), ('Coffee'), ('Reading'), ('Gym'), 
('Pets'), ('Movies'), ('Cooking'), ('Gaming'), ('Art'), 
('Photography'), ('Dancing'), ('Foodie'), ('Sports'), ('Karaoke');