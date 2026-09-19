<?php
/**
 * Review — avis laissés par les passagers sur les chauffeurs.
 * Un avis n'est visible qu'après validation par un employé.
 */
class Review
{
    private const DECISIONS = ['approved', 'rejected'];

    public function __construct(
        private ReviewRepository $reviews = new ReviewRepository(),
        private TripRepository $trips = new TripRepository(),
    ) {}

    // ── Dépôt d'un avis (passager) ────────────────────────────
    public function submit(int $tripId, int $reviewerId, int $rating, string $comment): void
    {
        if (!$tripId || $rating < 1 || $rating > 5) {
            throw new BusinessRuleException('Note invalide (1 à 5 requis).');
        }

        // Le trajet doit être terminé et l'auteur doit y avoir participé.
        $driverId = $this->trips->findCompletedDriverFor($tripId, $reviewerId);
        if ($driverId === null) {
            throw new BusinessRuleException('Vous ne pouvez noter que les trajets terminés auxquels vous avez participé.');
        }
        if ($this->reviews->exists($tripId, $reviewerId)) {
            throw new BusinessRuleException('Vous avez déjà laissé un avis pour ce trajet.');
        }

        $this->reviews->create($tripId, $reviewerId, $driverId, $rating, $comment);
    }

    // ── Modération (employé) ──────────────────────────────────
    public function pending(): array
    {
        return $this->reviews->findPending();
    }

    public function moderate(int $reviewId, string $decision): void
    {
        if (!in_array($decision, self::DECISIONS, true)) {
            throw new BusinessRuleException('Décision invalide.');
        }
        $this->reviews->setStatus($reviewId, $decision);
    }

    // ── Avis validés d'un chauffeur (public) ──────────────────
    public function forDriver(int $driverId, int $limit = 50): array
    {
        return $this->reviews->findApprovedByDriver($driverId, $limit);
    }
}
