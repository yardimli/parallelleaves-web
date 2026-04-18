CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    session_token VARCHAR(255) DEFAULT NULL,
    token_expires_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS novels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'draft',
    source_language VARCHAR(100) DEFAULT 'English',
    target_language VARCHAR(100) DEFAULT 'English',
    rephrase_settings TEXT DEFAULT NULL,
    translate_settings TEXT DEFAULT NULL,
    codex_content MEDIUMTEXT DEFAULT NULL,
    codex_status ENUM('none','pending','generating','complete','error') NOT NULL DEFAULT 'none',
    codex_chunks_total INT NOT NULL DEFAULT 0,
    codex_chunks_processed INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS chapters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    novel_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    source_content MEDIUMTEXT DEFAULT NULL,
    target_content MEDIUMTEXT DEFAULT NULL,
    status VARCHAR(50) DEFAULT NULL,
    chapter_order INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (novel_id) REFERENCES novels(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    novel_id INT DEFAULT NULL,
    image_local_path VARCHAR(255) DEFAULT NULL,
    thumbnail_local_path VARCHAR(255) DEFAULT NULL,
    remote_url VARCHAR(500) DEFAULT NULL,
    prompt TEXT DEFAULT NULL,
    image_type VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (novel_id) REFERENCES novels(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS translation_memory_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    novel_id INT NOT NULL,
    marker_id INT NOT NULL,
    source_text TEXT NOT NULL,
    target_text TEXT NOT NULL,
    is_analyzed TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY novel_marker (novel_id, marker_id),
    FOREIGN KEY (novel_id) REFERENCES novels(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS translation_memory_pairs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    novel_id INT NOT NULL,
    block_id INT NOT NULL,
    source_sentence TEXT NOT NULL,
    target_sentence TEXT NOT NULL,
    FOREIGN KEY (novel_id) REFERENCES novels(id) ON DELETE CASCADE,
    FOREIGN KEY (block_id) REFERENCES translation_memory_blocks(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tm_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    novel_id INT NOT NULL,
    status ENUM('pending','running','complete','error') NOT NULL DEFAULT 'pending',
    total_blocks INT NOT NULL DEFAULT 0,
    processed_blocks INT NOT NULL DEFAULT 0,
    error_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (novel_id) REFERENCES novels(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS api_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    request_payload LONGTEXT DEFAULT NULL,
    response_body LONGTEXT DEFAULT NULL,
    response_code SMALLINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS translation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    novel_id INT NOT NULL,
    chapter_id INT NOT NULL,
    source_text TEXT NOT NULL,
    target_text TEXT NOT NULL,
    marker VARCHAR(255) DEFAULT NULL,
    model VARCHAR(255) NOT NULL,
    temperature FLOAT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (novel_id) REFERENCES novels(id) ON DELETE CASCADE,
    FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE
);

-- Insert a default user for local testing if needed
INSERT IGNORE INTO users (username, password_hash) VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); -- password: password
