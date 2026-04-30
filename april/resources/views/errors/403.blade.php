<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Access Denied</title>
<style>
  :root {
    --bg: #0b1118;
    --panel: #131c28;
    --border: #243347;
    --text: #d7e0ea;
    --muted: #7f91a8;
    --gold: #d7a43a;
    --red: #d95c5c;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    min-height: 100vh;
    display: grid;
    place-items: center;
    background: radial-gradient(circle at top, #162233 0, var(--bg) 45%);
    color: var(--text);
    font: 14px/1.5 Arial, sans-serif;
  }
  .card {
    width: min(520px, calc(100vw - 32px));
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 28px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
  }
  .eyebrow {
    color: var(--red);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
  }
  h1 {
    margin: 10px 0 8px;
    font-size: 28px;
    color: var(--gold);
  }
  p {
    margin: 0 0 18px;
    color: var(--muted);
  }
  .actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }
  a {
    display: inline-block;
    padding: 10px 14px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
  }
  .primary {
    background: var(--gold);
    color: #111;
  }
  .secondary {
    border: 1px solid var(--border);
    color: var(--text);
  }
</style>
</head>
<body>
  <div class="card">
    <div class="eyebrow">403</div>
    <h1>Access denied</h1>
    <p>You do not have permission to open this module. Ask an administrator to enable the matching `MDI_*` access option in User Access.</p>
    <div class="actions">
      <a class="primary" href="{{ url('/dashboard') }}">Back to dashboard</a>
      <a class="secondary" href="{{ url('/logout') }}">Logout</a>
    </div>
  </div>
</body>
</html>
