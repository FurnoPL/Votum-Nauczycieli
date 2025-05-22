<?php
// Plik: classes/User.php

class User {
    // Właściwości odpowiadające polom w tabeli users
    public ?int $user_id = null;
    public string $name;
    public string $email;
    private string $password_hash; // Prywatne, bo nie chcemy go bezpośrednio eksponować
    public string $role; // 'admin' lub 'teacher'

    private PDO $pdo; // Prywatna właściwość do przechowywania obiektu PDO

    /**
     * Konstruktor klasy User.
     *
     * @param PDO $pdo Obiekt połączenia PDO.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Ustawia właściwości obiektu na podstawie danych z tablicy (np. z bazy danych).
     *
     * @param array $data Tablica asocjacyjna z danymi użytkownika.
     */
    private function populate(array $data): void {
        $this->user_id = (int)$data['user_id'];
        $this->name = $data['name'];
        $this->email = $data['email'];
        $this->password_hash = $data['password_hash']; // Przechowujemy hash
        $this->role = $data['role'];
    }

    /**
     * Hashuje podane hasło.
     *
     * @param string $plainTextPassword Hasło w postaci czystego tekstu.
     * @return string Zahashowane hasło.
     */
    public static function hashPassword(string $plainTextPassword): string {
        return password_hash($plainTextPassword, PASSWORD_DEFAULT);
    }

    /**
     * Weryfikuje, czy podane hasło pasuje do zapisanego hasha.
     *
     * @param string $plainTextPassword Hasło w postaci czystego tekstu.
     * @param string $hashedPassword Zahashowane hasło z bazy danych.
     * @return bool True, jeśli hasło pasuje, false w przeciwnym razie.
     */
    private function verifyPassword(string $plainTextPassword, string $hashedPassword): bool {
        return password_verify($plainTextPassword, $hashedPassword);
    }

    /**
     * Loguje użytkownika.
     *
     * @param string $email Email użytkownika.
     * @param string $password Hasło użytkownika.
     * @return array|false Dane zalogowanego użytkownika jako tablica asocjacyjna lub false w przypadku niepowodzenia.
     */
    public function login(string $email, string $password): array|false {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userData && $this->verifyPassword($password, $userData['password_hash'])) {
            // Hasło poprawne, zwróć dane użytkownika (bez hasha)
            unset($userData['password_hash']); // Usuwamy hash z danych zwracanych
            return $userData;
        }
        return false; // Błędny email lub hasło
    }

    /**
     * Tworzy nowego użytkownika w bazie danych.
     *
     * @param string $name Imię i nazwisko lub nazwa użytkownika.
     * @param string $email Adres email (będzie używany jako login).
     * @param string $plainTextPassword Hasło w postaci czystego tekstu.
     * @param string $role Rola użytkownika ('admin' lub 'teacher').
     * @return int|false ID nowo utworzonego użytkownika lub false w przypadku błędu.
     */
    public function create(string $name, string $email, string $plainTextPassword, string $role): int|false {
        // Sprawdź, czy email już istnieje
        if ($this->findByEmail($email)) {
            // Można by rzucić wyjątek lub zwrócić specyficzny błąd
            error_log("Próba utworzenia użytkownika z istniejącym adresem email: " . $email);
            return false; // Email już zajęty
        }

        // Sprawdź, czy rola jest poprawna
        if (!in_array($role, ['admin', 'teacher'])) {
            error_log("Próba utworzenia użytkownika z nieprawidłową rolą: " . $role);
            return false; // Nieprawidłowa rola
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

    /**
     * Znajduje użytkownika po jego ID.
     * Wypełnia właściwości bieżącego obiektu, jeśli użytkownik zostanie znaleziony.
     *
     * @param int $userId ID użytkownika.
     * @return User|null Obiekt User lub null, jeśli nie znaleziono.
     */
    public function findById(int $userId): ?User {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userData) {
            $user = new self($this->pdo); // Tworzymy nowy obiekt User
            $user->populate($userData);   // Wypełniamy go danymi
            return $user;
        }
        return null;
    }

    /**
     * Znajduje użytkownika po jego adresie email.
     *
     * @param string $email Adres email użytkownika.
     * @return array|false Dane użytkownika jako tablica asocjacyjna lub false, jeśli nie znaleziono.
     */
    public function findByEmail(string $email): array|false {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    /**
     * Sprawdza, czy użytkownik ma określoną rolę.
     * Ta metoda powinna być wywoływana na załadowanym obiekcie użytkownika.
     *
     * @param string $roleName Nazwa roli do sprawdzenia.
     * @return bool True, jeśli użytkownik ma daną rolę, false w przeciwnym razie.
     */
    public function hasRole(string $roleName): bool {
        // Upewnij się, że obiekt jest załadowany (ma user_id i rolę)
        if ($this->user_id === null) {
            return false; // Obiekt nie jest załadowany danymi użytkownika
        }
        return $this->role === $roleName;
    }

    // --- Gettery dla właściwości (opcjonalnie, ale dobra praktyka dla enkapsulacji) ---

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

    // Nie tworzymy gettera dla password_hash, aby go nie eksponować niepotrzebnie.
}
?>