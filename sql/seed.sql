-- ============================================================
-- EcoRide - Données de test
-- ============================================================
-- USE supprimé pour hébergement mutualisé : la base est déjà sélectionnée dans phpMyAdmin.

-- Mots de passe hashés = "Password1!" pour tous les comptes de test
-- hash généré avec password_hash('Password1!', PASSWORD_DEFAULT)

INSERT INTO users (pseudo, email, password, role, status, credits, is_driver, is_passenger) VALUES
('AdminEco',   'admin@ecoride.fr',    '$2y$12$LmQX3kVX2vGz0rF8N1aOcumvOjfHvT5wL4rN9eK1bHrQzVcA6y3ki', 'admin',    'active', 100, 0, 0),
('EmployeeLuc','employe@ecoride.fr',  '$2y$12$LmQX3kVX2vGz0rF8N1aOcumvOjfHvT5wL4rN9eK1bHrQzVcA6y3ki', 'employee', 'active', 50,  0, 0),
('SophiaD',    'sophia@example.com',  '$2y$12$LmQX3kVX2vGz0rF8N1aOcumvOjfHvT5wL4rN9eK1bHrQzVcA6y3ki', 'user',     'active', 45,  1, 1),
('MarcoV',     'marco@example.com',   '$2y$12$LmQX3kVX2vGz0rF8N1aOcumvOjfHvT5wL4rN9eK1bHrQzVcA6y3ki', 'user',     'active', 30,  1, 1),
('ClaireB',    'claire@example.com',  '$2y$12$LmQX3kVX2vGz0rF8N1aOcumvOjfHvT5wL4rN9eK1bHrQzVcA6y3ki', 'user',     'active', 60,  0, 1),
('TomR',       'tom@example.com',     '$2y$12$LmQX3kVX2vGz0rF8N1aOcumvOjfHvT5wL4rN9eK1bHrQzVcA6y3ki', 'user',     'active', 20,  1, 0);

-- Véhicules
INSERT INTO vehicles (user_id, plate, first_registration, brand, model, color, seats, energy) VALUES
(3, 'AB-123-CD', '2021-03-15', 'Tesla',   'Model 3',  'Blanc',  4, 'electrique'),
(4, 'EF-456-GH', '2019-07-20', 'Renault', 'Zoe',      'Bleu',   4, 'electrique'),
(6, 'IJ-789-KL', '2018-01-10', 'Peugeot', '308',      'Gris',   5, 'diesel');

-- Préférences chauffeurs
INSERT INTO driver_preferences (user_id, smoking, animals, music, chat) VALUES
(3, 0, 1, 1, 1),
(4, 0, 0, 1, 0),
(6, 1, 0, 0, 1);

-- Trajets
INSERT INTO trips (driver_id, vehicle_id, departure_address, departure_city, arrival_address, arrival_city, departure_datetime, arrival_datetime, price, available_seats, total_seats, status) VALUES
(3, 1, '12 Rue de la Paix, Paris',     'Paris',    '5 Avenue de la Liberté, Lyon',   'Lyon',      '2026-04-10 08:00:00', '2026-04-10 12:00:00', 15.00, 3, 4, 'active'),
(4, 2, '3 Boulevard Victor Hugo, Lyon','Lyon',     '8 Rue Gambetta, Marseille',       'Marseille', '2026-04-10 09:00:00', '2026-04-10 13:30:00', 12.00, 2, 4, 'active'),
(3, 1, '12 Rue de la Paix, Paris',     'Paris',    '15 Rue des Fleurs, Bordeaux',     'Bordeaux',  '2026-04-12 07:30:00', '2026-04-12 13:00:00', 18.00, 1, 4, 'active'),
(6, 3, '7 Allée des Roses, Toulouse',  'Toulouse', '2 Rue du Port, Montpellier',      'Montpellier','2026-04-11 14:00:00','2026-04-11 16:30:00', 8.00,  3, 5, 'active'),
(4, 2, '3 Boulevard Victor Hugo, Lyon','Lyon',     '20 Cours Mirabeau, Aix-en-Provence','Aix-en-Provence','2026-04-13 10:00:00','2026-04-13 13:00:00',10.00, 4, 4, 'active');

-- Réservations
INSERT INTO bookings (trip_id, passenger_id, credits_used, status) VALUES
(1, 5, 15, 'confirmed'),
(2, 5, 12, 'confirmed');

-- Avis (en attente de validation)
INSERT INTO reviews (trip_id, reviewer_id, reviewed_id, rating, comment, status) VALUES
(1, 5, 3, 5, 'Sophia est une excellente conductrice, trajet très agréable !', 'approved'),
(2, 5, 4, 4, 'Bonne conduite, ponctuel mais peu bavard.', 'pending'),
(1, 3, 5, 5, 'Passagère très sympa et ponctuelle.', 'approved');

-- Crédits plateforme
INSERT INTO platform_credits (trip_id, booking_id, amount) VALUES
(1, 1, 2.00),
(2, 2, 2.00);
