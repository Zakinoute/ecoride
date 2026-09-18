<?php
/**
 * EcoRide — Statistiques MongoDB (NoSQL)
 * =========================================================
 * Endpoints (administrateur) :
 *   GET ?action=stats_trips   → nombre de covoiturages réservés par jour (30 derniers jours)
 *   GET ?action=stats_credits → crédits gagnés par la plateforme par jour
 *   GET ?action=total_credits → total des crédits plateforme (MySQL)
 *
 * Les documents MongoDB sont écrits par Booking::book() à chaque réservation.
 * Si MongoDB est indisponible, la réponse est { success: false } et le tableau
 * de bord affiche les chiffres calculés en MySQL (voir js/admin.js).
 * =========================================================
 */

require_once __DIR__ . '/config.php';
startSession();
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

switch ($action) {

    // ── Nombre de covoiturages par jour ───────────────────────
    case 'stats_trips':
        requireRole('admin');
        try {
            jsonResponse(true, '', ['stats' => (new BookingLog())->bookingsPerDay(30)]);
        } catch (Throwable $e) {
            jsonResponse(false, 'Statistiques MongoDB indisponibles.');
        }
        break;

    // ── Crédits plateforme par jour ───────────────────────────
    case 'stats_credits':
        requireRole('admin');
        try {
            jsonResponse(true, '', ['stats' => (new BookingLog())->platformCreditsPerDay(30)]);
        } catch (Throwable $e) {
            jsonResponse(false, 'Statistiques MongoDB indisponibles.');
        }
        break;

    // ── Total crédits plateforme (MySQL) ──────────────────────
    case 'total_credits':
        requireRole('admin');
        $stmt = getPDO()->query('SELECT COALESCE(SUM(amount), 0) AS total FROM platform_credits');
        jsonResponse(true, '', ['total' => (float) $stmt->fetch()['total']]);
        break;

    default:
        jsonResponse(false, 'Action non reconnue.');
}
