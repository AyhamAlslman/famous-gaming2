<?php
require_once 'auth_check.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Only admins can manage menu items
if (!isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $item_name = sanitize_input($_POST['item_name']);
            $item_category = sanitize_input($_POST['item_category']);
            $item_price = floatval($_POST['item_price']);
            $item_description = sanitize_input($_POST['item_description']);
            $is_available = isset($_POST['is_available']) ? 1 : 0;

            $stmt = mysqli_prepare($conn, "INSERT INTO menu_items (item_name, item_category, item_price, item_description, is_available) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssdsi", $item_name, $item_category, $item_price, $item_description, $is_available);

            if (mysqli_stmt_execute($stmt)) {
                $success_message = 'Menu item added successfully';
                log_admin_action($conn, $_SESSION['admin_id'], 'CREATE', 'menu_items', mysqli_insert_id($conn));
            } else {
                $error_message = 'Error adding menu item';
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] == 'edit') {
            $id = intval($_POST['id']);
            $item_name = sanitize_input($_POST['item_name']);
            $item_category = sanitize_input($_POST['item_category']);
            $item_price = floatval($_POST['item_price']);
            $item_description = sanitize_input($_POST['item_description']);
            $is_available = isset($_POST['is_available']) ? 1 : 0;

            $stmt = mysqli_prepare($conn, "UPDATE menu_items SET item_name = ?, item_category = ?, item_price = ?, item_description = ?, is_available = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssdsii", $item_name, $item_category, $item_price, $item_description, $is_available, $id);

            if (mysqli_stmt_execute($stmt)) {
                $success_message = 'Menu item updated successfully';
                log_admin_action($conn, $_SESSION['admin_id'], 'UPDATE', 'menu_items', $id);
            } else {
                $error_message = 'Error updating menu item';
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] == 'delete') {
            $id = intval($_POST['id']);

            $stmt = mysqli_prepare($conn, "DELETE FROM menu_items WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);

            if (mysqli_stmt_execute($stmt)) {
                $success_message = 'Menu item deleted successfully';
                log_admin_action($conn, $_SESSION['admin_id'], 'DELETE', 'menu_items', $id);
            } else {
                $error_message = 'Error deleting menu item';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

$items = mysqli_query($conn, "SELECT * FROM menu_items ORDER BY item_category, item_name ASC");

$page_title = 'Menu Items Management';
$active_page = 'menu_items';
include 'includes/header.php';
?>

    <div class="container" style="margin-top: 2rem;">
        <div class="page-header">
            <h2>Menu Items Management</h2>
            <button onclick="openAddModal()" class="btn">Add New Item</button>
        </div>

        <?php if ($success_message): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div style="margin-top: 2rem;">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Price (JOD)</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($item = mysqli_fetch_assoc($items)): ?>
                        <tr>
                            <td><?php echo $item['id']; ?></td>
                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                            <td>
                                <span class="category-badge category-<?php echo strtolower($item['item_category']); ?>">
                                    <?php echo htmlspecialchars($item['item_category']); ?>
                                </span>
                            </td>
                            <td><?php echo number_format($item['item_price'], 2); ?> JOD</td>
                            <td><?php echo htmlspecialchars($item['item_description']); ?></td>
                            <td>
                                <?php if ($item['is_available']): ?>
                                    <span style="color: #4CAF50;">✓ Available</span>
                                <?php else: ?>
                                    <span style="color: #f44336;">✗ Unavailable</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-small btn-success" onclick='openEditModal(<?php echo json_encode($item); ?>)'>Edit</button>
                                <button class="btn btn-small btn-danger" onclick="confirmDelete(<?php echo $item['id']; ?>)">Delete</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddModal()">&times;</span>
            <h2 style="margin-bottom: 1.5rem;">Add Menu Item</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label>Item Name</label>
                    <input type="text" name="item_name" required>
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <select name="item_category" required>
                        <option value="Drinks">Drinks</option>
                        <option value="Snacks">Snacks</option>
                        <option value="Services">Services</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Price (JOD)</label>
                    <input type="number" step="0.01" name="item_price" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="item_description" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_available" checked>
                        Available
                    </label>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn" style="width: 100%;">Add Item</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2 style="margin-bottom: 1.5rem;">Edit Menu Item</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">

                <div class="form-group">
                    <label>Item Name</label>
                    <input type="text" name="item_name" id="edit_item_name" required>
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <select name="item_category" id="edit_item_category" required>
                        <option value="Drinks">Drinks</option>
                        <option value="Snacks">Snacks</option>
                        <option value="Services">Services</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Price (JOD)</label>
                    <input type="number" step="0.01" name="item_price" id="edit_item_price" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="item_description" id="edit_item_description" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_available" id="edit_is_available">
                        Available
                    </label>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn" style="width: 100%;">Update Item</button>
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

        function openEditModal(item) {
            document.getElementById('edit_id').value = item.id;
            document.getElementById('edit_item_name').value = item.item_name;
            document.getElementById('edit_item_category').value = item.item_category;
            document.getElementById('edit_item_price').value = item.item_price;
            document.getElementById('edit_item_description').value = item.item_description;
            document.getElementById('edit_is_available').checked = item.is_available == 1;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function confirmDelete(id) {
            if (confirm('Are you sure you want to delete this menu item?')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
