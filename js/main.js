// ============================================================
// EcoRide - JavaScript principal
// ============================================================

// API base path (adapter selon l'emplacement du fichier appelant)
const API_BASE = window.API_BASE || '../php/';

// ============================================================
// Navigation mobile (burger menu)
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
  const burger   = document.getElementById('burger');
  const navLinks = document.getElementById('navLinks');

  if (burger && navLinks) {
    burger.addEventListener('click', () => {
      navLinks.classList.toggle('open');
      const isOpen = navLinks.classList.contains('open');
      burger.setAttribute('aria-expanded', isOpen);
    });
    // Fermer en cliquant à l'extérieur
    document.addEventListener('click', (e) => {
      if (!burger.contains(e.target) && !navLinks.contains(e.target)) {
        navLinks.classList.remove('open');
      }
    });
  }

  // Mettre à jour l'affichage de connexion dans le nav
  updateNavAuth();
});

// ============================================================
// Gestion de l'état d'authentification (localStorage)
// ============================================================
function setAuthState(user) {
  localStorage.setItem('ecoride_user', JSON.stringify(user));
}

function getAuthState() {
  try { return JSON.parse(localStorage.getItem('ecoride_user')); }
  catch { return null; }
}

function clearAuthState() {
  localStorage.removeItem('ecoride_user');
}

function updateNavAuth() {
  const user        = getAuthState();
  const loginLink   = document.getElementById('navLogin');
  const registerLink= document.getElementById('navRegister');
  const userNav     = document.getElementById('navUser');
  const creditsEl   = document.getElementById('navCredits');
  const pseudoEl    = document.getElementById('navPseudo');

  if (user) {
    if (loginLink)    loginLink.classList.add('hidden');
    if (registerLink) registerLink.classList.add('hidden');
    if (userNav)      userNav.classList.remove('hidden');
    if (creditsEl)    creditsEl.textContent = user.credits ?? '—';
    if (pseudoEl)     pseudoEl.textContent  = user.pseudo;
  } else {
    if (loginLink)    loginLink.classList.remove('hidden');
    if (registerLink) registerLink.classList.remove('hidden');
    if (userNav)      userNav.classList.add('hidden');
  }
}

// ============================================================
// Déconnexion
// ============================================================
function logout(basePath = '../') {
  fetch(basePath + 'php/auth.php?action=logout', { method: 'POST' })
    .finally(() => {
      clearAuthState();
      window.location.href = basePath + 'index.html';
    });
}

// ============================================================
// Requête API générique
// ============================================================
async function apiRequest(endpoint, method = 'GET', data = null) {
  const options = { method, credentials: 'include' };
  if (data && method !== 'GET') {
    if (data instanceof FormData) {
      options.body = data;
    } else {
      const fd = new FormData();
      Object.entries(data).forEach(([k, v]) => fd.append(k, v));
      options.body = fd;
    }
  }
  const url = (method === 'GET' && data)
    ? endpoint + '?' + new URLSearchParams(data)
    : endpoint;

  const res  = await fetch(url, options);
  const json = await res.json();
  return json;
}

// ============================================================
// Afficher une alerte
// ============================================================
function showAlert(container, type, message) {
  const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
  const alert = document.createElement('div');
  alert.className = `alert alert-${type}`;
  alert.innerHTML = `<span>${icons[type] || 'ℹ️'}</span><span>${message}</span>`;
  container.prepend(alert);
  setTimeout(() => alert.remove(), 5000);
}

// ============================================================
// Formater une date en français
// ============================================================
function formatDate(dateStr) {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('fr-FR', {
    weekday: 'short', day: 'numeric', month: 'short', year: 'numeric'
  });
}

function formatDateTime(dateStr) {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleString('fr-FR', {
    day: 'numeric', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
  });
}

function formatTime(dateStr) {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
}

// ============================================================
// Générer les étoiles HTML
// ============================================================
function renderStars(rating, max = 5) {
  const filled = Math.round(rating);
  let html = '';
  for (let i = 1; i <= max; i++) {
    html += i <= filled ? '★' : '☆';
  }
  return `<span class="star-rating">${html}<span class="count">(${rating})</span></span>`;
}

// ============================================================
// Modale générique
// ============================================================
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }

document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
  }
  if (e.target.classList.contains('modal-close')) {
    e.target.closest('.modal-overlay')?.classList.remove('open');
  }
});

// ============================================================
// Indicateur de force du mot de passe
// ============================================================
function checkPasswordStrength(pwd) {
  let score = 0;
  if (pwd.length >= 8)           score++;
  if (/[A-Z]/.test(pwd))        score++;
  if (/\d/.test(pwd))           score++;
  if (/[\W_]/.test(pwd))        score++;
  const labels = ['', 'Très faible', 'Faible', 'Moyen', 'Fort'];
  const colors = ['', '#e53935', '#fb8c00', '#f9a825', '#388e3c'];
  return { score, label: labels[score] || '', color: colors[score] || '' };
}

// ============================================================
// Pagination simple
// ============================================================
function paginate(items, page = 1, perPage = 10) {
  const start = (page - 1) * perPage;
  return {
    items: items.slice(start, start + perPage),
    total: items.length,
    pages: Math.ceil(items.length / perPage),
    current: page,
  };
}

// ============================================================
// Exposer les fonctions globalement
// ============================================================
window.EcoRide = {
  setAuthState, getAuthState, clearAuthState, updateNavAuth,
  logout, apiRequest, showAlert, formatDate, formatDateTime, formatTime,
  renderStars, openModal, closeModal, checkPasswordStrength, paginate
};
