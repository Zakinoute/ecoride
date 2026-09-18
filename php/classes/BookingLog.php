<?php
/**
 * BookingLog — journal des réservations dans MongoDB (NoSQL).
 *
 * Chaque réservation confirmée ajoute un document dans la collection
 * `bookings_log`. Les statistiques de l'administrateur regroupent ces
 * documents par jour avec un pipeline d'agrégation.
 *
 * Utilise le pilote natif : l'extension PHP `mongodb`, installée par le Dockerfile.
 */
class BookingLog
{
    private const COLLECTION = 'bookings_log';

    private MongoDB\Driver\Manager $manager;
    private string $database;

    public function __construct()
    {
        $this->manager  = new MongoDB\Driver\Manager(getenv('MONGO_URI') ?: 'mongodb://localhost:27017');
        $this->database = getenv('MONGO_DB') ?: 'ecoride_stats';
    }

    /** Ajoute un document pour une réservation confirmée. */
    public function record(int $bookingId, int $tripId, int $credits, int $platformFee, bool $isEco): void
    {
        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->insert([
            'booking_id'   => $bookingId,
            'trip_id'      => $tripId,
            'credits'      => $credits,
            'platform_fee' => $platformFee,
            'is_eco'       => $isEco,
            'date'         => date('Y-m-d'),
            'created_at'   => new MongoDB\BSON\UTCDateTime(),
        ]);
        $this->manager->executeBulkWrite($this->database . '.' . self::COLLECTION, $bulk);
    }

    /** Retire les réservations d'un trajet annulé : il n'a pas eu lieu. */
    public function forgetTrip(int $tripId): void
    {
        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->delete(['trip_id' => $tripId]);
        $this->manager->executeBulkWrite($this->database . '.' . self::COLLECTION, $bulk);
    }

    /** Nombre de covoiturages réservés par jour, sur les $days derniers jours. */
    public function bookingsPerDay(int $days = 30): array
    {
        $rows = $this->aggregate([
            ['$match' => ['date' => ['$gte' => $this->since($days)]]],
            ['$group' => ['_id' => '$date', 'count' => ['$sum' => 1]]],
            ['$sort'  => ['_id' => 1]],
        ]);
        return array_map(fn($row) => ['date' => $row->_id, 'count' => (int) $row->count], $rows);
    }

    /** Crédits gagnés par la plateforme par jour, sur les $days derniers jours. */
    public function platformCreditsPerDay(int $days = 30): array
    {
        $rows = $this->aggregate([
            ['$match' => ['date' => ['$gte' => $this->since($days)]]],
            ['$group' => ['_id' => '$date', 'credits' => ['$sum' => '$platform_fee']]],
            ['$sort'  => ['_id' => 1]],
        ]);
        return array_map(fn($row) => ['date' => $row->_id, 'credits' => (int) $row->credits], $rows);
    }

    private function aggregate(array $pipeline): array
    {
        $command = new MongoDB\Driver\Command([
            'aggregate' => self::COLLECTION,
            'pipeline'  => $pipeline,
            'cursor'    => new stdClass(),
        ]);
        return $this->manager->executeCommand($this->database, $command)->toArray();
    }

    private function since(int $days): string
    {
        return date('Y-m-d', strtotime("-{$days} days"));
    }
}
