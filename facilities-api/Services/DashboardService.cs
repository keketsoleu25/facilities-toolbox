using FacilitiesApi.Data;
using FacilitiesApi.Dtos;
using FacilitiesApi.Models;
using Microsoft.EntityFrameworkCore;

namespace FacilitiesApi.Services;

// --------------------------------------------------
// DashboardService
// --------------------------------------------------
//
// Converts raw employee and attendance records into
// day-aware operational intelligence.
// --------------------------------------------------

public class DashboardService
{
    private readonly FacilitiesDbContext _database;

    public DashboardService(FacilitiesDbContext database)
    {
        _database = database;
    }

    public async Task<DashboardResponse> GetSnapshotAsync()
    {
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

        var activeEmployees = employees
            .Where(employee => employee.Active)
            .ToList();

        var southAfricaZone = GetSouthAfricaTimeZone();

        var generatedAt = TimeZoneInfo.ConvertTimeFromUtc(
            DateTime.UtcNow,
            southAfricaZone
        );

        var today = generatedAt.Date;

        // --------------------------------------------------
        // Today's events only
        // --------------------------------------------------

        var attendanceToday = attendance
            .Where(record =>
                ConvertToSouthAfricaTime(
                    record.Timestamp,
                    southAfricaZone
                ).Date == today
            )
            .OrderBy(record => record.Timestamp)
            .ToList();

        var employeesSeenTodayIds = attendanceToday
            .Select(record => record.EmployeeId)
            .Distinct()
            .ToHashSet();

        // The latest event TODAY determines current state.
        var latestTodayByEmployee = attendanceToday
            .GroupBy(record => record.EmployeeId)
            .ToDictionary(
                group => group.Key,
                group => group.Last()
            );

        var presentNow = activeEmployees.Count(employee =>
            latestTodayByEmployee.TryGetValue(
                employee.EmployeeId,
                out var latest
            ) &&
            latest.Action == "IN"
        );

        var clockedOut = activeEmployees.Count(employee =>
            latestTodayByEmployee.TryGetValue(
                employee.EmployeeId,
                out var latest
            ) &&
            latest.Action == "OUT"
        );

        var absentToday = activeEmployees.Count(employee =>
            !employeesSeenTodayIds.Contains(employee.EmployeeId)
        );

        var attendanceRate = activeEmployees.Count == 0
            ? 0
            : Math.Round(
                employeesSeenTodayIds.Count * 100.0 /
                activeEmployees.Count,
                1
            );

        // --------------------------------------------------
        // Daily sessions and completed hours
        // --------------------------------------------------

        var totalWorkedMinutes = 0.0;
        var openSessions = new List<DashboardOpenSessionItem>();

        foreach (var employee in activeEmployees)
        {
            var employeeEvents = attendanceToday
                .Where(record => record.EmployeeId == employee.EmployeeId)
                .OrderBy(record => record.Timestamp)
                .ToList();

            DateTime? openSession = null;

            foreach (var record in employeeEvents)
            {
                var localTimestamp = ConvertToSouthAfricaTime(
                    record.Timestamp,
                    southAfricaZone
                );

                if (record.Action == "IN")
                {
                    openSession = localTimestamp;
                    continue;
                }

                if (
                    record.Action == "OUT" &&
                    openSession.HasValue &&
                    localTimestamp >= openSession.Value
                )
                {
                    totalWorkedMinutes +=
                        (localTimestamp - openSession.Value).TotalMinutes;

                    openSession = null;
                }
            }

            // If the latest event today is IN, expose the open
            // session so the command centre can warn operators.
            if (
                latestTodayByEmployee.TryGetValue(
                    employee.EmployeeId,
                    out var latestToday
                ) &&
                latestToday.Action == "IN" &&
                openSession.HasValue
            )
            {
                openSessions.Add(new DashboardOpenSessionItem
                {
                    EmployeeId = employee.EmployeeId,
                    EmployeeName = employee.Name,
                    Department = employee.Department,
                    ClockedInAt = openSession.Value,
                    OpenHours = Math.Round(
                        Math.Max(
                            0,
                            (generatedAt - openSession.Value).TotalHours
                        ),
                        2
                    )
                });
            }
        }

        var totalHoursWorkedToday =
            Math.Round(totalWorkedMinutes / 60.0, 1);

        // --------------------------------------------------
        // Average first arrival
        // --------------------------------------------------

        var firstArrivals = attendanceToday
            .Where(record => record.Action == "IN")
            .GroupBy(record => record.EmployeeId)
            .Select(group =>
                ConvertToSouthAfricaTime(
                    group.First().Timestamp,
                    southAfricaZone
                )
            )
            .ToList();

        var averageFirstArrival = "--";

        if (firstArrivals.Count > 0)
        {
            var averageTicks =
                (long) firstArrivals
                    .Average(arrival => arrival.TimeOfDay.Ticks);

            averageFirstArrival =
                new TimeSpan(averageTicks)
                    .ToString(@"hh\:mm");
        }

        // --------------------------------------------------
        // Department health
        // --------------------------------------------------

        var departments = activeEmployees
            .GroupBy(employee =>
                string.IsNullOrWhiteSpace(employee.Department)
                    ? "Unassigned"
                    : employee.Department
            )
            .OrderBy(group => group.Key)
            .Select(group =>
            {
                var departmentEmployees = group.ToList();

                var seenToday = departmentEmployees.Count(employee =>
                    employeesSeenTodayIds.Contains(employee.EmployeeId)
                );

                var departmentPresent = departmentEmployees.Count(employee =>
                    latestTodayByEmployee.TryGetValue(
                        employee.EmployeeId,
                        out var latest
                    ) &&
                    latest.Action == "IN"
                );

                return new DashboardDepartmentItem
                {
                    Department = group.Key,
                    ActiveEmployees = departmentEmployees.Count,
                    SeenToday = seenToday,
                    PresentNow = departmentPresent,
                    AttendanceRate = departmentEmployees.Count == 0
                        ? 0
                        : Math.Round(
                            seenToday * 100.0 /
                            departmentEmployees.Count,
                            1
                        )
                };
            })
            .ToList();

        // --------------------------------------------------
        // Seven-day attendance trend
        // --------------------------------------------------

        var trend = new List<DashboardTrendItem>();

        for (var offset = 6; offset >= 0; offset--)
        {
            var date = today.AddDays(-offset);

            var seenOnDate = attendance
                .Where(record =>
                    ConvertToSouthAfricaTime(
                        record.Timestamp,
                        southAfricaZone
                    ).Date == date
                )
                .Select(record => record.EmployeeId)
                .Distinct()
                .Count(id => activeEmployees.Any(
                    employee => employee.EmployeeId == id
                ));

            trend.Add(new DashboardTrendItem
            {
                Date = date,
                SeenEmployees = seenOnDate,
                AttendanceRate = activeEmployees.Count == 0
                    ? 0
                    : Math.Round(
                        seenOnDate * 100.0 /
                        activeEmployees.Count,
                        1
                    )
            });
        }

        // Latest activity intentionally remains historical so
        // operators still see the most recent system events.
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
                Timestamp = ConvertToSouthAfricaTime(
                    record.Timestamp,
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
            AbsentToday = absentToday,
            AttendanceEventsToday = attendanceToday.Count,
            AttendanceRate = attendanceRate,
            TotalHoursWorkedToday = totalHoursWorkedToday,
            AverageFirstArrival = averageFirstArrival,
            OpenSessionCount = openSessions.Count,
            OpenSessions = openSessions
                .OrderByDescending(session => session.OpenHours)
                .ToList(),
            LatestActivity = latestActivity,
            Departments = departments,
            AttendanceTrend = trend
        };
    }

    private static DateTime ConvertToSouthAfricaTime(
        DateTime timestamp,
        TimeZoneInfo southAfricaZone
    )
    {
        return TimeZoneInfo.ConvertTimeFromUtc(
            DateTime.SpecifyKind(timestamp, DateTimeKind.Utc),
            southAfricaZone
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
