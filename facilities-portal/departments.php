<?php

// --------------------------------------------------
// Facilities Toolbox - Department Operations
// --------------------------------------------------
//
// Department intelligence is derived from employee data
// through the C# API. PHP remains a presentation layer.
// --------------------------------------------------

require_once __DIR__ . "/includes/api.php";

$result = facilitiesApiRequest("GET", "/api/departments");

$departments =
    $result["success"] && is_array($result["data"])
        ? $result["data"]
        : [];

$errorMessage = $result["success"]
    ? null
    : $result["message"];

$totalDepartments = count($departments);
$totalActive = array_sum(array_map(
    fn(array $department): int => (int)($department["activeEmployees"] ?? 0),
    $departments
));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments | Facilities Toolbox</title>
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
            <a class="nav-link active" href="departments.php">Departments</a>
            <a class="nav-link" href="settings.php">Settings</a>
        </nav>

        <div class="sidebar-footer">v0.2 Operations Intelligence</div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <p class="eyebrow">Team Intelligence</p>
                <h2 class="page-title">Departments</h2>
                <p class="page-subtitle">
                    Workforce structure, staffing levels and role visibility by operational team.
                </p>
            </div>
            <span class="status-pill">API Connected</span>
        </header>

        <?php if ($errorMessage): ?>
            <div class="notice error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <section class="grid kpi-grid">
            <article class="kpi-card">
                <p class="kpi-label">Departments</p>
                <p class="kpi-value"><?= $totalDepartments ?></p>
                <p class="kpi-meta">Operational teams detected</p>
            </article>

            <article class="kpi-card">
                <p class="kpi-label">Active Staff</p>
                <p class="kpi-value"><?= $totalActive ?></p>
                <p class="kpi-meta">Active employees across all teams</p>
            </article>
        </section>

        <section class="grid" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr));">
            <?php foreach ($departments as $department): ?>
                <article class="panel">
                    <div class="panel-header">
                        <div>
                            <h3 class="panel-title">
                                <?= htmlspecialchars($department["department"] ?? "Unassigned") ?>
                            </h3>
                            <p class="panel-description">
                                <?= (int)($department["activeEmployees"] ?? 0) ?> active ·
                                <?= (int)($department["inactiveEmployees"] ?? 0) ?> inactive
                            </p>
                        </div>
                    </div>

                    <?php $roles = $department["roles"] ?? []; ?>
                    <div style="margin-bottom:16px;color:var(--muted);font-size:.82rem;">
                        Roles:
                        <?= $roles
                            ? htmlspecialchars(implode(", ", $roles))
                            : "No roles assigned" ?>
                    </div>

                    <div class="activity-list">
                        <?php foreach (($department["employees"] ?? []) as $employee): ?>
                            <a
                                class="activity-item"
                                href="profile.php?id=<?= rawurlencode($employee["employeeId"] ?? "") ?>"
                                style="text-decoration:none;grid-template-columns:minmax(0,1fr) auto;"
                            >
                                <div>
                                    <p class="activity-name">
                                        <?= htmlspecialchars($employee["name"] ?? "Unknown Employee") ?>
                                    </p>
                                    <p class="activity-meta">
                                        <?= htmlspecialchars($employee["employeeId"] ?? "") ?> ·
                                        <?= htmlspecialchars($employee["role"] ?? "Unassigned role") ?>
                                    </p>
                                </div>
                                <span class="badge <?= !empty($employee["active"]) ? "active" : "inactive" ?>">
                                    <?= !empty($employee["active"]) ? "Active" : "Inactive" ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</div>
</body>
</html>
