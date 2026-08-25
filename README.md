# Facilities Toolbox

> From a webcam attendance experiment to a modular facilities operations platform.

**Facilities Toolbox** is a facilities and workforce operations platform built by **The Tech Alchemy Lab**.

The important part of this repository is not only what the system can do today. It is the engineering journey that produced it.

The project started with one question: **can Python recognise an employee and record attendance?** That small experiment became a working attendance prototype. Attendance then needed reliable business rules and persistent data, which introduced C#/.NET, PostgreSQL and an API. Once the data existed, managers needed somewhere to use it, which introduced the PHP operations portal. From there the project expanded into dashboards, workforce intelligence, sites, buildings, departments, shifts, assets, maintenance and work orders.

**Current milestone: v0.3 — Operations Core**

---

## The evolution

```text
v0.1
Python facial recognition
        +
attendance capture
        |
        v
v0.2
ASP.NET Core + PostgreSQL
        +
PHP operations portal
        +
workforce intelligence
        |
        v
v0.3
Facilities structure + shifts
        +
assets + maintenance + work orders
        +
live command centre
        +
unified light/dark product UI
```

Each version answered a larger operational question than the version before it.

---

# v0.1 — The Attendance Experiment

## Where it started

Facilities Toolbox did **not** begin as a full Facilities Management System.

It began as a Python computer-vision project: use a webcam to recognise employees and turn recognition events into useful attendance records.

The first prototype used **Python and OpenCV** with an LBPH face recogniser and Haar-cascade face detection. Employee identities were mapped to trained face data, and the camera loop allowed an operator to record attendance actions.

The first practical workflow was deliberately small:

```text
Camera
  ↓
Face detection
  ↓
Face recognition
  ↓
Employee lookup
  ↓
I = IN
O = OUT
  ↓
Attendance record
```

That prototype proved the core idea: computer vision could become an input mechanism for a real facilities workflow.

## The first engineering refactor

The early prototype worked, but keeping detection, recognition, employee data and attendance logic in one script would not scale.

The Python side was therefore separated into responsibilities such as:

- `FaceDetector`
- `FaceRecognizer`
- `EmployeeRepository`
- `AttendanceService`

This was an important turning point. The project stopped being only a facial-recognition demo and started being treated as a system.

## What v0.1 taught us

Face recognition was only the **capture layer**.

A useful attendance product also needed to know things such as:

- whether an employee exists;
- whether that employee is active;
- whether they already clocked in;
- whether they have an open attendance session;
- when they arrived and left;
- how many hours they worked; and
- how management could inspect that information later.

Those requirements created the need for a proper backend.

---

# v0.2 — From Prototype to Operations System

v0.2 is where Facilities Toolbox became much more than Python.

The architecture expanded into a deliberate multi-language stack:

```text
Python
Recognition / computer vision
        |
        v
C# / ASP.NET Core
Business rules + API
        |
        v
EF Core + Neon PostgreSQL
Persistent operational data
        ^
        |
PHP
Management / operations portal
```

The technologies were not added simply to make the stack bigger. Each received a clear job.

## C#/.NET became the business layer

The ASP.NET Core API became responsible for enforcing operational rules instead of trusting the camera or UI to do so.

`AttendanceService` introduced rules such as validating employees and preventing invalid or duplicate IN/OUT behaviour.

Entity Framework Core and Npgsql connected the API to **Neon PostgreSQL**, giving the project persistent relational data instead of local prototype files alone.

Database credentials were moved out of source code and into secure development configuration such as .NET User Secrets.

## Employee operations

The system gained real employee administration rather than relying only on recognition labels.

This included employee creation, active/inactive state, roles, departments and later employee profile drill-downs.

The PHP portal became the management interface while the C# API remained responsible for business behaviour.

## The dashboard changed the project

Once attendance was stored centrally, the next question became:

> What does a facilities manager need to know right now?

That produced `DashboardService`, dashboard DTOs and a live dashboard API.

The command centre grew from basic totals into operational intelligence including:

- total and active employees;
- present now;
- clocked out;
- absent today;
- attendance events today;
- attendance rate;
- total hours worked;
- average first arrival;
- open attendance sessions;
- latest activity;
- department summaries; and
- attendance trends.

The repository history shows this progression clearly: dashboard response DTOs, operational dashboard service, dashboard endpoint and service registration were added as separate steps before the portal became a full command centre.

## Employee intelligence

v0.2 then moved beyond totals.

Employee profile drill-downs and punctuality intelligence were added so attendance could be understood at an individual level. The system began deriving useful operational meaning rather than simply storing timestamps.

## Operational alerts

The next step was exception-based management.

`OperationalAlertsService` was introduced to identify conditions requiring attention. Alerts were then exposed through the API and surfaced in the portal.

This changed the philosophy of the dashboard from:

**"Here is your data."**

to:

**"Here is what may require your attention."**

## Configurable shift policy

Early attendance rules naturally contained assumptions about working times. v0.2 moved those assumptions into `ShiftPolicyOptions` and configuration.

Policies could represent values such as:

- expected start time;
- grace period;
- missing check-in threshold;
- long-session threshold; and
- minimum attendance target.

Those settings were then reused by employee profiles and operational alerts instead of duplicating hard-coded times throughout the application.

## Reporting

The project also gained attendance history, daily reports and CSV export.

At this stage the system could capture attendance, store it, enforce rules, analyse it, alert on it and report on it.

That was the point where the original attendance project began revealing a much larger opportunity.

---

# v0.3 — Facilities Operations Core

The question changed again:

> If we can understand the workforce, can the same platform understand the facility around that workforce?

v0.3 answered yes.

## Sites and buildings

The first expansion beyond workforce management introduced proper facilities structure.

Site and Building domain models were added, registered with the database and followed by management APIs and a facilities structure workspace.

The system could now begin representing **where operations happen**, not only who was working.

## Departments and shifts

Departments became first-class operational entities rather than plain employee text fields.

Reusable Shift models were introduced along with historical employee shift assignments. Employees could be connected to departments and shift assignments while preserving assignment history.

The API and portal then gained dedicated shift-management capabilities.

This gave the platform a much stronger operational model:

```text
Site
  ↓
Building
  ↓
Department
  ↓
Employee
  ↓
Shift Assignment
  ↓
Attendance
```

## Assets and maintenance

The next major expansion was physical asset operations.

The repository introduced domain models for:

- facilities assets;
- maintenance requests;
- work orders; and
- asset inspections.

These were registered into the operations domain, exposed through APIs and given portal workspaces/command-centre views.

The Toolbox was no longer only a workforce system. It had started becoming a genuine facilities platform capable of connecting people, places and physical assets.

## Maintenance and work-order command centres

Maintenance operations gained API support and a dedicated command-centre direction, followed by a work-order command centre.

This establishes the foundation for a future workflow such as:

```text
Asset problem detected
        ↓
Maintenance request
        ↓
Work order
        ↓
Assignment / action
        ↓
Inspection / completion
        ↓
Operational history
```

## Live PHP ↔ C# integration

During v0.3 the portal integration was tightened substantially.

The Facilities API configuration was centralized, a reusable PHP API client was introduced, employee management was separated into a dedicated page, and the operational dashboard was connected directly to the live `/api/dashboard` endpoint.

This created a clean end-to-end path:

```text
PHP Portal
    ↓
ASP.NET Core API
    ↓
Business Services
    ↓
Entity Framework Core
    ↓
Neon PostgreSQL
    ↓
Live operational response
```

The integration was tested directly against the running API and real database-backed dashboard responses.

## One product identity

As features multiplied, another problem appeared: pages built at different stages no longer looked like one product.

The portal was therefore unified around a shared design system. Dashboard, Employees and Shift Operations now share the same visual shell.

v0.3 also introduced persistent **light and dark modes**, including operating-system preference detection and browser-stored user preference.

The visual work was deliberately isolated on a feature branch, reviewed through a pull request, merged into the operations branch and then released with the v0.3 operations core into `main`.

---

# What Facilities Toolbox is today

Facilities Toolbox is now a modular operations platform with several connected domains.

```text
Facilities Toolbox
|
|-- Workforce Operations
|   |-- Employees
|   |-- Attendance
|   |-- Punctuality
|   |-- Attendance history
|   |-- Reports / CSV
|   `-- Operational alerts
|
|-- Workforce Scheduling
|   |-- Shift definitions
|   |-- Shift assignments
|   `-- Assignment history
|
|-- Facilities Structure
|   |-- Sites
|   |-- Buildings
|   `-- Departments
|
|-- Asset & Maintenance Operations
|   |-- Assets
|   |-- Maintenance requests
|   |-- Work orders
|   `-- Inspections
|
|-- Operations Intelligence
|   |-- Live command centre
|   |-- Attendance rate
|   |-- Hours worked
|   |-- Absence intelligence
|   |-- Department intelligence
|   |-- Trends
|   `-- Alerts
|
`-- Recognition Layer
    `-- Python / OpenCV facial-recognition attendance
```

The modules are intentionally capable of evolving independently. A customer may eventually need only attendance, only maintenance/work orders, or the complete Toolbox.

---

# Current architecture

```text
Recognition / Capture
Python + OpenCV
        |
        v
Application / Business Layer
C# + ASP.NET Core
        |
        v
Persistence
EF Core + Npgsql + Neon PostgreSQL
        ^
        |
Management Experience
PHP + HTML + CSS + JavaScript
```

### Technology responsibilities

- **Python / OpenCV** — computer vision, recognition and intelligent capture.
- **C# / ASP.NET Core** — API, domain rules, services and operational workflows.
- **Entity Framework Core / Npgsql** — relational persistence and schema evolution.
- **Neon PostgreSQL** — cloud operational database.
- **PHP** — lightweight management portal and server-rendered operations UI.
- **HTML/CSS/JavaScript** — responsive interaction, shared product design and theme controls.

---

# Engineering principles learned through the build

The project has gradually adopted several rules because real problems forced them into existence:

1. **Business rules belong in services, not in the camera or UI.**
2. **Credentials do not belong in source control.**
3. **Operational policies should be configurable instead of duplicated as magic values.**
4. **The portal consumes the API; it does not become a second business-logic backend.**
5. **Database changes are tracked through migrations.**
6. **Large product changes move through feature branches and pull requests.**
7. **A working prototype should be refactored when its responsibilities become clear — not before.**
8. **Operational software should surface exceptions and decisions, not only raw records.**

---

# Repository structure

```text
facilities-toolbox/
|
|-- facilities-api/        # ASP.NET Core API and domain/business services
|   |-- Configuration/
|   |-- Controllers/
|   |-- Data/
|   |-- Dtos/
|   |-- Migrations/
|   |-- Models/
|   `-- Services/
|
|-- facilities-portal/     # PHP facilities operations portal
|   |-- assets/
|   |-- api-client.php
|   |-- config.php
|   `-- *.php workspaces
|
`-- README.md
```

The Python recognition work represents the project's original capture layer and remains part of the broader Toolbox architecture as computer-vision integration continues to mature.

---

# Local development

## ASP.NET Core API

```powershell
cd facilities-api
dotnet restore
dotnet build
dotnet run
```

The current local API uses port `5209`.

Verify the dashboard path with:

```powershell
curl.exe -i http://localhost:5209/api/dashboard
```

A healthy request should return `HTTP 200` and dashboard JSON.

## PHP portal

From the repository root:

```powershell
php -S localhost:8080 -t facilities-portal
```

Then open `localhost:8080` in a browser.

## Database configuration

Never commit database credentials.

For local .NET development, configure the PostgreSQL connection string through User Secrets or another secure provider:

```powershell
dotnet user-secrets set "ConnectionStrings:FacilitiesDatabase" "YOUR_POSTGRES_CONNECTION_STRING"
```

## Database migrations

```powershell
dotnet ef database update
```

Review generated migrations before applying them to an important environment.

---

# Version story at a glance

| Version | Question | Result |
| --- | --- | --- |
| **v0.1** | Can a camera recognise an employee and record attendance? | Python/OpenCV facial recognition, employee lookup and IN/OUT attendance prototype. |
| **v0.2** | Can attendance become reliable operational information? | ASP.NET Core API, PostgreSQL persistence, employee management, dashboard intelligence, profiles, alerts, configurable shift policy, reports and PHP portal. |
| **v0.3** | Can the same platform manage broader facilities operations? | Sites, buildings, departments, reusable shifts, assignments, assets, maintenance requests, work orders, inspections, live portal/API integration and unified light/dark product UI. |

---

# Roadmap

The next chapters can build on the foundations already present:

- stronger site and building management workflows;
- preventive and corrective maintenance planning;
- work-order assignment and lifecycle controls;
- richer asset register and lifecycle history;
- inspection templates and checklists;
- contractor management;
- incident and safety reporting;
- compliance and document management;
- notifications and escalation workflows;
- authentication and role-based access control;
- richer operational analytics;
- deeper Python recognition integration;
- production deployment and multi-user readiness; and
- eventual modular packaging of Toolbox capabilities as standalone products.

---

# Current status

**v0.3 Operations Core is merged into `main`.**

The project is still evolving, but its direction is now clear:

```text
Attendance prototype
        ↓
Workforce operations
        ↓
Facilities intelligence
        ↓
Asset and maintenance operations
        ↓
Modular Facilities Management Toolbox
```

That progression is the point of this repository. It records how a small computer-vision experiment was repeatedly pushed one operational problem further until it became the foundation of a much larger product.

---

### The Tech Alchemy Lab

**Turning practical operational problems into useful software.**
