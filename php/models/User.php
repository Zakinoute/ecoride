<?php
/**
 * User — règles des comptes : inscription, connexion, profil,
 * préférences du chauffeur, gestion des comptes par l'administrateur.
 * Les requêtes SQL sont dans UserRepository et PreferenceRepository.
 */
class User
{
    /** 8 caractères minimum, dont une majuscule, un chiffre et un caractère spécial. */
    private const PASSWORD_RULE = '/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';
    private const ROLES = ['user', 'employee', 'admin'];
    private const DEFAULT_PREFERENCES = ['smoking' => 0, 'animals' => 0, 'music' => 1, 'chat' => 1, 'other_preferences' => ''];

    public function __construct(
        private UserRepository $users = new UserRepository(),
        private PreferenceRepository $preferences = new PreferenceRepository(),
    ) {}

    // ── Création de compte ────────────────────────────────────
    /** Inscription d'un visiteur ; renvoie l'id du nouveau compte. */
    public function register(string $pseudo, string $email, string $password): int
    {
        return $this->createAccount($pseudo, $email, $password, 'user');
    }

    /** Création d'un compte employé par l'administrateur. */
    public function createEmployee(string $pseudo, string $email, string $password): int
    {
        return $this->createAccount($pseudo, $email, $password, 'employee');
    }

    private function createAccount(string $pseudo, string $email, string $password, string $role): int
    {
        if (!$pseudo || !$email || !$password) {
            throw new BusinessRuleException('Tous les champs sont obligatoires.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BusinessRuleException('Adresse email invalide.');
        }
        if (!preg_match(self::PASSWORD_RULE, $password)) {
            throw new BusinessRuleException('Mot de passe trop faible. Il doit contenir au moins 8 caractères, une majuscule, un chiffre et un caractère spécial.');
        }
        if ($this->users->existsByEmailOrPseudo($email, $pseudo)) {
            throw new BusinessRuleException('Ce pseudo ou email est déjà utilisé.');
        }

        // Le mot de passe n'est jamais stocké en clair : hachage bcrypt avec sel aléatoire.
        return $this->users->create($pseudo, $email, password_hash($password, PASSWORD_DEFAULT), $role);
    }

    // ── Connexion ─────────────────────────────────────────────
    public function login(string $email, string $password): array
    {
        if (!$email || !$password) {
            throw new BusinessRuleException('Email et mot de passe requis.');
        }
        $user = $this->users->findByEmail($email);

        // Même message si l'e-mail ou le mot de passe est faux : on n'aide pas un attaquant.
        if (!$user || !password_verify($password, $user['password'])) {
            throw new BusinessRuleException('Identifiants incorrects.');
        }
        if ($user['status'] === 'suspended') {
            throw new BusinessRuleException('Votre compte est suspendu. Contactez l\'administration.');
        }

        unset($user['password']);
        return $user;
    }

    // ── Profil ────────────────────────────────────────────────
    public function profile(int $userId): ?array
    {
        return $this->users->findById($userId);
    }

    /** Un utilisateur est chauffeur, passager, ou les deux. */
    public function setRoles(int $userId, bool $isDriver, bool $isPassenger): void
    {
        if (!$isDriver && !$isPassenger) {
            throw new BusinessRuleException('Vous devez choisir au moins un statut.');
        }
        $this->users->updateRoles($userId, $isDriver, $isPassenger);
    }

    public function preferences(int $userId): array
    {
        return $this->preferences->findByUser($userId) ?? self::DEFAULT_PREFERENCES;
    }

    public function savePreferences(int $userId, array $preferences): void
    {
        $this->preferences->save($userId, $preferences);
    }

    // ── Administration ────────────────────────────────────────
    public function list(string $search, string $role): array
    {
        // Un rôle inconnu est ignoré : il ne filtre rien.
        return $this->users->search($search, in_array($role, self::ROLES, true) ? $role : '');
    }

    /** Suspend un compte actif ou réactive un compte suspendu ; renvoie le nouveau statut. */
    public function toggleStatus(int $userId): string
    {
        $user = $this->users->findRoleAndStatus($userId);
        if (!$user) {
            throw new BusinessRuleException('Utilisateur introuvable.');
        }
        if ($user['role'] === 'admin') {
            throw new BusinessRuleException('Impossible de suspendre un administrateur.');
        }

        $newStatus = $user['status'] === 'active' ? 'suspended' : 'active';
        $this->users->updateStatus($userId, $newStatus);
        return $newStatus;
    }
}
