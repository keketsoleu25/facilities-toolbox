using FacilitiesApi.Data;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace FacilitiesApi.Controllers;

// --------------------------------------------------
// SiteOperationsController
// --------------------------------------------------
//
// Aggregates facilities structure and attendance into a
// site-level operational snapshot.
//
// Route:
// GET /api/site-operations/{siteCode}
// --------------------------------------------------

[ApiController]
[Route("api/site-operations")]
public class SiteOperationsController : ControllerBase
{
    private readonly FacilitiesDbContext _database;

    public SiteOperationsController(FacilitiesDbContext database)
    {
        _database = database;
    }

    [HttpGet("{siteCode}")]
    public async Task<IActionResult> GetSiteOperations(string siteCode)
    {
        var site = await _database
            .Sites
            .AsNoTracking()
            .FirstOrDefaultAsync(item => item.SiteCode == siteCode);

        if (site is null)
        {
            return NotFound(new
            {
                error = $"Site {siteCode} was not found."
            });
        }

        var buildings = await _database
            .Buildings
            .AsNoTracking()
            .Where(item => item.SiteId == site.Id)
            .OrderBy(item => item.Name)
            .ToListAsync();

        var departments = await _database
            .Departments
            .AsNoTracking()
            .Where(item => item.SiteId == site.Id)
            .OrderBy(item => item.Name)
            .ToListAsync();

        var departmentIds = departments
            .Select(item => item.Id)
            .ToHashSet();

        var employees = await _database
            .Employees
            .AsNoTracking()
            .Where(item =>
                item.Active &&
                item.DepartmentId.HasValue &&
                departmentIds.Contains(item.DepartmentId.Value)
            )
            .OrderBy(item => item.EmployeeId)
            .ToListAsync();

        var employeeIds = employees
            .Select(item => item.EmployeeId)
            .ToHashSet();

        var zone = GetSouthAfricaTimeZone();
        var now = TimeZoneInfo.ConvertTimeFromUtc(DateTime.UtcNow, zone);
        var today = now.Date;

        var attendanceToday = await _database
            .AttendanceRecords
            .AsNoTracking()
            .Where(record => employeeIds.Contains(record.EmployeeId))
            .ToListAsync();

        var localAttendanceToday = attendanceToday
            .Select(record => new
            {
                Record = record,
                LocalTimestamp = ConvertToSouthAfricaTime(record.Timestamp, zone)
            })
            .Where(item => item.LocalTimestamp.Date == today)
            .OrderBy(item => item.LocalTimestamp)
            .ToList();

        var latestToday = localAttendanceToday
            .GroupBy(item => item.Record.EmployeeId)
            .ToDictionary(
                group => group.Key,
                group => group.Last()
            );

        var presentNow = employees.Count(employee =>
            latestToday.TryGetValue(employee.EmployeeId, out var latest) &&
            latest.Record.Action == "IN"
        );

        var seenTodayIds = localAttendanceToday
            .Select(item => item.Record.EmployeeId)
            .Distinct()
            .ToHashSet();

        var attendanceRate = employees.Count == 0
            ? 0
            : Math.Round(seenTodayIds.Count * 100.0 / employees.Count, 1);

        var departmentHealth = departments
            .Select(department =>
            {
                var departmentEmployees = employees
                    .Where(employee => employee.DepartmentId == department.Id)
                    .ToList();

                var seen = departmentEmployees.Count(employee =>
                    seenTodayIds.Contains(employee.EmployeeId)
                );

                var present = departmentEmployees.Count(employee =>
                    latestToday.TryGetValue(employee.EmployeeId, out var latest) &&
                    latest.Record.Action == "IN"
                );

                return new
                {
                    department.Id,
                    department.DepartmentCode,
                    department.Name,
                    activeEmployees = departmentEmployees.Count,
                    seenToday = seen,
                    presentNow = present,
                    attendanceRate = departmentEmployees.Count == 0
                        ? 0
                        : Math.Round(seen * 100.0 / departmentEmployees.Count, 1)
                };
            })
            .ToList();

        return Ok(new
        {
            generatedAt = now,
            site = new
            {
                site.Id,
                site.SiteCode,
                site.Name,
                site.Address,
                site.Active
            },
            buildingCount = buildings.Count,
            departmentCount = departments.Count,
            activeEmployees = employees.Count,
            presentNow,
            absentToday = employees.Count - seenTodayIds.Count,
            attendanceRate,
            buildings = buildings.Select(building => new
            {
                building.Id,
                building.BuildingCode,
                building.Name,
                building.Active
            }),
            departments = departmentHealth
        });
    }

    private static DateTime ConvertToSouthAfricaTime(
        DateTime timestamp,
        TimeZoneInfo zone
    )
    {
        return TimeZoneInfo.ConvertTimeFromUtc(
            DateTime.SpecifyKind(timestamp, DateTimeKind.Utc),
            zone
        );
    }

    private static TimeZoneInfo GetSouthAfricaTimeZone()
    {
        try
        {
            return TimeZoneInfo.FindSystemTimeZoneById(
                "South Africa Standard Time"
            );
        }
        catch (TimeZoneNotFoundException)
        {
            return TimeZoneInfo.FindSystemTimeZoneById(
                "Africa/Johannesburg"
            );
        }
    }
}
