    CREATE DATABASE alumni_network;
    USE alumni_network;

    CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        user_type ENUM('admin', 'alumni', 'student') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        headline VARCHAR(100),
        about TEXT,
        skills TEXT,
        education TEXT,
        experience TEXT,
        resume_path VARCHAR(255),
        profile_pic VARCHAR(255) DEFAULT 'default.png',
        is_verified BOOLEAN DEFAULT FALSE,
        verified_by INT,
        verified_at TIMESTAMP NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
    );

    CREATE TABLE friend_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    );

    CREATE TABLE messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        message TEXT NOT NULL,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_read BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    );
    -- Add these to your database.sql file
    CREATE TABLE job_postings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alumni_id INT NOT NULL,
        company_name VARCHAR(100) NOT NULL,
        job_title VARCHAR(100) NOT NULL,
        job_description TEXT NOT NULL,
        required_skills TEXT,
        location VARCHAR(100),
        job_type ENUM('full-time', 'part-time', 'internship', 'contract') DEFAULT 'full-time',
        experience_level ENUM('entry', 'mid', 'senior') DEFAULT 'entry',
        is_verified BOOLEAN DEFAULT FALSE,
        posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (alumni_id) REFERENCES users(id) ON DELETE CASCADE
    );

    CREATE TABLE referrals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_posting_id INT NOT NULL,
        student_id INT NOT NULL,
        alumni_id INT NOT NULL,
        status ENUM('pending', 'approved', 'rejected', 'submitted') DEFAULT 'pending',
        application_message TEXT,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (job_posting_id) REFERENCES job_postings(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (alumni_id) REFERENCES users(id) ON DELETE CASCADE
    );
    -- Insert default admin user (password: admin123)
    INSERT INTO users (username, password, email, user_type) 
    VALUES ('admin', '$2y$10$r8V.5JZ7q3V1WkKk6wQzO.FcLd9YbNfLd8XcV7sNqR3rV2M1JYbW', 'admin@example.com', 'admin');