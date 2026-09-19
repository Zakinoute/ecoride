<?php
/**
 * PreferenceRepository — requêtes SQL de la table `driver_preferences`.
 */
class PreferenceRepository extends Repository
{
    public function findByUser(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM driver_preferences WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    /** Crée la ligne la première fois, puis la met à jour (user_id est UNIQUE). */
    public function save(int $userId, array $prefs): void
    {
        $values = [(int) $prefs['smoking'], (int) $prefs['animals'], (int) $prefs['music'], (int) $prefs['chat'], $prefs['other_preferences']];
        $this->db->prepare('
            INSERT INTO driver_preferences (user_id, smoking, animals, music, chat, other_preferences)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE smoking = ?, animals = ?, music = ?, chat = ?, other_preferences = ?
        ')->execute([$userId, ...$values, ...$values]);
    }
}
