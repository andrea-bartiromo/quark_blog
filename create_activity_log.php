<?php
$p = new PDO('sqlite:database/database.sqlite');
$p->exec("CREATE TABLE IF NOT EXISTS activity_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    subject_type TEXT,
    subject_id INTEGER,
    subject_title TEXT,
    ip TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
try { $p->exec("ALTER TABLE articles ADD COLUMN scheduled_at DATETIME"); } catch(Exception $e) {}
echo "Fatto\n";
