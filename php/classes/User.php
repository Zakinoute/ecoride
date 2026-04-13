<?php
require_once __DIR__ . '/Database.php';

/**
 * User — gestion des utilisateurs (inscription, connexion, profil, suspension).
 */
class User
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // ── Création de compte ────────────────────────────────────
    public function register(string $pseudo, string $email, string $password): array
    {
        if (!$pseudo || !$email || !$password) {
            return ['success' => false, 'message' => 'Tous les champs sont obligatoires.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Adresse email invalide.'];
        }
        if (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
            return ['success' => false, 'message' => 'Mot de passe trop faible (8 car. min, 1 maj, 1 chiffre, 1 spécial).'];
        }

        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ? OR pseudo = ?');
        $stmt->execute([$email, $pseudo]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Ce pseudo ou email est déjà utilisé.'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('INSERT INTO users (pseudo, email, password) VALUES (?, ?, ?)');
        $stmt->execute([$pseudo, $email, $hash]);

        return ['success' => true, 'id' => (int) $this->pdo->lastInsertId(), 'credits' => 20];
    }

    // ── Connexion ─────────────────────────────────────────────
    public function login(string $email, string $password): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, pseudo, password, role, credits, status, is_driver, is_passenger FROM users WHERE email = ?'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Identifiants incorrects.'];
        }
        if ($user['status'] === 'suspended') {
            return ['success' => false, 'message' => 'Compte suspendu. Contactez l\'administration.'];
        }

        unset($user['password']);
        return ['success' => true, 'user' => $user];
    }

    // ── Profil ────────────────────────────────────────────────
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, pseudo, email, role, credits, photo, is_driver, is_passenger, status FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function updateProfile(int $id, array $data): bool
    {
        $allowed = ['pseudo', 'email', 'photo', 'is_driver', 'is_passenger'];
        $fields  = [];
        $values  = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $values[] = $data[$field];
            }
        }
        if (empty($fields)) return false;

        $values[] = $id;
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    // ── Suspension (admin) ────────────────────────────────────
    public function suspend(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function activate(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // ── Crédits ───────────────────────────────────────────────
    public function updateCredits(int $id, int $delta): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET credits = credits + ? WHERE id = ?');
        return $stmt->execute([$delta, $id]);
    }
}
