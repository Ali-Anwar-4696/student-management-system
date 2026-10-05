-- Safe additive schema updates. Do not DROP or TRUNCATE.

CREATE TABLE IF NOT EXISTS exam_subjects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    total_marks DECIMAL(6,2) NOT NULL DEFAULT 100.00,
    pass_marks DECIMAL(6,2) NOT NULL DEFAULT 40.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (exam_id, subject_id),
    INDEX idx_exam_subjects_exam (exam_id),
    INDEX idx_exam_subjects_subject (subject_id),
    CONSTRAINT fk_exam_subjects_exam
        FOREIGN KEY (exam_id) REFERENCES exams(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_exam_subjects_subject
        FOREIGN KEY (subject_id) REFERENCES subjects(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    target_role ENUM('admin', 'teacher', 'student', 'all') NOT NULL DEFAULT 'all',
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_notifications_creator
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_notifications_role (target_role),
    INDEX idx_notifications_user (user_id)
);

CREATE TABLE IF NOT EXISTS notification_reads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    notification_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (notification_id, user_id),
    CONSTRAINT fk_notification_reads_notification
        FOREIGN KEY (notification_id) REFERENCES notifications(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_notification_reads_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);
