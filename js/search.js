// ============================================================
// EcoRide - Recherche et affichage des trajets
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
  const form        = document.getElementById('searchForm');
  const resultsDiv  = document.getElementById('searchResults');
  const filterForm  = document.getElementById('filterForm');
  const countEl     = document.getElementById('resultsCount');

  let allTrips = [];

  // Pré-remplir depuis l'URL (paramètres de recherche venus de l'accueil)
  const params = new URLSearchParams(window.location.search);
  if (params.has('departure')) document.getElementById('departure').value = params.get('departure');
  if (params.has('arrival'))   document.getElementById('arrival').value   = params.get('arrival');
  if (params.has('date'))      document.getElementById('date').value      = params.get('date');
  if (params.toString()) searchTrips();

  // Soumission du formulaire de recherche
  if (form) {
    form.addEventListener('submit', (e) => { e.preventDefault(); searchTrips(); });
  }

  // Application des filtres en temps réel
  if (filterForm) {
    filterForm.addEventListener('input', () => applyFilters());
    filterForm.addEventListener('change', () => applyFilters());
  }

  async function searchTrips() {
    if (!resultsDiv) return;
    resultsDiv.innerHTML = renderSkeletons(3);

    const departure = document.getElementById('departure')?.value || '';
    const arrival   = document.getElementById('arrival')?.value   || '';
    const date      = document.getElementById('date')?.value      || '';

    const data = await EcoRide.apiRequest('../php/trips.php', 'GET', { action: 'search', departure, arrival, date });

    if (!data.success) {
      resultsDiv.innerHTML = `<div class="alert alert-error">❌ ${data.message}</div>`;
      return;
    }
    allTrips = data.trips || [];
    applyFilters();
  }

  function applyFilters() {
    if (!filterForm) { renderTrips(allTrips); return; }
    const maxPrice  = parseFloat(document.getElementById('maxPrice')?.value) || Infinity;
    const minRating = parseFloat(document.getElementById('minRating')?.value) || 0;
    const ecoOnly   = document.getElementById('ecoOnly')?.checked || false;
    const sortBy    = document.getElementById('sortBy')?.value || 'departure_datetime';

    let filtered = allTrips.filter(t => {
      if (t.price > maxPrice) return false;
      if (t.avg_rating < minRating) return false;
      if (ecoOnly && !t.is_eco) return false;
      return true;
    });

    filtered.sort((a, b) => {
      if (sortBy === 'price')               return a.price - b.price;
      if (sortBy === 'avg_rating')          return b.avg_rating - a.avg_rating;
      if (sortBy === 'available_seats')     return b.available_seats - a.available_seats;
      return new Date(a.departure_datetime) - new Date(b.departure_datetime);
    });

    renderTrips(filtered);
  }

  function renderTrips(trips) {
    if (!resultsDiv) return;
    if (countEl) countEl.textContent = `${trips.length} trajet${trips.length !== 1 ? 's' : ''} trouvé${trips.length !== 1 ? 's' : ''}`;

    if (trips.length === 0) {
      resultsDiv.innerHTML = `
        <div class="empty-state">
          <div class="icon">🚗</div>
          <h3>Aucun trajet disponible</h3>
          <p>Modifiez vos critères de recherche ou vérifiez une autre date.</p>
        </div>`;
      return;
    }

    resultsDiv.innerHTML = trips.map(trip => renderTripCard(trip)).join('');

    // Attacher les événements "Voir" sur chaque carte
    resultsDiv.querySelectorAll('.btn-view-trip').forEach(btn => {
      btn.addEventListener('click', () => {
        window.location.href = `trip-detail.html?id=${btn.dataset.id}`;
      });
    });
  }

  function renderTripCard(trip) {
    const stars   = EcoRide.renderStars(trip.avg_rating);
    const ecoBadge = trip.is_eco
      ? `<span class="badge badge-eco">⚡ Voyage écologique</span>`
      : '';
    const depTime = EcoRide.formatTime(trip.departure_datetime);
    const depDate = EcoRide.formatDate(trip.departure_datetime);
    const filled  = trip.total_seats - trip.available_seats;
    const pct     = Math.round((filled / trip.total_seats) * 100);

    return `
      <div class="trip-card">
        <div class="driver-info">
          <img src="../assets/avatars/${trip.driver_photo || 'default-avatar.png'}"
               alt="${trip.driver_pseudo}"
               class="driver-avatar"
               onerror="this.src='../assets/avatars/default-avatar.png'">
          <div>
            <div class="driver-name">${trip.driver_pseudo}</div>
            ${stars}
          </div>
        </div>
        <div>
          <div class="trip-route">
            <div class="city">📍 ${trip.departure_city}</div>
            <div class="arrow">↓</div>
            <div class="city">🏁 ${trip.arrival_city}</div>
            <div class="addr">${depDate} · ${depTime}</div>
          </div>
          <div style="margin-top:0.5rem;display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center">
            ${ecoBadge}
            <span class="badge badge-active">🪑 ${trip.available_seats} place${trip.available_seats > 1 ? 's' : ''}</span>
          </div>
          <div class="seats-bar mt-1" style="max-width:160px">
            <div class="seats-bar-fill" style="width:${pct}%"></div>
          </div>
        </div>
        <div class="trip-meta" style="text-align:right">
          <div class="trip-price">${parseFloat(trip.price).toFixed(2)} €</div>
          <div class="trip-seats">par place</div>
          <button class="btn btn-primary btn-sm mt-2 btn-view-trip" data-id="${trip.id}">
            Voir le trajet →
          </button>
        </div>
      </div>`;
  }

  function renderSkeletons(n) {
    return Array(n).fill(`
      <div class="trip-card" style="gap:1rem">
        <div class="skeleton" style="width:48px;height:48px;border-radius:50%"></div>
        <div style="flex:1;display:flex;flex-direction:column;gap:8px">
          <div class="skeleton" style="height:18px;width:60%"></div>
          <div class="skeleton" style="height:14px;width:40%"></div>
          <div class="skeleton" style="height:14px;width:80%"></div>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end">
          <div class="skeleton" style="height:28px;width:70px"></div>
          <div class="skeleton" style="height:32px;width:120px;border-radius:6px"></div>
        </div>
      </div>`).join('');
  }
});
