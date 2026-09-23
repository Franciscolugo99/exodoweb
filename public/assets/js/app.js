(function () {
    'use strict';

    const API = '/api/index.php';
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const CART_KEY = 'exodo_cart_v1';
    const DEFAULT_PRODUCT_IMAGE = '/assets/img/burger-demo.webp';

    let catalog = { categories: [], products: [], promo: null };
    let cart = loadCart();
    let currentProduct = null;
    let currentCartIndex = null;

    // ============================================================
    // Utilidades
    // ============================================================
    function loadCart() {
        try {
            return JSON.parse(localStorage.getItem(CART_KEY)) || [];
        } catch { return []; }
    }

    function saveCart() {
        localStorage.setItem(CART_KEY, JSON.stringify(cart));
    }

    function money(n) {
        return '$ ' + Number(n).toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    function makeIdempotencyKey() {
        if (globalThis.crypto && typeof globalThis.crypto.randomUUID === 'function') {
            return globalThis.crypto.randomUUID();
        }

        // crypto.randomUUID requiere un contexto seguro en algunos navegadores.
        // El fallback permite probar el sitio por HTTP dentro de la red local.
        return 'exodo-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2) + '-' + Math.random().toString(36).slice(2);
    }

    async function api(action, options = {}) {
        const url = new URL(API, window.location.origin);
        url.searchParams.set('action', action);
        if (options.query) {
            Object.entries(options.query).forEach(([key, value]) => {
                url.searchParams.set(key, String(value));
            });
        }
        const { query: _query, ...requestOptions } = options;
        const res = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF,
                ...(requestOptions.headers || {}),
            },
            ...requestOptions,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.error || 'Error de conexión');
        return data;
    }

    // ============================================================
    // Carga inicial
    // ============================================================
    async function init() {
        try {
            catalog = await api('catalog');
            syncDeliveryAvailability();
            renderCategories();
            renderProducts();
            renderPromoBanner();
            updateCartUI();
            setStatusText();
        } catch (e) {
            console.error(e);
            document.getElementById('heroStatus').textContent = 'No se pudo cargar el menú. Recargá la página.';
        }
    }

    function setStatusText() {
        const el = document.getElementById('heroStatus');
        if (!catalog.orders_open) {
            el.textContent = 'El local está cerrado. No se están recibiendo pedidos.';
            el.classList.add('status-closed');
        } else if (catalog.demo_mode) {
            el.textContent = 'Menú de muestra · nombres y precios editables desde el panel.';
            el.classList.remove('status-closed');
        } else {
            el.textContent = 'Recibiendo pedidos.';
            el.classList.remove('status-closed');
        }
    }

    // ============================================================
    // Render
    // ============================================================
    function renderCategories() {
        const container = document.getElementById('categoryFilters');
        container.innerHTML = '';
        catalog.categories.forEach((cat, i) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = cat.name;
            btn.className = 'category-btn' + (i === 0 ? ' active' : '');
            btn.dataset.categoryId = cat.id;
            btn.setAttribute('role', 'tab');
            btn.setAttribute('aria-selected', i === 0 ? 'true' : 'false');
            btn.addEventListener('click', () => filterCategory(cat.id, btn));
            container.appendChild(btn);
        });
    }

    function filterCategory(categoryId, btn) {
        document.querySelectorAll('.category-btn').forEach(b => {
            b.classList.remove('active');
            b.setAttribute('aria-selected', 'false');
        });
        btn.classList.add('active');
        btn.setAttribute('aria-selected', 'true');
        renderProducts(categoryId);
    }

    function renderProducts(categoryId = null) {
        const grid = document.getElementById('menuGrid');
        grid.innerHTML = '';
        const products = categoryId
            ? catalog.products.filter(p => p.category_id == categoryId)
            : catalog.products;

        if (products.length === 0) {
            grid.innerHTML = '<p class="empty-state">No hay productos en esta categoría.</p>';
            return;
        }

        products.forEach(product => {
            const card = document.createElement('article');
            card.className = 'menu-card';
            card.innerHTML = `
                <img src="${escapeHtml(product.image_url || DEFAULT_PRODUCT_IMAGE)}"
                     alt="${escapeHtml(product.name)}"
                     loading="lazy" width="400" height="300">
                <div class="menu-card-body">
                    <h3>${escapeHtml(product.name)}</h3>
                    <p class="ingredients">${escapeHtml(product.description || '')}</p>
                    <p class="price">${money(product.price)}</p>
                    ${catalog.demo_mode ? '<small class="demo-label">Precio de muestra</small>' : ''}
                    <button type="button" class="btn btn-primary btn-block add-to-cart" data-id="${product.id}">
                        ${product.is_promo ? 'Personalizar promo' : 'Agregar al carrito'}
                    </button>
                </div>
            `;
            const image = card.querySelector('img');
            image.addEventListener('error', () => {
                if (image.src !== new URL(DEFAULT_PRODUCT_IMAGE, window.location.origin).href) image.src = DEFAULT_PRODUCT_IMAGE;
            }, { once: true });
            card.querySelector('.add-to-cart').addEventListener('click', () => openCustomize(product));
            grid.appendChild(card);
        });
    }

    function renderPromoBanner() {
        const promo = catalog.promo;
        const banner = document.getElementById('promoBanner');
        if (!promo) {
            banner.hidden = true;
            return;
        }
        banner.hidden = false;
        document.querySelector('.promo-title').textContent = promo.name;
        document.querySelector('.promo-description').textContent = promo.description || '';
        document.getElementById('promoPrice').textContent = money(promo.price);
        const menuPromo = catalog.products.find(product => Number(product.id) === Number(promo.id)) || promo;
        document.getElementById('promoButton').onclick = () => openCustomize(menuPromo);
    }

    // ============================================================
    // Personalización
    // ============================================================
    function openCustomize(product, cartIndex = null) {
        currentProduct = product;
        currentCartIndex = cartIndex;
        const modal = document.getElementById('customizeModal');
        document.getElementById('customizeTitle').textContent = cartIndex === null
            ? 'Personalizá tu ' + product.name
            : 'Editá tu ' + product.name;
        document.getElementById('customizeSubmit').textContent = cartIndex === null
            ? 'Agregar al pedido'
            : 'Guardar cambios';
        const ingContainer = document.getElementById('customizeIngredients');
        const hasIngredients = Array.isArray(product.ingredients) && product.ingredients.length > 0;
        document.getElementById('customizeOptions').hidden = !hasIngredients;
        document.getElementById('customizeEmpty').hidden = hasIngredients;
        document.getElementById('customizeRemoveLabel').textContent = hasIngredients
            ? '¿Querés quitar algo más?'
            : '¿Qué ingredientes querés quitar?';
        document.getElementById('customizeRemoveHelp').textContent = hasIngredients
            ? 'Podés sumar otros ingredientes separados por coma; cocina verá todo junto.'
            : 'Escribilos separados por coma; aparecerán destacados para cocina.';
        ingContainer.innerHTML = '';
        document.getElementById('customizeIntro').textContent = hasIngredients
            ? 'Marcá lo que querés sacar. Lo que queda seleccionado se mantiene en tu burger.'
            : 'Sumá cualquier detalle para que cocina prepare tu pedido como te gusta.';
        const savedItem = cartIndex === null ? null : cart[cartIndex];
        const removedBefore = new Set((savedItem?.removed_ingredients || []).map(Number));

        (product.ingredients || []).forEach(ing => {
            const label = document.createElement('label');
            label.className = 'ingredient-check';
            label.innerHTML = `
                <input type="checkbox" checked value="${escapeHtml(ing.ingredient_id)}">
                <span class="ingredient-name">${escapeHtml(ing.name)}</span>
                <span class="ingredient-state">Mantener</span>
            `;
            const checkbox = label.querySelector('input');
            checkbox.checked = !removedBefore.has(Number(ing.ingredient_id));
            checkbox.addEventListener('change', () => {
                label.querySelector('.ingredient-state').textContent = checkbox.checked ? 'Se mantiene' : 'Se quita';
                updateCustomizeSummary();
            });
            label.querySelector('.ingredient-state').textContent = checkbox.checked ? 'Se mantiene' : 'Se quita';
            ingContainer.appendChild(label);
        });

        document.getElementById('customizeNotes').value = savedItem?.custom_notes || '';
        document.getElementById('customizeRemoveText').value = savedItem?.custom_removals || '';
        updateCustomizeSummary();
        updateCustomizeNotesCount();
        modal.showModal();
    }

    function updateCustomizeSummary() {
        const removed = [...document.querySelectorAll('#customizeIngredients input[type="checkbox"]:not(:checked)')]
            .map(input => input.closest('.ingredient-check')?.querySelector('.ingredient-name')?.textContent.trim())
            .filter(Boolean);
        document.getElementById('customizeSummary').textContent = removed.length
            ? `Se quitar${removed.length === 1 ? 'á' : 'án'}: ${removed.join(', ')}`
            : 'No se quitarán ingredientes.';
    }

    function updateCustomizeNotesCount() {
        const notes = document.getElementById('customizeNotes');
        document.getElementById('customizeNotesCount').textContent = `${notes.value.length} / ${notes.maxLength}`;
    }

    function closeCustomization() {
        document.getElementById('customizeModal').close();
        currentProduct = null;
        currentCartIndex = null;
    }

    document.getElementById('customizeNotes')?.addEventListener('input', updateCustomizeNotesCount);
    document.getElementById('customizeClose')?.addEventListener('click', closeCustomization);

    document.getElementById('customizeForm')?.addEventListener('submit', (e) => {
        e.preventDefault();
        if (!currentProduct) return;

        const removed = [];
        document.querySelectorAll('#customizeIngredients input[type="checkbox"]').forEach(cb => {
            if (!cb.checked) removed.push(parseInt(cb.value, 10));
        });

        const notes = document.getElementById('customizeNotes').value.trim();
        const customRemovals = document.getElementById('customizeRemoveText').value.trim().replace(/\s+/g, ' ');

        const editedItem = {
            product_id: currentProduct.id,
            product_name: currentProduct.name,
            unit_price: currentProduct.price,
            quantity: currentCartIndex === null ? 1 : cart[currentCartIndex].quantity,
            removed_ingredients: removed,
            custom_removals: customRemovals,
            custom_notes: notes,
        };

        if (currentCartIndex === null) cart.push(editedItem);
        else cart[currentCartIndex] = editedItem;
        saveCart();
        updateCartUI();
        closeCustomization();
    });

    document.getElementById('customizeCancel')?.addEventListener('click', () => {
        closeCustomization();
    });

    // ============================================================
    // Carrito
    // ============================================================
    function updateCartUI() {
        const count = cart.reduce((sum, i) => sum + i.quantity, 0);
        document.getElementById('cartCount').textContent = count;
        renderCartItems();
    }

    function renderCartItems() {
        const container = document.getElementById('cartItems');
        const panel = document.getElementById('cartPanel');
        container.innerHTML = '';
        panel.classList.toggle('is-empty', cart.length === 0);
        document.getElementById('checkoutButton').disabled = cart.length === 0;
        if (cart.length === 0) {
            container.innerHTML = '<div class="empty-cart-message"><strong>Tu carrito está vacío</strong><span>Elegí una hamburguesa para empezar el pedido.</span></div>';
            document.getElementById('cartTotals').innerHTML = '';
            return;
        }

        cart.forEach((item, index) => {
            const product = catalog.products.find(p => Number(p.id) === Number(item.product_id));
            const removedNames = (item.removed_ingredients || [])
                .map(id => product?.ingredients?.find(ing => Number(ing.ingredient_id) === Number(id))?.name)
                .filter(Boolean);
            const div = document.createElement('div');
            div.className = 'cart-item';
            div.innerHTML = `
                <div class="cart-item-info">
                    <strong>${escapeHtml(item.product_name)}</strong>
                    <span>${money(item.unit_price)}</span>
                    ${removedNames.length ? `<small>Quitar: ${escapeHtml(removedNames.join(', '))}</small>` : ''}
                    ${item.custom_removals ? `<small>Quitar: ${escapeHtml(item.custom_removals)}</small>` : ''}
                    ${item.custom_notes ? `<small>Nota: ${escapeHtml(item.custom_notes)}</small>` : ''}
                </div>
                <div class="cart-item-actions">
                    <button type="button" class="btn-qty" data-action="dec" data-index="${index}" aria-label="Quitar uno">−</button>
                    <span aria-live="polite">${item.quantity}</span>
                    <button type="button" class="btn-qty" data-action="inc" data-index="${index}" aria-label="Agregar uno">+</button>
                    <button type="button" class="btn-edit-item" data-action="edit" data-index="${index}">Editar</button>
                    <button type="button" class="btn-remove" data-action="remove" data-index="${index}" aria-label="Eliminar">×</button>
                </div>
            `;
            container.appendChild(div);
        });

        container.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = parseInt(btn.dataset.index, 10);
                const action = btn.dataset.action;
                if (action === 'inc' && cart[idx].quantity < 99) cart[idx].quantity++;
                if (action === 'dec') {
                    cart[idx].quantity--;
                    if (cart[idx].quantity <= 0) cart.splice(idx, 1);
                }
                if (action === 'remove') cart.splice(idx, 1);
                if (action === 'edit') {
                    const product = catalog.products.find(p => Number(p.id) === Number(cart[idx].product_id));
                    if (product) openCustomize(product, idx);
                    return;
                }
                saveCart();
                updateCartUI();
            });
        });

        const subtotal = cart.reduce((s, i) => s + i.unit_price * i.quantity, 0);
        const deliveryType = document.querySelector('input[name="deliveryType"]:checked')?.value;
        const deliveryFee = deliveryType === 'delivery' ? Number(catalog.delivery_fee || 0) : 0;
        document.getElementById('cartTotals').innerHTML = `
            <div class="total-row"><span>Subtotal</span><span>${money(subtotal)}</span></div>
            ${deliveryType === 'delivery' ? `<div class="total-row"><span>Delivery</span><span>${deliveryFee ? money(deliveryFee) : 'Sin cargo'}</span></div>` : ''}
            <div class="total-row total-final"><span>Total</span><span>${money(subtotal + deliveryFee)}</span></div>
        `;
    }

    // ============================================================
    // Checkout
    // ============================================================
    document.getElementById('checkoutButton')?.addEventListener('click', async () => {
        const issuesEl = document.getElementById('cartIssues');
        const checkoutButton = document.getElementById('checkoutButton');
        issuesEl.hidden = true;

        if (checkoutButton.disabled) return;
        if (cart.length === 0) {
            issuesEl.textContent = 'El carrito está vacío.';
            issuesEl.hidden = false;
            return;
        }

        const deliveryType = document.querySelector('input[name="deliveryType"]:checked').value;

        if (deliveryType === 'delivery') {
            const required = ['customerName', 'customerPhone', 'deliveryStreet', 'deliveryNumber', 'deliveryLocality'];
            for (const id of required) {
                if (!document.getElementById(id).value.trim()) {
                    issuesEl.textContent = 'Completá todos los campos obligatorios para delivery.';
                    issuesEl.hidden = false;
                    return;
                }
            }
        }

        const payload = {
            items: cart,
            delivery_type: deliveryType,
            payment_method: 'cash',
            customer_name: document.getElementById('customerName')?.value.trim() || null,
            customer_phone: document.getElementById('customerPhone')?.value.trim() || null,
            delivery_street: document.getElementById('deliveryStreet')?.value.trim() || null,
            delivery_number: document.getElementById('deliveryNumber')?.value.trim() || null,
            delivery_locality: document.getElementById('deliveryLocality')?.value.trim() || null,
            delivery_apartment: document.getElementById('deliveryApartment')?.value.trim() || null,
            delivery_floor: document.getElementById('deliveryFloor')?.value.trim() || null,
            delivery_ring: document.getElementById('deliveryRing')?.value.trim() || null,
            delivery_notes: document.getElementById('deliveryNotes')?.value.trim() || null,
            idempotency_key: makeIdempotencyKey(),
        };

        checkoutButton.disabled = true;
        checkoutButton.textContent = 'Enviando pedido…';
        try {
            const result = await api('create-order', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            cart = [];
            saveCart();
            updateCartUI();
            document.getElementById('cartPanel').hidden = true;
            document.getElementById('cartToggle').setAttribute('aria-expanded', 'false');
            showTracking(result.tracking_token);
        } catch (e) {
            issuesEl.textContent = e.message;
            issuesEl.hidden = false;
        } finally {
            checkoutButton.textContent = 'Confirmar pedido';
            checkoutButton.disabled = cart.length === 0;
        }
    });

    // ============================================================
    // Seguimiento
    // ============================================================
    async function showTracking(token) {
        const modal = document.getElementById('trackingModal');
        const content = document.getElementById('trackingContent');
        content.innerHTML = '<p>Cargando…</p>';
        modal.showModal();
        try {
            const order = await api('track', { query: { token } });
            content.innerHTML = `
                <p><strong>Pedido #${order.id}</strong></p>
                <p>Estado: <strong>${statusLabel(order.status)}</strong></p>
                <p>Total: ${money(order.total)}</p>
                <p>Modalidad: ${order.delivery_type === 'delivery' ? 'Delivery' : 'Retiro en local'}</p>
                ${order.delivery_type === 'delivery' ? `<p>Dirección: ${escapeHtml(order.delivery_street || '')} ${escapeHtml(order.delivery_number || '')}, ${escapeHtml(order.delivery_locality || '')}</p>` : ''}
                <h3>Productos</h3>
                <ul>${order.items.map(i => `<li>${escapeHtml(i.product_name)} × ${i.quantity}</li>`).join('')}</ul>
            `;
        } catch (e) {
            content.innerHTML = `<p class="error">${escapeHtml(e.message)}</p>`;
        }
    }

    function statusLabel(status) {
        const map = {
            received: 'Recibido',
            preparing: 'En preparación',
            ready_for_pickup: 'Listo para retirar',
            ready_for_delivery: 'Listo para enviar',
            on_the_way: 'En camino',
            delivered: 'Entregado',
            cancelled: 'Cancelado',
        };
        return map[status] || status;
    }

    document.getElementById('trackingClose')?.addEventListener('click', () => {
        document.getElementById('trackingModal').close();
    });

    // ============================================================
    // Carrito toggle
    // ============================================================
    document.getElementById('cartToggle')?.addEventListener('click', () => {
        const panel = document.getElementById('cartPanel');
        panel.hidden = !panel.hidden;
        document.getElementById('cartToggle').setAttribute('aria-expanded', !panel.hidden);
    });

    document.getElementById('cartClose')?.addEventListener('click', () => {
        document.getElementById('cartPanel').hidden = true;
        document.getElementById('cartToggle').setAttribute('aria-expanded', 'false');
    });

    // Delivery toggle
    function syncDeliveryAvailability() {
        const delivery = document.querySelector('input[name="deliveryType"][value="delivery"]');
        const pickup = document.querySelector('input[name="deliveryType"][value="pickup"]');
        const label = delivery?.closest('label');
        const enabled = catalog.delivery_enabled === true;

        if (!delivery || !pickup) return;

        delivery.disabled = !enabled;
        label?.classList.toggle('is-disabled', !enabled);

        let unavailable = label?.querySelector('.delivery-unavailable');
        if (!enabled) {
            delivery.checked = false;
            pickup.checked = true;
            if (label && !unavailable) {
                unavailable = document.createElement('small');
                unavailable.className = 'delivery-unavailable';
                unavailable.textContent = 'No disponible';
                label.appendChild(unavailable);
            }
        } else if (unavailable) {
            unavailable.remove();
        }

        syncDeliveryForm();
    }

    function syncDeliveryForm() {
        const selected = document.querySelector('input[name="deliveryType"]:checked');
        document.getElementById('deliveryForm').hidden = selected?.value !== 'delivery';
    }

    document.querySelectorAll('input[name="deliveryType"]').forEach(radio => {
        radio.addEventListener('change', () => {
            syncDeliveryForm();
            updateCartUI();
        });
    });
    syncDeliveryForm();

    // ============================================================
    // Escape
    // ============================================================
    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[m]));
    }

    // Iniciar
    document.addEventListener('DOMContentLoaded', init);
})();

