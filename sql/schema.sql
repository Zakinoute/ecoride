-- ============================================================
-- EcoRide - Schéma de base de données
-- ============================================================

-- CREATE DATABASE et USE supprimés pour hébergement mutualisé (InfinityFree, Alwaysdata...)
-- La base doit être créée manuellement dans le panneau d'hébergement avant l'import.

-- ============================================================
-- Suppression des tables existantes (ordre inverse des FK)
-- ============================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS platform_credits;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS trips;
DROP TABLE IF EXISTS driver_preferences;
DROP TABLE IF EXISTS vehicles;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Table des utilisateurs
-- ============================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pseudo VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user','employee','admin') DEFAULT 'user',
    status ENUM('active','suspended') DEFAULT 'active',
    credits INT DEFAULT 20,
    photo VARCHAR(255) DEFAULT 'default-avatar.png',
    is_driver TINYINT(1) DEFAULT 0,
    is_passenger TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- Table des véhicules
-- ============================================================
CREATE TABLE vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plate VARCHAR(20) NOT NULL UNIQUE,
    first_registration DATE NOT NULL,
    brand VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    color VARCHAR(30) NOT NULL,
    seats INT NOT NULL DEFAULT 4,
    energy ENUM('essence','diesel','electrique','hybride') NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- Table des préférences chauffeur
-- ============================================================
CREATE TABLE driver_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    smoking TINYINT(1) DEFAULT 0,
    animals TINYINT(1) DEFAULT 0,
    music TINYINT(1) DEFAULT 1,
    chat TINYINT(1) DEFAULT 1,
    other_preferences TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- Table des trajets
-- ============================================================
CREATE TABLE trips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    departure_address VARCHAR(255) NOT NULL,
    departure_city VARCHAR(100) NOT NULL,
    arrival_address VARCHAR(255) NOT NULL,
    arrival_city VARCHAR(100) NOT NULL,
    departure_datetime DATETIME NOT NULL,
    arrival_datetime DATETIME,
    price DECIMAL(6,2) NOT NULL,
    available_seats INT NOT NULL,
    total_seats INT NOT NULL,
    status ENUM('active','started','completed','cancelled') DEFAULT 'active',
    is_reported TINYINT(1) DEFAULT 0,
    report_reason TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
);

-- ============================================================
-- Table des réservations
-- ============================================================
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_id INT NOT NULL,
    passenger_id INT NOT NULL,
    credits_used INT NOT NULL,
    status ENUM('confirmed','cancelled') DEFAULT 'confirmed',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_booking (trip_id, passenger_id),
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    FOREIGN KEY (passenger_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- Table des avis
-- ============================================================
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    reviewed_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- Table des crédits plateforme (gains EcoRide)
-- ============================================================
CREATE TABLE platform_credits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_id INT NOT NULL,
    booking_id INT NOT NULL,
    amount DECIMAL(6,2) NOT NULL,
    earned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES trips(id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id)
);

-- NOTE : La vue driver_ratings a été supprimée car CREATE VIEW est interdit
-- sur les hébergements mutualisés (InfinityFree, Alwaysdata...).
-- Le calcul de la note moyenne est effectué directement dans les requêtes PHP.

-- ============================================================
-- Index pour les performances
-- ============================================================
CREATE INDEX idx_trips_departure_city ON trips(departure_city);
CREATE INDEX idx_trips_arrival_city ON trips(arrival_city);
CREATE INDEX idx_trips_departure_datetime ON trips(departure_datetime);
CREATE INDEX idx_trips_status ON trips(status);
CREATE INDEX idx_bookings_trip ON bookings(trip_id);
CREATE INDEX idx_bookings_passenger ON bookings(passenger_id);
CREATE INDEX idx_reviews_reviewed ON reviews(reviewed_id);
CREATE INDEX idx_reviews_status ON reviews(status);
