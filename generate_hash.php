<?php
// Plik: generate_admin_hash.php (tymczasowy)
require_once __DIR__ . '/classes/User.php'; // Upewnij się, że ścieżka jest poprawna

// Ustaw login i hasło dla nowego administratora
$adminEmail = 'nowyadmin@example.com'; // Zmień na wybrany email
$adminPassword = 'admin123';   // ZMIEŃ NA SILNE HASŁO!

$hashedPassword = User::hashPassword($adminPassword);

echo "Email administratora: " . htmlspecialchars($adminEmail) . "<br>";
echo "Hasło (czysty tekst - tylko do zapamiętania, NIE ZAPISUJ W KODZIE!): " . htmlspecialchars($adminPassword) . "<br>";
echo "Zahashowane hasło (do wklejenia w SQL): <textarea rows='3' cols='70' readonly>" . htmlspecialchars($hashedPassword) . "</textarea>";

// Skrypt SQL do wklejenia:
echo "<hr><h3>Skrypt SQL do wklejenia (zastąp hash jeśli generujesz ponownie):</h3>";
echo "<pre>";
echo htmlspecialchars("INSERT INTO `users` (`name`, `email`, `password_hash`, `role`) VALUES\n");
echo htmlspecialchars("('Nowy Administrator', '{$adminEmail}', '{$hashedPassword}', 'admin');");
echo "</pre>";
?>