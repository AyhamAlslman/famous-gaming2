<?php
require_once 'auth_check.php';
include '../includes/config.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'delete') {
        $id = intval($_POST['id']);

        $query = "DELETE FROM complaints WHERE id = $id";
        if (mysqli_query($conn, $query)) {
            $success_message = 'Complaint deleted successfully';
        } else {
            $error_message = 'Error deleting complaint';
        }
    }
}

$complaints = mysqli_query($conn, "SELECT * FROM complaints ORDER BY id DESC");

$page_title = 'Complaints Management';
$active_page = 'complaints';
include 'includes/header.php';
?>

    <div class="content">
        <div class="container">
            <div class="page-header">
                <h1>Complaints Management</h1>
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
                            <th>ID</th>
                            <th>Customer Name</th>
                            <th>Phone</th>
                            <th>Message</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($complaint = mysqli_fetch_assoc($complaints)): ?>
                        <tr>
                            <td><?php echo $complaint['id']; ?></td>
                            <td><?php echo htmlspecialchars($complaint['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($complaint['phone'] ?? 'N/A'); ?></td>
                            <td>
                                <div class="complaint-text">
                                    <?php echo htmlspecialchars($complaint['message']); ?>
                                </div>
                            </td>
                            <td><?php echo date('Y-m-d h:i A', strtotime($complaint['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-small btn-info" onclick='viewComplaint(<?php echo json_encode($complaint); ?>)'>View</button>
                                <button class="btn btn-small btn-danger" onclick="confirmDelete(<?php echo $complaint['id']; ?>)">Delete</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-data">No complaints found</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="viewModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeViewModal()">&times;</span>
            <h2 style="margin-bottom: 1.5rem; color: #fff;">Complaint Details</h2>
            <div class="complaint-details">
                <p><strong>ID:</strong> <span id="view_id"></span></p>
                <p><strong>Customer Name:</strong> <span id="view_customer_name"></span></p>
                <p><strong>Phone:</strong> <span id="view_phone"></span></p>
                <p><strong>Submitted:</strong> <span id="view_date"></span></p>
                <h3>Message:</h3>
                <p id="view_message" style="line-height: 1.8; white-space: pre-wrap;"></p>
            </div>
        </div>
    </div>

    <form id="deleteForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id">
    </form>

    <script>
        function viewComplaint(complaint) {
            document.getElementById('view_id').textContent = complaint.id;
            document.getElementById('view_customer_name').textContent = complaint.customer_name;
            document.getElementById('view_phone').textContent = complaint.phone || 'N/A';
            document.getElementById('view_message').textContent = complaint.message;
            document.getElementById('view_date').textContent = new Date(complaint.created_at).toLocaleString();
            document.getElementById('viewModal').style.display = 'block';
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }

        function confirmDelete(id) {
            if (confirm('Are you sure you want to delete this complaint?')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('viewModal')) {
                closeViewModal();
            }
        }
    </script>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
