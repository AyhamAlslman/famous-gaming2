<?php
require_once 'auth_check.php';

if (!isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$allowed_categories = [
    'PlayStation Consoles',
    'Controllers',
    'Games / CDs',
    'Controller Covers',
    'PlayStation Accessories'
];

$allowed_statuses = ['Active', 'Inactive'];
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add' || $action === 'edit') {
        $product_name = sanitize_input($_POST['product_name']);
        $category = sanitize_input($_POST['category']);
        $price = floatval($_POST['price']);
        $description = sanitize_input($_POST['description']);
        $stock_quantity = max(0, intval($_POST['stock_quantity']));
        $status = sanitize_input($_POST['status']);

        if ($product_name === '') {
            $error_message = 'Product name is required.';
        } elseif (!in_array($category, $allowed_categories, true)) {
            $error_message = 'Please choose a valid category.';
        } elseif ($price < 0) {
            $error_message = 'Price cannot be negative.';
        } elseif (!in_array($status, $allowed_statuses, true)) {
            $error_message = 'Please choose a valid status.';
        }
    }

    if ($error_message === '') {
        if ($action === 'add') {
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO store_products (product_name, category, price, description, stock_quantity, status)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt, "ssdsis", $product_name, $category, $price, $description, $stock_quantity, $status);

            if (mysqli_stmt_execute($stmt)) {
                $product_id = mysqli_insert_id($conn);

                if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $upload_result = upload_store_product_image($_FILES['product_image'], $product_id);

                    if ($upload_result['success']) {
                        $img_stmt = mysqli_prepare($conn, "UPDATE store_products SET image_path = ? WHERE id = ?");
                        mysqli_stmt_bind_param($img_stmt, "si", $upload_result['file_path'], $product_id);
                        mysqli_stmt_execute($img_stmt);
                        mysqli_stmt_close($img_stmt);
                        $success_message = 'Store product added successfully with image.';
                    } else {
                        $success_message = 'Store product added, but image upload failed: ' . $upload_result['message'];
                    }
                } else {
                    $success_message = 'Store product added successfully.';
                }

                log_admin_action($conn, $_SESSION['admin_id'], 'CREATE', 'store_products', $product_id);
            } else {
                $error_message = 'Error adding store product: ' . mysqli_stmt_error($stmt);
            }

            mysqli_stmt_close($stmt);
        } elseif ($action === 'edit') {
            $id = intval($_POST['id']);
            $remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';

            $current_stmt = mysqli_prepare($conn, "SELECT image_path FROM store_products WHERE id = ?");
            mysqli_stmt_bind_param($current_stmt, "i", $id);
            mysqli_stmt_execute($current_stmt);
            $current_result = mysqli_stmt_get_result($current_stmt);
            $current_product = mysqli_fetch_assoc($current_result);
            mysqli_stmt_close($current_stmt);

            if (!$current_product) {
                $error_message = 'Product not found.';
            } else {
                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE store_products
                     SET product_name = ?, category = ?, price = ?, description = ?, stock_quantity = ?, status = ?
                     WHERE id = ?"
                );
                mysqli_stmt_bind_param($stmt, "ssdsisi", $product_name, $category, $price, $description, $stock_quantity, $status, $id);

                if (mysqli_stmt_execute($stmt)) {
                    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $upload_result = upload_store_product_image($_FILES['product_image'], $id);

                        if ($upload_result['success']) {
                            if (!empty($current_product['image_path'])) {
                                delete_image($current_product['image_path']);
                            }

                            $img_stmt = mysqli_prepare($conn, "UPDATE store_products SET image_path = ? WHERE id = ?");
                            mysqli_stmt_bind_param($img_stmt, "si", $upload_result['file_path'], $id);
                            mysqli_stmt_execute($img_stmt);
                            mysqli_stmt_close($img_stmt);
                            $success_message = 'Store product updated successfully with new image.';
                        } else {
                            $success_message = 'Store product updated, but image upload failed: ' . $upload_result['message'];
                        }
                    } elseif ($remove_image && !empty($current_product['image_path'])) {
                        delete_image($current_product['image_path']);

                        $img_stmt = mysqli_prepare($conn, "UPDATE store_products SET image_path = NULL WHERE id = ?");
                        mysqli_stmt_bind_param($img_stmt, "i", $id);
                        mysqli_stmt_execute($img_stmt);
                        mysqli_stmt_close($img_stmt);

                        $success_message = 'Store product updated and image removed successfully.';
                    } else {
                        $success_message = 'Store product updated successfully.';
                    }

                    log_admin_action($conn, $_SESSION['admin_id'], 'UPDATE', 'store_products', $id);
                } else {
                    $error_message = 'Error updating store product: ' . mysqli_stmt_error($stmt);
                }

                mysqli_stmt_close($stmt);
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id']);

            $product_stmt = mysqli_prepare($conn, "SELECT image_path FROM store_products WHERE id = ?");
            mysqli_stmt_bind_param($product_stmt, "i", $id);
            mysqli_stmt_execute($product_stmt);
            $product_result = mysqli_stmt_get_result($product_stmt);
            $product_data = mysqli_fetch_assoc($product_result);
            mysqli_stmt_close($product_stmt);

            if (!$product_data) {
                $error_message = 'Product not found.';
            } else {
                $delete_stmt = mysqli_prepare($conn, "DELETE FROM store_products WHERE id = ?");
                mysqli_stmt_bind_param($delete_stmt, "i", $id);

                if (mysqli_stmt_execute($delete_stmt)) {
                    if (!empty($product_data['image_path'])) {
                        delete_image($product_data['image_path']);
                    }

                    log_admin_action($conn, $_SESSION['admin_id'], 'DELETE', 'store_products', $id);
                    $success_message = 'Store product deleted successfully.';
                } else {
                    $error_message = 'Error deleting store product.';
                }

                mysqli_stmt_close($delete_stmt);
            }
        }
    }
}

$products = [];
$stats = [
    'total_products' => 0,
    'active_products' => 0,
    'low_stock' => 0,
    'out_of_stock' => 0
];

$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'store_products'");
$store_ready = $table_check && mysqli_num_rows($table_check) > 0;

if ($store_ready) {
    $products_result = mysqli_query($conn, "SELECT * FROM store_products ORDER BY created_at DESC, id DESC");
    if ($products_result) {
        $products = mysqli_fetch_all($products_result, MYSQLI_ASSOC);
    }

    $stats_query = mysqli_query(
        $conn,
        "SELECT
            COUNT(*) AS total_products,
            SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) AS active_products,
            SUM(CASE WHEN stock_quantity BETWEEN 1 AND 5 THEN 1 ELSE 0 END) AS low_stock,
            SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) AS out_of_stock
         FROM store_products"
    );

    if ($stats_query) {
        $stats_row = mysqli_fetch_assoc($stats_query);
        if ($stats_row) {
            $stats = array_map('intval', $stats_row);
        }
    }
}

$page_title = t('admin_store_products_management');
$active_page = 'store_products';
include 'includes/header.php';
?>

<div class="content">
    <div class="container">
        <div class="page-header">
            <h1><?php echo t('admin_store_products_management'); ?></h1>
            <?php if ($store_ready): ?>
                <button class="btn" onclick="openProductModal()"><?php echo t('admin_action_add'); ?> <?php echo t('admin_product'); ?></button>
            <?php endif; ?>
        </div>

        <?php if ($success_message): ?>
            <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (!$store_ready): ?>
            <div class="card admin-store-empty">
                <h2>Store table is missing</h2>
                <p>Run the updated database setup so the new <code>store_products</code> table is created before managing products here.</p>
            </div>
        <?php else: ?>
            <div class="dashboard-stats admin-store-stats">
                <div class="stat-card">
                    <h3><?php echo t('admin_total_products'); ?></h3>
                    <div class="stat-number"><?php echo $stats['total_products']; ?></div>
                </div>
                <div class="stat-card">
                    <h3><?php echo t('admin_active_products'); ?></h3>
                    <div class="stat-number"><?php echo $stats['active_products']; ?></div>
                </div>
                <div class="stat-card">
                    <h3><?php echo t('admin_low_stock'); ?></h3>
                    <div class="stat-number"><?php echo $stats['low_stock']; ?></div>
                </div>
                <div class="stat-card">
                    <h3><?php echo t('admin_out_of_stock'); ?></h3>
                    <div class="stat-number"><?php echo $stats['out_of_stock']; ?></div>
                </div>
            </div>

            <div class="table-container">
                <table class="admin-store-table">
                    <thead>
                        <tr>
                            <th><?php echo t('admin_field_id'); ?></th>
                            <th><?php echo t('admin_field_image'); ?></th>
                            <th><?php echo t('admin_product_name'); ?></th>
                            <th><?php echo t('admin_field_category'); ?></th>
                            <th><?php echo t('admin_field_price'); ?></th>
                            <th><?php echo t('admin_field_stock'); ?></th>
                            <th><?php echo t('admin_field_status'); ?></th>
                            <th><?php echo t('admin_field_actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="8" class="no-data"><?php echo t('store_no_products'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <?php $admin_product_image = site_asset_url($product['image_path'] ?? '', ''); ?>
                                <tr>
                                    <td><?php echo (int)$product['id']; ?></td>
                                    <td class="admin-store-image-cell">
                                        <?php if ($admin_product_image !== ''): ?>
                                            <img src="<?php echo htmlspecialchars($admin_product_image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="admin-store-thumb">
                                        <?php else: ?>
                                            <div class="admin-store-thumb admin-store-thumb-placeholder">FG</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="admin-store-product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                        <div class="admin-store-product-description"><?php echo htmlspecialchars($product['description']); ?></div>
                                    </td>
                                    <td>
                                        <span class="category-badge admin-store-category">
                                            <?php echo htmlspecialchars(translated_category_label($product['category'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($product['price'], 2); ?> JOD</td>
                                    <td><?php echo (int)$product['stock_quantity']; ?></td>
                                    <td>
                                        <span class="admin-store-status <?php echo strtolower($product['status']) === 'active' ? 'admin-store-status-active' : 'admin-store-status-inactive'; ?>">
                                            <?php echo htmlspecialchars(t('admin_status_' . strtolower($product['status']), [], $product['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="admin-store-actions-cell">
                                        <div class="admin-store-actions">
                                            <button class="btn btn-small btn-success admin-store-action-btn" onclick='openProductModal(<?php echo json_encode($product, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)'><?php echo t('admin_action_edit'); ?></button>
                                            <button class="btn btn-small btn-danger admin-store-action-btn" onclick="confirmDelete(<?php echo (int)$product['id']; ?>)"><?php echo t('admin_action_delete'); ?></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($store_ready): ?>
<div id="formModal" class="modal">
    <div class="modal-content admin-store-modal">
        <span class="close" onclick="closeFormModal()">&times;</span>
        <h2 id="formModalTitle"><?php echo t('admin_action_add'); ?> <?php echo t('admin_product'); ?></h2>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" id="form_action" value="add">
            <input type="hidden" name="id" id="form_id">
            <?php echo admin_csrf_input(); ?>

            <div class="form-group">
                <label><?php echo t('admin_product_name'); ?></label>
                <input type="text" name="product_name" id="form_product_name" required>
            </div>

            <div class="form-group">
                <label><?php echo t('admin_field_category'); ?></label>
                <select name="category" id="form_category" required>
                    <?php foreach ($allowed_categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars(translated_category_label($category)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label><?php echo t('admin_field_price'); ?> (JOD)</label>
                <input type="number" step="0.01" min="0" name="price" id="form_price" required>
            </div>

            <div class="form-group">
                <label><?php echo t('admin_field_stock'); ?></label>
                <input type="number" min="0" name="stock_quantity" id="form_stock_quantity" required>
            </div>

            <div class="form-group">
                <label><?php echo t('admin_field_status'); ?></label>
                <select name="status" id="form_status" required>
                    <?php foreach ($allowed_statuses as $status_option): ?>
                        <option value="<?php echo htmlspecialchars($status_option); ?>"><?php echo htmlspecialchars(t('admin_status_' . strtolower($status_option), [], $status_option)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label><?php echo t('admin_field_description'); ?></label>
                <textarea name="description" id="form_description" rows="4" placeholder="<?php echo htmlspecialchars(t('admin_product_description_placeholder'), ENT_QUOTES, 'UTF-8'); ?>"></textarea>
            </div>

            <div class="form-group admin-current-image-group" id="form_current_image_group" hidden>
                <label><?php echo t('admin_current_image'); ?></label>
                <div id="form_current_image" class="admin-store-current-image"></div>
            </div>

            <div class="form-group admin-current-image-group" id="form_remove_image_group" hidden>
                <label>
                    <input type="checkbox" name="remove_image" id="form_remove_image" value="1">
                    <?php echo t('admin_remove_current_image'); ?>
                </label>
                <small><?php echo t('admin_remove_image_hint'); ?></small>
            </div>

            <div class="form-group">
                <label id="form_image_label"><?php echo t('admin_field_image'); ?></label>
                <input type="file" name="product_image" id="form_product_image" accept="image/jpeg,image/png,image/gif">
                <small id="form_image_hint"><?php echo t('admin_upload_hint'); ?></small>
            </div>

            <div class="form-group">
                <button type="submit" class="btn" id="form_submit" style="width: 100%;"><?php echo t('admin_action_add'); ?></button>
            </div>
        </form>
    </div>
</div>

<form id="deleteForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
    <?php echo admin_csrf_input(); ?>
</form>

<script>
    const adminSiteBaseUrl = <?php echo json_encode(site_url(), JSON_UNESCAPED_SLASHES); ?>;

    function resolveAdminAssetUrl(imagePath) {
        if (!imagePath) {
            return '';
        }

        if (/^https?:\/\//i.test(imagePath)) {
            return imagePath;
        }

        return adminSiteBaseUrl.replace(/\/$/, '/') + String(imagePath).replace(/^\/+/, '');
    }

    function renderEditProductImage(imagePath) {
        const imageContainer = document.getElementById('form_current_image');
        const imageUrl = resolveAdminAssetUrl(imagePath);

        if (imageUrl) {
            imageContainer.innerHTML = '<img src="' + imageUrl.replace(/"/g, '&quot;') + '" alt="Current product image"><span>Current product image</span>';
        } else {
            imageContainer.innerHTML = '<div class="admin-store-thumb admin-store-thumb-placeholder">FG</div><span>No image uploaded</span>';
        }
    }

    function openProductModal(product) {
        const isEdit = !!product;
        document.getElementById('form_action').value = isEdit ? 'edit' : 'add';
        document.getElementById('form_id').value = isEdit ? product.id : '';
        document.getElementById('form_product_name').value = isEdit ? product.product_name : '';
        document.getElementById('form_category').value = isEdit ? product.category : '<?php echo addslashes($allowed_categories[0]); ?>';
        document.getElementById('form_price').value = isEdit ? product.price : '';
        document.getElementById('form_stock_quantity').value = isEdit ? product.stock_quantity : '';
        document.getElementById('form_status').value = isEdit ? product.status : 'Active';
        document.getElementById('form_description').value = isEdit ? (product.description || '') : '';
        document.getElementById('form_remove_image').checked = false;
        document.getElementById('form_product_image').value = '';
        document.getElementById('form_current_image_group').hidden = !isEdit;
        document.getElementById('form_remove_image_group').hidden = !isEdit;
        document.getElementById('form_image_label').textContent = isEdit ? '<?php echo addslashes(t('admin_replace_image')); ?>' : '<?php echo addslashes(t('admin_field_image')); ?>';
        document.getElementById('form_image_hint').textContent = isEdit ? '<?php echo addslashes(t('admin_replace_image_hint')); ?>' : '<?php echo addslashes(t('admin_upload_hint')); ?>';
        document.getElementById('formModalTitle').textContent = isEdit ? '<?php echo addslashes(t('admin_action_edit') . ' ' . t('admin_product')); ?>' : '<?php echo addslashes(t('admin_action_add') . ' ' . t('admin_product')); ?>';
        document.getElementById('form_submit').textContent = isEdit ? '<?php echo addslashes(t('admin_action_update')); ?>' : '<?php echo addslashes(t('admin_action_add')); ?>';

        document.getElementById('form_current_image').dataset.imagePath = isEdit ? (product.image_path || '') : '';
        renderEditProductImage(isEdit ? (product.image_path || '') : '');

        document.getElementById('formModal').style.display = 'block';
    }

    function closeFormModal() {
        document.getElementById('formModal').style.display = 'none';
    }

    function confirmDelete(id) {
        showAdminConfirm('<?php echo addslashes(t('admin_delete_confirm')); ?>', function() {
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteForm').submit();
        });
    }

    document.getElementById('form_remove_image').addEventListener('change', function() {
        const currentImagePath = document.getElementById('form_current_image').dataset.imagePath || '';
        renderEditProductImage(this.checked ? '' : currentImagePath);
    });
</script>
<?php endif; ?>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
