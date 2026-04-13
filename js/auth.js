// ============================================================
// EcoRide - Authentification (login & inscription)
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

  // ============================================================
  // Formulaire d'inscription
  // ============================================================
  const registerForm = document.getElementById('registerForm');
  if (registerForm) {
    const pwdInput    = document.getElementById('password');
    const strengthBar = document.getElementById('strengthText');

    // Indicateur de force en temps réel
    if (pwdInput && strengthBar) {
      pwdInput.addEventListener('input', () => {
        const { label, color } = EcoRide.checkPasswordStrength(pwdInput.value);
        strengthBar.textContent = label ? `Force : ${label}` : '';
        strengthBar.style.color = color;
      });
    }

    registerForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = registerForm.querySelector('[type="submit"]');
      const alertZone = document.getElementById('alertZone');

      const password = document.getElementById('password').value;
      const confirm  = document.getElementById('confirmPassword')?.value;

      if (confirm !== undefined && password !== confirm) {
        EcoRide.showAlert(alertZone, 'error', 'Les mots de passe ne correspondent pas.');
        return;
      }

      btn.disabled    = true;
      btn.textContent = 'Création en cours…';

      const formData = new FormData(registerForm);
      formData.append('action', 'register');

      const data = await EcoRide.apiRequest('../php/auth.php', 'POST', formData);

      btn.disabled    = false;
      btn.textContent = 'Créer mon compte';

      if (data.success) {
        EcoRide.setAuthState(data.user || { pseudo: formData.get('pseudo'), credits: 20, role: 'user' });
        EcoRide.showAlert(alertZone, 'success', data.message);
        setTimeout(() => window.location.href = data.redirect || '../user/dashboard.html', 1200);
      } else {
        EcoRide.showAlert(alertZone, 'error', data.message);
      }
    });
  }

  // ============================================================
  // Formulaire de connexion
  // ============================================================
  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn       = loginForm.querySelector('[type="submit"]');
      const alertZone = document.getElementById('alertZone');

      btn.disabled    = true;
      btn.textContent = 'Connexion…';

      const formData = new FormData(loginForm);
      formData.append('action', 'login');

      const data = await EcoRide.apiRequest('../php/auth.php', 'POST', formData);

      btn.disabled    = false;
      btn.textContent = 'Se connecter';

      if (data.success) {
        EcoRide.setAuthState(data.user);
        EcoRide.showAlert(alertZone, 'success', data.message);
        setTimeout(() => window.location.href = data.redirect || '../user/dashboard.html', 800);
      } else {
        EcoRide.showAlert(alertZone, 'error', data.message);
      }
    });
  }

  // ============================================================
  // Lien de déconnexion
  // ============================================================
  document.querySelectorAll('.logout-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const base = btn.dataset.base || '../';
      EcoRide.logout(base);
    });
  });

  // ============================================================
  // Redirection si déjà connecté
  // ============================================================
  const user = EcoRide.getAuthState();
  if (user && (registerForm || loginForm)) {
    const dest = {
      admin:    '../admin/dashboard.html',
      employee: '../employee/dashboard.html',
      user:     '../user/dashboard.html',
    }[user.role] || '../user/dashboard.html';
    window.location.href = dest;
  }
});
