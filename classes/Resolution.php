<?php
// Plik: classes/Resolution.php

class Resolution {
    public ?int $resolution_id = null;
    public int $session_id;
    public string $text;
    public ?int $number; 
    public string $voting_status = 'pending'; // NOWA WŁAŚCIWOŚĆ z wartością domyślną

    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // Metoda do wypełniania obiektu danymi z bazy
    private function populate(array $data): void {
        $this->resolution_id = (int)$data['resolution_id'];
        $this->session_id = (int)$data['session_id'];
        $this->text = $data['text'];
        $this->number = $data['number'] !== null ? (int)$data['number'] : null;
        $this->voting_status = $data['voting_status'] ?? 'pending'; // Pobierz status głosowania
    }

    public function create(int $sessionId, string $text, ?int $number = null): int|false {
        // voting_status będzie domyślnie 'pending' dzięki definicji w bazie
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
            $this->resolution_id = (int)$this->pdo->lastInsertId();
            $this->session_id = $sessionId;
            $this->text = $text;
            $this->number = $number;
            $this->voting_status = 'pending'; // Ustaw też w obiekcie
            return $this->resolution_id;
        }
        error_log("Błąd SQL podczas tworzenia rezolucji dla sesji ID {$sessionId}: " . implode(", ", $stmt->errorInfo()));
        return false;
    }

    public static function getBySessionId(PDO $pdo, int $sessionId): array {
        // Pobieramy teraz także voting_status
        $stmt = $pdo->prepare("SELECT * FROM resolutions WHERE session_id = :session_id ORDER BY number ASC, resolution_id ASC");
        $stmt->bindParam(':session_id', $sessionId, PDO::PARAM_INT);
        $stmt->execute();
        
        $resolutions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resolution = new self($pdo); 
            $resolution->populate($row); // Użyj populate
            $resolutions[] = $resolution;
        }
        return $resolutions;
    }

    public static function findById(PDO $pdo, int $resolutionId): ?Resolution {
        $stmt = $pdo->prepare("SELECT * FROM resolutions WHERE resolution_id = :resolution_id");
        $stmt->bindParam(':resolution_id', $resolutionId, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) {
            $resolution = new self($pdo);
            $resolution->populate($data);
            return $resolution;
        }
        return null;
    }

    public function updateVotingStatus(string $newStatus): bool {
        if (!in_array($newStatus, ['pending', 'active', 'closed'])) {
            error_log("Nieprawidłowy status głosowania rezolucji: {$newStatus}");
            return false;
        }
        if ($this->resolution_id === null) {
            error_log("Nie można zaktualizować statusu niezapisanej rezolucji.");
            return false;
        }

        // Dodatkowa logika: Jeśli ustawiamy na 'active', upewnijmy się, że żadna inna uchwała w tej sesji nie jest 'active'
        if ($newStatus === 'active') {
            $sqlCheck = "UPDATE resolutions SET voting_status = 'pending' 
                         WHERE session_id = :session_id AND voting_status = 'active' AND resolution_id != :current_resolution_id";
            $stmtCheck = $this->pdo->prepare($sqlCheck);
            $stmtCheck->bindParam(':session_id', $this->session_id, PDO::PARAM_INT);
            $stmtCheck->bindParam(':current_resolution_id', $this->resolution_id, PDO::PARAM_INT);
            $stmtCheck->execute(); // Nie przejmujemy się wynikiem, to tylko reset
        }


        $sql = "UPDATE resolutions SET voting_status = :voting_status WHERE resolution_id = :resolution_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':voting_status', $newStatus, PDO::PARAM_STR);
        $stmt->bindParam(':resolution_id', $this->resolution_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $this->voting_status = $newStatus;
            return true;
        }
        error_log("Błąd SQL podczas aktualizacji statusu głosowania rezolucji ID {$this->resolution_id}: " . implode(", ", $stmt->errorInfo()));
        return false;
    }
}
?>