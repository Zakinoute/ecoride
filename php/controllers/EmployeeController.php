<?php
/**
 * EmployeeController — espace employé : trajets signalés et compteurs.
 * (La modération des avis passe par ReviewController.)
 */
class EmployeeController extends Controller
{
    protected const ACTIONS = [
        'reported_trips' => 'reportedTrips',
        'stats'          => 'stats',
    ];

    public function __construct(
        private Trip $trips = new Trip(),
        private Statistics $statistics = new Statistics(),
    ) {
        parent::__construct();
    }

    protected function reportedTrips(): void
    {
        $this->requireRole('employee');
        $this->json(true, '', ['trips' => $this->trips->reported()]);
    }

    protected function stats(): void
    {
        $this->requireRole('employee');
        $this->json(true, '', $this->statistics->employeeCounters());
    }
}
