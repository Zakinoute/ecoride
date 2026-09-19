<?php
/**
 * VehicleRepository — requêtes SQL de la table `vehicles`.
 * Un véhicule supprimé est seulement désactivé : les anciens trajets le citent encore.
 */
class VehicleRepository extends Repository
{
    public function findActiveByUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM vehicles WHERE user_id = ? AND is_active = 1 ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /** Le véhicule, seulement s'il est actif et appartient à cet utilisateur. */
    public function findOwnedActive(int $vehicleId, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, seats FROM vehicles WHERE id = ? AND user_id = ? AND is_active = 1');
        $stmt->execute([$vehicleId, $userId]);
        return $stmt->fetch() ?: null;
    }

    public function plateExists(string $plate): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM vehicles WHERE plate = ?');
        $stmt->execute([$plate]);
        return (bool) $stmt->fetch();
    }

    public function create(int $userId, array $vehicle): int
    {
        $this->db->prepare('INSERT INTO vehicles (user_id, plate, first_registration, brand, model, color, seats, energy) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                 ->execute([$userId, $vehicle['plate'], $vehicle['first_registration'], $vehicle['brand'],
                            $vehicle['model'], $vehicle['color'], $vehicle['seats'], $vehicle['energy']]);
        return (int) $this->db->lastInsertId();
    }

    public function deactivate(int $vehicleId, int $userId): void
    {
        $this->db->prepare('UPDATE vehicles SET is_active = 0 WHERE id = ? AND user_id = ?')->execute([$vehicleId, $userId]);
    }
}
