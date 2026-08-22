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
//
// FacilitiesDbContext can now be injected into
// services such as AttendanceService.
// --------------------------------------------------

builder.Services.AddDbContext<FacilitiesDbContext>(
    options =>
        options.UseNpgsql(connectionString)
);


// --------------------------------------------------
// Register business services
// --------------------------------------------------
//
// Scoped means one AttendanceService instance is
// created per HTTP request.
//
// This works correctly with EF Core DbContext,
// which is also scoped.
// --------------------------------------------------

builder.Services.AddScoped<AttendanceService>();


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