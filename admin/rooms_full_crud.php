<?php
require_once 'auth_check.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $room_name = sanitize_input($_POST['room_name']);
            $room_type = sanitize_input($_POST['room_type']);
            $price_per_hour = floatval($_POST['price_per_hour']);
            $status = sanitize_input($_POST['status']);

            // Insert room using prepared statement
            $stmt = mysqli_prepare($conn, "INSERT INTO rooms (room_name, room_type, price_per_hour, status) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssds", $room_name, $room_type, $price_per_hour, $status);

            if (mysqli_stmt_execute($stmt)) {
                $room_id = mysqli_insert_id($conn);

                // Handle image upload if provided
                if (isset($_FILES['room_image']) && $_FILES['room_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $upload_result = upload_room_image($_FILES['room_image'], $room_id);

                    if ($upload_result['success']) {
                        // Update room with image path
                        $img_stmt = mysqli_prepare($conn, "UPDATE rooms SET image_path = ?, image_uploaded_at = NOW() WHERE id = ?");
                        mysqli_stmt_bind_param($img_stmt, "si", $upload_result['file_path'], $room_id);
                        mysqli_stmt_execute($img_stmt);
                        mysqli_stmt_close($img_stmt);

                        $success_message = 'Room added successfully with image';
                    } else {
                        $success_message = 'Room added, but image upload failed: ' . $upload_result['message'];
                    }
                } else {
                    $success_message = 'Room added successfully';
                }

                log_admin_action($conn, $_SESSION['admin_id'], 'CREATE', 'rooms', $room_id);
            } else {
                $error_message = 'Error adding room: ' . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] == 'edit') {
            $id = intval($_POST['id']);
            $room_name = sanitize_input($_POST['room_name']);
            $room_type = sanitize_input($_POST['room_type']);
            $price_per_hour = floatval($_POST['price_per_hour']);
            $status = sanitize_input($_POST['status']);

            // Get current room data for old image
            $current_stmt = mysqli_prepare($conn, "SELECT image_path FROM rooms WHERE id = ?");
            mysqli_stmt_bind_param($current_stmt, "i", $id);
            mysqli_stmt_execute($current_stmt);
            $current_result = mysqli_stmt_get_result($current_stmt);
            $current_room = mysqli_fetch_assoc($current_result);
            $old_image = $current_room['image_path'];
            mysqli_stmt_close($current_stmt);

            // Update room using prepared statement
            $stmt = mysqli_prepare($conn, "UPDATE rooms SET room_name = ?, room_type = ?, price_per_hour = ?, status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssdsi", $room_name, $room_type, $price_per_hour, $status, $id);

            if (mysqli_stmt_execute($stmt)) {
                // Handle image upload if provided
                if (isset($_FILES['room_image']) && $_FILES['room_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $upload_result = upload_room_image($_FILES['room_image'], $id);

                    if ($upload_result['success']) {
                        // Delete old image if exists
                        if ($old_image) {
                            delete_image($old_image);
                        }

                        // Update room with new image path
                        $img_stmt = mysqli_prepare($conn, "UPDATE rooms SET image_path = ?, image_uploaded_at = NOW() WHERE id = ?");
                        mysqli_stmt_bind_param($img_stmt, "si", $upload_result['file_path'], $id);
                        mysqli_stmt_execute($img_stmt);
                        mysqli_stmt_close($img_stmt);

                        $success_message = 'Room updated successfully with new image';
                    } else {
                        $success_message = 'Room updated, but image upload failed: ' . $upload_result['message'];
                    }
                } else {
                    $success_message = 'Room updated successfully';
                }

                log_admin_action($conn, $_SESSION['admin_id'], 'UPDATE', 'rooms', $id);
            } else {
                $error_message = 'Error updating room: ' . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] == 'delete') {
            $id = intval($_POST['id']);

            // Check for existing bookings
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM bookings WHERE room_id = ? LIMIT 1");
            mysqli_stmt_bind_param($check_stmt, "i", $id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);

            if (mysqli_num_rows($check_result) > 0) {
                $error_message = 'Cannot delete room with existing bookings';
            } else {
                // Get room image to delete
                $img_stmt = mysqli_prepare($conn, "SELECT image_path FROM rooms WHERE id = ?");
                mysqli_stmt_bind_param($img_stmt, "i", $id);
                mysqli_stmt_execute($img_stmt);
                $img_result = mysqli_stmt_get_result($img_stmt);
                $room_data = mysqli_fetch_assoc($img_result);
                mysqli_stmt_close($img_stmt);

                // Delete room
                $del_stmt = mysqli_prepare($conn, "DELETE FROM rooms WHERE id = ?");
                mysqli_stmt_bind_param($del_stmt, "i", $id);

                if (mysqli_stmt_execute($del_stmt)) {
                    // Delete image file if exists
                    if ($room_data && $room_data['image_path']) {
                        delete_image($room_data['image_path']);
                    }

                    log_admin_action($conn, $_SESSION['admin_id'], 'DELETE', 'rooms', $id);
                    $success_message = 'Room deleted successfully';
                } else {
                    $error_message = 'Error deleting room';
                }
                mysqli_stmt_close($del_stmt);
            }
            mysqli_stmt_close($check_stmt);
        }
    }
}

$rooms = mysqli_query($conn, "SELECT * FROM rooms ORDER BY id DESC");

$page_title = 'Rooms Management';
$active_page = 'rooms';
include 'includes/header.php';
?>

    <div class="content">
        <div class="container">
            <div class="page-header">
                <h1>Rooms Management</h1>
                <button class="btn" onclick="openAddModal()">Add Room</button>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Room Name</th>
                            <th>Type</th>
                            <th>Price/Hour</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($room = mysqli_fetch_assoc($rooms)): ?>
                        <tr>
                            <td><?php echo $room['id']; ?></td>
                            <td><?php echo htmlspecialchars($room['room_name']); ?></td>
                            <td><?php echo htmlspecialchars($room['room_type']); ?></td>
                            <td>$<?php echo number_format($room['price_per_hour'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($room['status']); ?>">
                                    <?php echo $room['status']; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-small btn-success" onclick='openEditModal(<?php echo json_encode($room); ?>)'>Edit</button>
                                <button class="btn btn-small btn-danger" onclick="confirmDelete(<?php echo $room['id']; ?>)">Delete</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddModal()">&times;</span>
            <h2 style="margin-bottom: 1.5rem; color: #fff;">Add Room</h2>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label>Room Name</label>
                    <input type="text" name="room_name" required>
                </div>

                <div class="form-group">
                    <label>Room Type</label>
                    <select name="room_type" required>
                        <option value="PS5">PS5</option>
                        <option value="PS4">PS4</option>
                        <option value="VIP PS5">VIP PS5</option>
                        <option value="VIP PS4">VIP PS4</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Room Image (Optional)</label>
                    <input type="file" name="room_image" accept="image/jpeg,image/png,image/gif">
                    <small style="color: #888; display: block; margin-top: 0.5rem;">Max size: 5MB. Formats: JPG, PNG, GIF</small>
                </div>

                <div class="form-group">
                    <label>Price Per Hour</label>
                    <input type="number" step="0.01" name="price_per_hour" required>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" required>
                        <option value="Available">Available</option>
                        <option value="Busy">Busy</option>
                    </select>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn" style="width: 100%;">Add Room</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2 style="margin-bottom: 1.5rem; color: #fff;">Edit Room</h2>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">

                <div class="form-group">
                    <label>Room Name</label>
                    <input type="text" name="room_name" id="edit_room_name" required>
                </div>

                <div class="form-group">
                    <label>Room Type</label>
                    <select name="room_type" id="edit_room_type" required>
                        <option value="PS5">PS5</option>
                        <option value="PS4">PS4</option>
                        <option value="VIP PS5">VIP PS5</option>
                        <option value="VIP PS4">VIP PS4</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Room Image (Optional)</label>
                    <div id="edit_current_image" style="margin-bottom: 0.5rem;"></div>
                    <input type="file" name="room_image" accept="image/jpeg,image/png,image/gif">
                    <small style="color: #888; display: block; margin-top: 0.5rem;">Upload new image to replace current one. Max size: 5MB</small>
                </div>

                <div class="form-group">
                    <label>Price Per Hour</label>
                    <input type="number" step="0.01" name="price_per_hour" id="edit_price_per_hour" required>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status" required>
                        <option value="Available">Available</option>
                        <option value="Busy">Busy</option>
                    </select>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn" style="width: 100%;">Update Room</button>
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

        function openEditModal(room) {
            document.getElementById('edit_id').value = room.id;
            document.getElementById('edit_room_name').value = room.room_name;
            document.getElementById('edit_room_type').value = room.room_type;
            document.getElementById('edit_price_per_hour').value = room.price_per_hour;
            document.getElementById('edit_status').value = room.status;

            // Display current image if exists
            const imageDiv = document.getElementById('edit_current_image');
            if (room.image_path) {
                imageDiv.innerHTML = '<img src="../' + room.image_path + '" alt="Current room image" style="max-width: 200px; max-height: 150px; border-radius: 5px; border: 2px solid #0f3460;">';
            } else {
                imageDiv.innerHTML = '<span style="color: #888;">No image uploaded</span>';
            }

            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function confirmDelete(id) {
            if (confirm('Are you sure you want to delete this room?')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('addModal')) {
                closeAddModal();
            }
            if (event.target == document.getElementById('editModal')) {
                closeEditModal();
            }
        }
    </script>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
