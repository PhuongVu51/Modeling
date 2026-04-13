-- 1. Xóa các bảng cũ để tránh xung đột
DROP TABLE IF EXISTS user_interests;
DROP TABLE IF EXISTS profiles;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS interests;

-- 2. Bảng Users 
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    is_pro TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Bảng Profiles (Đã mở rộng thành 6 slot ảnh)
CREATE TABLE profiles (
    user_id INT PRIMARY KEY,
    full_name VARCHAR(255),
    nickname VARCHAR(100),
    dob DATE,
    gender ENUM('Male', 'Female', 'Other'),
    interested_in ENUM('Male', 'Female', 'Anyone') DEFAULT 'Anyone',
    bio TEXT,
    avatar VARCHAR(255),    
    photo_1 VARCHAR(255),   
    photo_2 VARCHAR(255),   
    photo_3 VARCHAR(255),
    photo_4 VARCHAR(255),
    photo_5 VARCHAR(255),
    photo_6 VARCHAR(255),
    location VARCHAR(255),
    occupation VARCHAR(255),
    company VARCHAR(255),
    height VARCHAR(50),
    education VARCHAR(255),
    drinking VARCHAR(50),
    pets VARCHAR(100),
    match_rate INT DEFAULT 84,
    response_rate INT DEFAULT 92,
    profile_views INT DEFAULT 1200,
    current_vibe_title VARCHAR(100) DEFAULT 'Romantic & Calm',
    current_vibe_desc TEXT DEFAULT 'People perceive your profile as peaceful and open-hearted.',
    ai_feedback TEXT DEFAULT 'Your profile is performing well! We noticed that you get 3x more replies when your bio includes specific details about your hobbies.',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 4. Bảng Danh mục Sở thích
CREATE TABLE interests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE
);

-- 5. Bảng trung gian
CREATE TABLE user_interests (
    user_id INT,
    interest_id INT,
    PRIMARY KEY (user_id, interest_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (interest_id) REFERENCES interests(id) ON DELETE CASCADE
);

-- 6. Insert 15 sở thích chuẩn
INSERT INTO interests (name) VALUES 
('Music'), ('Travel'), ('Coffee'), ('Reading'), ('Gym'), 
('Pets'), ('Movies'), ('Cooking'), ('Gaming'), ('Art'), 
('Photography'), ('Dancing'), ('Foodie'), ('Sports'), ('Karaoke');