<?php

// --------------------------------------------------
// Facilities Toolbox - Asset Operations Workspace
// --------------------------------------------------
//
// First dedicated facilities-maintenance page.
//
// The portal can:
// - register assets against real sites / buildings
// - view maintenance health KPIs
// - raise maintenance requests
// - record inspections
//
// Work-order execution remains API-first in this slice and
// will receive its own dedicated workflow next.
// --------------------------------------------------

require_once __DIR__ . "/includes/api.php";

$successMessage = null;
$errorMessage = null;

// --------------------------------------------------
// Handle asset / maintenance commands
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $formAction = $_POST["form_action"] ?? "";

    if ($formAction === "create_asset") {
        $result = facilitiesApiRequest(
            "POST",
            "/api/assets",
            [
                "assetCode" => trim($_POST["asset_code"] ?? ""),
                "name" => trim($_POST["name"] ?? ""),
                "category" => trim($_POST["category"] ?? ""),
                "serialNumber" => trim($_POST["serial_number"] ?? ""),
                "locationNote" => trim($_POST["location_note"] ?? ""),
                "siteCode" => trim($_POST["site_code"] ?? ""),
                "buildingCode" => trim($_POST["building_code"] ?? "")
            ]
        );

        if ($result["success"]) {
            $successMessage = "Asset registered successfully.";
        } else {
            $errorMessage = $result["message"];
        }
    }

    if ($formAction === "maintenance_request") {
        $assetCode = trim($_POST["asset_code"] ?? "");

        $result = facilitiesApiRequest(
            "POST",
            "/api/assets/" . rawurlencode($assetCode) . "/maintenance-requests",
            [
                "title" => trim($_POST["title"] ?? ""),
                "description" => trim($_POST["description"] ?? ""),
                "priority" => trim($_POST["priority"] ?? "MEDIUM"),
                "reportedByEmployeeId" => trim($_POST["reported_by"] ?? "")
            ]
        );

        if ($result["success"]) {
            $successMessage = "Maintenance request raised successfully.";
        } else {
            $errorMessage = $result["message"];
        }
    }

    if ($formAction === "inspection") {
        $assetCode = trim($_POST["asset_code"] ?? "");

        $result = facilitiesApiRequest(
            "POST",
            "/api/assets/" . rawurlencode($assetCode) . "/inspections",
            [
                "result" => trim($_POST["result"] ?? "PASS"),
                "notes" => trim($_POST["notes"] ?? ""),
                "inspectorEmployeeId" => trim($_POST["inspector"] ?? "")
            ]
        );

        if ($result["success"]) {
            $successMessage = "Inspection recorded successfully.";
        } else {
            $errorMessage = $result["message"];
        }
    }
}

// --------------------------------------------------
// Load live asset operations data
// --------------------------------------------------

$overviewResult = facilitiesApiRequest("GET", "/api/assets/overview");
$assetsResult = facilitiesApiRequest("GET", "/api/assets");
$sitesResult = facilitiesApiRequest("GET", "/api/sites");
$buildingsResult = facilitiesApiRequest("GET", "/api/buildings");
$employeesResult = facilitiesApiRequest("GET", "/api/employees");

$overview = $overviewResult["success"] && is_array($overviewResult["data"])
    ? $overviewResult["data"]
    : [];

$assets = $assetsResult["success"] && is_array($assetsResult["data"])
    ? $assetsResult["data"]
    : [];

$sites = $sitesResult["success"] && is_array($sitesResult["data"])
    ? $sitesResult["data"]
    : [];

$buildings = $buildingsResult["success"] && is_array($buildingsResult["data"])
    ? $buildingsResult["data"]
    : [];

$employees = $employeesResult["success"] && is_array($employeesResult["data"])
    ? $employeesResult["data"]
    : [];

foreach ([$overviewResult, $assetsResult, $sitesResult, $buildingsResult] as $result) {
    if (!$result["success"] && $errorMessage === null) {
        $errorMessage = $result["message"];
    }
}

$totalAssets = (int) ($overview["totalAssets"] ?? 0);
$activeAssets = (int) ($overview["activeAssets"] ?? 0);
$openRequests = (int) ($overview["openRequests"] ?? 0);
$criticalRequests = (int) ($overview["criticalRequests"] ?? 0);
$openWorkOrders = (int) ($overview["openWorkOrders"] ?? 0);
$failedInspections = (int) ($overview["failedInspections"] ?? 0);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Operations | Facilities Toolbox</title>
    <link rel="stylesheet" href="assets/app.css">
    <style>
        .asset-kpis {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .asset-actions {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            align-items: start;
        }

        @media (max-width: 1050px) {
            .asset-kpis,
            .asset-actions {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .asset-kpis {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .asset-kpis .kpi-card {
                padding: 15px;
                min-height: 128px;
            }

            .asset-kpis .kpi-value {
                font-size: 1.55rem;
            }

            .asset-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
            <a class="nav-link" href="workforce.php">Who Is Where</a>
            <a class="nav-link" href="attendance.php">Attendance</a>
            <a class="nav-link" href="reports.php">Reports</a>
        </nav>

        <div class="nav-section-label">Facilities</div>
        <nav>
            <a class="nav-link" href="structure.php">Structure</a>
            <a class="nav-link" href="shifts.php">Shifts</a>
            <a class="nav-link active" href="assets.php">Assets</a>
        </nav>

        <div class="sidebar-footer">v0.3 Operations Core</div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <p class="eyebrow">Facilities Maintenance</p>
                <h2 class="page-title">Asset Operations</h2>
                <p class="page-subtitle">
                    Register physical assets, surface maintenance risk and capture inspection evidence from one workspace.
                </p>
            </div>

            <?php if ($overviewResult["success"]): ?>
                <span class="status-pill">Asset Core Live</span>
            <?php else: ?>
                <span class="badge inactive">API Offline</span>
            <?php endif; ?>
        </header>

        <?php if ($successMessage): ?>
            <div class="notice success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="notice error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <section class="grid asset-kpis" style="margin-bottom:18px;">
            <article class="kpi-card"><p class="kpi-label">Assets</p><p class="kpi-value"><?= $totalAssets ?></p><p class="kpi-meta"><?= $activeAssets ?> active</p></article>
            <article class="kpi-card"><p class="kpi-label">Open Requests</p><p class="kpi-value"><?= $openRequests ?></p><p class="kpi-meta">Faults awaiting resolution</p></article>
            <article class="kpi-card"><p class="kpi-label">Critical</p><p class="kpi-value"><?= $criticalRequests ?></p><p class="kpi-meta">Critical unresolved requests</p></article>
            <article class="kpi-card"><p class="kpi-label">Work Orders</p><p class="kpi-value"><?= $openWorkOrders ?></p><p class="kpi-meta">Open maintenance execution</p></article>
            <article class="kpi-card"><p class="kpi-label">Failed Inspections</p><p class="kpi-value"><?= $failedInspections ?></p><p class="kpi-meta">Recorded FAIL results</p></article>
            <article class="kpi-card"><p class="kpi-label">Asset Health</p><p class="kpi-value"><?= $criticalRequests === 0 ? "OK" : "CHECK" ?></p><p class="kpi-meta">Based on unresolved critical faults</p></article>
        </section>

        <section class="grid asset-actions" style="margin-bottom:18px;">
            <article class="panel">
                <div class="panel-header"><div><p class="eyebrow">Asset Register</p><h3 class="panel-title">Register Asset</h3><p class="panel-description">Attach equipment to a real site and building.</p></div></div>
                <form method="POST" class="grid" style="gap:12px;">
                    <input type="hidden" name="form_action" value="create_asset">
                    <div class="field"><label>Asset Code</label><input name="asset_code" placeholder="AST001" required></div>
                    <div class="field"><label>Name</label><input name="name" placeholder="Main Generator" required></div>
                    <div class="field"><label>Category</label><input name="category" placeholder="Electrical"></div>
                    <div class="field"><label>Serial Number</label><input name="serial_number" placeholder="Optional"></div>
                    <div class="field"><label>Site</label><select name="site_code" required><option value="">Select site</option><?php foreach ($sites as $site): ?><option value="<?= htmlspecialchars($site["siteCode"] ?? "") ?>"><?= htmlspecialchars(($site["siteCode"] ?? "") . " · " . ($site["name"] ?? "")) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label>Building</label><select name="building_code"><option value="">Site-wide / outdoor</option><?php foreach ($buildings as $building): ?><option value="<?= htmlspecialchars($building["buildingCode"] ?? "") ?>"><?= htmlspecialchars(($building["buildingCode"] ?? "") . " · " . ($building["name"] ?? "")) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label>Location Note</label><input name="location_note" placeholder="Plant room / roof / gate"></div>
                    <button class="button primary" type="submit">Register Asset</button>
                </form>
            </article>

            <article class="panel">
                <div class="panel-header"><div><p class="eyebrow">Fault Intake</p><h3 class="panel-title">Raise Maintenance Request</h3><p class="panel-description">Record a fault before work is planned.</p></div></div>
                <form method="POST" class="grid" style="gap:12px;">
                    <input type="hidden" name="form_action" value="maintenance_request">
                    <div class="field"><label>Asset</label><select name="asset_code" required><option value="">Select asset</option><?php foreach ($assets as $asset): ?><option value="<?= htmlspecialchars($asset["assetCode"] ?? "") ?>"><?= htmlspecialchars(($asset["assetCode"] ?? "") . " · " . ($asset["name"] ?? "")) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label>Title</label><input name="title" placeholder="Generator fails to start" required></div>
                    <div class="field"><label>Priority</label><select name="priority"><option>LOW</option><option selected>MEDIUM</option><option>HIGH</option><option>CRITICAL</option></select></div>
                    <div class="field"><label>Reported By</label><select name="reported_by"><option value="">Not specified</option><?php foreach ($employees as $employee): ?><option value="<?= htmlspecialchars($employee["employeeId"] ?? "") ?>"><?= htmlspecialchars(($employee["name"] ?? "") . " · " . ($employee["employeeId"] ?? "")) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label>Description</label><input name="description" placeholder="Short operational description"></div>
                    <button class="button primary" type="submit">Raise Request</button>
                </form>
            </article>

            <article class="panel">
                <div class="panel-header"><div><p class="eyebrow">Inspection Evidence</p><h3 class="panel-title">Record Inspection</h3><p class="panel-description">Capture a quick operational condition result.</p></div></div>
                <form method="POST" class="grid" style="gap:12px;">
                    <input type="hidden" name="form_action" value="inspection">
                    <div class="field"><label>Asset</label><select name="asset_code" required><option value="">Select asset</option><?php foreach ($assets as $asset): ?><option value="<?= htmlspecialchars($asset["assetCode"] ?? "") ?>"><?= htmlspecialchars(($asset["assetCode"] ?? "") . " · " . ($asset["name"] ?? "")) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label>Result</label><select name="result"><option>PASS</option><option>ATTENTION</option><option>FAIL</option></select></div>
                    <div class="field"><label>Inspector</label><select name="inspector"><option value="">Not specified</option><?php foreach ($employees as $employee): ?><option value="<?= htmlspecialchars($employee["employeeId"] ?? "") ?>"><?= htmlspecialchars(($employee["name"] ?? "") . " · " . ($employee["employeeId"] ?? "")) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label>Notes</label><input name="notes" placeholder="Condition / observation"></div>
                    <button class="button primary" type="submit">Record Inspection</button>
                </form>
            </article>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div><p class="eyebrow">Asset Register</p><h3 class="panel-title">Managed Assets</h3><p class="panel-description"><?= count($assets) ?> assets loaded from PostgreSQL.</p></div>
            </div>

            <?php if (!$assets): ?>
                <div class="empty-state">No assets registered yet. Add the first physical asset above.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Asset</th><th>Category</th><th>Site / Building</th><th>Location</th><th>Open Faults</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($assets as $asset): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($asset["name"] ?? "") ?></strong><br><span style="color:var(--muted);font-size:.76rem;"><?= htmlspecialchars($asset["assetCode"] ?? "") ?></span></td>
                                <td><?= htmlspecialchars($asset["category"] ?? "Uncategorised") ?></td>
                                <td><?= htmlspecialchars($asset["siteName"] ?? "--") ?><br><span style="color:var(--muted);font-size:.76rem;"><?= htmlspecialchars($asset["buildingName"] ?? "Site-wide") ?></span></td>
                                <td><?= htmlspecialchars($asset["locationNote"] ?? "--") ?></td>
                                <td><?= (int) ($asset["openRequests"] ?? 0) ?></td>
                                <td><span class="badge <?= !empty($asset["active"]) ? "active" : "inactive" ?>"><?= !empty($asset["active"]) ? "Active" : "Inactive" ?></span></td>
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
