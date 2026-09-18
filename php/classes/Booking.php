<?php
/**
 * Booking — réservation d'une place sur un trajet.
 *
 * Règle métier : le passager paie le prix du trajet en crédits ; la plateforme
 * garde PLATFORM_FEE crédits par réservation ; le chauffeur est payé à la fin
 * du trajet (voir Trip::complete()).
 */
class Booking
{
    public const PLATFORM_FEE = 2;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * Réserve une place. Tout se fait dans une transaction :
     * si une étape échoue, rien n'est enregistré.
     */
    public function book(int $tripId, int $passengerId): array
    {
        $this->pdo->beginTransaction();
        try {
            // Verrouiller la ligne du trajet : deux réservations simultanées
            // ne peuvent pas prendre la même dernière place.
            $stmt = $this->pdo->prepare("
                SELECT t.*, v.energy
                FROM trips t
                JOIN vehicles v ON v.id = t.vehicle_id
                WHERE t.id = ? AND t.status = 'active' AND t.available_seats > 0
                FOR UPDATE
            ");
            $stmt->execute([$tripId]);
            $trip = $stmt->fetch();
            if (!$trip) {
                return $this->refuse('Trajet indisponible.');
            }
            if ((int) $trip['driver_id'] === $passengerId) {
                return $this->refuse('Vous ne pouvez pas réserver votre propre trajet.');
            }

            // Verrouiller le solde du passager
            $stmt = $this->pdo->prepare('SELECT credits FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$passengerId]);
            $passenger = $stmt->fetch();
            $cost = (int) $trip['price'];
            if (!$passenger || $passenger['credits'] < $cost) {
                return $this->refuse('Crédits insuffisants.');
            }

            $stmt = $this->pdo->prepare('SELECT id FROM bookings WHERE trip_id = ? AND passenger_id = ?');
            $stmt->execute([$tripId, $passengerId]);
            if ($stmt->fetch()) {
                return $this->refuse('Vous avez déjà réservé ce trajet.');
            }

            // Débiter le passager, créer la réservation, retirer une place,
            // enregistrer la commission de la plateforme.
            $this->pdo->prepare('UPDATE users SET credits = credits - ? WHERE id = ?')
                      ->execute([$cost, $passengerId]);
            $this->pdo->prepare('INSERT INTO bookings (trip_id, passenger_id, credits_used) VALUES (?, ?, ?)')
                      ->execute([$tripId, $passengerId, $cost]);
            $bookingId = (int) $this->pdo->lastInsertId();
            $this->pdo->prepare('UPDATE trips SET available_seats = available_seats - 1 WHERE id = ?')
                      ->execute([$tripId]);
            $this->pdo->prepare('INSERT INTO platform_credits (trip_id, booking_id, amount) VALUES (?, ?, ?)')
                      ->execute([$tripId, $bookingId, self::PLATFORM_FEE]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => 'Erreur lors de la réservation.'];
        }

        // Après la validation dans MySQL : journal NoSQL pour les statistiques.
        // Une panne de MongoDB ne doit pas annuler une réservation déjà payée.
        try {
            (new BookingLog())->record($bookingId, $tripId, $cost, self::PLATFORM_FEE, $trip['energy'] === 'electrique');
        } catch (Throwable $e) {
            error_log('EcoRide - journal MongoDB indisponible : ' . $e->getMessage());
        }

        return ['success' => true, 'message' => 'Réservation confirmée !', 'new_credits' => $passenger['credits'] - $cost];
    }

    /** Annule la transaction en cours et renvoie le motif du refus. */
    private function refuse(string $message): array
    {
        $this->pdo->rollBack();
        return ['success' => false, 'message' => $message];
    }
}
