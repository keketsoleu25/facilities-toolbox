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

    public int AbsentToday { get; set; }

    public int AttendanceEventsToday { get; set; }

    public double AttendanceRate { get; set; }

    public double TotalHoursWorkedToday { get; set; }

    public string AverageFirstArrival { get; set; } =
        "--";

    public List<DashboardActivityItem> LatestActivity { get; set; } =
        new();

    public List<DashboardDepartmentItem> Departments { get; set; } =
        new();

    public List<DashboardTrendItem> AttendanceTrend { get; set; } =
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


// --------------------------------------------------
// DashboardDepartmentItem
// --------------------------------------------------
//
// Department-level attendance health for the current
// South African business day.
// --------------------------------------------------

public class DashboardDepartmentItem
{
    public string Department { get; set; } =
        string.Empty;

    public int ActiveEmployees { get; set; }

    public int SeenToday { get; set; }

    public int PresentNow { get; set; }

    public double AttendanceRate { get; set; }
}


// --------------------------------------------------
// DashboardTrendItem
// --------------------------------------------------
//
// Seven-day attendance coverage history used by the
// command centre trend strip.
// --------------------------------------------------

public class DashboardTrendItem
{
    public DateTime Date { get; set; }

    public int SeenEmployees { get; set; }

    public double AttendanceRate { get; set; }
}
