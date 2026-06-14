SET FOREIGN_KEY_CHECKS = 0;

-- 1. Xóa các bảng cũ
DROP TABLE IF EXISTS group_messages;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS double_date_members;
DROP TABLE IF EXISTS double_dates;
DROP TABLE IF EXISTS post_likes;
DROP TABLE IF EXISTS posts;
DROP TABLE IF EXISTS date_spots;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS matches;
DROP TABLE IF EXISTS likes;
DROP TABLE IF EXISTS user_interests;
DROP TABLE IF EXISTS interests;
DROP TABLE IF EXISTS profiles;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- 2. Bảng Users (Lưu thông tin đăng nhập tài khoản)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    is_pro TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Bảng Profiles (Lưu thông tin chi tiết hiển thị trên app)
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
    match_rate INT DEFAULT 0,
    response_rate INT DEFAULT 0,
    profile_views INT DEFAULT 0,
    current_vibe_title VARCHAR(100) DEFAULT 'Mysterious Vibe',
    current_vibe_desc TEXT DEFAULT 'People perceive this profile as peaceful and open-hearted.',
    ai_feedback TEXT DEFAULT 'Profile của bạn đang khá trống. Hãy thêm sở thích và cập nhật Bio để tăng 50% tỉ lệ match nhé!',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 4. Bảng Danh mục Sở thích
CREATE TABLE interests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE
);

-- 5. Bảng Trung gian (Nối User và Interests)
CREATE TABLE user_interests (
    user_id INT,
    interest_id INT,
    PRIMARY KEY (user_id, interest_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (interest_id) REFERENCES interests(id) ON DELETE CASCADE
);

-- 6. Bảng Lưu trữ lịch sử Quẹt thẻ (Thích hoặc Bỏ qua)
CREATE TABLE likes (
    user_id INT,
    liked_user_id INT,
    is_like TINYINT(1) DEFAULT 1, -- 1 là Like, 0 là Pass (X)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, liked_user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (liked_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 7. Bảng Tương hợp (Lưu danh sách 2 người đã Match nhau)
CREATE TABLE matches (
    user1_id INT,
    user2_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user1_id, user2_id),
    FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message_text TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 8. Insert sẵn 15 sở thích chuẩn để hiển thị lên giao diện
INSERT INTO interests (name) VALUES 
('Music'), ('Travel'), ('Coffee'), ('Reading'), ('Gym'), 
('Pets'), ('Movies'), ('Cooking'), ('Gaming'), ('Art'), 
('Photography'), ('Dancing'), ('Foodie'), ('Sports'), ('Karaoke');

ALTER TABLE matches ADD COLUMN streak_count INT DEFAULT 0;
ALTER TABLE matches ADD COLUMN last_interact_date DATE DEFAULT NULL;
ALTER TABLE matches ADD COLUMN is_blind TINYINT(1) DEFAULT 0;
ALTER TABLE matches ADD COLUMN is_revealed TINYINT(1) DEFAULT 0;
ALTER TABLE profiles ADD COLUMN is_waiting_blind TINYINT(1) DEFAULT 0;


-----------------------------------------------------------------------------
-- PHẦN DOUBLE DATE (Được gộp từ nhánh HEAD)
-----------------------------------------------------------------------------

-- Bảng lưu trữ phiên Double Date
CREATE TABLE IF NOT EXISTS double_dates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    creator_id INT NOT NULL,
    status ENUM('pending', 'active', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Bảng lưu thành viên của Double Date và trạng thái đồng ý/từ chối
CREATE TABLE IF NOT EXISTS double_date_members (
    double_date_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    PRIMARY KEY (double_date_id, user_id),
    FOREIGN KEY (double_date_id) REFERENCES double_dates(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Bảng lưu thông báo (Hình cái chuông)
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sender_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    reference_id INT, 
    message TEXT,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Bảng lưu tin nhắn trong Double Date
CREATE TABLE IF NOT EXISTS group_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    double_date_id INT NOT NULL,
    sender_id INT NOT NULL,
    message_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (double_date_id) REFERENCES double_dates(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);


-----------------------------------------------------------------------------
-- PHẦN EXPLORE VÀ DATE SPOTS (Được gộp từ nhánh origin/explore)
-----------------------------------------------------------------------------

-- 9. Posts table for the Explore social feed
CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    caption TEXT,
    photo VARCHAR(255),
    mood_tag VARCHAR(50) DEFAULT NULL,
    shared_interest VARCHAR(100) DEFAULT NULL,
    likes_count INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS post_likes (
    user_id INT NOT NULL,
    post_id INT NOT NULL,
    PRIMARY KEY (user_id, post_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
);

-- 10. Date spots suggestions
CREATE TABLE IF NOT EXISTS date_spots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    photo VARCHAR(255),
    sync_rate INT DEFAULT 96
);

INSERT INTO date_spots (name, description, photo, sync_rate) VALUES
('West Lake (Trich Sai / Ve Ho area)', 'Enjoy a sunset walk, then grab a salted coffee from nearby street vendors.', NULL, 96),
('Hoan Kiem Walking Street', 'Perfect for weekend dates, where couples can watch street performances together.', NULL, 94),
('Sky Walk Observation Deck (Lotte Lieu Giai)', 'Experience a panoramic view of Hanoi from the 65th floor, with many great photo spots.', NULL, 92),
('Complex 01 (Tay Son)', 'A creative art space with workshops like pottery and painting—ideal for breaking the ice on a first date.', NULL, 89);