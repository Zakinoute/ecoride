<?php
/**
 * ReviewRepository — requêtes SQL de la table `reviews`.
 */
class ReviewRepository extends Repository
{
    public function exists(int $tripId, int $reviewerId): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM reviews WHERE trip_id = ? AND reviewer_id = ?');
        $stmt->execute([$tripId, $reviewerId]);
        return (bool) $stmt->fetch();
    }

    /** Un nouvel avis est « en attente » (valeur par défaut de la colonne status). */
    public function create(int $tripId, int $reviewerId, int $reviewedId, int $rating, string $comment): void
    {
        $this->db->prepare('INSERT INTO reviews (trip_id, reviewer_id, reviewed_id, rating, comment) VALUES (?, ?, ?, ?, ?)')
                 ->execute([$tripId, $reviewerId, $reviewedId, $rating, $comment]);
    }

    public function findPending(): array
    {
        $stmt = $this->db->prepare("
            SELECT r.*, u.pseudo AS reviewer_pseudo, d.pseudo AS driver_pseudo,
                   t.departure_city, t.arrival_city, t.departure_datetime
            FROM reviews r
            JOIN users u ON u.id = r.reviewer_id
            JOIN users d ON d.id = r.reviewed_id
            JOIN trips t ON t.id = r.trip_id
            WHERE r.status = 'pending'
            ORDER BY r.created_at ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function setStatus(int $reviewId, string $status): void
    {
        $this->db->prepare('UPDATE reviews SET status = ? WHERE id = ?')->execute([$status, $reviewId]);
    }

    public function findApprovedByDriver(int $driverId, int $limit): array
    {
        $stmt = $this->db->prepare("
            SELECT r.rating, r.comment, r.created_at, u.pseudo AS reviewer_pseudo, u.photo
            FROM reviews r
            JOIN users u ON u.id = r.reviewer_id
            WHERE r.reviewed_id = ? AND r.status = 'approved'
            ORDER BY r.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $driverId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countPending(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn();
    }
}
