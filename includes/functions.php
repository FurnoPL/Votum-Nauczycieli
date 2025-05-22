<?php
// Plik: includes/functions.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generuje losowy, alfanumeryczny kod o określonej długości.
 *
 * @param int $length Długość kodu (domyślnie 8).
 * @return string Wygenerowany kod.
 */
function generateSessionCode(int $length = 8): string {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Pomocnicza funkcja do wysyłania odpowiedzi JSON.
 *
 * @param int $statusCode Kod statusu HTTP.
 * @param array $data Dane do wysłania.
 */
function sendJsonResponse(int $statusCode, array $data): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

?>