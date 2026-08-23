using FacilitiesApi.Data;
using FacilitiesApi.Services;
using Microsoft.EntityFrameworkCore;


// --------------------------------------------------
// Create ASP.NET Core application builder
// --------------------------------------------------

var builder = WebApplication.CreateBuilder(args);


// --------------------------------------------------
// Load PostgreSQL connection string
// --------------------------------------------------
//
// During development this should come from
// .NET User Secrets.
//
// We deliberately do NOT hard-code database passwords
// inside source code.
// --------------------------------------------------

var connectionString =
    builder.Configuration.GetConnectionString(
        "FacilitiesDatabase"
    );


// Fail immediately if database configuration
// is missing.
//
// It is better for startup to fail clearly than
// for database requests to fail unpredictably later.
if (string.IsNullOrWhiteSpace(connectionString))
{
    throw new InvalidOperationException(
        "FacilitiesDatabase connection string is missing."
    );
}


// --------------------------------------------------
// Register PostgreSQL database
// --------------------------------------------------
//
// UseNpgsql tells Entity Framework Core that
// PostgreSQL is the database engine.
// --------------------------------------------------

builder.Services.AddDbContext<FacilitiesDbContext>(
    options =>
        options.UseNpgsql(connectionString)
);


// --------------------------------------------------
// Register business services
// --------------------------------------------------
//
// Each service is scoped to one HTTP request, matching
// Entity Framework Core's DbContext lifetime.
// --------------------------------------------------

builder.Services.AddScoped<AttendanceService>();
builder.Services.AddScoped<DashboardService>();


// --------------------------------------------------
// Register controller support
// --------------------------------------------------

builder.Services.AddControllers();


// --------------------------------------------------
// Build application
// --------------------------------------------------

var app = builder.Build();


// --------------------------------------------------
// Map API controllers
// --------------------------------------------------

app.MapControllers();


// --------------------------------------------------
// Start web server
// --------------------------------------------------

app.Run();
