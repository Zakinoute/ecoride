#!/bin/bash
# ============================================================
# EcoRide - Jeu d'essai automatisé de l'API (39 tests)
#
# Utilisation, depuis la racine du projet, conteneurs lancés :
#   bash tests/tests_api.sh
# Variables facultatives :
#   BASE=http://localhost:8080   adresse du site
#   RESET=docker                 recharge schema.sql, seed.sql et vide MongoDB avant les tests
#   RESET=aucun                  ne touche pas aux bases (à remettre à zéro soi-même)
# ============================================================
BASE="${BASE:-http://localhost:8080}"
RESET="${RESET:-docker}"
API="$BASE/php"
COOKIES="$(mktemp -d)"
trap 'rm -rf "$COOKIES"' EXIT
n=0; ok=0

if [ "$RESET" = "docker" ]; then
  docker compose exec -T mysql mysql -uecoride -pecoride_pass ecoride < sql/schema.sql 2>/dev/null
  docker compose exec -T mysql mysql -uecoride -pecoride_pass ecoride < sql/seed.sql 2>/dev/null
  docker compose exec -T mongodb mongosh --quiet ecoride_stats --eval 'db.bookings_log.drop()' >/dev/null
fi

check() { # check "nom du test" "réponse" "texte attendu"
  n=$((n + 1))
  if echo "$2" | grep -q -- "$3"; then
    ok=$((ok + 1)); printf 'OK    T%02d %s\n' "$n" "$1"
  else
    printf 'ÉCHEC T%02d %s\n      attendu : %s\n      reçu    : %s\n' "$n" "$1" "$3" "$2"
  fi
}
get()   { curl -s -b "$COOKIES/$1" -c "$COOKIES/$1" "$API/$2"; }
post()  { local qui=$1 url=$2; shift 2; curl -s -b "$COOKIES/$qui" -c "$COOKIES/$qui" -X POST "$API/$url" "$@"; }
login() { post "$1" auth.php -F action=login -F "email=$2" -F "password=${3:-Password1!}"; }

# Recherche et consultation
check "Recherche publique Paris → Lyon" "$(get anon 'trips.php?action=search&departure=Paris&arrival=Lyon')" '"departure_city":"Paris"'
check "Trajet électrique marqué écologique" "$(get anon 'trips.php?action=search&departure=Paris&arrival=Lyon')" '"is_eco":true'
check "Tri : une saisie hors liste blanche est ignorée" "$(get anon 'trips.php?action=search&sort=price%3BDROP%20TABLE%20users')" '"success":true'
check "Détail du trajet 1 avec avis validé" "$(get anon 'trips.php?action=detail&id=1')" '"reviewer_pseudo":"ClaireB"'
# Connexion et droits
check "Réserver sans être connecté" "$(post anon trips.php -F action=book -F trip_id=1)" 'Non authentifié'
check "Mauvais mot de passe" "$(login claire claire@example.com mauvais)" 'Identifiants incorrects'
check "Injection SQL dans l'e-mail" "$(login pirate "' OR '1'='1" x)" '"success":false'
check "Connexion ClaireB" "$(login claire claire@example.com)" '"success":true'
check "Profil ClaireB" "$(get claire 'auth.php?action=me')" '"pseudo":"ClaireB"'
# Réservation et crédits
check "ClaireB réserve Paris → Bordeaux (18 crédits)" "$(post claire trips.php -F action=book -F trip_id=3)" '"new_credits":42'
check "ClaireB réserve Lyon → Aix (10 crédits)" "$(post claire trips.php -F action=book -F trip_id=5)" '"new_credits":32'
check "Plus de place sur ce trajet" "$(post claire trips.php -F action=book -F trip_id=3)" 'Trajet indisponible'
check "Réservation en double refusée" "$(post claire trips.php -F action=book -F trip_id=1)" 'déjà réservé'
check "Accès administrateur refusé à un utilisateur" "$(get claire 'admin.php?action=stats')" 'Accès refusé'
# Inscription
check "Inscription refusée : mot de passe faible" "$(post nina auth.php -F action=register -F pseudo=Nina -F email=nina@example.com -F password=faible)" 'trop faible'
check "Inscription acceptée avec 20 crédits" "$(post nina auth.php -F action=register -F pseudo=Nina -F email=nina@example.com -F 'password=Secret12!')" '20 crédits offerts'
check "Inscription en double refusée" "$(post autre auth.php -F action=register -F pseudo=Nina -F email=nina@example.com -F 'password=Secret12!')" 'déjà utilisé'
check "Nina réserve Paris → Lyon (15 crédits)" "$(post nina trips.php -F action=book -F trip_id=1)" '"new_credits":5'
check "Crédits insuffisants" "$(post nina trips.php -F action=book -F trip_id=5)" 'Crédits insuffisants'
# Cycle de vie d'un trajet
check "Connexion SophiaD (chauffeur)" "$(login sophia sophia@example.com)" '"success":true'
check "Un chauffeur ne réserve pas son propre trajet" "$(post sophia trips.php -F action=book -F trip_id=1)" 'propre trajet'
check "Sophia démarre Paris → Bordeaux" "$(post sophia trips.php -F action=start -F trip_id=3)" 'Trajet démarré'
check "Sophia termine : 18 - 2 = 16 crédits" "$(post sophia trips.php -F action=complete -F trip_id=3)" '"earned":16'
check "Un autre chauffeur ne peut pas annuler ce trajet" "$(post sophia trips.php -F action=cancel -F trip_id=2)" 'introuvable'
check "Connexion MarcoV" "$(login marco marco@example.com)" '"success":true'
check "Marco annule Lyon → Marseille : passagers remboursés" "$(post marco trips.php -F action=cancel -F trip_id=2)" 'remboursés'
check "Marco annule Lyon → Aix : ClaireB remboursée" "$(post marco trips.php -F action=cancel -F trip_id=5)" 'remboursés'
# Avis et modération
check "ClaireB note le trajet terminé" "$(post claire reviews.php -F action=submit -F trip_id=3 -F rating=5 -F 'comment=Super trajet <script>alert(1)</script>')" 'Avis soumis'
check "Avis sur un trajet non terminé refusé" "$(post claire reviews.php -F action=submit -F trip_id=1 -F rating=4)" 'terminés'
check "Connexion employé" "$(login employe employe@ecoride.fr)" 'employee/dashboard'
check "Employé : avis en attente" "$(get employe 'reviews.php?action=pending')" 'Super trajet'
n=$((n + 1))
if get employe 'reviews.php?action=pending' | grep -q "<script>"; then
  printf 'ÉCHEC T%02d Commentaire neutralisé (XSS)\n' "$n"
else
  ok=$((ok + 1)); printf 'OK    T%02d Commentaire neutralisé (XSS) : balise <script> retirée\n' "$n"
fi
AVIS=$(get employe 'reviews.php?action=pending' | grep -o '"id":[0-9]*' | tail -1 | cut -d: -f2)
check "Employé valide l'avis" "$(post employe reviews.php -F action=moderate -F "review_id=$AVIS" -F decision=approved)" 'Avis validé'
check "Décision invalide refusée" "$(post employe reviews.php -F action=moderate -F review_id=1 -F decision=supprimer)" 'Décision invalide'
# Statistiques MySQL et MongoDB
check "Connexion administrateur" "$(login admin admin@ecoride.fr)" 'admin/dashboard'
check "Total des crédits plateforme (MySQL) après annulations" "$(get admin 'nosql.php?action=total_credits')" '"total":6'
check "Covoiturages par jour (MongoDB)" "$(get admin 'nosql.php?action=stats_trips')" '"count":2'
check "Crédits plateforme par jour (MongoDB)" "$(get admin 'nosql.php?action=stats_credits')" '"credits":4'
check "Statistiques MySQL de secours" "$(get admin 'admin.php?action=stats')" '"success":true'

echo "---- $ok / $n tests réussis"
[ "$ok" -eq "$n" ]
