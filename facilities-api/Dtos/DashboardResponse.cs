namespace FacilitiesApi.Dtos;

// --------------------------------------------------
// DashboardResponse
// --------------------------------------------------
//
// Represents the operational snapshot shown on the
// Facilities Toolbox dashboard.
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
    public string AverageFirstArrival { get; set; } = "--";
    public int OpenSessionCount { get; set; }

    public List<DashboardOpenSessionItem> OpenSessions { get; set; } =
        new();

    public List<DashboardActivityItem> LatestActivity { get; set; } =
        new();

    public List<DashboardDepartmentItem> Departments { get; set; } =
        new();

    public List<DashboardTrendItem> AttendanceTrend { get; set; } =
        new();
}

// --------------------------------------------------
// DashboardOpenSessionItem
// --------------------------------------------------
//
// Describes an employee whose latest event today is IN.
// This gives operators a safe "still clocked in" view.
// --------------------------------------------------

public class DashboardOpenSessionItem
{
    public string EmployeeId { get; set; } = string.Empty;
    public string EmployeeName { get; set; } = string.Empty;
    public string Department { get; set; } = string.Empty;
    public DateTime ClockedInAt { get; set; }
    public double OpenHours { get; set; }
}

public class DashboardActivityItem
{
    public int Id { get; set; }
    public string EmployeeId { get; set; } = string.Empty;
    public string EmployeeName { get; set; } = string.Empty;
    public string Department { get; set; } = string.Empty;
    public string Action { get; set; } = string.Empty;
    public DateTime Timestamp { get; set; }
}

public class DashboardDepartmentItem
{
    public string Department { get; set; } = string.Empty;
    public int ActiveEmployees { get; set; }
    public int SeenToday { get; set; }
    public int PresentNow { get; set; }
    public double AttendanceRate { get; set; }
}

public class DashboardTrendItem
{
    public DateTime Date { get; set; }
    public int SeenEmployees { get; set; }
    public double AttendanceRate { get; set; }
}
