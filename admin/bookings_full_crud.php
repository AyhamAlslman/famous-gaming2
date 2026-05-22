<?php
require_once 'auth_check.php';
include '../includes/config.php';

ensure_user_auth_schema($conn);

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    admin_require_csrf();

    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $room_id = intval($_POST['room_id']);
            $customer_name = trim($_POST['customer_name']);
            $phone = trim($_POST['phone']);
            $booking_date = $_POST['booking_date'];
            $start_time = $_POST['start_time'];
            $hours = intval($_POST['hours']);
            $total_price = floatval($_POST['total_price']);
            $status = $_POST['status'];
            $booking_code = generate_booking_code();

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO bookings (booking_code, room_id, customer_name, phone, booking_date, start_time, hours, total_price, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt, "sissssids", $booking_code, $room_id, $customer_name, $phone, $booking_date, $start_time, $hours, $total_price, $status);

            if (mysqli_stmt_execute($stmt)) {
                $booking_id = mysqli_insert_id($conn);
                create_admin_notification(
                    $conn,
                    'booking_created',
                    'New booking created',
                    $customer_name . ' was added to bookings for ' . $booking_date . ' at ' . $start_time . '.',
                    'bookings',
                    $booking_id,
                    'booking_details.php?id=' . $booking_id
                );
                if ($status !== 'Cancelled') {
                    create_admin_notification(
                        $conn,
                        'payment_pending',
                        'Payment still pending',
                        'Booking #' . $booking_id . ' is waiting for payment.',
                        'bookings',
                        $booking_id,
                        'booking_details.php?id=' . $booking_id
                    );
                }
                $success_message = 'Booking added successfully';
            } else {
                $error_message = 'Error adding booking';
            }
            mysqli_stmt_close($stmt);
        } elseif ($_POST['action'] == 'edit') {
            $id = intval($_POST['id']);
            $room_id = intval($_POST['room_id']);
            $customer_name = trim($_POST['customer_name']);
            $phone = trim($_POST['phone']);
            $booking_date = $_POST['booking_date'];
            $start_time = $_POST['start_time'];
            $hours = intval($_POST['hours']);
            $total_price = floatval($_POST['total_price']);
            $status = $_POST['status'];

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE bookings
                 SET room_id = ?, customer_name = ?, phone = ?, booking_date = ?, start_time = ?, hours = ?, total_price = ?, status = ?
                 WHERE id = ?"
            );
            mysqli_stmt_bind_param($stmt, "issssidsi", $room_id, $customer_name, $phone, $booking_date, $start_time, $hours, $total_price, $status, $id);

            if (mysqli_stmt_execute($stmt)) {
                $success_message = 'Booking updated successfully';
            } else {
                $error_message = 'Error updating booking';
            }
            mysqli_stmt_close($stmt);
        } elseif ($_POST['action'] == 'delete') {
            $id = intval($_POST['id']);

            $stmt = mysqli_prepare($conn, "DELETE FROM bookings WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);

            if (mysqli_stmt_execute($stmt)) {
                $success_message = 'Booking deleted successfully';
            } else {
                $error_message = 'Error deleting booking';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

$bookings = mysqli_query($conn, "SELECT b.*, r.room_name FROM bookings b LEFT JOIN rooms r ON b.room_id = r.id ORDER BY b.id DESC");
$rooms = mysqli_query($conn, "SELECT id, room_name FROM rooms ORDER BY room_name");
$rooms_for_edit = mysqli_query($conn, "SELECT id, room_name FROM rooms ORDER BY room_name");

$page_title = t('admin_bookings_management');
$active_page = 'bookings';
include 'includes/header.php';
?>

    <div class="content">
        <div class="container">
            <div class="page-header">
                <h1><?php echo t('admin_bookings_management'); ?></h1>
                <button class="btn" onclick="openAddModal()"><?php echo t('admin_add_booking'); ?></button>
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
                            <th><?php echo t('admin_field_id'); ?></th>
                            <th><?php echo t('admin_field_customer_name'); ?></th>
                            <th><?php echo t('admin_field_phone'); ?></th>
                            <th><?php echo t('admin_field_room'); ?></th>
                            <th><?php echo t('admin_field_date'); ?></th>
                            <th><?php echo t('admin_field_time'); ?></th>
                            <th><?php echo t('admin_field_duration'); ?></th>
                            <th><?php echo t('admin_field_status'); ?></th>
                            <th><?php echo t('admin_field_actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($booking = mysqli_fetch_assoc($bookings)): ?>
                        <tr>
                            <td><?php echo $booking['id']; ?></td>
                            <td><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['phone']); ?></td>
                            <td><?php echo htmlspecialchars($booking['room_name']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($booking['booking_date'])); ?></td>
                            <td><?php echo date('h:i A', strtotime($booking['start_time'])); ?></td>
                            <td><?php echo $booking['hours']; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                                    <?php echo htmlspecialchars(t('status_' . strtolower($booking['status']), [], $booking['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <a href="booking_details.php?id=<?php echo $booking['id']; ?>" class="btn btn-small btn-info"><?php echo t('admin_action_view_details'); ?></a>
                                <button class="btn btn-small btn-success" onclick='openEditModal(<?php echo json_encode($booking); ?>)'><?php echo t('admin_action_edit'); ?></button>
                                <button class="btn btn-small btn-danger" onclick="confirmDelete(<?php echo $booking['id']; ?>)"><?php echo t('admin_action_delete'); ?></button>
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
            <h2 style="margin-bottom: 1.5rem; color: #fff;"><?php echo t('admin_add_booking'); ?></h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                <?php echo admin_csrf_input(); ?>

                <div class="form-group">
                    <label><?php echo t('admin_field_room'); ?></label>
                    <select name="room_id" required>
                        <option value=""><?php echo t('booking_form_choose_room'); ?></option>
                        <?php while ($room = mysqli_fetch_assoc($rooms)): ?>
                        <option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['room_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_field_customer_name'); ?></label>
                    <input type="text" name="customer_name" required>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_field_phone'); ?></label>
                    <input type="text" name="phone" required>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_field_date'); ?></label>
                    <input type="date" name="booking_date" required>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_field_time'); ?></label>
                    <input type="time" name="start_time" required>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_field_duration'); ?></label>
                    <input type="number" name="hours" min="1" required>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_field_price'); ?> (JOD)</label>
                    <input type="number" step="0.50" name="total_price" required>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_field_status'); ?></label>
                    <select name="status" required>
                        <option value="Pending"><?php echo t('status_pending'); ?></option>
                        <option value="Confirmed"><?php echo t('status_confirmed'); ?></option>
                        <option value="Cancelled"><?php echo t('status_cancelled'); ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn" style="width: 100%;"><?php echo t('admin_add_booking'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2 style="margin-bottom: 1.5rem; color: #fff;"><?php echo t('admin_action_edit'); ?> <?php echo t('admin_add_booking'); ?></h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <?php echo admin_csrf_input(); ?>

                <div class="form-group">
                    <label><?php echo t('admin_field_room'); ?></label>
                    <select name="room_id" id="edit_room_id" required>
                        <option value=""><?php echo t('booking_form_choose_room'); ?></option>
                        <?php while ($room = mysqli_fetch_assoc($rooms_for_edit)): ?>
                        <option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['room_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_field_customer_name'); ?></label>
                    <input type="text" name="customer_name" id="edit_customer_name" required>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_field_phone'); ?></label>
                    <input type="text" name="phone" id="edit_phone" required>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_field_date'); ?></label>
                    <input type="date" name="booking_date" id="edit_booking_date" required>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_field_time'); ?></label>
                    <input type="time" name="start_time" id="edit_start_time" required>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_field_duration'); ?></label>
                    <input type="number" name="hours" id="edit_hours" min="1" required>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_field_price'); ?> (JOD)</label>
                    <input type="number" step="0.50" name="total_price" id="edit_total_price" required>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_field_status'); ?></label>
                    <select name="status" id="edit_status" required>
                        <option value="Pending"><?php echo t('status_pending'); ?></option>
                        <option value="Confirmed"><?php echo t('status_confirmed'); ?></option>
                        <option value="Cancelled"><?php echo t('status_cancelled'); ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn" style="width: 100%;"><?php echo t('admin_action_update'); ?></button>
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
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        function openEditModal(booking) {
            document.getElementById('edit_id').value = booking.id;
            document.getElementById('edit_room_id').value = booking.room_id;
            document.getElementById('edit_customer_name').value = booking.customer_name;
            document.getElementById('edit_phone').value = booking.phone;
            document.getElementById('edit_booking_date').value = booking.booking_date;
            document.getElementById('edit_start_time').value = booking.start_time;
            document.getElementById('edit_hours').value = booking.hours;
            document.getElementById('edit_total_price').value = booking.total_price;
            document.getElementById('edit_status').value = booking.status;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function confirmDelete(id) {
            showAdminConfirm('<?php echo addslashes(t('admin_delete_confirm')); ?>', function() {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            });
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
