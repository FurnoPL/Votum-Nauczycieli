<?php
// Plik: classes/Resolution.php

class Resolution {
    public ?int $resolution_id = null; // Zmieniono z public ?int $resolution_id;
    public int $session_id;
    public string $text;
    public ?int $number; // Numer porządkowy, opcjonalny

    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Tworzy nową rezolucję w bazie danych.
     *
     * @param int $sessionId ID sesji, do której należy rezolucja.
     * @param string $text Treść rezolucji.
     * @param int|null $number Numer porządkowy (opcjonalnie).
     * @return int|false ID nowo utworzonej rezolucji lub false w przypadku błędu.
     */
    public function create(int $sessionId, string $text, ?int $number = null): int|false {
        $sql = "INSERT INTO resolutions (session_id, text, number) VALUES (:session_id, :text, :number)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':session_id', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':text', $text, PDO::PARAM_STR);
        
        if ($number === null) {
            $stmt->bindValue(':number', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':number', $number, PDO::PARAM_INT);
        }

        if ($stmt->execute()) {
            $this->resolution_id = (int)$this->pdo->lastInsertId(); // Ustaw ID w obiekcie
            // Można też załadować resztę danych do obiektu, jeśli potrzebne od razu
            $this->session_id = $sessionId;
            $this->text = $text;
            $this->number = $number;
            return $this->resolution_id;
        }
        error_log("Błąd SQL podczas tworzenia rezolucji dla sesji ID {$sessionId}: " . implode(", ", $stmt->errorInfo()));
        return false;
    }

    /**
     * Pobiera wszystkie rezolucje dla danej sesji.
     *
     * @param int $sessionId ID sesji.
     * @return array Tablica obiektów Resolution.
     */
    public static function getBySessionId(PDO $pdo, int $sessionId): array {
        $stmt = $pdo->prepare("SELECT * FROM resolutions WHERE session_id = :session_id ORDER BY number ASC, resolution_id ASC");
        $stmt->bindParam(':session_id', $sessionId, PDO::PARAM_INT);
        $stmt->execute();
        
        $resolutions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resolution = new self($pdo); 
            $resolution->resolution_id = (int)$row['resolution_id'];
            $resolution->session_id = (int)$row['session_id'];
            $resolution->text = $row['text'];
            $resolution->number = $row['number'] !== null ? (int)$row['number'] : null;
            $resolutions[] = $resolution;
        }
        return $resolutions;
    }
}
?>