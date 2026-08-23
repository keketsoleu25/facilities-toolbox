using FacilitiesApi.Configuration;
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
// During development this comes from .NET User Secrets.
// Database credentials must never be hard-coded here.
// --------------------------------------------------

var connectionString =
    builder.Configuration.GetConnectionString(
        "FacilitiesDatabase"
    );

if (string.IsNullOrWhiteSpace(connectionString))
{
    throw new InvalidOperationException(
        "FacilitiesDatabase connection string is missing."
    );
}

// --------------------------------------------------
// Register PostgreSQL database
// --------------------------------------------------

builder.Services.AddDbContext<FacilitiesDbContext>(
    options => options.UseNpgsql(connectionString)
);

// --------------------------------------------------
// Register configurable product policies
// --------------------------------------------------
//
// v0.2 reads shift behaviour from appsettings.json.
// This removes duplicated hard-coded times from the API.
// --------------------------------------------------

builder.Services.Configure<ShiftPolicyOptions>(
    builder.Configuration.GetSection(
        ShiftPolicyOptions.SectionName
    )
);

// --------------------------------------------------
// Register business services
// --------------------------------------------------

builder.Services.AddScoped<AttendanceService>();
builder.Services.AddScoped<DashboardService>();
builder.Services.AddScoped<OperationalAlertsService>();

// --------------------------------------------------
// Register controller support
// --------------------------------------------------

builder.Services.AddControllers();

var app = builder.Build();

// --------------------------------------------------
// Map API controllers and start server
// --------------------------------------------------

app.MapControllers();
app.Run();
