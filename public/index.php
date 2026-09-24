<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
if (!\Exodo\Config::isConfigured()) {
    http_response_code(503);
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="icon" type="image/png" href="/assets/img/logo-exodo-transparent.png"><title>ÉXODO · Instalación pendiente</title><style>body{font-family:system-ui,sans-serif;background:#1c1c1c;color:#f5f0e8;display:grid;place-items:center;min-height:100vh;margin:0;padding:1.5rem;text-align:center}main{max-width:34rem;background:#262626;border-top:4px solid #e8501a;border-radius:10px;padding:2rem}a{color:#f4a17f}</style></head><body><main><h1>ÉXODO todavía no está instalado</h1><p>Completá la configuración de MySQL y el usuario inicial para poner en marcha el sitio.</p><p><a href="/install/">Abrir instalador</a></p></main></body></html><?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="/assets/img/logo-exodo-transparent.png">
    <title>ÉXODO — Smash Burgers</title>
    <meta name="description" content="ÉXODO — Smash burgers artesanales. Pedí para retirar o recibir.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;900&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css?v=20260924-2">
    <meta name="csrf-token" content="<?= htmlspecialchars(\Exodo\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <a href="#main" class="skip-link">Saltar al contenido</a>

    <!-- Header -->
    <header class="site-header" role="banner">
        <div class="header-inner">
            <a href="/" class="logo" aria-label="ÉXODO Burgers inicio"><img src="/assets/img/logo-exodo-transparent.png" alt="ÉXODO Burgers" width="72" height="72"></a>
            <div class="header-actions">
                <button type="button" class="btn-cart" id="cartToggle" aria-label="Abrir carrito" aria-expanded="false">
                    Carrito <span class="cart-count" id="cartCount">0</span>
                </button>
            </div>
        </div>
    </header>

    <main id="main">
        <!-- Hero -->
        <section class="hero" aria-labelledby="hero-title">
            <div class="hero-content">
                <h1 id="hero-title">Smash. Fuego. ÉXODO.</h1>
                <p class="hero-subtitle">Elegí tu burger, personalizá el pedido y mandalo directo al local.</p>
                <p class="hero-status" id="heroStatus" role="status"></p>
            </div>
        </section>

        <!-- Banner promocional -->
        <section class="promo-banner" id="promoBanner" hidden aria-labelledby="promo-title">
            <div class="promo-banner-content">
                <span class="promo-badge">PROMO</span>
                <h2 id="promo-title" class="promo-title"></h2>
                <p class="promo-description"></p>
                <p class="promo-price" id="promoPrice"></p>
                <button type="button" class="btn btn-primary" id="promoButton">Personalizar promo</button>
            </div>
        </section>

        <!-- Menú -->
        <section class="menu-section" aria-labelledby="menu-title">
            <h2 id="menu-title" class="section-title">Menú</h2>
            <div class="category-filters" id="categoryFilters" role="tablist"></div>
            <div class="menu-grid" id="menuGrid" aria-live="polite"></div>
        </section>
    </main>

    <!-- Carrito lateral -->
    <aside class="cart-panel is-empty" id="cartPanel" aria-labelledby="cartTitle" hidden>
        <div class="cart-header">
            <h2 id="cartTitle">Tu pedido</h2>
            <button type="button" class="btn-close" id="cartClose" aria-label="Cerrar carrito">×</button>
        </div>
        <div class="cart-items" id="cartItems"></div>
        <div class="cart-footer">
            <div class="cart-totals" id="cartTotals"></div>
            <div class="delivery-toggle">
                <label><input type="radio" name="deliveryType" value="pickup" checked> Retiro en local (sin costo)</label>
                <label><input type="radio" name="deliveryType" value="delivery"> Delivery</label>
            </div>
            <div id="deliveryForm" hidden>
                <label for="customerName">Nombre</label>
                <input type="text" id="customerName" name="customer_name" required autocomplete="name">
                <label for="customerPhone">Teléfono</label>
                <input type="tel" id="customerPhone" name="customer_phone" required autocomplete="tel">
                <label for="deliveryStreet">Calle</label>
                <input type="text" id="deliveryStreet" name="delivery_street" required autocomplete="street-address">
                <label for="deliveryNumber">Número</label>
                <input type="text" id="deliveryNumber" name="delivery_number" required>
                <label for="deliveryLocality">Localidad</label>
                <input type="text" id="deliveryLocality" name="delivery_locality" required>
                <label for="deliveryApartment">Departamento (opcional)</label>
                <input type="text" id="deliveryApartment" name="delivery_apartment">
                <label for="deliveryFloor">Piso (opcional)</label>
                <input type="text" id="deliveryFloor" name="delivery_floor">
                <label for="deliveryRing">Timbre (opcional)</label>
                <input type="text" id="deliveryRing" name="delivery_ring">
                <label for="deliveryNotes">Referencias (opcional)</label>
                <textarea id="deliveryNotes" name="delivery_notes"></textarea>
            </div>
            <button type="button" class="btn btn-primary btn-block" id="checkoutButton">Confirmar pedido</button>
            <p class="cart-issues" id="cartIssues" role="alert" hidden></p>
        </div>
    </aside>

    <!-- Modal de personalización -->
    <dialog class="modal customize-modal" id="customizeModal" aria-labelledby="customizeTitle">
        <div class="modal-content">
            <div class="customize-heading">
                <div>
                    <p class="customize-eyebrow">Armá tu pedido</p>
                    <h2 id="customizeTitle"></h2>
                </div>
                <button type="button" class="modal-close" id="customizeClose" aria-label="Cerrar personalización">×</button>
            </div>
            <p class="customize-intro" id="customizeIntro"></p>
            <form id="customizeForm">
                <section class="customize-options" id="customizeOptions" aria-labelledby="customizeOptionsTitle" hidden>
                    <div class="customize-section-heading">
                        <h3 id="customizeOptionsTitle">¿Qué querés sacar?</h3>
                        <p>Desmarcá lo que querés quitar; se marcará en rojo.</p>
                    </div>
                    <div id="customizeIngredients"></div>
                    <p class="customize-summary" id="customizeSummary" role="status" aria-live="polite"></p>
                </section>
                <section class="customize-addons" id="customizeAddOns" aria-labelledby="customizeAddOnsTitle" hidden>
                    <div class="customize-section-heading">
                        <h3 id="customizeAddOnsTitle">¿Querés sumar extras?</h3>
                        <p>Usá − y + para elegir porciones. El extra se marca en verde y el precio se calcula por porción.</p>
                    </div>
                    <div id="customizeAddOnsList"></div>
                    <p class="customize-summary customize-addons-summary" id="customizeAddOnsSummary" role="status" aria-live="polite">No se agregarán extras.</p>
                </section>
                <div class="customize-empty" id="customizeEmpty" hidden>
                    <div><strong>Receta base pendiente</strong><p>Podés escribir abajo qué querés quitar; cocina verá la indicación.</p></div>
                </div>
                <div class="customize-removals">
                    <label for="customizeRemoveText"><span id="customizeRemoveLabel">¿Qué ingredientes querés quitar?</span><span>Opcional</span></label>
                    <input id="customizeRemoveText" name="removals" type="text" maxlength="250" placeholder="Ej.: cebolla, aderezo" autocomplete="off">
                    <small id="customizeRemoveHelp">Escribilos separados por coma; aparecerán destacados para cocina.</small>
                </div>
                <div class="customize-notes">
                    <label for="customizeNotes">Indicaciones para cocina <span>Opcional</span></label>
                    <textarea id="customizeNotes" name="notes" rows="3" maxlength="700" placeholder="Ej.: bien cocida, salsa aparte…"></textarea>
                    <small class="notes-count" id="customizeNotesCount">0 / 700</small>
                </div>
                <p class="customize-price-summary" id="customizePriceSummary" role="status" aria-live="polite"></p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" id="customizeCancel">Volver</button>
                    <button type="submit" class="btn btn-primary" id="customizeSubmit">Agregar al pedido</button>
                </div>
            </form>
        </div>
    </dialog>

    <!-- Seguimiento -->
    <dialog class="modal tracking-modal" id="trackingModal" aria-labelledby="trackingTitle">
        <div class="modal-content">
            <header class="tracking-header">
                <div>
                    <h2 id="trackingTitle">Seguimiento del pedido</h2>
                    <p id="trackingOrderRef" class="tracking-order-ref" hidden></p>
                </div>
            </header>
            <div id="trackingContent" class="tracking-content" aria-live="polite"></div>
            <button type="button" class="btn btn-secondary tracking-close" id="trackingClose">Cerrar</button>
        </div>
    </dialog>

    <footer class="site-footer">
        <p>ÉXODO — Smash Burgers. <span id="footerDemo"></span></p>
    </footer>

    <script src="/assets/js/app.js?v=20260923-8" defer></script>
</body>
</html>

