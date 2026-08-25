using FacilitiesApi.Data;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace FacilitiesApi.Controllers;

// --------------------------------------------------
// WorkforceOperationsController
// --------------------------------------------------
//
// Provides a cross-site operational view answering:
//
// - Who is working today?
// - Where are they placed?
// - Which building / department owns them?
// - Which shift applies to them?
// - Are they currently IN, OUT, or not seen today?
//
// The endpoint derives presence from today's latest
// attendance event and never treats a historical IN as
// current presence.
// --------------------------------------------------

[ApiController]
[Route("api/workforce-operations")]
public class WorkforceOperationsController : ControllerBase
{
    private readonly FacilitiesDbContext _database;

    public WorkforceOperationsController(FacilitiesDbContext database)
    {
        _database = database;
    }

    // --------------------------------------------------
    // GET /api/workforce-operations
    // --------------------------------------------------

    [HttpGet]
    public async Task<IActionResult> GetOperationsBoard()
    {
        var zone = GetSouthAfricaTimeZone();
        var now = TimeZoneInfo.ConvertTimeFromUtc(DateTime.UtcNow, zone);
        var today = DateOnly.FromDateTime(now);

        // Load active employees with their structured
        // facilities placement.
        var employees = await _database
            .Employees
            .AsNoTracking()
            .Where(employee => employee.Active)
            .Include(employee => employee.DepartmentRecord)
                .ThenInclude(department => department!.Site)
            .Include(employee => employee.DepartmentRecord)
                .ThenInclude(department => department!.Building)
            .OrderBy(employee => employee.EmployeeId)
            .ToListAsync();

        var employeeIds = employees
            .Select(employee => employee.EmployeeId)
            .ToHashSet();

        // Load attendance for the relevant employees and
        // reduce it to the current South African business day.
        var attendance = await _database
            .AttendanceRecords
            .AsNoTracking()
            .Where(record => employeeIds.Contains(record.EmployeeId))
            .ToListAsync();

        var todayAttendance = attendance
            .Select(record => new
            {
                Record = record,
                LocalTimestamp = ConvertToSouthAfricaTime(record.Timestamp, zone)
            })
            .Where(item => DateOnly.FromDateTime(item.LocalTimestamp) == today)
            .OrderBy(item => item.LocalTimestamp)
            .ToList();

        var latestToday = todayAttendance
            .GroupBy(item => item.Record.EmployeeId)
            .ToDictionary(group => group.Key, group => group.Last());

        var firstInToday = todayAttendance
            .Where(item => item.Record.Action == "IN")
            .GroupBy(item => item.Record.EmployeeId)
            .ToDictionary(group => group.Key, group => group.First().LocalTimestamp);

        // Active shift assignments are resolved separately so
        // the board can show the schedule attached to each
        // employee without duplicating shift data on Employee.
        var assignments = await _database
            .ShiftAssignments
            .AsNoTracking()
            .Include(assignment => assignment.Shift)
            .Where(assignment =>
                assignment.Active &&
                employeeIds.Contains(assignment.EmployeeId) &&
                assignment.EffectiveFrom <= today &&
                (!assignment.EffectiveTo.HasValue || assignment.EffectiveTo.Value >= today)
            )
            .OrderByDescending(assignment => assignment.EffectiveFrom)
            .ToListAsync();

        var assignmentByEmployee = assignments
            .GroupBy(assignment => assignment.EmployeeId)
            .ToDictionary(group => group.Key, group => group.First());

        var rows = employees
            .Select(employee =>
            {
                latestToday.TryGetValue(employee.EmployeeId, out var latest);
                firstInToday.TryGetValue(employee.EmployeeId, out var firstIn);
                assignmentByEmployee.TryGetValue(employee.EmployeeId, out var assignment);

                var status = latest is null
                    ? "NOT_SEEN"
                    : latest.Record.Action;

                return new
                {
                    employee.EmployeeId,
                    employee.Name,
                    employee.Role,
                    status,
                    firstArrival = firstIn == default
                        ? null
                        : firstIn.ToString("HH:mm"),
                    lastEvent = latest is null
                        ? null
                        : latest.LocalTimestamp.ToString("HH:mm"),
                    siteCode = employee.DepartmentRecord?.Site?.SiteCode,
                    site = employee.DepartmentRecord?.Site?.Name,
                    buildingCode = employee.DepartmentRecord?.Building?.BuildingCode,
                    building = employee.DepartmentRecord?.Building?.Name,
                    departmentCode = employee.DepartmentRecord?.DepartmentCode,
                    department = employee.DepartmentRecord?.Name
                        ?? employee.Department,
                    shiftCode = assignment?.Shift?.ShiftCode,
                    shift = assignment?.Shift?.Name,
                    shiftStart = assignment?.Shift is null
                        ? null
                        : assignment.Shift.StartTime.ToString(@"hh\:mm"),
                    shiftEnd = assignment?.Shift is null
                        ? null
                        : assignment.Shift.EndTime.ToString(@"hh\:mm")
                };
            })
            .ToList();

        var presentNow = rows.Count(row => row.status == "IN");
        var clockedOut = rows.Count(row => row.status == "OUT");
        var notSeen = rows.Count(row => row.status == "NOT_SEEN");
        var unplaced = rows.Count(row => string.IsNullOrWhiteSpace(row.siteCode));

        // Building occupancy is derived from employees whose
        // latest event today is IN.
        var buildingOccupancy = rows
            .Where(row => !string.IsNullOrWhiteSpace(row.buildingCode))
            .GroupBy(row => new
            {
                row.siteCode,
                row.site,
                row.buildingCode,
                row.building
            })
            .OrderBy(group => group.Key.site)
            .ThenBy(group => group.Key.building)
            .Select(group => new
            {
                group.Key.siteCode,
                group.Key.site,
                group.Key.buildingCode,
                group.Key.building,
                assignedEmployees = group.Count(),
                presentNow = group.Count(row => row.status == "IN"),
                clockedOut = group.Count(row => row.status == "OUT"),
                notSeenToday = group.Count(row => row.status == "NOT_SEEN")
            })
            .ToList();

        return Ok(new
        {
            generatedAt = now,
            activeEmployees = rows.Count,
            presentNow,
            clockedOut,
            notSeenToday = notSeen,
            unplacedEmployees = unplaced,
            buildingOccupancy,
            employees = rows
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
