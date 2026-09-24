<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';
use Exodo\Auth;
use Exodo\Csrf;

Auth::startSession();
$user = Auth::user();

if (!$user) {
    // Mostrar login
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            $error = 'Token CSRF inválido.';
        } elseif (Auth::attempt(trim($_POST['username'] ?? ''), $_POST['password'] ?? '')) {
            header('Location: /admin/'); exit;
        } else {
            $error = 'Credenciales incorrectas o demasiados intentos.';
        }
    }
    ?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><link rel="icon" type="image/png" href="/assets/img/logo-exodo-transparent.png"><title>Login — ÉXODO Admin</title>
<style>
*{box-sizing:border-box}
body{font-family:system-ui,sans-serif;background:#1C1C1C;color:#F5F0E8;display:flex;justify-content:center;align-items:center;min-height:100vh;min-height:100svh;margin:0;padding:clamp(1rem,5vw,2rem);line-height:1.5}
.card{background:#262626;padding:clamp(1.4rem,6vw,2.5rem);border:1px solid #3a3a3a;border-radius:12px;width:min(100%,24rem);box-shadow:0 18px 55px #0005,inset 0 3px 0 #E8501A}
h1{font-size:clamp(1.35rem,6vw,1.6rem);line-height:1.2;margin:0 0 1.5rem;letter-spacing:.04em}
label{display:block;margin:1rem 0 .4rem;font-size:1rem;color:#e2ddd5}
input{display:block;width:100%;min-height:48px;padding:.75rem;border-radius:6px;border:1px solid #555;background:#1a1a1a;color:#F5F0E8;font-size:16px}
input:focus-visible,button:focus-visible{outline:3px solid #f4a17f;outline-offset:3px}
button{background:#E8501A;color:#fff;border:none;min-height:50px;padding:.85rem 1rem;border-radius:6px;font-weight:700;width:100%;margin-top:1.5rem;cursor:pointer;font-size:1rem}
button:hover{background:#c94415}
.err{background:#4a1a1a;padding:.8rem;border-radius:6px;margin-bottom:1rem;color:#ffaaaa;font-size:.95rem;overflow-wrap:anywhere}
@media(max-width:360px){body{padding:.75rem}.card{padding:1.25rem}}
</style></head><body><div class="card">
<h1>ÉXODO — Admin</h1>
<?php if ($error): ?><div class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
<label>Usuario</label><input type="text" name="username" required autofocus>
<label>Contraseña</label><input type="password" name="password" required>
<button type="submit">Entrar</button>
</form>
</div></body></html>
    <?php
    exit;
}

// Dashboard
$role = $user['role'];
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><link rel="icon" type="image/png" href="/assets/img/logo-exodo-transparent.png"><title>Panel — ÉXODO</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
*{box-sizing:border-box;margin:0;padding:0}
[hidden]{display:none!important}
:root{color-scheme:dark;--panel:#262626;--panel-soft:#202020;--line:#3a3a3a;--accent:#e8501a;--text:#f5f0e8;--muted:#b8b2a8}
body{font-family:Inter,system-ui,sans-serif;background:#1c1c1c;color:var(--text);padding:clamp(.8rem,3vw,1.6rem);line-height:1.5;max-width:1500px;margin:0 auto}
header{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1.2rem;border-bottom:2px solid var(--accent);padding-bottom:1rem}
h1,h2,h3{font-family:'Barlow Condensed',Impact,sans-serif;line-height:1.05}
h1{font-size:clamp(1.5rem,4vw,2rem);letter-spacing:.02em;display:flex;align-items:center;gap:.65rem}
.admin-logo{width:52px;height:52px;object-fit:contain;border-radius:50%}
.user{font-size:.85rem;color:var(--muted);margin-right:.65rem}
.btn{background:var(--accent);color:#fff;border:none;padding:.7rem 1rem;border-radius:6px;font-weight:750;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;min-height:46px;font-size:.92rem;transition:filter .18s,transform .12s}
.btn:hover{filter:brightness(1.08)}.btn:active{transform:translateY(1px)}.btn:focus-visible,.tab:focus-visible,input:focus-visible,textarea:focus-visible,select:focus-visible{outline:3px solid #f4a17f;outline-offset:2px}
.btn-ghost{background:#333;color:var(--text)}
.tabs{display:flex;gap:.5rem;margin-bottom:1.2rem;flex-wrap:wrap}
.tab{background:var(--panel);color:var(--text);border:1px solid #444;padding:.7rem 1.15rem;border-radius:7px;cursor:pointer;font-weight:700;font-size:.95rem;min-height:46px}
.tab.active{background:var(--accent);border-color:var(--accent)}
.section{display:none}.section.active{display:block}
.section-heading{margin:0 0 1rem}.section-heading h2{font-size:1.7rem}.section-heading p{color:var(--muted);margin-top:.35rem;max-width:55rem}
.section-note{margin:.8rem 0 1rem;padding:.7rem .85rem;border-left:3px solid var(--accent);background:var(--panel-soft);color:var(--muted);border-radius:6px;font-size:.9rem}
.row{display:flex;gap:.85rem;margin-bottom:1rem;align-items:end;flex-wrap:wrap}
.field{display:flex;flex-direction:column;gap:.4rem;flex:1;min-width:190px}
.field label,.product-editor label,.status-editor label{font-size:.82rem;color:var(--muted);font-weight:650}
select,input,textarea{width:100%;background:#171717;color:var(--text);border:1px solid #4b4b4b;border-radius:6px;padding:.72rem .75rem;font-size:1rem;min-height:46px}
textarea{resize:vertical;min-height:6rem;line-height:1.45}
.filters .btn{min-width:120px}
table{width:100%;border-collapse:collapse;background:var(--panel);border-radius:8px;overflow:hidden}
th,td{padding:.78rem;text-align:left;border-bottom:1px solid #333;font-size:.9rem;vertical-align:middle}
th{background:#1a1a1a;color:#f07a4e;text-transform:uppercase;font-size:.75rem;letter-spacing:.06em;white-space:nowrap}
tr:hover{background:#2b2b2b}
.status{display:inline-flex;padding:.25rem .55rem;border-radius:999px;font-size:.74rem;font-weight:750;white-space:nowrap}
.status.received{background:#383838;color:#fff}.status.preparing{background:#61400b;color:#ffdb80}
.status.ready_for_pickup,.status.ready_for_delivery{background:#1e4a32;color:#a8edbd}
.status.on_the_way{background:#1b3e4b;color:#ade4f3}.status.delivered{background:#353535;color:#c1c1c1}.status.cancelled{background:#4a2525;color:#ffb0b0}
.order-table-wrap{overflow-x:auto;border-radius:8px}.orders-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,330px),1fr));gap:.85rem}
.order-card,.product-editor,.settings-card{background:var(--panel);border:1px solid #303030;border-radius:10px;padding:1rem;min-width:0}
.order-card.has-customization{border-color:#e8501a;background:linear-gradient(145deg,#2f211c 0%,var(--panel) 42%);box-shadow:0 10px 28px #0005,0 0 0 2px #e8501a33}
.order-card-head{display:flex;justify-content:space-between;align-items:center;gap:.75rem;padding-bottom:.7rem;border-bottom:1px solid var(--line)}
.order-card h2{font-size:1.25rem}.order-card-data{display:grid;grid-template-columns:1fr 1fr;gap:.65rem .9rem;padding:.85rem 0;color:var(--muted);font-size:.86rem}
.order-card-data strong{display:block;color:var(--text);font-size:.95rem;overflow-wrap:anywhere}
.custom-order-badge{display:inline-flex;align-items:center;gap:.25rem;padding:.3rem .55rem;border-radius:999px;background:#e8501a;color:#fff;font-size:.68rem;font-weight:850;letter-spacing:.06em;text-transform:uppercase;white-space:nowrap}
.order-card .btn{width:100%}
.order-kitchen{margin:0 0 .85rem;padding:.75rem .8rem;border:1px solid var(--line);border-radius:8px;background:#1b1b1b}
.order-kitchen.has-customization{border-color:#8d4228;background:#321f18}
.order-kitchen>strong{display:block;margin-bottom:.45rem;color:#f2b49c;font-size:.72rem;letter-spacing:.08em;text-transform:uppercase}
.order-kitchen ul{list-style:none;display:grid;gap:.6rem}
.order-kitchen li{display:grid;gap:.18rem;min-width:0;font-size:.88rem;font-weight:700;overflow-wrap:anywhere}
.order-kitchen li small{font-size:.8rem;font-weight:500;line-height:1.4;white-space:pre-wrap}
.kitchen-custom-label{color:#ffd7c7;font-size:.68rem!important;font-weight:850!important;letter-spacing:.05em;text-transform:uppercase}
.kitchen-removals{color:#ff8b83}.kitchen-additions{color:#89e1a3}.kitchen-note{color:#d1cbc2}
.sales-summary{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin:0 0 1rem;padding:.85rem 1rem;border:1px solid #3c3c3c;border-radius:9px;background:#222}
.sales-summary-item{display:grid;gap:.12rem;min-width:0}
.sales-summary-item span{color:var(--muted);font-size:.78rem;font-weight:650}
.sales-summary-item strong{color:var(--text);font-size:1.08rem;font-variant-numeric:tabular-nums}
.sales-summary-note{max-width:22rem;color:var(--muted);font-size:.75rem;line-height:1.4;text-align:right}
.analytics-filter-panel,.analytics-card{background:var(--panel);border:1px solid #343434;border-radius:12px}
.analytics-filter-panel{padding:1rem;margin-bottom:1rem}
.analytics-filters{display:grid;grid-template-columns:1.1fr 1fr 2fr auto;gap:.75rem;align-items:end}
.analytics-filters .field{min-width:0}.analytics-filters .btn{min-width:132px}
.analytics-date-fields{display:grid;grid-template-columns:1fr 1fr;gap:.65rem}.analytics-date-fields[hidden]{display:none}
.analytics-status{margin:.75rem 0;color:var(--muted);font-size:.9rem}.analytics-status:empty{display:none}
.analytics-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem;margin-bottom:1rem}
.analytics-kpi{min-width:0;padding:1rem;background:linear-gradient(145deg,#292929,#202020);border:1px solid #363636;border-radius:11px}
.analytics-kpi-label{display:block;color:var(--muted);font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase}
.analytics-kpi-value{display:block;margin-top:.42rem;color:var(--text);font-size:clamp(1.15rem,2vw,1.65rem);font-weight:850;line-height:1.2;overflow-wrap:anywhere;font-variant-numeric:tabular-nums}
.analytics-kpi-note{display:block;margin-top:.3rem;color:#9c968d;font-size:.75rem}
.analytics-kpi--featured{border-color:#7e3d25;background:linear-gradient(145deg,#3a251d,#24201e)}
.analytics-kpi--featured .analytics-kpi-value{color:#ff986e}
.analytics-layout{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(280px,1fr);gap:.9rem;align-items:stretch}
.analytics-card{padding:1rem;min-width:0}.analytics-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:.8rem;margin-bottom:.9rem}
.analytics-card-head h3{font-size:1.3rem}.analytics-card-head p{color:var(--muted);font-size:.8rem;margin-top:.25rem}
.analytics-chart-wrap{width:100%;min-height:250px}.analytics-chart{display:block;width:100%;height:auto;min-height:220px;overflow:visible}
.analytics-chart .grid-line{stroke:#3b3b3b;stroke-width:1}.analytics-chart .axis-label{fill:#a8a198;font:12px Inter,system-ui,sans-serif}.analytics-chart .chart-area{fill:url(#salesArea)}
.analytics-chart .chart-line{fill:none;stroke:#f06a37;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}.analytics-chart .chart-point{fill:#ff986e;stroke:#262626;stroke-width:3}
.analytics-chart .chart-empty{fill:#a8a198;font:14px Inter,system-ui,sans-serif}
.ranking-list{display:grid;gap:.85rem;list-style:none}.ranking-item{display:grid;gap:.35rem;min-width:0}
.ranking-head{display:flex;justify-content:space-between;gap:.65rem;align-items:baseline;font-size:.85rem}.ranking-name{font-weight:750;overflow-wrap:anywhere}.ranking-units{flex:none;color:#ff986e;font-weight:800;white-space:nowrap}
.ranking-track{height:8px;overflow:hidden;border-radius:999px;background:#161616}.ranking-bar{height:100%;border-radius:inherit;background:linear-gradient(90deg,#b83d17,#ff7545)}
.ranking-foot{color:var(--muted);font-size:.75rem}.analytics-empty{padding:2rem .8rem;text-align:center;color:var(--muted);font-size:.9rem}
.product-editors{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr));gap:.9rem}
.product-editor{display:grid;gap:.8rem}.product-editor>header{margin:0;padding:0 0 .65rem;border:0;border-bottom:1px solid var(--line)}.product-editor h2{font-size:1.4rem}
.product-editor small{color:var(--muted);line-height:1.4}
.product-image-editor{display:flex;align-items:center;gap:.85rem;min-width:0}
.product-image-preview{flex:0 0 112px;width:112px;height:84px;object-fit:cover;border:1px solid #4b4b4b;border-radius:7px;background:#171717}
.product-image-controls{display:grid;gap:.4rem;min-width:0;flex:1}
.product-image-controls label{color:var(--muted);font-size:.82rem;font-weight:650}
.product-image-controls input[type=file]{min-height:44px;padding:.55rem;font-size:.82rem}
.product-editor input[type=checkbox]{width:22px;height:22px;min-height:22px;accent-color:var(--accent)}
.availability{display:flex;align-items:center;gap:.6rem;color:var(--text)!important;font-size:.95rem!important;min-height:44px}
.save-product{width:100%}.settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.9rem}
.settings-card{display:grid;gap:.85rem}.settings-card h2{font-size:1.35rem}.settings-card .field{min-width:0}
.global-addons{margin-bottom:1rem}.global-addons>p{color:var(--muted);font-size:.9rem;max-width:58rem}
.global-addon-list{display:grid}.global-addon-row{display:grid;grid-template-columns:minmax(180px,1fr) minmax(150px,.65fr) minmax(170px,.8fr);gap:1rem;align-items:center;padding:.8rem 0;border-bottom:1px solid var(--line)}
.global-addon-row:last-child{border-bottom:0}.global-addon-name{font-weight:750}.global-addon-name small{display:block;margin-top:.12rem;color:var(--muted);font-weight:450}
.global-addon-price{display:grid;gap:.25rem}.global-addon-price label{color:var(--muted);font-size:.78rem;font-weight:650}.global-addon-availability{display:flex;align-items:center;gap:.55rem;min-height:44px;font-weight:650}
.global-addon-availability input{width:22px;height:22px;min-height:22px;accent-color:var(--accent)}.global-addons-status{color:var(--muted);font-size:.82rem}.global-addons-status:empty{display:none}
.settings-save{width:100%;margin-top:1rem}
.empty{padding:2rem;text-align:center;color:var(--muted);background:var(--panel);border-radius:8px}
.toast{position:fixed;left:50%;bottom:max(1rem,env(safe-area-inset-bottom));transform:translateX(-50%);background:#1c5135;color:#c5f0d2;padding:.8rem 1.1rem;border-radius:8px;font-weight:700;z-index:1100;display:none;max-width:min(92vw,32rem);box-shadow:0 12px 34px #0008}.toast.err{background:#612d26;color:#ffd1c6}
.admin-dialog{width:min(560px,calc(100vw - 1rem));max-height:calc(100dvh - 1rem);padding:0;border:1px solid #4a4a4a;border-radius:12px;background:#202020;color:var(--text);margin:auto;box-shadow:0 20px 70px #0008}
.admin-dialog::backdrop{background:#000a;backdrop-filter:blur(3px)}
.order-sheet{max-height:calc(100dvh - 1rem);overflow:auto;padding:1rem;overscroll-behavior:contain}
.order-sheet>header{position:sticky;top:-1rem;z-index:2;background:#202020;padding:.25rem 0 .8rem;margin-bottom:.8rem}
.order-sheet>header h2{font-size:1.65rem}.order-sheet>header .btn{min-width:46px;padding:.45rem;font-size:1.2rem}
.order-info{display:grid;gap:.8rem}.order-info p{overflow-wrap:anywhere}.order-info ul{padding-left:1.2rem;display:grid;gap:.4rem}.order-info li small{display:block;color:var(--muted);overflow-wrap:anywhere}
.status-editor{display:grid;gap:.65rem;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--line)}
.dialog-actions{display:grid;grid-template-columns:1fr 1fr;gap:.6rem}.dialog-actions .btn{width:100%}
@media(max-width:700px){
 body{padding:.75rem;padding-bottom:max(1rem,env(safe-area-inset-bottom))}
 header{align-items:flex-start;padding-bottom:.8rem;margin-bottom:.9rem}header>div{display:flex;align-items:center;gap:.35rem}.user{font-size:.75rem;margin:0;text-align:right}
 .tabs{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.4rem;position:sticky;top:0;z-index:5;background:#1c1c1c;padding:.35rem 0 .55rem;margin:0 0 .9rem}
 .tabs--owner{grid-template-columns:repeat(2,minmax(0,1fr))}
 .tab{padding:.6rem .35rem;font-size:.86rem;min-width:0}
 .sales-summary{display:grid;grid-template-columns:1fr 1fr;gap:.65rem}.sales-summary-note{grid-column:1/-1;max-width:none;text-align:left}
 .analytics-filters{grid-template-columns:1fr 1fr}.analytics-filters>.btn{grid-column:1/-1;width:100%}.analytics-date-fields{display:grid;grid-column:1/-1;grid-template-columns:1fr 1fr;gap:.65rem}.analytics-date-fields[hidden]{display:none}
 .analytics-kpis{grid-template-columns:1fr 1fr;gap:.6rem}.analytics-kpi{padding:.8rem}.analytics-layout{grid-template-columns:1fr}.analytics-chart-wrap{min-height:210px}.analytics-chart{min-height:200px}
 .filters{display:grid;grid-template-columns:1fr 1fr;gap:.65rem}.filters .field{min-width:0}.filters>.btn{grid-column:1/-1;width:100%}
 .order-table-wrap{display:none}.orders-cards{grid-template-columns:1fr;gap:.7rem}.order-card{padding:.85rem}.order-card-data{gap:.55rem .75rem;padding:.7rem 0}
 .product-editors{grid-template-columns:1fr}.settings-grid{grid-template-columns:1fr}.product-editor,.settings-card{padding:.85rem}
 .global-addon-row{grid-template-columns:1fr 1fr;gap:.45rem .8rem}.global-addon-name{grid-column:1/-1}.global-addon-availability{align-self:end}
 .product-image-editor{align-items:flex-start}.product-image-preview{flex-basis:84px;width:84px;height:72px}
 .dialog-actions{grid-template-columns:1fr}.order-sheet{padding:.85rem}.order-sheet>header{top:-.85rem}
}
@media(max-width:360px){.user{display:none}.filters{grid-template-columns:1fr}.filters>.btn{grid-column:auto}.order-card-data{grid-template-columns:1fr 1fr}}
</style></head><body>

<header>
<h1><img class="admin-logo" src="/assets/img/logo-exodo-transparent.png" alt=""> Panel</h1>
<div>
<span class="user"><?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($role) ?>)</span>
<a href="/admin/logout.php" class="btn btn-ghost" style="margin-left:1rem">Salir</a>
</div>
</header>

<nav class="tabs<?= $role === 'owner' ? ' tabs--owner' : '' ?>" aria-label="Secciones del panel">
<button type="button" class="tab active" data-tab="orders" aria-current="page">Pedidos</button>
<?php if ($role === 'owner'): ?>
<button type="button" class="tab" data-tab="analytics">Administración</button>
<button type="button" class="tab" data-tab="products">Productos</button>
<button type="button" class="tab" data-tab="settings">Configuración</button>
<?php endif; ?>
</nav>

<div class="section active" id="section-orders">
<div class="sales-summary" id="salesSummary" role="status" aria-live="polite">
<div class="sales-summary-item"><span>Ventas acumuladas de la web</span><strong id="salesTotal">$ 0</strong></div>
<div class="sales-summary-item"><span>Pedidos incluidos</span><strong id="salesOrderCount">0</strong></div>
<p class="sales-summary-note">Histórico: incluye pedidos pendientes; excluye cancelados, pagos fallidos y reintegrados.</p>
</div>
<div class="row filters">
<div class="field"><label>Filtrar estado</label>
<select id="filterStatus">
<option value="">Todos</option>
<option value="received">Recibidos</option>
<option value="preparing">En preparación</option>
<option value="ready_for_pickup">Listos para retirar</option>
<option value="ready_for_delivery">Listos para enviar</option>
<option value="on_the_way">En camino</option>
<option value="delivered">Entregados</option>
<option value="cancelled">Cancelados</option>
</select></div>
<div class="field"><label>Filtrar modalidad</label>
<select id="filterDelivery">
<option value="">Todas</option>
<option value="pickup">Retiro</option>
<option value="delivery">Delivery</option>
</select></div>
<button class="btn" onclick="loadOrders()">Actualizar</button>
</div>
<div id="ordersList"></div>
</div>

<?php if ($role === 'owner'): ?>
<div class="section" id="section-analytics">
<div class="section-heading"><h2>Administración</h2><p>Revisá cuánto vendiste, cómo evoluciona el negocio y cuáles hamburguesas salen más.</p></div>
<div class="analytics-filter-panel">
<div class="analytics-filters">
<div class="field"><label for="analyticsRange">Período</label><select id="analyticsRange">
<option value="7d">Últimos 7 días</option><option value="30d" selected>Últimos 30 días</option><option value="month">Este mes</option><option value="all">Histórico completo</option><option value="custom">Elegir fechas</option>
</select></div>
<div class="field"><label for="analyticsDelivery">Modalidad</label><select id="analyticsDelivery"><option value="">Todas</option><option value="pickup">Retiro</option><option value="delivery">Delivery</option></select></div>
<div class="analytics-date-fields" id="analyticsDateFields" hidden>
<div class="field"><label for="analyticsFrom">Desde</label><input id="analyticsFrom" type="date"></div>
<div class="field"><label for="analyticsTo">Hasta</label><input id="analyticsTo" type="date"></div>
</div>
<button type="button" class="btn" id="analyticsApply">Aplicar filtros</button>
</div>
</div>
<p class="analytics-status" id="analyticsStatus" role="status" aria-live="polite"></p>
<div id="analyticsContent" hidden>
<div class="analytics-kpis">
<article class="analytics-kpi analytics-kpi--featured"><span class="analytics-kpi-label">Ventas del período</span><strong class="analytics-kpi-value" id="analyticsSales">$ 0</strong><small class="analytics-kpi-note">Total de pedidos válidos</small></article>
<article class="analytics-kpi"><span class="analytics-kpi-label">Pedidos</span><strong class="analytics-kpi-value" id="analyticsOrders">0</strong><small class="analytics-kpi-note">Pendientes incluidos</small></article>
<article class="analytics-kpi"><span class="analytics-kpi-label">Ticket promedio</span><strong class="analytics-kpi-value" id="analyticsAverage">$ 0</strong><small class="analytics-kpi-note">Ventas ÷ pedidos</small></article>
<article class="analytics-kpi"><span class="analytics-kpi-label">Más vendida</span><strong class="analytics-kpi-value" id="analyticsTopProduct">—</strong><small class="analytics-kpi-note" id="analyticsTopUnits">Sin unidades vendidas</small></article>
</div>
<div class="analytics-layout">
<section class="analytics-card"><div class="analytics-card-head"><div><h3>Evolución de ventas</h3><p id="analyticsChartCaption">Importe total por día</p></div></div><div class="analytics-chart-wrap" id="analyticsChart"></div></section>
<section class="analytics-card"><div class="analytics-card-head"><div><h3>Hamburguesas más vendidas</h3><p>Ordenadas por unidades pedidas</p></div></div><ol class="ranking-list" id="analyticsRanking"></ol></section>
</div>
<p class="section-note">Las ventas incluyen pedidos pendientes y pagados. Se excluyen cancelaciones, pagos fallidos y reintegros. El ranking suma las hamburguesas por separado del costo de envío.</p>
</div>
</div>

<div class="section" id="section-products">
<div class="section-heading"><h2>Menú y productos</h2><p>Actualizá la información que ve el cliente. Los cambios se guardan al tocar cada botón.</p></div>
<p class="section-note">Los precios de las hamburguesas están cargados como muestra. Cargá los valores reales antes de recibir pedidos.</p>
<form class="settings-card global-addons" id="globalAddOnsForm">
<h2>Extras para todas las hamburguesas</h2>
<p>Definí cuánto cuesta cada porción y activá los extras que quieras vender. Se ofrecerán con el mismo precio en todas las hamburguesas. El medallón comienza pausado: fijá su precio y activalo cuando quieras ofrecerlo.</p>
<div class="global-addon-list" id="globalAddOnsList"><div class="empty">Cargando precios…</div></div>
<p class="global-addons-status" id="globalAddOnsStatus" role="status" aria-live="polite"></p>
<button type="submit" class="btn" id="globalAddOnsSave">Guardar precios de extras</button>
</form>
<div id="productsList"></div>
</div>

<div class="section" id="section-settings">
<div class="settings-grid">
<section class="settings-card"><h2>Datos del local</h2><div class="field">
<label>Nombre del local</label><input type="text" id="s_restaurant_name">
</div><div class="field">
<label>Dirección</label><input type="text" id="s_restaurant_address">
</div><div class="field">
<label>WhatsApp</label><input type="text" id="s_restaurant_whatsapp">
</div><div class="field">
<label>Horarios</label><input type="text" id="s_restaurant_hours">
</div></section>
<section class="settings-card"><h2>Pedidos y entrega</h2><div class="field">
<label>Delivery activado</label>
<select id="s_delivery_enabled"><option value="0">No</option><option value="1">Sí</option></select>
</div><div class="field">
<label>Costo de delivery</label><input type="number" id="s_delivery_fee" step="0.01" min="0">
</div><div class="field">
<label>Pedidos abiertos</label>
<select id="s_orders_open"><option value="1">Sí</option><option value="0">No</option></select>
</div><div class="field">
<label>Modo demostración</label>
<select id="s_demo_mode"><option value="1">Sí</option><option value="0">No</option></select>
</div></section>
<section class="settings-card"><h2>Pagos</h2><div class="field">
<label>Mercado Pago activado</label>
<select id="s_mercadopago_enabled"><option value="0">No</option><option value="1">Sí</option></select>
</div><div class="field">
<label>Mercado Pago Access Token</label><input type="text" id="s_mercadopago_access_token">
</div></section>
</div>
<button class="btn settings-save" onclick="saveSettings()">Guardar configuración</button>
</div>
<?php endif; ?>

<dialog class="admin-dialog" id="orderDialog" aria-labelledby="orderDialogTitle">
<div class="order-sheet">
<header><h2 id="orderDialogTitle">Detalle del pedido</h2><button type="button" class="btn btn-ghost" id="orderDialogClose" aria-label="Cerrar detalle">×</button></header>
<div class="order-info" id="orderDetails"></div>
<form class="status-editor" id="orderStatusForm" hidden>
<label for="orderNextStatus">Cambiar estado</label>
<select id="orderNextStatus" required></select>
<div class="dialog-actions"><button type="button" class="btn btn-ghost" id="orderDialogCancel">Cerrar</button><button type="submit" class="btn">Guardar estado</button></div>
</form>
<div class="dialog-actions" id="orderCloseActions"><button type="button" class="btn btn-ghost" id="orderDialogDone">Cerrar</button></div>
</div>
</dialog>

<div class="toast" id="toast"></div>

<script>
const CSRF = '<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>';
const ROLE = '<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>';
const API = '/admin/api/index.php';

document.querySelectorAll('.tab').forEach(t => t.addEventListener('click', () => {
    document.querySelectorAll('.tab').forEach(x => x.classList.remove('active'));
    document.querySelectorAll('.section').forEach(x => x.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(x => x.removeAttribute('aria-current'));
    t.classList.add('active');
    t.setAttribute('aria-current', 'page');
    document.getElementById('section-' + t.dataset.tab).classList.add('active');
    if (t.dataset.tab === 'analytics') loadAnalytics();
    if (t.dataset.tab === 'products') { loadProducts(); loadGlobalAddOns(); }
    if (t.dataset.tab === 'settings') loadSettings();
}));

function toast(msg, err = false) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = 'toast' + (err ? ' err' : '');
    el.style.display = 'block';
    setTimeout(() => el.style.display = 'none', 2500);
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[char]));
}

function parseKitchenNotes(value) {
    const result = { removals: [], additions: [], notes: [] };
    for (const line of String(value || '').split(/\r?\n/).map(part => part.trim()).filter(Boolean)) {
        if (/^Quitar:\s*/i.test(line)) result.removals.push(line.replace(/^Quitar:\s*/i, ''));
        else if (/^(Agregar|Sumar):\s*/i.test(line)) result.additions.push(line.replace(/^(Agregar|Sumar):\s*/i, ''));
        else result.notes.push(line.replace(/^Indicaciones:\s*/i, ''));
    }
    return result;
}

function formatKitchenAddOns(addOns, burgerQuantity = 1) {
    return (addOns || []).map(extra => {
        const quantity = Math.max(1, Number(extra.quantity) || 1);
        const burgers = Math.max(1, Number(burgerQuantity) || 1);
        const perBurger = `${quantity} ${quantity === 1 ? 'porción' : 'porciones'}`;
        return `${extra.name} × ${perBurger}${burgers > 1 ? ` por hamburguesa (${quantity * burgers} en total)` : ''}`;
    });
}

async function api(action, options = {}) {
    const url = new URL(API, window.location.origin);
    url.searchParams.set('action', action);
    const searchParams = options.query || {};
    if (searchParams instanceof URLSearchParams) searchParams.forEach((value, key) => url.searchParams.set(key, value));
    else Object.entries(searchParams).forEach(([key, value]) => url.searchParams.set(key, String(value)));
    const { query, ...requestOptions } = options;
    const res = await fetch(url, {
        ...requestOptions,
        headers: {
            ...(requestOptions.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
            'X-CSRF-Token': CSRF,
            ...(requestOptions.headers || {}),
        },
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || 'Error');
    return data;
}

async function loadOrders() {
    const status = document.getElementById('filterStatus').value;
    const delivery = document.getElementById('filterDelivery').value;
    const params = new URLSearchParams();
    if (status) params.set('status', status);
    if (delivery) params.set('delivery_type', delivery);
    try {
        const data = await api('orders.list', { query: params });
        const summary = data.sales_summary || {};
        document.getElementById('salesTotal').textContent = '$ ' + Number(summary.total || 0).toLocaleString('es-AR', { maximumFractionDigits: 2 });
        document.getElementById('salesOrderCount').textContent = Number(summary.order_count || 0).toLocaleString('es-AR');
        renderOrders(data.orders || []);
    } catch (e) { toast(e.message, true); }
}

function renderOrders(orders) {
    const el = document.getElementById('ordersList');
    if (orders.length === 0) { el.innerHTML = '<div class="empty">No hay pedidos.</div>'; return; }
    const labels = { received:'Recibido', preparing:'En preparación', ready_for_pickup:'Listo para retirar', ready_for_delivery:'Listo para enviar', on_the_way:'En camino', delivered:'Entregado', cancelled:'Cancelado' };
    el.innerHTML = `<div class="orders-cards">${orders.map(o => {
        const customized = (o.items || []).some(item => item.is_customized);
        return `
        <article class="order-card${customized ? ' has-customization' : ''}">
            <div class="order-card-head"><h2>Pedido #${Number(o.id)}</h2><div>${customized ? '<span class="custom-order-badge">Personalizado</span> ' : ''}<span class="status ${escapeHtml(o.status)}">${escapeHtml(labels[o.status] || o.status)}</span></div></div>
            <div class="order-card-data">
                <div>Modalidad<strong>${o.delivery_type === 'delivery' ? 'Delivery' : 'Retiro'}</strong></div>
                <div>Total<strong>$ ${Number(o.total).toLocaleString('es-AR')}</strong></div>
                <div>Cliente<strong>${escapeHtml(o.customer_name || 'Sin nombre')}</strong></div>
                <div>Pago<strong>${escapeHtml(o.payment_method)} · ${escapeHtml(o.payment_status)}</strong></div>
                <div>Recibido<strong>${escapeHtml(o.created_at)}</strong></div>
            </div>
            <div class="order-kitchen${customized ? ' has-customization' : ''}">
                <strong>Para cocina</strong>
                <ul>${(o.items || []).map(item => {
                    const instructions = parseKitchenNotes(item.custom_notes);
                    const removals = [...(item.removed_ingredient_names || []), ...instructions.removals];
                    const additions = [...formatKitchenAddOns(item.added_ingredients, item.quantity), ...instructions.additions];
                    const removed = removals.length
                        ? `<small class="kitchen-removals">QUITAR: ${escapeHtml([...new Set(removals)].join(', '))}</small>` : '';
                    const added = additions.length
                        ? `<small class="kitchen-additions">AGREGAR: ${escapeHtml([...new Set(additions)].join(', '))}</small>` : '';
                    const notes = instructions.notes.length ? `<small class="kitchen-note">NOTA: ${escapeHtml(instructions.notes.join(' · '))}</small>` : '';
                    const customizedLabel = item.is_customized ? '<small class="kitchen-custom-label">Personalizada · revisar antes de preparar</small>' : '';
                    return `<li><span>${escapeHtml(item.product_name)} × ${Number(item.quantity)}</span>${customizedLabel}${removed}${added}${notes}</li>`;
                }).join('') || '<li>Sin productos asociados</li>'}</ul>
            </div>
            <button type="button" class="btn" data-order-id="${Number(o.id)}">Ver pedido</button>
        </article>`;
    }).join('')}</div>`;
    el.querySelectorAll('[data-order-id]').forEach(button => {
        button.addEventListener('click', () => viewOrder(Number(button.dataset.orderId)));
    });
}

function money(value) {
    return '$ ' + Number(value || 0).toLocaleString('es-AR', { maximumFractionDigits: 0 });
}

function localDateValue(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function chartPoints(timeline, from, to, bucket) {
    const values = new Map((timeline || []).map(item => [String(item.period).slice(0, 10), {
        total: Number(item.total) || 0,
        orderCount: Number(item.order_count) || 0,
    }]));
    const start = new Date(`${from}T12:00:00`);
    const end = new Date(`${to}T12:00:00`);
    if (bucket === 'month') start.setDate(1);
    const points = [];
    for (const current = new Date(start); current <= end; bucket === 'day' ? current.setDate(current.getDate() + 1) : current.setMonth(current.getMonth() + 1)) {
        const key = bucket === 'day' ? localDateValue(current) : `${current.getFullYear()}-${String(current.getMonth() + 1).padStart(2, '0')}-01`;
        const value = values.get(key) || { total: 0, orderCount: 0 };
        points.push({ key, date: new Date(current), total: value.total, orderCount: value.orderCount });
    }
    return points;
}

function renderSalesChart(timeline, filters, bucket) {
    const host = document.getElementById('analyticsChart');
    const points = chartPoints(timeline, filters.from, filters.to, bucket);
    const hasSales = points.some(point => point.total > 0);
    document.getElementById('analyticsChartCaption').textContent = bucket === 'day'
        ? 'Importe total por día'
        : 'Importe total por mes';
    if (!hasSales) {
        host.innerHTML = '<div class="analytics-empty">No hay ventas registradas en este período.</div>';
        return;
    }

    const width = 760, height = 260;
    const left = 66, right = 16, top = 16, bottom = 42;
    const plotWidth = width - left - right, plotHeight = height - top - bottom;
    const maxValue = Math.max(...points.map(point => point.total));
    const step = maxValue > 0 ? maxValue / 4 : 1;
    const ceiling = step * 4 || 1;
    const x = index => points.length === 1 ? left + plotWidth / 2 : left + (index / (points.length - 1)) * plotWidth;
    const y = value => top + plotHeight - (value / ceiling) * plotHeight;
    const linePath = points.map((point, index) => `${index === 0 ? 'M' : 'L'} ${x(index).toFixed(1)} ${y(point.total).toFixed(1)}`).join(' ');
    const areaPath = `${linePath} L ${x(points.length - 1).toFixed(1)} ${(top + plotHeight).toFixed(1)} L ${x(0).toFixed(1)} ${(top + plotHeight).toFixed(1)} Z`;
    const grid = Array.from({ length: 5 }, (_, index) => {
        const value = ceiling - step * index;
        const position = top + (plotHeight / 4) * index;
        const label = '$' + value.toLocaleString('es-AR', { notation: 'compact', maximumFractionDigits: 1 });
        return `<g><line class="grid-line" x1="${left}" y1="${position}" x2="${width - right}" y2="${position}"/><text class="axis-label" x="${left - 10}" y="${position + 4}" text-anchor="end">${label}</text></g>`;
    }).join('');
    const labelIndexes = [...new Set([0, Math.floor((points.length - 1) / 3), Math.floor((points.length - 1) * 2 / 3), points.length - 1])];
    const labels = labelIndexes.map(index => {
        const point = points[index];
        const label = bucket === 'day'
            ? point.date.toLocaleDateString('es-AR', { day: '2-digit', month: 'short' }).replace('.', '')
            : point.date.toLocaleDateString('es-AR', { month: 'short', year: '2-digit' }).replace('.', '');
        return `<text class="axis-label" x="${x(index)}" y="${height - 12}" text-anchor="middle">${escapeHtml(label)}</text>`;
    }).join('');
    const circles = points.map((point, index) => {
        const dateLabel = point.date.toLocaleDateString('es-AR', { dateStyle: 'medium' });
        return `<circle class="chart-point" cx="${x(index)}" cy="${y(point.total)}" r="4"><title>${escapeHtml(dateLabel)}: ${escapeHtml(money(point.total))} · ${point.orderCount} pedidos</title></circle>`;
    }).join('');
    host.innerHTML = `<svg class="analytics-chart" viewBox="0 0 ${width} ${height}" role="img" aria-label="Gráfico de evolución de ventas">
        <defs><linearGradient id="salesArea" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#f06a37" stop-opacity=".3"/><stop offset="100%" stop-color="#f06a37" stop-opacity="0"/></linearGradient></defs>
        ${grid}<path class="chart-area" d="${areaPath}"/><path class="chart-line" d="${linePath}"/>${circles}${labels}</svg>`;
}

function renderProductRanking(products) {
    const list = document.getElementById('analyticsRanking');
    if (!products.length) {
        list.innerHTML = '<li class="analytics-empty">Todavía no hay hamburguesas vendidas en este período.</li>';
        return;
    }
    const maxUnits = Math.max(...products.map(product => Number(product.units) || 0), 1);
    list.innerHTML = products.map((product, index) => {
        const units = Number(product.units) || 0;
        const width = Math.max(4, units / maxUnits * 100);
        return `<li class="ranking-item">
            <div class="ranking-head"><span class="ranking-name">${index + 1}. ${escapeHtml(product.product_name)}</span><span class="ranking-units">${units} ${units === 1 ? 'unidad' : 'unidades'}</span></div>
            <div class="ranking-track" aria-hidden="true"><div class="ranking-bar" style="width:${width.toFixed(1)}%"></div></div>
            <small class="ranking-foot">${escapeHtml(money(product.revenue))} en productos</small>
        </li>`;
    }).join('');
}

async function loadAnalytics() {
    const range = document.getElementById('analyticsRange').value;
    const button = document.getElementById('analyticsApply');
    const status = document.getElementById('analyticsStatus');
    const content = document.getElementById('analyticsContent');
    const params = new URLSearchParams({
        range,
        delivery_type: document.getElementById('analyticsDelivery').value,
        today: localDateValue(new Date()),
    });
    if (range === 'custom') {
        const from = document.getElementById('analyticsFrom').value;
        const to = document.getElementById('analyticsTo').value;
        if (!from || !to) {
            status.textContent = 'Elegí las dos fechas para consultar el período.';
            status.style.color = '#ff9b8e';
            content.hidden = true;
            return;
        }
        params.set('from', from);
        params.set('to', to);
    }
    button.disabled = true;
    button.textContent = 'Cargando…';
    status.style.color = '';
    status.textContent = 'Actualizando estadísticas…';
    content.hidden = true;
    try {
        const data = await api('analytics.get', { query: params });
        const summary = data.summary || {};
        document.getElementById('analyticsSales').textContent = money(summary.total);
        document.getElementById('analyticsOrders').textContent = Number(summary.order_count || 0).toLocaleString('es-AR');
        document.getElementById('analyticsAverage').textContent = money(summary.average_ticket);
        document.getElementById('analyticsTopProduct').textContent = summary.top_product?.product_name || '—';
        document.getElementById('analyticsTopUnits').textContent = summary.top_product
            ? `${Number(summary.top_product.units).toLocaleString('es-AR')} ${Number(summary.top_product.units) === 1 ? 'unidad vendida' : 'unidades vendidas'}`
            : 'Sin unidades vendidas';
        renderSalesChart(data.timeline || [], data.filters, data.bucket);
        renderProductRanking(data.products || []);
        status.textContent = `Período: ${new Date(`${data.filters.from}T12:00:00`).toLocaleDateString('es-AR')} al ${new Date(`${data.filters.to}T12:00:00`).toLocaleDateString('es-AR')}`;
        content.hidden = false;
    } catch (error) {
        status.textContent = error.message || 'No se pudieron cargar las estadísticas.';
        status.style.color = '#ff9b8e';
        toast(status.textContent, true);
    } finally {
        button.disabled = false;
        button.textContent = 'Aplicar filtros';
    }
}

document.getElementById('analyticsRange')?.addEventListener('change', event => {
    const custom = event.target.value === 'custom';
    const fields = document.getElementById('analyticsDateFields');
    fields.hidden = !custom;
    if (custom && !document.getElementById('analyticsFrom').value) {
        const today = new Date();
        document.getElementById('analyticsTo').value = localDateValue(today);
        today.setDate(today.getDate() - 29);
        document.getElementById('analyticsFrom').value = localDateValue(today);
    }
});
document.getElementById('analyticsApply')?.addEventListener('click', loadAnalytics);

async function viewOrder(id) {
    try {
        const data = await api('orders.get', { query: { id } });
        const o = data.order;
        const labels = { received:'Recibido', preparing:'En preparación', ready_for_pickup:'Listo para retirar', ready_for_delivery:'Listo para enviar', on_the_way:'En camino', delivered:'Entregado', cancelled:'Cancelado' };
        const next = {
            received: ['preparing', 'cancelled'],
            preparing: o.delivery_type === 'delivery' ? ['ready_for_delivery','cancelled'] : ['ready_for_pickup','cancelled'],
            ready_for_pickup: ['delivered','cancelled'],
            ready_for_delivery: ['on_the_way','cancelled'],
            on_the_way: ['delivered'],
            delivered: [], cancelled: [],
        }[o.status] || [];
        document.getElementById('orderDialogTitle').textContent = `Pedido #${o.id}`;
        const itemLines = (o.items || []).map(item => {
            const instructions = parseKitchenNotes(item.custom_notes);
            const removals = [...(item.removed_ingredient_names || []), ...instructions.removals];
            const additions = [...formatKitchenAddOns(item.added_ingredients, item.quantity), ...instructions.additions];
            const without = removals.length
                ? `<small class="kitchen-removals"><strong>QUITAR:</strong> ${escapeHtml([...new Set(removals)].join(', '))}</small>` : '';
            const withExtras = additions.length
                ? `<small class="kitchen-additions"><strong>AGREGAR:</strong> ${escapeHtml([...new Set(additions)].join(', '))}</small>` : '';
            const notes = instructions.notes.length
                ? `<small class="kitchen-note"><strong>INDICACIONES:</strong> ${escapeHtml(instructions.notes.join(' · '))}</small>` : '';
            const customizedLabel = item.is_customized ? '<small class="kitchen-custom-label">PERSONALIZADA · revisar antes de preparar</small>' : '';
            return `<li><strong>${escapeHtml(item.product_name)} × ${Number(item.quantity)}</strong><span>$ ${Number(item.unit_price).toLocaleString('es-AR')}</span>${customizedLabel}${without}${withExtras}${notes}</li>`;
        }).join('');
        const deliveryAddress = o.delivery_type === 'delivery'
            ? `<p><strong>Dirección:</strong> ${escapeHtml([o.delivery_street, o.delivery_number, o.delivery_locality, o.delivery_apartment, o.delivery_floor].filter(Boolean).join(', '))}</p>`
            : '';
        document.getElementById('orderDetails').innerHTML = `
            <p><strong>Estado:</strong> <span class="status ${escapeHtml(o.status)}">${escapeHtml(labels[o.status] || o.status)}</span></p>
            <p><strong>Modalidad:</strong> ${o.delivery_type === 'delivery' ? 'Delivery' : 'Retiro en local'}</p>
            <p><strong>Pago:</strong> ${escapeHtml(o.payment_method)} · ${escapeHtml(o.payment_status)}</p>
            <p><strong>Total:</strong> $ ${Number(o.total).toLocaleString('es-AR')}</p>
            <p><strong>Cliente:</strong> ${escapeHtml(o.customer_name || 'Sin nombre')}${o.customer_phone ? ` · ${escapeHtml(o.customer_phone)}` : ''}</p>
            ${deliveryAddress}
            ${o.delivery_notes ? `<p><strong>Referencias:</strong> ${escapeHtml(o.delivery_notes)}</p>` : ''}
            <h3>Productos</h3><ul>${itemLines || '<li>Sin productos asociados</li>'}</ul>`;

        const statusForm = document.getElementById('orderStatusForm');
        const closeActions = document.getElementById('orderCloseActions');
        const select = document.getElementById('orderNextStatus');
        select.innerHTML = next.map(status => `<option value="${status}">${escapeHtml(labels[status] || status)}</option>`).join('');
        statusForm.hidden = next.length === 0;
        closeActions.hidden = next.length > 0;
        statusForm.dataset.orderId = String(o.id);
        document.getElementById('orderDialog').showModal();
    } catch (e) { toast(e.message, true); }
}

async function loadProducts() {
    try {
        const data = await api('products.list');
        const el = document.getElementById('productsList');
        if (!data.products?.length) { el.innerHTML = '<div class="empty">No hay productos cargados.</div>'; return; }
        el.innerHTML = `<div class="product-editors">${data.products.map(p => `
            <form class="product-editor" data-product-id="${Number(p.id)}" enctype="multipart/form-data">
                <header><h2>${escapeHtml(p.name)}</h2><span class="status ${p.is_available ? 'preparing' : 'cancelled'}">${p.is_available ? 'Disponible' : 'Pausado'}</span></header>
                <div class="field"><label>Nombre del producto</label><input name="name" value="${escapeHtml(p.name)}" maxlength="150" required></div>
                <div class="field"><label>Descripción del menú</label><textarea name="description" maxlength="2000">${escapeHtml(p.description || '')}</textarea></div>
                <div class="field"><label>Ingredientes incluidos (se pueden quitar)</label><input name="ingredients" value="${escapeHtml((p.ingredients || []).join(', '))}" maxlength="2000" placeholder="Ej.: cebolla, aderezo, pepinillos"><small>Cargá solo lo que trae esta burger por defecto, separado por comas. En Personalizar aparecerá cada uno para quitarlo.</small></div>
                <div class="field"><label>Precio</label><input name="price" type="number" min="0.01" step="0.01" value="${escapeHtml(p.price)}" required inputmode="decimal"></div>
                <div class="field"><div class="product-image-editor"><img class="product-image-preview" src="${escapeHtml(p.image_url || '/assets/img/burger-demo.webp')}" alt="Foto actual de ${escapeHtml(p.name)}"><div class="product-image-controls"><label for="product-image-${Number(p.id)}">Foto del producto</label><input id="product-image-${Number(p.id)}" name="image_file" type="file" accept="image/jpeg,image/png,image/webp"><input name="image_url" type="hidden" value="${escapeHtml(p.image_url || '')}"><small>Elegí una nueva foto para reemplazarla. JPG, PNG o WebP; hasta 5 MB (según el límite del hosting).</small></div></div></div>
                <label class="availability"><input name="is_available" type="checkbox" ${p.is_available ? 'checked' : ''}> Disponible para pedidos</label>
                <button type="submit" class="btn save-product">Guardar cambios</button>
            </form>`).join('')}</div>`;
        el.querySelectorAll('.product-editor').forEach(form => {
            form.addEventListener('submit', event => { event.preventDefault(); updateProduct(form); });
            const imageInput = form.elements.image_file;
            const preview = form.querySelector('.product-image-preview');
            let previewUrl = null;
            imageInput.addEventListener('change', () => {
                if (previewUrl) URL.revokeObjectURL(previewUrl);
                const file = imageInput.files?.[0];
                if (!file) return;
                previewUrl = URL.createObjectURL(file);
                preview.src = previewUrl;
                preview.alt = `Vista previa de ${form.elements.name.value.trim() || 'la nueva foto'}`;
            });
        });
    } catch (e) { toast(e.message, true); }
}

async function updateProduct(form) {
    try {
        const button = form.querySelector('[type="submit"]');
        button.disabled = true;
        button.textContent = form.elements.image_file.files?.length ? 'Subiendo foto y guardando…' : 'Guardando…';
        const payload = new FormData();
        payload.set('id', String(Number(form.dataset.productId)));
        payload.set('name', form.elements.name.value.trim());
        payload.set('description', form.elements.description.value.trim());
        payload.set('ingredients', form.elements.ingredients.value.trim());
        payload.set('price', form.elements.price.value);
        payload.set('image_url', form.elements.image_url.value.trim());
        payload.set('is_available', form.elements.is_available.checked ? '1' : '0');
        const imageFile = form.elements.image_file.files?.[0];
        if (imageFile) payload.set('image_file', imageFile);
        await api('products.update', { method: 'POST', body: payload });
        toast('Cambios guardados');
        await loadProducts();
    } catch (e) { toast(e.message, true); }
    finally {
        const button = form.querySelector('[type="submit"]');
        if (button?.isConnected) { button.disabled = false; button.textContent = 'Guardar cambios'; }
    }
}

async function loadGlobalAddOns() {
    const list = document.getElementById('globalAddOnsList');
    const status = document.getElementById('globalAddOnsStatus');
    try {
        const data = await api('addons.list');
        list.innerHTML = (data.add_ons || []).map(addOn => `
            <div class="global-addon-row" data-addon-key="${escapeHtml(addOn.key)}">
                <div class="global-addon-name">${escapeHtml(addOn.name)}<small>Importe de cada porción</small></div>
                <div class="global-addon-price"><label for="addon-price-${escapeHtml(addOn.key)}">Precio por porción ($)</label><input id="addon-price-${escapeHtml(addOn.key)}" data-addon-price type="number" min="0" max="99999999.99" step="0.01" value="${Number(addOn.price || 0).toFixed(2)}" required inputmode="decimal"></div>
                <label class="global-addon-availability"><input data-addon-available type="checkbox" ${addOn.available ? 'checked' : ''}> Disponible para pedidos</label>
            </div>`).join('') || '<div class="empty">No hay extras configurados.</div>';
        status.textContent = '';
    } catch (e) {
        list.innerHTML = '<div class="empty">No se pudieron cargar los extras. Volvé a intentar.</div>';
        status.textContent = e.message;
        toast(e.message, true);
    }
}

async function saveGlobalAddOns(event) {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form.reportValidity()) return;
    const button = document.getElementById('globalAddOnsSave');
    const status = document.getElementById('globalAddOnsStatus');
    const addOns = [...form.querySelectorAll('.global-addon-row')].map(row => ({
        key: row.dataset.addonKey,
        price: row.querySelector('[data-addon-price]').value,
        available: row.querySelector('[data-addon-available]').checked,
    }));
    button.disabled = true;
    button.textContent = 'Guardando…';
    status.textContent = '';
    try {
        const data = await api('addons.save', { method: 'POST', body: JSON.stringify({ add_ons: addOns }) });
        toast('Precios y disponibilidad actualizados');
        status.textContent = 'La personalización del menú ya usa estos precios para todas las hamburguesas.';
        if (data.add_ons) {
            document.querySelectorAll('.global-addon-row').forEach((row, index) => {
                const saved = data.add_ons[index];
                if (!saved) return;
                row.querySelector('[data-addon-price]').value = Number(saved.price || 0).toFixed(2);
                row.querySelector('[data-addon-available]').checked = Boolean(saved.available);
            });
        }
    } catch (e) {
        status.textContent = e.message;
        toast(e.message, true);
    } finally {
        button.disabled = false;
        button.textContent = 'Guardar precios de extras';
    }
}

async function loadSettings() {
    try {
        const data = await api('settings.get');
        for (const [k, v] of Object.entries(data.settings || {})) {
            const el = document.getElementById('s_' + k);
            if (el) el.value = v;
        }
    } catch (e) { toast(e.message, true); }
}

function closeOrderDialog() {
    document.getElementById('orderDialog').close();
}
document.getElementById('orderDialogClose').addEventListener('click', closeOrderDialog);
document.getElementById('orderDialogCancel').addEventListener('click', closeOrderDialog);
document.getElementById('orderDialogDone').addEventListener('click', closeOrderDialog);
const globalAddOnsForm = document.getElementById('globalAddOnsForm');
if (globalAddOnsForm) globalAddOnsForm.addEventListener('submit', saveGlobalAddOns);
document.getElementById('orderStatusForm').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const button = form.querySelector('[type="submit"]');
    button.disabled = true;
    button.textContent = 'Guardando…';
    try {
        const result = await api('orders.update-status', {
            method: 'POST',
            body: JSON.stringify({ id: Number(form.dataset.orderId), status: document.getElementById('orderNextStatus').value }),
        });
        if (!result.ok) throw new Error('No se pudo actualizar el estado.');
        closeOrderDialog();
        toast('Estado actualizado');
        await loadOrders();
    } catch (e) {
        toast(e.message, true);
    } finally {
        button.disabled = false;
        button.textContent = 'Guardar estado';
    }
});

async function saveSettings() {
    const keys = ['restaurant_name','restaurant_address','restaurant_whatsapp','restaurant_hours','delivery_enabled','delivery_fee','orders_open','demo_mode','mercadopago_enabled','mercadopago_access_token'];
    const payload = {};
    keys.forEach(k => { const el = document.getElementById('s_' + k); if (el) payload[k] = el.value; });
    try {
        await api('settings.save', { method: 'POST', body: JSON.stringify(payload) });
        toast('Configuración guardada');
    } catch (e) { toast(e.message, true); }
}

loadOrders();
if (ROLE === 'owner') loadSettings();
</script>
</body></html>

