// ============================================================
// EcoRide - Dashboard Admin (graphiques Chart.js)
// ============================================================

document.addEventListener('DOMContentLoaded', async () => {

  // Vérifier la session
  const user = EcoRide.getAuthState();
  if (!user || user.role !== 'admin') {
    window.location.href = '../login.html';
    return;
  }

  // Afficher les infos utilisateur dans la sidebar
  const pseudoEl  = document.getElementById('sidebarPseudo');
  const creditsEl = document.getElementById('sidebarCredits');
  if (pseudoEl)  pseudoEl.textContent  = user.pseudo;
  if (creditsEl) creditsEl.textContent = user.credits ?? '—';

  // Charger les stats
  await loadStats();
  await loadUsers();

  // ============================================================
  // Charger et afficher les statistiques
  // ============================================================
  async function loadStats() {
    // ── Compteurs MySQL (totaux) ───────────────────────────────
    const data = await EcoRide.apiRequest('../php/admin.php', 'GET', { action: 'stats' });
    const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val ?? '—'; };
    if (data.success) {
      set('statUsers',     data.total_users);
      set('statEmployees', data.total_employees);
      set('statTrips',     data.total_trips);
    }

    // ── Crédits totaux plateforme (MySQL) ─────────────────────
    const creditsData = await EcoRide.apiRequest('../php/nosql.php', 'GET', { action: 'total_credits' });
    set('statCredits', creditsData.success ? Number(creditsData.total).toFixed(0) : '—');

    // ── Graphique trajets/jour : données MongoDB (NoSQL) ──────
    const tripsStats = await EcoRide.apiRequest('../php/nosql.php', 'GET', { action: 'stats_trips' });
    if (tripsStats.success && tripsStats.stats.length > 0) {
      buildTripChart(tripsStats.stats.map(d => ({ day: d.date, count: d.count })));
    } else {
      // Fallback MySQL si MongoDB vide (ex. première utilisation)
      buildTripChart(data.trips_by_day || []);
    }

    // ── Graphique crédits/jour : données MongoDB (NoSQL) ──────
    const creditsStats = await EcoRide.apiRequest('../php/nosql.php', 'GET', { action: 'stats_credits' });
    if (creditsStats.success && creditsStats.stats.length > 0) {
      buildCreditsChart(creditsStats.stats.map(d => ({ day: d.date, total: d.credits })));
    } else {
      buildCreditsChart(data.credits_by_day || []);
    }
  }

  function buildTripChart(rawData) {
    const ctx = document.getElementById('tripsChart');
    if (!ctx) return;

    const labels = rawData.map(d => EcoRide.formatDate(d.day));
    const values = rawData.map(d => parseInt(d.count));

    new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Covoiturages réservés',
          data: values,
          backgroundColor: 'rgba(76, 175, 80, 0.7)',
          borderColor: '#2E7D32',
          borderWidth: 1.5,
          borderRadius: 6,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { backgroundColor: '#1B5E20' }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { stepSize: 1 },
            grid: { color: 'rgba(0,0,0,0.05)' }
          },
          x: {
            ticks: { maxTicksLimit: 10, maxRotation: 30 },
            grid: { display: false }
          }
        }
      }
    });
  }

  function buildCreditsChart(rawData) {
    const ctx = document.getElementById('creditsChart');
    if (!ctx) return;

    const labels = rawData.map(d => EcoRide.formatDate(d.day));
    const values = rawData.map(d => parseFloat(d.total));

    new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Crédits gagnés',
          data: values,
          borderColor: '#00897B',
          backgroundColor: 'rgba(0, 137, 123, 0.1)',
          borderWidth: 2.5,
          pointBackgroundColor: '#00897B',
          pointRadius: 4,
          fill: true,
          tension: 0.4,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#00695C',
            callbacks: {
              label: ctx => `${ctx.parsed.y.toFixed(2)} crédits`
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: { color: 'rgba(0,0,0,0.05)' }
          },
          x: {
            ticks: { maxTicksLimit: 10, maxRotation: 30 },
            grid: { display: false }
          }
        }
      }
    });
  }

  // ============================================================
  // Gestion des utilisateurs
  // ============================================================
  async function loadUsers(search = '', role = '') {
    const tbody = document.getElementById('usersTableBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Chargement…</td></tr>';

    const data = await EcoRide.apiRequest('../php/admin.php', 'GET', { action: 'users', search, role });
    if (!data.success) return;

    if (data.users.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Aucun utilisateur trouvé.</td></tr>';
      return;
    }

    tbody.innerHTML = data.users.map(u => `
      <tr>
        <td><strong>${u.pseudo}</strong></td>
        <td>${u.email}</td>
        <td><span class="badge badge-${u.role === 'admin' ? 'approved' : u.role === 'employee' ? 'started' : 'active'}">${u.role}</span></td>
        <td>
          <span class="status-dot ${u.status}"></span>
          ${u.status === 'active' ? 'Actif' : 'Suspendu'}
        </td>
        <td>${u.credits}</td>
        <td>${EcoRide.formatDate(u.created_at)}</td>
        <td>
          ${u.role !== 'admin' ? `
            <button class="btn btn-sm ${u.status === 'active' ? 'btn-danger' : 'btn-outline'} toggle-status-btn"
                    data-id="${u.id}" data-status="${u.status}">
              ${u.status === 'active' ? 'Suspendre' : 'Réactiver'}
            </button>` : '—'
          }
        </td>
      </tr>`).join('');

    // Actions suspend/reactive
    tbody.querySelectorAll('.toggle-status-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        const confirm = window.confirm(
          btn.dataset.status === 'active'
            ? 'Confirmer la suspension de ce compte ?'
            : 'Réactiver ce compte ?'
        );
        if (!confirm) return;
        const res = await EcoRide.apiRequest('../php/admin.php', 'POST', { action: 'toggle_status', user_id: btn.dataset.id });
        if (res.success) loadUsers(search, role);
        else alert(res.message);
      });
    });
  }

  // Barre de recherche utilisateurs
  const userSearch = document.getElementById('userSearch');
  const roleFilter = document.getElementById('roleFilter');
  if (userSearch) userSearch.addEventListener('input', () => loadUsers(userSearch.value, roleFilter?.value));
  if (roleFilter) roleFilter.addEventListener('change', () => loadUsers(userSearch?.value, roleFilter.value));

  // ============================================================
  // Création d'un compte employé
  // ============================================================
  const createEmpForm = document.getElementById('createEmployeeForm');
  if (createEmpForm) {
    createEmpForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const alertZone = document.getElementById('empAlertZone');
      const formData  = new FormData(createEmpForm);
      formData.append('action', 'create_employee');
      const data = await EcoRide.apiRequest('../php/admin.php', 'POST', formData);
      if (data.success) {
        EcoRide.showAlert(alertZone, 'success', data.message);
        createEmpForm.reset();
        loadUsers();
      } else {
        EcoRide.showAlert(alertZone, 'error', data.message);
      }
    });
  }
});
