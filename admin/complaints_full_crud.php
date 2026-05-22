<?php
require_once 'auth_check.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'delete') {
        $id = intval($_POST['id']);

        $stmt = mysqli_prepare($conn, "DELETE FROM complaints WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);

        if (mysqli_stmt_execute($stmt)) {
            $success_message = 'Complaint deleted successfully';
        } else {
            $error_message = 'Error deleting complaint';
        }
        mysqli_stmt_close($stmt);
    }
}

$complaints = mysqli_query(
    $conn,
    "SELECT c.*, u.full_name AS site_user_name, u.email AS site_user_email
     FROM complaints c
     LEFT JOIN site_users u ON c.user_id = u.id
     ORDER BY c.id DESC"
);

$page_title = t('admin_complaints_management');
$active_page = 'complaints';
include 'includes/header.php';
?>

    <div class="content">
        <div class="container">
            <div class="page-header">
                <h1><?php echo t('admin_complaints_management'); ?></h1>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="table-container">
                <?php if (mysqli_num_rows($complaints) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th><?php echo t('admin_field_id'); ?></th>
                            <th><?php echo t('admin_field_customer_name'); ?></th>
                            <th><?php echo t('admin_field_phone'); ?></th>
                            <th><?php echo t('nav_account'); ?></th>
                            <th><?php echo t('admin_field_message'); ?></th>
                            <th><?php echo t('admin_field_submitted'); ?></th>
                            <th><?php echo t('admin_field_actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($complaint = mysqli_fetch_assoc($complaints)): ?>
                        <tr>
                            <td><?php echo $complaint['id']; ?></td>
                            <td><?php echo htmlspecialchars($complaint['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($complaint['phone'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if (!empty($complaint['site_user_email'])): ?>
                                    <strong><?php echo htmlspecialchars($complaint['site_user_name']); ?></strong><br>
                                    <span class="admin-muted"><?php echo htmlspecialchars($complaint['site_user_email']); ?></span>
                                <?php else: ?>
                                    <span class="admin-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="complaint-text">
                                    <?php echo htmlspecialchars($complaint['message']); ?>
                                </div>
                            </td>
                            <td><?php echo date('Y-m-d h:i A', strtotime($complaint['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-small btn-info" onclick='viewComplaint(<?php echo json_encode($complaint); ?>)'><?php echo t('common_view'); ?></button>
                                <button class="btn btn-small btn-danger" onclick="confirmDelete(<?php echo $complaint['id']; ?>)"><?php echo t('admin_action_delete'); ?></button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-data"><?php echo t('admin_no_complaints'); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="viewModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeViewModal()">&times;</span>
            <h2 style="margin-bottom: 1.5rem; color: #fff;"><?php echo t('admin_complaint_details'); ?></h2>
            <div class="complaint-details">
                <p><strong>ID:</strong> <span id="view_id"></span></p>
                <p><strong><?php echo t('admin_field_customer_name'); ?>:</strong> <span id="view_customer_name"></span></p>
                <p><strong><?php echo t('admin_field_phone'); ?>:</strong> <span id="view_phone"></span></p>
                <p><strong><?php echo t('nav_account'); ?>:</strong> <span id="view_account"></span></p>
                <p><strong><?php echo t('admin_field_submitted'); ?>:</strong> <span id="view_date"></span></p>
                <h3><?php echo t('admin_field_message'); ?>:</h3>
                <p id="view_message" style="line-height: 1.8; white-space: pre-wrap;"></p>
            </div>
        </div>
    </div>

    <form id="deleteForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id">
        <?php echo admin_csrf_input(); ?>
    </form>

    <script>
        function viewComplaint(complaint) {
            document.getElementById('view_id').textContent = complaint.id;
            document.getElementById('view_customer_name').textContent = complaint.customer_name;
            document.getElementById('view_phone').textContent = complaint.phone || 'N/A';
            document.getElementById('view_account').textContent = complaint.site_user_email ? (complaint.site_user_name + ' - ' + complaint.site_user_email) : 'N/A';
            document.getElementById('view_message').textContent = complaint.message;
            document.getElementById('view_date').textContent = new Date(complaint.created_at).toLocaleString();
            document.getElementById('viewModal').style.display = 'block';
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }

        function confirmDelete(id) {
            showAdminConfirm('<?php echo addslashes(t('admin_delete_confirm')); ?>', function() {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            });
        }

    </script>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
