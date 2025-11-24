<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

// Wenn schon eingeloggt -> direkt zum Dashboard
if (isset($_SESSION['kunde'])) {
    header('Location: kunden_dashboard.php');
    exit;
}

$fehler = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $benutzer = trim($_POST['benutzer'] ?? '');
    $passwort = trim($_POST['passwort'] ?? '');

    $file = __DIR__ . '/data/kunden.json';
    $kunden = [];

    if (file_exists($file)) {
        $json = file_get_contents($file);
        $kunden = json_decode($json, true);
        if (!is_array($kunden)) {
            $kunden = [];
        }
    }

    $gefunden = null;
    foreach ($kunden as $k) {
        if (($k['kunde'] ?? '') === $benutzer && ($k['passwort'] ?? '') === $passwort) {
            $gefunden = $k;
            break;
        }
    }

    if ($gefunden) {
        // Login erfolgreich
        $_SESSION['kunde']        = $gefunden['kunde'];
        $_SESSION['kunde_name']   = $gefunden['name'] ?? $gefunden['kunde'];
        $_SESSION['traeger_filter'] = $gefunden['traeger_filter'] ?? '';
        header('Location: kunden_dashboard.php');
        exit;
    } else {
        $fehler = 'Benutzername oder Passwort falsch.';
    }
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>Kunden-Login – Schulungsübersicht</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --color-primary:#005BBB;
      --color-bg:#F7F9FC;
      --color-dark:#0A1F44;
    }
    *{box-sizing:border-box;}
    body{
      font-family:Inter,system-ui,sans-serif;
      background:var(--color-bg);
      margin:0;
    }
    .container{
      max-width:1100px;
      margin:30px auto;
      padding:20px;
      display:flex;
      justify-content:center;
    }
    .card{
      background:#fff;
      padding:24px 28px;
      border-radius:12px;
      box-shadow:0 8px 24px rgba(0,0,0,0.08);
      width:100%;
      max-width:380px;
    }
    h1{margin:0 0 12px;font-size:1.4rem;color:var(--color-dark);}
    label{font-weight:600;font-size:0.9rem;}
    input{
      width:100%;
      padding:10px;
      margin-top:6px;
      margin-bottom:14px;
      border-radius:8px;
      border:1px solid #ccd7e0;
      font-size:0.95rem;
    }
    button{
      width:100%;
      padding:10px 16px;
      border:none;
      border-radius:8px;
      background:var(--color-primary);
      color:#fff;
      font-weight:600;
      cursor:pointer;
      font-size:0.95rem;
    }
    button:hover{background:#006fe0;}
    .error{
      color:#c00;
      font-size:0.85rem;
      margin-bottom:10px;
    }
    .hint{
      font-size:0.8rem;
      color:#666;
      margin-top:10px;
    }
  </style>
</head>
<body>

<header id="nav"></header>
<script>
fetch('nav.html').then(r=>r.text()).then(t=>document.getElementById('nav').innerHTML=t).catch(()=>{});
</script>

<div class="container">
  <div class="card">
    <h1>Kunden-Login</h1>
    <p style="font-size:0.9rem;color:#555;">
      Bitte melden Sie sich an, um Ihre Schulungstermine zu sehen.
    </p>

    <?php if ($fehler): ?>
      <div class="error"><?php echo htmlspecialchars($fehler, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="post">
      <label for="benutzer">Benutzername</label>
      <input type="text" id="benutzer" name="benutzer" required>

      <label for="passwort">Passwort</label>
      <input type="password" id="passwort" name="passwort" required>

      <button type="submit">Anmelden</button>
    </form>

    <div class="hint">
      Zugangsdaten erhalten Sie von Ihrem Dozenten.
    </div>
  </div>
</div>

<footer id="footer"></footer>
<script>
fetch('footer.html').then(r=>r.text()).then(t=>document.getElementById('footer').innerHTML=t).catch(()=>{});
</script>

</body>
</html>
