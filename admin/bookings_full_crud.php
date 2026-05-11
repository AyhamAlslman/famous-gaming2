<?php
require_once 'auth_check.php';
include '../includes/config.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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

            $query = "INSERT INTO bookings (room_id, customer_name, phone, booking_date, start_time, hours, total_price, status) VALUES ($room_id, '$customer_name', '$phone', '$booking_date', '$start_time', $hours, $total_price, '$status')";
            if (mysqli_query($conn, $query)) {
                $success_message = 'Booking added successfully';
            } else {
                $error_message = 'Error adding booking';
            }
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

            $query = "UPDATE bookings SET room_id = $room_id, customer_name = '$customer_name', phone = '$phone', booking_date = '$booking_date', start_time = '$start_time', hours = $hours, total_price = $total_price, status = '$status' WHERE id = $id";
            if (mysqli_query($conn, $query)) {
                $success_message = 'Booking updated successfully';
            } else {
                $error_message = 'Error updating booking';
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = intval($_POST['id']);

            $query = "DELETE FROM bookings WHERE id = $id";
            if (mysqli_query($conn, $query)) {
                $success_message = 'Booking deleted successfully';
            } else {
                $error_message = 'Error deleting booking';
            }
        }
    }
}

$bookings = mysqli_query($conn, "SELECT b.*, r.room_name FROM bookings b LEFT JOIN rooms r ON b.room_id = r.id ORDER BY b.id DESC");
$rooms = mysqli_query($conn, "SELECT id, room_name FROM rooms ORDER BY room_name");
$rooms_for_edit = mysqli_query($conn, "SELECT id, room_name FROM rooms ORDER BY room_name");

$page_title = 'Bookings Management';
$active_page = 'bookings';
include 'includes/header.php';
?>

    <div class="content">
        <div class="container">
            <div class="page-header">
                <h1>Bookings Management</h1>
                <button class="btn" onclick="openAddModal()">Add Booking</button>
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
                            <th>Customer Name</th>
                            <th>Phone</th>
                            <th>Room</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Duration (hours)</th>
                            <th>Status</th>
                            <th>Actions</th>
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
                                    <?php echo $booking['status']; ?>
                                </span>
                            </td>
                            <td>
                                <a href="booking_details.php?id=<?php echo $booking['id']; ?>" class="btn btn-small btn-info">View Details</a>
                                <button class="btn btn-small btn-success" onclick='openEditModal(<?php echo json_encode($booking); ?>)'>Edit</button>
                                <button class="btn btn-small btn-danger" onclick="confirmDelete(<?php echo $booking['id']; ?>)">Delete</button>
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
            <h2 style="margin-bottom: 1.5rem; color: #fff;">Add Booking</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label>Room</label>
                    <select name="room_id" required>
                        <option value="">Select Room</option>
                        <?php while ($room = mysqli_fetch_assoc($rooms)): ?>
                        <option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['room_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Customer Name</label>
                    <input type="text" name="customer_name" required>
                </div>

                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" required>
                </div>

                <div class="form-group">
                    <label>Booking Date</label>
                    <input type="date" name="booking_date" required>
                </div>

                <div class="form-group">
                    <label>Start Time</label>
                    <input type="time" name="start_time" required>
                </div>

                <div class="form-group">
                    <label>Duration (hours)</label>
                    <input type="number" name="hours" min="1" required>
                </div>

                <div class="form-group">
                    <label>Total Price (JOD)</label>
                    <input type="number" step="0.01" name="total_price" required>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" required>
                        <option value="Pending">Pending</option>
                        <option value="Confirmed">Confirmed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn" style="width: 100%;">Add Booking</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2 style="margin-bottom: 1.5rem; color: #fff;">Edit Booking</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">

                <div class="form-group">
                    <label>Room</label>
                    <select name="room_id" id="edit_room_id" required>
                        <option value="">Select Room</option>
                        <?php while ($room = mysqli_fetch_assoc($rooms_for_edit)): ?>
                        <option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['room_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Customer Name</label>
                    <input type="text" name="customer_name" id="edit_customer_name" required>
                </div>

                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" id="edit_phone" required>
                </div>

                <div class="form-group">
                    <label>Booking Date</label>
                    <input type="date" name="booking_date" id="edit_booking_date" required>
                </div>

                <div class="form-group">
                    <label>Start Time</label>
                    <input type="time" name="start_time" id="edit_start_time" required>
                </div>

                <div class="form-group">
                    <label>Duration (hours)</label>
                    <input type="number" name="hours" id="edit_hours" min="1" required>
                </div>

                <div class="form-group">
                    <label>Total Price (JOD)</label>
                    <input type="number" step="0.01" name="total_price" id="edit_total_price" required>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status" required>
                        <option value="Pending">Pending</option>
                        <option value="Confirmed">Confirmed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn" style="width: 100%;">Update Booking</button>
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
            if (confirm('Are you sure you want to delete this booking?')) {
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
