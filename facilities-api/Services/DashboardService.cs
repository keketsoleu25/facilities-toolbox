using FacilitiesApi.Configuration;
using FacilitiesApi.Data;
using FacilitiesApi.Dtos;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Options;

namespace FacilitiesApi.Services;

// --------------------------------------------------
// DashboardService
// --------------------------------------------------
//
// Converts raw employee and attendance records into
// day-aware operational intelligence.
//
// v0.2 also applies configurable shift policies:
//
// - shift start time
// - grace period
// - minimum attendance target
//
// Raw attendance recording remains the responsibility
// of AttendanceService.
// --------------------------------------------------

public class DashboardService
{
    // EF Core database context.
    private readonly FacilitiesDbContext _database;

    // Configurable attendance and shift policy.
    private readonly ShiftPolicyOptions _shiftPolicy;


    // --------------------------------------------------
    // Constructor
    // --------------------------------------------------
    //
    // ASP.NET Core injects both dependencies through
    // dependency injection.
    // --------------------------------------------------

    public DashboardService(
        FacilitiesDbContext database,
        IOptions<ShiftPolicyOptions> shiftPolicy
    )
    {
        _database = database;
        _shiftPolicy = shiftPolicy.Value;
    }


    // --------------------------------------------------
    // Get dashboard snapshot
    // --------------------------------------------------

    public async Task<DashboardResponse> GetSnapshotAsync()
    {
        // --------------------------------------------------
        // Load employees
        // --------------------------------------------------

        var employees = await _database
            .Employees
            .AsNoTracking()
            .OrderBy(employee => employee.EmployeeId)
            .ToListAsync();


        // --------------------------------------------------
        // Load attendance history
        // --------------------------------------------------
        //
        // For v0.2 we keep the existing behaviour.
        //
        // A later optimisation can query only the date
        // range required by the dashboard.
        // --------------------------------------------------

        var attendance = await _database
            .AttendanceRecords
            .AsNoTracking()
            .Include(record => record.Employee)
            .OrderByDescending(record => record.Timestamp)
            .ToListAsync();


        // --------------------------------------------------
        // Active employees
        // --------------------------------------------------

        var activeEmployees = employees
            .Where(employee => employee.Active)
            .ToList();


        // --------------------------------------------------
        // South African local time
        // --------------------------------------------------

        var southAfricaZone = GetSouthAfricaTimeZone();

        var generatedAt = TimeZoneInfo.ConvertTimeFromUtc(
            DateTime.UtcNow,
            southAfricaZone
        );

        var today = generatedAt.Date;


        // --------------------------------------------------
        // Parse configured shift start
        // --------------------------------------------------
        //
        // Example:
        //
        // StartTime = "08:00"
        // GraceMinutes = 15
        //
        // Employees arriving at or before 08:15 are
        // considered on time.
        // --------------------------------------------------

        if (!TimeSpan.TryParse(
            _shiftPolicy.StartTime,
            out var shiftStartTime
        ))
        {
            throw new InvalidOperationException(
                $"Invalid ShiftPolicy StartTime: " +
                $"{_shiftPolicy.StartTime}."
            );
        }

        var shiftStart = today.Add(shiftStartTime);

        var onTimeCutoff = shiftStart.AddMinutes(
            _shiftPolicy.GraceMinutes
        );


        // --------------------------------------------------
        // Today's attendance events
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


        // --------------------------------------------------
        // Employees seen today
        // --------------------------------------------------

        var employeesSeenTodayIds = attendanceToday
            .Select(record => record.EmployeeId)
            .Distinct()
            .ToHashSet();


        // --------------------------------------------------
        // Latest event today per employee
        // --------------------------------------------------
        //
        // The latest event TODAY determines whether an
        // employee is currently IN or OUT.
        // --------------------------------------------------

        var latestTodayByEmployee = attendanceToday
            .GroupBy(record => record.EmployeeId)
            .ToDictionary(
                group => group.Key,
                group => group.Last()
            );


        // --------------------------------------------------
        // Present now
        // --------------------------------------------------

        var presentNow = activeEmployees.Count(employee =>
            latestTodayByEmployee.TryGetValue(
                employee.EmployeeId,
                out var latest
            ) &&
            latest.Action == "IN"
        );


        // --------------------------------------------------
        // Clocked out
        // --------------------------------------------------

        var clockedOut = activeEmployees.Count(employee =>
            latestTodayByEmployee.TryGetValue(
                employee.EmployeeId,
                out var latest
            ) &&
            latest.Action == "OUT"
        );


        // --------------------------------------------------
        // Absent today
        // --------------------------------------------------

        var absentToday = activeEmployees.Count(employee =>
            !employeesSeenTodayIds.Contains(
                employee.EmployeeId
            )
        );


        // --------------------------------------------------
        // Attendance rate
        // --------------------------------------------------

        var attendanceRate = activeEmployees.Count == 0
            ? 0
            : Math.Round(
                employeesSeenTodayIds.Count * 100.0 /
                activeEmployees.Count,
                1
            );


        // --------------------------------------------------
        // Attendance target health
        // --------------------------------------------------
        //
        // This compares today's attendance rate with the
        // configured minimum operational target.
        // --------------------------------------------------

        var attendanceBelowTarget =
            attendanceRate <
            _shiftPolicy.MinimumAttendanceRate;


        // --------------------------------------------------
        // First arrivals today
        // --------------------------------------------------
        //
        // Only the employee's FIRST IN event determines
        // whether they arrived on time or late.
        // --------------------------------------------------

        var firstArrivalByEmployee = attendanceToday
            .Where(record => record.Action == "IN")
            .GroupBy(record => record.EmployeeId)
            .ToDictionary(
                group => group.Key,
                group => group.First()
            );


        // --------------------------------------------------
        // Calculate on-time and late arrivals
        // --------------------------------------------------

        var onTimeToday = 0;

        var lateArrivals =
            new List<DashboardLateArrivalItem>();


        foreach (var employee in activeEmployees)
        {
            // Employee has not clocked IN today.
            if (!firstArrivalByEmployee.TryGetValue(
                employee.EmployeeId,
                out var firstArrival
            ))
            {
                continue;
            }


            var arrivalTime = ConvertToSouthAfricaTime(
                firstArrival.Timestamp,
                southAfricaZone
            );


            // --------------------------------------------------
            // On-time arrival
            // --------------------------------------------------

            if (arrivalTime <= onTimeCutoff)
            {
                onTimeToday++;

                continue;
            }


            // --------------------------------------------------
            // Late arrival
            // --------------------------------------------------
            //
            // MinutesLate measures lateness beyond the
            // allowed grace-period cutoff.
            // --------------------------------------------------

            var minutesLate = (int)Math.Ceiling(
                (arrivalTime - onTimeCutoff)
                .TotalMinutes
            );


            lateArrivals.Add(
                new DashboardLateArrivalItem
                {
                    EmployeeId = employee.EmployeeId,

                    EmployeeName = employee.Name,

                    Department =
                        employee.Department
                        ?? string.Empty,

                    ArrivedAt = arrivalTime,

                    MinutesLate = minutesLate
                }
            );
        }


        var lateToday = lateArrivals.Count;


        // --------------------------------------------------
        // Daily sessions and completed hours
        // --------------------------------------------------

        var totalWorkedMinutes = 0.0;

        var openSessions =
            new List<DashboardOpenSessionItem>();


        foreach (var employee in activeEmployees)
        {
            var employeeEvents = attendanceToday
                .Where(record =>
                    record.EmployeeId ==
                    employee.EmployeeId
                )
                .OrderBy(record => record.Timestamp)
                .ToList();


            DateTime? openSession = null;


            foreach (var record in employeeEvents)
            {
                var localTimestamp =
                    ConvertToSouthAfricaTime(
                        record.Timestamp,
                        southAfricaZone
                    );


                // Start a new attendance session.
                if (record.Action == "IN")
                {
                    openSession = localTimestamp;

                    continue;
                }


                // Complete an existing attendance session.
                if (
                    record.Action == "OUT" &&
                    openSession.HasValue &&
                    localTimestamp >= openSession.Value
                )
                {
                    totalWorkedMinutes +=
                        (
                            localTimestamp -
                            openSession.Value
                        )
                        .TotalMinutes;

                    openSession = null;
                }
            }


            // --------------------------------------------------
            // Detect open attendance session
            // --------------------------------------------------
            //
            // If the latest event today is IN, expose the
            // session to the dashboard command centre.
            // --------------------------------------------------

            if (
                latestTodayByEmployee.TryGetValue(
                    employee.EmployeeId,
                    out var latestToday
                ) &&
                latestToday.Action == "IN" &&
                openSession.HasValue
            )
            {
                openSessions.Add(
                    new DashboardOpenSessionItem
                    {
                        EmployeeId =
                            employee.EmployeeId,

                        EmployeeName =
                            employee.Name,

                        Department =
                            employee.Department
                            ?? string.Empty,

                        ClockedInAt =
                            openSession.Value,

                        OpenHours = Math.Round(
                            Math.Max(
                                0,
                                (
                                    generatedAt -
                                    openSession.Value
                                )
                                .TotalHours
                            ),
                            2
                        )
                    }
                );
            }
        }


        // --------------------------------------------------
        // Total completed hours worked today
        // --------------------------------------------------

        var totalHoursWorkedToday =
            Math.Round(
                totalWorkedMinutes / 60.0,
                1
            );


        // --------------------------------------------------
        // Average first arrival
        // --------------------------------------------------

        var firstArrivals = firstArrivalByEmployee
            .Values
            .Select(record =>
                ConvertToSouthAfricaTime(
                    record.Timestamp,
                    southAfricaZone
                )
            )
            .ToList();


        var averageFirstArrival = "--";


        if (firstArrivals.Count > 0)
        {
            var averageTicks =
                (long)firstArrivals
                    .Average(
                        arrival =>
                            arrival.TimeOfDay.Ticks
                    );


            averageFirstArrival =
                new TimeSpan(averageTicks)
                    .ToString(@"hh\:mm");
        }


        // --------------------------------------------------
        // Department health
        // --------------------------------------------------

        var departments = activeEmployees
            .GroupBy(employee =>
                string.IsNullOrWhiteSpace(
                    employee.Department
                )
                    ? "Unassigned"
                    : employee.Department
            )
            .OrderBy(group => group.Key)
            .Select(group =>
            {
                var departmentEmployees =
                    group.ToList();


                var seenToday =
                    departmentEmployees.Count(
                        employee =>
                            employeesSeenTodayIds
                                .Contains(
                                    employee.EmployeeId
                                )
                    );


                var departmentPresent =
                    departmentEmployees.Count(
                        employee =>
                            latestTodayByEmployee
                                .TryGetValue(
                                    employee.EmployeeId,
                                    out var latest
                                ) &&
                            latest.Action == "IN"
                    );


                return new DashboardDepartmentItem
                {
                    Department = group.Key,

                    ActiveEmployees =
                        departmentEmployees.Count,

                    SeenToday =
                        seenToday,

                    PresentNow =
                        departmentPresent,

                    AttendanceRate =
                        departmentEmployees.Count == 0
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

        var trend =
            new List<DashboardTrendItem>();


        for (var offset = 6; offset >= 0; offset--)
        {
            var date =
                today.AddDays(-offset);


            var seenOnDate = attendance
                .Where(record =>
                    ConvertToSouthAfricaTime(
                        record.Timestamp,
                        southAfricaZone
                    ).Date == date
                )
                .Select(record => record.EmployeeId)
                .Distinct()
                .Count(id =>
                    activeEmployees.Any(
                        employee =>
                            employee.EmployeeId == id
                    )
                );


            trend.Add(
                new DashboardTrendItem
                {
                    Date = date,

                    SeenEmployees =
                        seenOnDate,

                    AttendanceRate =
                        activeEmployees.Count == 0
                            ? 0
                            : Math.Round(
                                seenOnDate * 100.0 /
                                activeEmployees.Count,
                                1
                            )
                }
            );
        }


        // --------------------------------------------------
        // Latest activity
        // --------------------------------------------------
        //
        // Latest activity intentionally remains historical
        // so operators can still see the most recent system
        // events even when today has no activity.
        // --------------------------------------------------

        var latestActivity = attendance
            .Take(10)
            .Select(record =>
                new DashboardActivityItem
                {
                    Id = record.Id,

                    EmployeeId =
                        record.EmployeeId,

                    EmployeeName =
                        record.Employee?.Name
                        ?? record.EmployeeId,

                    Department =
                        record.Employee?.Department
                        ?? string.Empty,

                    Action =
                        record.Action,

                    Timestamp =
                        ConvertToSouthAfricaTime(
                            record.Timestamp,
                            southAfricaZone
                        )
                }
            )
            .ToList();


        // --------------------------------------------------
        // Build dashboard response
        // --------------------------------------------------

        return new DashboardResponse
        {
            GeneratedAt =
                generatedAt,

            TotalEmployees =
                employees.Count,

            ActiveEmployees =
                activeEmployees.Count,

            PresentNow =
                presentNow,

            ClockedOut =
                clockedOut,

            AbsentToday =
                absentToday,

            AttendanceEventsToday =
                attendanceToday.Count,

            AttendanceRate =
                attendanceRate,

            TotalHoursWorkedToday =
                totalHoursWorkedToday,

            AverageFirstArrival =
                averageFirstArrival,

            OpenSessionCount =
                openSessions.Count,


            // --------------------------------------------------
            // Shift-policy intelligence
            // --------------------------------------------------

            OnTimeToday =
                onTimeToday,

            LateToday =
                lateToday,

            MinimumAttendanceRate =
                _shiftPolicy.MinimumAttendanceRate,

            AttendanceBelowTarget =
                attendanceBelowTarget,

            LateArrivals = lateArrivals
                .OrderByDescending(
                    arrival =>
                        arrival.MinutesLate
                )
                .ToList(),


            // --------------------------------------------------
            // Dashboard collections
            // --------------------------------------------------

            OpenSessions = openSessions
                .OrderByDescending(
                    session =>
                        session.OpenHours
                )
                .ToList(),

            LatestActivity =
                latestActivity,

            Departments =
                departments,

            AttendanceTrend =
                trend
        };
    }


    // --------------------------------------------------
    // Convert UTC timestamp to South African time
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
    // Resolve South African timezone
    // --------------------------------------------------
    //
    // Windows uses:
    //
    // South Africa Standard Time
    //
    // Linux typically uses:
    //
    // Africa/Johannesburg
    //
    // Supporting both keeps the API portable between
    // Windows development and Linux hosting.
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