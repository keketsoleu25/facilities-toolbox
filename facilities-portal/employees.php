<?php

// --------------------------------------------------
// Facilities Toolbox - Employee Management
// --------------------------------------------------
//
// Employee CRUD actions are sent to the ASP.NET Core
// API. PHP remains a presentation layer only.
// --------------------------------------------------

require_once __DIR__ . '/api-client.php';

$employees = [];
$errorMessage = null;
$successMessage = null;

// --------------------------------------------------
// Handle employee commands
// --------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['form_action'] ?? '';

    if ($formAction === 'create') {
        $result = facilitiesApiRequest(
            'POST',
            '/api/employees',
            [
                'employeeId' => trim($_POST['employee_id'] ?? ''),
                'name' => trim($_POST['name'] ?? ''),
                'department' => trim($_POST['department'] ?? ''),
                'role' => trim($_POST['role'] ?? '')
            ]
        );

        if ($result['success']) {
            $successMessage = 'Employee created successfully.';
        } else {
            $errorMessage = $result['message'];
        }
    }

    if ($formAction === 'status') {
        $employeeId = trim($_POST['employee_id'] ?? '');
        $active = ($_POST['active'] ?? '0') === '1';

        $result = facilitiesApiRequest(
            'PATCH',
            '/api/employees/' . rawurlencode($employeeId) . '/status',
            ['active' => $active]
        );

        if ($result['success']) {
            $successMessage = $active
                ? "$employeeId activated."
                : "$employeeId deactivated.";
        } else {
            $errorMessage = $result['message'];
        }
    }
}

// --------------------------------------------------
// Load current employee directory
// --------------------------------------------------

$result = facilitiesApiRequest('GET', '/api/employees');

if ($result['success'] && is_array($result['data'])) {
    $employees = $result['data'];
} else {
    $errorMessage = $errorMessage ?? $result['message'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees - Facilities Toolbox</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px 20px; font-family: Arial, sans-serif; background: #f3f4f6; color: #111827; }
        .container { max-width: 1150px; margin: 0 auto; }
        nav { display: flex; gap: 18px; margin-bottom: 28px; }
        nav a { color: #111827; text-decoration: none; font-weight: 700; }
        .card { background: #fff; border-radius: 14px; padding: 24px; margin-bottom: 24px; box-shadow: 0 8px 28px rgba(17, 24, 39, .06); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; }
        input { width: 100%; padding: 11px; border: 1px solid #d1d5db; border-radius: 8px; }
        button { padding: 10px 14px; border: 0; border-radius: 8px; cursor: pointer; font-weight: 700; }
        .primary { background: #111827; color: white; }
        .activate { background: #dcfce7; }
        .deactivate { background: #fee2e2; }
        .success, .error { padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; }
        .success { background: #dcfce7; }
        .error { background: #fee2e2; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 760px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
<div class="container">
    <nav>
        <a href="index.php">Dashboard</a>
        <a href="employees.php">Employees</a>
    </nav>

    <h1>Employee Management</h1>
    <p class="muted">Manage staff records through the Facilities API.</p>

    <?php if ($successMessage): ?>
        <div class="success"><?= e($successMessage) ?></div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="error"><?= e($errorMessage) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>Add Employee</h2>
        <form method="POST" class="form-grid">
            <input type="hidden" name="form_action" value="create">
            <input type="text" name="employee_id" placeholder="EMP003" required>
            <input type="text" name="name" placeholder="Employee name" required>
            <input type="text" name="department" placeholder="Department">
            <input type="text" name="role" placeholder="Role">
            <button type="submit" class="primary">Add Employee</button>
        </form>
    </div>

    <div class="card">
        <h2>Employee Directory</h2>

        <?php if (!$employees): ?>
            <p>No employees found.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($employees as $employee): ?>
                        <tr>
                            <td><?= e($employee['employeeId'] ?? '') ?></td>
                            <td><?= e($employee['name'] ?? '') ?></td>
                            <td><?= e($employee['department'] ?? '') ?></td>
                            <td><?= e($employee['role'] ?? '') ?></td>
                            <td><?= !empty($employee['active']) ? 'Active' : 'Inactive' ?></td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="form_action" value="status">
                                    <input type="hidden" name="employee_id" value="<?= e($employee['employeeId'] ?? '') ?>">
                                    <input type="hidden" name="active" value="<?= !empty($employee['active']) ? '0' : '1' ?>">
                                    <button type="submit" class="<?= !empty($employee['active']) ? 'deactivate' : 'activate' ?>">
                                        <?= !empty($employee['active']) ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
