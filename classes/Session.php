<?php
// Plik: classes/Session.php
// Nazwa klasy: VotingSession

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/Resolution.php';
require_once __DIR__ . '/Vote.php';
require_once __DIR__ . '/Participant.php';

class VotingSession {
    public ?int $session_id = null;
    public string $code;
    public string $title;
    public string $created_at;
    public int $created_by;
    public string $status;

    private PDO $pdo;
    private array $_resolutions = [];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    private function populate(array $data): void {
        $this->session_id = (int)$data['session_id'];
        $this->code = $data['code'];
        $this->title = $data['title'];
        $this->created_at = $data['created_at'];
        $this->created_by = (int)$data['created_by'];
        $this->status = $data['status'];
    }

    public function create(string $title, int $creatorId, array $resolutionsTexts): VotingSession|false {
        // ... (kod tej metody bez zmian - skrócono dla czytelności) ...
        if (empty(trim($title))) { error_log("Błąd tworzenia sesji: Tytuł nie może być pusty."); return false; }
        if (empty($creatorId)) { error_log("Błąd tworzenia sesji: ID twórcy jest wymagane."); return false; }
        if (empty($resolutionsTexts)) { error_log("Błąd tworzenia sesji: Lista rezolucji nie może być pusta."); return false; }
        foreach ($resolutionsTexts as $text) { if (empty(trim($text))) { error_log("Błąd tworzenia sesji: Tekst rezolucji nie może być pusty."); return false; } }

        $generatedCode = $this->generateUniqueSessionCode();
        $this->pdo->beginTransaction();
        try {
            $sqlSession = "INSERT INTO sessions (code, title, created_by, status) VALUES (:code, :title, :created_by, :status)";
            $stmtSession = $this->pdo->prepare($sqlSession);
            $status = 'open';
            $stmtSession->bindParam(':code', $generatedCode, PDO::PARAM_STR);
            $stmtSession->bindParam(':title', $title, PDO::PARAM_STR);
            $stmtSession->bindParam(':created_by', $creatorId, PDO::PARAM_INT);
            $stmtSession->bindParam(':status', $status, PDO::PARAM_STR);

            if (!$stmtSession->execute()) { $this->pdo->rollBack(); error_log("Błąd SQL: " . implode(", ", $stmtSession->errorInfo())); return false; }
            $sessionId = (int)$this->pdo->lastInsertId();
            if (!$sessionId) { $this->pdo->rollBack(); error_log("Brak ID sesji."); return false; }

            $resolutionHandler = new Resolution($this->pdo);
            foreach ($resolutionsTexts as $index => $resText) {
                if (!$resolutionHandler->create($sessionId, trim($resText), $index + 1)) {
                    $this->pdo->rollBack(); error_log("Błąd tworzenia rezolucji."); return false;
                }
            }
            $this->pdo->commit();
            return self::findById($this->pdo, $sessionId);
        } catch (PDOException $e) { $this->pdo->rollBack(); error_log("PDOException: " . $e->getMessage()); return false;
        } catch (Exception $e) { $this->pdo->rollBack(); error_log("Exception: " . $e->getMessage()); return false; }
    }

    private function generateUniqueSessionCode(int $length = 6): string {
        // ... (kod tej metody bez zmian) ...
        do {
            $code = generateSessionCode($length);
            $stmt = $this->pdo->prepare("SELECT session_id FROM sessions WHERE code = :code");
            $stmt->bindParam(':code', $code, PDO::PARAM_STR); $stmt->execute();
        } while ($stmt->fetchColumn());
        return $code;
    }

    public static function findById(PDO $pdo, int $sessionId): ?VotingSession {
        // ... (kod tej metody bez zmian) ...
        $stmt = $pdo->prepare("SELECT * FROM sessions WHERE session_id = :session_id");
        $stmt->bindParam(':session_id', $sessionId, PDO::PARAM_INT); $stmt->execute();
        $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($sessionData) { $session = new self($pdo); $session->populate($sessionData); return $session; }
        return null;
    }

    public static function findByCode(PDO $pdo, string $code): ?VotingSession {
        // ... (kod tej metody bez zmian) ...
        $stmt = $pdo->prepare("SELECT * FROM sessions WHERE code = :code");
        $stmt->bindParam(':code', $code, PDO::PARAM_STR); $stmt->execute();
        $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($sessionData) { $session = new self($pdo); $session->populate($sessionData); return $session; }
        return null;
    }

    public function close(): bool {
        // ... (kod tej metody bez zmian) ...
        if (!$this->session_id) { error_log("Próba zamknięcia niezaładowanej sesji."); return false; }
        if ($this->status === 'closed') { return true; }
        $sql = "UPDATE sessions SET status = 'closed' WHERE session_id = :session_id AND status = 'open'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':session_id', $this->session_id, PDO::PARAM_INT);
        try {
            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) { $this->status = 'closed'; return true; }
                $currentStatusInDb = $this->pdo->query("SELECT status FROM sessions WHERE session_id = {$this->session_id}")->fetchColumn();
                if ($currentStatusInDb === 'closed') { $this->status = 'closed'; return true; }
                error_log("Nie zamknięto sesji ID: {$this->session_id}."); return false;
            }
        } catch (PDOException $e) { error_log("PDOException przy zamykaniu sesji: " . $e->getMessage()); }
        return false;
    }

    public static function getActiveSessions(PDO $pdo): array {
        // ... (kod tej metody bez zmian) ...
        $stmt = $pdo->prepare("SELECT * FROM sessions WHERE status = 'open' ORDER BY created_at DESC");
        $stmt->execute(); $sessions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $session = new self($pdo); $session->populate($row); $sessions[] = $session; }
        return $sessions;
    }

    public static function getClosedSessions(PDO $pdo): array {
        // ... (kod tej metody bez zmian) ...
        $stmt = $pdo->prepare("SELECT * FROM sessions WHERE status = 'closed' ORDER BY created_at DESC");
        $stmt->execute(); $sessions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $session = new self($pdo); $session->populate($row); $sessions[] = $session; }
        return $sessions;
    }
    
    public static function getAllSessions(PDO $pdo): array {
        // ... (kod tej metody bez zmian) ...
        $stmt = $pdo->prepare("SELECT * FROM sessions ORDER BY created_at DESC");
        $stmt->execute(); $sessions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $session = new self($pdo); $session->populate($row); $sessions[] = $session; }
        return $sessions;
    }

    public function getResolutions(): array {
        // ... (kod tej metody bez zmian) ...
        if (empty($this->_resolutions) && $this->session_id !== null) {
            $this->_resolutions = Resolution::getBySessionId($this->pdo, $this->session_id);
        }
        return $this->_resolutions;
    }

    public function getPublicData(?int $participantId = null): array {
        // ... (kod tej metody bez zmian) ...
        $resolutionsData = [];
        $this->getResolutions();
        foreach ($this->_resolutions as $resolution) {
            $resData = [
                'resolution_id' => $resolution->resolution_id,
                'text' => $resolution->text,
                'number' => $resolution->number,
                'voted_choice' => null
            ];
            if ($participantId !== null && $resolution->resolution_id !== null) {
                $vote = Vote::getVoteByParticipantAndResolution($this->pdo, $participantId, $resolution->resolution_id);
                if ($vote) { $resData['voted_choice'] = $vote->choice; }
            }
            $resolutionsData[] = $resData;
        }
        return [
            'session_id' => $this->session_id, 'code' => $this->code, 'title' => $this->title,
            'status' => $this->status, 'created_at' => $this->created_at,
            'resolutions' => $resolutionsData,
        ];
    }

    public function getSessionProgress(): array|false {
        // ... (kod tej metody bez zmian, jak w poprzedniej odpowiedzi) ...
        if ($this->session_id === null) {
            error_log("Próba pobrania postępu dla niezaładowanej sesji.");
            return false;
        }
        $stmtParticipants = $this->pdo->prepare("SELECT COUNT(*) FROM participants WHERE session_id = :session_id");
        $stmtParticipants->bindParam(':session_id', $this->session_id, PDO::PARAM_INT);
        $stmtParticipants->execute();
        $totalJoinedParticipants = (int)$stmtParticipants->fetchColumn();
        $resolutions = $this->getResolutions();
        $totalResolutions = count($resolutions);
        $stmtVotedOnce = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT v.participant_id) FROM votes v
             JOIN resolutions r ON v.resolution_id = r.resolution_id
             WHERE r.session_id = :session_id"
        );
        $stmtVotedOnce->bindParam(':session_id', $this->session_id, PDO::PARAM_INT);
        $stmtVotedOnce->execute();
        $participantsVotedAtLeastOnce = (int)$stmtVotedOnce->fetchColumn();
        $participantsVotedOnAll = 0;
        if ($totalResolutions > 0) {
            $stmtVotedAll = $this->pdo->prepare(
                "SELECT v.participant_id, COUNT(DISTINCT v.resolution_id) as voted_resolutions
                 FROM votes v
                 JOIN resolutions r ON v.resolution_id = r.resolution_id
                 WHERE r.session_id = :session_id
                 GROUP BY v.participant_id
                 HAVING COUNT(DISTINCT v.resolution_id) = :total_resolutions"
            );
            $stmtVotedAll->bindParam(':session_id', $this->session_id, PDO::PARAM_INT);
            $stmtVotedAll->bindParam(':total_resolutions', $totalResolutions, PDO::PARAM_INT);
            $stmtVotedAll->execute();
            $participantsVotedOnAll = (int)$stmtVotedAll->rowCount();
        }
        $stmtTotalVotes = $this->pdo->prepare(
            "SELECT COUNT(v.vote_id) FROM votes v
             JOIN resolutions r ON v.resolution_id = r.resolution_id
             WHERE r.session_id = :session_id"
        );
        $stmtTotalVotes->bindParam(':session_id', $this->session_id, PDO::PARAM_INT);
        $stmtTotalVotes->execute();
        $totalVotesCasted = (int)$stmtTotalVotes->fetchColumn();
        return [
            'session_id' => $this->session_id, 'title' => $this->title, 'status' => $this->status,
            'total_resolutions' => $totalResolutions,
            'total_joined_participants' => $totalJoinedParticipants,
            'participants_voted_at_least_once' => $participantsVotedAtLeastOnce,
            'participants_voted_on_all_resolutions' => $participantsVotedOnAll,
            'total_votes_casted' => $totalVotesCasted,
            'expected_total_votes' => ($totalResolutions > 0 && $totalJoinedParticipants > 0) ? ($totalJoinedParticipants * $totalResolutions) : 0
        ];
    }

    /**
     * Pobiera i oblicza wyniki głosowania dla bieżącej sesji.
     * (NOWA METODA - DODAJ JĄ)
     * @return array|false Ustrukturyzowane wyniki głosowania lub false, jeśli sesja nie jest załadowana.
     */
    public function getResults(): array|false {
        if ($this->session_id === null) {
            error_log("Próba pobrania wyników dla niezaładowanej sesji.");
            return false;
        }

        // Pobierz wszystkie rezolucje dla tej sesji (upewnij się, że są załadowane)
        $resolutions = $this->getResolutions();
        if (empty($resolutions)) {
            // Jeśli nie ma rezolucji, nie ma wyników do obliczenia
            return [
                'session_id' => $this->session_id,
                'title' => $this->title,
                'code' => $this->code,
                'status' => $this->status,
                'created_at' => $this->created_at,
                'resolutions_results' => [],
                'total_participants' => 0 // Można by to też pobrać, ale nie jest kluczowe, jeśli nie ma rezolucji
            ];
        }

        // Pobierz wszystkie głosy dla tej sesji
        $allVotesInSession = Vote::getVotesBySession($this->pdo, $this->session_id);

        // Oblicz zagregowane wyniki dla każdej rezolucji
        $calculatedResults = Vote::calculateResultsForResolutions($this->pdo, $resolutions, $allVotesInSession);

        $outputResults = [];
        foreach ($resolutions as $resolution) {
            if ($resolution->resolution_id === null) continue; // Na wszelki wypadek
            
            $outputResults[] = [
                'resolution_id' => $resolution->resolution_id,
                'text' => $resolution->text,
                'number' => $resolution->number,
                'results' => $calculatedResults[$resolution->resolution_id] ?? [ // Domyślne wyniki, jeśli brak głosów na rezolucję
                    Vote::CHOICE_YES => 0,
                    Vote::CHOICE_NO => 0,
                    Vote::CHOICE_ABSTAIN => 0,
                    'total_votes' => 0
                ]
            ];
        }
        
        // Przygotuj informacje o sesji
        $sessionInfo = [
            'session_id' => $this->session_id,
            'title' => $this->title,
            'code' => $this->code,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'resolutions_results' => $outputResults
        ];
        
        // Dodaj informację o liczbie uczestników sesji
        $stmtParticipants = $this->pdo->prepare("SELECT COUNT(*) FROM participants WHERE session_id = :session_id");
        $stmtParticipants->bindParam(':session_id', $this->session_id, PDO::PARAM_INT);
        $stmtParticipants->execute();
        $sessionInfo['total_participants_in_session'] = (int) $stmtParticipants->fetchColumn();

        return $sessionInfo;
    }
}
?>