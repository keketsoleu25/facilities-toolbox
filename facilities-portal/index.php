<?php

// --------------------------------------------------
// Facilities Toolbox - Employees Management
// --------------------------------------------------
//
// This page is the management interface for employees.
//
// It talks to the C# API only.
//
// It does NOT connect directly to PostgreSQL.
//
// Responsibilities:
//
// - list employees
// - add employees
// - activate employees
// - deactivate employees
//
// C# remains responsible for business rules
// and PostgreSQL persistence.
// --------------------------------------------------


// --------------------------------------------------
// API configuration
// --------------------------------------------------

$apiBaseUrl = "http://localhost:5209";

$employeesEndpoint =
    $apiBaseUrl . "/api/employees";


// --------------------------------------------------
// Page state
// --------------------------------------------------

$employees = [];

$errorMessage = null;

$successMessage = null;


// --------------------------------------------------
// Helper: send HTTP request to the C# API
// --------------------------------------------------
//
// PHP's built-in file_get_contents can make HTTP
// requests when allow_url_fopen is enabled.
//
// This helper keeps our API communication in one place.
// --------------------------------------------------

function apiRequest(
    string $method,
    string $url,
    ?array $payload = null
): array {

    // Prepare HTTP headers.
    $headers = [
        "Content-Type: application/json"
    ];


    // Build stream options.
    $options = [
        "http" => [
            "method" => $method,
            "header" => implode(
                "\r\n",
                $headers
            ),
            "ignore_errors" => true,
            "timeout" => 5
        ]
    ];


    // Add JSON body when required.
    if ($payload !== null) {
        $options["http"]["content"] =
            json_encode($payload);
    }


    // Build the stream context.
    $context =
        stream_context_create(
            $options
        );


    // Send the request.
    $response =
        @file_get_contents(
            $url,
            false,
            $context
        );


    // If the C# API cannot be reached,
    // return a controlled failure.
    if ($response === false) {
        return [
            "success" => false,
            "status" => 0,
            "data" => null,
            "message" =>
                "Facilities API is unavailable."
        ];
    }


    // --------------------------------------------------
    // Determine HTTP status code
    // --------------------------------------------------

    $statusCode = 200;

    if (
        isset($http_response_header) &&
        isset($http_response_header[0])
    ) {
        if (
            preg_match(
                "/HTTP\/\S+\s+(\d+)/",
                $http_response_header[0],
                $matches
            )
        ) {
            $statusCode =
                (int) $matches[1];
        }
    }


    // Decode API JSON.
    $data =
        json_decode(
            $response,
            true
        );


    // Successful API response.
    if (
        $statusCode >= 200 &&
        $statusCode < 300
    ) {
        return [
            "success" => true,
            "status" => $statusCode,
            "data" => $data,
            "message" => null
        ];
    }


    // Read API error message if one exists.
    $message =
        $data["error"]
        ?? "The request failed.";


    return [
        "success" => false,
        "status" => $statusCode,
        "data" => $data,
        "message" => $message
    ];
}


// --------------------------------------------------
// Handle form submissions
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $formAction =
        $_POST["form_action"]
        ?? "";


    // --------------------------------------------------
    // Add employee
    // --------------------------------------------------

    if ($formAction === "create") {

        $employeeId =
            trim(
                $_POST["employee_id"]
                ?? ""
            );

        $name =
            trim(
                $_POST["name"]
                ?? ""
            );

        $department =
            trim(
                $_POST["department"]
                ?? ""
            );

        $role =
            trim(
                $_POST["role"]
                ?? ""
            );


        // Send employee creation request
        // to the C# API.
        $result = apiRequest(
            "POST",
            $employeesEndpoint,
            [
                "employeeId" => $employeeId,
                "name" => $name,
                "department" => $department,
                "role" => $role
            ]
        );


        if ($result["success"]) {
            $successMessage =
                "Employee created successfully.";
        } else {
            $errorMessage =
                $result["message"];
        }
    }


    // --------------------------------------------------
    // Change employee status
    // --------------------------------------------------

    if ($formAction === "status") {

        $employeeId =
            trim(
                $_POST["employee_id"]
                ?? ""
            );


        // HTML sends "1" or "0".
        $active =
            ($_POST["active"] ?? "0")
            === "1";


        $statusEndpoint =
            $employeesEndpoint
            . "/"
            . rawurlencode($employeeId)
            . "/status";


        $result = apiRequest(
            "PATCH",
            $statusEndpoint,
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

            $errorMessage =
                $result["message"];
        }
    }
}


// --------------------------------------------------
// Load employees from C# API
// --------------------------------------------------

$result = apiRequest(
    "GET",
    $employeesEndpoint
);


if ($result["success"]) {

    $employees =
        is_array($result["data"])
        ? $result["data"]
        : [];

} else {

    $errorMessage =
        $result["message"];
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Employees - Facilities Toolbox
    </title>


    <style>

        /* --------------------------------------------------
           Base page styling
        -------------------------------------------------- */

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 40px 20px;
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            color: #111827;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
        }


        /* --------------------------------------------------
           Navigation
        -------------------------------------------------- */

        nav {
            display: flex;
            gap: 18px;
            margin-bottom: 25px;
        }

        nav a {
            color: #111827;
            text-decoration: none;
            font-weight: bold;
        }


        /* --------------------------------------------------
           Cards
        -------------------------------------------------- */

        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
        }


        /* --------------------------------------------------
           Form
        -------------------------------------------------- */

        .form-grid {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(180px, 1fr)
                );
            gap: 15px;
        }

        input {
            width: 100%;
            padding: 11px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
        }

        button {
            padding: 10px 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }

        .primary {
            background: #111827;
            color: white;
        }


        /* --------------------------------------------------
           Messages
        -------------------------------------------------- */

        .success {
            background: #dcfce7;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .error {
            background: #fee2e2;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }


        /* --------------------------------------------------
           Employee table
        -------------------------------------------------- */

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom:
                1px solid #e5e7eb;
        }

        th {
            background: #f9fafb;
        }

        .active {
            font-weight: bold;
        }

        .inactive {
            color: #6b7280;
        }

        .activate {
            background: #dcfce7;
        }

        .deactivate {
            background: #fee2e2;
        }

    </style>

</head>


<body>

<div class="container">


    <!-- --------------------------------------------------
         Navigation
    --------------------------------------------------- -->

    <nav>

        <a href="index.php">
            Attendance
        </a>

        <a href="employees.php">
            Employees
        </a>

    </nav>


    <h1>
        Employees
    </h1>

    <p>
        Facilities Toolbox Staff Management
    </p>


    <!-- --------------------------------------------------
         Feedback messages
    --------------------------------------------------- -->

    <?php if ($successMessage): ?>

        <div class="success">

            <?php
            echo htmlspecialchars(
                $successMessage
            );
            ?>

        </div>

    <?php endif; ?>


    <?php if ($errorMessage): ?>

        <div class="error">

            <?php
            echo htmlspecialchars(
                $errorMessage
            );
            ?>

        </div>

    <?php endif; ?>


    <!-- --------------------------------------------------
         Add employee form
    --------------------------------------------------- -->

    <div class="card">

        <h2>
            Add Employee
        </h2>

        <form
            method="POST"
            class="form-grid"
        >

            <input
                type="hidden"
                name="form_action"
                value="create"
            >


            <input
                type="text"
                name="employee_id"
                placeholder="EMP003"
                required
            >


            <input
                type="text"
                name="name"
                placeholder="Employee name"
                required
            >


            <input
                type="text"
                name="department"
                placeholder="Department"
            >


            <input
                type="text"
                name="role"
                placeholder="Role"
            >


            <button
                type="submit"
                class="primary"
            >
                Add Employee
            </button>

        </form>

    </div>


    <!-- --------------------------------------------------
         Employee table
    --------------------------------------------------- -->

    <div class="card">

        <h2>
            Employee Directory
        </h2>


        <?php if (!$employees): ?>

            <p>
                No employees found.
            </p>

        <?php else: ?>

            <table>

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>

                </thead>


                <tbody>

                <?php foreach ($employees as $employee): ?>

                    <tr>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                (string) $employee["id"]
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                $employee["employeeId"]
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                $employee["name"]
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                $employee["department"]
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                $employee["role"]
                            );
                            ?>
                        </td>

                        <td>

                            <?php if ($employee["active"]): ?>

                                <span class="active">
                                    Active
                                </span>

                            <?php else: ?>

                                <span class="inactive">
                                    Inactive
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="form_action"
                                    value="status"
                                >

                                <input
                                    type="hidden"
                                    name="employee_id"
                                    value="<?php
                                        echo htmlspecialchars(
                                            $employee["employeeId"]
                                        );
                                    ?>"
                                >

                                <input
                                    type="hidden"
                                    name="active"
                                    value="<?php
                                        echo $employee["active"]
                                            ? "0"
                                            : "1";
                                    ?>"
                                >


                                <?php if ($employee["active"]): ?>

                                    <button
                                        type="submit"
                                        class="deactivate"
                                    >
                                        Deactivate
                                    </button>

                                <?php else: ?>

                                    <button
                                        type="submit"
                                        class="activate"
                                    >
                                        Activate
                                    </button>

                                <?php endif; ?>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>


</div>

</body>

</html>