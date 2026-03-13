<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'constants.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'api.php';

startAppSession();
$sessionUser = sessionAuthUser();
if (is_array($sessionUser)) {
  header('Location: index.php');
  exit;
}
$csrfToken = $_SESSION['csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
<title>Login | Catalogo de Sistemas SEI</title>
<style>
  :root {
    --bg: #f4f7fb;
    --panel: #ffffff;
    --text: #1f2937;
    --muted: #6b7280;
    --border: #d1d5db;
    --primary: #0f4c81;
    --primary-hover: #0d3d67;
    --danger: #b91c1c;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    min-height: 100vh;
    font-family: "DM Sans", "Segoe UI", sans-serif;
    background: linear-gradient(160deg, #eef2f8 0%, #f8fafc 45%, #eaf0f7 100%);
    color: var(--text);
    display: grid;
    place-items: center;
    padding: 24px;
  }
  .login-card {
    width: 100%;
    max-width: 420px;
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
  }
  .title {
    margin: 0 0 8px;
    font-size: 26px;
    line-height: 1.2;
  }
  .subtitle {
    margin: 0 0 24px;
    color: var(--muted);
    font-size: 14px;
  }
  .field {
    display: grid;
    gap: 8px;
    margin-bottom: 16px;
  }
  .field label {
    font-size: 13px;
    font-weight: 600;
  }
  .field input {
    width: 100%;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 15px;
    outline: none;
    transition: border-color .15s ease;
  }
  .field input:focus {
    border-color: var(--primary);
  }
  .btn {
    width: 100%;
    border: 0;
    border-radius: 10px;
    background: var(--primary);
    color: #fff;
    padding: 12px 14px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: background-color .15s ease, opacity .15s ease;
  }
  .btn:hover { background: var(--primary-hover); }
  .btn:disabled { opacity: .7; cursor: wait; }
  .hint {
    margin: 14px 0 0;
    color: var(--muted);
    font-size: 13px;
  }
  .error {
    min-height: 20px;
    margin: 14px 0 0;
    color: var(--danger);
    font-size: 13px;
    font-weight: 600;
  }
</style>
</head>
<body>
  <main class="login-card">
    <h1 class="title">Acesso ao Sistema</h1>
    <p class="subtitle">Informe usuario e senha para acessar o Catalogo de Sistemas SEI.</p>
    <form id="login-form" autocomplete="on">
      <div class="field">
        <label for="username">Usuario</label>
        <input id="username" name="username" autocomplete="username" placeholder="editor" required>
      </div>
      <div class="field">
        <label for="password">Senha</label>
        <input id="password" name="password" type="password" autocomplete="current-password" placeholder="********" required>
      </div>
      <button id="submit-btn" class="btn" type="submit">Entrar</button>
      <div id="error-msg" class="error"></div>
      <p class="hint">Perfis com acesso de edicao: editor e admin.</p>
    </form>
  </main>

  <script>
    const form = document.getElementById('login-form');
    const submitBtn = document.getElementById('submit-btn');
    const errorMsg = document.getElementById('error-msg');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function setBusy(isBusy) {
      submitBtn.disabled = isBusy;
      submitBtn.textContent = isBusy ? 'Entrando...' : 'Entrar';
    }

    async function submitLogin(event) {
      event.preventDefault();
      errorMsg.textContent = '';
      const username = String(usernameInput.value || '').trim();
      const password = String(passwordInput.value || '');
      if (!username || !password) {
        errorMsg.textContent = 'Informe usuario e senha.';
        return;
      }

      setBusy(true);
      try {
        const response = await fetch('index.php?api=login', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
          },
          body: JSON.stringify({ username, password })
        });
        const raw = await response.text();
        let payload = null;
        try { payload = raw ? JSON.parse(raw) : null; } catch {}
        if (!response.ok || !payload || payload.ok !== true) {
          throw new Error(payload?.error || 'Falha no login.');
        }
        window.location.href = 'index.php';
      } catch (error) {
        errorMsg.textContent = String(error?.message || 'Falha no login.');
      } finally {
        setBusy(false);
      }
    }

    form.addEventListener('submit', submitLogin);
    usernameInput.focus();
  </script>
</body>
</html>
