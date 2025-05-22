<?php
// Plik: generate_hash.php (tymczasowy)
require_once __DIR__ . '/classes/User.php'; // Upewnij się, że ścieżka jest poprawna

$plainPassword = 'nauczyciel';
$hashedPassword = User::hashPassword($plainPassword);

echo "Hasło: " . $plainPassword . "<br>";
echo "Zahashowane hasło: " . htmlspecialchars($hashedPassword);
?>