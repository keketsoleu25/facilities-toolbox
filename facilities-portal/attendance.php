<?php

// --------------------------------------------------
// Facilities Toolbox - Attendance History
// --------------------------------------------------
//
// Provides an operator-friendly view over permanent
// attendance events stored by the C# API. Employee
// metadata is joined in PHP for presentation only.
// --------------------------------------------------

require_once __DIR__ . "/includes/api.php";

$attendanceResult =
    facilitiesApiRequest("GET", "/api/attendance");

$employeeResult =
    facilitiesApiRequest("GET", "/api/employees");

$records =
    $attendanceResult["success"] &&
    is_array($attendanceResult["data"])
        ? $attendanceResult["data"]
        : [];

$employees =
    $employeeResult["success"] &&
    is_array($employeeResult["data"])
        ? $employeeResult["data"]
        : [];

$errorMessage = null;

if (!$attendanceResult["success"]) {
    $errorMessage = $attendanceResult["message"];
} elseif (!$employeeResult["success"]) {
    $errorMessage = $employeeResult["message"];
}


// --------------------------------------------------
// Index employees by EmployeeId
// --------------------------------------------------
//
// This allows O(1) metadata lookup while rendering each
// attendance row instead of repeatedly scanning the full
// employee array.
// --------------------------------------------------

$employeesById = [];

foreach ($employees as $employee) {
    $employeeId =
        $employee["employeeId"] ?? "";

    if ($employeeId !== "") {
        $employeesById[$employeeId] = $employee;
    }
}


// --------------------------------------------------
// Timestamp formatting helper
// --------------------------------------------------

function formatAttendanceTimestamp(?string $timestamp): string
{
    if (!$timestamp) {
        return "--";
    }

    try {
        $date = new DateTime($timestamp);
        $date->setTimezone(new DateTimeZone("Africa/Johannesburg"));
        return $date->format("Y-m-d H:i");
    } catch (Exception $exception) {
        return "--";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance | Facilities Toolbox</title>
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
            <a class="nav-link active" href="attendance.php">Attendance</a>
            <a class="nav-link" href="reports.php">Reports</a>
        </nav>

        <div class="sidebar-footer">v0.2 Operations Intelligence</div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <p class="eyebrow">Attendance Intelligence</p>
                <h2 class="page-title">Attendance History</h2>
                <p class="page-subtitle">
                    Search permanent IN/OUT events by employee, action or date.
                </p>
            </div>
            <span class="status-pill">Live Records</span>
        </header>

        <?php if ($errorMessage): ?>
            <div class="notice error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <section class="grid kpi-grid">
            <article class="kpi-card">
                <p class="kpi-label">Total Events</p>
                <p class="kpi-value"><?= count($records) ?></p>
                <p class="kpi-meta">Permanent attendance records</p>
            </article>
            <article class="kpi-card">
                <p class="kpi-label">Employees</p>
                <p class="kpi-value"><?= count($employees) ?></p>
                <p class="kpi-meta">Available employee profiles</p>
            </article>
        </section>

        <section class="panel" style="margin-bottom:18px;">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">History Filters</h3>
                    <p class="panel-description">Filter the currently loaded history instantly.</p>
                </div>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="historySearch">Employee</label>
                    <input id="historySearch" placeholder="EMP001 or employee name">
                </div>

                <div class="field">
                    <label for="actionFilter">Action</label>
                    <select id="actionFilter">
                        <option value="">IN & OUT</option>
                        <option value="in">IN</option>
                        <option value="out">OUT</option>
                    </select>
                </div>

                <div class="field">
                    <label for="dateFilter">Date</label>
                    <input id="dateFilter" type="date">
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">Attendance Timeline</h3>
                    <p class="panel-description">Newest events are shown first.</p>
                </div>
            </div>

            <?php if (!$records): ?>
                <div class="empty-state">No attendance events found.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table id="attendanceTable">
                        <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Action</th>
                            <th>Timestamp</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($records as $record): ?>
                            <?php
                            $employeeId =
                                $record["employeeId"] ?? "";

                            $employee =
                                $employeesById[$employeeId] ?? [];

                            $name =
                                $employee["name"] ?? "Unknown Employee";

                            $department =
                                $employee["department"] ?? "Unassigned";

                            $action =
                                strtoupper($record["action"] ?? "");

                            $formattedTimestamp =
                                formatAttendanceTimestamp($record["timestamp"] ?? null);

                            $dateOnly =
                                substr($formattedTimestamp, 0, 10);

                            $searchText = strtolower(
                                $employeeId . " " . $name . " " . $department
                            );
                            ?>
                            <tr
                                data-search="<?= htmlspecialchars($searchText) ?>"
                                data-action="<?= htmlspecialchars(strtolower($action)) ?>"
                                data-date="<?= htmlspecialchars($dateOnly) ?>"
                            >
                                <td>
                                    <strong><?= htmlspecialchars($name) ?></strong><br>
                                    <span style="color:var(--muted);font-size:0.76rem;">
                                        <?= htmlspecialchars($employeeId) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($department) ?></td>
                                <td>
                                    <span class="badge <?= strtolower($action) ?>">
                                        <?= htmlspecialchars($action) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($formattedTimestamp) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<script>
// --------------------------------------------------
// Attendance history filters
// --------------------------------------------------

const historySearch = document.getElementById("historySearch");
const actionFilter = document.getElementById("actionFilter");
const dateFilter = document.getElementById("dateFilter");
const attendanceRows = document.querySelectorAll("#attendanceTable tbody tr");

function applyHistoryFilters() {
    const search = (historySearch?.value || "").trim().toLowerCase();
    const action = actionFilter?.value || "";
    const date = dateFilter?.value || "";

    attendanceRows.forEach((row) => {
        const searchMatches =
            search === "" || row.dataset.search.includes(search);

        const actionMatches =
            action === "" || row.dataset.action === action;

        const dateMatches =
            date === "" || row.dataset.date === date;

        row.hidden = !(searchMatches && actionMatches && dateMatches);
    });
}

historySearch?.addEventListener("input", applyHistoryFilters);
actionFilter?.addEventListener("change", applyHistoryFilters);
dateFilter?.addEventListener("change", applyHistoryFilters);
</script>
</body>
</html>
