<?php
/**
 * TripController — recherche, détail, publication, réservation et cycle de vie des trajets.
 */
class TripController extends Controller
{
    protected const ACTIONS = [
        'search'   => 'search',
        'detail'   => 'detail',
        'create'   => 'create',
        'book'     => 'book',
        'start'    => 'start',
        'complete' => 'complete',
        'cancel'   => 'cancel',
        'my_trips' => 'myTrips',
        'report'   => 'report',
    ];

    public function __construct(private Trip $trips = new Trip())
    {
        parent::__construct();
    }

    // ── Public ────────────────────────────────────────────────
    protected function search(): void
    {
        $this->json(true, '', ['trips' => $this->trips->search([
            'departure'  => $this->text('departure'),
            'arrival'    => $this->text('arrival'),
            'date'       => $this->raw('date'),
            'max_price'  => $this->has('max_price')  ? (float) $this->raw('max_price')  : null,
            'min_rating' => $this->has('min_rating') ? (float) $this->raw('min_rating') : null,
            'eco'        => !empty($this->raw('eco')),
            'sort'       => $this->raw('sort') ?: 'departure_datetime',
        ])]);
    }

    protected function detail(): void
    {
        $id = $this->int('id');
        if (!$id) {
            $this->json(false, 'ID manquant.');
        }
        $trip = $this->trips->find($id);
        if (!$trip) {
            $this->json(false, 'Trajet introuvable.');
        }
        $reviews = (new Review())->forDriver((int) $trip['driver_id'], 10);
        $this->json(true, '', ['trip' => $trip, 'reviews' => $reviews]);
    }

    // ── Chauffeur ─────────────────────────────────────────────
    protected function create(): void
    {
        $tripId = $this->trips->create($this->requireAuth(), [
            'departure_address'  => $this->text('departure_address'),
            'departure_city'     => $this->text('departure_city'),
            'arrival_address'    => $this->text('arrival_address'),
            'arrival_city'       => $this->text('arrival_city'),
            'departure_datetime' => $this->raw('departure_datetime'),
            'arrival_datetime'   => $this->raw('arrival_datetime'),
            'price'              => $this->int('price'),   // les crédits sont des entiers
            'seats'              => $this->has('seats') ? $this->int('seats') : 1,
            'vehicle_id'         => $this->int('vehicle_id'),
        ]);
        $this->json(true, 'Trajet publié avec succès !', ['trip_id' => $tripId]);
    }

    protected function start(): void
    {
        $this->trips->start($this->int('trip_id'), $this->requireAuth());
        $this->json(true, 'Trajet démarré !');
    }

    protected function complete(): void
    {
        $earned = $this->trips->complete($this->int('trip_id'), $this->requireAuth());
        $_SESSION['credits'] = ($_SESSION['credits'] ?? 0) + $earned;
        $this->json(true, 'Trajet terminé ! Crédits crédités.', ['earned' => $earned, 'new_credits' => $_SESSION['credits']]);
    }

    protected function cancel(): void
    {
        $this->trips->cancel($this->int('trip_id'), $this->requireAuth());
        $this->json(true, 'Trajet annulé. Les passagers ont été remboursés.');
    }

    // ── Passager ──────────────────────────────────────────────
    protected function book(): void
    {
        $userId = $this->requireAuth();
        $tripId = $this->int('trip_id');
        if (!$tripId) {
            $this->json(false, 'ID de trajet manquant.');
        }
        $newCredits = (new Booking())->book($tripId, $userId);
        $_SESSION['credits'] = $newCredits;
        $this->json(true, 'Réservation confirmée !', ['new_credits' => $newCredits]);
    }

    protected function myTrips(): void
    {
        $this->json(true, '', ['trips' => $this->trips->forUser($this->requireAuth())]);
    }

    protected function report(): void
    {
        $this->requireAuth();
        $tripId = $this->int('trip_id');
        $reason = $this->text('reason');
        if (!$tripId || !$reason) {
            $this->json(false, 'Données manquantes.');
        }
        $this->trips->report($tripId, $reason);
        $this->json(true, 'Signalement envoyé.');
    }
}
