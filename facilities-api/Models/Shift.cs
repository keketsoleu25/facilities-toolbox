namespace FacilitiesApi.Models;

// --------------------------------------------------
// Shift
// --------------------------------------------------
//
// Reusable work schedule. Shift rules move out of hard-
// coded attendance logic and become real domain data.
// --------------------------------------------------

public class Shift
{
    public int Id { get; set; }

    public string ShiftCode { get; set; } = string.Empty;

    public string Name { get; set; } = string.Empty;

    // Local facility time, for example 08:00 to 17:00.
    public TimeSpan StartTime { get; set; }

    public TimeSpan EndTime { get; set; }

    public int GraceMinutes { get; set; } = 15;

    public bool Active { get; set; } = true;

    public ICollection<ShiftAssignment> Assignments { get; set; } =
        new List<ShiftAssignment>();
}
