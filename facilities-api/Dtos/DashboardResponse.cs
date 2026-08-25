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

    // --------------------------------------------------
    // Shift-policy intelligence
    // --------------------------------------------------
    //
    // These values are calculated from the configurable
    // shift rules in ShiftPolicyOptions.
    // --------------------------------------------------

    public int OnTimeToday { get; set; }

    public int LateToday { get; set; }

    public double MinimumAttendanceRate { get; set; }

    public bool AttendanceBelowTarget { get; set; }

    // --------------------------------------------------
    // Dashboard collections
    // --------------------------------------------------

    public List<DashboardLateArrivalItem> LateArrivals { get; set; } =
        new();

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
// DashboardLateArrivalItem
// --------------------------------------------------
//
// Describes an employee whose first IN event occurred
// after the configured shift start plus grace period.
// --------------------------------------------------

public class DashboardLateArrivalItem
{
    public string EmployeeId { get; set; } = string.Empty;

    public string EmployeeName { get; set; } = string.Empty;

    public string Department { get; set; } = string.Empty;

    public DateTime ArrivedAt { get; set; }

    public int MinutesLate { get; set; }
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


// --------------------------------------------------
// DashboardActivityItem
// --------------------------------------------------
//
// Represents one recent attendance event.
// --------------------------------------------------

public class DashboardActivityItem
{
    public int Id { get; set; }

    public string EmployeeId { get; set; } = string.Empty;

    public string EmployeeName { get; set; } = string.Empty;

    public string Department { get; set; } = string.Empty;

    public string Action { get; set; } = string.Empty;

    public DateTime Timestamp { get; set; }
}


// --------------------------------------------------
// DashboardDepartmentItem
// --------------------------------------------------
//
// Represents attendance health for one department.
// --------------------------------------------------

public class DashboardDepartmentItem
{
    public string Department { get; set; } = string.Empty;

    public int ActiveEmployees { get; set; }

    public int SeenToday { get; set; }

    public int PresentNow { get; set; }

    public double AttendanceRate { get; set; }
}


// --------------------------------------------------
// DashboardTrendItem
// --------------------------------------------------
//
// Represents one day in the seven-day attendance trend.
// --------------------------------------------------

public class DashboardTrendItem
{
    public DateTime Date { get; set; }

    public int SeenEmployees { get; set; }

    public double AttendanceRate { get; set; }
}