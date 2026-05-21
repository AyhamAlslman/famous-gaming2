<?php
require_once 'auth_check.php';
include '../includes/config.php';
require_once '../includes/functions.php';

ensure_booking_confirmation_schema($conn);

$page_title = t('admin_customer_tickets');
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
                    <h1><?php echo t('admin_customer_tickets'); ?></h1>
                    <p class="admin-page-subtitle"><?php echo t('admin_customer_tickets_subtitle'); ?></p>
                </div>
                <a href="bookings_full_crud.php" class="btn btn-secondary"><?php echo t('admin_manage_bookings'); ?></a>
            </div>

            <div class="table-container">
                <?php if ($tickets && mysqli_num_rows($tickets) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th><?php echo t('booking_ticket_label'); ?></th>
                                <th><?php echo t('common_customer'); ?></th>
                                <th><?php echo t('booking_device_session'); ?></th>
                                <th><?php echo t('common_date'); ?></th>
                                <th><?php echo t('common_time'); ?></th>
                                <th><?php echo t('admin_field_status'); ?></th>
                                <th><?php echo t('admin_field_actions'); ?></th>
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
                                    <td><?php echo date('h:i A', strtotime($ticket['start_time'])); ?> - <?php echo translated_hours_label($ticket['hours']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(htmlspecialchars($ticket['status'])); ?>">
                                            <?php echo htmlspecialchars(t('status_' . strtolower($ticket['status']), [], $ticket['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="booking_details.php?id=<?php echo (int)$ticket['id']; ?>" class="btn btn-small"><?php echo t('admin_action_view_details'); ?></a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data"><?php echo t('admin_no_customer_tickets'); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
