<?php

// --------------------------------------------------
// Facilities Toolbox - Site Operations
// --------------------------------------------------
//
// v0.3 site-level command view.
// Pulls one physical site's structure and workforce
// attendance health from the C# API.
// --------------------------------------------------

require_once __DIR__ . "/includes/api.php";

$siteCode = trim($_GET["code"] ?? "");
$siteData = [];
$errorMessage = null;

if ($siteCode === "") {
    $errorMessage = "Site code is required.";
} else {
    $result = facilitiesApiRequest(
        "GET",
        "/api/site-operations/" . rawurlencode($siteCode)
    );

    if ($result["success"] && is_array($result["data"])) {
        $siteData = $result["data"];
    } else {
        $errorMessage = $result["message"];
    }
}

$site = is_array($siteData["site"] ?? null)
    ? $siteData["site"]
    : [];

$buildings = is_array($siteData["buildings"] ?? null)
    ? $siteData["buildings"]
    : [];

$departments = is_array($siteData["departments"] ?? null)
    ? $siteData["departments"]
    : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Operations | Facilities Toolbox</title>
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

        <div class="nav-section-label">Facilities</div>
        <nav>
            <a class="nav-link active" href="structure.php">Structure</a>
            <a class="nav-link" href="shifts.php">Shifts</a>
        </nav>

        <div class="sidebar-footer">v0.3 Operations Core</div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <p class="eyebrow">Site Intelligence</p>
                <h2 class="page-title">
                    <?= htmlspecialchars($site["name"] ?? "Site Operations") ?>
                </h2>
                <p class="page-subtitle">
                    <?= htmlspecialchars($site["siteCode"] ?? $siteCode) ?>
                    <?php if (!empty($site["address"])): ?>
                        · <?= htmlspecialchars($site["address"]) ?>
                    <?php endif; ?>
                </p>
            </div>

            <a class="action-link" href="structure.php">Back to Structure</a>
        </header>

        <?php if ($errorMessage): ?>
            <div class="notice error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php else: ?>
            <section class="grid kpi-grid">
                <article class="kpi-card">
                    <p class="kpi-label">Buildings</p>
                    <p class="kpi-value"><?= (int)($siteData["buildingCount"] ?? 0) ?></p>
                    <p class="kpi-meta">Physical buildings at this site</p>
                </article>

                <article class="kpi-card">
                    <p class="kpi-label">Departments</p>
                    <p class="kpi-value"><?= (int)($siteData["departmentCount"] ?? 0) ?></p>
                    <p class="kpi-meta">Operational teams placed here</p>
                </article>

                <article class="kpi-card">
                    <p class="kpi-label">Present Now</p>
                    <p class="kpi-value"><?= (int)($siteData["presentNow"] ?? 0) ?></p>
                    <p class="kpi-meta"><?= (int)($siteData["activeEmployees"] ?? 0) ?> active employees assigned</p>
                </article>

                <article class="kpi-card">
                    <p class="kpi-label">Attendance</p>
                    <p class="kpi-value"><?= number_format((float)($siteData["attendanceRate"] ?? 0), 1) ?>%</p>
                    <p class="kpi-meta"><?= (int)($siteData["absentToday"] ?? 0) ?> not seen today</p>
                </article>
            </section>

            <section class="grid dashboard-grid">
                <article class="panel">
                    <div class="panel-header">
                        <div>
                            <h3 class="panel-title">Department Health</h3>
                            <p class="panel-description">Live workforce coverage by department.</p>
                        </div>
                    </div>

                    <?php if (!$departments): ?>
                        <div class="empty-state">No departments are assigned to this site yet.</div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Active</th>
                                    <th>Seen Today</th>
                                    <th>Present</th>
                                    <th>Coverage</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($departments as $department): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($department["name"] ?? "") ?></strong><br>
                                            <span style="color:var(--muted);font-size:.76rem;">
                                                <?= htmlspecialchars($department["departmentCode"] ?? "") ?>
                                            </span>
                                        </td>
                                        <td><?= (int)($department["activeEmployees"] ?? 0) ?></td>
                                        <td><?= (int)($department["seenToday"] ?? 0) ?></td>
                                        <td><?= (int)($department["presentNow"] ?? 0) ?></td>
                                        <td><?= number_format((float)($department["attendanceRate"] ?? 0), 1) ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </article>

                <article class="panel">
                    <div class="panel-header">
                        <div>
                            <h3 class="panel-title">Buildings</h3>
                            <p class="panel-description">Physical footprint for this site.</p>
                        </div>
                    </div>

                    <?php if (!$buildings): ?>
                        <div class="empty-state">No buildings have been created yet.</div>
                    <?php else: ?>
                        <div class="activity-list">
                            <?php foreach ($buildings as $building): ?>
                                <div class="activity-item" style="grid-template-columns:minmax(0,1fr) auto;">
                                    <div>
                                        <p class="activity-name"><?= htmlspecialchars($building["name"] ?? "") ?></p>
                                        <p class="activity-meta"><?= htmlspecialchars($building["buildingCode"] ?? "") ?></p>
                                    </div>
                                    <span class="badge <?= !empty($building["active"]) ? "active" : "inactive" ?>">
                                        <?= !empty($building["active"]) ? "Active" : "Inactive" ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
