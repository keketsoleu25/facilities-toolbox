<?php

// --------------------------------------------------
// Facilities Toolbox - Employee Management Workspace
// --------------------------------------------------
//
// v0.2 upgrades employee management into a proper
// operational workspace. The page supports:
//
// - employee creation
// - employee editing
// - activation / deactivation
// - client-side search
// - department filtering
// - status filtering
//
// All writes continue to flow through the C# API.
// --------------------------------------------------

require_once __DIR__ . "/includes/api.php";

$successMessage = null;
$errorMessage = null;


// --------------------------------------------------
// Handle employee commands
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $formAction =
        $_POST["form_action"] ?? "";


    // Create employee.
    if ($formAction === "create") {
        $result = facilitiesApiRequest(
            "POST",
            "/api/employees",
            [
                "employeeId" => trim($_POST["employee_id"] ?? ""),
                "name" => trim($_POST["name"] ?? ""),
                "department" => trim($_POST["department"] ?? ""),
                "role" => trim($_POST["role"] ?? "")
            ]
        );

        if ($result["success"]) {
            $successMessage = "Employee created successfully.";
        } else {
            $errorMessage = $result["message"];
        }
    }


    // Edit employee profile.
    if ($formAction === "edit") {
        $employeeId =
            trim($_POST["employee_id"] ?? "");

        $result = facilitiesApiRequest(
            "PUT",
            "/api/employees/" . rawurlencode($employeeId),
            [
                "name" => trim($_POST["name"] ?? ""),
                "department" => trim($_POST["department"] ?? ""),
                "role" => trim($_POST["role"] ?? "")
            ]
        );

        if ($result["success"]) {
            $successMessage = "$employeeId updated successfully.";
        } else {
            $errorMessage = $result["message"];
        }
    }


    // Activate or deactivate employee.
    if ($formAction === "status") {
        $employeeId =
            trim($_POST["employee_id"] ?? "");

        $active =
            ($_POST["active"] ?? "0") === "1";

        $result = facilitiesApiRequest(
            "PATCH",
            "/api/employees/"
                . rawurlencode($employeeId)
                . "/status",
            [
                "active" => $active
            ]
        );

        if ($result["success"]) {
            $successMessage =
                $active
                    ? "$employeeId activated."
                    : "$employeeId deactivated.";
        } else {
            $errorMessage = $result["message"];
        }
    }
}


// --------------------------------------------------
// Load employee directory
// --------------------------------------------------

$employeeResult =
    facilitiesApiRequest(
        "GET",
        "/api/employees"
    );

$employees = [];

if ($employeeResult["success"]) {
    $employees =
        is_array($employeeResult["data"])
            ? $employeeResult["data"]
            : [];
} else {
    $errorMessage =
        $employeeResult["message"];
}


// Build department filter options from real API data.
$departments = [];

foreach ($employees as $employee) {
    $department = trim($employee["department"] ?? "");

    if (
        $department !== "" &&
        !in_array($department, $departments, true)
    ) {
        $departments[] = $department;
    }
}

sort($departments);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees | Facilities Toolbox</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app-shell">

    <!-- --------------------------------------------------
         Product navigation
    --------------------------------------------------- -->
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">FT</div>
            <h1>Facilities Toolbox</h1>
            <p>The Tech Alchemy Lab</p>
        </div>

        <div class="nav-section-label">Operations</div>
        <nav>
            <a class="nav-link" href="index.php">Dashboard</a>
            <a class="nav-link active" href="employees.php">Employees</a>
            <a class="nav-link" href="attendance.php">Attendance</a>
            <a class="nav-link" href="reports.php">Reports</a>
        </nav>

        <div class="sidebar-footer">
            v0.2 Operations Intelligence
        </div>
    </aside>


    <main class="main">
        <header class="topbar">
            <div>
                <p class="eyebrow">Workforce Operations</p>
                <h2 class="page-title">Employee Directory</h2>
                <p class="page-subtitle">
                    Manage staff records, access status and operational ownership from one workspace.
                </p>
            </div>

            <span class="status-pill">API Connected</span>
        </header>


        <?php if ($successMessage): ?>
            <div class="notice success">
                <?= htmlspecialchars($successMessage) ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="notice error">
                <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>


        <!-- --------------------------------------------------
             Add employee
        --------------------------------------------------- -->
        <section class="panel" style="margin-bottom: 18px;">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">Add Employee</h3>
                    <p class="panel-description">
                        Create a staff record that can participate in attendance operations.
                    </p>
                </div>
            </div>

            <form method="POST" class="form-grid">
                <input type="hidden" name="form_action" value="create">

                <div class="field">
                    <label for="employee_id">Employee ID</label>
                    <input id="employee_id" name="employee_id" placeholder="EMP004" required>
                </div>

                <div class="field">
                    <label for="name">Full Name</label>
                    <input id="name" name="name" placeholder="Employee name" required>
                </div>

                <div class="field">
                    <label for="department">Department</label>
                    <input id="department" name="department" placeholder="Facilities">
                </div>

                <div class="field">
                    <label for="role">Role</label>
                    <input id="role" name="role" placeholder="Technician">
                </div>

                <div>
                    <button class="button primary" type="submit">Add Employee</button>
                </div>
            </form>
        </section>


        <!-- --------------------------------------------------
             Search and filter controls
        --------------------------------------------------- -->
        <section class="panel" style="margin-bottom: 18px;">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">Search & Filters</h3>
                    <p class="panel-description">
                        Narrow the directory without another API round trip.
                    </p>
                </div>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="employeeSearch">Search</label>
                    <input id="employeeSearch" placeholder="ID, name, role...">
                </div>

                <div class="field">
                    <label for="departmentFilter">Department</label>
                    <select id="departmentFilter">
                        <option value="">All departments</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= htmlspecialchars(strtolower($department)) ?>">
                                <?= htmlspecialchars($department) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="statusFilter">Status</label>
                    <select id="statusFilter">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
        </section>


        <!-- --------------------------------------------------
             Employee directory
        --------------------------------------------------- -->
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">Staff Directory</h3>
                    <p class="panel-description">
                        <?= count($employees) ?> employee records loaded from PostgreSQL.
                    </p>
                </div>
            </div>

            <?php if (!$employees): ?>
                <div class="empty-state">No employees found.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table id="employeeTable">
                        <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($employees as $employee): ?>
                            <?php
                            $statusText =
                                $employee["active"]
                                    ? "active"
                                    : "inactive";

                            $searchText = strtolower(
                                ($employee["employeeId"] ?? "") . " " .
                                ($employee["name"] ?? "") . " " .
                                ($employee["department"] ?? "") . " " .
                                ($employee["role"] ?? "")
                            );
                            ?>
                            <tr
                                data-search="<?= htmlspecialchars($searchText) ?>"
                                data-department="<?= htmlspecialchars(strtolower($employee["department"] ?? "")) ?>"
                                data-status="<?= $statusText ?>"
                            >
                                <td>
                                    <strong><?= htmlspecialchars($employee["name"] ?? "") ?></strong><br>
                                    <span style="color: var(--muted); font-size: 0.76rem;">
                                        <?= htmlspecialchars($employee["employeeId"] ?? "") ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($employee["department"] ?? "") ?></td>
                                <td><?= htmlspecialchars($employee["role"] ?? "") ?></td>
                                <td>
                                    <span class="badge <?= $statusText ?>">
                                        <?= ucfirst($statusText) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                        <button
                                            class="button"
                                            type="button"
                                            onclick='openEditDialog(<?= json_encode($employee, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                        >
                                            Edit
                                        </button>

                                        <form method="POST">
                                            <input type="hidden" name="form_action" value="status">
                                            <input type="hidden" name="employee_id" value="<?= htmlspecialchars($employee["employeeId"] ?? "") ?>">
                                            <input type="hidden" name="active" value="<?= $employee["active"] ? "0" : "1" ?>">

                                            <button
                                                class="button <?= $employee["active"] ? "danger" : "success" ?>"
                                                type="submit"
                                            >
                                                <?= $employee["active"] ? "Deactivate" : "Activate" ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>


<!-- --------------------------------------------------
     Lightweight employee edit dialog
--------------------------------------------------- -->
<dialog id="editDialog" style="max-width:560px; width:calc(100% - 32px); border:1px solid var(--border); border-radius:16px; background:var(--panel); color:var(--text); padding:0;">
    <form method="POST" style="padding:24px;">
        <input type="hidden" name="form_action" value="edit">
        <input type="hidden" id="edit_employee_id" name="employee_id">

        <div class="panel-header">
            <div>
                <p class="eyebrow">Employee Profile</p>
                <h3 class="panel-title">Edit Employee</h3>
            </div>
            <button class="button" type="button" onclick="document.getElementById('editDialog').close()">Close</button>
        </div>

        <div class="grid" style="gap:14px;">
            <div class="field">
                <label for="edit_name">Full Name</label>
                <input id="edit_name" name="name" required>
            </div>

            <div class="field">
                <label for="edit_department">Department</label>
                <input id="edit_department" name="department">
            </div>

            <div class="field">
                <label for="edit_role">Role</label>
                <input id="edit_role" name="role">
            </div>

            <button class="button primary" type="submit">Save Changes</button>
        </div>
    </form>
</dialog>


<script>
// --------------------------------------------------
// Client-side employee directory filtering
// --------------------------------------------------
//
// Filtering is intentionally performed in the browser
// for v0.2 because the current directory is small and
// already loaded from the API. Server-side pagination
// can replace this when workforce scale requires it.
// --------------------------------------------------

const searchInput = document.getElementById("employeeSearch");
const departmentFilter = document.getElementById("departmentFilter");
const statusFilter = document.getElementById("statusFilter");
const employeeRows = document.querySelectorAll("#employeeTable tbody tr");

function applyEmployeeFilters() {
    const searchValue = (searchInput?.value || "").trim().toLowerCase();
    const departmentValue = departmentFilter?.value || "";
    const statusValue = statusFilter?.value || "";

    employeeRows.forEach((row) => {
        const searchMatches =
            searchValue === "" ||
            row.dataset.search.includes(searchValue);

        const departmentMatches =
            departmentValue === "" ||
            row.dataset.department === departmentValue;

        const statusMatches =
            statusValue === "" ||
            row.dataset.status === statusValue;

        row.hidden = !(searchMatches && departmentMatches && statusMatches);
    });
}

searchInput?.addEventListener("input", applyEmployeeFilters);
departmentFilter?.addEventListener("change", applyEmployeeFilters);
statusFilter?.addEventListener("change", applyEmployeeFilters);


// --------------------------------------------------
// Populate employee edit dialog
// --------------------------------------------------

function openEditDialog(employee) {
    document.getElementById("edit_employee_id").value = employee.employeeId || "";
    document.getElementById("edit_name").value = employee.name || "";
    document.getElementById("edit_department").value = employee.department || "";
    document.getElementById("edit_role").value = employee.role || "";
    document.getElementById("editDialog").showModal();
}
</script>
</body>
</html>
