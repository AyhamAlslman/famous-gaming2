<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

ensure_user_auth_schema($conn);

$page_title = t('store_page_title');
$store_can_order = !empty($_SESSION['site_user_id']);
$store_login_url = site_url('general/login.php?redirect=' . urlencode('user/store.php'));
$allowed_categories = [
    'PlayStation Consoles',
    'Controllers',
    'Games / CDs',
    'Controller Covers',
    'PlayStation Accessories'
];

$selected_category = isset($_GET['category']) ? sanitize_input($_GET['category']) : '';
if (!in_array($selected_category, $allowed_categories, true)) {
    $selected_category = '';
}

$store_ready = false;
$products = [];

$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'store_products'");
if ($table_check && mysqli_num_rows($table_check) > 0) {
    $store_ready = true;
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, product_name, category, price, description, image_path, stock_quantity, status
         FROM store_products
         WHERE status = 'Active'
         ORDER BY created_at DESC, id DESC"
    );

    if ($stmt) {
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $products = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }
}

include dirname(__DIR__) . '/includes/header.php';
?>

<section class="hero store-hero"> 
    <div class="container">
        <div class="store-hero-shell">
            <div class="store-hero-copy store-hero-copy-centered">
                <span class="store-eyebrow"><?php echo t('store_eyebrow'); ?></span>
                <h1><?php echo t('store_hero_title'); ?></h1>
                <p><?php echo t('store_hero_text'); ?></p>
            </div>
        </div>
    </div>
</section>

<section class="content store-content">
    <div class="container">
        <div class="store-toolbar">
            <div>
                <h2 class="section-title store-section-title"><?php echo t('store_section_title'); ?></h2>
                <p class="store-toolbar-text"><?php echo t('store_toolbar_text'); ?></p>
                <p class="store-scope-note"><?php echo t('store_scope_text'); ?></p>
                <?php if (!$store_can_order): ?>
                    <div class="message info store-auth-message"><?php echo t('store_login_required_notice'); ?></div>
                <?php endif; ?>
            </div>
            <div class="store-filter-chips" id="storeFilterChips">
                <a href="<?php echo htmlspecialchars(site_url('user/store.php'), ENT_QUOTES, 'UTF-8'); ?>" class="store-filter-chip <?php echo $selected_category === '' ? 'active' : ''; ?>" data-store-filter="all"><?php echo t('store_all_products'); ?></a>
                <?php foreach ($allowed_categories as $category): ?>
                    <a
                        href="<?php echo htmlspecialchars(site_url('user/store.php?category=' . urlencode($category)), ENT_QUOTES, 'UTF-8'); ?>"
                        class="store-filter-chip <?php echo $selected_category === $category ? 'active' : ''; ?>"
                        data-store-filter="<?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <?php echo htmlspecialchars(translated_category_label($category)); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!$store_ready): ?>
            <div class="store-empty-state">
                <h3><?php echo t('store_setup_missing'); ?></h3>
                <p><?php echo t('store_setup_missing_text'); ?></p>
            </div>
        <?php elseif (empty($products)): ?>
            <div class="store-empty-state">
                <h3><?php echo t('store_no_products'); ?></h3>
                <p><?php echo t('store_no_products_text'); ?></p>
            </div>
        <?php else: ?>
            <div class="store-layout">
                <div class="store-products-column">
                    <div class="row g-4 store-grid" id="storeProductsGrid">
                        <?php foreach ($products as $product): ?>
                            <?php
                            $product_image = site_asset_url($product['image_path'] ?? '', 'images/store.jpg');
                            $is_in_stock = ((int)$product['stock_quantity'] > 0);
                            $stock_label = !$is_in_stock ? t('store_stock_out') : (((int)$product['stock_quantity'] <= 5) ? t('store_stock_limited') : t('store_stock_in'));
                            $stock_class = !$is_in_stock ? 'store-stock-out' : (((int)$product['stock_quantity'] <= 5) ? 'store-stock-limited' : 'store-stock-in');
                            ?>
                            <div class="col-12 col-md-6 col-xl-4" data-store-card data-store-category="<?php echo htmlspecialchars($product['category'], ENT_QUOTES, 'UTF-8'); ?>">
                                <article
                                    class="store-product-card store-detail-card h-100"
                                    role="button"
                                    tabindex="0"
                                    aria-label="View details for <?php echo htmlspecialchars($product['product_name']); ?>"
                                    data-card-modal-trigger
                                    data-product-id="<?php echo (int) $product['id']; ?>"
                                    data-product-name="<?php echo htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-product-type="<?php echo htmlspecialchars(translated_category_label($product['category']), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-product-description="<?php echo htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-product-price="<?php echo number_format((float) $product['price'], 2, '.', ''); ?>"
                                    data-product-stock="<?php echo (int) $product['stock_quantity']; ?>"
                                    data-product-image="<?php echo htmlspecialchars($product_image, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-card-title="<?php echo htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-card-type="<?php echo htmlspecialchars(translated_category_label($product['category']), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-card-description="<?php echo htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                    <div class="store-product-media" data-card-media>
                                        <img src="<?php echo htmlspecialchars($product_image); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="img-fluid">
                                        <span class="store-category-badge"><?php echo htmlspecialchars(translated_category_label($product['category'])); ?></span>
                                    </div>

                                    <div class="store-product-body">
                                        <div class="store-product-meta">
                                            <span class="store-stock-badge <?php echo $stock_class; ?>" data-card-extra><?php echo $stock_label; ?></span>
                                            <span class="store-stock-qty" data-card-extra><?php echo t('store_in_stock_suffix', ['count' => (int)$product['stock_quantity']]); ?></span>
                                        </div>
                                        <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                                        <p><?php echo htmlspecialchars($product['description']); ?></p>
                                        <div class="store-product-footer">
                                            <div class="store-price" data-card-extra><?php echo number_format($product['price'], 2); ?> JOD</div>
                                            <button
                                                type="button"
                                                class="btn store-add-btn"
                                                data-store-add
                                                <?php echo !$is_in_stock ? 'disabled' : ''; ?>
                                            >
                                                <?php echo $is_in_stock ? ($store_can_order ? t('store_add_to_basket') : t('nav_login')) : t('store_unavailable'); ?>
                                            </button>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="store-empty-state store-empty-state-filtered" id="storeFilteredEmptyState" hidden>
                        <h3><?php echo t('store_no_products'); ?></h3>
                        <p><?php echo t('store_no_filtered_products_text'); ?></p>
                    </div>
                </div>

                <aside class="store-basket-sidebar" id="storeBasketSidebar">
                    <div class="store-basket-card">
                        <div class="store-basket-header">
                            <div>
                                <span class="store-basket-eyebrow"><?php echo t('store_basket'); ?></span>
                                <h3><?php echo t('store_basket_title'); ?></h3>
                            </div>
                            <span class="store-basket-count" id="storeBasketCount"><?php echo t('store_basket_items', ['count' => 0]); ?></span>
                        </div>

                        <div class="store-basket-summary">
                            <div class="store-basket-summary-item">
                                <span><?php echo t('store_basket_summary_items'); ?></span>
                                <strong id="storeBasketItemsCount">0</strong>
                            </div>
                            <div class="store-basket-summary-item">
                                <span><?php echo t('store_basket_summary_subtotal'); ?></span>
                                <strong id="storeBasketSubtotal">0.00 JOD</strong>
                            </div>
                        </div>

                        <div class="store-basket-body" id="storeBasketItems">
                            <div class="store-basket-empty">
                                <h4><?php echo t('store_basket_empty_title'); ?></h4>
                                <p><?php echo t('store_basket_empty_text'); ?></p>
                            </div>
                        </div>

                        <div class="store-basket-footer">
                            <p class="store-basket-note"><?php echo t('store_basket_note'); ?></p>
                            <div class="store-basket-actions">
                                <?php if ($store_can_order): ?>
                                    <form method="POST" action="<?php echo htmlspecialchars(site_url('user/store_checkout.php'), ENT_QUOTES, 'UTF-8'); ?>" id="storeCheckoutForm" class="store-checkout-form">
                                        <input type="hidden" name="checkout_action" value="review">
                                        <input type="hidden" name="cart_data" id="storeCheckoutCartData" value="">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit" class="btn store-checkout-btn" id="storeCheckoutButton" disabled><?php echo t('store_checkout_continue'); ?></button>
                                    </form>
                                <?php else: ?>
                                    <div class="store-basket-auth-actions">
                                        <a href="<?php echo htmlspecialchars($store_login_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn store-checkout-btn"><?php echo t('nav_login'); ?></a>
                                        <a href="<?php echo htmlspecialchars(site_url('general/register.php?redirect=' . urlencode('user/store.php')), ENT_QUOTES, 'UTF-8'); ?>" class="btn store-basket-clear-btn"><?php echo t('nav_register'); ?></a>
                                    </div>
                                <?php endif; ?>
                                <button type="button" class="btn store-basket-clear-btn" id="storeBasketClearBtn"><?php echo t('store_clear_basket'); ?></button>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($store_ready && !empty($products)): ?>
<div class="card-detail-modal" id="cardDetailModal" hidden>
    <div class="card-detail-backdrop" data-card-modal-close></div>
    <div class="card-detail-dialog" role="dialog" aria-modal="true" aria-labelledby="cardDetailModalTitle">
        <button type="button" class="card-detail-close" aria-label="<?php echo htmlspecialchars(t('store_close_details'), ENT_QUOTES, 'UTF-8'); ?>" data-card-modal-close>&times;</button>
        <div class="card-detail-layout">
            <div class="card-detail-media-shell" id="cardDetailModalMedia"></div>
            <div class="card-detail-copy">
                <span class="card-detail-type" id="cardDetailModalType"></span>
                <h3 class="card-detail-title" id="cardDetailModalTitle"></h3>
                <p class="card-detail-description" id="cardDetailModalDescription"></p>
                <div class="card-detail-meta" id="cardDetailModalMeta"></div>
                <ul class="card-detail-list" id="cardDetailModalList"></ul>
                <div class="card-detail-actions">
                    <button type="button" class="btn store-add-btn card-detail-add-btn" id="cardDetailAddBtn"><?php echo t('store_add_to_basket'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="store-basket-toast" id="storeBasketToast" hidden>
    <span id="storeBasketToastText"><?php echo t('store_added_to_basket'); ?></span>
</div>
<script>
    (function () {
        const storeTexts = <?php echo json_encode([
            'addToBasket' => t('store_add_to_basket'),
            'unavailable' => t('store_unavailable'),
            'login' => t('nav_login'),
            'addedToBasket' => t('store_added_to_basket'),
            'maxStockReached' => t('store_max_stock_reached'),
            'basketCleared' => t('store_basket_cleared'),
            'basketEmptyTitle' => t('store_basket_empty_title'),
            'basketEmptyText' => t('store_basket_empty_text'),
            'basketItem' => t('store_basket_item'),
            'basketItems' => t('store_basket_items'),
            'removeItem' => t('store_remove_item'),
            'bookedOut' => t('booking_slot_booked'),
            'loginRequired' => t('store_login_required_notice')
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const storeCanOrder = <?php echo $store_can_order ? 'true' : 'false'; ?>;
        const storeLoginUrl = <?php echo json_encode($store_login_url); ?>;
        const filterContainer = document.getElementById('storeFilterChips');
        const cards = Array.from(document.querySelectorAll('[data-store-card]'));
        const filteredEmptyState = document.getElementById('storeFilteredEmptyState');
        const initialFilter = <?php echo json_encode($selected_category === '' ? 'all' : $selected_category); ?>;
        const modal = document.getElementById('cardDetailModal');
        const modalMedia = document.getElementById('cardDetailModalMedia');
        const modalType = document.getElementById('cardDetailModalType');
        const modalTitle = document.getElementById('cardDetailModalTitle');
        const modalDescription = document.getElementById('cardDetailModalDescription');
        const modalMeta = document.getElementById('cardDetailModalMeta');
        const modalList = document.getElementById('cardDetailModalList');
        const modalAddButton = document.getElementById('cardDetailAddBtn');
        const basketCount = document.getElementById('storeBasketCount');
        const basketItemsCount = document.getElementById('storeBasketItemsCount');
        const basketSubtotal = document.getElementById('storeBasketSubtotal');
        const basketItems = document.getElementById('storeBasketItems');
        const basketClearBtn = document.getElementById('storeBasketClearBtn');
        const checkoutForm = document.getElementById('storeCheckoutForm');
        const checkoutCartInput = document.getElementById('storeCheckoutCartData');
        const checkoutButton = document.getElementById('storeCheckoutButton');
        const toast = document.getElementById('storeBasketToast');
        const toastText = document.getElementById('storeBasketToastText');
        const cartStorageKey = 'famousGamingStoreCart';
        const productMap = new Map();
        let cart = [];
        let lastTrigger = null;
        let toastTimer = null;

        if (!filterContainer || cards.length === 0) {
            return;
        }

        cards.forEach(function (card) {
            const trigger = card.querySelector('[data-card-modal-trigger]');

            if (!trigger) {
                return;
            }

            productMap.set(String(trigger.dataset.productId), {
                id: String(trigger.dataset.productId),
                name: trigger.dataset.productName || '',
                type: trigger.dataset.productType || '',
                description: trigger.dataset.productDescription || '',
                price: Number(trigger.dataset.productPrice || 0),
                stock: Number(trigger.dataset.productStock || 0),
                image: trigger.dataset.productImage || ''
            });
        });

        function saveCart() {
            window.localStorage.setItem(cartStorageKey, JSON.stringify(cart));
        }

        function loadCart() {
            try {
                const storedCart = JSON.parse(window.localStorage.getItem(cartStorageKey) || '[]');

                if (Array.isArray(storedCart)) {
                    cart = storedCart.filter(function (item) {
                        return productMap.has(String(item.id));
                    }).map(function (item) {
                        const product = productMap.get(String(item.id));
                        const quantity = Math.min(Math.max(Number(item.quantity) || 0, 0), product.stock);

                        return quantity > 0 ? { id: String(item.id), quantity: quantity } : null;
                    }).filter(Boolean);
                }
            } catch (error) {
                cart = [];
            }
        }

        function formatCurrency(value) {
            return Number(value).toFixed(2) + ' JOD';
        }

        function replaceText(template, replacements) {
            return Object.keys(replacements).reduce(function (result, key) {
                return result.replace(':' + key, replacements[key]);
            }, template);
        }

        function escapeHtml(value) {
            return String(value).replace(/[&<>"']/g, function (character) {
                const entities = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                };

                return entities[character] || character;
            });
        }

        function showToast(message) {
            if (typeof window.showSiteModal === 'function') {
                window.showSiteModal({
                    title: <?php echo json_encode(t('modal_message_title'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                    message: message,
                    type: 'info'
                });
                return;
            }

            if (!toast || !toastText) {
                return;
            }

            toastText.textContent = message;
            toast.hidden = false;
            toast.classList.add('is-visible');

            window.clearTimeout(toastTimer);
            toastTimer = window.setTimeout(function () {
                toast.classList.remove('is-visible');
                window.setTimeout(function () {
                    toast.hidden = true;
                }, 220);
            }, 1800);
        }

        function updateModalButtonState(productId) {
            if (!modalAddButton) {
                return;
            }

            const product = productMap.get(String(productId));

            if (!product || product.stock <= 0) {
                modalAddButton.disabled = true;
                modalAddButton.textContent = storeTexts.unavailable;
                modalAddButton.dataset.productId = '';
                return;
            }

            modalAddButton.disabled = false;
            modalAddButton.textContent = storeCanOrder ? storeTexts.addToBasket : storeTexts.login;
            modalAddButton.dataset.productId = String(productId);
        }

        function renderCart() {
            if (!basketItems || !basketCount || !basketItemsCount || !basketSubtotal || !basketClearBtn) {
                return;
            }

            const totalItems = cart.reduce(function (sum, item) {
                return sum + item.quantity;
            }, 0);

            const subtotalValue = cart.reduce(function (sum, item) {
                const product = productMap.get(String(item.id));
                return product ? sum + (product.price * item.quantity) : sum;
            }, 0);

            basketCount.textContent = replaceText(totalItems === 1 ? storeTexts.basketItem : storeTexts.basketItems, {
                count: String(totalItems)
            });
            basketItemsCount.textContent = String(totalItems);
            basketSubtotal.textContent = formatCurrency(subtotalValue);
            basketClearBtn.disabled = totalItems === 0;

            if (checkoutCartInput) {
                checkoutCartInput.value = JSON.stringify(cart);
            }

            if (checkoutButton) {
                checkoutButton.disabled = totalItems === 0;
            }

            if (totalItems === 0) {
                basketItems.innerHTML = '<div class="store-basket-empty"><h4>' + escapeHtml(storeTexts.basketEmptyTitle) + '</h4><p>' + escapeHtml(storeTexts.basketEmptyText) + '</p></div>';
                return;
            }

            basketItems.innerHTML = cart.map(function (item) {
                const product = productMap.get(String(item.id));

                if (!product) {
                    return '';
                }

                const imageMarkup = product.image
                    ? '<img src="' + escapeHtml(product.image) + '" alt="' + escapeHtml(product.name) + '">'
                    : '<div class="store-basket-item-placeholder">FG</div>';

                return '' +
                    '<div class="store-basket-item">' +
                        '<div class="store-basket-item-media">' + imageMarkup + '</div>' +
                        '<div class="store-basket-item-copy">' +
                            '<span class="store-basket-item-type">' + escapeHtml(product.type) + '</span>' +
                            '<h4>' + escapeHtml(product.name) + '</h4>' +
                            '<div class="store-basket-item-price-row">' +
                                '<span class="store-basket-item-price">' + formatCurrency(product.price) + '</span>' +
                                '<div class="store-basket-qty-controls">' +
                                    '<button type="button" class="store-basket-qty-btn" data-basket-decrease="' + product.id + '">-</button>' +
                                    '<span class="store-basket-qty-value">' + item.quantity + '</span>' +
                                    '<button type="button" class="store-basket-qty-btn" data-basket-increase="' + product.id + '"' + (item.quantity >= product.stock ? ' disabled' : '') + '>+</button>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                        '<button type="button" class="store-basket-remove-btn" data-basket-remove="' + product.id + '" aria-label="' + escapeHtml(replaceText(storeTexts.removeItem, { name: product.name })) + '">&times;</button>' +
                    '</div>';
            }).join('');
        }

        function changeQuantity(productId, nextQuantity) {
            const product = productMap.get(String(productId));

            if (!product || product.stock <= 0) {
                return;
            }

            const index = cart.findIndex(function (item) {
                return String(item.id) === String(productId);
            });

            if (nextQuantity <= 0) {
                if (index >= 0) {
                    cart.splice(index, 1);
                }
            } else {
                const safeQuantity = Math.min(nextQuantity, product.stock);

                if (index >= 0) {
                    cart[index].quantity = safeQuantity;
                } else {
                    cart.push({ id: String(productId), quantity: safeQuantity });
                }
            }

            saveCart();
            renderCart();
        }

        function addToCart(productId) {
            if (!storeCanOrder) {
                if (typeof window.showSiteConfirm === 'function') {
                    window.showSiteConfirm(storeTexts.loginRequired, function () {
                        window.location.href = storeLoginUrl;
                    }, <?php echo json_encode(t('modal_message_title'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>);
                } else {
                    window.location.href = storeLoginUrl;
                }
                return;
            }

            const product = productMap.get(String(productId));

            if (!product || product.stock <= 0) {
                return;
            }

            const currentItem = cart.find(function (item) {
                return String(item.id) === String(productId);
            });
            const nextQuantity = (currentItem ? currentItem.quantity : 0) + 1;

            if (nextQuantity > product.stock) {
                showToast(replaceText(storeTexts.maxStockReached, { name: product.name }));
                return;
            }

            changeQuantity(productId, nextQuantity);
            showToast(product.name + ' - ' + storeTexts.addedToBasket);
        }

        function applyFilter(filterValue) {
            let visibleCount = 0;

            cards.forEach(function (card) {
                const matches = filterValue === 'all' || card.dataset.storeCategory === filterValue;
                card.hidden = !matches;

                if (matches) {
                    visibleCount += 1;
                }
            });

            Array.from(filterContainer.querySelectorAll('.store-filter-chip')).forEach(function (chip) {
                chip.classList.toggle('active', chip.dataset.storeFilter === filterValue);
            });

            if (filteredEmptyState) {
                filteredEmptyState.hidden = visibleCount !== 0;
            }
        }

        function openModal(card) {
            if (!modal || !modalMedia || !modalType || !modalTitle || !modalDescription || !modalMeta || !modalList) {
                return;
            }

            lastTrigger = card;
            modalMedia.innerHTML = '';
            modalMeta.innerHTML = '';
            modalList.innerHTML = '';

            const media = card.querySelector('[data-card-media]');
            const extras = Array.from(card.querySelectorAll('[data-card-extra]'));

            if (media) {
                modalMedia.appendChild(media.cloneNode(true));
            }

            modalType.textContent = card.dataset.cardType || '';
            modalTitle.textContent = card.dataset.cardTitle || '';
            modalDescription.textContent = card.dataset.cardDescription || '';
            updateModalButtonState(card.dataset.productId || '');

            extras.forEach(function (extra) {
                const item = document.createElement('span');
                item.className = 'card-detail-meta-chip';
                item.textContent = extra.textContent.trim();
                modalMeta.appendChild(item);
            });

            if (!modalMeta.children.length) {
                modalMeta.hidden = true;
            } else {
                modalMeta.hidden = false;
            }

            modalList.hidden = true;
            modal.hidden = false;
            document.body.classList.add('card-detail-modal-open');
        }

        function closeModal() {
            if (!modal || modal.hidden) {
                return;
            }

            modal.hidden = true;
            document.body.classList.remove('card-detail-modal-open');

            if (lastTrigger) {
                lastTrigger.focus();
            }
        }

        filterContainer.addEventListener('click', function (event) {
            const chip = event.target.closest('.store-filter-chip');

            if (!chip) {
                return;
            }

            event.preventDefault();
            applyFilter(chip.dataset.storeFilter || 'all');
        });

        cards.forEach(function (card) {
            const trigger = card.querySelector('[data-card-modal-trigger]');

            if (!trigger) {
                return;
            }

            trigger.addEventListener('click', function (event) {
                if (event.target.closest('[data-store-add]')) {
                    return;
                }

                openModal(trigger);
            });

            trigger.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openModal(trigger);
                }
            });

            const addButton = trigger.querySelector('[data-store-add]');

            if (addButton) {
                addButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    addToCart(trigger.dataset.productId || '');
                });
            }
        });

        if (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target.closest('[data-card-modal-close]')) {
                    closeModal();
                }
            });
        }

        if (modalAddButton) {
            modalAddButton.addEventListener('click', function () {
                addToCart(modalAddButton.dataset.productId || '');
            });
        }

        if (basketItems) {
            basketItems.addEventListener('click', function (event) {
                const increaseButton = event.target.closest('[data-basket-increase]');
                const decreaseButton = event.target.closest('[data-basket-decrease]');
                const removeButton = event.target.closest('[data-basket-remove]');

                if (increaseButton) {
                    const item = cart.find(function (cartItem) {
                        return String(cartItem.id) === String(increaseButton.dataset.basketIncrease);
                    });

                    changeQuantity(increaseButton.dataset.basketIncrease, (item ? item.quantity : 0) + 1);
                }

                if (decreaseButton) {
                    const item = cart.find(function (cartItem) {
                        return String(cartItem.id) === String(decreaseButton.dataset.basketDecrease);
                    });

                    changeQuantity(decreaseButton.dataset.basketDecrease, (item ? item.quantity : 0) - 1);
                }

                if (removeButton) {
                    changeQuantity(removeButton.dataset.basketRemove, 0);
                }
            });
        }

        if (basketClearBtn) {
            basketClearBtn.addEventListener('click', function () {
                cart = [];
                saveCart();
                renderCart();
                showToast(storeTexts.basketCleared);
            });
        }

        if (checkoutForm) {
            checkoutForm.addEventListener('submit', function(event) {
                const totalItems = cart.reduce(function (sum, item) {
                    return sum + item.quantity;
                }, 0);

                if (totalItems === 0) {
                    event.preventDefault();
                    showToast(storeTexts.basketEmptyTitle);
                    return;
                }

                if (checkoutCartInput) {
                    checkoutCartInput.value = JSON.stringify(cart);
                }
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });

        loadCart();
        renderCart();
        applyFilter(initialFilter);
    })();
</script>
<?php endif; ?>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
