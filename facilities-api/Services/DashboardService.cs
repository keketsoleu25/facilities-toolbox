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

        var employeesSeenToday =
            employeesSeenTodayIds.Count;

        var attendanceRate = activeEmployees.Count == 0
            ? 0
            : Math.Round(
                employeesSeenToday * 100.0 /
                activeEmployees.Count,
                1
            );

        var absentToday = activeEmployees.Count(employee =>
            !employeesSeenTodayIds.Contains(employee.EmployeeId)
        );


        // --------------------------------------------------
        // Daily worked hours
        // --------------------------------------------------
        //
        // Attendance events are paired in chronological order.
        // Only completed IN -> OUT sessions are counted.
        // An employee who is still clocked IN is intentionally
        // excluded from completed worked hours until they OUT.
        // --------------------------------------------------

        var totalWorkedMinutes = 0.0;

        foreach (
            var employeeGroup in attendanceToday
                .GroupBy(record => record.EmployeeId)
        )
        {
            DateTime? openSession = null;

            foreach (var record in employeeGroup)
            {
                var localTimestamp =
                    ConvertToSouthAfricaTime(
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
                        (localTimestamp - openSession.Value)
                        .TotalMinutes;

                    openSession = null;
                }
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

                var departmentPresent =
                    departmentEmployees.Count(employee =>
                        latestByEmployee.TryGetValue(
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
                .Count();

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
            LatestActivity = latestActivity,
            Departments = departments,
            AttendanceTrend = trend
        };
    }


    // --------------------------------------------------
    // Convert one UTC database timestamp to South Africa
    // --------------------------------------------------

    private static DateTime ConvertToSouthAfricaTime(
        DateTime timestamp,
        TimeZoneInfo southAfricaZone
    )
    {
        return TimeZoneInfo.ConvertTimeFromUtc(
            DateTime.SpecifyKind(
                timestamp,
                DateTimeKind.Utc
            ),
            southAfricaZone
        );
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
