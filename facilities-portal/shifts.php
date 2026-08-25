<?php

// --------------------------------------------------
// Facilities Toolbox - Shift Management Workspace
// --------------------------------------------------
//
// v0.3 page for reusable shifts and employee shift
// assignments.
// --------------------------------------------------

require_once __DIR__ . "/includes/api.php";

$successMessage = null;
$errorMessage = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["form_action"] ?? "";

    if ($action === "shift") {
        $result = facilitiesApiRequest("POST", "/api/shifts", [
            "shiftCode" => trim($_POST["shift_code"] ?? ""),
            "name" => trim($_POST["shift_name"] ?? ""),
            "startTime" => trim($_POST["start_time"] ?? "08:00"),
            "endTime" => trim($_POST["end_time"] ?? "17:00"),
            "graceMinutes" => (int) ($_POST["grace_minutes"] ?? 15)
        ]);

        $successMessage = $result["success"]
            ? "Shift created successfully."
            : null;
        $errorMessage = $result["success"]
            ? null
            : $result["message"];
    }

    if ($action === "assignment") {
        $result = facilitiesApiRequest("POST", "/api/shifts/assignments", [
            "employeeId" => trim($_POST["employee_id"] ?? ""),
            "shiftCode" => trim($_POST["assignment_shift_code"] ?? ""),
            "effectiveFrom" => trim($_POST["effective_from"] ?? "")
        ]);

        $successMessage = $result["success"]
            ? "Shift assignment created successfully."
            : null;
        $errorMessage = $result["success"]
            ? null
            : $result["message"];
    }
}

$shiftResult = facilitiesApiRequest("GET", "/api/shifts");
$assignmentResult = facilitiesApiRequest("GET", "/api/shifts/assignments");
$employeeResult = facilitiesApiRequest("GET", "/api/employees");

$shifts = $shiftResult["success"] && is_array($shiftResult["data"])
    ? $shiftResult["data"] : [];
$assignments = $assignmentResult["success"] && is_array($assignmentResult["data"])
    ? $assignmentResult["data"] : [];
$employees = $employeeResult["success"] && is_array($employeeResult["data"])
    ? $employeeResult["data"] : [];

foreach ([$shiftResult, $assignmentResult, $employeeResult] as $result) {
    if (!$result["success"] && $errorMessage === null) {
        $errorMessage = $result["message"];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shifts | Facilities Toolbox</title>
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
            <a class="nav-link" href="employees.php">Employees</a>
            <a class="nav-link" href="attendance.php">Attendance</a>
            <a class="nav-link" href="reports.php">Reports</a>
        </nav>

        <div class="nav-section-label">Facilities</div>
        <nav>
            <a class="nav-link" href="structure.php">Structure</a>
            <a class="nav-link active" href="shifts.php">Shifts</a>
        </nav>

        <div class="sidebar-footer">v0.3 Operations Core</div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <p class="eyebrow">Workforce Scheduling</p>
                <h2 class="page-title">Shift Operations</h2>
                <p class="page-subtitle">
                    Define reusable schedules and assign employees without losing assignment history.
                </p>
            </div>
            <span class="status-pill">Scheduling Live</span>
        </header>

        <?php if ($successMessage): ?>
            <div class="notice success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="notice error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <section class="grid" style="grid-template-columns:1fr 1fr;margin-bottom:18px;">
            <article class="panel">
                <div class="panel-header"><div><h3 class="panel-title">Create Shift</h3><p class="panel-description">Reusable operating schedule.</p></div></div>
                <form method="POST" class="form-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));">
                    <input type="hidden" name="form_action" value="shift">
                    <div class="field"><label>Shift Code</label><input name="shift_code" placeholder="SHIFT01" required></div>
                    <div class="field"><label>Name</label><input name="shift_name" placeholder="Day Shift" required></div>
                    <div class="field"><label>Start</label><input name="start_time" type="time" value="08:00" required></div>
                    <div class="field"><label>End</label><input name="end_time" type="time" value="17:00" required></div>
                    <div class="field"><label>Grace Minutes</label><input name="grace_minutes" type="number" min="0" value="15" required></div>
                    <div style="display:flex;align-items:end;"><button class="button primary" type="submit">Create Shift</button></div>
                </form>
            </article>

            <article class="panel">
                <div class="panel-header"><div><h3 class="panel-title">Assign Employee</h3><p class="panel-description">New assignment automatically closes the prior active one.</p></div></div>
                <form method="POST" class="grid">
                    <input type="hidden" name="form_action" value="assignment">
                    <div class="field"><label>Employee</label><select name="employee_id" required><option value="">Select employee</option><?php foreach ($employees as $employee): ?><option value="<?= htmlspecialchars($employee["employeeId"] ?? "") ?>"><?= htmlspecialchars(($employee["employeeId"] ?? "") . " · " . ($employee["name"] ?? "")) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label>Shift</label><select name="assignment_shift_code" required><option value="">Select shift</option><?php foreach ($shifts as $shift): ?><option value="<?= htmlspecialchars($shift["shiftCode"] ?? "") ?>"><?= htmlspecialchars(($shift["shiftCode"] ?? "") . " · " . ($shift["name"] ?? "")) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label>Effective From</label><input name="effective_from" type="date" value="<?= htmlspecialchars(date("Y-m-d")) ?>" required></div>
                    <button class="button primary" type="submit">Assign Shift</button>
                </form>
            </article>
        </section>

        <section class="panel" style="margin-bottom:18px;">
            <div class="panel-header"><div><h3 class="panel-title">Shift Catalogue</h3><p class="panel-description"><?= count($shifts) ?> reusable schedules configured.</p></div></div>
            <?php if (!$shifts): ?>
                <div class="empty-state">No shifts configured yet.</div>
            <?php else: ?>
                <div class="table-wrap"><table><thead><tr><th>Shift</th><th>Hours</th><th>Grace</th><th>Assignments</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($shifts as $shift): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($shift["name"] ?? "") ?></strong><br><span style="color:var(--muted);font-size:.76rem;"><?= htmlspecialchars($shift["shiftCode"] ?? "") ?></span></td>
                        <td><?= htmlspecialchars(substr((string)($shift["startTime"] ?? ""), 0, 5)) ?> → <?= htmlspecialchars(substr((string)($shift["endTime"] ?? ""), 0, 5)) ?></td>
                        <td><?= (int) ($shift["graceMinutes"] ?? 0) ?> min</td>
                        <td><?= (int) ($shift["activeAssignments"] ?? 0) ?></td>
                        <td><span class="badge <?= !empty($shift["active"]) ? "active" : "inactive" ?>"><?= !empty($shift["active"]) ? "Active" : "Inactive" ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </section>

        <section class="panel">
            <div class="panel-header"><div><h3 class="panel-title">Assignment History</h3><p class="panel-description">Preserved workforce scheduling history.</p></div></div>
            <?php if (!$assignments): ?>
                <div class="empty-state">No shift assignments yet.</div>
            <?php else: ?>
                <div class="table-wrap"><table><thead><tr><th>Employee</th><th>Shift</th><th>From</th><th>To</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($assignments as $assignment): ?>
                    <tr>
                        <td><a href="profile.php?id=<?= rawurlencode($assignment["employeeId"] ?? "") ?>" style="text-decoration:none;"><strong><?= htmlspecialchars($assignment["employeeName"] ?? "") ?></strong></a><br><span style="color:var(--muted);font-size:.76rem;"><?= htmlspecialchars($assignment["employeeId"] ?? "") ?></span></td>
                        <td><?= htmlspecialchars(($assignment["shiftCode"] ?? "") . " · " . ($assignment["shiftName"] ?? "")) ?></td>
                        <td><?= htmlspecialchars($assignment["effectiveFrom"] ?? "") ?></td>
                        <td><?= htmlspecialchars($assignment["effectiveTo"] ?? "Open") ?></td>
                        <td><span class="badge <?= !empty($assignment["active"]) ? "active" : "inactive" ?>"><?= !empty($assignment["active"]) ? "Current" : "Historical" ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
