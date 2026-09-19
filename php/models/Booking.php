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

    public function __construct(
        private TripRepository $trips = new TripRepository(),
        private UserRepository $users = new UserRepository(),
        private BookingRepository $bookings = new BookingRepository(),
        private PlatformCreditRepository $platformCredits = new PlatformCreditRepository(),
    ) {}

    /**
     * Réserve une place et renvoie le nouveau solde du passager.
     * Tout se fait dans une transaction : si une étape échoue, rien n'est enregistré.
     */
    public function book(int $tripId, int $passengerId): int
    {
        $booking = Database::transaction(function () use ($tripId, $passengerId): array {
            // Trajet verrouillé : deux réservations simultanées ne peuvent pas prendre la même dernière place.
            $trip = $this->trips->lockBookable($tripId);
            if (!$trip) {
                throw new BusinessRuleException('Trajet indisponible.');
            }
            if ((int) $trip['driver_id'] === $passengerId) {
                throw new BusinessRuleException('Vous ne pouvez pas réserver votre propre trajet.');
            }

            // Solde du passager verrouillé lui aussi.
            $credits = $this->users->lockCredits($passengerId);
            $cost    = (int) $trip['price'];
            if ($credits === null || $credits < $cost) {
                throw new BusinessRuleException('Crédits insuffisants.');
            }
            if ($this->bookings->exists($tripId, $passengerId)) {
                throw new BusinessRuleException('Vous avez déjà réservé ce trajet.');
            }

            // Débiter le passager, créer la réservation, retirer une place,
            // enregistrer la commission de la plateforme.
            $this->users->debit($passengerId, $cost);
            $bookingId = $this->bookings->create($tripId, $passengerId, $cost);
            $this->trips->takeSeat($tripId);
            $this->platformCredits->add($tripId, $bookingId, self::PLATFORM_FEE);

            return ['id' => $bookingId, 'cost' => $cost, 'is_eco' => $trip['energy'] === 'electrique', 'new_credits' => $credits - $cost];
        });

        // Après la validation dans MySQL : journal NoSQL pour les statistiques.
        // Une panne de MongoDB ne doit pas annuler une réservation déjà payée.
        try {
            (new BookingLogRepository())->record($booking['id'], $tripId, $booking['cost'], self::PLATFORM_FEE, $booking['is_eco']);
        } catch (Throwable $e) {
            error_log('EcoRide - journal MongoDB indisponible : ' . $e->getMessage());
        }

        return $booking['new_credits'];
    }
}
