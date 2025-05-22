<?php
// Plik: classes/Vote.php

class Vote {
    public ?int $vote_id = null;
    public int $resolution_id;
    public int $participant_id; // ID z tabeli participants
    public string $choice;     // 'tak', 'nie', 'wstrzymanie'
    public string $voted_at;   // Automatycznie ustawiane przez bazę

    public const CHOICE_YES = 'tak';
    public const CHOICE_NO = 'nie';
    public const CHOICE_ABSTAIN = 'wstrzymanie';

    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public static function getAllowedChoices(): array {
        return [self::CHOICE_YES, self::CHOICE_NO, self::CHOICE_ABSTAIN];
    }

    public static function isValidChoice(string $choice): bool {
        return in_array($choice, self::getAllowedChoices());
    }

    /**
     * Zapisuje lub aktualizuje głos uczestnika na daną rezolucję.
     *
     * @param int $resolutionId ID rezolucji.
     * @param int $participantId ID uczestnika (z tabeli participants).
     * @param string $choice Wybór uczestnika ('tak', 'nie', 'wstrzymanie').
     * @return Vote|false Obiekt Vote jeśli głos został zapisany/zaktualizowany, false w przypadku błędu.
     */
    public function castOrUpdateVote(int $resolutionId, int $participantId, string $choice): Vote|false {
        if (!self::isValidChoice($choice)) {
            error_log("Próba oddania głosu z nieprawidłowym wyborem: {$choice} dla resolutionId: {$resolutionId}, participantId: {$participantId}");
            return false;
        }

        // Sprawdź, czy głos już istnieje
        $existingVote = self::getVoteByParticipantAndResolution($this->pdo, $participantId, $resolutionId);

        if ($existingVote) {
            // Głos istnieje, aktualizujemy go
            // Możemy sprawdzić, czy nowy wybór jest taki sam jak stary, aby uniknąć niepotrzebnego UPDATE
            if ($existingVote->choice === $choice) {
                return $existingVote; // Zwróć istniejący głos, nic się nie zmieniło
            }

            $sql = "UPDATE votes SET choice = :choice, voted_at = CURRENT_TIMESTAMP 
                    WHERE vote_id = :vote_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':choice', $choice, PDO::PARAM_STR);
            $stmt->bindParam(':vote_id', $existingVote->vote_id, PDO::PARAM_INT);
            
            try {
                if ($stmt->execute()) {
                    return self::findById($this->pdo, $existingVote->vote_id); // Zwróć zaktualizowany głos
                }
            } catch (PDOException $e) {
                error_log("Błąd PDO podczas aktualizacji głosu (vote_id: {$existingVote->vote_id}): " . $e->getMessage());
                return false;
            }
        } else {
            // Głos nie istnieje, tworzymy nowy
            $sql = "INSERT INTO votes (resolution_id, participant_id, choice) 
                    VALUES (:resolution_id, :participant_id, :choice)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':resolution_id', $resolutionId, PDO::PARAM_INT);
            $stmt->bindParam(':participant_id', $participantId, PDO::PARAM_INT);
            $stmt->bindParam(':choice', $choice, PDO::PARAM_STR);

            try {
                if ($stmt->execute()) {
                    $voteId = (int)$this->pdo->lastInsertId();
                    return self::findById($this->pdo, $voteId);
                }
            } catch (PDOException $e) {
                // Ten catch dla INSERT jest nadal ważny w razie innych problemów niż UNIQUE (choć UNIQUE tu nie powinien być problemem)
                error_log("Błąd PDO podczas zapisywania nowego głosu dla resolutionId: {$resolutionId}, participantId: {$participantId}: " . $e->getMessage());
                return false;
            }
        }
        return false; // Jeśli żadna ścieżka nie zwróciła obiektu Vote
    }

    // Metoda castVote może być teraz aliasem lub zostać zastąpiona
    // Dla spójności API, jeśli chcemy jawnie rozróżnić, można zostawić castVote dla pierwszego oddania
    // a dodać np. updateVote. Ale castOrUpdateVote jest bardziej elastyczne.
    // Zastąpmy starą metodę castVote nową logiką.
    public function castVote(int $resolutionId, int $participantId, string $choice): Vote|false {
        return $this->castOrUpdateVote($resolutionId, $participantId, $choice);
    }


    public static function findById(PDO $pdo, int $voteId): ?Vote {
        $stmt = $pdo->prepare("SELECT * FROM votes WHERE vote_id = :vote_id");
        $stmt->bindParam(':vote_id', $voteId, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $vote = new self($pdo);
            $vote->vote_id = (int)$data['vote_id'];
            $vote->resolution_id = (int)$data['resolution_id'];
            $vote->participant_id = (int)$data['participant_id'];
            $vote->choice = $data['choice'];
            $vote->voted_at = $data['voted_at'];
            return $vote;
        }
        return null;
    }

    public static function getVoteByParticipantAndResolution(PDO $pdo, int $participantId, int $resolutionId): ?Vote {
        $stmt = $pdo->prepare("SELECT * FROM votes WHERE participant_id = :participant_id AND resolution_id = :resolution_id");
        $stmt->bindParam(':participant_id', $participantId, PDO::PARAM_INT);
        $stmt->bindParam(':resolution_id', $resolutionId, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $vote = new self($pdo);
            $vote->vote_id = (int)$data['vote_id'];
            $vote->resolution_id = (int)$data['resolution_id'];
            $vote->participant_id = (int)$data['participant_id'];
            $vote->choice = $data['choice'];
            $vote->voted_at = $data['voted_at'];
            return $vote;
        }
        return null;
    }

    public static function getVotesBySession(PDO $pdo, int $sessionId): array {
        $sql = "SELECT v.* FROM votes v
                JOIN resolutions r ON v.resolution_id = r.resolution_id
                WHERE r.session_id = :session_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':session_id', $sessionId, PDO::PARAM_INT);
        $stmt->execute();
        
        $votes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $vote = new self($pdo);
            $vote->vote_id = (int)$row['vote_id'];
            $vote->resolution_id = (int)$row['resolution_id'];
            $vote->participant_id = (int)$row['participant_id'];
            $vote->choice = $row['choice'];
            $vote->voted_at = $row['voted_at'];
            $votes[] = $vote;
        }
        return $votes;
    }

    public static function calculateResultsForResolutions(PDO $pdo, array $resolutions, array $allVotesInSession): array {
        $results = [];
        foreach ($resolutions as $resolution) {
            if ($resolution->resolution_id === null) continue;
            $results[$resolution->resolution_id] = [
                self::CHOICE_YES => 0, self::CHOICE_NO => 0, self::CHOICE_ABSTAIN => 0, 'total_votes' => 0
            ];
        }
        foreach ($allVotesInSession as $vote) {
            if (isset($results[$vote->resolution_id]) && isset($results[$vote->resolution_id][$vote->choice])) {
                $results[$vote->resolution_id][$vote->choice]++;
                $results[$vote->resolution_id]['total_votes']++;
            }
        }
        return $results;
    }
}
?>