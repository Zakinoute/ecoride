<?php
// ============================================================
// EcoRide - Espace Administrateur
// ============================================================
require_once 'config.php';
startSession();

header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ----------------------------------------------------------
    // Créer un compte employé
    // ----------------------------------------------------------
    case 'create_employee':
        requireRole('admin');
        $session  = requireAuth();
        $pseudo   = sanitize($_POST['pseudo'] ?? '');
        $email    = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (!$pseudo || !$email || !$password) jsonResponse(false, 'Tous les champs sont obligatoires.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(false, 'Email invalide.');
        if (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
            jsonResponse(false, 'Mot de passe trop faible.');
        }

        $pdo  = getPDO();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? OR pseudo = ?');
        $stmt->execute([$email, $pseudo]);
        if ($stmt->fetch()) jsonResponse(false, 'Ce pseudo ou email est déjà utilisé.');

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO users (pseudo, email, password, role) VALUES (?, ?, ?, "employee")')->execute([$pseudo, $email, $hash]);

        jsonResponse(true, 'Compte employé créé.', ['employee_id' => $pdo->lastInsertId()]);
        break;

    // ----------------------------------------------------------
    // Statistiques graphiques
    // ----------------------------------------------------------
    case 'stats':
        requireRole('admin');
        $pdo = getPDO();

        // Covoiturages réservés par jour (30 derniers jours) : même mesure que MongoDB
        $stmt = $pdo->query("
            SELECT DATE(created_at) AS day, COUNT(*) AS count
            FROM bookings
            WHERE status = 'confirmed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY day
            ORDER BY day ASC
        ");
        $tripsByDay = $stmt->fetchAll();

        // Crédits gagnés par jour par la plateforme
        $stmt = $pdo->query("
            SELECT DATE(earned_at) AS day, SUM(amount) AS total
            FROM platform_credits
            WHERE earned_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY day
            ORDER BY day ASC
        ");
        $creditsByDay = $stmt->fetchAll();

        // Total crédits plateforme
        $totalCredits = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM platform_credits")->fetchColumn();

        // Comptes total
        $totalUsers     = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
        $totalEmployees = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'employee'")->fetchColumn();
        $totalTrips     = $pdo->query("SELECT COUNT(*) FROM trips")->fetchColumn();

        jsonResponse(true, '', [
            'trips_by_day'   => $tripsByDay,
            'credits_by_day' => $creditsByDay,
            'total_credits'  => (float)$totalCredits,
            'total_users'    => (int)$totalUsers,
            'total_employees'=> (int)$totalEmployees,
            'total_trips'    => (int)$totalTrips,
        ]);
        break;

    // ----------------------------------------------------------
    // Liste des utilisateurs
    // ----------------------------------------------------------
    case 'users':
        requireRole('admin');
        $search = sanitize($_GET['search'] ?? '');
        $role   = $_GET['role'] ?? '';
        $pdo    = getPDO();

        $sql    = "SELECT id, pseudo, email, role, status, credits, created_at FROM users WHERE 1=1";
        $params = [];
        if ($search) { $sql .= " AND (pseudo LIKE ? OR email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        if (in_array($role, ['user','employee','admin'])) { $sql .= " AND role = ?"; $params[] = $role; }
        $sql .= " ORDER BY created_at DESC LIMIT 100";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        jsonResponse(true, '', ['users' => $stmt->fetchAll()]);
        break;

    // ----------------------------------------------------------
    // Suspendre / réactiver un compte
    // ----------------------------------------------------------
    case 'toggle_status':
        requireRole('admin');
        $userId = (int)($_POST['user_id'] ?? 0);
        $pdo    = getPDO();

        $stmt = $pdo->prepare('SELECT status, role FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) jsonResponse(false, 'Utilisateur introuvable.');
        if ($user['role'] === 'admin') jsonResponse(false, 'Impossible de suspendre un administrateur.');

        $newStatus = $user['status'] === 'active' ? 'suspended' : 'active';
        $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$newStatus, $userId]);
        jsonResponse(true, 'Compte ' . ($newStatus === 'suspended' ? 'suspendu' : 'réactivé') . '.', ['new_status' => $newStatus]);
        break;

    default:
        jsonResponse(false, 'Action non reconnue.');
}
