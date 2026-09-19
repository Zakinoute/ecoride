<?php
/**
 * UserRepository — requêtes SQL de la table `users`.
 */
class UserRepository extends Repository
{
    /** Profil affiché dans l'espace utilisateur (jamais le mot de passe). */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, pseudo, email, role, credits, photo, is_driver, is_passenger FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Compte complet pour la connexion (avec le hachage du mot de passe). */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT id, pseudo, password, role, credits, status FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function findRoleAndStatus(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT status, role FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function existsByEmailOrPseudo(string $email, string $pseudo): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ? OR pseudo = ?');
        $stmt->execute([$email, $pseudo]);
        return (bool) $stmt->fetch();
    }

    /** Les 20 crédits de bienvenue sont la valeur par défaut de la colonne. */
    public function create(string $pseudo, string $email, string $passwordHash, string $role): int
    {
        $this->db->prepare('INSERT INTO users (pseudo, email, password, role) VALUES (?, ?, ?, ?)')
                 ->execute([$pseudo, $email, $passwordHash, $role]);
        return (int) $this->db->lastInsertId();
    }

    public function isDriver(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT is_driver FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return (bool) ($stmt->fetch()['is_driver'] ?? false);
    }

    public function markAsDriver(int $id): void
    {
        $this->db->prepare('UPDATE users SET is_driver = 1 WHERE id = ?')->execute([$id]);
    }

    public function updateRoles(int $id, bool $isDriver, bool $isPassenger): void
    {
        $this->db->prepare('UPDATE users SET is_driver = ?, is_passenger = ? WHERE id = ?')
                 ->execute([(int) $isDriver, (int) $isPassenger, $id]);
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->db->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$status, $id]);
    }

    // ── Crédits ───────────────────────────────────────────────
    /**
     * Lit le solde en verrouillant la ligne (FOR UPDATE) jusqu'à la fin
     * de la transaction : deux réservations ne peuvent pas dépenser le même solde.
     */
    public function lockCredits(int $id): ?int
    {
        $stmt = $this->db->prepare('SELECT credits FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? (int) $row['credits'] : null;
    }

    public function credit(int $id, int $amount): void
    {
        $this->db->prepare('UPDATE users SET credits = credits + ? WHERE id = ?')->execute([$amount, $id]);
    }

    public function debit(int $id, int $amount): void
    {
        $this->db->prepare('UPDATE users SET credits = credits - ? WHERE id = ?')->execute([$amount, $id]);
    }

    // ── Administration ────────────────────────────────────────
    /** Liste des comptes, filtrée par pseudo / e-mail et par rôle (100 au plus). */
    public function search(string $search, string $role): array
    {
        $sql    = 'SELECT id, pseudo, email, role, status, credits, created_at FROM users WHERE 1=1';
        $params = [];
        if ($search !== '') {
            $sql .= ' AND (pseudo LIKE ? OR email LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($role !== '') {
            $sql .= ' AND role = ?';
            $params[] = $role;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 100';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countByRole(string $role): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE role = ?');
        $stmt->execute([$role]);
        return (int) $stmt->fetchColumn();
    }
}
