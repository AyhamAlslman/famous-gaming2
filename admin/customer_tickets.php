<?php
require_once 'auth_check.php';
include '../includes/config.php';
require_once '../includes/functions.php';

ensure_booking_confirmation_schema($conn);

$page_title = 'Customer Tickets';
$active_page = 'customer_tickets';

$tickets_query = "SELECT b.*, r.room_name, r.room_type
                  FROM bookings b
                  LEFT JOIN rooms r ON b.room_id = r.id
                  WHERE b.booking_code IS NOT NULL OR b.customer_session_token IS NOT NULL
                  ORDER BY b.booking_date DESC, b.start_time DESC, b.id DESC";
$tickets = mysqli_query($conn, $tickets_query);

include 'includes/header.php';
?>

    <div class="content">
        <div class="container">
            <div class="page-header">
                <div>
                    <h1>Customer Tickets</h1>
                    <p class="admin-page-subtitle">Customer-facing booking confirmations and barcode proof.</p>
                </div>
                <a href="bookings_full_crud.php" class="btn btn-secondary">Manage Bookings</a>
            </div>

            <div class="table-container">
                <?php if ($tickets && mysqli_num_rows($tickets) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Customer</th>
                                <th>Device / Session</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($ticket = mysqli_fetch_assoc($tickets)): ?>
                                <tr>
                                    <td>
                                        <div class="admin-ticket-barcode" aria-label="Customer ticket barcode">
                                            <?php echo render_booking_barcode($ticket['booking_code'] ?: $ticket['id']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($ticket['customer_name']); ?></strong><br>
                                        <span class="admin-muted"><?php echo htmlspecialchars($ticket['phone']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($ticket['room_name']); ?> - <?php echo htmlspecialchars($ticket['room_type']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($ticket['booking_date'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($ticket['start_time'])); ?> for <?php echo (int)$ticket['hours']; ?> hour<?php echo (int)$ticket['hours'] === 1 ? '' : 's'; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(htmlspecialchars($ticket['status'])); ?>">
                                            <?php echo htmlspecialchars($ticket['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="booking_details.php?id=<?php echo (int)$ticket['id']; ?>" class="btn btn-small">View Details</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">No customer tickets yet</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
