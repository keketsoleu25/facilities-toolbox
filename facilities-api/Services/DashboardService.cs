using FacilitiesApi.Data;
using FacilitiesApi.Dtos;
using Microsoft.EntityFrameworkCore;

namespace FacilitiesApi.Services;


// --------------------------------------------------
// DashboardService
// --------------------------------------------------
//
// Converts raw employee and attendance records into
// operational information for the management dashboard.
// --------------------------------------------------

public class DashboardService
{
    private readonly FacilitiesDbContext _database;

    public DashboardService(FacilitiesDbContext database)
    {
        _database = database;
    }


    // --------------------------------------------------
    // Build current dashboard snapshot
    // --------------------------------------------------

    public async Task<DashboardResponse> GetSnapshotAsync()
    {
        // Load employees once because the current data set is
        // small. We can optimise with dedicated aggregate SQL
        // queries later when the product grows.
        var employees = await _database
            .Employees
            .AsNoTracking()
            .OrderBy(employee => employee.EmployeeId)
            .ToListAsync();

        var attendance = await _database
            .AttendanceRecords
            .AsNoTracking()
            .Include(record => record.Employee)
            .OrderByDescending(record => record.Timestamp)
            .ToListAsync();


        // --------------------------------------------------
        // Current attendance state
        // --------------------------------------------------
        //
        // The newest event for each employee tells us whether
        // that employee is currently IN or OUT.
        // --------------------------------------------------

        var latestByEmployee = attendance
            .GroupBy(record => record.EmployeeId)
            .ToDictionary(
                group => group.Key,
                group => group.First()
            );

        var activeEmployees = employees
            .Where(employee => employee.Active)
            .ToList();

        var presentNow = activeEmployees.Count(employee =>
            latestByEmployee.TryGetValue(
                employee.EmployeeId,
                out var latest
            ) &&
            latest.Action == "IN"
        );

        var clockedOut = activeEmployees.Count(employee =>
            latestByEmployee.TryGetValue(
                employee.EmployeeId,
                out var latest
            ) &&
            latest.Action == "OUT"
        );


        // --------------------------------------------------
        // South African business day
        // --------------------------------------------------
        //
        // Attendance records are stored in UTC, but the product
        // is being built for South African facilities teams.
        // --------------------------------------------------

        var southAfricaZone = GetSouthAfricaTimeZone();

        var generatedAt = TimeZoneInfo.ConvertTimeFromUtc(
            DateTime.UtcNow,
            southAfricaZone
        );

        var today = generatedAt.Date;

        var attendanceToday = attendance
            .Where(record =>
                TimeZoneInfo.ConvertTimeFromUtc(
                    DateTime.SpecifyKind(
                        record.Timestamp,
                        DateTimeKind.Utc
                    ),
                    southAfricaZone
                ).Date == today
            )
            .ToList();

        var employeesSeenToday = attendanceToday
            .Select(record => record.EmployeeId)
            .Distinct()
            .Count();

        var attendanceRate = activeEmployees.Count == 0
            ? 0
            : Math.Round(
                employeesSeenToday * 100.0 /
                activeEmployees.Count,
                1
            );


        // --------------------------------------------------
        // Latest operational activity
        // --------------------------------------------------

        var latestActivity = attendance
            .Take(10)
            .Select(record => new DashboardActivityItem
            {
                Id = record.Id,
                EmployeeId = record.EmployeeId,
                EmployeeName = record.Employee?.Name
                    ?? record.EmployeeId,
                Department = record.Employee?.Department
                    ?? string.Empty,
                Action = record.Action,
                Timestamp = TimeZoneInfo.ConvertTimeFromUtc(
                    DateTime.SpecifyKind(
                        record.Timestamp,
                        DateTimeKind.Utc
                    ),
                    southAfricaZone
                )
            })
            .ToList();


        return new DashboardResponse
        {
            GeneratedAt = generatedAt,
            TotalEmployees = employees.Count,
            ActiveEmployees = activeEmployees.Count,
            PresentNow = presentNow,
            ClockedOut = clockedOut,
            AttendanceEventsToday = attendanceToday.Count,
            AttendanceRate = attendanceRate,
            LatestActivity = latestActivity
        };
    }


    // --------------------------------------------------
    // Resolve South Africa timezone cross-platform
    // --------------------------------------------------
    //
    // Windows and Linux use different timezone identifiers.
    // Supporting both keeps local Windows development and
    // future Linux hosting compatible.
    // --------------------------------------------------

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
