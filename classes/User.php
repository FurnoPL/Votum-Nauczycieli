<?php
// Plik: classes/User.php

class User {
    public ?int $user_id = null;
    public string $name;
    public string $email;
    private string $password_hash; 
    public string $role; 

    private PDO $pdo; 

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    private function populate(array $data): void {
        $this->user_id = (int)$data['user_id'];
        $this->name = $data['name'];
        $this->email = $data['email'];
        $this->password_hash = $data['password_hash']; 
        $this->role = $data['role'];
    }

    public static function hashPassword(string $plainTextPassword): string {
        return password_hash($plainTextPassword, PASSWORD_DEFAULT);
    }

    private function verifyPassword(string $plainTextPassword, string $hashedPassword): bool {
        // Jeśli hash jest pusty (np. dla nauczycieli tworzonych bez hasła), nie weryfikuj
        if (empty($hashedPassword)) {
            return false; 
        }
        return password_verify($plainTextPassword, $hashedPassword);
    }

    public function login(string $email, string $password): array|false {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email AND role = 'admin'"); // Tylko admin może się logować
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userData && !empty($userData['password_hash']) && $this->verifyPassword($password, $userData['password_hash'])) {
            unset($userData['password_hash']); 
            return $userData;
        }
        return false; 
    }

    /**
     * Tworzy nowego użytkownika w bazie danych.
     * Dla roli 'teacher', $plainTextPassword może być ignorowane lub użyte do wygenerowania nieużywanego hasha.
     */
    public function create(string $name, string $email, string $plainTextPassword, string $role): int|false {
        if ($this->findByEmail($email)) {
            // Jeśli użytkownik już istnieje, a próbujemy stworzyć nauczyciela z tym samym pseudo-emailem,
            // to jest OK, jeśli to ten sam nauczyciel dla tej samej sesji.
            // Ale findByEmail zwraca tablicę, więc nie mamy tu obiektu User.
            // join_session_endpoint.php już to obsługuje, sprawdzając czy user istnieje przed create.
            // error_log("Próba utworzenia użytkownika z istniejącym adresem email: " . $email);
            // return false; 
        }

        if (!in_array($role, ['admin', 'teacher'])) {
            error_log("Próba utworzenia użytkownika z nieprawidłową rolą: " . $role);
            return false; 
        }

        // Dla nauczycieli nie potrzebujemy silnego hasła, bo nie będą się logować.
        // Możemy ustawić pusty hash lub wygenerować coś losowego.
        // Dla spójności z NOT NULL w bazie, generujemy hash.
        $hashedPassword = self::hashPassword($plainTextPassword); // To hasło nie będzie używane do logowania nauczyciela

        $sql = "INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':password_hash', $hashedPassword, PDO::PARAM_STR);
        $stmt->bindParam(':role', $role, PDO::PARAM_STR);

        try {
            if ($stmt->execute()) {
                return (int)$this->pdo->lastInsertId();
            }
        } catch (PDOException $e) {
            // Kod 23000 to naruszenie unikalności (np. email)
            if ($e->getCode() == '23000') {
                error_log("Błąd SQL (naruszenie unikalności) podczas tworzenia użytkownika {$email}: " . $e->getMessage());
                // Możemy spróbować pobrać istniejącego użytkownika, jeśli to był błąd unikalności emaila
                $existingUser = $this->findByEmail($email);
                if ($existingUser) return (int)$existingUser['user_id']; // Zwróć ID istniejącego
                return false;
            }
            error_log("Błąd SQL podczas tworzenia użytkownika {$email}: " . $e->getMessage() . " | SQL ErrorInfo: " . implode(", ", $stmt->errorInfo()));
            return false;
        }
        error_log("Nieznany błąd podczas tworzenia użytkownika {$email}. SQL ErrorInfo: " . implode(", ", $stmt->errorInfo()));
        return false;
    }

    public function findById(int $userId): ?User {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userData) {
            $user = new self($this->pdo); 
            $user->populate($userData);   
            return $user;
        }
        return null;
    }

    public function findByEmail(string $email): array|false {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function hasRole(string $roleName): bool {
        if ($this->user_id === null) {
            return false; 
        }
        return $this->role === $roleName;
    }

    public function getId(): ?int {
        return $this->user_id;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getEmail(): string {
        return $this->email;
    }

    public function getRole(): string {
        return $this->role;
    }
}
?>