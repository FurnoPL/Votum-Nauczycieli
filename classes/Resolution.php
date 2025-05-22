<?php
// Plik: classes/Resolution.php

class Resolution {
    public ?int $resolution_id;
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
        
        // $stmt->bindParam(':number', $number, PDO::PARAM_INT_OR_NULL); // ZAKOMENTUJ LUB USUŃ TĘ LINIĘ

        // Starsze PDO mogą nie wspierać PDO::PARAM_INT_OR_NULL, alternatywa:
        if ($number === null) {
            $stmt->bindValue(':number', null, PDO::PARAM_NULL); // ODKOMENTUJ TĘ LINIĘ
        } else {
            $stmt->bindParam(':number', $number, PDO::PARAM_INT); // ODKOMENTUJ TĘ LINIĘ
        }


        if ($stmt->execute()) {
            return (int)$this->pdo->lastInsertId();
        }
        error_log("Błąd SQL podczas tworzenia rezolucji: " . implode(", ", $stmt->errorInfo())); // Dodaj logowanie błędów SQL
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
        
        // Upewnij się, że przekazujesz $pdo do konstruktora, jeśli jest potrzebny
        // W tym przypadku Resolution nie używa $pdo w konstruktorze, jeśli jest tworzony przez FETCH_CLASS
        // ale jeśli by używał, to: return $stmt->fetchAll(PDO::FETCH_CLASS, self::class, [$pdo]);
        // Jeśli konstruktor nie wymaga $pdo przy FETCH_CLASS, to po prostu:
        $resolutions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resolution = new self($pdo); // Potrzebujemy $pdo dla operacji na obiekcie
            $resolution->resolution_id = (int)$row['resolution_id'];
            $resolution->session_id = (int)$row['session_id'];
            $resolution->text = $row['text'];
            $resolution->number = $row['number'] !== null ? (int)$row['number'] : null;
            $resolutions[] = $resolution;
        }
        return $resolutions;
        // Lub jeśli konstruktor jest prosty i ustawia tylko $pdo:
        // return $stmt->fetchAll(PDO::FETCH_CLASS, self::class, [$pdo]);
        // Ale wtedy musisz ręcznie ustawić pozostałe właściwości lub mieć metodę `populate`.
        // Aktualnie twój konstruktor Resolution przyjmuje $pdo, więc FETCH_CLASS z $pdo jest ok,
        // ale musisz się upewnić, że dane są poprawnie mapowane.
        // Bezpieczniejsza opcja to iteracja i ręczne tworzenie obiektów jak wyżej.
    }
}
?>