<?php

// --------------------------------------------------
// Facilities Toolbox - Employee Operational Profile
// --------------------------------------------------
//
// Read-only drill-down into one employee's current
// attendance state, today's worked time and recent
// attendance history.
// --------------------------------------------------

require_once __DIR__ . "/includes/api.php";

$employeeId = trim($_GET["id"] ?? "");
$profile = [];
$errorMessage = null;

if ($employeeId === "") {
    $errorMessage = "Employee ID is required.";
} else {
    $result = facilitiesApiRequest(
        "GET",
        "/api/employee-profiles/" . rawurlencode($employeeId)
    );

    if ($result["success"] && is_array($result["data"])) {
        $profile = $result["data"];
    } else {
        $errorMessage = $result["message"];
    }
}

function profileTime(?string $timestamp): string
{
    if (!$timestamp) {
        return "--";
    }

    try {
        return (new DateTime($timestamp))->format("d M Y · H:i");
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
    <title>Employee Profile | Facilities Toolbox</title>
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
            <a class="nav-link active" href="employees.php">Employees</a>
            <a class="nav-link" href="attendance.php">Attendance</a>
            <a class="nav-link" href="reports.php">Reports</a>
        </nav>

        <div class="sidebar-footer">v0.2 Operations Intelligence</div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <p class="eyebrow">Employee Intelligence</p>
                <h2 class="page-title">
                    <?= htmlspecialchars($profile["name"] ?? "Employee Profile") ?>
                </h2>
                <p class="page-subtitle">
                    <?= htmlspecialchars($profile["employeeId"] ?? $employeeId) ?>
                    <?php if (!empty($profile["department"])): ?>
                        · <?= htmlspecialchars($profile["department"]) ?>
                    <?php endif; ?>
                    <?php if (!empty($profile["role"])): ?>
                        · <?= htmlspecialchars($profile["role"]) ?>
                    <?php endif; ?>
                </p>
            </div>

            <a class="action-link" href="employees.php">Back to Employees</a>
        </header>

        <?php if ($errorMessage): ?>
            <div class="notice error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php else: ?>
            <?php
            $status = strtoupper($profile["currentStatus"] ?? "NOT_SEEN");
            $statusLabel = $status === "IN"
                ? "Present"
                : ($status === "OUT" ? "Clocked Out" : "Not Seen Today");
            ?>

            <section class="grid kpi-grid">
                <article class="kpi-card">
                    <p class="kpi-label">Current Status</p>
                    <p class="kpi-value" style="font-size:1.45rem;">
                        <?= htmlspecialchars($statusLabel) ?>
                    </p>
                    <p class="kpi-meta">
                        <?= !empty($profile["active"]) ? "Active employee" : "Inactive employee" ?>
                    </p>
                </article>

                <article class="kpi-card">
                    <p class="kpi-label">First Arrival</p>
                    <p class="kpi-value"><?= htmlspecialchars($profile["firstArrivalToday"] ?? "--") ?></p>
                    <p class="kpi-meta">First IN event today</p>
                </article>

                <article class="kpi-card">
                    <p class="kpi-label">Completed Hours</p>
                    <p class="kpi-value"><?= number_format((float)($profile["completedHoursToday"] ?? 0), 1) ?>h</p>
                    <p class="kpi-meta">Completed IN → OUT sessions</p>
                </article>

                <article class="kpi-card">
                    <p class="kpi-label">Open Session</p>
                    <p class="kpi-value">
                        <?= !empty($profile["hasOpenSession"])
                            ? number_format((float)($profile["openSessionHours"] ?? 0), 1) . "h"
                            : "--" ?>
                    </p>
                    <p class="kpi-meta">
                        <?= !empty($profile["hasOpenSession"])
                            ? "Still clocked in"
                            : "No open work session" ?>
                    </p>
                </article>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h3 class="panel-title">Recent Attendance</h3>
                        <p class="panel-description">The latest 20 attendance events for this employee.</p>
                    </div>
                </div>

                <?php $recent = $profile["recentAttendance"] ?? []; ?>

                <?php if (!$recent): ?>
                    <div class="empty-state">No attendance history available.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Event</th>
                                <th>Date / Time</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recent as $event): ?>
                                <tr>
                                    <td>
                                        <span class="badge <?= strtolower($event["action"] ?? "") ?>">
                                            <?= htmlspecialchars($event["action"] ?? "--") ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars(profileTime($event["timestamp"] ?? null)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
