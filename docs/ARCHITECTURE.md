# Facilities Toolbox Architecture

This document explains how the major Facilities Toolbox components fit together and where responsibilities live.

## System boundary

Facilities Toolbox is a modular facilities and workforce operations platform. The current core runtime is made of three connected services:

```text
Browser
  |
  v
Facilities Portal
PHP + Apache
  |
  v
Facilities API
ASP.NET Core / C#
  |
  v
PostgreSQL
```

A separate Python/OpenCV recognition layer provides the original intelligent attendance-capture capability and can integrate with the backend without owning business rules.

## Component responsibilities

### Facilities Portal

Location: `facilities-portal/`

The PHP portal is the management interface. It renders operations pages, gathers user input and communicates with the Facilities API.

Responsibilities:

- dashboard and management views;
- employee and facilities workspaces;
- maintenance/work-order interfaces;
- server-rendered UI;
- calls to the ASP.NET Core API.

The portal should not directly implement domain rules that belong in the API.

### Facilities API

Location: `facilities-api/`

The ASP.NET Core service is the central application and business layer.

Responsibilities:

- HTTP API endpoints;
- employee and attendance rules;
- dashboard calculations;
- operational alerts;
- shifts and assignments;
- sites, buildings and departments;
- assets, maintenance requests, work orders and inspections;
- persistence through Entity Framework Core;
- application configuration.

The API is the source of truth for operational behaviour.

### PostgreSQL

The database stores persistent facilities and workforce data.

The API accesses PostgreSQL through Entity Framework Core and Npgsql. Schema changes are tracked through migrations in `facilities-api/Migrations/`.

Within Docker Compose the database hostname is `facilities-db`, not `localhost`.

### Recognition Service

Location: `recognition-service/`

The recognition layer represents the project's original Python/OpenCV attendance experiment.

Its purpose is intelligent capture:

```text
Camera
  ↓
Face detection
  ↓
Recognition
  ↓
Employee identity
  ↓
Attendance action
```

Recognition should provide an input to the system rather than becoming the owner of attendance business logic. Rules such as employee validity, active status and legal IN/OUT transitions belong in the C# backend.

The recognition service is not currently included in the default Compose runtime.

## Docker architecture

`compose.yaml` defines the current local application environment.

```text
Host machine
|
|-- localhost:8081
|      |
|      v
|   facilities-portal
|      |
|      | http://facilities-api:8080
|      v
|   facilities-api
|      |
|      | PostgreSQL protocol
|      v
|   facilities-db:5432
|
`-- localhost:5433 -> facilities-db:5432
```

### Service networking

Docker Compose creates a private application network. Containers address each other through Compose service names.

- Portal → API: `http://facilities-api:8080`
- API → database: `facilities-db:5432`
- Browser → portal: `http://localhost:8081`
- Host database tools → PostgreSQL: `localhost:5433`

This avoids depending on host-specific IP addresses.

## Data persistence

PostgreSQL uses the named volume:

```text
facilities-postgres-data
```

Normal container recreation therefore does not remove database data.

Be careful with commands that explicitly remove volumes, such as:

```bash
docker compose down -v
```

That command is destructive to the local container database.

## Configuration

The Compose runtime expects `POSTGRES_PASSWORD` from the local environment or `.env` file.

The API receives its connection string through:

```text
ConnectionStrings__FacilitiesDatabase
```

The portal receives its API address through:

```text
FACILITIES_API_BASE_URL
```

Environment-specific secrets must remain outside source control.

## Health and startup order

Compose uses health checks and dependencies to reduce race conditions during startup:

```text
PostgreSQL healthy
      ↓
API starts and becomes healthy
      ↓
Portal starts
```

This makes the local stack more predictable than starting each process manually.

## Domain direction

The platform currently connects several operational domains:

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

Asset
  ↓
Maintenance Request
  ↓
Work Order
  ↓
Inspection / completion history
```

These domains are intended to remain modular. The Toolbox can evolve without requiring every customer or environment to use every capability.

## Architectural rules

1. The API owns business rules.
2. The portal consumes the API instead of duplicating backend behaviour.
3. Computer vision acts as a capture mechanism, not a second source of truth.
4. PostgreSQL is accessed through the application layer.
5. Environment-specific secrets stay outside Git.
6. Service-to-service addresses use Docker service names inside Compose.
7. Database changes are managed through migrations.
8. New infrastructure should solve a concrete operational requirement rather than increase complexity by default.
