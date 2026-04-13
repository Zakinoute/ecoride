<?php
require_once __DIR__ . '/Database.php';

/**
 * Booking — participation à un covoiturage (réservation de place).
 */
class Booking
{
    private PDO $pdo;
    private const PLATFORM_FEE = 2;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * Réserver une place sur un trajet.
     * Débite les crédits du passager, crédite le chauffeur (prix - frais plateforme),
     * enregistre les gains plateforme dans platform_credits.
     */
    public function book(int $tripId, int $passengerId): array
    {
        // Vérifications
        $stmt = $this->pdo->prepare(
            "SELECT t.price, t.available_seats, t.driver_id, t.status
             FROM trips t WHERE t.id = ? FOR UPDATE"
        );
        $this->pdo->beginTransaction();
        $stmt->execute([$tripId]);
        $trip = $stmt->fetch();

        if (!$trip || $trip['status'] !== 'active') {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Trajet non disponible.'];
        }
        if ($trip['available_seats'] < 1) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Plus de place disponible.'];
        }

        $cost = (int) $trip['price'];

        // Vérifier crédits passager
        $stmt = $this->pdo->prepare('SELECT credits FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$passengerId]);
        $passenger = $stmt->fetch();
        if (!$passenger || $passenger['credits'] < $cost) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Crédits insuffisants.'];
        }

        // Débit passager
        $this->pdo->prepare('UPDATE users SET credits = credits - ? WHERE id = ?')
                  ->execute([$cost, $passengerId]);

        // Crédit chauffeur (prix - frais plateforme)
        $driverGain = $cost - self::PLATFORM_FEE;
        $this->pdo->prepare('UPDATE users SET credits = credits + ? WHERE id = ?')
                  ->execute([$driverGain, $trip['driver_id']]);

        // Mise à jour places
        $this->pdo->prepare('UPDATE trips SET available_seats = available_seats - 1 WHERE id = ?')
                  ->execute([$tripId]);

        // Enregistrement réservation
        $stmt = $this->pdo->prepare(
            'INSERT INTO bookings (trip_id, passenger_id, credits_used) VALUES (?, ?, ?)'
        );
        $stmt->execute([$tripId, $passengerId, $cost]);
        $bookingId = (int) $this->pdo->lastInsertId();

        // Gains plateforme
        $this->pdo->prepare(
            'INSERT INTO platform_credits (trip_id, booking_id, amount) VALUES (?, ?, ?)'
        )->execute([$tripId, $bookingId, self::PLATFORM_FEE]);

        $this->pdo->commit();
        return ['success' => true, 'booking_id' => $bookingId];
    }

    /**
     * Annuler une réservation (rembourse le passager).
     */
    public function cancel(int $bookingId, int $passengerId): array
    {
        $this->pdo->beginTransaction();

        $stmt = $this->pdo->prepare(
            "SELECT b.trip_id, b.credits_used, t.driver_id, t.status
             FROM bookings b JOIN trips t ON b.trip_id = t.id
             WHERE b.id = ? AND b.passenger_id = ? AND b.status = 'confirmed'
             FOR UPDATE"
        );
        $stmt->execute([$bookingId, $passengerId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Réservation introuvable.'];
        }

        // Remboursement passager
        $this->pdo->prepare('UPDATE users SET credits = credits + ? WHERE id = ?')
                  ->execute([$booking['credits_used'], $passengerId]);

        // Retrait crédit chauffeur
        $driverGain = $booking['credits_used'] - self::PLATFORM_FEE;
        $this->pdo->prepare('UPDATE users SET credits = credits - ? WHERE id = ?')
                  ->execute([$driverGain, $booking['driver_id']]);

        // Libérer la place
        $this->pdo->prepare('UPDATE trips SET available_seats = available_seats + 1 WHERE id = ?')
                  ->execute([$booking['trip_id']]);

        // Marquer annulé
        $this->pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")
                  ->execute([$bookingId]);

        $this->pdo->commit();
        return ['success' => true];
    }
}
