<?php
require_once __DIR__ . '/Database.php';

/**
 * Review — avis laissés par les passagers sur les chauffeurs.
 */
class Review
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function create(int $tripId, int $reviewerId, int $reviewedId, int $rating, string $comment): array
    {
        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'message' => 'La note doit être entre 1 et 5.'];
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO reviews (trip_id, reviewer_id, reviewed_id, rating, comment) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$tripId, $reviewerId, $reviewedId, $rating, $comment]);
        return ['success' => true, 'id' => (int) $this->pdo->lastInsertId()];
    }

    /** Avis en attente de validation (espace employé). */
    public function getPending(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*, u1.pseudo AS reviewer, u2.pseudo AS reviewed
            FROM reviews r
            JOIN users u1 ON r.reviewer_id = u1.id
            JOIN users u2 ON r.reviewed_id = u2.id
            WHERE r.status = 'pending'
            ORDER BY r.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function approve(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE reviews SET status = 'approved' WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function reject(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE reviews SET status = 'rejected' WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /** Avis approuvés d'un chauffeur. */
    public function getByDriver(int $driverId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.rating, r.comment, r.created_at, u.pseudo AS reviewer
            FROM reviews r
            JOIN users u ON r.reviewer_id = u.id
            WHERE r.reviewed_id = ? AND r.status = 'approved'
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$driverId]);
        return $stmt->fetchAll();
    }
}
