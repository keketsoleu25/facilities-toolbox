using FacilitiesApi.Configuration;
using FacilitiesApi.Dtos;
using Microsoft.Extensions.Options;

namespace FacilitiesApi.Services;

// --------------------------------------------------
// OperationalAlertsService
// --------------------------------------------------
//
// Converts dashboard metrics into concise operator alerts.
// Thresholds are now read from ShiftPolicy configuration so
// facilities teams can tune behaviour without code changes.
// --------------------------------------------------

public class OperationalAlertsService
{
    private readonly DashboardService _dashboardService;
    private readonly ShiftPolicyOptions _shiftPolicy;

    public OperationalAlertsService(
        DashboardService dashboardService,
        IOptions<ShiftPolicyOptions> shiftPolicy
    )
    {
        _dashboardService = dashboardService;
        _shiftPolicy = shiftPolicy.Value;
    }

    public async Task<List<OperationalAlertResponse>> GetAlertsAsync()
    {
        var dashboard = await _dashboardService.GetSnapshotAsync();
        var alerts = new List<OperationalAlertResponse>();

        var missingCheckInTime = ParseTime(
            _shiftPolicy.MissingCheckInAlertTime,
            new TimeSpan(9, 0, 0)
        );

        if (
            dashboard.GeneratedAt.TimeOfDay >= missingCheckInTime &&
            dashboard.AbsentToday > 0
        )
        {
            alerts.Add(new OperationalAlertResponse
            {
                Severity = "WARNING",
                Code = "MISSING_CHECK_IN",
                Title = "Missing check-ins",
                Message = dashboard.AbsentToday == 1
                    ? "1 active employee has no attendance event today."
                    : $"{dashboard.AbsentToday} active employees have no attendance event today."
            });
        }

        if (dashboard.OpenSessionCount > 0)
        {
            alerts.Add(new OperationalAlertResponse
            {
                Severity = "INFO",
                Code = "OPEN_SESSIONS",
                Title = "Open work sessions",
                Message = dashboard.OpenSessionCount == 1
                    ? "1 employee is currently clocked in."
                    : $"{dashboard.OpenSessionCount} employees are currently clocked in."
            });
        }

        var longSessionHours = Math.Max(1, _shiftPolicy.LongSessionHours);

        var longSessions = dashboard.OpenSessions
            .Where(session => session.OpenHours >= longSessionHours)
            .ToList();

        if (longSessions.Count > 0)
        {
            alerts.Add(new OperationalAlertResponse
            {
                Severity = "WARNING",
                Code = "LONG_OPEN_SESSION",
                Title = "Long open session",
                Message = longSessions.Count == 1
                    ? $"1 employee has been clocked in for {longSessionHours:0.#} hours or more."
                    : $"{longSessions.Count} employees have been clocked in for {longSessionHours:0.#} hours or more."
            });
        }

        var minimumAttendanceRate = Math.Clamp(
            _shiftPolicy.MinimumAttendanceRate,
            0,
            100
        );

        if (
            dashboard.GeneratedAt.TimeOfDay >= missingCheckInTime &&
            dashboard.ActiveEmployees > 0 &&
            dashboard.AttendanceRate < minimumAttendanceRate
        )
        {
            alerts.Add(new OperationalAlertResponse
            {
                Severity = "INFO",
                Code = "LOW_ATTENDANCE_COVERAGE",
                Title = $"Attendance coverage below {minimumAttendanceRate:0.#}%",
                Message = $"Today's attendance coverage is {dashboard.AttendanceRate:0.0}%."
            });
        }

        return alerts;
    }

    private static TimeSpan ParseTime(string value, TimeSpan fallback)
    {
        return TimeSpan.TryParse(value, out var parsed)
            ? parsed
            : fallback;
    }
}
