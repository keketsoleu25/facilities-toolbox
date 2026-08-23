<?php

// --------------------------------------------------
// Facilities Toolbox - Operations Dashboard
// --------------------------------------------------
//
// This page consumes the ASP.NET Core dashboard API
// and presents live facilities attendance intelligence.
//
// Data flow:
// PHP Portal -> ASP.NET Core API -> EF Core -> PostgreSQL
// --------------------------------------------------

require_once __DIR__ . '/api-client.php';

$dashboard = [];
$errorMessage = null;

// --------------------------------------------------
// Load the current dashboard snapshot
// --------------------------------------------------

$result = facilitiesApiRequest('GET', '/api/dashboard');

if ($result['success'] && is_array($result['data'])) {
    $dashboard = $result['data'];
} else {
    $errorMessage = $result['message'];
}

// --------------------------------------------------
// Normalize dashboard collections
// --------------------------------------------------

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

// Convert an API date/time into a compact local display.
function formatDashboardDateTime(?string $value): string
{
    if (!$value) {
        return '—';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('d M Y H:i', $timestamp);
}

// Keep percentage values inside a safe visual range.
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
    <title>Dashboard - Facilities Toolbox</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 30px 20px 50px; font-family: Arial, sans-serif; background: #f3f4f6; color: #111827; }
        .container { max-width: 1250px; margin: 0 auto; }
        nav { display: flex; gap: 18px; margin-bottom: 28px; }
        nav a { color: #111827; text-decoration: none; font-weight: 700; }
        .hero { display: flex; justify-content: space-between; gap: 20px; align-items: flex-start; margin-bottom: 24px; }
        .hero h1 { margin: 0 0 8px; }
        .muted { color: #6b7280; }
        .status { display: inline-flex; align-items: center; gap: 8px; padding: 9px 12px; border-radius: 999px; font-weight: 700; background: #dcfce7; }
        .status::before { content: ''; width: 9px; height: 9px; border-radius: 50%; background: #16a34a; }
        .error { padding: 16px; border-radius: 12px; background: #fee2e2; margin-bottom: 24px; }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px; }
        .card { background: #fff; border-radius: 14px; padding: 22px; box-shadow: 0 8px 28px rgba(17, 24, 39, .06); }
        .metric-label { color: #6b7280; font-size: 14px; }
        .metric-value { font-size: 30px; font-weight: 800; margin-top: 8px; }
        .metric-note { margin-top: 8px; color: #6b7280; font-size: 13px; }
        .two-column { display: grid; grid-template-columns: 1.25fr .75fr; gap: 20px; margin-bottom: 24px; }
        .section-title { margin-top: 0; }
        .alert { border-left: 4px solid #dc2626; padding: 12px 14px; background: #fef2f2; border-radius: 8px; margin-bottom: 10px; }
        .warning { border-left-color: #d97706; background: #fffbeb; }
        .good { border-left-color: #16a34a; background: #f0fdf4; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 650px; }
        th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { color: #6b7280; font-size: 13px; background: #f9fafb; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; background: #eef2ff; font-size: 12px; font-weight: 700; }
        .progress { height: 9px; background: #e5e7eb; border-radius: 999px; overflow: hidden; margin-top: 7px; }
        .progress > span { display: block; height: 100%; background: #111827; border-radius: inherit; }
        .trend-list { display: grid; gap: 12px; }
        .trend-row { display: grid; grid-template-columns: 105px 1fr 60px; gap: 12px; align-items: center; }
        .empty { color: #6b7280; padding: 8px 0; }
        .footer-note { margin-top: 24px; color: #6b7280; font-size: 13px; }
        @media (max-width: 980px) {
            .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .two-column { grid-template-columns: 1fr; }
        }
        @media (max-width: 620px) {
            body { padding: 20px 14px 36px; }
            .hero { flex-direction: column; }
            .grid { grid-template-columns: 1fr; }
            .trend-row { grid-template-columns: 90px 1fr 50px; }
        }
    </style>
</head>
<body>
<div class="container">
    <nav>
        <a href="index.php">Dashboard</a>
        <a href="employees.php">Employees</a>
    </nav>

    <div class="hero">
        <div>
            <h1>Facilities Operations Dashboard</h1>
            <div class="muted">Live attendance and workforce intelligence from the Facilities API.</div>
        </div>

        <?php if (!$errorMessage): ?>
            <div class="status">API Connected</div>
        <?php endif; ?>
    </div>

    <?php if ($errorMessage): ?>
        <div class="error">
            <strong>Dashboard unavailable.</strong><br>
            <?= e($errorMessage) ?><br>
            <span class="muted">Confirm the ASP.NET Core API is running on <?= e($apiBaseUrl) ?>.</span>
        </div>
    <?php else: ?>

        <div class="grid">
            <div class="card">
                <div class="metric-label">Active Employees</div>
                <div class="metric-value"><?= e($dashboard['activeEmployees'] ?? 0) ?></div>
                <div class="metric-note">of <?= e($dashboard['totalEmployees'] ?? 0) ?> total employees</div>
            </div>

            <div class="card">
                <div class="metric-label">Present Now</div>
                <div class="metric-value"><?= e($dashboard['presentNow'] ?? 0) ?></div>
                <div class="metric-note"><?= e($dashboard['clockedOut'] ?? 0) ?> clocked out</div>
            </div>

            <div class="card">
                <div class="metric-label">Attendance Rate</div>
                <div class="metric-value"><?= e(number_format((float) ($dashboard['attendanceRate'] ?? 0), 1)) ?>%</div>
                <div class="metric-note">Target <?= e(number_format((float) ($dashboard['minimumAttendanceRate'] ?? 0), 1)) ?>%</div>
            </div>

            <div class="card">
                <div class="metric-label">Attendance Events Today</div>
                <div class="metric-value"><?= e($dashboard['attendanceEventsToday'] ?? 0) ?></div>
                <div class="metric-note"><?= e($dashboard['onTimeToday'] ?? 0) ?> on time · <?= e($dashboard['lateToday'] ?? 0) ?> late</div>
            </div>

            <div class="card">
                <div class="metric-label">Absent Today</div>
                <div class="metric-value"><?= e($dashboard['absentToday'] ?? 0) ?></div>
                <div class="metric-note">Active staff not seen today</div>
            </div>

            <div class="card">
                <div class="metric-label">Open Sessions</div>
                <div class="metric-value"><?= e($dashboard['openSessionCount'] ?? 0) ?></div>
                <div class="metric-note">Employees currently clocked in</div>
            </div>

            <div class="card">
                <div class="metric-label">Hours Worked Today</div>
                <div class="metric-value"><?= e(number_format((float) ($dashboard['totalHoursWorkedToday'] ?? 0), 1)) ?></div>
                <div class="metric-note">Recorded completed hours</div>
            </div>

            <div class="card">
                <div class="metric-label">Average First Arrival</div>
                <div class="metric-value" style="font-size:24px"><?= e($dashboard['averageFirstArrival'] ?? '—') ?></div>
                <div class="metric-note">Across employees seen today</div>
            </div>
        </div>

        <div class="two-column">
            <div class="card">
                <h2 class="section-title">Operational Alerts</h2>

                <?php if (!empty($dashboard['attendanceBelowTarget'])): ?>
                    <div class="alert">
                        <strong>Attendance below target</strong><br>
                        Current attendance is <?= e(number_format((float) ($dashboard['attendanceRate'] ?? 0), 1)) ?>%, below the configured minimum.
                    </div>
                <?php endif; ?>

                <?php if (count($lateArrivals) > 0): ?>
                    <div class="alert warning">
                        <strong><?= e(count($lateArrivals)) ?> late arrival(s)</strong><br>
                        Review today's late attendance exceptions.
                    </div>
                <?php endif; ?>

                <?php if (count($openSessions) > 0): ?>
                    <div class="alert warning">
                        <strong><?= e(count($openSessions)) ?> open attendance session(s)</strong><br>
                        Staff are currently clocked in or may require session review.
                    </div>
                <?php endif; ?>

                <?php if (empty($dashboard['attendanceBelowTarget']) && count($lateArrivals) === 0 && count($openSessions) === 0): ?>
                    <div class="alert good">
                        <strong>No active operational alerts.</strong><br>
                        Attendance conditions are currently within configured policy.
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2 class="section-title">Attendance Trend</h2>

                <?php if (!$attendanceTrend): ?>
                    <div class="empty">No trend data available yet.</div>
                <?php else: ?>
                    <div class="trend-list">
                        <?php foreach ($attendanceTrend as $trend): ?>
                            <?php $rate = percentageWidth($trend['attendanceRate'] ?? 0); ?>
                            <div class="trend-row">
                                <div><?= e(date('d M', strtotime($trend['date'] ?? 'now'))) ?></div>
                                <div class="progress"><span style="width: <?= e($rate) ?>%"></span></div>
                                <div><?= e(number_format($rate, 0)) ?>%</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" style="margin-bottom:24px">
            <h2 class="section-title">Department Attendance</h2>

            <?php if (!$departments): ?>
                <div class="empty">No department data available.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Department</th>
                            <th>Active</th>
                            <th>Seen Today</th>
                            <th>Present Now</th>
                            <th>Attendance</th>
                        </tr>
                        </thead>
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
        </div>

        <div class="card">
            <h2 class="section-title">Latest Attendance Activity</h2>

            <?php if (!$latestActivity): ?>
                <div class="empty">No attendance activity available.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Action</th>
                            <th>Timestamp</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($latestActivity as $activity): ?>
                            <tr>
                                <td>
                                    <strong><?= e($activity['employeeName'] ?? 'Unknown') ?></strong><br>
                                    <span class="muted"><?= e($activity['employeeId'] ?? '') ?></span>
                                </td>
                                <td><?= e($activity['department'] ?? '') ?></td>
                                <td><span class="badge"><?= e($activity['action'] ?? '') ?></span></td>
                                <td><?= e(formatDashboardDateTime($activity['timestamp'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="footer-note">
            Snapshot generated: <?= e(formatDashboardDateTime($dashboard['generatedAt'] ?? null)) ?>
        </div>

    <?php endif; ?>
</div>
</body>
</html>
