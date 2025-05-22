<?php
// Plik: classes/User.php

class User {
    public ?int $user_id = null;
    public string $name;
    public string $email;
    private string $password_hash; 
    public string $role; // 'admin' lub 'teacher'

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
        return password_verify($plainTextPassword, $hashedPassword);
    }

    public function login(string $email, string $password): array|false {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userData && $this->verifyPassword($password, $userData['password_hash'])) {
            unset($userData['password_hash']); 
            return $userData;
        }
        // error_log("Login attempt failed for: " . $email . ". User found: " . ($userData ? "Yes" : "No"));
        return false; 
    }

    public function create(string $name, string $email, string $plainTextPassword, string $role): int|false {
        if ($this->findByEmail($email)) {
            error_log("Próba utworzenia użytkownika z istniejącym adresem email: " . $email);
            return false; 
        }

        if (!in_array($role, ['admin', 'teacher'])) {
            error_log("Próba utworzenia użytkownika z nieprawidłową rolą: " . $role);
            return false; 
        }

        $hashedPassword = self::hashPassword($plainTextPassword);

        $sql = "INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':password_hash', $hashedPassword, PDO::PARAM_STR);
        $stmt->bindParam(':role', $role, PDO::PARAM_STR);

        if ($stmt->execute()) {
            return (int)$this->pdo->lastInsertId();
        }
        error_log("Błąd SQL podczas tworzenia użytkownika: " . implode(", ", $stmt->errorInfo()));
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

    public function findByEmail(string $email): array|false { // Zwraca array|false, a nie User|null
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC); // To jest OK dla create(), ale login() oczekuje trochę inaczej
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