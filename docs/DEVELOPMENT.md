# Facilities Toolbox Development Guide

This guide covers the normal local development workflow for Facilities Toolbox.

## Recommended workflow: Docker Compose

The fastest way to run the current core stack is Docker Compose.

### Prerequisites

- Git
- Docker Desktop or Docker Engine with Compose support

### Clone

```bash
git clone https://github.com/keketsoleu25/facilities-toolbox.git
cd facilities-toolbox
```

### Configure local environment

Create `.env` from the provided example.

PowerShell:

```powershell
Copy-Item .env.example .env
```

macOS/Linux:

```bash
cp .env.example .env
```

Set a development-only PostgreSQL password:

```env
POSTGRES_PASSWORD=replace-with-a-local-password
```

`.env` is ignored by Git and must not be committed.

### Build and run

```bash
docker compose up --build -d
```

### Inspect status

```bash
docker compose ps
```

Expected core services:

- `facilities-db`
- `facilities-api`
- `facilities-portal`

### Open the application

- Portal: `http://localhost:8081`
- API: `http://localhost:8080`
- PostgreSQL from host tools: `localhost:5433`

### View logs

All services:

```bash
docker compose logs -f
```

API only:

```bash
docker compose logs -f facilities-api
```

Portal only:

```bash
docker compose logs -f facilities-portal
```

Database only:

```bash
docker compose logs -f facilities-db
```

### Rebuild one service

```bash
docker compose build facilities-api
docker compose up -d facilities-api
```

Or for the portal:

```bash
docker compose build facilities-portal
docker compose up -d facilities-portal
```

### Stop

```bash
docker compose down
```

This preserves the named PostgreSQL volume.

### Reset local container database

Only use this when you intentionally want to delete the local Docker database:

```bash
docker compose down -v
```

Then start again:

```bash
docker compose up --build -d
```

## Container communication

Do not replace internal service names with `localhost`.

Inside containers:

```text
Portal -> http://facilities-api:8080
API    -> facilities-db:5432
```

From the host machine:

```text
Portal -> http://localhost:8081
API    -> http://localhost:8080
DB     -> localhost:5433
```

`localhost` inside a container refers to that same container, not another Compose service.

## Manual API development

When working primarily on the C# backend, you can run it outside Docker.

```powershell
cd facilities-api
dotnet restore
dotnet build
dotnet run
```

Supply a valid PostgreSQL connection string through .NET User Secrets or another secure configuration provider.

Example:

```powershell
dotnet user-secrets set "ConnectionStrings:FacilitiesDatabase" "Host=localhost;Port=5433;Database=facilities;Username=facilities;Password=YOUR_LOCAL_PASSWORD"
```

If PostgreSQL is still running through Compose, port `5433` is the host-side database port.

## Entity Framework migrations

From `facilities-api`:

```powershell
dotnet ef database update
```

Create a migration only when the domain model intentionally changes:

```powershell
dotnet ef migrations add DescriptiveMigrationName
```

Review generated migrations before applying them to important environments.

## Manual portal development

The PHP portal can be run with the built-in PHP server:

```powershell
php -S localhost:8080 -t facilities-portal
```

When the API is running outside Docker, configure the portal API base URL for that environment.

For the Dockerized portal, Compose already provides:

```text
FACILITIES_API_BASE_URL=http://facilities-api:8080
```

## Useful Docker commands

```bash
# Show running Toolbox containers
docker compose ps

# Restart the API
docker compose restart facilities-api

# Restart the portal
docker compose restart facilities-portal

# Rebuild everything after Dockerfile changes
docker compose up --build -d

# Follow logs
docker compose logs -f

# Stop without deleting data
docker compose down
```

## Common problems

### Portal cannot reach API

Check:

```bash
docker compose ps
docker compose logs facilities-api
docker compose logs facilities-portal
```

Inside Docker, the portal must use `http://facilities-api:8080` rather than `localhost`.

### API cannot reach PostgreSQL

Confirm the database is healthy:

```bash
docker compose ps
docker compose logs facilities-db
```

The container connection uses:

```text
Host=facilities-db;Port=5432
```

### Port already in use

The stack currently maps:

- `8081` -> portal
- `8080` -> API
- `5433` -> PostgreSQL

Stop the conflicting local process or intentionally change the host-side mapping in `compose.yaml`.

### Environment variable missing

If Compose reports a missing `POSTGRES_PASSWORD`, make sure `.env` exists in the repository root and contains:

```env
POSTGRES_PASSWORD=your-local-password
```

## Recognition service

The Python/OpenCV recognition work remains separate from the default Compose stack.

That is the current supported development boundary. The core Docker path is:

```text
Portal -> API -> PostgreSQL
```

Recognition integration can be developed independently without blocking the main operations platform.
