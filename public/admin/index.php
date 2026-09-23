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
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Login — ÉXODO Admin</title>
<style>
body{font-family:system-ui,sans-serif;background:#1C1C1C;color:#F5F0E8;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0}
.card{background:#262626;padding:2.5rem;border-radius:8px;border-top:4px solid #E8501A;width:340px}
h1{font-size:1.6rem;margin:0 0 1.5rem;letter-spacing:2px}
label{display:block;margin:.8rem 0 .3rem;font-size:.9rem}
input{width:100%;padding:.7rem;border-radius:4px;border:1px solid #444;background:#1a1a1a;color:#F5F0E8;font-size:1rem;box-sizing:border-box}
button{background:#E8501A;color:#fff;border:none;padding:.9rem;border-radius:4px;font-weight:700;width:100%;margin-top:1.5rem;cursor:pointer;font-size:1rem}
button:hover{background:#c94415}
.err{background:#4a1a1a;padding:.7rem;border-radius:4px;margin-bottom:1rem;color:#ffaaaa;font-size:.9rem}
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
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Panel — ÉXODO</title>
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
.order-card-head{display:flex;justify-content:space-between;align-items:center;gap:.75rem;padding-bottom:.7rem;border-bottom:1px solid var(--line)}
.order-card h2{font-size:1.25rem}.order-card-data{display:grid;grid-template-columns:1fr 1fr;gap:.65rem .9rem;padding:.85rem 0;color:var(--muted);font-size:.86rem}
.order-card-data strong{display:block;color:var(--text);font-size:.95rem;overflow-wrap:anywhere}
.order-card .btn{width:100%}
.order-kitchen{margin:0 0 .85rem;padding:.75rem .8rem;border:1px solid var(--line);border-left:3px solid var(--accent);border-radius:8px;background:#1b1b1b}
.order-kitchen>strong{display:block;margin-bottom:.45rem;color:#f2b49c;font-size:.72rem;letter-spacing:.08em;text-transform:uppercase}
.order-kitchen ul{list-style:none;display:grid;gap:.6rem}
.order-kitchen li{display:grid;gap:.18rem;min-width:0;font-size:.88rem;font-weight:700;overflow-wrap:anywhere}
.order-kitchen li small{font-size:.8rem;font-weight:500;line-height:1.4;white-space:pre-wrap}
.kitchen-removals{color:#ffb695}.kitchen-note{color:#d1cbc2}
.product-editors{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr));gap:.9rem}
.product-editor{display:grid;gap:.8rem}.product-editor>header{margin:0;padding:0 0 .65rem;border:0;border-bottom:1px solid var(--line)}.product-editor h2{font-size:1.4rem}
.product-editor small{color:var(--muted);line-height:1.4}
.product-editor input[type=checkbox]{width:22px;height:22px;min-height:22px;accent-color:var(--accent)}
.availability{display:flex;align-items:center;gap:.6rem;color:var(--text)!important;font-size:.95rem!important;min-height:44px}
.save-product{width:100%}.settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.9rem}
.settings-card{display:grid;gap:.85rem}.settings-card h2{font-size:1.35rem}.settings-card .field{min-width:0}
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
 .tab{padding:.6rem .35rem;font-size:.86rem;min-width:0}
 .filters{display:grid;grid-template-columns:1fr 1fr;gap:.65rem}.filters .field{min-width:0}.filters>.btn{grid-column:1/-1;width:100%}
 .order-table-wrap{display:none}.orders-cards{grid-template-columns:1fr;gap:.7rem}.order-card{padding:.85rem}.order-card-data{gap:.55rem .75rem;padding:.7rem 0}
 .product-editors{grid-template-columns:1fr}.settings-grid{grid-template-columns:1fr}.product-editor,.settings-card{padding:.85rem}
 .dialog-actions{grid-template-columns:1fr}.order-sheet{padding:.85rem}.order-sheet>header{top:-.85rem}
}
@media(max-width:360px){.user{display:none}.filters{grid-template-columns:1fr}.filters>.btn{grid-column:auto}.order-card-data{grid-template-columns:1fr 1fr}}
</style></head><body>

<header>
<h1><img class="admin-logo" src="/assets/img/logo-exodo.png" alt=""> Panel</h1>
<div>
<span class="user"><?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($role) ?>)</span>
<a href="/admin/logout.php" class="btn btn-ghost" style="margin-left:1rem">Salir</a>
</div>
</header>

<nav class="tabs" aria-label="Secciones del panel">
<button type="button" class="tab active" data-tab="orders" aria-current="page">Pedidos</button>
<?php if ($role === 'owner'): ?>
<button type="button" class="tab" data-tab="products">Productos</button>
<button type="button" class="tab" data-tab="settings">Configuración</button>
<?php endif; ?>
</nav>

<div class="section active" id="section-orders">
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
<div class="section" id="section-products">
<div class="section-heading"><h2>Menú y productos</h2><p>Actualizá la información que ve el cliente. Los cambios se guardan al tocar cada botón.</p></div>
<p class="section-note">Los precios actuales están marcados como muestra. Cargá los valores reales antes de recibir pedidos.</p>
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
    if (t.dataset.tab === 'products') loadProducts();
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
    const result = { removals: [], notes: [] };
    for (const line of String(value || '').split(/\r?\n/).map(part => part.trim()).filter(Boolean)) {
        if (/^Quitar:\s*/i.test(line)) result.removals.push(line.replace(/^Quitar:\s*/i, ''));
        else result.notes.push(line.replace(/^Indicaciones:\s*/i, ''));
    }
    return result;
}

async function api(action, options = {}) {
    const url = new URL(API, window.location.origin);
    url.searchParams.set('action', action);
    Object.entries(options.query || {}).forEach(([key, value]) => url.searchParams.set(key, String(value)));
    const { query, ...requestOptions } = options;
    const res = await fetch(url, {
        ...requestOptions,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF, ...(requestOptions.headers || {}) },
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
        renderOrders(data.orders || []);
    } catch (e) { toast(e.message, true); }
}

function renderOrders(orders) {
    const el = document.getElementById('ordersList');
    if (orders.length === 0) { el.innerHTML = '<div class="empty">No hay pedidos.</div>'; return; }
    const labels = { received:'Recibido', preparing:'En preparación', ready_for_pickup:'Listo para retirar', ready_for_delivery:'Listo para enviar', on_the_way:'En camino', delivered:'Entregado', cancelled:'Cancelado' };
    el.innerHTML = `<div class="orders-cards">${orders.map(o => `
        <article class="order-card">
            <div class="order-card-head"><h2>Pedido #${Number(o.id)}</h2><span class="status ${escapeHtml(o.status)}">${escapeHtml(labels[o.status] || o.status)}</span></div>
            <div class="order-card-data">
                <div>Modalidad<strong>${o.delivery_type === 'delivery' ? 'Delivery' : 'Retiro'}</strong></div>
                <div>Total<strong>$ ${Number(o.total).toLocaleString('es-AR')}</strong></div>
                <div>Cliente<strong>${escapeHtml(o.customer_name || 'Sin nombre')}</strong></div>
                <div>Pago<strong>${escapeHtml(o.payment_method)} · ${escapeHtml(o.payment_status)}</strong></div>
                <div>Recibido<strong>${escapeHtml(o.created_at)}</strong></div>
            </div>
            <div class="order-kitchen">
                <strong>Para cocina</strong>
                <ul>${(o.items || []).map(item => {
                    const instructions = parseKitchenNotes(item.custom_notes);
                    const removals = [...(item.removed_ingredient_names || []), ...instructions.removals];
                    const removed = removals.length
                        ? `<small class="kitchen-removals">QUITAR: ${escapeHtml([...new Set(removals)].join(', '))}</small>` : '';
                    const notes = instructions.notes.length ? `<small class="kitchen-note">NOTA: ${escapeHtml(instructions.notes.join(' · '))}</small>` : '';
                    return `<li><span>${escapeHtml(item.product_name)} × ${Number(item.quantity)}</span>${removed}${notes}</li>`;
                }).join('') || '<li>Sin productos asociados</li>'}</ul>
            </div>
            <button type="button" class="btn" data-order-id="${Number(o.id)}">Ver pedido</button>
        </article>`).join('')}</div>`;
    el.querySelectorAll('[data-order-id]').forEach(button => {
        button.addEventListener('click', () => viewOrder(Number(button.dataset.orderId)));
    });
}

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
            const without = removals.length
                ? `<small class="kitchen-removals"><strong>QUITAR:</strong> ${escapeHtml([...new Set(removals)].join(', '))}</small>` : '';
            const notes = instructions.notes.length
                ? `<small class="kitchen-note"><strong>INDICACIONES:</strong> ${escapeHtml(instructions.notes.join(' · '))}</small>` : '';
            return `<li><strong>${escapeHtml(item.product_name)} × ${Number(item.quantity)}</strong><span>$ ${Number(item.unit_price).toLocaleString('es-AR')}</span>${without}${notes}</li>`;
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
            <form class="product-editor" data-product-id="${Number(p.id)}">
                <header><h2>${escapeHtml(p.name)}</h2><span class="status ${p.is_available ? 'preparing' : 'cancelled'}">${p.is_available ? 'Disponible' : 'Pausado'}</span></header>
                <div class="field"><label>Nombre del producto</label><input name="name" value="${escapeHtml(p.name)}" maxlength="150" required></div>
                <div class="field"><label>Descripción del menú</label><textarea name="description" maxlength="2000">${escapeHtml(p.description || '')}</textarea></div>
                <div class="field"><label>Ingredientes incluidos (se pueden quitar)</label><input name="ingredients" value="${escapeHtml((p.ingredients || []).join(', '))}" maxlength="2000" placeholder="Ej.: cebolla, aderezo, pepinillos"><small>Cargá solo lo que trae esta burger por defecto, separado por comas. En Personalizar aparecerá cada uno para quitarlo.</small></div>
                <div class="field"><label>Precio</label><input name="price" type="number" min="0.01" step="0.01" value="${escapeHtml(p.price)}" required inputmode="decimal"></div>
                <div class="field"><label>Imagen (URL segura o ruta /assets/img/...)</label><input name="image_url" value="${escapeHtml(p.image_url || '')}" maxlength="500" placeholder="/assets/img/sinai.webp"><small>Podés reemplazar el archivo o pegar otra ruta de imagen.</small></div>
                <label class="availability"><input name="is_available" type="checkbox" ${p.is_available ? 'checked' : ''}> Disponible para pedidos</label>
                <button type="submit" class="btn save-product">Guardar cambios</button>
            </form>`).join('')}</div>`;
        el.querySelectorAll('.product-editor').forEach(form => {
            form.addEventListener('submit', event => { event.preventDefault(); updateProduct(form); });
        });
    } catch (e) { toast(e.message, true); }
}

async function updateProduct(form) {
    try {
        const button = form.querySelector('[type="submit"]');
        button.disabled = true;
        button.textContent = 'Guardando…';
        const payload = {
            id: Number(form.dataset.productId),
            name: form.elements.name.value.trim(),
            description: form.elements.description.value.trim(),
            ingredients: form.elements.ingredients.value.trim(),
            price: Number(form.elements.price.value),
            image_url: form.elements.image_url.value.trim(),
            is_available: form.elements.is_available.checked ? 1 : 0,
        };
        await api('products.update', { method: 'POST', body: JSON.stringify(payload) });
        toast('Cambios guardados');
        await loadProducts();
    } catch (e) { toast(e.message, true); }
    finally {
        const button = form.querySelector('[type="submit"]');
        if (button?.isConnected) { button.disabled = false; button.textContent = 'Guardar cambios'; }
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

