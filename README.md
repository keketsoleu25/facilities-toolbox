# Facilities Toolbox

> A modular facilities and workforce operations platform that grew from a Python facial-recognition attendance experiment into a multi-service operations system.

**Facilities Toolbox** is built by **The Tech Alchemy Lab** to turn practical facilities problems into clear digital workflows.

The system connects workforce attendance, operational intelligence, sites and buildings, shifts, assets, maintenance requests, work orders and inspections behind a central ASP.NET Core API.

**Current milestone: v0.3 — Operations Core**

---

## Why this project exists

Facilities operations are often spread across paper registers, spreadsheets and disconnected tools. Facilities Toolbox explores a different approach: keep operational rules and data in one backend while allowing specialised interfaces and capture mechanisms to work through it.

The project started with a simple question:

> Can a camera recognise an employee and record attendance?

That Python/OpenCV prototype exposed larger requirements: employee validation, attendance rules, persistent records, dashboards, alerts, reporting and management workflows. Those requirements gradually produced the current multi-service architecture.

```text
Python attendance experiment
          ↓
ASP.NET Core business API
          ↓
PostgreSQL operational data
          ↓
PHP management portal
          ↓
Facilities + workforce operations platform
```

---

## Current capabilities

### Workforce operations

- Employee administration
- Attendance capture and history
- IN/OUT attendance rules
- Punctuality information
- Attendance reporting and CSV export
- Operational alerts

### Workforce scheduling

- Shift definitions
- Employee shift assignments
- Assignment history
- Configurable shift policies

### Facilities structure

- Sites
- Buildings
- Departments

### Asset and maintenance operations

- Asset register foundation
- Maintenance requests
- Work orders
- Inspections

### Operations intelligence

The dashboard can surface information such as:

- total and active employees;
- employees currently present;
- clocked-out and absent employees;
- attendance events;
- attendance rate;
- hours worked;
- average first arrival;
- open attendance sessions;
- recent activity;
- department summaries; and
- attendance trends.

---

## Architecture

```text
                    Facilities Toolbox

 Recognition / Capture            Management Experience
 Python + OpenCV                   PHP + HTML/CSS/JS
          |                                |
          |                                |
          +------------+   +---------------+
                       |   |
                       v   v
                 ASP.NET Core API
                 C# business services
                        |
                        v
                  Entity Framework Core
                        |
                        v
                     PostgreSQL
```

### Technology responsibilities

| Technology | Responsibility |
| --- | --- |
| **C# / ASP.NET Core** | API, business rules, domain services and operational workflows |
| **Entity Framework Core / Npgsql** | Relational persistence and migrations |
| **PostgreSQL** | Operational data store |
| **PHP** | Management and operations portal |
| **Python / OpenCV** | Recognition and intelligent attendance capture |
| **Docker / Compose** | Reproducible local multi-service environment |

For a deeper technical explanation, see [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

---

## Repository structure

```text
facilities-toolbox/
|
|-- facilities-api/          # ASP.NET Core API
|   |-- Configuration/
|   |-- Controllers/
|   |-- Data/
|   |-- Dtos/
|   |-- Migrations/
|   |-- Models/
|   |-- Services/
|   `-- Dockerfile
|
|-- facilities-portal/       # PHP operations portal
|   |-- assets/
|   |-- api-client.php
|   |-- config.php
|   |-- Dockerfile
|   `-- *.php workspaces
|
|-- recognition-service/     # Python/OpenCV recognition work
|
|-- compose.yaml             # Local multi-service orchestration
|-- .env.example             # Safe local configuration template
|-- docs/
|   |-- ARCHITECTURE.md
|   `-- DEVELOPMENT.md
|
`-- README.md
```

---

## Run with Docker — recommended

### Prerequisites

- Docker Desktop or another Docker Engine with Compose support
- Git

### 1. Clone the repository

```bash
git clone https://github.com/keketsoleu25/facilities-toolbox.git
cd facilities-toolbox
```

### 2. Create local environment configuration

Copy `.env.example` to `.env`.

PowerShell:

```powershell
Copy-Item .env.example .env
```

Then set a local PostgreSQL password in `.env`.

```env
POSTGRES_PASSWORD=replace-with-a-local-password
```

Do not commit `.env`.

### 3. Build and start the stack

```bash
docker compose up --build -d
```

Compose starts three services:

| Service | Local address | Purpose |
| --- | --- | --- |
| `facilities-portal` | `http://localhost:8081` | PHP operations portal |
| `facilities-api` | `http://localhost:8080` | ASP.NET Core API |
| `facilities-db` | `localhost:5433` | PostgreSQL database exposed for local tools |

Inside the Docker network, the API connects to PostgreSQL using the service name `facilities-db`, while the portal calls the API through `http://facilities-api:8080`.

### 4. Check running services

```bash
docker compose ps
```

### 5. Stop the stack

```bash
docker compose down
```

The PostgreSQL data is stored in the named Docker volume `facilities-postgres-data`, so normal `docker compose down` does not delete the database.

For troubleshooting, rebuilding, logs and manual development, see [`docs/DEVELOPMENT.md`](docs/DEVELOPMENT.md).

---

## Manual local development

Docker is the recommended way to run the complete stack, but the API and portal can also be run separately during development.

### ASP.NET Core API

```powershell
cd facilities-api
dotnet restore
dotnet build
dotnet run
```

When running manually, configure `ConnectionStrings:FacilitiesDatabase` through .NET User Secrets or another secure configuration provider.

```powershell
dotnet user-secrets set "ConnectionStrings:FacilitiesDatabase" "YOUR_POSTGRES_CONNECTION_STRING"
```

### PHP portal

From the repository root:

```powershell
php -S localhost:8080 -t facilities-portal
```

The portal's API base URL can be supplied through `FACILITIES_API_BASE_URL`.

### Database migrations

From `facilities-api`:

```powershell
dotnet ef database update
```

Review migrations before applying them to an important environment.

---

## Version evolution

| Version | Question | Result |
| --- | --- | --- |
| **v0.1** | Can a camera recognise an employee and record attendance? | Python/OpenCV recognition, employee lookup and IN/OUT attendance prototype |
| **v0.2** | Can attendance become reliable operational information? | ASP.NET Core API, PostgreSQL, employee management, dashboards, alerts, policies, reports and PHP portal |
| **v0.3** | Can the same platform manage broader facilities operations? | Sites, buildings, departments, shifts, assets, maintenance, work orders, inspections and a unified operations portal |

---

## Engineering principles

The project has adopted several rules as it evolved:

1. Business rules belong in backend services, not in the camera or portal.
2. Credentials and environment-specific values do not belong in source control.
3. Operational policies should be configurable instead of duplicated as magic values.
4. The PHP portal consumes the API rather than becoming a second business-logic backend.
5. Database changes are tracked through Entity Framework migrations.
6. Containers should communicate through service names rather than host-specific addresses.
7. A working prototype should be refactored when responsibilities become clear.
8. Operational software should surface decisions and exceptions, not only raw records.

---

## Docker status

The current Compose environment containerises the core local application path:

```text
Browser
   ↓
PHP / Apache container :8081
   ↓
ASP.NET Core container :8080
   ↓
PostgreSQL container :5432
```

The Python recognition service remains a separate integration layer and is **not currently part of the default Compose stack**.

This is intentional: the current Docker milestone proves the core portal → API → database path without forcing the webcam/computer-vision workflow into the same runtime prematurely.

---

## Roadmap

The existing foundation can support later work such as authentication and role-based access control, richer maintenance lifecycles, contractor management, compliance workflows, notifications, deeper asset history, production deployment and tighter recognition-service integration.

These are future directions rather than requirements for the current v0.3 milestone.

---

## Project status

**v0.3 Operations Core is on `main`.**

The current focus is a stable, documented system rather than adding infrastructure for its own sake.

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

---

### The Tech Alchemy Lab

**Turning practical operational problems into useful software.**
