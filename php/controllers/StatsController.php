<?php
/**
 * StatsController — statistiques de l'administrateur tirées du journal MongoDB (NoSQL).
 *
 * Si MongoDB est indisponible, la réponse est { success: false } et le tableau
 * de bord affiche les chiffres calculés en MySQL (voir js/admin.js).
 */
class StatsController extends Controller
{
    protected const ACTIONS = [
        'stats_trips'   => 'tripsPerDay',
        'stats_credits' => 'creditsPerDay',
        'total_credits' => 'totalCredits',
    ];

    public function __construct(private Statistics $statistics = new Statistics())
    {
        parent::__construct();
    }

    protected function tripsPerDay(): void
    {
        $this->requireRole('admin');
        $this->fromMongo(fn() => $this->statistics->bookingsPerDayFromLog());
    }

    protected function creditsPerDay(): void
    {
        $this->requireRole('admin');
        $this->fromMongo(fn() => $this->statistics->platformCreditsPerDayFromLog());
    }

    /** Total des crédits de la plateforme (MySQL, qui fait foi). */
    protected function totalCredits(): void
    {
        $this->requireRole('admin');
        $this->json(true, '', ['total' => $this->statistics->totalPlatformCredits()]);
    }

    private function fromMongo(callable $query): void
    {
        try {
            $stats = $query();
        } catch (Throwable $e) {
            $this->json(false, 'Statistiques MongoDB indisponibles.');
        }
        $this->json(true, '', ['stats' => $stats]);
    }
}
