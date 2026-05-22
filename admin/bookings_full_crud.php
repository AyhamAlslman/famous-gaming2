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
$rooms_status_query = "SELECT
        r.id,
        r.room_name,
        r.status,
        CASE
            WHEN active_booking.id IS NOT NULL THEN 'Busy'
            ELSE r.status
        END AS current_status
    FROM rooms r
    LEFT JOIN bookings active_booking
        ON active_booking.room_id = r.id
        AND active_booking.status IN ('Pending', 'Confirmed')
        AND active_booking.booking_date = CURDATE()
        AND CURTIME() >= active_booking.start_time
        AND CURTIME() < ADDTIME(active_booking.start_time, SEC_TO_TIME(active_booking.hours * 3600))
    ORDER BY FIELD(current_status, 'Available', 'Busy'), r.room_name";
$rooms = mysqli_query($conn, $rooms_status_query);

$page_title = t('admin_bookings_management');
$active_page = 'bookings';
include 'includes/header.php';
?>

    <div class="content">
        <div class="container">
            <div class="page-header">
                <h1><?php echo t('admin_bookings_management'); ?></h1>
                <button class="btn" onclick="openBookingModal()"><?php echo t('admin_add_booking'); ?></button>
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
                                <button class="btn btn-small btn-success" onclick='openBookingModal(<?php echo json_encode($booking); ?>)'><?php echo t('admin_action_edit'); ?></button>
                                <button class="btn btn-small btn-danger" onclick="confirmDelete(<?php echo $booking['id']; ?>)"><?php echo t('admin_action_delete'); ?></button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="formModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeFormModal()">&times;</span>
            <h2 id="formModalTitle" style="margin-bottom: 1.5rem; color: #fff;"><?php echo t('admin_add_booking'); ?></h2>
            <form method="POST" action="">
                <input type="hidden" name="action" id="form_action" value="add">
                <input type="hidden" name="id" id="form_id">
                <?php echo admin_csrf_input(); ?>

                <div class="form-group">
                    <label><?php echo t('admin_field_room'); ?></label>
                    <select name="room_id" id="form_room_id" required>
                        <option value=""><?php echo t('booking_form_choose_room'); ?></option>
                        <?php while ($room = mysqli_fetch_assoc($rooms)): ?>
                        <?php $room_status_label = t('status_' . strtolower($room['current_status']), [], $room['current_status']); ?>
                        <option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['room_name'] . ' - ' . $room_status_label); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_field_customer_name'); ?></label>
                    <input type="text" name="customer_name" id="form_customer_name" required>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_field_phone'); ?></label>
                    <input type="text" name="phone" id="form_phone" required>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_field_date'); ?></label>
                    <input type="date" name="booking_date" id="form_booking_date" required>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_field_time'); ?></label>
                    <input type="time" name="start_time" id="form_start_time" required>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_field_duration'); ?></label>
                    <input type="number" name="hours" id="form_hours" min="1" required>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_field_price'); ?> (JOD)</label>
                    <input type="number" step="0.50" name="total_price" id="form_total_price" required>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_field_status'); ?></label>
                    <select name="status" id="form_status" required>
                        <option value="Pending"><?php echo t('status_pending'); ?></option>
                        <option value="Confirmed"><?php echo t('status_confirmed'); ?></option>
                        <option value="Cancelled"><?php echo t('status_cancelled'); ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn" id="form_submit" style="width: 100%;"><?php echo t('admin_add_booking'); ?></button>
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
        function openBookingModal(booking) {
            const isEdit = !!booking;
            document.getElementById('form_action').value = isEdit ? 'edit' : 'add';
            document.getElementById('form_id').value = isEdit ? booking.id : '';
            document.getElementById('form_room_id').value = isEdit ? booking.room_id : '';
            document.getElementById('form_customer_name').value = isEdit ? booking.customer_name : '';
            document.getElementById('form_phone').value = isEdit ? booking.phone : '';
            document.getElementById('form_booking_date').value = isEdit ? booking.booking_date : '';
            document.getElementById('form_start_time').value = isEdit ? booking.start_time : '';
            document.getElementById('form_hours').value = isEdit ? booking.hours : '';
            document.getElementById('form_total_price').value = isEdit ? booking.total_price : '';
            document.getElementById('form_status').value = isEdit ? booking.status : 'Pending';
            document.getElementById('formModalTitle').textContent = isEdit ? '<?php echo addslashes(t('admin_action_edit') . ' ' . t('admin_add_booking')); ?>' : '<?php echo addslashes(t('admin_add_booking')); ?>';
            document.getElementById('form_submit').textContent = isEdit ? '<?php echo addslashes(t('admin_action_update')); ?>' : '<?php echo addslashes(t('admin_add_booking')); ?>';
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

        window.onclick = function(event) {
            if (event.target == document.getElementById('formModal')) {
                closeFormModal();
            }
        }
    </script>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
