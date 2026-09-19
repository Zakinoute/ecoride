<?php
/**
 * ReviewController — dépôt des avis, modération par l'employé, avis publics d'un chauffeur.
 */
class ReviewController extends Controller
{
    protected const ACTIONS = [
        'submit'   => 'submit',
        'pending'  => 'pending',
        'moderate' => 'moderate',
        'driver'   => 'driver',
    ];

    public function __construct(private Review $reviews = new Review())
    {
        parent::__construct();
    }

    protected function submit(): void
    {
        $this->reviews->submit($this->int('trip_id'), $this->requireAuth(), $this->int('rating'), $this->text('comment'));
        $this->json(true, 'Avis soumis. Il sera visible après validation par notre équipe.');
    }

    protected function pending(): void
    {
        $this->requireRole('employee');
        $this->json(true, '', ['reviews' => $this->reviews->pending()]);
    }

    protected function moderate(): void
    {
        $this->requireRole('employee');
        $decision = $this->raw('decision');
        $this->reviews->moderate($this->int('review_id'), $decision);
        $this->json(true, 'Avis ' . ($decision === 'approved' ? 'validé' : 'refusé') . '.');
    }

    protected function driver(): void
    {
        $driverId = $this->int('driver_id');
        if (!$driverId) {
            $this->json(false, 'ID manquant.');
        }
        $this->json(true, '', ['reviews' => $this->reviews->forDriver($driverId)]);
    }
}
