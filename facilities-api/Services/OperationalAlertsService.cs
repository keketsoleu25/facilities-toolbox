using FacilitiesApi.Dtos;

namespace FacilitiesApi.Services;

// --------------------------------------------------
// OperationalAlertsService
// --------------------------------------------------
//
// Converts dashboard metrics into concise operator alerts.
// v0.2 keeps these rules intentionally simple and derived
// from live data. Persistent alert workflows can follow in
// a later version.
// --------------------------------------------------

public class OperationalAlertsService
{
    private readonly DashboardService _dashboardService;

    public OperationalAlertsService(
        DashboardService dashboardService
    )
    {
        _dashboardService = dashboardService;
    }

    public async Task<List<OperationalAlertResponse>> GetAlertsAsync()
    {
        var dashboard = await _dashboardService.GetSnapshotAsync();
        var alerts = new List<OperationalAlertResponse>();

        // --------------------------------------------------
        // Missing attendance warning
        // --------------------------------------------------
        //
        // After 09:00 local time, active employees with no
        // attendance event today deserve operator attention.
        // This is a warning, not a claim that the employee is
        // absent without reason.
        // --------------------------------------------------

        if (
            dashboard.GeneratedAt.TimeOfDay >= new TimeSpan(9, 0, 0) &&
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

        // --------------------------------------------------
        // Open session warning
        // --------------------------------------------------

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

        // Escalate unusually long open sessions.
        var longSessions = dashboard.OpenSessions
            .Where(session => session.OpenHours >= 10)
            .ToList();

        if (longSessions.Count > 0)
        {
            alerts.Add(new OperationalAlertResponse
            {
                Severity = "WARNING",
                Code = "LONG_OPEN_SESSION",
                Title = "Long open session",
                Message = longSessions.Count == 1
                    ? "1 employee has been clocked in for 10 hours or more."
                    : $"{longSessions.Count} employees have been clocked in for 10 hours or more."
            });
        }

        // --------------------------------------------------
        // Low coverage signal
        // --------------------------------------------------

        if (
            dashboard.GeneratedAt.TimeOfDay >= new TimeSpan(9, 0, 0) &&
            dashboard.ActiveEmployees > 0 &&
            dashboard.AttendanceRate < 80
        )
        {
            alerts.Add(new OperationalAlertResponse
            {
                Severity = "INFO",
                Code = "LOW_ATTENDANCE_COVERAGE",
                Title = "Attendance coverage below 80%",
                Message = $"Today's attendance coverage is {dashboard.AttendanceRate:0.0}%."
            });
        }

        return alerts;
    }
}
