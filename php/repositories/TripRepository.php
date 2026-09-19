<?php
/**
 * TripRepository — requêtes SQL de la table `trips`.
 */
class TripRepository extends Repository
{
    /** Colonnes autorisées pour le tri : on ne met jamais une saisie brute dans ORDER BY. */
    private const ALLOWED_SORTS = ['departure_datetime', 'price', 'available_seats'];

    // ── Recherche et consultation ─────────────────────────────
    /** Trajets à venir avec des places libres, filtrés selon les critères. */
    public function search(array $criteria): array
    {
        $sortBy = in_array($criteria['sort'] ?? '', self::ALLOWED_SORTS, true)
            ? $criteria['sort']
            : 'departure_datetime';

        $sql = "
            SELECT t.*, u.pseudo AS driver_pseudo, u.photo AS driver_photo,
                   COALESCE(AVG(r.rating), 0) AS avg_rating, COUNT(r.id) AS review_count,
                   v.brand, v.model, v.energy, v.color
            FROM trips t
            JOIN users u ON u.id = t.driver_id
            JOIN vehicles v ON v.id = t.vehicle_id
            LEFT JOIN reviews r ON r.reviewed_id = t.driver_id AND r.status = 'approved'
            WHERE t.status = 'active'
              AND t.available_seats > 0
              AND t.departure_datetime >= NOW()
        ";
        $params = [];

        if (!empty($criteria['departure'])) {
            $sql .= " AND t.departure_city LIKE ?";
            $params[] = '%' . $criteria['departure'] . '%';
        }
        if (!empty($criteria['arrival'])) {
            $sql .= " AND t.arrival_city LIKE ?";
            $params[] = '%' . $criteria['arrival'] . '%';
        }
        if (!empty($criteria['date'])) {
            $sql .= " AND DATE(t.departure_datetime) = ?";
            $params[] = $criteria['date'];
        }
        if (isset($criteria['max_price'])) {
            $sql .= " AND t.price <= ?";
            $params[] = (float) $criteria['max_price'];
        }
        if (!empty($criteria['eco'])) {
            $sql .= " AND v.energy = 'electrique'";
        }

        $sql .= " GROUP BY t.id";

        if (isset($criteria['min_rating'])) {
            $sql .= " HAVING avg_rating >= ?";
            $params[] = (float) $criteria['min_rating'];
        }

        $sql .= " ORDER BY $sortBy ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Détail d'un trajet : chauffeur, note moyenne, véhicule et préférences. */
    public function findDetail(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, u.pseudo AS driver_pseudo, u.photo AS driver_photo,
                   COALESCE(AVG(r.rating), 0) AS avg_rating, COUNT(r.id) AS review_count,
                   v.brand, v.model, v.energy, v.color, v.seats AS total_vehicle_seats,
                   dp.smoking, dp.animals, dp.music, dp.chat, dp.other_preferences
            FROM trips t
            JOIN users u ON u.id = t.driver_id
            JOIN vehicles v ON v.id = t.vehicle_id
            LEFT JOIN driver_preferences dp ON dp.user_id = t.driver_id
            LEFT JOIN reviews r ON r.reviewed_id = t.driver_id AND r.status = 'approved'
            WHERE t.id = ?
            GROUP BY t.id
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Trajets d'un utilisateur, comme chauffeur et comme passager. */
    public function findByUser(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, 'driver' AS my_role,
                   v.brand, v.model, v.energy,
                   (SELECT COUNT(*) FROM bookings WHERE trip_id = t.id AND status = 'confirmed') AS booked_count
            FROM trips t
            JOIN vehicles v ON v.id = t.vehicle_id
            WHERE t.driver_id = ?
            UNION ALL
            SELECT t.*, 'passenger' AS my_role,
                   v.brand, v.model, v.energy,
                   (SELECT COUNT(*) FROM bookings WHERE trip_id = t.id AND status = 'confirmed') AS booked_count
            FROM trips t
            JOIN vehicles v ON v.id = t.vehicle_id
            JOIN bookings b ON b.trip_id = t.id AND b.passenger_id = ?
            ORDER BY departure_datetime DESC
        ");
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll();
    }

    /** Chauffeur d'un trajet terminé, si ce passager y a participé (sinon null). */
    public function findCompletedDriverFor(int $tripId, int $passengerId): ?int
    {
        $stmt = $this->db->prepare("
            SELECT t.driver_id FROM trips t
            JOIN bookings b ON b.trip_id = t.id AND b.passenger_id = ?
            WHERE t.id = ? AND t.status = 'completed'
        ");
        $stmt->execute([$passengerId, $tripId]);
        $row = $stmt->fetch();
        return $row ? (int) $row['driver_id'] : null;
    }

    // ── Écriture ──────────────────────────────────────────────
    public function create(int $driverId, array $trip): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO trips (driver_id, vehicle_id, departure_address, departure_city, arrival_address, arrival_city,
                               departure_datetime, arrival_datetime, price, available_seats, total_seats)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $driverId, $trip['vehicle_id'], $trip['departure_address'], $trip['departure_city'],
            $trip['arrival_address'], $trip['arrival_city'], $trip['departure_datetime'],
            $trip['arrival_datetime'] ?: null, $trip['price'], $trip['seats'], $trip['seats'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Passe le trajet de « active » à « started » ; false si ce n'est pas possible. */
    public function start(int $tripId, int $driverId): bool
    {
        $stmt = $this->db->prepare("UPDATE trips SET status = 'started' WHERE id = ? AND driver_id = ? AND status = 'active'");
        $stmt->execute([$tripId, $driverId]);
        return $stmt->rowCount() > 0;
    }

    public function setStatus(int $tripId, string $status): void
    {
        $this->db->prepare('UPDATE trips SET status = ? WHERE id = ?')->execute([$status, $tripId]);
    }

    public function takeSeat(int $tripId): void
    {
        $this->db->prepare('UPDATE trips SET available_seats = available_seats - 1 WHERE id = ?')->execute([$tripId]);
    }

    public function report(int $tripId, string $reason): void
    {
        $this->db->prepare('UPDATE trips SET is_reported = 1, report_reason = ? WHERE id = ?')->execute([$reason, $tripId]);
    }

    // ── Verrous (à utiliser dans une transaction) ─────────────
    /**
     * Trajet réservable, verrouillé (FOR UPDATE) jusqu'à la fin de la transaction :
     * deux réservations simultanées ne peuvent pas prendre la même dernière place.
     */
    public function lockBookable(int $tripId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, v.energy
            FROM trips t
            JOIN vehicles v ON v.id = t.vehicle_id
            WHERE t.id = ? AND t.status = 'active' AND t.available_seats > 0
            FOR UPDATE
        ");
        $stmt->execute([$tripId]);
        return $stmt->fetch() ?: null;
    }

    /** Trajet de ce chauffeur dans l'état demandé, verrouillé jusqu'à la fin de la transaction. */
    public function lockForDriver(int $tripId, int $driverId, string $status): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM trips WHERE id = ? AND driver_id = ? AND status = ? FOR UPDATE');
        $stmt->execute([$tripId, $driverId, $status]);
        return $stmt->fetch() ?: null;
    }

    // ── Espace employé et statistiques ────────────────────────
    /** Trajets signalés, avec le chauffeur et la liste des passagers. */
    public function findReported(): array
    {
        $stmt = $this->db->prepare("
            SELECT t.id, t.departure_city, t.arrival_city, t.departure_datetime,
                   t.report_reason, t.status,
                   d.id AS driver_id, d.pseudo AS driver_pseudo, d.email AS driver_email,
                   GROUP_CONCAT(DISTINCT p.pseudo ORDER BY p.pseudo SEPARATOR ', ') AS passengers
            FROM trips t
            JOIN users d ON d.id = t.driver_id
            LEFT JOIN bookings b ON b.trip_id = t.id AND b.status = 'confirmed'
            LEFT JOIN users p ON p.id = b.passenger_id
            WHERE t.is_reported = 1
            GROUP BY t.id
            ORDER BY t.departure_datetime DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countReported(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM trips WHERE is_reported = 1')->fetchColumn();
    }

    public function countAll(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM trips')->fetchColumn();
    }

    /** Trajets à venir ou en cours qui utilisent ce véhicule. */
    public function countActiveForVehicle(int $vehicleId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM trips WHERE vehicle_id = ? AND status IN ('active', 'started')");
        $stmt->execute([$vehicleId]);
        return (int) $stmt->fetchColumn();
    }
}
