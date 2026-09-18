<?php
/**
 * Review — avis laissés par les passagers sur les chauffeurs.
 * Un avis n'est visible qu'après validation par un employé.
 */
class Review
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // ── Dépôt d'un avis (passager) ────────────────────────────
    public function submit(int $tripId, int $reviewerId, int $rating, string $comment): array
    {
        if (!$tripId || $rating < 1 || $rating > 5) {
            return ['success' => false, 'message' => 'Note invalide (1 à 5 requis).'];
        }

        // Le trajet doit être terminé et l'auteur doit y avoir participé.
        $stmt = $this->pdo->prepare("
            SELECT t.driver_id FROM trips t
            JOIN bookings b ON b.trip_id = t.id AND b.passenger_id = ?
            WHERE t.id = ? AND t.status = 'completed'
        ");
        $stmt->execute([$reviewerId, $tripId]);
        $trip = $stmt->fetch();
        if (!$trip) {
            return ['success' => false, 'message' => 'Vous ne pouvez noter que les trajets terminés auxquels vous avez participé.'];
        }

        $stmt = $this->pdo->prepare('SELECT id FROM reviews WHERE trip_id = ? AND reviewer_id = ?');
        $stmt->execute([$tripId, $reviewerId]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Vous avez déjà laissé un avis pour ce trajet.'];
        }

        $this->pdo->prepare('INSERT INTO reviews (trip_id, reviewer_id, reviewed_id, rating, comment) VALUES (?, ?, ?, ?, ?)')
                  ->execute([$tripId, $reviewerId, $trip['driver_id'], $rating, $comment]);

        return ['success' => true, 'message' => 'Avis soumis. Il sera visible après validation par notre équipe.'];
    }

    // ── Avis en attente (employé) ─────────────────────────────
    public function getPending(): array
    {
        $stmt = $this->pdo->prepare("
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

    // ── Validation ou refus (employé) ─────────────────────────
    public function moderate(int $reviewId, string $decision): bool
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            return false;
        }
        $this->pdo->prepare('UPDATE reviews SET status = ? WHERE id = ?')->execute([$decision, $reviewId]);
        return true;
    }

    // ── Avis validés d'un chauffeur (public) ──────────────────
    public function getByDriver(int $driverId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
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
}
