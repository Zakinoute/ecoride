<?php
/**
 * Statistics — chiffres des tableaux de bord employé et administrateur.
 * MySQL fait foi ; le journal MongoDB sert aux graphiques par jour.
 */
class Statistics
{
    private const DAYS = 30;

    public function __construct(
        private BookingRepository $bookings = new BookingRepository(),
        private PlatformCreditRepository $platformCredits = new PlatformCreditRepository(),
        private UserRepository $users = new UserRepository(),
        private TripRepository $trips = new TripRepository(),
        private ReviewRepository $reviews = new ReviewRepository(),
    ) {}

    /** Tableau de bord administrateur, calculé en MySQL (sert aussi de secours si MongoDB est arrêté). */
    public function adminDashboard(): array
    {
        return [
            'trips_by_day'    => $this->bookings->confirmedPerDay(self::DAYS),
            'credits_by_day'  => $this->platformCredits->perDay(self::DAYS),
            'total_credits'   => $this->platformCredits->total(),
            'total_users'     => $this->users->countByRole('user'),
            'total_employees' => $this->users->countByRole('employee'),
            'total_trips'     => $this->trips->countAll(),
        ];
    }

    public function employeeCounters(): array
    {
        return [
            'pending_reviews' => $this->reviews->countPending(),
            'reported_trips'  => $this->trips->countReported(),
        ];
    }

    public function totalPlatformCredits(): float
    {
        return $this->platformCredits->total();
    }

    // ── Journal MongoDB ───────────────────────────────────────
    public function bookingsPerDayFromLog(): array
    {
        return (new BookingLogRepository())->bookingsPerDay(self::DAYS);
    }

    public function platformCreditsPerDayFromLog(): array
    {
        return (new BookingLogRepository())->platformCreditsPerDay(self::DAYS);
    }
}
