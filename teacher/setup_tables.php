<?php
// setup_tables.php - Создание необходимых таблиц если они не существуют

// Таблица предметов
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subjects (
            id INT PRIMARY KEY AUTO_INCREMENT,
            school_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            short_name VARCHAR(20),
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (school_id) REFERENCES schools(id)
        )
    ");

    // Добавляем базовые предметы если их нет
    $check_subjects = $pdo->prepare("SELECT COUNT(*) as count FROM subjects WHERE school_id = ?");
    $check_subjects->execute([$school_id]);
    $subjects_count = $check_subjects->fetch()['count'];

    if ($subjects_count == 0) {
        $base_subjects = [
            ['name' => 'Математика', 'short_name' => 'Матем'],
            ['name' => 'Русский язык', 'short_name' => 'Рус яз'],
            ['name' => 'Литература', 'short_name' => 'Лит-ра'],
            ['name' => 'История', 'short_name' => 'Ист'],
            ['name' => 'Обществознание', 'short_name' => 'Общ'],
            ['name' => 'География', 'short_name' => 'Геогр'],
            ['name' => 'Биология', 'short_name' => 'Биол'],
            ['name' => 'Физика', 'short_name' => 'Физ'],
            ['name' => 'Химия', 'short_name' => 'Хим'],
            ['name' => 'Английский язык', 'short_name' => 'Англ'],
            ['name' => 'Информатика', 'short_name' => 'Инф'],
            ['name' => 'Физкультура', 'short_name' => 'Физ-ра'],
            ['name' => 'Музыка', 'short_name' => 'Муз'],
            ['name' => 'ИЗО', 'short_name' => 'ИЗО'],
            ['name' => 'Технология', 'short_name' => 'Техн']
        ];

        $stmt = $pdo->prepare("INSERT INTO subjects (school_id, name, short_name) VALUES (?, ?, ?)");
        foreach ($base_subjects as $subject) {
            $stmt->execute([$school_id, $subject['name'], $subject['short_name']]);
        }
    }
} catch (PDOException $e) {
    error_log("Ошибка при создании таблицы subjects: " . $e->getMessage());
}

// Таблица оценок
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS grades (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id INT NOT NULL,
            teacher_id INT NOT NULL,
            subject_id INT NOT NULL,
            grade_value VARCHAR(10),
            lesson_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES users(id),
            FOREIGN KEY (teacher_id) REFERENCES users(id),
            FOREIGN KEY (subject_id) REFERENCES subjects(id)
        )
    ");
} catch (PDOException $e) {
    error_log("Ошибка при создании таблицы grades: " . $e->getMessage());
}

// Таблица домашних заданий
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS homework (
            id INT PRIMARY KEY AUTO_INCREMENT,
            teacher_id INT NOT NULL,
            class_id INT NOT NULL,
            subject_id INT NOT NULL,
            due_date DATE NOT NULL,
            description TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (teacher_id) REFERENCES users(id),
            FOREIGN KEY (class_id) REFERENCES classes(id),
            FOREIGN KEY (subject_id) REFERENCES subjects(id)
        )
    ");
} catch (PDOException $e) {
    error_log("Ошибка при создании таблицы homework: " . $e->getMessage());
}

// Таблица посещаемости
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id INT NOT NULL,
            teacher_id INT NOT NULL,
            class_id INT NOT NULL,
            lesson_date DATE NOT NULL,
            status ENUM('present', 'absent', 'late') DEFAULT 'present',
            reason VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES users(id),
            FOREIGN KEY (teacher_id) REFERENCES users(id),
            FOREIGN KEY (class_id) REFERENCES classes(id)
        )
    ");
} catch (PDOException $e) {
    error_log("Ошибка при создании таблицы attendance: " . $e->getMessage());
}
?>