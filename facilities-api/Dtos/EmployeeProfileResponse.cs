namespace FacilitiesApi.Dtos;

// --------------------------------------------------
// EmployeeProfileResponse
// --------------------------------------------------
//
// Read-only operational profile used by the management
// portal. It combines employee identity with today's
// attendance state, shift punctuality and recent history.
// --------------------------------------------------

public class EmployeeProfileResponse
{
    public string EmployeeId { get; set; } = string.Empty;
    public string Name { get; set; } = string.Empty;
    public string Department { get; set; } = string.Empty;
    public string Role { get; set; } = string.Empty;
    public bool Active { get; set; }

    public string CurrentStatus { get; set; } = "NOT_SEEN";
    public string FirstArrivalToday { get; set; } = "--";
    public string LastEventToday { get; set; } = "--";
    public double CompletedHoursToday { get; set; }
    public bool HasOpenSession { get; set; }
    public double OpenSessionHours { get; set; }

    // --------------------------------------------------
    // v0.2 shift intelligence
    // --------------------------------------------------
    //
    // These defaults model a standard 08:00 start with a
    // 15-minute grace period. They are intentionally kept
    // explicit for now and can later move into site/shift
    // configuration tables.
    // --------------------------------------------------

    public string ShiftStart { get; set; } = "08:00";
    public int GraceMinutes { get; set; } = 15;
    public string PunctualityStatus { get; set; } = "NOT_SEEN";
    public int MinutesLate { get; set; }

    public List<EmployeeProfileAttendanceItem> RecentAttendance { get; set; } =
        new();
}

public class EmployeeProfileAttendanceItem
{
    public int Id { get; set; }
    public string Action { get; set; } = string.Empty;
    public DateTime Timestamp { get; set; }
}
