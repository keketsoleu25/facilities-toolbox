<?php

// --------------------------------------------------
// Facilities Toolbox - Daily Summary
// --------------------------------------------------
//
// This page turns raw attendance events into a compact
// management summary for the current South African day.
//
// The high-level KPI values come from /api/dashboard.
// Detailed employee rows are derived from today's events
// so operators can see who arrived, left and remains in.
// --------------------------------------------------

require_once __DIR__ . "/includes/api.php";

$dashboardResult =
    facilitiesApiRequest("GET", "/api/dashboard");

$attendanceResult =
    facilitiesApiRequest("GET", "/api/attendance");

$employeeResult =
    facilitiesApiRequest("GET", "/api/employees");

$dashboard =
    $dashboardResult["success"] && is_array($dashboardResult["data"])
        ? $dashboardResult["data"]
        : [];

$records =
    $attendanceResult["success"] && is_array($attendanceResult["data"])
        ? $attendanceResult["data"]
        : [];

$employees =
    $employeeResult["success"] && is_array($employeeResult["data"])
        ? $employeeResult["data"]
        : [];

$errorMessage = null;

foreach ([$dashboardResult, $attendanceResult, $employeeResult] as $result) {
    if (!$result["success"]) {
        $errorMessage = $result["message"];
        break;
    }
}


// --------------------------------------------------
// Establish today's date in South Africa
// --------------------------------------------------

$saTimezone =
    new DateTimeZone("Africa/Johannesburg");

$today =
    new DateTime("now", $saTimezone);

$todayKey =
    $today->format("Y-m-d");

$todayLabel =
    $today->format("d F Y");


// --------------------------------------------------
// Index employee profiles
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
// Build per-employee attendance sessions for today
// --------------------------------------------------
//
// For each employee we capture:
// - first IN
// - last OUT
// - latest state
// - number of events today
// --------------------------------------------------

$dailyRows = [];

foreach ($records as $record) {
    $timestampText =
        $record["timestamp"] ?? null;

    if (!$timestampText) {
        continue;
    }

    try {
        $timestamp =
            new DateTime($timestampText);

        $timestamp->setTimezone($saTimezone);
    } catch (Exception $exception) {
        continue;
    }

    if ($timestamp->format("Y-m-d") !== $todayKey) {
        continue;
    }

    $employeeId =
        $record["employeeId"] ?? "";

    if ($employeeId === "") {
        continue;
    }

    if (!isset($dailyRows[$employeeId])) {
        $profile =
            $employeesById[$employeeId] ?? [];

        $dailyRows[$employeeId] = [
            "employeeId" => $employeeId,
            "name" => $profile["name"] ?? "Unknown Employee",
            "department" => $profile["department"] ?? "Unassigned",
            "firstIn" => null,
            "lastOut" => null,
            "latestAction" => null,
            "latestTimestamp" => null,
            "events" => 0
        ];
    }

    $action =
        strtoupper($record["action"] ?? "");

    $dailyRows[$employeeId]["events"]++;

    if (
        $action === "IN" &&
        $dailyRows[$employeeId]["firstIn"] === null
    ) {
        $dailyRows[$employeeId]["firstIn"] =
            clone $timestamp;
    }

    if ($action === "OUT") {
        $dailyRows[$employeeId]["lastOut"] =
            clone $timestamp;
    }

    if (
        $dailyRows[$employeeId]["latestTimestamp"] === null ||
        $timestamp > $dailyRows[$employeeId]["latestTimestamp"]
    ) {
        $dailyRows[$employeeId]["latestTimestamp"] =
            clone $timestamp;

        $dailyRows[$employeeId]["latestAction"] =
            $action;
    }
}


// Sort rows by first arrival where available.
usort(
    $dailyRows,
    function (array $left, array $right): int {
        $leftTime =
            $left["firstIn"]?->getTimestamp() ?? PHP_INT_MAX;

        $rightTime =
            $right["firstIn"]?->getTimestamp() ?? PHP_INT_MAX;

        return $leftTime <=> $rightTime;
    }
);


$totalEmployees =
    (int) ($dashboard["totalEmployees"] ?? 0);

$activeEmployees =
    (int) ($dashboard["activeEmployees"] ?? 0);

$presentNow =
    (int) ($dashboard["presentNow"] ?? 0);

$clockedOut =
    (int) ($dashboard["clockedOut"] ?? 0);

$attendanceRate =
    (float) ($dashboard["attendanceRate"] ?? 0);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Summary | Facilities Toolbox</title>
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
            <a class="nav-link" href="attendance.php">Attendance</a>
            <a class="nav-link active" href="reports.php">Reports</a>
        </nav>

        <div class="sidebar-footer">v0.2 Operations Intelligence</div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <p class="eyebrow">Management Reporting</p>
                <h2 class="page-title">Daily Summary</h2>
                <p class="page-subtitle">
                    <?= htmlspecialchars($todayLabel) ?> · South Africa time
                </p>
            </div>
            <span class="status-pill">Live Summary</span>
        </header>

        <?php if ($errorMessage): ?>
            <div class="notice error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <section class="grid kpi-grid">
            <article class="kpi-card">
                <p class="kpi-label">Active Staff</p>
                <p class="kpi-value"><?= $activeEmployees ?></p>
                <p class="kpi-meta"><?= $totalEmployees ?> total employee records</p>
            </article>

            <article class="kpi-card">
                <p class="kpi-label">Present Now</p>
                <p class="kpi-value"><?= $presentNow ?></p>
                <p class="kpi-meta">Latest state is IN</p>
            </article>

            <article class="kpi-card">
                <p class="kpi-label">Clocked Out</p>
                <p class="kpi-value"><?= $clockedOut ?></p>
                <p class="kpi-meta">Latest state is OUT</p>
            </article>

            <article class="kpi-card">
                <p class="kpi-label">Attendance Rate</p>
                <p class="kpi-value"><?= number_format($attendanceRate, 1) ?>%</p>
                <p class="kpi-meta">Current daily coverage</p>
            </article>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">Employee Day View</h3>
                    <p class="panel-description">
                        First arrival, latest exit and current state for employees with activity today.
                    </p>
                </div>
            </div>

            <?php if (!$dailyRows): ?>
                <div class="empty-state">
                    No attendance events have been recorded today.
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>First IN</th>
                            <th>Last OUT</th>
                            <th>Current State</th>
                            <th>Events</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($dailyRows as $row): ?>
                            <?php
                            $state =
                                strtolower($row["latestAction"] ?? "out");
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($row["name"]) ?></strong><br>
                                    <span style="color:var(--muted);font-size:0.76rem;">
                                        <?= htmlspecialchars($row["employeeId"]) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($row["department"]) ?></td>
                                <td>
                                    <?= $row["firstIn"] ? htmlspecialchars($row["firstIn"]->format("H:i")) : "--" ?>
                                </td>
                                <td>
                                    <?= $row["lastOut"] ? htmlspecialchars($row["lastOut"]->format("H:i")) : "--" ?>
                                </td>
                                <td>
                                    <span class="badge <?= $state === "in" ? "in" : "out" ?>">
                                        <?= htmlspecialchars($row["latestAction"] ?? "--") ?>
                                    </span>
                                </td>
                                <td><?= (int) $row["events"] ?></td>
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
