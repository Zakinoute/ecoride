-- ============================================================
-- EcoRide - Données de test
-- ============================================================
-- USE supprimé pour hébergement mutualisé : la base est déjà sélectionnée dans phpMyAdmin.

-- Mots de passe hashés = "Password1!" pour tous les comptes de test
-- hash généré avec password_hash('Password1!', PASSWORD_DEFAULT)

INSERT INTO users (pseudo, email, password, role, status, credits, is_driver, is_passenger) VALUES
('AdminEco',   'admin@ecoride.fr',    '$2y$10$iLfSipbpuvzJ976FzrwPvuDKnDgH7xxOQnU5z9i/28ckVCavQc.x6', 'admin',    'active', 100, 0, 0),
('EmployeeLuc','employe@ecoride.fr',  '$2y$10$iLfSipbpuvzJ976FzrwPvuDKnDgH7xxOQnU5z9i/28ckVCavQc.x6', 'employee', 'active', 50,  0, 0),
('SophiaD',    'sophia@example.com',  '$2y$10$iLfSipbpuvzJ976FzrwPvuDKnDgH7xxOQnU5z9i/28ckVCavQc.x6', 'user',     'active', 45,  1, 1),
('MarcoV',     'marco@example.com',   '$2y$10$iLfSipbpuvzJ976FzrwPvuDKnDgH7xxOQnU5z9i/28ckVCavQc.x6', 'user',     'active', 30,  1, 1),
('ClaireB',    'claire@example.com',  '$2y$10$iLfSipbpuvzJ976FzrwPvuDKnDgH7xxOQnU5z9i/28ckVCavQc.x6', 'user',     'active', 60,  0, 1),
('TomR',       'tom@example.com',     '$2y$10$iLfSipbpuvzJ976FzrwPvuDKnDgH7xxOQnU5z9i/28ckVCavQc.x6', 'user',     'active', 20,  1, 0);

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

-- Trajets (dates calculées à partir du jour de l'import : ils sont toujours à venir)
INSERT INTO trips (driver_id, vehicle_id, departure_address, departure_city, arrival_address, arrival_city, departure_datetime, arrival_datetime, price, available_seats, total_seats, status) VALUES
(3, 1, '12 Rue de la Paix, Paris',     'Paris',    '5 Avenue de la Liberté, Lyon',   'Lyon',      DATE_ADD(CURDATE(), INTERVAL '3 08:00' DAY_MINUTE), DATE_ADD(CURDATE(), INTERVAL '3 12:00' DAY_MINUTE), 15.00, 3, 4, 'active'),
(4, 2, '3 Boulevard Victor Hugo, Lyon','Lyon',     '8 Rue Gambetta, Marseille',       'Marseille', DATE_ADD(CURDATE(), INTERVAL '3 09:00' DAY_MINUTE), DATE_ADD(CURDATE(), INTERVAL '3 13:30' DAY_MINUTE), 12.00, 3, 4, 'active'),
(3, 1, '12 Rue de la Paix, Paris',     'Paris',    '15 Rue des Fleurs, Bordeaux',     'Bordeaux',  DATE_ADD(CURDATE(), INTERVAL '5 07:30' DAY_MINUTE), DATE_ADD(CURDATE(), INTERVAL '5 13:00' DAY_MINUTE), 18.00, 1, 1, 'active'),
(6, 3, '7 Allée des Roses, Toulouse',  'Toulouse', '2 Rue du Port, Montpellier',      'Montpellier',DATE_ADD(CURDATE(), INTERVAL '4 14:00' DAY_MINUTE), DATE_ADD(CURDATE(), INTERVAL '4 16:30' DAY_MINUTE), 8.00,  4, 4, 'active'),
(4, 2, '3 Boulevard Victor Hugo, Lyon','Lyon',     '20 Cours Mirabeau, Aix-en-Provence','Aix-en-Provence',DATE_ADD(CURDATE(), INTERVAL '6 10:00' DAY_MINUTE), DATE_ADD(CURDATE(), INTERVAL '6 13:00' DAY_MINUTE),10.00, 4, 4, 'active');

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
