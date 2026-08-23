<?php

// --------------------------------------------------
// Facilities Toolbox - Work Order Command Centre
// --------------------------------------------------
//
// This page turns maintenance intake into controlled
// execution. It gives operators one place to:
//
// - triage reported maintenance requests
// - create work orders from requests
// - assign technicians
// - start / complete / cancel work
// - monitor backlog and critical faults
// - see simple completion-time intelligence
//
// All data and rules remain in the C# API.
// --------------------------------------------------

require_once __DIR__ . "/includes/api.php";

$successMessage = null;
$errorMessage = null;

// --------------------------------------------------
// Handle maintenance commands
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $formAction = $_POST["form_action"] ?? "";

    // Convert a maintenance request into a work order.
    if ($formAction === "create_work_order") {
        $requestCode = trim($_POST["request_code"] ?? "");

        $result = facilitiesApiRequest(
            "POST",
            "/api/maintenance-requests/" . rawurlencode($requestCode) . "/work-orders",
            [
                "title" => trim($_POST["title"] ?? ""),
                "assignedEmployeeId" => trim($_POST["employee_id"] ?? "")
            ]
        );

        if ($result["success"]) {
            $successMessage = "Work order created successfully.";
        } else {
            $errorMessage = $result["message"];
        }
    }

    // Assign or reassign an active work order.
    if ($formAction === "assign_work_order") {
        $workOrderCode = trim($_POST["work_order_code"] ?? "");

        $result = facilitiesApiRequest(
            "PATCH",
            "/api/maintenance/work-orders/" . rawurlencode($workOrderCode) . "/assign",
            [
                "employeeId" => trim($_POST["employee_id"] ?? "")
            ]
        );

        if ($result["success"]) {
            $successMessage = "$workOrderCode assigned successfully.";
        } else {
            $errorMessage = $result["message"];
        }
    }

    // Move work through the controlled status lifecycle.
    if ($formAction === "work_order_status") {
        $workOrderCode = trim($_POST["work_order_code"] ?? "");
        $status = trim($_POST["status"] ?? "");

        $result = facilitiesApiRequest(
            "PATCH",
            "/api/maintenance/work-orders/" . rawurlencode($workOrderCode) . "/status",
            [
                "status" => $status
            ]
        );

        if ($result["success"]) {
            $successMessage = "$workOrderCode moved to $status.";
        } else {
            $errorMessage = $result["message"];
        }
    }

    // Triage / resolve maintenance requests independently
    // when no work order is required.
    if ($formAction === "request_status") {
        $requestCode = trim($_POST["request_code"] ?? "");
        $status = trim($_POST["status"] ?? "");

        $result = facilitiesApiRequest(
            "PATCH",
            "/api/maintenance/requests/" . rawurlencode($requestCode) . "/status",
            [
                "status" => $status
            ]
        );

        if ($result["success"]) {
            $successMessage = "$requestCode moved to $status.";
        } else {
            $errorMessage = $result["message"];
        }
    }
}

// --------------------------------------------------
// Load command-centre data
// --------------------------------------------------

$overviewResult = facilitiesApiRequest("GET", "/api/maintenance/overview");
$requestResult = facilitiesApiRequest("GET", "/api/maintenance/requests");
$workOrderResult = facilitiesApiRequest("GET", "/api/maintenance/work-orders");
$employeeResult = facilitiesApiRequest("GET", "/api/employees");

$overview = $overviewResult["success"] && is_array($overviewResult["data"])
    ? $overviewResult["data"]
    : [];

$requests = $requestResult["success"] && is_array($requestResult["data"])
    ? $requestResult["data"]
    : [];

$workOrders = $workOrderResult["success"] && is_array($workOrderResult["data"])
    ? $workOrderResult["data"]
    : [];

$employees = $employeeResult["success"] && is_array($employeeResult["data"])
    ? $employeeResult["data"]
    : [];

foreach ([$overviewResult, $requestResult, $workOrderResult, $employeeResult] as $result) {
    if (!$result["success"] && $errorMessage === null) {
        $errorMessage = $result["message"];
    }
}

$openRequests = (int) ($overview["openRequests"] ?? 0);
$criticalOpen = (int) ($overview["criticalOpen"] ?? 0);
$openWorkOrders = (int) ($overview["openWorkOrders"] ?? 0);
$unassignedWorkOrders = (int) ($overview["unassignedWorkOrders"] ?? 0);
$inProgressWorkOrders = (int) ($overview["inProgressWorkOrders"] ?? 0);
$completedWorkOrders = (int) ($overview["completedWorkOrders"] ?? 0);
$averageCompletionHours = (float) ($overview["averageCompletionHours"] ?? 0);

// Prioritise active faults for the command centre.
$activeRequests = array_values(array_filter(
    $requests,
    fn($item) => !in_array(($item["status"] ?? ""), ["RESOLVED", "CLOSED"], true)
));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Orders | Facilities Toolbox</title>
    <link rel="stylesheet" href="assets/app.css">
    <style>
        .maintenance-kpis {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .command-grid {
            grid-template-columns: minmax(0, 1.25fr) minmax(340px, .75fr);
            align-items: start;
        }

        .priority-critical { color: #fecdd3; }
        .priority-high { color: #fde68a; }

        .work-order-card {
            padding: 16px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: rgba(255,255,255,.02);
        }

        .work-order-stack {
            display: grid;
            gap: 12px;
        }

        .work-order-head {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: flex-start;
        }

        .compact-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .compact-actions form {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .compact-actions select {
            width: auto;
            min-width: 150px;
        }

        @media (max-width: 1100px) {
            .maintenance-kpis {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .command-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .maintenance-kpis {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .maintenance-kpis .kpi-card {
                padding: 15px;
                min-height: 126px;
            }

            .maintenance-kpis .kpi-value {
                font-size: 1.55rem;
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
            <a class="nav-link" href="assets.php">Assets</a>
            <a class="nav-link active" href="maintenance.php">Work Orders</a>
        </nav>

        <div class="sidebar-footer">v0.3 Operations Core</div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <p class="eyebrow">Maintenance Execution</p>
                <h2 class="page-title">Work Order Command Centre</h2>
                <p class="page-subtitle">
                    Convert reported faults into assigned, trackable and measurable maintenance work.
                </p>
            </div>

            <?php if ($overviewResult["success"]): ?>
                <span class="status-pill">Maintenance Live</span>
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

        <!-- Maintenance intelligence KPIs -->
        <section class="grid maintenance-kpis" style="margin-bottom:18px;">
            <article class="kpi-card"><p class="kpi-label">Open Faults</p><p class="kpi-value"><?= $openRequests ?></p><p class="kpi-meta">Unresolved maintenance requests</p></article>
            <article class="kpi-card"><p class="kpi-label">Critical</p><p class="kpi-value"><?= $criticalOpen ?></p><p class="kpi-meta">Critical faults still open</p></article>
            <article class="kpi-card"><p class="kpi-label">Open Work Orders</p><p class="kpi-value"><?= $openWorkOrders ?></p><p class="kpi-meta"><?= $unassignedWorkOrders ?> currently unassigned</p></article>
            <article class="kpi-card"><p class="kpi-label">In Progress</p><p class="kpi-value"><?= $inProgressWorkOrders ?></p><p class="kpi-meta">Maintenance currently executing</p></article>
            <article class="kpi-card"><p class="kpi-label">Completed</p><p class="kpi-value"><?= $completedWorkOrders ?></p><p class="kpi-meta">Completed work-order records</p></article>
            <article class="kpi-card"><p class="kpi-label">Avg Completion</p><p class="kpi-value"><?= number_format($averageCompletionHours, 1) ?>h</p><p class="kpi-meta">Created → completed average</p></article>
        </section>

        <section class="grid command-grid">
            <!-- --------------------------------------------------
                 Work-order execution board
            --------------------------------------------------- -->
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <p class="eyebrow">Execution Board</p>
                        <h3 class="panel-title">Active Work Orders</h3>
                        <p class="panel-description">
                            Assignment and execution state for maintenance work.
                        </p>
                    </div>
                </div>

                <?php if (!$workOrders): ?>
                    <div class="empty-state">
                        No work orders yet. Create one from a maintenance request.
                    </div>
                <?php else: ?>
                    <div class="work-order-stack">
                        <?php foreach ($workOrders as $order): ?>
                            <?php
                            $status = strtoupper($order["status"] ?? "OPEN");
                            $priority = strtoupper($order["priority"] ?? "MEDIUM");
                            ?>
                            <article class="work-order-card">
                                <div class="work-order-head">
                                    <div>
                                        <strong><?= htmlspecialchars($order["title"] ?? "") ?></strong><br>
                                        <span style="color:var(--muted);font-size:.76rem;">
                                            <?= htmlspecialchars(($order["workOrderCode"] ?? "") . " · " . ($order["assetCode"] ?? "")) ?>
                                        </span>
                                    </div>
                                    <span class="badge <?= $status === "COMPLETED" ? "active" : ($status === "IN_PROGRESS" ? "in" : "out") ?>">
                                        <?= htmlspecialchars(str_replace("_", " ", $status)) ?>
                                    </span>
                                </div>

                                <p style="margin:12px 0 0;color:var(--muted);font-size:.82rem;">
                                    <?= htmlspecialchars(($order["siteName"] ?? "") . (!empty($order["buildingName"]) ? " / " . $order["buildingName"] : "")) ?>
                                    · Priority <?= htmlspecialchars($priority) ?>
                                    · Age <?= htmlspecialchars((string) ($order["ageHours"] ?? 0)) ?>h
                                </p>

                                <p style="margin:7px 0 0;font-size:.82rem;">
                                    Assigned: <strong><?= htmlspecialchars($order["assignedEmployeeName"] ?? "Unassigned") ?></strong>
                                </p>

                                <?php if (!in_array($status, ["COMPLETED", "CANCELLED"], true)): ?>
                                    <div class="compact-actions">
                                        <form method="POST">
                                            <input type="hidden" name="form_action" value="assign_work_order">
                                            <input type="hidden" name="work_order_code" value="<?= htmlspecialchars($order["workOrderCode"] ?? "") ?>">
                                            <select name="employee_id" required>
                                                <option value="">Assign technician</option>
                                                <?php foreach ($employees as $employee): ?>
                                                    <?php if (!empty($employee["active"])): ?>
                                                        <option value="<?= htmlspecialchars($employee["employeeId"] ?? "") ?>">
                                                            <?= htmlspecialchars(($employee["name"] ?? "") . " · " . ($employee["employeeId"] ?? "")) ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="button" type="submit">Assign</button>
                                        </form>

                                        <?php if (!empty($order["assignedEmployeeId"])): ?>
                                            <form method="POST">
                                                <input type="hidden" name="form_action" value="work_order_status">
                                                <input type="hidden" name="work_order_code" value="<?= htmlspecialchars($order["workOrderCode"] ?? "") ?>">
                                                <?php if ($status !== "IN_PROGRESS"): ?>
                                                    <button class="button primary" name="status" value="IN_PROGRESS" type="submit">Start Work</button>
                                                <?php endif; ?>
                                                <button class="button success" name="status" value="COMPLETED" type="submit">Complete</button>
                                                <button class="button danger" name="status" value="CANCELLED" type="submit">Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- --------------------------------------------------
                 Fault triage queue
            --------------------------------------------------- -->
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <p class="eyebrow">Fault Queue</p>
                        <h3 class="panel-title">Maintenance Backlog</h3>
                        <p class="panel-description">
                            Reported faults waiting for triage or execution.
                        </p>
                    </div>
                </div>

                <?php if (!$activeRequests): ?>
                    <div class="empty-state">No unresolved maintenance requests.</div>
                <?php else: ?>
                    <div class="work-order-stack">
                        <?php foreach ($activeRequests as $request): ?>
                            <?php
                            $priority = strtoupper($request["priority"] ?? "MEDIUM");
                            $hasOpenWorkOrder = !empty($request["hasOpenWorkOrder"]);
                            ?>
                            <article class="work-order-card">
                                <div class="work-order-head">
                                    <div>
                                        <strong class="<?= $priority === "CRITICAL" ? "priority-critical" : ($priority === "HIGH" ? "priority-high" : "") ?>">
                                            <?= htmlspecialchars($request["title"] ?? "") ?>
                                        </strong><br>
                                        <span style="color:var(--muted);font-size:.76rem;">
                                            <?= htmlspecialchars(($request["requestCode"] ?? "") . " · " . ($request["assetCode"] ?? "")) ?>
                                        </span>
                                    </div>
                                    <span class="badge <?= $priority === "CRITICAL" ? "inactive" : "out" ?>">
                                        <?= htmlspecialchars($priority) ?>
                                    </span>
                                </div>

                                <p style="margin:10px 0;color:var(--muted);font-size:.8rem;">
                                    <?= htmlspecialchars($request["description"] ?? "No description") ?>
                                </p>

                                <?php if (!$hasOpenWorkOrder): ?>
                                    <form method="POST" class="grid" style="gap:8px;margin-top:12px;">
                                        <input type="hidden" name="form_action" value="create_work_order">
                                        <input type="hidden" name="request_code" value="<?= htmlspecialchars($request["requestCode"] ?? "") ?>">
                                        <input type="text" name="title" value="<?= htmlspecialchars($request["title"] ?? "") ?>" required>
                                        <select name="employee_id">
                                            <option value="">Create unassigned</option>
                                            <?php foreach ($employees as $employee): ?>
                                                <?php if (!empty($employee["active"])): ?>
                                                    <option value="<?= htmlspecialchars($employee["employeeId"] ?? "") ?>">
                                                        <?= htmlspecialchars(($employee["name"] ?? "") . " · " . ($employee["employeeId"] ?? "")) ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="button primary" type="submit">Create Work Order</button>
                                    </form>
                                <?php else: ?>
                                    <div class="notice" style="margin:12px 0 0;">Execution work order already open.</div>
                                <?php endif; ?>

                                <form method="POST" class="compact-actions" style="margin-top:10px;">
                                    <input type="hidden" name="form_action" value="request_status">
                                    <input type="hidden" name="request_code" value="<?= htmlspecialchars($request["requestCode"] ?? "") ?>">
                                    <button class="button" name="status" value="TRIAGED" type="submit">Triage</button>
                                    <button class="button success" name="status" value="RESOLVED" type="submit">Resolve</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </section>
    </main>
</div>
</body>
</html>
