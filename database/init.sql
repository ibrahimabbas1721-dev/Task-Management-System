-- 1. Users Table (Must be first for foreign key references)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    profile_pic VARCHAR(255) DEFAULT 'default_user.png',
    created_by_admin INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Projects Table
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('active', 'completed', 'on_hold') DEFAULT 'active',
    assigned_date DATE,
    plan_type VARCHAR(50), -- e.g., Basic, Premium, Custom
    display_order INT DEFAULT 0,
    created_by_admin INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by_admin) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 3. Tasks Table
CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    group_name VARCHAR(100), -- For categorizing within a project (e.g., "Design", "Dev")
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('in_progress', 'review', 'done') DEFAULT 'in_progress',
    due_date DATETIME,
    assigned_to INT,
    created_by_admin INT,
    attachment VARCHAR(255), -- File path
    tags VARCHAR(255), -- CSV or JSON string
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by_admin) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;