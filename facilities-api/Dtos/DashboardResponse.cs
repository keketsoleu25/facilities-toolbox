namespace FacilitiesApi.Dtos;


// --------------------------------------------------
// DashboardResponse
// --------------------------------------------------
//
// Represents the operational snapshot shown on the
// Facilities Toolbox dashboard.
//
// The API calculates these values so the PHP portal
// remains focused on presentation rather than business
// logic.
// --------------------------------------------------

public class DashboardResponse
{
    public DateTime GeneratedAt { get; set; }

    public int TotalEmployees { get; set; }

    public int ActiveEmployees { get; set; }

    public int PresentNow { get; set; }

    public int ClockedOut { get; set; }

    public int AttendanceEventsToday { get; set; }

    public double AttendanceRate { get; set; }

    public List<DashboardActivityItem> LatestActivity { get; set; } =
        new();
}


// --------------------------------------------------
// DashboardActivityItem
// --------------------------------------------------
//
// A lightweight attendance item used by the dashboard
// activity feed.
// --------------------------------------------------

public class DashboardActivityItem
{
    public int Id { get; set; }

    public string EmployeeId { get; set; } =
        string.Empty;

    public string EmployeeName { get; set; } =
        string.Empty;

    public string Department { get; set; } =
        string.Empty;

    public string Action { get; set; } =
        string.Empty;

    public DateTime Timestamp { get; set; }
}
