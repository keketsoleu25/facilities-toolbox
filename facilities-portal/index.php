<?php

// --------------------------------------------------
// Facilities Toolbox - Operations Command Centre
// --------------------------------------------------
//
// v0.2 turns the portal homepage into a live facilities
// operations dashboard. Data comes from the C# API and
// PostgreSQL through /api/dashboard.
//
// PHP is responsible only for presentation. Business
// calculations remain in the ASP.NET Core service layer.
// --------------------------------------------------

require_once __DIR__ . "/includes/api.php";


// --------------------------------------------------
// Load dashboard intelligence
// --------------------------------------------------

$dashboardResult =
    facilitiesApiRequest(
        "GET",
        "/api/dashboard"
    );

$dashboard =
    $dashboardResult["success"] &&
    is_array($dashboardResult["data"])
        ? $dashboardResult["data"]
        : [];

$errorMessage =
    $dashboardResult["success"]
        ? null
        : $dashboardResult["message"];


// --------------------------------------------------
// Safe dashboard defaults
// --------------------------------------------------

$totalEmployees = (int) ($dashboard["totalEmployees"] ?? 0);
$activeEmployees = (int) ($dashboard["activeEmployees"] ?? 0);
$presentNow = (int) ($dashboard["presentNow"] ?? 0);
$clockedOut = (int) ($dashboard["clockedOut"] ?? 0);
$absentToday = (int) ($dashboard["absentToday"] ?? 0);
$attendanceEventsToday = (int) ($dashboard["attendanceEventsToday"] ?? 0);
$attendanceRate = (float) ($dashboard["attendanceRate"] ?? 0);
$totalHoursWorkedToday = (float) ($dashboard["totalHoursWorkedToday"] ?? 0);
$averageFirstArrival = (string) ($dashboard["averageFirstArrival"] ?? "--");

$latestActivity =
    is_array($dashboard["latestActivity"] ?? null)
        ? $dashboard["latestActivity"]
        : [];

$departments =
    is_array($dashboard["departments"] ?? null)
        ? $dashboard["departments"]
        : [];

$attendanceTrend =
    is_array($dashboard["attendanceTrend"] ?? null)
        ? $dashboard["attendanceTrend"]
        : [];

$attendanceProgress = max(0, min(100, $attendanceRate));


// --------------------------------------------------
// Formatting helpers
// --------------------------------------------------

function formatActivityTime(?string $timestamp): string
{
    if (!$timestamp) {
        return "--";
    }

    try {
        $date = new DateTime($timestamp);
        $date->setTimezone(
            new DateTimeZone("Africa/Johannesburg")
        );

        return $date->format("d M · H:i");
    } catch (Exception $exception) {
        return "--";
    }
}

function formatTrendDay(?string $date): string
{
    if (!$date) {
        return "--";
    }

    try {
        return (new DateTime($date))->format("D");
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
    <title>Command Centre | Facilities Toolbox</title>
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
            <a class="nav-link active" href="index.php">Dashboard</a>
            <a class="nav-link" href="employees.php">Employees</a>
            <a class="nav-link" href="attendance.php">Attendance</a>
            <a class="nav-link" href="reports.php">Reports</a>
        </nav>

        <div class="nav-section-label">System</div>
        <nav>
            <a class="nav-link" href="#department-health">Departments</a>
            <a class="nav-link" href="#">Settings</a>
        </nav>

        <div class="sidebar-footer">
            v0.2 Operations Intelligence
        </div>
    </aside>


    <main class="main">
        <header class="topbar">
            <div>
                <p class="eyebrow">Live Operations</p>
                <h2 class="page-title">Command Centre</h2>
                <p class="page-subtitle">
                    Workforce presence, completed hours, attendance health and facility readiness in one live view.
                </p>
            </div>

            <?php if ($dashboardResult["success"]): ?>
                <span class="status-pill">API Live</span>
            <?php else: ?>
                <span class="badge inactive">API Offline</span>
            <?php endif; ?>
        </header>


        <?php if ($errorMessage): ?>
            <div class="notice error">
                <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>


        <!-- Primary real-time KPIs -->
        <section class="grid kpi-grid">
            <article class="kpi-card">
                <p class="kpi-label">Present Now</p>
                <p class="kpi-value"><?= $presentNow ?></p>
                <p class="kpi-meta">Active staff currently inside</p>
            </article>

            <article class="kpi-card">
                <p class="kpi-label">Attendance Rate</p>
                <p class="kpi-value"><?= number_format($attendanceRate, 1) ?>%</p>
                <p class="kpi-meta"><?= $attendanceEventsToday ?> events recorded today</p>
            </article>

            <article class="kpi-card">
                <p class="kpi-label">Hours Completed</p>
                <p class="kpi-value"><?= number_format($totalHoursWorkedToday, 1) ?>h</p>
                <p class="kpi-meta">Completed IN → OUT sessions today</p>
            </article>

            <article class="kpi-card">
                <p class="kpi-label">Average Arrival</p>
                <p class="kpi-value"><?= htmlspecialchars($averageFirstArrival) ?></p>
                <p class="kpi-meta">Average first clock-in today</p>
            </article>
        </section>


        <!-- Secondary health KPIs -->
        <section class="grid kpi-grid">
            <article class="kpi-card">
                <p class="kpi-label">Active Employees</p>
                <p class="kpi-value"><?= $activeEmployees ?></p>
                <p class="kpi-meta"><?= $totalEmployees ?> total employee records</p>
            </article>

            <article class="kpi-card">
                <p class="kpi-label">Clocked Out</p>
                <p class="kpi-value"><?= $clockedOut ?></p>
                <p class="kpi-meta">Active staff whose latest state is OUT</p>
            </article>

            <article class="kpi-card">
                <p class="kpi-label">Absent Today</p>
                <p class="kpi-value"><?= $absentToday ?></p>
                <p class="kpi-meta">Active employees with no attendance event today</p>
            </article>

            <article class="kpi-card">
                <p class="kpi-label">Coverage</p>
                <p class="kpi-value"><?= $presentNow ?> / <?= $activeEmployees ?></p>
                <p class="kpi-meta">Current live workforce coverage</p>
            </article>
        </section>


        <section class="grid dashboard-grid">
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h3 class="panel-title">Latest Activity</h3>
                        <p class="panel-description">
                            Recent attendance events flowing through recognition, API and PostgreSQL.
                        </p>
                    </div>
                    <a class="action-link" href="attendance.php">View history</a>
                </div>

                <?php if (!$latestActivity): ?>
                    <div class="empty-state">
                        No attendance activity is available yet.
                    </div>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($latestActivity as $activity): ?>
                            <?php
                            $action = strtoupper($activity["action"] ?? "");
                            $isOut = $action === "OUT";
                            ?>
                            <div class="activity-item">
                                <div class="activity-icon <?= $isOut ? "out" : "" ?>">
                                    <?= htmlspecialchars($action ?: "--") ?>
                                </div>

                                <div>
                                    <p class="activity-name">
                                        <?= htmlspecialchars($activity["employeeName"] ?? "Unknown employee") ?>
                                    </p>
                                    <p class="activity-meta">
                                        <?= htmlspecialchars($activity["employeeId"] ?? "") ?>
                                        ·
                                        <?= htmlspecialchars($activity["department"] ?? "Unassigned") ?>
                                    </p>
                                </div>

                                <div class="activity-time">
                                    <?= htmlspecialchars(formatActivityTime($activity["timestamp"] ?? null)) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>


            <aside class="grid">
                <article class="panel">
                    <div class="panel-header">
                        <div>
                            <h3 class="panel-title">Attendance Health</h3>
                            <p class="panel-description">Today’s active workforce coverage.</p>
                        </div>
                    </div>

                    <div class="progress-card">
                        <div class="progress-row">
                            <span style="color: var(--muted);">Seen Today</span>
                            <strong><?= number_format($attendanceRate, 1) ?>%</strong>
                        </div>

                        <div class="progress-track">
                            <div
                                class="progress-bar"
                                style="width: <?= $attendanceProgress ?>%;"
                            ></div>
                        </div>
                    </div>

                    <div class="progress-card" style="margin-top:12px;">
                        <div class="progress-row">
                            <span style="color: var(--muted);">Currently Present</span>
                            <strong><?= $presentNow ?> / <?= $activeEmployees ?></strong>
                        </div>
                    </div>

                    <div class="progress-card" style="margin-top:12px;">
                        <div class="progress-row">
                            <span style="color: var(--muted);">Absent Today</span>
                            <strong><?= $absentToday ?></strong>
                        </div>
                    </div>
                </article>


                <article class="panel">
                    <div class="panel-header">
                        <div>
                            <h3 class="panel-title">Quick Actions</h3>
                            <p class="panel-description">Move directly into operational work.</p>
                        </div>
                    </div>

                    <div class="quick-actions">
                        <a class="action-link" href="employees.php">Manage Employees</a>
                        <a class="action-link" href="attendance.php">Attendance History</a>
                        <a class="action-link" href="reports.php">Daily Summary</a>
                    </div>
                </article>
            </aside>
        </section>


        <!-- Seven-day intelligence -->
        <section class="panel" style="margin-top:18px;">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">7-Day Attendance Signal</h3>
                    <p class="panel-description">
                        Daily percentage of active employees seen by the attendance system.
                    </p>
                </div>
            </div>

            <?php if (!$attendanceTrend): ?>
                <div class="empty-state">No trend information available yet.</div>
            <?php else: ?>
                <div class="grid" style="grid-template-columns:repeat(7,minmax(70px,1fr));gap:10px;">
                    <?php foreach ($attendanceTrend as $trendItem): ?>
                        <?php
                        $trendRate = (float) ($trendItem["attendanceRate"] ?? 0);
                        $trendWidth = max(0, min(100, $trendRate));
                        ?>
                        <div class="progress-card" style="padding:12px;">
                            <div class="progress-row" style="display:block;">
                                <strong><?= htmlspecialchars(formatTrendDay($trendItem["date"] ?? null)) ?></strong>
                                <div style="margin-top:5px;color:var(--muted);font-size:.78rem;">
                                    <?= number_format($trendRate, 1) ?>%
                                </div>
                            </div>
                            <div class="progress-track">
                                <div class="progress-bar" style="width:<?= $trendWidth ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>


        <!-- Department intelligence -->
        <section id="department-health" class="panel" style="margin-top:18px;">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">Department Health</h3>
                    <p class="panel-description">
                        Attendance coverage and live presence by operational team.
                    </p>
                </div>
            </div>

            <?php if (!$departments): ?>
                <div class="empty-state">No department information available yet.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Department</th>
                            <th>Active Staff</th>
                            <th>Seen Today</th>
                            <th>Present Now</th>
                            <th>Attendance</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($departments as $department): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($department["department"] ?? "Unassigned") ?></strong></td>
                                <td><?= (int) ($department["activeEmployees"] ?? 0) ?></td>
                                <td><?= (int) ($department["seenToday"] ?? 0) ?></td>
                                <td><?= (int) ($department["presentNow"] ?? 0) ?></td>
                                <td><?= number_format((float) ($department["attendanceRate"] ?? 0), 1) ?>%</td>
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
