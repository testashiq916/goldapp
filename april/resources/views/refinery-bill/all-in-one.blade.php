<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Refinery Bill - All in one</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,system-ui,-apple-system,sans-serif;background:linear-gradient(180deg,#eef4ff 0%,#f8fafc 100%);color:#0f172a;min-height:100vh}
.wrap{max-width:1180px;margin:0 auto;padding:24px}
.hero{background:linear-gradient(135deg,#1d4ed8 0%,#2563eb 45%,#0f766e 100%);color:#fff;border-radius:20px;padding:26px 28px;box-shadow:0 18px 40px rgba(37,99,235,.20)}
.hero h1{font-size:30px;line-height:1.1;margin-bottom:8px}
.hero p{font-size:14px;opacity:.92;max-width:760px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;margin-top:22px}
.card{background:#fff;border:1px solid #dbe7f5;border-radius:18px;padding:18px;box-shadow:0 12px 30px rgba(15,23,42,.06);display:flex;flex-direction:column;gap:10px}
.eyebrow{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#2563eb}
.card h2{font-size:20px;line-height:1.1}
.card p{font-size:13px;color:#475569;line-height:1.45;min-height:38px}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:auto}
.btn{appearance:none;border:none;border-radius:10px;padding:11px 14px;font-size:13px;font-weight:700;cursor:pointer}
.btn.primary{background:#2563eb;color:#fff}
.btn.secondary{background:#e2e8f0;color:#0f172a}
.btn:hover{filter:brightness(.98)}
.tips{margin-top:22px;background:#fff;border:1px solid #dbe7f5;border-radius:18px;padding:18px 20px}
.tips h3{font-size:16px;margin-bottom:8px}
.tips ul{padding-left:18px;color:#475569;font-size:13px;line-height:1.6}
@media (max-width:700px){
  .wrap{padding:14px}
  .hero{padding:18px}
  .hero h1{font-size:24px}
}
</style>
</head>
<body>
<div class="wrap">
  <section class="hero">
    <h1>Refinery Bill All in one</h1>
    <p>Open the full refinery workflow from one place. Use this launcher to go straight to new entry, edit, cancel, or returns without leaving the dashboard module frame.</p>
  </section>

  <section class="grid">
    <article class="card">
      <div class="eyebrow">Entry</div>
      <h2>New Entry</h2>
      <p>Create a new refinery bill entry and start directly in the item grid.</p>
      <div class="actions">
        <button class="btn primary" data-url="{{ url('/refinery-bill/bill') }}">Open</button>
      </div>
    </article>

    <article class="card">
      <div class="eyebrow">Modify</div>
      <h2>Edit Entry</h2>
      <p>Search an existing refinery bill and load it into edit mode.</p>
      <div class="actions">
        <button class="btn primary" data-url="{{ url('/refinery-bill/picker/edit?title=Refinery%20Bill%20-%20Edit') }}">Open</button>
      </div>
    </article>

    <article class="card">
      <div class="eyebrow">Control</div>
      <h2>Cancel Entry</h2>
      <p>Locate a saved refinery bill and cancel it with the dedicated picker.</p>
      <div class="actions">
        <button class="btn primary" data-url="{{ url('/refinery-bill/picker/cancel?title=Refinery%20Bill%20-%20Cancel') }}">Open</button>
      </div>
    </article>

    <article class="card">
      <div class="eyebrow">Return</div>
      <h2>Returns</h2>
      <p>Create or edit refinery return entries from the return screen.</p>
      <div class="actions">
        <button class="btn primary" data-url="{{ url('/refinery-return/bill') }}">Open</button>
      </div>
    </article>
  </section>

  <section class="tips">
    <h3>Quick notes</h3>
    <ul>
      <li>`New Entry` starts with a preview document number and focus in `Item Code`.</li>
      <li>`Edit` and `Cancel` open the existing document picker.</li>
      <li>`Returns` opens the refinery return module.</li>
    </ul>
  </section>
</div>

<script>
document.querySelectorAll('[data-url]').forEach(btn => {
  btn.addEventListener('click', () => {
    window.location.href = btn.dataset.url;
  });
});
</script>
</body>
</html>
