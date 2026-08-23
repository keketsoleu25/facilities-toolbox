<?php

// --------------------------------------------------
// Facilities Toolbox - Workforce Operations Board
// --------------------------------------------------
//
// v0.3 answers the practical facilities question:
//
// "Who is where right now?"
//
// The page combines:
// - employee placement
// - site / building / department context
// - active shift assignment
// - today's attendance state
// - building occupancy
//
// All operational calculations remain in the C# API.
// --------------------------------------------------

require_once __DIR__ . "/includes/api.php";

$successMessage = null;
$errorMessage = null;

// --------------------------------------------------
// Handle employee placement command
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $formAction = $_POST["form_action"] ?? "";

    if ($formAction === "place_employee") {
        $employeeId = trim($_POST["employee_id"] ?? "");
        $departmentId = (int) ($_POST["department_id"] ?? 0);

        $result = facilitiesApiRequest(
            "PATCH",
            "/api/employee-placement/" . rawurlencode($employeeId),
            [
                "departmentId" => $departmentId
            ]
        );

        if ($result["success"]) {
            $successMessage = "$employeeId placement updated successfully.";
        } else {
            $errorMessage = $result["message"];
        }
    }
}

// --------------------------------------------------
// Load board data and placement options
// --------------------------------------------------

$boardResult = facilitiesApiRequest("GET", "/api/workforce-operations");
$employeeResult = facilitiesApiRequest("GET", "/api/employees");
$departmentResult = facilitiesApiRequest("GET", "/api/departments-v3");

$board = $boardResult["success"] && is_array($boardResult["data"])
    ? $boardResult["data"]
    : [];

$employees = $employeeResult["success"] && is_array($employeeResult["data"])
    ? $employeeResult["data"]
    : [];

$departments = $departmentResult["success"] && is_array($departmentResult["data"])
    ? $departmentResult["data"]
    : [];

foreach ([$boardResult, $employeeResult, $departmentResult] as $result) {
    if (!$result["success"] && $errorMessage === null) {
        $errorMessage = $result["message"];
    }
}

$activeEmployees = (int) ($board["activeEmployees"] ?? 0);
$presentNow = (int) ($board["presentNow"] ?? 0);
$clockedOut = (int) ($board["clockedOut"] ?? 0);
$notSeenToday = (int) ($board["notSeenToday"] ?? 0);
$unplacedEmployees = (int) ($board["unplacedEmployees"] ?? 0);

$buildingOccupancy = is_array($board["buildingOccupancy"] ?? null)
    ? $board["buildingOccupancy"]
    : [];

$workforceRows = is_array($board["employees"] ?? null)
    ? $board["employees"]
    : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workforce Board | Facilities Toolbox</title>
    <link rel="stylesheet" href="assets/app.css">
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
            <a class="nav-link active" href="workforce.php">Who Is Where</a>
            <a class="nav-link" href="attendance.php">Attendance</a>
            <a class="nav-link" href="reports.php">Reports</a>
        </nav>

        <div class="nav-section-label">Facilities</div>
        <nav>
            <a class="nav-link" href="structure.php">Structure</a>
            <a class="nav-link" href="shifts.php">Shifts</a>
        </nav>

        <div class="sidebar-footer">v0.3 Operations Core</div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <p class="eyebrow">Live Workforce Operations</p>
                <h2 class="page-title">Who Is Where</h2>
                <p class="page-subtitle">
                    Current workforce presence across sites, buildings, departments and shifts.
                </p>
            </div>

            <?php if ($boardResult["success"]): ?>
                <span class="status-pill">Operations Live</span>
            <?php else: ?>
                <span class="badge inactive">API Offline</span>
            <?php endif; ?>
        </header>

        <?php if ($successMessage): ?>
            <div class="notice success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="notice error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <!-- --------------------------------------------------
             Live workforce KPIs
        --------------------------------------------------- -->
        <section class="grid kpi-grid">
            <article class="kpi-card">
                <p class="kpi-label">Active Employees</p>
                <p class="kpi-value"><?= $activeEmployees ?></p>
                <p class="kpi-meta">Current active workforce records</p>
            </article>

            <article class="kpi-card">
                <p class="kpi-label">Present Now</p>
                <p class="kpi-value"><?= $presentNow ?></p>
                <p class="kpi-meta">Latest attendance state today is IN</p>
            </article>

            <article class="kpi-card">
                <p class="kpi-label">Clocked Out</p>
                <p class="kpi-value"><?= $clockedOut ?></p>
                <p class="kpi-meta">Seen today and latest state is OUT</p>
            </article>

            <article class="kpi-card">
                <p class="kpi-label">Not Seen Today</p>
                <p class="kpi-value"><?= $notSeenToday ?></p>
                <p class="kpi-meta">No attendance event today</p>
            </article>
        </section>

        <!-- --------------------------------------------------
             Employee placement workspace
        --------------------------------------------------- -->
        <section class="panel" style="margin-bottom:18px;">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Placement Control</p>
                    <h3 class="panel-title">Place Employee</h3>
                    <p class="panel-description">
                        Connect an employee to a structured department. Site and building context follow automatically.
                    </p>
                </div>
                <span class="badge <?= $unplacedEmployees > 0 ? "inactive" : "active" ?>">
                    <?= $unplacedEmployees ?> unplaced
                </span>
            </div>

            <form method="POST" class="form-grid">
                <input type="hidden" name="form_action" value="place_employee">

                <div class="field">
                    <label for="employee_id">Employee</label>
                    <select id="employee_id" name="employee_id" required>
                        <option value="">Select employee</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?= htmlspecialchars($employee["employeeId"] ?? "") ?>">
                                <?= htmlspecialchars(($employee["name"] ?? "") . " · " . ($employee["employeeId"] ?? "")) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="department_id">Department</label>
                    <select id="department_id" name="department_id" required>
                        <option value="">Select department</option>
                        <?php foreach ($departments as $department): ?>
                            <?php if (!empty($department["active"])): ?>
                                <option value="<?= (int) ($department["id"] ?? 0) ?>">
                                    <?= htmlspecialchars(
                                        ($department["departmentCode"] ?? "")
                                        . " · "
                                        . ($department["name"] ?? "")
                                        . (!empty($department["siteName"])
                                            ? " · " . $department["siteName"]
                                            : "")
                                        . (!empty($department["buildingName"])
                                            ? " / " . $department["buildingName"]
                                            : "")
                                    ) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="align-self:end;">
                    <button class="button primary" type="submit">Update Placement</button>
                </div>
            </form>
        </section>

        <!-- --------------------------------------------------
             Building occupancy
        --------------------------------------------------- -->
        <section class="panel" style="margin-bottom:18px;">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Physical Occupancy</p>
                    <h3 class="panel-title">Building Occupancy</h3>
                    <p class="panel-description">
                        Live headcount based on employee placement and today's latest attendance state.
                    </p>
                </div>
            </div>

            <?php if (!$buildingOccupancy): ?>
                <div class="empty-state">No structured building occupancy is available yet.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Site</th>
                            <th>Building</th>
                            <th>Assigned</th>
                            <th>Present</th>
                            <th>Clocked Out</th>
                            <th>Not Seen</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($buildingOccupancy as $building): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($building["site"] ?? "Unassigned") ?></strong><br>
                                    <span style="color:var(--muted);font-size:.76rem;">
                                        <?= htmlspecialchars($building["siteCode"] ?? "--") ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($building["building"] ?? "Unassigned") ?></strong><br>
                                    <span style="color:var(--muted);font-size:.76rem;">
                                        <?= htmlspecialchars($building["buildingCode"] ?? "--") ?>
                                    </span>
                                </td>
                                <td><?= (int) ($building["assignedEmployees"] ?? 0) ?></td>
                                <td><span class="badge active"><?= (int) ($building["presentNow"] ?? 0) ?></span></td>
                                <td><?= (int) ($building["clockedOut"] ?? 0) ?></td>
                                <td><?= (int) ($building["notSeenToday"] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- --------------------------------------------------
             Employee operations matrix
        --------------------------------------------------- -->
        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Live Workforce Matrix</p>
                    <h3 class="panel-title">Employee Locations & Shifts</h3>
                    <p class="panel-description">
                        One operational view of employee state, placement and assigned schedule.
                    </p>
                </div>
            </div>

            <?php if (!$workforceRows): ?>
                <div class="empty-state">No active employees available.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Status</th>
                            <th>Site / Building</th>
                            <th>Department</th>
                            <th>Shift</th>
                            <th>Today</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($workforceRows as $row): ?>
                            <?php
                            $status = strtoupper($row["status"] ?? "NOT_SEEN");
                            $statusClass = $status === "IN"
                                ? "active"
                                : ($status === "OUT" ? "out" : "inactive");
                            ?>
                            <tr>
                                <td>
                                    <a href="profile.php?id=<?= rawurlencode($row["employeeId"] ?? "") ?>" style="text-decoration:none;">
                                        <strong><?= htmlspecialchars($row["name"] ?? "") ?></strong>
                                    </a><br>
                                    <span style="color:var(--muted);font-size:.76rem;">
                                        <?= htmlspecialchars(($row["employeeId"] ?? "") . " · " . ($row["role"] ?? "")) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $statusClass ?>">
                                        <?= htmlspecialchars($status === "NOT_SEEN" ? "Not Seen" : $status) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row["site"] ?? "Unplaced") ?><br>
                                    <span style="color:var(--muted);font-size:.76rem;">
                                        <?= htmlspecialchars($row["building"] ?? "No building") ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($row["department"] ?? "Unassigned") ?></td>
                                <td>
                                    <?= htmlspecialchars($row["shift"] ?? "No shift") ?><br>
                                    <span style="color:var(--muted);font-size:.76rem;">
                                        <?= htmlspecialchars(
                                            !empty($row["shiftStart"])
                                                ? ($row["shiftStart"] . " → " . ($row["shiftEnd"] ?? "--"))
                                                : "No active assignment"
                                        ) ?>
                                    </span>
                                </td>
                                <td>
                                    First: <?= htmlspecialchars($row["firstArrival"] ?? "--") ?><br>
                                    <span style="color:var(--muted);font-size:.76rem;">
                                        Last: <?= htmlspecialchars($row["lastEvent"] ?? "--") ?>
                                    </span>
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
