<?php
/**
 * User — comptes utilisateurs (inscription, connexion, profil).
 */
class User
{
    /** 8 caractères minimum, dont une majuscule, un chiffre et un caractère spécial. */
    private const PASSWORD_RULE = '/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';

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
        if (!preg_match(self::PASSWORD_RULE, $password)) {
            return ['success' => false, 'message' => 'Mot de passe trop faible. Il doit contenir au moins 8 caractères, une majuscule, un chiffre et un caractère spécial.'];
        }

        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ? OR pseudo = ?');
        $stmt->execute([$email, $pseudo]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Ce pseudo ou email est déjà utilisé.'];
        }

        // Le mot de passe n'est jamais stocké en clair : hachage bcrypt avec sel aléatoire.
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->pdo->prepare('INSERT INTO users (pseudo, email, password) VALUES (?, ?, ?)')
                  ->execute([$pseudo, $email, $hash]);

        return ['success' => true, 'message' => 'Compte créé avec succès ! 20 crédits offerts.', 'id' => (int) $this->pdo->lastInsertId()];
    }

    // ── Connexion ─────────────────────────────────────────────
    public function login(string $email, string $password): array
    {
        $stmt = $this->pdo->prepare('SELECT id, pseudo, password, role, credits, status FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Même message si l'e-mail ou le mot de passe est faux : on n'aide pas un attaquant.
        if (!$user || !password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Identifiants incorrects.'];
        }
        if ($user['status'] === 'suspended') {
            return ['success' => false, 'message' => 'Votre compte est suspendu. Contactez l\'administration.'];
        }

        unset($user['password']);
        return ['success' => true, 'message' => 'Connexion réussie.', 'user' => $user];
    }

    // ── Profil ────────────────────────────────────────────────
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, pseudo, email, role, credits, photo, is_driver, is_passenger FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
