<?php
/**
 * Controller — base commune des contrôleurs (le « C » de MVC).
 *
 * Un contrôleur lit la requête HTTP, vérifie la session et le rôle,
 * appelle un modèle, puis renvoie la réponse en JSON.
 * Il ne contient ni règle métier ni requête SQL.
 */
abstract class Controller
{
    /** Liste blanche : action demandée → méthode du contrôleur. */
    protected const ACTIONS = [];

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => false, // mettre true en production (HTTPS)
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    /** Exécute l'action demandée. Une règle métier non respectée devient une réponse d'erreur. */
    public function handle(string $action): void
    {
        $method = static::ACTIONS[$action] ?? null;
        if ($method === null) {
            $this->json(false, 'Action non reconnue.');
        }
        try {
            $this->$method();
        } catch (BusinessRuleException $e) {
            $this->json(false, $e->getMessage());
        } catch (Throwable $e) {
            error_log('EcoRide - ' . $e->getMessage());
            $this->json(false, 'Une erreur est survenue. Réessayez plus tard.');
        }
    }

    // ── Réponse ───────────────────────────────────────────────
    protected function json(bool $success, string $message = '', array $data = []): never
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // ── Session et rôles ──────────────────────────────────────
    /** Renvoie l'id de l'utilisateur connecté, ou répond « Non authentifié ». */
    protected function requireAuth(): int
    {
        if (empty($_SESSION['user_id'])) {
            $this->json(false, 'Non authentifié.', ['redirect' => '../login.html']);
        }
        return (int) $_SESSION['user_id'];
    }

    /** L'administrateur a aussi accès à l'espace employé. */
    protected function requireRole(string $role): int
    {
        $userId  = $this->requireAuth();
        $current = $_SESSION['role'] ?? '';
        if ($current !== $role && !($role === 'employee' && $current === 'admin')) {
            $this->json(false, 'Accès refusé.');
        }
        return $userId;
    }

    // ── Lecture des données envoyées ──────────────────────────
    /** Valeur envoyée par un formulaire (POST) ou dans l'adresse (GET). */
    protected function raw(string $key): string
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? '';
        return is_string($value) ? $value : '';
    }

    protected function has(string $key): bool
    {
        return isset($_POST[$key]) || isset($_GET[$key]);
    }

    /** Texte saisi : espaces retirés, balises supprimées, caractères spéciaux échappés. */
    protected function text(string $key): string
    {
        return htmlspecialchars(strip_tags(trim($this->raw($key))), ENT_QUOTES, 'UTF-8');
    }

    protected function int(string $key): int
    {
        return (int) $this->raw($key);
    }

    protected function email(string $key): string
    {
        return (string) filter_var($this->raw($key), FILTER_SANITIZE_EMAIL);
    }

    /** Case à cocher : présente dans le formulaire = cochée. */
    protected function checked(string $key): bool
    {
        return isset($_POST[$key]);
    }
}
