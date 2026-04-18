CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS books (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    author TEXT,
    status TEXT NOT NULL DEFAULT 'draft',
    source_language TEXT DEFAULT 'English',
    target_language TEXT DEFAULT 'English',
    rephrase_settings TEXT,
    translate_settings TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS chapters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    book_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    source_content TEXT,
    target_content TEXT,
    status TEXT,
    chapter_order INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    book_id INTEGER,
    image_local_path TEXT,
    thumbnail_local_path TEXT,
    remote_url TEXT,
    prompt TEXT,
    image_type TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Seed a default user if none exists
INSERT INTO users (id, name, email)
SELECT 1, 'Default User', 'user@example.com'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE id = 1);

-- When a book's own details are updated
CREATE TRIGGER IF NOT EXISTS update_book_timestamp_on_update
    AFTER UPDATE ON books
    FOR EACH ROW
BEGIN
    UPDATE books
    SET updated_at = CURRENT_TIMESTAMP
    WHERE id = OLD.id;
END;

-- When a chapter is changed
CREATE TRIGGER IF NOT EXISTS update_book_on_chapter_update
    AFTER UPDATE ON chapters
    FOR EACH ROW
BEGIN
    UPDATE books
    SET updated_at = CURRENT_TIMESTAMP
    WHERE id = NEW.book_id;
END;

CREATE TRIGGER IF NOT EXISTS update_book_on_chapter_insert
    AFTER INSERT ON chapters
    FOR EACH ROW
BEGIN
    UPDATE books
    SET updated_at = CURRENT_TIMESTAMP
    WHERE id = NEW.book_id;
END;

CREATE TRIGGER IF NOT EXISTS update_book_on_chapter_delete
    AFTER DELETE ON chapters
    FOR EACH ROW
BEGIN
    UPDATE books
    SET updated_at = CURRENT_TIMESTAMP
    WHERE id = OLD.book_id;
END;
