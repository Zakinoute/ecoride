<?php
/**
 * Trip — règles des trajets : publication, démarrage, fin, annulation, signalement.
 * Les requêtes SQL sont dans TripRepository (et les autres repositories).
 */
class Trip
{
    public function __construct(
        private TripRepository $trips = new TripRepository(),
        private UserRepository $users = new UserRepository(),
        private VehicleRepository $vehicles = new VehicleRepository(),
        private BookingRepository $bookings = new BookingRepository(),
        private PlatformCreditRepository $platformCredits = new PlatformCreditRepository(),
    ) {}

    // ── Consultation ──────────────────────────────────────────
    public function search(array $criteria): array
    {
        return array_map([$this, 'withEcoFlag'], $this->trips->search($criteria));
    }

    public function find(int $id): ?array
    {
        $trip = $this->trips->findDetail($id);
        return $trip ? $this->withEcoFlag($trip) : null;
    }

    public function forUser(int $userId): array
    {
        return $this->trips->findByUser($userId);
    }

    public function reported(): array
    {
        return $this->trips->findReported();
    }

    // ── Publication (chauffeur) ───────────────────────────────
    public function create(int $driverId, array $data): int
    {
        if (!$this->users->isDriver($driverId)) {
            throw new BusinessRuleException('Vous devez être enregistré comme chauffeur.');
        }
        if (!$data['departure_address'] || !$data['departure_city'] || !$data['arrival_address'] || !$data['arrival_city']
            || !$data['departure_datetime'] || !$data['vehicle_id'] || $data['price'] <= 0 || $data['seats'] < 1) {
            throw new BusinessRuleException('Tous les champs sont obligatoires.');
        }

        // Le véhicule doit appartenir au chauffeur ; une place reste pour lui.
        $vehicle = $this->vehicles->findOwnedActive($data['vehicle_id'], $driverId);
        if (!$vehicle) {
            throw new BusinessRuleException('Véhicule invalide.');
        }
        if ($data['seats'] > $vehicle['seats'] - 1) {
            throw new BusinessRuleException('Nombre de places trop élevé pour ce véhicule.');
        }

        return $this->trips->create($driverId, $data);
    }

    // ── Démarrer ──────────────────────────────────────────────
    public function start(int $tripId, int $driverId): void
    {
        if (!$this->trips->start($tripId, $driverId)) {
            throw new BusinessRuleException('Impossible de démarrer ce trajet.');
        }
    }

    // ── Terminer et payer le chauffeur ────────────────────────
    /** Renvoie les crédits gagnés par le chauffeur. */
    public function complete(int $tripId, int $driverId): int
    {
        return Database::transaction(function () use ($tripId, $driverId): int {
            if (!$this->trips->lockForDriver($tripId, $driverId, 'started')) {
                throw new BusinessRuleException('Trajet non trouvé.');
            }

            // Gain du chauffeur = crédits payés par les passagers - commission par réservation
            $bookings = $this->bookings->confirmedTotals($tripId);
            $earned   = $bookings['total'] - Booking::PLATFORM_FEE * $bookings['count'];

            $this->trips->setStatus($tripId, 'completed');
            if ($earned > 0) {
                $this->users->credit($driverId, $earned);
            }
            return max($earned, 0);
        });
    }

    // ── Annuler et rembourser les passagers ───────────────────
    public function cancel(int $tripId, int $driverId): void
    {
        Database::transaction(function () use ($tripId, $driverId): void {
            if (!$this->trips->lockForDriver($tripId, $driverId, 'active')) {
                throw new BusinessRuleException('Trajet introuvable ou déjà commencé.');
            }

            foreach ($this->bookings->findConfirmedByTrip($tripId) as $booking) {
                $this->users->credit((int) $booking['passenger_id'], (int) $booking['credits_used']);
                $this->bookings->cancel((int) $booking['id']);
            }
            // Le trajet n'a pas lieu : la plateforme ne garde pas sa commission.
            $this->platformCredits->deleteByTrip($tripId);
            $this->trips->setStatus($tripId, 'cancelled');
        });

        // Même règle dans les statistiques MongoDB (une panne MongoDB ne bloque pas l'annulation).
        try {
            (new BookingLogRepository())->forgetTrip($tripId);
        } catch (Throwable $e) {
            error_log('EcoRide - journal MongoDB indisponible : ' . $e->getMessage());
        }
    }

    // ── Signalement d'un trajet mal passé ─────────────────────
    public function report(int $tripId, string $reason): void
    {
        $this->trips->report($tripId, $reason);
    }

    /** Un trajet est « écologique » quand la voiture est électrique. */
    private function withEcoFlag(array $trip): array
    {
        $trip['is_eco']     = $trip['energy'] === 'electrique';
        $trip['avg_rating'] = round((float) $trip['avg_rating'], 1);
        return $trip;
    }
}
