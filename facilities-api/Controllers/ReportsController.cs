using System.Text;
using FacilitiesApi.Configuration;
using FacilitiesApi.Data;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Options;

namespace FacilitiesApi.Controllers;

// --------------------------------------------------
// ReportsController
// --------------------------------------------------
//
// Produces day-scoped management reports from employee
// and attendance data.
//
// Routes:
// GET /api/reports/daily?date=2026-08-23
// GET /api/reports/daily.csv?date=2026-08-23
// --------------------------------------------------

[ApiController]
[Route("api/reports")]
public class ReportsController : ControllerBase
{
    private readonly FacilitiesDbContext _database;
    private readonly ShiftPolicyOptions _shiftPolicy;

    public ReportsController(
        FacilitiesDbContext database,
        IOptions<ShiftPolicyOptions> shiftPolicy
    )
    {
        _database = database;
        _shiftPolicy = shiftPolicy.Value;
    }

    [HttpGet("daily")]
    public async Task<IActionResult> GetDaily([FromQuery] DateOnly? date)
    {
        var report = await BuildDailyReportAsync(date);
        return Ok(report);
    }

    [HttpGet("daily.csv")]
    public async Task<IActionResult> DownloadDailyCsv([FromQuery] DateOnly? date)
    {
        var report = await BuildDailyReportAsync(date);
        var csv = new StringBuilder();

        csv.AppendLine(
            "Employee ID,Name,Department,Role,Status,First IN,Last OUT,Completed Hours,Punctuality,Minutes Late"
        );

        foreach (var row in report.Rows)
        {
            csv.AppendLine(string.Join(",",
                Csv(row.EmployeeId),
                Csv(row.Name),
                Csv(row.Department),
                Csv(row.Role),
                Csv(row.Status),
                Csv(row.FirstIn),
                Csv(row.LastOut),
                row.CompletedHours.ToString("0.00"),
                Csv(row.Punctuality),
                row.MinutesLate.ToString()
            ));
        }

        var bytes = Encoding.UTF8.GetBytes(csv.ToString());
        var fileName = $"facilities-attendance-{report.Date:yyyy-MM-dd}.csv";

        return File(bytes, "text/csv; charset=utf-8", fileName);
    }

    // --------------------------------------------------
    // Build one management day
    // --------------------------------------------------

    private async Task<DailyReport> BuildDailyReportAsync(DateOnly? requestedDate)
    {
        var zone = GetSouthAfricaTimeZone();
        var now = TimeZoneInfo.ConvertTimeFromUtc(DateTime.UtcNow, zone);
        var reportDate = requestedDate ?? DateOnly.FromDateTime(now);

        var employees = await _database
            .Employees
            .AsNoTracking()
            .OrderBy(employee => employee.EmployeeId)
            .ToListAsync();

        var records = await _database
            .AttendanceRecords
            .AsNoTracking()
            .OrderBy(record => record.Timestamp)
            .ToListAsync();

        var dayRecords = records
            .Select(record => new
            {
                Record = record,
                Local = ConvertToSouthAfricaTime(record.Timestamp, zone)
            })
            .Where(item => DateOnly.FromDateTime(item.Local) == reportDate)
            .ToList();

        var shiftStart = ParseTime(_shiftPolicy.StartTime, new TimeSpan(8, 0, 0));
        var lateThreshold = shiftStart.Add(
            TimeSpan.FromMinutes(Math.Max(0, _shiftPolicy.GraceMinutes))
        );

        var rows = new List<DailyReportRow>();

        foreach (var employee in employees)
        {
            var employeeEvents = dayRecords
                .Where(item => item.Record.EmployeeId == employee.EmployeeId)
                .OrderBy(item => item.Local)
                .ToList();

            var firstIn = employeeEvents
                .FirstOrDefault(item => item.Record.Action == "IN");

            var lastOut = employeeEvents
                .LastOrDefault(item => item.Record.Action == "OUT");

            var latest = employeeEvents.LastOrDefault();

            double completedMinutes = 0;
            DateTime? openSession = null;

            foreach (var item in employeeEvents)
            {
                if (item.Record.Action == "IN")
                {
                    openSession = item.Local;
                    continue;
                }

                if (
                    item.Record.Action == "OUT" &&
                    openSession.HasValue &&
                    item.Local >= openSession.Value
                )
                {
                    completedMinutes +=
                        (item.Local - openSession.Value).TotalMinutes;
                    openSession = null;
                }
            }

            var punctuality = "NOT_SEEN";
            var minutesLate = 0;

            if (firstIn is not null)
            {
                if (firstIn.Local.TimeOfDay <= lateThreshold)
                {
                    punctuality = "ON_TIME";
                }
                else
                {
                    punctuality = "LATE";
                    minutesLate = (int)Math.Ceiling(
                        (firstIn.Local.TimeOfDay - lateThreshold).TotalMinutes
                    );
                }
            }

            rows.Add(new DailyReportRow
            {
                EmployeeId = employee.EmployeeId,
                Name = employee.Name,
                Department = string.IsNullOrWhiteSpace(employee.Department)
                    ? "Unassigned"
                    : employee.Department,
                Role = employee.Role,
                Active = employee.Active,
                Status = latest is null ? "NOT_SEEN" : latest.Record.Action,
                FirstIn = firstIn?.Local.ToString("HH:mm") ?? "--",
                LastOut = lastOut?.Local.ToString("HH:mm") ?? "--",
                CompletedHours = Math.Round(completedMinutes / 60.0, 2),
                Punctuality = punctuality,
                MinutesLate = minutesLate
            });
        }

        var activeRows = rows.Where(row => row.Active).ToList();
        var seenRows = activeRows.Where(row => row.Status != "NOT_SEEN").ToList();

        return new DailyReport
        {
            Date = reportDate,
            ShiftStart = _shiftPolicy.StartTime,
            GraceMinutes = _shiftPolicy.GraceMinutes,
            ActiveEmployees = activeRows.Count,
            SeenEmployees = seenRows.Count,
            PresentNow = activeRows.Count(row => row.Status == "IN"),
            ClockedOut = activeRows.Count(row => row.Status == "OUT"),
            AbsentToday = activeRows.Count(row => row.Status == "NOT_SEEN"),
            LateEmployees = activeRows.Count(row => row.Punctuality == "LATE"),
            AttendanceRate = activeRows.Count == 0
                ? 0
                : Math.Round(seenRows.Count * 100.0 / activeRows.Count, 1),
            TotalCompletedHours = Math.Round(
                activeRows.Sum(row => row.CompletedHours),
                2
            ),
            Rows = rows
        };
    }

    private static string Csv(string value)
    {
        var escaped = value.Replace("\"", "\"\"");
        return $"\"{escaped}\"";
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

// --------------------------------------------------
// Internal report response models
// --------------------------------------------------

public class DailyReport
{
    public DateOnly Date { get; set; }
    public string ShiftStart { get; set; } = "08:00";
    public int GraceMinutes { get; set; }
    public int ActiveEmployees { get; set; }
    public int SeenEmployees { get; set; }
    public int PresentNow { get; set; }
    public int ClockedOut { get; set; }
    public int AbsentToday { get; set; }
    public int LateEmployees { get; set; }
    public double AttendanceRate { get; set; }
    public double TotalCompletedHours { get; set; }
    public List<DailyReportRow> Rows { get; set; } = new();
}

public class DailyReportRow
{
    public string EmployeeId { get; set; } = string.Empty;
    public string Name { get; set; } = string.Empty;
    public string Department { get; set; } = string.Empty;
    public string Role { get; set; } = string.Empty;
    public bool Active { get; set; }
    public string Status { get; set; } = "NOT_SEEN";
    public string FirstIn { get; set; } = "--";
    public string LastOut { get; set; } = "--";
    public double CompletedHours { get; set; }
    public string Punctuality { get; set; } = "NOT_SEEN";
    public int MinutesLate { get; set; }
}
