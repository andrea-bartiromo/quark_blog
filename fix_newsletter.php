<?php
$p = new PDO('sqlite:database/database.sqlite');
try {
    $p->exec('ALTER TABLE newsletter ADD COLUMN unsubscribe_token TEXT');
    echo "Colonna aggiunta\n";
} catch(Exception $e) {
    echo "Gia esistente\n";
}
$subs = $p->query('SELECT id FROM newsletter WHERE unsubscribe_token IS NULL')->fetchAll(PDO::FETCH_COLUMN);
$stmt = $p->prepare('UPDATE newsletter SET unsubscribe_token = ? WHERE id = ?');
foreach($subs as $id) {
    $stmt->execute([bin2hex(random_bytes(16)), $id]);
}
echo "Token generati per " . count($subs) . " iscritti\n";
