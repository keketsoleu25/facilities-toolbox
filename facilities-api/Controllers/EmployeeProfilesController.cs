using FacilitiesApi.Configuration;
using FacilitiesApi.Data;
using FacilitiesApi.Dtos;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Options;

namespace FacilitiesApi.Controllers;

// --------------------------------------------------
// EmployeeProfilesController
// --------------------------------------------------
//
// Read-only operational drill-down for one employee.
// Route:
// GET /api/employee-profiles/{employeeId}
// --------------------------------------------------

[ApiController]
[Route("api/employee-profiles")]
public class EmployeeProfilesController : ControllerBase
{
    private readonly FacilitiesDbContext _database;
    private readonly ShiftPolicyOptions _shiftPolicy;

    public EmployeeProfilesController(
        FacilitiesDbContext database,
        IOptions<ShiftPolicyOptions> shiftPolicy
    )
    {
        _database = database;
        _shiftPolicy = shiftPolicy.Value;
    }

    [HttpGet("{employeeId}")]
    public async Task<IActionResult> GetProfile(string employeeId)
    {
        var employee = await _database
            .Employees
            .AsNoTracking()
            .FirstOrDefaultAsync(item => item.EmployeeId == employeeId);

        if (employee is null)
        {
            return NotFound(new
            {
                error = $"Employee {employeeId} was not found."
            });
        }

        var records = await _database
            .AttendanceRecords
            .AsNoTracking()
            .Where(record => record.EmployeeId == employeeId)
            .OrderByDescending(record => record.Timestamp)
            .ToListAsync();

        var zone = GetSouthAfricaTimeZone();
        var now = TimeZoneInfo.ConvertTimeFromUtc(DateTime.UtcNow, zone);
        var today = now.Date;

        var todayRecords = records
            .Select(record => new
            {
                Record = record,
                LocalTimestamp = ConvertToSouthAfricaTime(record.Timestamp, zone)
            })
            .Where(item => item.LocalTimestamp.Date == today)
            .OrderBy(item => item.LocalTimestamp)
            .ToList();

        var latestToday = todayRecords.LastOrDefault();
        var firstArrival = todayRecords
            .FirstOrDefault(item => item.Record.Action == "IN");

        double completedMinutes = 0;
        DateTime? openSession = null;

        foreach (var item in todayRecords)
        {
            if (item.Record.Action == "IN")
            {
                openSession = item.LocalTimestamp;
                continue;
            }

            if (
                item.Record.Action == "OUT" &&
                openSession.HasValue &&
                item.LocalTimestamp >= openSession.Value
            )
            {
                completedMinutes +=
                    (item.LocalTimestamp - openSession.Value).TotalMinutes;

                openSession = null;
            }
        }

        var hasOpenSession =
            latestToday is not null &&
            latestToday.Record.Action == "IN" &&
            openSession.HasValue;

        // --------------------------------------------------
        // Configurable punctuality rule
        // --------------------------------------------------

        var shiftStart = ParseTime(
            _shiftPolicy.StartTime,
            new TimeSpan(8, 0, 0)
        );

        var graceMinutes = Math.Max(0, _shiftPolicy.GraceMinutes);
        var lateThreshold = shiftStart.Add(
            TimeSpan.FromMinutes(graceMinutes)
        );

        var punctualityStatus = "NOT_SEEN";
        var minutesLate = 0;

        if (firstArrival is not null)
        {
            var arrivalTime = firstArrival.LocalTimestamp.TimeOfDay;

            if (arrivalTime <= lateThreshold)
            {
                punctualityStatus = "ON_TIME";
            }
            else
            {
                punctualityStatus = "LATE";
                minutesLate = (int)Math.Ceiling(
                    (arrivalTime - lateThreshold).TotalMinutes
                );
            }
        }

        var response = new EmployeeProfileResponse
        {
            EmployeeId = employee.EmployeeId,
            Name = employee.Name,
            Department = employee.Department,
            Role = employee.Role,
            Active = employee.Active,
            CurrentStatus = latestToday is null
                ? "NOT_SEEN"
                : latestToday.Record.Action,
            FirstArrivalToday = firstArrival is null
                ? "--"
                : firstArrival.LocalTimestamp.ToString("HH:mm"),
            LastEventToday = latestToday is null
                ? "--"
                : latestToday.LocalTimestamp.ToString("HH:mm"),
            CompletedHoursToday = Math.Round(completedMinutes / 60.0, 2),
            HasOpenSession = hasOpenSession,
            OpenSessionHours = hasOpenSession
                ? Math.Round(
                    Math.Max(0, (now - openSession!.Value).TotalHours),
                    2
                )
                : 0,
            ShiftStart = shiftStart.ToString(@"hh\:mm"),
            GraceMinutes = graceMinutes,
            PunctualityStatus = punctualityStatus,
            MinutesLate = minutesLate,
            RecentAttendance = records
                .Take(20)
                .Select(record => new EmployeeProfileAttendanceItem
                {
                    Id = record.Id,
                    Action = record.Action,
                    Timestamp = ConvertToSouthAfricaTime(
                        record.Timestamp,
                        zone
                    )
                })
                .ToList()
        };

        return Ok(response);
    }

    private static TimeSpan ParseTime(string value, TimeSpan fallback)
    {
        return TimeSpan.TryParse(value, out var parsed)
            ? parsed
            : fallback;
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
