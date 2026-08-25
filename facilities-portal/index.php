<?php

// --------------------------------------------------
// Facilities Toolbox - Operations Dashboard
// --------------------------------------------------
//
// The dashboard is a presentation layer over the
// ASP.NET Core Facilities API. All operational data
// continues to come from the backend and PostgreSQL.
// --------------------------------------------------

require_once __DIR__ . '/api-client.php';

$dashboard = [];
$errorMessage = null;

$result = facilitiesApiRequest('GET', '/api/dashboard');

if ($result['success'] && is_array($result['data'])) {
    $dashboard = $result['data'];
} else {
    $errorMessage = $result['message'];
}

$latestActivity = is_array($dashboard['latestActivity'] ?? null)
    ? $dashboard['latestActivity']
    : [];

$departments = is_array($dashboard['departments'] ?? null)
    ? $dashboard['departments']
    : [];

$attendanceTrend = is_array($dashboard['attendanceTrend'] ?? null)
    ? $dashboard['attendanceTrend']
    : [];

$lateArrivals = is_array($dashboard['lateArrivals'] ?? null)
    ? $dashboard['lateArrivals']
    : [];

$openSessions = is_array($dashboard['openSessions'] ?? null)
    ? $dashboard['openSessions']
    : [];

function formatDashboardDateTime(?string $value): string
{
    if (!$value) {
        return '—';
    }

    $timestamp = strtotime($value);

    return $timestamp === false
        ? $value
        : date('d M Y H:i', $timestamp);
}

function percentageWidth(mixed $value): float
{
    $number = is_numeric($value) ? (float) $value : 0.0;
    return max(0.0, min(100.0, $number));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Facilities Toolbox</title>
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
            <a class="nav-link active" href="index.php">Dashboard</a>
            <a class="nav-link" href="employees.php">Employees</a>
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
                <p class="eyebrow">Operations Intelligence</p>
                <h2 class="page-title">Facilities Operations Dashboard</h2>
                <p class="page-subtitle">Live attendance and workforce intelligence from the Facilities API.</p>
            </div>

            <?php if (!$errorMessage): ?>
                <span class="status-pill">API Connected</span>
            <?php endif; ?>
        </header>

        <?php if ($errorMessage): ?>
            <div class="notice error">
                <strong>Dashboard unavailable.</strong><br>
                <?= e($errorMessage) ?><br>
                Confirm the ASP.NET Core API is running on <?= e($apiBaseUrl) ?>.
            </div>
        <?php else: ?>
            <section class="grid kpi-grid">
                <article class="kpi-card">
                    <p class="kpi-label">Active Employees</p>
                    <p class="kpi-value"><?= e($dashboard['activeEmployees'] ?? 0) ?></p>
                    <div class="kpi-meta">of <?= e($dashboard['totalEmployees'] ?? 0) ?> total employees</div>
                </article>

                <article class="kpi-card">
                    <p class="kpi-label">Present Now</p>
                    <p class="kpi-value"><?= e($dashboard['presentNow'] ?? 0) ?></p>
                    <div class="kpi-meta"><?= e($dashboard['clockedOut'] ?? 0) ?> clocked out</div>
                </article>

                <article class="kpi-card">
                    <p class="kpi-label">Attendance Rate</p>
                    <p class="kpi-value"><?= e(number_format((float) ($dashboard['attendanceRate'] ?? 0), 1)) ?>%</p>
                    <div class="kpi-meta">Target <?= e(number_format((float) ($dashboard['minimumAttendanceRate'] ?? 0), 1)) ?>%</div>
                </article>

                <article class="kpi-card">
                    <p class="kpi-label">Attendance Events Today</p>
                    <p class="kpi-value"><?= e($dashboard['attendanceEventsToday'] ?? 0) ?></p>
                    <div class="kpi-meta"><?= e($dashboard['onTimeToday'] ?? 0) ?> on time · <?= e($dashboard['lateToday'] ?? 0) ?> late</div>
                </article>

                <article class="kpi-card">
                    <p class="kpi-label">Absent Today</p>
                    <p class="kpi-value"><?= e($dashboard['absentToday'] ?? 0) ?></p>
                    <div class="kpi-meta">Active staff not seen today</div>
                </article>

                <article class="kpi-card">
                    <p class="kpi-label">Open Sessions</p>
                    <p class="kpi-value"><?= e($dashboard['openSessionCount'] ?? 0) ?></p>
                    <div class="kpi-meta">Employees currently clocked in</div>
                </article>

                <article class="kpi-card">
                    <p class="kpi-label">Hours Worked Today</p>
                    <p class="kpi-value"><?= e(number_format((float) ($dashboard['totalHoursWorkedToday'] ?? 0), 1)) ?></p>
                    <div class="kpi-meta">Recorded completed hours</div>
                </article>

                <article class="kpi-card">
                    <p class="kpi-label">Average First Arrival</p>
                    <p class="kpi-value"><?= e($dashboard['averageFirstArrival'] ?? '—') ?></p>
                    <div class="kpi-meta">Across employees seen today</div>
                </article>
            </section>

            <section class="grid dashboard-grid" style="margin-bottom:18px;">
                <article class="panel">
                    <div class="panel-header">
                        <div>
                            <h3 class="panel-title">Operational Alerts</h3>
                            <p class="panel-description">Exceptions that require supervisor attention.</p>
                        </div>
                    </div>

                    <?php if (!empty($dashboard['attendanceBelowTarget'])): ?>
                        <div class="notice error">
                            <strong>Attendance below target</strong><br>
                            Current attendance is <?= e(number_format((float) ($dashboard['attendanceRate'] ?? 0), 1)) ?>%.
                        </div>
                    <?php endif; ?>

                    <?php if (count($lateArrivals) > 0): ?>
                        <div class="notice"><strong><?= e(count($lateArrivals)) ?> late arrival(s)</strong><br>Review today's attendance exceptions.</div>
                    <?php endif; ?>

                    <?php if (count($openSessions) > 0): ?>
                        <div class="notice"><strong><?= e(count($openSessions)) ?> open session(s)</strong><br>Staff are clocked in or require session review.</div>
                    <?php endif; ?>

                    <?php if (empty($dashboard['attendanceBelowTarget']) && count($lateArrivals) === 0 && count($openSessions) === 0): ?>
                        <div class="notice success"><strong>All clear.</strong><br>No operational attendance alerts at this time.</div>
                    <?php endif; ?>
                </article>

                <article class="panel">
                    <div class="panel-header">
                        <div>
                            <h3 class="panel-title">Attendance Trend</h3>
                            <p class="panel-description">Recent workforce attendance rate.</p>
                        </div>
                    </div>

                    <?php if (!$attendanceTrend): ?>
                        <div class="empty-state">No trend data available yet.</div>
                    <?php else: ?>
                        <div class="grid">
                            <?php foreach ($attendanceTrend as $trend): ?>
                                <?php $rate = percentageWidth($trend['attendanceRate'] ?? 0); ?>
                                <div class="progress-card">
                                    <div class="progress-row">
                                        <strong><?= e(date('d M', strtotime($trend['date'] ?? 'now'))) ?></strong>
                                        <span><?= e(number_format($rate, 0)) ?>%</span>
                                    </div>
                                    <div class="progress-track"><div class="progress-bar" style="width:<?= e($rate) ?>%"></div></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            </section>

            <section class="panel" style="margin-bottom:18px;">
                <div class="panel-header">
                    <div>
                        <h3 class="panel-title">Department Attendance</h3>
                        <p class="panel-description">Live attendance coverage by operational unit.</p>
                    </div>
                </div>

                <?php if (!$departments): ?>
                    <div class="empty-state">No department data available.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Department</th><th>Active</th><th>Seen Today</th><th>Present Now</th><th>Attendance</th></tr></thead>
                            <tbody>
                            <?php foreach ($departments as $department): ?>
                                <tr>
                                    <td><strong><?= e($department['department'] ?? 'Unassigned') ?></strong></td>
                                    <td><?= e($department['activeEmployees'] ?? 0) ?></td>
                                    <td><?= e($department['seenToday'] ?? 0) ?></td>
                                    <td><?= e($department['presentNow'] ?? 0) ?></td>
                                    <td><?= e(number_format((float) ($department['attendanceRate'] ?? 0), 1)) ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h3 class="panel-title">Latest Attendance Activity</h3>
                        <p class="panel-description">Most recent workforce check-in and check-out events.</p>
                    </div>
                </div>

                <?php if (!$latestActivity): ?>
                    <div class="empty-state">No attendance activity available.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Employee</th><th>Department</th><th>Action</th><th>Timestamp</th></tr></thead>
                            <tbody>
                            <?php foreach ($latestActivity as $activity): ?>
                                <?php $action = strtolower((string) ($activity['action'] ?? '')); ?>
                                <tr>
                                    <td><strong><?= e($activity['employeeName'] ?? 'Unknown') ?></strong><br><span style="color:var(--muted);font-size:.76rem;"><?= e($activity['employeeId'] ?? '') ?></span></td>
                                    <td><?= e($activity['department'] ?? '') ?></td>
                                    <td><span class="badge <?= $action === 'in' ? 'in' : 'out' ?>"><?= e(strtoupper($action)) ?></span></td>
                                    <td><?= e(formatDashboardDateTime($activity['timestamp'] ?? null)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <p class="page-subtitle" style="font-size:.76rem;margin-top:18px;">Snapshot generated: <?= e(formatDashboardDateTime($dashboard['generatedAt'] ?? null)) ?></p>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
