<?php
// Plik: classes/Participant.php

class Participant {
    public ?int $participant_id = null;
    public int $user_id;
    public int $session_id;
    public string $joined_at; // Automatycznie ustawiane przez bazę danych

    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Wypełnia właściwości obiektu na podstawie danych z tablicy.
     */
    private function populate(array $data): void {
        $this->participant_id = (int)$data['participant_id'];
        $this->user_id = (int)$data['user_id'];
        $this->session_id = (int)$data['session_id'];
        $this->joined_at = $data['joined_at'];
    }

    /**
     * Pozwala użytkownikowi dołączyć do sesji głosowania.
     *
     * @param int $userId ID użytkownika.
     * @param int $sessionId ID sesji.
     * @return Participant|false Obiekt Participant jeśli dołączenie się powiodło, false w przeciwnym razie (np. błąd SQL).
     *                           Można by też rzucać wyjątki dla bardziej szczegółowej obsługi błędów.
     */
    public function joinSession(int $userId, int $sessionId): Participant|false {
        // Sprawdzenie, czy użytkownik już nie dołączył, można zrobić przed wywołaniem tej metody
        // za pomocą isUserInSession(), aby uniknąć zbędnego zapytania INSERT, które by się nie powiodło
        // z powodu ograniczenia UNIQUE (user_id, session_id) w bazie.

        $sql = "INSERT INTO participants (user_id, session_id) VALUES (:user_id, :session_id)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':session_id', $sessionId, PDO::PARAM_INT);

        try {
            if ($stmt->execute()) {
                $participantId = (int)$this->pdo->lastInsertId();
                // Pobierz pełne dane utworzonego uczestnika (w tym joined_at)
                return self::findById($this->pdo, $participantId);
            }
        } catch (PDOException $e) {
            // Kod błędu 23000 to naruszenie ograniczenia integralności (np. UNIQUE constraint)
            if ($e->getCode() == '23000') {
                error_log("Użytkownik ID: {$userId} już dołączył do sesji ID: {$sessionId} lub inne naruszenie UNIQUE.");
                return false; // Jawnie zwracamy false, bo użytkownik już jest lub inny błąd UNIQUE
            }
            error_log("Błąd PDO podczas dołączania do sesji: " . $e->getMessage());
            // W bardziej rozbudowanym systemie można by rzucić tutaj konkretny wyjątek
        }
        return false;
    }

    /**
     * Sprawdza, czy dany użytkownik jest już uczestnikiem danej sesji.
     *
     * @param PDO $pdo Instancja PDO.
     * @param int $userId ID użytkownika.
     * @param int $sessionId ID sesji.
     * @return bool True jeśli użytkownik jest uczestnikiem, false w przeciwnym razie.
     */
    public static function isUserInSession(PDO $pdo, int $userId, int $sessionId): bool {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM participants WHERE user_id = :user_id AND session_id = :session_id");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':session_id', $sessionId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Znajduje uczestnika po jego ID.
     *
     * @param PDO $pdo Instancja PDO.
     * @param int $participantId ID uczestnika.
     * @return Participant|null Obiekt Participant lub null, jeśli nie znaleziono.
     */
    public static function findById(PDO $pdo, int $participantId): ?Participant {
        $stmt = $pdo->prepare("SELECT * FROM participants WHERE participant_id = :participant_id");
        $stmt->bindParam(':participant_id', $participantId, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $participant = new self($pdo);
            $participant->populate($data);
            return $participant;
        }
        return null;
    }

    /**
     * Znajduje wpis uczestnika na podstawie ID użytkownika i ID sesji.
     *
     * @param PDO $pdo Instancja PDO.
     * @param int $userId ID użytkownika.
     * @param int $sessionId ID sesji.
     * @return Participant|null Obiekt Participant lub null, jeśli nie znaleziono.
     */
    public static function findByUserAndSession(PDO $pdo, int $userId, int $sessionId): ?Participant {
        $stmt = $pdo->prepare("SELECT * FROM participants WHERE user_id = :user_id AND session_id = :session_id");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':session_id', $sessionId, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $participant = new self($pdo);
            $participant->populate($data);
            return $participant;
        }
        return null;
    }

    /**
     * Pobiera listę wszystkich uczestników (ich obiektów) dla danej sesji.
     *
     * @param PDO $pdo Instancja PDO.
     * @param int $sessionId ID sesji.
     * @return array Tablica obiektów Participant.
     */
    public static function getParticipantsBySession(PDO $pdo, int $sessionId): array {
        $stmt = $pdo->prepare("SELECT p.*, u.name as user_name, u.email as user_email FROM participants p JOIN users u ON p.user_id = u.user_id WHERE p.session_id = :session_id");
        $stmt->bindParam(':session_id', $sessionId, PDO::PARAM_INT);
        $stmt->execute();
        
        $participants = [];
        // Można by rozważyć dodanie user_name i user_email do obiektu Participant,
        // albo zwracać tablicę tablic asocjacyjnych, jeśli dane użytkownika są potrzebne tylko tutaj.
        // Na razie zwracamy obiekty Participant, a dane usera można by dociągnąć osobno.
        // Alternatywnie, jeśli klasa Participant miałaby właściwości user_name, user_email:
        // $participantsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // foreach ($participantsData as $data) {
        //    $p = new self($pdo);
        //    $p->populate($data);
        //    $p->user_name = $data['user_name']; // Przykład jeśli dodamy takie pole
        //    $participants[] = $p;
        // }
        // Na razie prościej, fetchAll z PDO::FETCH_CLASS
        // Aby to zadziałało poprawnie, konstruktor Participant nie powinien wymagać $pdo jako argumentu
        // lub PDO::FETCH_CLASS musi być użyte z PDO::FETCH_PROPS_LATE i __set()
        // Dla prostoty, iterujemy:
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $participant = new self($pdo);
            $participant->populate($row);
            // Możemy rozszerzyć obiekt Participant o dane użytkownika, jeśli potrzebne
            // $participant->userName = $row['user_name']; 
            // $participant->userEmail = $row['user_email'];
            $participants[] = $participant;
        }
        return $participants;
    }
}
?>