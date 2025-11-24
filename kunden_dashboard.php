<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

// Zugangsschutz
if (!isset($_SESSION['kunde'])) {
    header('Location: kunden_login.php');
    exit;
}

$kunde_id      = $_SESSION['kunde'];
$kunde_name    = $_SESSION['kunde_name'] ?? $kunde_id;
$traegerFilter = $_SESSION['traeger_filter'] ?? '';

// Schulungsdaten laden
$events = [];
$file = __DIR__ . '/data/schulungen.json';
if (file_exists($file)) {
    $json = file_get_contents($file);
    $events = json_decode($json, true);
    if (!is_array($events)) {
        $events = [];
    }
}

$nowDate = date('Y-m-d');

// Nach Träger + Zukunft filtern
$filtered = array_values(array_filter($events, function($e) use ($traegerFilter, $nowDate) {
    $traeger = $e['traeger'] ?? '';
    $datum   = $e['datum'] ?? '';
    if ($traegerFilter !== '' && $traeger !== $traegerFilter) {
        return false;
    }
    if ($datum < $nowDate) return false;
    return true;
}));

usort($filtered, function($a,$b){
    $da = $a['datum'] ?? '';
    $db = $b['datum'] ?? '';
    $ta = $a['von'] ?? '';
    $tb = $b['von'] ?? '';
    return strcmp($da.$ta, $db.$tb);
});

function format_date_eu($d) {
    if (strpos($d,'-') === false) return $d;
    [$y,$m,$day] = explode('-', $d);
    return sprintf('%02d.%02d.%04d', $day, $m, $y);
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>Kundenbereich – Ihre Schulungstermine</title>
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
    }
    .card{
      background:#fff;
      padding:20px;
      border-radius:12px;
      box-shadow:0 6px 18px rgba(0,0,0,0.08);
      margin-bottom:20px;
    }
    h1,h2{color:var(--color-dark);margin-top:0;}
    table{width:100%;border-collapse:collapse;font-size:0.9rem;}
    th,td{padding:6px 8px;vertical-align:top;}
    th{border-bottom:1px solid #e0e4ec;text-align:left;color:#334;}
    tr:nth-child(even){background:#f8f9fd;}
    .date-head{
      margin:12px 0 4px;
      font-weight:600;
      color:var(--color-dark);
    }
    .badge{
      display:inline-block;
      padding:2px 6px;
      font-size:0.75rem;
      background:#eef3ff;
      border-radius:999px;
      color:var(--color-dark);
      margin-left:6px;
    }
    .empty{
      color:#777;
      font-size:0.9rem;
    }
    .topbar{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
      margin-bottom:10px;
    }
    .topbar a{
      font-size:0.85rem;
      text-decoration:none;
      color:#fff;
      background:#d9534f;
      padding:6px 10px;
      border-radius:6px;
    }
    .topbar a:hover{
      background:#c64541;
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
    <div class="topbar">
      <h1>Schulungstermine für <?php echo htmlspecialchars($kunde_name, ENT_QUOTES, 'UTF-8'); ?></h1>
      <a href="kunden_logout.php">Abmelden</a>
    </div>
    <p style="font-size:0.9rem;color:#555;">
      Hier sehen Sie alle geplanten zukünftigen Termine, die Ihrem Träger zugeordnet sind.
    </p>
<a href="kunden_export_pdf.php" style="
  display:inline-block;
  background:#005BBB;
  padding:8px 14px;
  color:white;
  border-radius:6px;
  text-decoration:none;
  font-size:0.9rem;
  margin-bottom:10px;
">
📄 Termine als PDF herunterladen
</a>
    <?php if (empty($filtered)): ?>
      <p class="empty">Derzeit sind keine zukünftigen Termine für Sie eingetragen.</p>
    <?php else: ?>
      <?php
      $currentDate = null;
      foreach ($filtered as $ev):
        $datum  = $ev['datum'] ?? '';
        $von    = $ev['von'] ?? '';
        $bis    = $ev['bis'] ?? '';
        $lg     = $ev['lehrgang'] ?? '';
        $ort    = $ev['ort'] ?? '';
        $beschr = $ev['beschreibung'] ?? '';
        $dauer  = $ev['dauer'] ?? '';
        if ($datum !== $currentDate) {
            if ($currentDate !== null) {
                echo "</tbody></table>";
            }
            echo "<div class='date-head'>".format_date_eu($datum)." <span class='badge'>".htmlspecialchars($lg,ENT_QUOTES,'UTF-8')."</span></div>";
            echo "<table><thead><tr><th>Zeit</th><th>Ort</th><th>Details</th></tr></thead><tbody>";
            $currentDate = $datum;
        }
      ?>
        <tr>
          <td>
            <?php echo htmlspecialchars($von,ENT_QUOTES,'UTF-8'); ?> – <?php echo htmlspecialchars($bis,ENT_QUOTES,'UTF-8'); ?> Uhr
            <?php if($dauer !== '' && $dauer !== null): ?>
              <br><small>(<?php echo htmlspecialchars(number_format((float)$dauer,2,',','.'),ENT_QUOTES,'UTF-8'); ?> Std)</small>
            <?php endif; ?>
          </td>
          <td><?php echo htmlspecialchars($ort,ENT_QUOTES,'UTF-8'); ?></td>
          <td><?php echo nl2br(htmlspecialchars($beschr,ENT_QUOTES,'UTF-8')); ?></td>
        </tr>
      <?php endforeach;
      if ($currentDate !== null) echo "</tbody></table>";
      ?>
    <?php endif; ?>
  </div>
</div>

<footer id="footer"></footer>
<script>
fetch('footer.html').then(r=>r.text()).then(t=>document.getElementById('footer').innerHTML=t).catch(()=>{});
</script>

</body>
</html>
