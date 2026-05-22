<?php
require_once 'auth_check.php';

if (!isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);
            $full_name = trim($_POST['full_name']);
            $role = $_POST['role'];
            $status = $_POST['status'];

            $check_stmt = mysqli_prepare($conn, "SELECT id FROM admins WHERE username = ?");
            mysqli_stmt_bind_param($check_stmt, "s", $username);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);

            if (mysqli_num_rows($check_result) > 0) {
                $error_message = 'Username already exists';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "INSERT INTO admins (username, password, full_name, role, status) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "sssss", $username, $hashed_password, $full_name, $role, $status);

                if (mysqli_stmt_execute($stmt)) {
                    $success_message = 'Employee added successfully';
                } else {
                    $error_message = 'Error adding employee';
                }
                mysqli_stmt_close($stmt);
            }
            mysqli_stmt_close($check_stmt);
        } elseif ($_POST['action'] == 'edit') {
            $id = intval($_POST['id']);
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);
            $full_name = trim($_POST['full_name']);
            $role = $_POST['role'];
            $status = $_POST['status'];

            $check_stmt = mysqli_prepare($conn, "SELECT id FROM admins WHERE username = ? AND id != ?");
            mysqli_stmt_bind_param($check_stmt, "si", $username, $id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);

            if (mysqli_num_rows($check_result) > 0) {
                $error_message = 'Username already exists';
            } else {
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE admins SET username = ?, password = ?, full_name = ?, role = ?, status = ? WHERE id = ?"
                    );
                    mysqli_stmt_bind_param($stmt, "sssssi", $username, $hashed_password, $full_name, $role, $status, $id);
                } else {
                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE admins SET username = ?, full_name = ?, role = ?, status = ? WHERE id = ?"
                    );
                    mysqli_stmt_bind_param($stmt, "ssssi", $username, $full_name, $role, $status, $id);
                }

                if (mysqli_stmt_execute($stmt)) {
                    $success_message = 'Employee updated successfully';
                } else {
                    $error_message = 'Error updating employee';
                }
                mysqli_stmt_close($stmt);
            }
            mysqli_stmt_close($check_stmt);
        } elseif ($_POST['action'] == 'delete') {
            $id = intval($_POST['id']);

            if ($id == $_SESSION['admin_id']) {
                $error_message = 'You cannot delete your own account';
            } else {
                $stmt = mysqli_prepare($conn, "DELETE FROM admins WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $id);

                if (mysqli_stmt_execute($stmt)) {
                    $success_message = 'Employee deleted successfully';
                } else {
                    $error_message = 'Error deleting employee';
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

$employees = mysqli_query($conn, "SELECT * FROM admins ORDER BY id DESC");

$page_title = t('admin_employees_management');
$active_page = 'employees';
include 'includes/header.php';
?>

    <div class="content">
        <div class="container">
            <div class="page-header">
                <h1><?php echo t('admin_employees_management'); ?></h1>
                <button class="btn" onclick="openEmployeeModal()"><?php echo t('admin_action_add'); ?> <?php echo t('admin_role_employee'); ?></button>
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
                            <th><?php echo t('admin_field_username'); ?></th>
                            <th><?php echo t('auth_full_name'); ?></th>
                            <th><?php echo t('admin_field_role'); ?></th>
                            <th><?php echo t('admin_field_status'); ?></th>
                            <th><?php echo t('admin_field_actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($employee = mysqli_fetch_assoc($employees)): ?>
                        <tr>
                            <td><?php echo $employee['id']; ?></td>
                            <td><?php echo htmlspecialchars($employee['username']); ?></td>
                            <td><?php echo htmlspecialchars($employee['full_name']); ?></td>
                            <td><span class="role-badge"><?php echo t('admin_role_' . strtolower($employee['role']), [], ucfirst($employee['role'])); ?></span></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($employee['status']); ?>">
                                    <?php echo htmlspecialchars(t('admin_status_' . strtolower($employee['status']), [], $employee['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-small btn-success" onclick='openEmployeeModal(<?php echo json_encode($employee); ?>)'><?php echo t('admin_action_edit'); ?></button>
                                <?php if ($employee['id'] != $_SESSION['admin_id']): ?>
                                <button class="btn btn-small btn-danger" onclick="confirmDelete(<?php echo $employee['id']; ?>)"><?php echo t('admin_action_delete'); ?></button>
                                <?php endif; ?>
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
            <h2 id="formModalTitle" style="margin-bottom: 1.5rem; color: #fff;"><?php echo t('admin_action_add'); ?> <?php echo t('admin_role_employee'); ?></h2>
            <form method="POST" action="">
                <input type="hidden" name="action" id="form_action" value="add">
                <input type="hidden" name="id" id="form_id">
                <?php echo admin_csrf_input(); ?>

                <div class="form-group">
                    <label><?php echo t('admin_field_username'); ?></label>
                    <input type="text" name="username" id="form_username" required>
                </div>

                <div class="form-group">
                    <label id="form_password_label"><?php echo t('auth_password'); ?></label>
                    <input type="password" name="password" id="form_password" required>
                </div>

                <div class="form-group">
                    <label><?php echo t('auth_full_name'); ?></label>
                    <input type="text" name="full_name" id="form_full_name" required>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_field_role'); ?></label>
                    <select name="role" id="form_role" required>
                        <option value="admin"><?php echo t('admin_role_admin'); ?></option>
                        <option value="employee"><?php echo t('admin_role_employee'); ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <label><?php echo t('admin_field_status'); ?></label>
                    <select name="status" id="form_status" required>
                        <option value="Active"><?php echo t('admin_status_active'); ?></option>
                        <option value="Inactive"><?php echo t('admin_status_inactive'); ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn" id="form_submit" style="width: 100%;"><?php echo t('admin_action_add'); ?></button>
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
        function openEmployeeModal(employee) {
            const isEdit = !!employee;
            document.getElementById('form_action').value = isEdit ? 'edit' : 'add';
            document.getElementById('form_id').value = isEdit ? employee.id : '';
            document.getElementById('form_username').value = isEdit ? employee.username : '';
            document.getElementById('form_full_name').value = isEdit ? employee.full_name : '';
            document.getElementById('form_role').value = isEdit ? employee.role : 'employee';
            document.getElementById('form_status').value = isEdit ? employee.status : 'Active';
            document.getElementById('form_password').value = '';
            document.getElementById('form_password').required = !isEdit;
            document.getElementById('form_password_label').textContent = isEdit ? '<?php echo addslashes(t('admin_password_optional')); ?>' : '<?php echo addslashes(t('auth_password')); ?>';
            document.getElementById('formModalTitle').textContent = isEdit ? '<?php echo addslashes(t('admin_action_edit') . ' ' . t('admin_role_employee')); ?>' : '<?php echo addslashes(t('admin_action_add') . ' ' . t('admin_role_employee')); ?>';
            document.getElementById('form_submit').textContent = isEdit ? '<?php echo addslashes(t('admin_action_update')); ?>' : '<?php echo addslashes(t('admin_action_add')); ?>';
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

    </script>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
