<?php
// Plik: classes/Session.php

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
    public ?string $closed_at = null; // NOWA WŁAŚCIWOŚĆ

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
        $this->closed_at = $data['closed_at']; // POBIERZ NOWĄ WŁAŚCIWOŚĆ
    }

    public function create(string $title, int $creatorId, array $resolutionsTexts): VotingSession|false {
        // ... (kod metody create bez zmian w logice, tylko upewnij się, że status jest 'open' a closed_at jest NULL przy INSERT)
        if (empty(trim($title))) { error_log("Błąd tworzenia sesji: Tytuł nie może być pusty."); return false; }
        if (empty($creatorId)) { error_log("Błąd tworzenia sesji: ID twórcy jest wymagane."); return false; }
        if (empty($resolutionsTexts)) { error_log("Błąd tworzenia sesji: Lista rezolucji nie może być pusta."); return false; }
        foreach ($resolutionsTexts as $text) { if (empty(trim($text))) { error_log("Błąd tworzenia sesji: Tekst rezolucji nie może być pusty."); return false; } }

        $generatedCode = $this->generateUniqueSessionCode();
        $this->pdo->beginTransaction();
        try {
            // Upewnij się, że closed_at nie jest ustawiane przy tworzeniu (domyślnie NULL)
            $sqlSession = "INSERT INTO sessions (code, title, created_by, status) VALUES (:code, :title, :created_by, :status)";
            $stmtSession = $this->pdo->prepare($sqlSession);
            $currentStatus = 'open';
            $stmtSession->bindParam(':code', $generatedCode, PDO::PARAM_STR);
            $stmtSession->bindParam(':title', $title, PDO::PARAM_STR);
            $stmtSession->bindParam(':created_by', $creatorId, PDO::PARAM_INT);
            $stmtSession->bindParam(':status', $currentStatus, PDO::PARAM_STR);

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
        // ... (bez zmian)
        do {
            $code = generateSessionCode($length);
            $stmt = $this->pdo->prepare("SELECT session_id FROM sessions WHERE code = :code");
            $stmt->bindParam(':code', $code, PDO::PARAM_STR); $stmt->execute();
        } while ($stmt->fetchColumn());
        return $code;
    }

    public static function findById(PDO $pdo, int $sessionId): ?VotingSession {
        // Zapytanie powinno teraz pobierać również closed_at
        $stmt = $pdo->prepare("SELECT * FROM sessions WHERE session_id = :session_id");
        $stmt->bindParam(':session_id', $sessionId, PDO::PARAM_INT); $stmt->execute();
        $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($sessionData) { $session = new self($pdo); $session->populate($sessionData); return $session; }
        return null;
    }

    public static function findByCode(PDO $pdo, string $code): ?VotingSession {
        // Zapytanie powinno teraz pobierać również closed_at
        $stmt = $pdo->prepare("SELECT * FROM sessions WHERE code = :code");
        $stmt->bindParam(':code', $code, PDO::PARAM_STR); $stmt->execute();
        $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($sessionData) { $session = new self($pdo); $session->populate($sessionData); return $session; }
        return null;
    }

    public function close(): bool {
        if (!$this->session_id) { error_log("Próba zamknięcia niezaładowanej sesji."); return false; }
        if ($this->status === 'closed') { return true; }
        
        // ZAKTUALIZOWANY SQL, ABY USTAWIAĆ closed_at
        $sql = "UPDATE sessions SET status = 'closed', closed_at = CURRENT_TIMESTAMP WHERE session_id = :session_id AND status = 'open'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':session_id', $this->session_id, PDO::PARAM_INT);
        try {
            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) { 
                    $this->status = 'closed'; 
                    // Pobierz i ustaw closed_at dla obiektu, aby było spójne
                    $updatedSessionData = self::findById($this->pdo, $this->session_id);
                    if ($updatedSessionData) $this->closed_at = $updatedSessionData->closed_at;
                    return true; 
                }
                // Sprawdź, czy nie została zamknięta przez inny proces
                $currentStatusInDb = $this->pdo->query("SELECT status, closed_at FROM sessions WHERE session_id = {$this->session_id}")->fetch(PDO::FETCH_ASSOC);
                if ($currentStatusInDb && $currentStatusInDb['status'] === 'closed') { 
                    $this->status = 'closed'; 
                    $this->closed_at = $currentStatusInDb['closed_at'];
                    return true; 
                }
                error_log("Nie zamknięto sesji ID: {$this->session_id}. Być może już była zamknięta lub wystąpił błąd."); return false;
            }
        } catch (PDOException $e) { error_log("PDOException przy zamykaniu sesji: " . $e->getMessage()); }
        return false;
    }

    public static function getActiveSessions(PDO $pdo): array {
        // Zapytanie powinno teraz pobierać również closed_at
        $stmt = $pdo->prepare("SELECT * FROM sessions WHERE status = 'open' ORDER BY created_at DESC");
        $stmt->execute(); $sessions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $session = new self($pdo); $session->populate($row); $sessions[] = $session; }
        return $sessions;
    }

    public static function getClosedSessions(PDO $pdo): array {
        // Zapytanie powinno teraz pobierać również closed_at
        $stmt = $pdo->prepare("SELECT * FROM sessions WHERE status = 'closed' ORDER BY closed_at DESC, created_at DESC"); // Sortuj po dacie zamknięcia
        $stmt->execute(); $sessions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $session = new self($pdo); $session->populate($row); $sessions[] = $session; }
        return $sessions;
    }
    
    public static function getAllSessions(PDO $pdo): array {
        // Zapytanie powinno teraz pobierać również closed_at
        $stmt = $pdo->prepare("SELECT * FROM sessions ORDER BY created_at DESC");
        $stmt->execute(); $sessions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $session = new self($pdo); $session->populate($row); $sessions[] = $session; }
        return $sessions;
    }

    public function getResolutions(): array {
        // ... (bez zmian)
        if (empty($this->_resolutions) && $this->session_id !== null) {
            $this->_resolutions = Resolution::getBySessionId($this->pdo, $this->session_id);
        }
        return $this->_resolutions;
    }

    public function getPublicData(?int $participantId = null): array {
        // ... (kod tej metody bez zmian, ale dzięki populate() $this->closed_at będzie dostępne)
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
            'closed_at' => $this->closed_at, // DODANO closed_at do zwracanych danych
            'resolutions' => $resolutionsData,
        ];
    }

    public function getSessionProgress(): array|false {
        // ... (bez zmian w logice, ale $this->closed_at będzie dostępne)
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
            'created_at' => $this->created_at, 'closed_at' => $this->closed_at, // DODANO closed_at
            'total_resolutions' => $totalResolutions,
            'total_joined_participants' => $totalJoinedParticipants,
            'participants_voted_at_least_once' => $participantsVotedAtLeastOnce,
            'participants_voted_on_all_resolutions' => $participantsVotedOnAll,
            'total_votes_casted' => $totalVotesCasted,
            'expected_total_votes' => ($totalResolutions > 0 && $totalJoinedParticipants > 0) ? ($totalJoinedParticipants * $totalResolutions) : 0
        ];
    }
    
    public function getResults(): array|false {
        // ... (bez zmian w logice, ale $this->closed_at będzie dostępne)
        if ($this->session_id === null) {
            error_log("Próba pobrania wyników dla niezaładowanej sesji.");
            return false;
        }
        $resolutions = $this->getResolutions();
        if (empty($resolutions)) {
            return [
                'session_id' => $this->session_id, 'title' => $this->title, 'code' => $this->code,
                'status' => $this->status, 'created_at' => $this->created_at, 'closed_at' => $this->closed_at, // DODANO closed_at
                'resolutions_results' => [], 'total_participants_in_session' => 0
            ];
        }
        $allVotesInSession = Vote::getVotesBySession($this->pdo, $this->session_id);
        $calculatedResults = Vote::calculateResultsForResolutions($this->pdo, $resolutions, $allVotesInSession);
        $outputResults = [];
        foreach ($resolutions as $resolution) {
            if ($resolution->resolution_id === null) continue; 
            $outputResults[] = [
                'resolution_id' => $resolution->resolution_id, 'text' => $resolution->text, 'number' => $resolution->number,
                'results' => $calculatedResults[$resolution->resolution_id] ?? [
                    Vote::CHOICE_YES => 0, Vote::CHOICE_NO => 0, Vote::CHOICE_ABSTAIN => 0, 'total_votes' => 0
                ]
            ];
        }
        $sessionInfo = [
            'session_id' => $this->session_id, 'title' => $this->title, 'code' => $this->code,
            'status' => $this->status, 'created_at' => $this->created_at, 'closed_at' => $this->closed_at, // DODANO closed_at
            'resolutions_results' => $outputResults
        ];
        $stmtParticipants = $this->pdo->prepare("SELECT COUNT(*) FROM participants WHERE session_id = :session_id");
        $stmtParticipants->bindParam(':session_id', $this->session_id, PDO::PARAM_INT);
        $stmtParticipants->execute();
        $sessionInfo['total_participants_in_session'] = (int) $stmtParticipants->fetchColumn();
        return $sessionInfo;
    }
}
?>