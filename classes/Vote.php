<?php
// Plik: classes/Vote.php

class Vote {
    public ?int $vote_id = null;
    public int $resolution_id;
    public string $session_php_id; // ZMIANA: Zamiast participant_id
    public string $choice;     
    public string $voted_at;   

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
     * Zapisuje lub aktualizuje głos dla danej sesji PHP na daną rezolucję.
     *
     * @param int $resolutionId ID rezolucji.
     * @param string $sessionPhpId ID sesji PHP.
     * @param string $choice Wybór ('tak', 'nie', 'wstrzymanie').
     * @return Vote|false Obiekt Vote jeśli głos został zapisany/zaktualizowany, false w przypadku błędu.
     */
    public function castOrUpdateVote(int $resolutionId, string $sessionPhpId, string $choice): Vote|false {
        if (!self::isValidChoice($choice)) {
            error_log("Próba oddania głosu z nieprawidłowym wyborem: {$choice} dla resolutionId: {$resolutionId}, session_php_id: {$sessionPhpId}");
            return false;
        }

        $existingVote = self::getVoteBySessionPhpIdAndResolution($this->pdo, $sessionPhpId, $resolutionId);

        if ($existingVote) {
            if ($existingVote->choice === $choice) {
                return $existingVote; 
            }

            $sql = "UPDATE votes SET choice = :choice, voted_at = CURRENT_TIMESTAMP 
                    WHERE vote_id = :vote_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':choice', $choice, PDO::PARAM_STR);
            $stmt->bindParam(':vote_id', $existingVote->vote_id, PDO::PARAM_INT);
            
            try {
                if ($stmt->execute()) {
                    return self::findById($this->pdo, $existingVote->vote_id); 
                }
            } catch (PDOException $e) {
                error_log("Błąd PDO podczas aktualizacji głosu (vote_id: {$existingVote->vote_id}): " . $e->getMessage());
                return false;
            }
        } else {
            $sql = "INSERT INTO votes (resolution_id, session_php_id, choice) 
                    VALUES (:resolution_id, :session_php_id, :choice)"; // ZMIANA: session_php_id
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':resolution_id', $resolutionId, PDO::PARAM_INT);
            $stmt->bindParam(':session_php_id', $sessionPhpId, PDO::PARAM_STR); // ZMIANA
            $stmt->bindParam(':choice', $choice, PDO::PARAM_STR);

            try {
                if ($stmt->execute()) {
                    $voteId = (int)$this->pdo->lastInsertId();
                    return self::findById($this->pdo, $voteId);
                }
            } catch (PDOException $e) {
                // Kod 23000 może wystąpić jeśli unikalny klucz (session_php_id, resolution_id) zostanie naruszony (np. wyścig)
                if ($e->getCode() == '23000') {
                     error_log("Naruszenie unikalności przy INSERT vote dla session_php_id: {$sessionPhpId}, resolutionId: {$resolutionId}. " . $e->getMessage());
                     // Spróbuj pobrać istniejący, jeśli to był wyścig
                     return self::getVoteBySessionPhpIdAndResolution($this->pdo, $sessionPhpId, $resolutionId);
                }
                error_log("Błąd PDO podczas zapisywania nowego głosu dla resolutionId: {$resolutionId}, session_php_id: {$sessionPhpId}: " . $e->getMessage());
                return false;
            }
        }
        return false; 
    }
    
    // castVote może być aliasem lub usunięte, castOrUpdateVote jest lepsze
    public function castVote(int $resolutionId, string $sessionPhpId, string $choice): Vote|false {
        return $this->castOrUpdateVote($resolutionId, $sessionPhpId, $choice);
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
            $vote->session_php_id = $data['session_php_id']; // ZMIANA
            $vote->choice = $data['choice'];
            $vote->voted_at = $data['voted_at'];
            return $vote;
        }
        return null;
    }

    /**
     * Pobiera głos dla danej sesji PHP i rezolucji.
     */
    public static function getVoteBySessionPhpIdAndResolution(PDO $pdo, string $sessionPhpId, int $resolutionId): ?Vote {
        $stmt = $pdo->prepare("SELECT * FROM votes WHERE session_php_id = :session_php_id AND resolution_id = :resolution_id"); // ZMIANA
        $stmt->bindParam(':session_php_id', $sessionPhpId, PDO::PARAM_STR); // ZMIANA
        $stmt->bindParam(':resolution_id', $resolutionId, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $vote = new self($pdo);
            $vote->vote_id = (int)$data['vote_id'];
            $vote->resolution_id = (int)$data['resolution_id'];
            $vote->session_php_id = $data['session_php_id']; // ZMIANA
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
            // populate z findById
            $vote->vote_id = (int)$row['vote_id'];
            $vote->resolution_id = (int)$row['resolution_id'];
            $vote->session_php_id = $row['session_php_id']; // ZMIANA
            $vote->choice = $row['choice'];
            $vote->voted_at = $row['voted_at'];
            $votes[] = $vote;
        }
        return $votes;
    }

    // calculateResultsForResolutions nie wymaga zmian, bo operuje na zagregowanych danych
    public static function calculateResultsForResolutions(PDO $pdo, array $resolutions, array $allVotesInSession): array {
        $results = [];
        foreach ($resolutions as $resolution) {
            if ($resolution->resolution_id === null) continue;
            $results[$resolution->resolution_id] = [
                self::CHOICE_YES => 0, self::CHOICE_NO => 0, self::CHOICE_ABSTAIN => 0, 'total_votes' => 0
            ];
        }
        foreach ($allVotesInSession as $vote) { // $vote to obiekt Vote
            if (isset($results[$vote->resolution_id]) && isset($results[$vote->resolution_id][$vote->choice])) {
                $results[$vote->resolution_id][$vote->choice]++;
                $results[$vote->resolution_id]['total_votes']++;
            }
        }
        return $results;
    }
}
?>