namespace FacilitiesApi.Models;

// --------------------------------------------------
// Employee
// --------------------------------------------------
//
// Represents one employee registered in Facilities Toolbox.
//
// v0.3 begins linking employees to real operational
// structure while keeping the legacy Department string for
// compatibility with existing portal and attendance code.
// --------------------------------------------------

public class Employee
{
    public int Id { get; set; }

    // Business employee identifier shared by Python, C# and PHP.
    public string EmployeeId { get; set; } = string.Empty;

    public string Name { get; set; } = string.Empty;

    // --------------------------------------------------
    // Legacy department label
    // --------------------------------------------------
    //
    // Kept temporarily so v0.2 screens and historical data do
    // not break while v0.3 transitions to DepartmentId.
    // --------------------------------------------------

    public string Department { get; set; } = string.Empty;

    // Optional structured department relationship.
    public int? DepartmentId { get; set; }
    public Department? DepartmentRecord { get; set; }

    public string Role { get; set; } = string.Empty;

    // Inactive employees remain for historical/audit purposes.
    public bool Active { get; set; } = true;

    public ICollection<AttendanceRecord> AttendanceRecords { get; set; } =
        new List<AttendanceRecord>();

    // Historical shift assignments let the system understand
    // which schedule applied to an employee on a given date.
    public ICollection<ShiftAssignment> ShiftAssignments { get; set; } =
        new List<ShiftAssignment>();
}
