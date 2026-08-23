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
// calculations such as present-now and attendance rate
// remain in the ASP.NET Core service layer.
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
//
// Defaults keep the page renderable even when the API
// is temporarily unavailable.
// --------------------------------------------------

$totalEmployees =
    (int) ($dashboard["totalEmployees"] ?? 0);

$activeEmployees =
    (int) ($dashboard["activeEmployees"] ?? 0);

$presentNow =
    (int) ($dashboard["presentNow"] ?? 0);

$clockedOut =
    (int) ($dashboard["clockedOut"] ?? 0);

$attendanceEventsToday =
    (int) ($dashboard["attendanceEventsToday"] ?? 0);

$attendanceRate =
    (float) ($dashboard["attendanceRate"] ?? 0);

$latestActivity =
    is_array($dashboard["latestActivity"] ?? null)
        ? $dashboard["latestActivity"]
        : [];


// Keep the progress width within valid CSS bounds.
$attendanceProgress =
    max(0, min(100, $attendanceRate));


// --------------------------------------------------
// Format API timestamps for South African operators
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

    <!-- --------------------------------------------------
         Facilities product navigation
    --------------------------------------------------- -->
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
            <a class="nav-link" href="#">Departments</a>
            <a class="nav-link" href="#">Settings</a>
        </nav>

        <div class="sidebar-footer">
            v0.2 Operations Intelligence
        </div>
    </aside>


    <main class="main">
        <!-- --------------------------------------------------
             Command centre heading
        --------------------------------------------------- -->
        <header class="topbar">
            <div>
                <p class="eyebrow">Live Operations</p>
                <h2 class="page-title">Command Centre</h2>
                <p class="page-subtitle">
                    A real-time view of workforce presence, attendance activity and facility readiness.
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


        <!-- --------------------------------------------------
             Primary facilities KPIs
        --------------------------------------------------- -->
        <section class="grid kpi-grid">
            <article class="kpi-card">
                <p class="kpi-label">Present Now</p>
                <p class="kpi-value"><?= $presentNow ?></p>
                <p class="kpi-meta">Currently clocked into the facility</p>
            </article>

            <article class="kpi-card">
                <p class="kpi-label">Active Employees</p>
                <p class="kpi-value"><?= $activeEmployees ?></p>
                <p class="kpi-meta"><?= $totalEmployees ?> total employee records</p>
            </article>

            <article class="kpi-card">
                <p class="kpi-label">Clocked Out</p>
                <p class="kpi-value"><?= $clockedOut ?></p>
                <p class="kpi-meta">Active staff currently out</p>
            </article>

            <article class="kpi-card">
                <p class="kpi-label">Attendance Rate</p>
                <p class="kpi-value"><?= number_format($attendanceRate, 1) ?>%</p>
                <p class="kpi-meta"><?= $attendanceEventsToday ?> events recorded today</p>
            </article>
        </section>


        <section class="grid dashboard-grid">
            <!-- --------------------------------------------------
                 Latest attendance activity
            --------------------------------------------------- -->
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h3 class="panel-title">Latest Activity</h3>
                        <p class="panel-description">
                            Recent attendance events flowing through the recognition and API pipeline.
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
                            $action =
                                strtoupper($activity["action"] ?? "");

                            $isOut =
                                $action === "OUT";
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


            <!-- --------------------------------------------------
                 Operational health and shortcuts
            --------------------------------------------------- -->
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
                            <span style="color: var(--muted);">Coverage</span>
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
    </main>
</div>
</body>
</html>
