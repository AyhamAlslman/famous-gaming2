<?php
require_once 'auth_check.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

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

$page_title = 'Store Products Management';
$active_page = 'store_products';
include 'includes/header.php';
?>

<div class="content">
    <div class="container">
        <div class="page-header">
            <h1>Store Products Management</h1>
            <?php if ($store_ready): ?>
                <button class="btn" onclick="openAddModal()">Add Product</button>
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
                    <h3>Total Products</h3>
                    <div class="stat-number"><?php echo $stats['total_products']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Active Products</h3>
                    <div class="stat-number"><?php echo $stats['active_products']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Low Stock</h3>
                    <div class="stat-number"><?php echo $stats['low_stock']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Out of Stock</h3>
                    <div class="stat-number"><?php echo $stats['out_of_stock']; ?></div>
                </div>
            </div>

            <div class="table-container">
                <table class="admin-store-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="8" class="no-data">No store products found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo (int)$product['id']; ?></td>
                                    <td class="admin-store-image-cell">
                                        <?php if (!empty($product['image_path'])): ?>
                                            <img src="../<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="admin-store-thumb">
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
                                            <?php echo htmlspecialchars($product['category']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($product['price'], 2); ?> JOD</td>
                                    <td><?php echo (int)$product['stock_quantity']; ?></td>
                                    <td>
                                        <span class="admin-store-status <?php echo strtolower($product['status']) === 'active' ? 'admin-store-status-active' : 'admin-store-status-inactive'; ?>">
                                            <?php echo htmlspecialchars($product['status']); ?>
                                        </span>
                                    </td>
                                    <td class="admin-store-actions-cell">
                                        <div class="admin-store-actions">
                                            <button class="btn btn-small btn-success admin-store-action-btn" onclick='openEditModal(<?php echo json_encode($product, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)'>Edit</button>
                                            <button class="btn btn-small btn-danger admin-store-action-btn" onclick="confirmDelete(<?php echo (int)$product['id']; ?>)">Delete</button>
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
<div id="addModal" class="modal">
    <div class="modal-content admin-store-modal">
        <span class="close" onclick="closeAddModal()">&times;</span>
        <h2>Add Store Product</h2>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">

            <div class="form-group">
                <label>Product Name</label>
                <input type="text" name="product_name" required>
            </div>

            <div class="form-group">
                <label>Category</label>
                <select name="category" required>
                    <?php foreach ($allowed_categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Price (JOD)</label>
                <input type="number" step="0.01" min="0" name="price" required>
            </div>

            <div class="form-group">
                <label>Stock Quantity</label>
                <input type="number" min="0" name="stock_quantity" required>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" required>
                    <?php foreach ($allowed_statuses as $status_option): ?>
                        <option value="<?php echo htmlspecialchars($status_option); ?>"><?php echo htmlspecialchars($status_option); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="4" placeholder="Add a short premium product description"></textarea>
            </div>

            <div class="form-group">
                <label>Product Image</label>
                <input type="file" name="product_image" accept="image/jpeg,image/png,image/gif">
                <small>Optional. Max size: 5MB. Formats: JPG, PNG, GIF.</small>
            </div>

            <div class="form-group">
                <button type="submit" class="btn" style="width: 100%;">Add Product</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content admin-store-modal">
        <span class="close" onclick="closeEditModal()">&times;</span>
        <h2>Edit Store Product</h2>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">

            <div class="form-group">
                <label>Product Name</label>
                <input type="text" name="product_name" id="edit_product_name" required>
            </div>

            <div class="form-group">
                <label>Category</label>
                <select name="category" id="edit_category" required>
                    <?php foreach ($allowed_categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Price (JOD)</label>
                <input type="number" step="0.01" min="0" name="price" id="edit_price" required>
            </div>

            <div class="form-group">
                <label>Stock Quantity</label>
                <input type="number" min="0" name="stock_quantity" id="edit_stock_quantity" required>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" id="edit_status" required>
                    <?php foreach ($allowed_statuses as $status_option): ?>
                        <option value="<?php echo htmlspecialchars($status_option); ?>"><?php echo htmlspecialchars($status_option); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="edit_description" rows="4"></textarea>
            </div>

            <div class="form-group">
                <label>Current Image</label>
                <div id="edit_current_image" class="admin-store-current-image"></div>
            </div>

            <div class="form-group">
                <label>Replace Image</label>
                <input type="file" name="product_image" accept="image/jpeg,image/png,image/gif">
                <small>Upload a new image only if you want to replace the current one.</small>
            </div>

            <div class="form-group">
                <button type="submit" class="btn" style="width: 100%;">Update Product</button>
            </div>
        </form>
    </div>
</div>

<form id="deleteForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
    function openAddModal() {
        document.getElementById('addModal').style.display = 'block';
    }

    function closeAddModal() {
        document.getElementById('addModal').style.display = 'none';
    }

    function openEditModal(product) {
        document.getElementById('edit_id').value = product.id;
        document.getElementById('edit_product_name').value = product.product_name;
        document.getElementById('edit_category').value = product.category;
        document.getElementById('edit_price').value = product.price;
        document.getElementById('edit_stock_quantity').value = product.stock_quantity;
        document.getElementById('edit_status').value = product.status;
        document.getElementById('edit_description').value = product.description || '';

        const imageContainer = document.getElementById('edit_current_image');
        if (product.image_path) {
            imageContainer.innerHTML = '<img src="../' + product.image_path + '" alt="Current product image">';
        } else {
            imageContainer.innerHTML = '<div class="admin-store-thumb admin-store-thumb-placeholder">FG</div><span>No image uploaded</span>';
        }

        document.getElementById('editModal').style.display = 'block';
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    function confirmDelete(id) {
        if (confirm('Are you sure you want to delete this store product?')) {
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteForm').submit();
        }
    }

    window.onclick = function(event) {
        const addModal = document.getElementById('addModal');
        const editModal = document.getElementById('editModal');

        if (event.target === addModal) {
            closeAddModal();
        }

        if (event.target === editModal) {
            closeEditModal();
        }
    };
</script>
<?php endif; ?>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
