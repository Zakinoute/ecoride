# 🌿 EcoRide — Plateforme de Covoiturage Éco-responsable

Projet réalisé dans le cadre du **Titre Professionnel Développeur Web et Web Mobile (TP-01280)**  
Candidat : **MSSIAIDI Zakaria** — Formation Studi (à distance)  
ECF : Sessions Juin-Juillet 2025 / Septembre-Octobre 2025

---

## Présentation

EcoRide est une plateforme de covoiturage favorisant les véhicules écologiques.  
Elle permet aux utilisateurs de proposer ou réserver des trajets, avec un système de crédits intégré et un espace de gestion pour les employés et administrateurs.

**13 User Stories couvertes** : inscription, connexion, recherche de trajet, réservation, publication, gestion du profil, véhicules, avis, espace employé, dashboard admin avec statistiques.

---

## Stack technique

| Couche | Technologie |
|---|---|
| Front-end | HTML5, CSS3, JavaScript (Vanilla) |
| Back-end | PHP 8.2 (POO, PDO) |
| Base de données relationnelle | MySQL 8.0 |
| Base de données NoSQL | MongoDB 7.0 |
| Serveur web | Apache (mod_rewrite) |
| Conteneurisation | Docker + Docker Compose |
| Graphiques | Chart.js |

---

## Prérequis

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows/Mac/Linux)
- Git

---

## Lancement en local (Docker)

```bash
# 1. Cloner le dépôt
git clone https://github.com/<votre-pseudo>/ecoride.git
cd ecoride

# 2. Lancer tous les services
docker compose up -d

# 3. Vérifier que les conteneurs tournent
docker compose ps
```

| Service | URL |
|---|---|
| Application EcoRide | http://localhost:8080 |
| phpMyAdmin | http://localhost:8081 |
| MySQL | localhost:3306 |
| MongoDB | localhost:27017 |

> La base MySQL est initialisée automatiquement au premier démarrage via `sql/schema.sql` et `sql/seed.sql`.

### Arrêter les services

```bash
docker compose down
```

### Réinitialiser la base de données

```bash
docker compose down -v   # supprime les volumes
docker compose up -d     # recrée tout depuis zéro
```

---

## Comptes de test

Mot de passe universel : **`Password1!`**

| Rôle | Email | Pseudo |
|---|---|---|
| Administrateur | admin@ecoride.fr | AdminEco |
| Employé | employe@ecoride.fr | EmployeeLuc |
| Utilisateur (chauffeur + passager) | sophia@example.com | SophiaD |
| Utilisateur (chauffeur) | marco@example.com | MarcoV |
| Utilisateur (passager) | claire@example.com | ClaireB |

---

## Structure du projet

```
ecoride/
├── Dockerfile                  # Image PHP 8.2 + Apache + MongoDB driver
├── docker-compose.yml          # Services : app, mysql, mongodb, phpmyadmin
├── .docker/
│   └── apache.conf             # VirtualHost Apache
├── sql/
│   ├── schema.sql              # Création des tables MySQL
│   └── seed.sql                # Données de test
├── php/
│   ├── config.php              # Configuration, session, helpers JSON
│   ├── auth.php                # Inscription / Connexion / Déconnexion
│   ├── trips.php               # CRUD trajets
│   ├── vehicles.php            # CRUD véhicules
│   ├── reviews.php             # Avis passagers
│   ├── employee.php            # Espace employé
│   ├── admin.php               # Espace admin (stats MySQL)
│   ├── nosql.php               # Statistiques MongoDB (NoSQL)
│   └── classes/
│       ├── Database.php        # Singleton PDO
│       ├── User.php            # Gestion utilisateurs
│       ├── Trip.php            # Gestion trajets
│       ├── Booking.php         # Réservations + transactions crédits
│       └── Review.php          # Avis chauffeurs
├── js/
│   ├── main.js                 # Helpers globaux (EcoRide namespace)
│   ├── auth.js                 # Login / Register
│   ├── search.js               # Recherche de trajets
│   └── admin.js                # Dashboard admin (Chart.js)
├── css/
│   ├── style.css               # Styles globaux
│   └── dashboard.css           # Styles dashboards
├── index.html                  # Page d'accueil
├── login.html                  # Connexion
├── register.html               # Inscription
├── search.html                 # Recherche de covoiturages
├── trip-detail.html            # Détail d'un trajet
├── user/                       # Espace utilisateur connecté
├── employee/                   # Espace employé
└── admin/                      # Espace administrateur
```

---

## Architecture base de données

### MySQL (données relationnelles)
- `users` — comptes, rôles, crédits
- `vehicles` — véhicules des chauffeurs
- `driver_preferences` — préférences de trajet
- `trips` — trajets publiés
- `bookings` — réservations (avec transaction crédits atomique)
- `reviews` — avis passagers (workflow validation employé)
- `platform_credits` — gains de la plateforme (2 crédits/réservation)

### MongoDB (statistiques NoSQL)
- Collection `bookings_log` — événements de réservation
- Aggregation pipeline pour les graphiques admin (trajets/jour, crédits/jour)

---

## Déploiement en production (Railway)

1. Créer un compte sur [railway.app](https://railway.app)
2. **New Project** → Deploy from GitHub → sélectionner ce repo
3. Ajouter un service **MySQL** : New Service → Database → MySQL
4. Ajouter un service **MongoDB** : New Service → Database → MongoDB
5. Dans le service app → **Variables** :

```
DB_HOST     = ${{MySQL.MYSQL_HOST}}
DB_NAME     = ecoride
DB_USER     = ecoride
DB_PASS     = ecoride_pass
MONGO_URI   = ${{MongoDB.MONGO_URL}}
MONGO_DB    = ecoride_stats
```

6. Dans MySQL → **Connect** → exécuter `sql/schema.sql` puis `sql/seed.sql`
7. Railway génère automatiquement une URL publique (`https://ecoride-xxx.up.railway.app`)

---

## Maquettes (Figma)

Fichier Figma : [EcoRide — Maquettes ECF](https://www.figma.com/design/IHd4CVD4Zx0biX7Z5b7qGF)

- 3 écrans Desktop (1440px) : Accueil, Covoiturages, Détail trajet
- 3 écrans Mobile (375px) : Accueil, Covoiturages, Détail trajet

---

## Charte graphique

| Couleur | Usage | Hex |
|---|---|---|
| Vert foncé | Primaire, nav, CTA | `#2E7D32` |
| Vert clair | Boutons, accents | `#4CAF50` |
| Fond vert pâle | Backgrounds | `#F1F8E9` |
| Teal éco | Badge écologique | `#00897B` |
| Vert très foncé | Footer, nav dark | `#1B5E20` |

Typographie : **Inter** (Google Fonts)

---

## Conformité

- **RGPD** : pas de cookies tiers, données personnelles minimales, mentions légales incluses
- **RGAA** : contrastes conformes, balises sémantiques HTML5, attributs `alt`

---

## Auteur

**MSSIAIDI Zakaria**  
Formation Développeur Web et Web Mobile — Studi  
Titre Professionnel Niveau 5 (Bac+2) — TP-01280
