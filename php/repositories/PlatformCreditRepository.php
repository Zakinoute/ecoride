<?php
/**
 * PlatformCreditRepository — requêtes SQL de la table `platform_credits`
 * (la commission gagnée par EcoRide sur chaque réservation).
 */
class PlatformCreditRepository extends Repository
{
    public function add(int $tripId, int $bookingId, int $amount): void
    {
        $this->db->prepare('INSERT INTO platform_credits (trip_id, booking_id, amount) VALUES (?, ?, ?)')
                 ->execute([$tripId, $bookingId, $amount]);
    }

    public function deleteByTrip(int $tripId): void
    {
        $this->db->prepare('DELETE FROM platform_credits WHERE trip_id = ?')->execute([$tripId]);
    }

    public function perDay(int $days): array
    {
        $stmt = $this->db->prepare('
            SELECT DATE(earned_at) AS day, SUM(amount) AS total
            FROM platform_credits
            WHERE earned_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY day
            ORDER BY day ASC
        ');
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }

    public function total(): float
    {
        return (float) $this->db->query('SELECT COALESCE(SUM(amount), 0) FROM platform_credits')->fetchColumn();
    }
}
