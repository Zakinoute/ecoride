<?php
/**
 * BookingRepository — requêtes SQL de la table `bookings`.
 */
class BookingRepository extends Repository
{
    public function exists(int $tripId, int $passengerId): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM bookings WHERE trip_id = ? AND passenger_id = ?');
        $stmt->execute([$tripId, $passengerId]);
        return (bool) $stmt->fetch();
    }

    public function create(int $tripId, int $passengerId, int $credits): int
    {
        $this->db->prepare('INSERT INTO bookings (trip_id, passenger_id, credits_used) VALUES (?, ?, ?)')
                 ->execute([$tripId, $passengerId, $credits]);
        return (int) $this->db->lastInsertId();
    }

    public function findConfirmedByTrip(int $tripId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM bookings WHERE trip_id = ? AND status = 'confirmed'");
        $stmt->execute([$tripId]);
        return $stmt->fetchAll();
    }

    /** Nombre de réservations confirmées d'un trajet et total des crédits payés. */
    public function confirmedTotals(int $tripId): array
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS nb, COALESCE(SUM(credits_used), 0) AS total
            FROM bookings WHERE trip_id = ? AND status = 'confirmed'
        ");
        $stmt->execute([$tripId]);
        $row = $stmt->fetch();
        return ['count' => (int) $row['nb'], 'total' => (int) $row['total']];
    }

    public function cancel(int $bookingId): void
    {
        $this->db->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")->execute([$bookingId]);
    }

    /** Covoiturages réservés par jour : même mesure que le journal MongoDB. */
    public function confirmedPerDay(int $days): array
    {
        $stmt = $this->db->prepare("
            SELECT DATE(created_at) AS day, COUNT(*) AS count
            FROM bookings
            WHERE status = 'confirmed' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY day
            ORDER BY day ASC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }
}
