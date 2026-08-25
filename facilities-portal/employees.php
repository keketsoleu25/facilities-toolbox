<?php

// --------------------------------------------------
// Facilities Toolbox - Employee Management
// --------------------------------------------------
//
// Employee commands are sent to the ASP.NET Core API.
// PHP remains responsible only for presentation and
// user interaction.
// --------------------------------------------------

require_once __DIR__ . '/api-client.php';

$employees = [];
$errorMessage = null;
$successMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['form_action'] ?? '';

    if ($formAction === 'create') {
        $result = facilitiesApiRequest('POST', '/api/employees', [
            'employeeId' => trim($_POST['employee_id'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'department' => trim($_POST['department'] ?? ''),
            'role' => trim($_POST['role'] ?? '')
        ]);

        $successMessage = $result['success']
            ? 'Employee created successfully.'
            : null;

        $errorMessage = $result['success']
            ? null
            : $result['message'];
    }

    if ($formAction === 'status') {
        $employeeId = trim($_POST['employee_id'] ?? '');
        $active = ($_POST['active'] ?? '0') === '1';

        $result = facilitiesApiRequest(
            'PATCH',
            '/api/employees/' . rawurlencode($employeeId) . '/status',
            ['active' => $active]
        );

        $successMessage = $result['success']
            ? ($active ? "$employeeId activated." : "$employeeId deactivated.")
            : null;

        $errorMessage = $result['success']
            ? null
            : $result['message'];
    }
}

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
    <title>Employees | Facilities Toolbox</title>
    <link rel="stylesheet" href="assets/app.css">
    <script defer src="assets/theme.js"></script>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">FT</div>
            <h1>Facilities Toolbox</h1>
            <p>The Tech Alchemy Lab</p>
        </div>

        <div class="nav-section-label">Operations</div>
        <nav>
            <a class="nav-link" href="index.php">Dashboard</a>
            <a class="nav-link active" href="employees.php">Employees</a>
            <a class="nav-link" href="attendance.php">Attendance</a>
            <a class="nav-link" href="reports.php">Reports</a>
        </nav>

        <div class="nav-section-label">Facilities</div>
        <nav>
            <a class="nav-link" href="structure.php">Structure</a>
            <a class="nav-link" href="shifts.php">Shifts</a>
            <a class="nav-link" href="assets.php">Assets</a>
            <a class="nav-link" href="maintenance.php">Maintenance</a>
        </nav>

        <div class="sidebar-footer">v0.3 Operations Core</div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <p class="eyebrow">Workforce Directory</p>
                <h2 class="page-title">Employee Management</h2>
                <p class="page-subtitle">Manage staff records and employment status through the Facilities API.</p>
            </div>
            <span class="status-pill"><?= count($employees) ?> Records Loaded</span>
        </header>

        <?php if ($successMessage): ?>
            <div class="notice success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="notice error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <section class="panel" style="margin-bottom:18px;">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">Add Employee</h3>
                    <p class="panel-description">Create a workforce record in the Facilities platform.</p>
                </div>
            </div>

            <form method="POST" class="form-grid">
                <input type="hidden" name="form_action" value="create">
                <div class="field"><label>Employee ID</label><input type="text" name="employee_id" placeholder="EMP003" required></div>
                <div class="field"><label>Name</label><input type="text" name="name" placeholder="Employee name" required></div>
                <div class="field"><label>Department</label><input type="text" name="department" placeholder="Department"></div>
                <div class="field"><label>Role</label><input type="text" name="role" placeholder="Role"></div>
                <div style="display:flex;align-items:end;"><button type="submit" class="button primary">Add Employee</button></div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">Employee Directory</h3>
                    <p class="panel-description"><?= count($employees) ?> workforce records available.</p>
                </div>
            </div>

            <?php if (!$employees): ?>
                <div class="empty-state">No employees found.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr><th>Employee ID</th><th>Name</th><th>Department</th><th>Role</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td><strong><?= e($employee['employeeId'] ?? '') ?></strong></td>
                                <td><?= e($employee['name'] ?? '') ?></td>
                                <td><?= e($employee['department'] ?? '') ?></td>
                                <td><?= e($employee['role'] ?? '') ?></td>
                                <td><span class="badge <?= !empty($employee['active']) ? 'active' : 'inactive' ?>"><?= !empty($employee['active']) ? 'Active' : 'Inactive' ?></span></td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="form_action" value="status">
                                        <input type="hidden" name="employee_id" value="<?= e($employee['employeeId'] ?? '') ?>">
                                        <input type="hidden" name="active" value="<?= !empty($employee['active']) ? '0' : '1' ?>">
                                        <button type="submit" class="button <?= !empty($employee['active']) ? 'danger' : 'success' ?>">
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
        </section>
    </main>
</div>
</body>
</html>
