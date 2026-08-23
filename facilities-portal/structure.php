<?php

// --------------------------------------------------
// Facilities Toolbox - Physical Structure Workspace
// --------------------------------------------------
//
// v0.3 management page for Sites, Buildings and
// structured Departments.
// --------------------------------------------------

require_once __DIR__ . "/includes/api.php";

$successMessage = null;
$errorMessage = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["form_action"] ?? "";

    if ($action === "site") {
        $result = facilitiesApiRequest("POST", "/api/sites", [
            "siteCode" => trim($_POST["site_code"] ?? ""),
            "name" => trim($_POST["site_name"] ?? ""),
            "address" => trim($_POST["site_address"] ?? "")
        ]);

        $successMessage = $result["success"]
            ? "Site created successfully."
            : null;
        $errorMessage = $result["success"]
            ? null
            : $result["message"];
    }

    if ($action === "building") {
        $result = facilitiesApiRequest("POST", "/api/buildings", [
            "buildingCode" => trim($_POST["building_code"] ?? ""),
            "siteCode" => trim($_POST["building_site_code"] ?? ""),
            "name" => trim($_POST["building_name"] ?? ""),
            "description" => trim($_POST["building_description"] ?? "")
        ]);

        $successMessage = $result["success"]
            ? "Building created successfully."
            : null;
        $errorMessage = $result["success"]
            ? null
            : $result["message"];
    }

    if ($action === "department") {
        $result = facilitiesApiRequest("POST", "/api/departments-v3", [
            "departmentCode" => trim($_POST["department_code"] ?? ""),
            "name" => trim($_POST["department_name"] ?? ""),
            "siteCode" => trim($_POST["department_site_code"] ?? ""),
            "buildingCode" => trim($_POST["department_building_code"] ?? "")
        ]);

        $successMessage = $result["success"]
            ? "Department created successfully."
            : null;
        $errorMessage = $result["success"]
            ? null
            : $result["message"];
    }
}

$sitesResult = facilitiesApiRequest("GET", "/api/sites");
$buildingsResult = facilitiesApiRequest("GET", "/api/buildings");
$departmentsResult = facilitiesApiRequest("GET", "/api/departments-v3");

$sites = $sitesResult["success"] && is_array($sitesResult["data"])
    ? $sitesResult["data"] : [];
$buildings = $buildingsResult["success"] && is_array($buildingsResult["data"])
    ? $buildingsResult["data"] : [];
$departments = $departmentsResult["success"] && is_array($departmentsResult["data"])
    ? $departmentsResult["data"] : [];

foreach ([$sitesResult, $buildingsResult, $departmentsResult] as $result) {
    if (!$result["success"] && $errorMessage === null) {
        $errorMessage = $result["message"];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facilities Structure | Facilities Toolbox</title>
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
                <p class="eyebrow">Physical Operations</p>
                <h2 class="page-title">Facilities Structure</h2>
                <p class="page-subtitle">
                    Model the real-world hierarchy of sites, buildings and operational departments.
                </p>
            </div>
            <span class="status-pill">Operations Core</span>
        </header>

        <?php if ($successMessage): ?>
            <div class="notice success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="notice error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <section class="grid" style="grid-template-columns:repeat(3,minmax(0,1fr));margin-bottom:18px;">
            <article class="kpi-card"><p class="kpi-label">Sites</p><p class="kpi-value"><?= count($sites) ?></p><p class="kpi-meta">Managed locations</p></article>
            <article class="kpi-card"><p class="kpi-label">Buildings</p><p class="kpi-value"><?= count($buildings) ?></p><p class="kpi-meta">Physical structures</p></article>
            <article class="kpi-card"><p class="kpi-label">Departments</p><p class="kpi-value"><?= count($departments) ?></p><p class="kpi-meta">Operational teams</p></article>
        </section>

        <section class="grid" style="grid-template-columns:repeat(3,minmax(0,1fr));margin-bottom:18px;">
            <article class="panel">
                <div class="panel-header"><div><h3 class="panel-title">Add Site</h3><p class="panel-description">Create a managed location.</p></div></div>
                <form method="POST" class="grid">
                    <input type="hidden" name="form_action" value="site">
                    <div class="field"><label>Site Code</label><input name="site_code" placeholder="SITE001" required></div>
                    <div class="field"><label>Name</label><input name="site_name" placeholder="Head Office" required></div>
                    <div class="field"><label>Address</label><input name="site_address" placeholder="Johannesburg"></div>
                    <button class="button primary" type="submit">Create Site</button>
                </form>
            </article>

            <article class="panel">
                <div class="panel-header"><div><h3 class="panel-title">Add Building</h3><p class="panel-description">Attach a building to a site.</p></div></div>
                <form method="POST" class="grid">
                    <input type="hidden" name="form_action" value="building">
                    <div class="field"><label>Building Code</label><input name="building_code" placeholder="BLD001" required></div>
                    <div class="field"><label>Site</label><select name="building_site_code" required><option value="">Select site</option><?php foreach ($sites as $site): ?><option value="<?= htmlspecialchars($site["siteCode"] ?? "") ?>"><?= htmlspecialchars(($site["siteCode"] ?? "") . " · " . ($site["name"] ?? "")) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label>Name</label><input name="building_name" placeholder="Administration Block" required></div>
                    <div class="field"><label>Description</label><input name="building_description" placeholder="Main office building"></div>
                    <button class="button primary" type="submit">Create Building</button>
                </form>
            </article>

            <article class="panel">
                <div class="panel-header"><div><h3 class="panel-title">Add Department</h3><p class="panel-description">Place a team inside the facility hierarchy.</p></div></div>
                <form method="POST" class="grid">
                    <input type="hidden" name="form_action" value="department">
                    <div class="field"><label>Department Code</label><input name="department_code" placeholder="DEP001" required></div>
                    <div class="field"><label>Name</label><input name="department_name" placeholder="Facilities" required></div>
                    <div class="field"><label>Site</label><select name="department_site_code"><option value="">Optional</option><?php foreach ($sites as $site): ?><option value="<?= htmlspecialchars($site["siteCode"] ?? "") ?>"><?= htmlspecialchars($site["name"] ?? "") ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label>Building</label><select name="department_building_code"><option value="">Optional</option><?php foreach ($buildings as $building): ?><option value="<?= htmlspecialchars($building["buildingCode"] ?? "") ?>"><?= htmlspecialchars(($building["buildingCode"] ?? "") . " · " . ($building["name"] ?? "")) ?></option><?php endforeach; ?></select></div>
                    <button class="button primary" type="submit">Create Department</button>
                </form>
            </article>
        </section>

        <section class="panel">
            <div class="panel-header"><div><h3 class="panel-title">Facility Hierarchy</h3><p class="panel-description">Current structured operational footprint.</p></div></div>
            <?php if (!$sites): ?>
                <div class="empty-state">Create your first site to begin modelling the facility.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Site</th><th>Address</th><th>Buildings</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($sites as $site): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($site["name"] ?? "") ?></strong><br><span style="color:var(--muted);font-size:.76rem;"><?= htmlspecialchars($site["siteCode"] ?? "") ?></span></td>
                                <td><?= htmlspecialchars($site["address"] ?? "") ?></td>
                                <td><?= (int) ($site["buildingCount"] ?? 0) ?></td>
                                <td><span class="badge <?= !empty($site["active"]) ? "active" : "inactive" ?>"><?= !empty($site["active"]) ? "Active" : "Inactive" ?></span></td>
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
