<?php
require_once 'auth_check.php';

if (!isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

include '../includes/config.php';

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

            $check_query = "SELECT id FROM admins WHERE username = '$username'";
            $check_result = mysqli_query($conn, $check_query);

            if (mysqli_num_rows($check_result) > 0) {
                $error_message = 'Username already exists';
            } else {
                $query = "INSERT INTO admins (username, password, full_name, role, status) VALUES ('$username', '$password', '$full_name', '$role', '$status')";
                if (mysqli_query($conn, $query)) {
                    $success_message = 'Employee added successfully';
                } else {
                    $error_message = 'Error adding employee';
                }
            }
        } elseif ($_POST['action'] == 'edit') {
            $id = $_POST['id'];
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);
            $full_name = trim($_POST['full_name']);
            $role = $_POST['role'];
            $status = $_POST['status'];

            $check_query = "SELECT id FROM admins WHERE username = '$username' AND id != $id";
            $check_result = mysqli_query($conn, $check_query);

            if (mysqli_num_rows($check_result) > 0) {
                $error_message = 'Username already exists';
            } else {
                if (!empty($password)) {
                    $query = "UPDATE admins SET username = '$username', password = '$password', full_name = '$full_name', role = '$role', status = '$status' WHERE id = $id";
                } else {
                    $query = "UPDATE admins SET username = '$username', full_name = '$full_name', role = '$role', status = '$status' WHERE id = $id";
                }

                if (mysqli_query($conn, $query)) {
                    $success_message = 'Employee updated successfully';
                } else {
                    $error_message = 'Error updating employee';
                }
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];

            if ($id == $_SESSION['admin_id']) {
                $error_message = 'You cannot delete your own account';
            } else {
                $query = "DELETE FROM admins WHERE id = $id";
                if (mysqli_query($conn, $query)) {
                    $success_message = 'Employee deleted successfully';
                } else {
                    $error_message = 'Error deleting employee';
                }
            }
        }
    }
}

$employees = mysqli_query($conn, "SELECT * FROM admins ORDER BY id DESC");

$page_title = 'Employees Management';
$active_page = 'employees';
include 'includes/header.php';
?>

    <div class="content">
        <div class="container">
            <div class="page-header">
                <h1>Employees Management</h1>
                <button class="btn" onclick="openAddModal()">Add Employee</button>
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
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($employee = mysqli_fetch_assoc($employees)): ?>
                        <tr>
                            <td><?php echo $employee['id']; ?></td>
                            <td><?php echo htmlspecialchars($employee['username']); ?></td>
                            <td><?php echo htmlspecialchars($employee['full_name']); ?></td>
                            <td><span class="role-badge"><?php echo ucfirst($employee['role']); ?></span></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($employee['status']); ?>">
                                    <?php echo $employee['status']; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-small btn-success" onclick='openEditModal(<?php echo json_encode($employee); ?>)'>Edit</button>
                                <?php if ($employee['id'] != $_SESSION['admin_id']): ?>
                                <button class="btn btn-small btn-danger" onclick="confirmDelete(<?php echo $employee['id']; ?>)">Delete</button>
                                <?php endif; ?>
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
            <h2 style="margin-bottom: 1.5rem; color: #fff;">Add Employee</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" required>
                </div>

                <div class="form-group">
                    <label>Role</label>
                    <select name="role" required>
                        <option value="admin">Admin</option>
                        <option value="employee">Employee</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn" style="width: 100%;">Add Employee</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2 style="margin-bottom: 1.5rem; color: #fff;">Edit Employee</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" id="edit_username" required>
                </div>

                <div class="form-group">
                    <label>Password (leave empty to keep current)</label>
                    <input type="password" name="password" id="edit_password">
                </div>

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" id="edit_full_name" required>
                </div>

                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="edit_role" required>
                        <option value="admin">Admin</option>
                        <option value="employee">Employee</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn" style="width: 100%;">Update Employee</button>
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

        function openEditModal(employee) {
            document.getElementById('edit_id').value = employee.id;
            document.getElementById('edit_username').value = employee.username;
            document.getElementById('edit_full_name').value = employee.full_name;
            document.getElementById('edit_role').value = employee.role;
            document.getElementById('edit_status').value = employee.status;
            document.getElementById('edit_password').value = '';
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function confirmDelete(id) {
            if (confirm('Are you sure you want to delete this employee?')) {
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
