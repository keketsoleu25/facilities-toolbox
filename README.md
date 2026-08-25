# Facilities Toolbox

**Facilities Toolbox v0.3** is a modular facilities and workforce operations platform built by **The Tech Alchemy Lab**.

The project began as a Python facial-recognition attendance prototype and has evolved into a broader facilities-management system combining workforce attendance, shift operations, operational intelligence, facilities structure, and an emerging asset-maintenance core.

> **Current milestone:** v0.3 — Operations Core

## Product vision

Facilities Toolbox is designed to give facilities teams one operational layer for understanding **who is on site, how teams are scheduled, what is happening across facilities, and where operational attention is required**.

The long-term goal is a modular toolbox rather than one monolithic application. Attendance, scheduling, dashboards, asset maintenance, inspections, work orders, incidents and other facilities capabilities can evolve as independent modules while sharing the same operational platform.

## Architecture

```text
Python Recognition / Attendance Capture
                |
                v
        ASP.NET Core API
                |
        Business Services
                |
        Entity Framework Core
                |
        Neon PostgreSQL
                ^
                |
          PHP Admin Portal
```

### Technology responsibilities

- **C# / ASP.NET Core** — API, business rules, operational services and backend workflows.
- **Entity Framework Core + Npgsql** — persistence and PostgreSQL integration.
- **Neon PostgreSQL** — cloud relational database.
- **PHP** — lightweight facilities administration and operations portal.
- **Python / OpenCV** — computer vision and facial-recognition attendance capabilities.
- **HTML/CSS/JavaScript** — responsive portal UI and persistent light/dark theme behaviour.

## Current capabilities

### Workforce and employees

- Employee directory and creation.
- Active/inactive employee status management.
- Department and role information.
- API-backed employee administration.

### Attendance

- Clock-in and clock-out workflow.
- Duplicate/open-session protection in the business layer.
- Current presence calculation.
- Daily attendance activity.
- Hours-worked reporting.
- First-arrival information.
- Attendance-rate calculation.
- Open attendance session tracking.
- Attendance trend data.

### Shift operations

- Reusable shift definitions.
- Employee shift assignments.
- Assignment history.
- Shift-aware operational data.
- Configurable shift policy settings instead of duplicated hard-coded rules.

### Operational dashboard

The PHP portal consumes the live ASP.NET Core dashboard endpoint and presents operational information including:

- total and active employees;
- employees currently present;
- clocked-out and absent employees;
- attendance events for the day;
- attendance rate;
- total hours worked;
- average first arrival;
- open sessions;
- latest attendance activity;
- department-level attendance;
- attendance trends; and
- operational alerts.

### Operational alerts

`OperationalAlertsService` provides a dedicated business layer for identifying conditions that require facilities-management attention. This creates the foundation for increasingly intelligent exception-based operations rather than relying only on static reports.

### Facilities structure

The v0.3 schema introduces the foundations for representing facilities operationally, including:

- sites;
- buildings;
- departments; and
- shifts.

### Asset maintenance

The asset-maintenance core has entered the database architecture in v0.3. This is the foundation for future asset registers, maintenance records, work orders, inspections and lifecycle reporting.

## Portal design system

The Facilities Toolbox portal now uses a shared visual system across the Dashboard, Employees and Shift Operations pages.

The portal includes:

- consistent navigation and page structure;
- shared cards, panels, tables, forms and status components;
- responsive layouts;
- **light and dark modes**;
- automatic operating-system theme detection on first visit; and
- persistent user theme preference using browser storage.

## Repository structure

```text
facilities-toolbox/
|
|-- facilities-api/        # ASP.NET Core API and business services
|   |-- Configuration/
|   |-- Controllers/
|   |-- Data/
|   |-- Dtos/
|   |-- Migrations/
|   |-- Models/
|   `-- Services/
|
|-- facilities-portal/     # PHP facilities administration portal
|   |-- assets/
|   |-- api-client.php
|   |-- config.php
|   |-- employees.php
|   |-- index.php
|   `-- shifts.php
|
`-- README.md
```

The Python recognition component is part of the broader Facilities Toolbox direction and is intended to integrate with the operations platform as the recognition workflow matures.

## Backend design

The API separates HTTP controllers from business logic. Important services include:

- `AttendanceService`
- `DashboardService`
- `OperationalAlertsService`

This keeps operational rules out of controllers and makes the system easier to test, maintain and extend.

Shift behaviour is represented through configurable policy options so that operational times and thresholds do not need to be duplicated throughout the application.

## Configuration and security

Database credentials must **not** be committed to source control.

During local development, configure the Facilities PostgreSQL connection string using .NET User Secrets or another secure configuration provider:

```powershell
dotnet user-secrets set "ConnectionStrings:FacilitiesDatabase" "YOUR_POSTGRES_CONNECTION_STRING"
```

Never place production credentials directly in `Program.cs`, PHP source files or committed configuration files.

## Local development

### 1. Start the ASP.NET Core API

From the API directory:

```powershell
cd facilities-api
dotnet restore
dotnet build
dotnet run
```

The current local development API uses port `5209`.

Verify the operational dashboard endpoint:

```powershell
curl.exe -i http://localhost:5209/api/dashboard
```

A healthy request should return `HTTP 200` with dashboard JSON.

### 2. Start the PHP portal

From the repository root:

```powershell
php -S localhost:8080 -t facilities-portal
```

Then open the portal in a browser at `localhost:8080`.

The API base URL is centralized in the portal configuration rather than duplicated across pages.

## Database migrations

The database schema is managed through Entity Framework Core migrations.

The v0.3 operations work includes schema evolution for:

- sites and buildings;
- departments and shifts; and
- asset-maintenance foundations.

Review generated migrations before applying them to an important environment.

Typical development command:

```powershell
dotnet ef database update
```

## Engineering workflow

Development uses feature branches and pull requests so larger product changes can be verified before reaching `main`.

The v0.3 release brought together the operations backend, live PHP dashboard integration and unified light/dark portal design before being merged into the main branch.

## Roadmap

Likely next modules include:

- site and building management UI;
- asset register;
- preventive and corrective maintenance;
- work orders;
- inspections and checklists;
- contractor management;
- incident reporting;
- compliance/document management;
- richer facilities analytics;
- authentication and role-based access control;
- notification workflows;
- deeper Python recognition integration; and
- production deployment and multi-user readiness.

## Project status

**v0.3 Operations Core is merged into `main`.**

The project is now beyond the original attendance prototype: it has a database-backed API, explicit business services, operational dashboard intelligence, employee and shift workflows, facilities schema foundations, asset-maintenance foundations, a unified management portal and persistent light/dark themes.

---

### The Tech Alchemy Lab

**Turning practical operational problems into useful software.**
