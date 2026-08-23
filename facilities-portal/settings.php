<?php

// --------------------------------------------------
// Facilities Toolbox - Operations Settings
// --------------------------------------------------
//
// v0.2 exposes the effective shift policy used by the
// API. Policy values are configured in appsettings.json
// and can later move into PostgreSQL for live editing.
// --------------------------------------------------

require_once __DIR__ . "/includes/api.php";

$result = facilitiesApiRequest("GET", "/api/shift-policy");

$policy =
    $result["success"] && is_array($result["data"])
        ? $result["data"]
        : [];

$errorMessage = $result["success"]
    ? null
    : $result["message"];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Facilities Toolbox</title>
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
            <a class="nav-link" href="reports.php">Reports</a>
        </nav>

        <div class="nav-section-label">System</div>
        <nav>
            <a class="nav-link" href="departments.php">Departments</a>
            <a class="nav-link active" href="settings.php">Settings</a>
        </nav>

        <div class="sidebar-footer">v0.2 Operations Intelligence</div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <p class="eyebrow">Policy Control</p>
                <h2 class="page-title">Operations Settings</h2>
                <p class="page-subtitle">
                    Effective attendance rules used by punctuality, alerts and reporting.
                </p>
            </div>
            <span class="status-pill">Policy Loaded</span>
        </header>

        <?php if ($errorMessage): ?>
            <div class="notice error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <section class="grid kpi-grid">
            <article class="kpi-card">
                <p class="kpi-label">Shift Start</p>
                <p class="kpi-value"><?= htmlspecialchars($policy["startTime"] ?? "--") ?></p>
                <p class="kpi-meta">South Africa local time</p>
            </article>

            <article class="kpi-card">
                <p class="kpi-label">Grace Period</p>
                <p class="kpi-value"><?= (int)($policy["graceMinutes"] ?? 0) ?>m</p>
                <p class="kpi-meta">Allowed before late status</p>
            </article>

            <article class="kpi-card">
                <p class="kpi-label">Missing Check-In Alert</p>
                <p class="kpi-value"><?= htmlspecialchars($policy["missingCheckInAlertTime"] ?? "--") ?></p>
                <p class="kpi-meta">Alert evaluation begins</p>
            </article>

            <article class="kpi-card">
                <p class="kpi-label">Coverage Threshold</p>
                <p class="kpi-value"><?= number_format((float)($policy["minimumAttendanceRate"] ?? 0), 0) ?>%</p>
                <p class="kpi-meta">Below this triggers a signal</p>
            </article>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">Policy Source</h3>
                    <p class="panel-description">
                        v0.2 reads these values from the ASP.NET Core configuration layer.
                    </p>
                </div>
            </div>

            <div class="notice">
                Live editing is intentionally deferred until policy persistence and audit history are added.
                For now, operations rules stay version-controlled and predictable.
            </div>

            <div class="progress-card">
                <div class="progress-row">
                    <span style="color:var(--muted);">Long session warning</span>
                    <strong><?= number_format((float)($policy["longSessionHours"] ?? 0), 1) ?>h</strong>
                </div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
