<?php
/**
 * Trip — gestion des trajets de covoiturage.
 */
class Trip
{
    /** Colonnes autorisées pour le tri : on ne met jamais une saisie brute dans ORDER BY. */
    private const ALLOWED_SORTS = ['departure_datetime', 'price', 'available_seats'];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // ── Recherche publique ────────────────────────────────────
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

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map([$this, 'withEcoFlag'], $stmt->fetchAll());
    }

    // ── Détail d'un trajet ────────────────────────────────────
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
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
        $trip = $stmt->fetch();
        return $trip ? $this->withEcoFlag($trip) : null;
    }

    // ── Publication (chauffeur) ───────────────────────────────
    public function create(int $driverId, array $data): array
    {
        $stmt = $this->pdo->prepare('SELECT is_driver FROM users WHERE id = ?');
        $stmt->execute([$driverId]);
        $user = $stmt->fetch();
        if (!$user || !$user['is_driver']) {
            return ['success' => false, 'message' => 'Vous devez être enregistré comme chauffeur.'];
        }

        if (!$data['departure_address'] || !$data['departure_city'] || !$data['arrival_address'] || !$data['arrival_city']
            || !$data['departure_datetime'] || !$data['vehicle_id'] || $data['price'] <= 0 || $data['seats'] < 1) {
            return ['success' => false, 'message' => 'Tous les champs sont obligatoires.'];
        }

        // Le véhicule doit appartenir au chauffeur ; une place reste pour lui.
        $stmt = $this->pdo->prepare('SELECT id, seats FROM vehicles WHERE id = ? AND user_id = ? AND is_active = 1');
        $stmt->execute([$data['vehicle_id'], $driverId]);
        $vehicle = $stmt->fetch();
        if (!$vehicle) {
            return ['success' => false, 'message' => 'Véhicule invalide.'];
        }
        if ($data['seats'] > $vehicle['seats'] - 1) {
            return ['success' => false, 'message' => 'Nombre de places trop élevé pour ce véhicule.'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO trips (driver_id, vehicle_id, departure_address, departure_city, arrival_address, arrival_city,
                               departure_datetime, arrival_datetime, price, available_seats, total_seats)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $driverId, $data['vehicle_id'], $data['departure_address'], $data['departure_city'],
            $data['arrival_address'], $data['arrival_city'], $data['departure_datetime'],
            $data['arrival_datetime'] ?: null, $data['price'], $data['seats'], $data['seats'],
        ]);

        return ['success' => true, 'message' => 'Trajet publié avec succès !', 'trip_id' => (int) $this->pdo->lastInsertId()];
    }

    // ── Démarrer ──────────────────────────────────────────────
    public function start(int $tripId, int $driverId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE trips SET status = 'started' WHERE id = ? AND driver_id = ? AND status = 'active'");
        $stmt->execute([$tripId, $driverId]);
        return $stmt->rowCount() > 0;
    }

    // ── Terminer et payer le chauffeur ────────────────────────
    public function complete(int $tripId, int $driverId): array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM trips WHERE id = ? AND driver_id = ? AND status = 'started' FOR UPDATE");
            $stmt->execute([$tripId, $driverId]);
            $trip = $stmt->fetch();
            if (!$trip) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Trajet non trouvé.'];
            }

            // Gain du chauffeur = crédits payés par les passagers - commission par réservation
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) AS nb, COALESCE(SUM(credits_used), 0) AS total
                FROM bookings WHERE trip_id = ? AND status = 'confirmed'
            ");
            $stmt->execute([$tripId]);
            $bookings = $stmt->fetch();
            $earned = (int) $bookings['total'] - Booking::PLATFORM_FEE * (int) $bookings['nb'];

            $this->pdo->prepare("UPDATE trips SET status = 'completed' WHERE id = ?")->execute([$tripId]);
            if ($earned > 0) {
                $this->pdo->prepare('UPDATE users SET credits = credits + ? WHERE id = ?')->execute([$earned, $driverId]);
            }
            $this->pdo->commit();
            return ['success' => true, 'message' => 'Trajet terminé ! Crédits crédités.', 'earned' => max($earned, 0)];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => 'Erreur.'];
        }
    }

    // ── Annuler et rembourser les passagers ───────────────────
    public function cancel(int $tripId, int $driverId): array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM trips WHERE id = ? AND driver_id = ? AND status = 'active' FOR UPDATE");
            $stmt->execute([$tripId, $driverId]);
            if (!$stmt->fetch()) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Trajet introuvable ou déjà commencé.'];
            }

            $stmt = $this->pdo->prepare("SELECT * FROM bookings WHERE trip_id = ? AND status = 'confirmed'");
            $stmt->execute([$tripId]);
            foreach ($stmt->fetchAll() as $booking) {
                $this->pdo->prepare('UPDATE users SET credits = credits + ? WHERE id = ?')
                          ->execute([$booking['credits_used'], $booking['passenger_id']]);
                $this->pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")
                          ->execute([$booking['id']]);
            }
            // Le trajet n'a pas lieu : la plateforme ne garde pas sa commission.
            $this->pdo->prepare('DELETE FROM platform_credits WHERE trip_id = ?')->execute([$tripId]);
            $this->pdo->prepare("UPDATE trips SET status = 'cancelled' WHERE id = ?")->execute([$tripId]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => 'Erreur lors de l\'annulation.'];
        }

        // Même règle dans les statistiques MongoDB (une panne MongoDB ne bloque pas l'annulation).
        try {
            (new BookingLog())->forgetTrip($tripId);
        } catch (Throwable $e) {
            error_log('EcoRide - journal MongoDB indisponible : ' . $e->getMessage());
        }

        return ['success' => true, 'message' => 'Trajet annulé. Les passagers ont été remboursés.'];
    }

    // ── Trajets d'un utilisateur (chauffeur et passager) ──────
    public function findByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare("
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

    // ── Signalement d'un trajet mal passé ─────────────────────
    public function report(int $tripId, string $reason): void
    {
        $this->pdo->prepare('UPDATE trips SET is_reported = 1, report_reason = ? WHERE id = ?')->execute([$reason, $tripId]);
    }

    /** Un trajet est « écologique » quand la voiture est électrique. */
    private function withEcoFlag(array $trip): array
    {
        $trip['is_eco']     = $trip['energy'] === 'electrique';
        $trip['avg_rating'] = round((float) $trip['avg_rating'], 1);
        return $trip;
    }
}
