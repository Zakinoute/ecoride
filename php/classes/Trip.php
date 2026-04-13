<?php
require_once __DIR__ . '/Database.php';

/**
 * Trip — gestion des trajets de covoiturage.
 */
class Trip
{
    private PDO $pdo;
    private const PLATFORM_FEE = 2; // crédits prélevés par EcoRide

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // ── Recherche ─────────────────────────────────────────────
    public function search(string $fromCity, string $toCity, string $date, array $filters = []): array
    {
        $sql = "
            SELECT t.*,
                   u.pseudo, u.photo,
                   v.energy, v.brand, v.model, v.color,
                   COALESCE(AVG(r.rating), 0) AS driver_rating
            FROM trips t
            JOIN users u   ON t.driver_id  = u.id
            JOIN vehicles v ON t.vehicle_id = v.id
            LEFT JOIN reviews r ON r.reviewed_id = u.id AND r.status = 'approved'
            WHERE t.departure_city = ?
              AND t.arrival_city   = ?
              AND DATE(t.departure_datetime) = ?
              AND t.status = 'active'
              AND t.available_seats > 0
        ";
        $params = [$fromCity, $toCity, $date];

        if (!empty($filters['eco'])) {
            $sql .= " AND v.energy = 'electrique'";
        }
        if (!empty($filters['max_price'])) {
            $sql .= " AND t.price <= ?";
            $params[] = (float) $filters['max_price'];
        }
        if (!empty($filters['min_rating'])) {
            $sql .= " HAVING driver_rating >= ?";
            $params[] = (float) $filters['min_rating'];
        }

        $sql .= " GROUP BY t.id ORDER BY t.departure_datetime ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ── Détail ────────────────────────────────────────────────
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.*,
                   u.pseudo, u.photo,
                   v.energy, v.brand, v.model, v.color, v.plate,
                   dp.smoking, dp.animals, dp.music, dp.chat, dp.other_preferences,
                   COALESCE(AVG(r.rating), 0) AS driver_rating
            FROM trips t
            JOIN users u    ON t.driver_id  = u.id
            JOIN vehicles v ON t.vehicle_id = v.id
            LEFT JOIN driver_preferences dp ON dp.user_id = u.id
            LEFT JOIN reviews r ON r.reviewed_id = u.id AND r.status = 'approved'
            WHERE t.id = ?
            GROUP BY t.id
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    // ── Création ──────────────────────────────────────────────
    public function create(int $driverId, array $data): array
    {
        $required = ['vehicle_id','departure_address','departure_city','arrival_address','arrival_city','departure_datetime','price','seats'];
        foreach ($required as $f) {
            if (empty($data[$f])) {
                return ['success' => false, 'message' => "Champ manquant : {$f}"];
            }
        }
        $stmt = $this->pdo->prepare("
            INSERT INTO trips
              (driver_id, vehicle_id, departure_address, departure_city,
               arrival_address, arrival_city, departure_datetime, price,
               available_seats, total_seats)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $driverId,
            $data['vehicle_id'],
            $data['departure_address'],
            $data['departure_city'],
            $data['arrival_address'],
            $data['arrival_city'],
            $data['departure_datetime'],
            $data['price'],
            $data['seats'],
            $data['seats'],
        ]);
        return ['success' => true, 'id' => (int) $this->pdo->lastInsertId()];
    }

    // ── Démarrer / Terminer ───────────────────────────────────
    public function start(int $tripId, int $driverId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE trips SET status = 'started' WHERE id = ? AND driver_id = ? AND status = 'active'"
        );
        return $stmt->execute([$tripId, $driverId]);
    }

    public function complete(int $tripId, int $driverId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE trips SET status = 'completed' WHERE id = ? AND driver_id = ? AND status = 'started'"
        );
        return $stmt->execute([$tripId, $driverId]);
    }

    // ── Annuler ───────────────────────────────────────────────
    public function cancel(int $tripId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE trips SET status = 'cancelled' WHERE id = ? AND driver_id = ? AND status = 'active'"
        );
        return $stmt->execute([$tripId, $userId]);
    }

    // ── Historique ────────────────────────────────────────────
    public function getByDriver(int $driverId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.*, v.brand, v.model, v.energy
            FROM trips t
            JOIN vehicles v ON t.vehicle_id = v.id
            WHERE t.driver_id = ?
            ORDER BY t.departure_datetime DESC
        ");
        $stmt->execute([$driverId]);
        return $stmt->fetchAll();
    }

    public function getByPassenger(int $passengerId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.*, u.pseudo AS driver_pseudo, v.brand, v.model, v.energy, b.status AS booking_status
            FROM bookings b
            JOIN trips t    ON b.trip_id   = t.id
            JOIN users u    ON t.driver_id = u.id
            JOIN vehicles v ON t.vehicle_id = v.id
            WHERE b.passenger_id = ?
            ORDER BY t.departure_datetime DESC
        ");
        $stmt->execute([$passengerId]);
        return $stmt->fetchAll();
    }

    // ── Prochain trajet disponible (suggestion date) ──────────
    public function nextAvailable(string $fromCity, string $toCity): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT DATE(departure_datetime) AS d
            FROM trips
            WHERE departure_city = ? AND arrival_city = ?
              AND status = 'active' AND available_seats > 0
              AND departure_datetime > NOW()
            ORDER BY departure_datetime ASC
            LIMIT 1
        ");
        $stmt->execute([$fromCity, $toCity]);
        $row = $stmt->fetch();
        return $row ? $row['d'] : null;
    }
}
