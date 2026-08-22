namespace FacilitiesApi.Models;


// --------------------------------------------------
// Employee
// --------------------------------------------------
//
// Represents one employee registered in the
// Facilities Toolbox.
//
// Attendance records will reference employees
// instead of accepting arbitrary employee IDs.
// --------------------------------------------------

public class Employee
{
    // --------------------------------------------------
    // Internal database primary key
    // --------------------------------------------------
    //
    // PostgreSQL generates this automatically.
    //
    // This is different from EmployeeId.
    // --------------------------------------------------

    public int Id { get; set; }


    // --------------------------------------------------
    // Business employee identifier
    // --------------------------------------------------
    //
    // This is the identifier used by:
    //
    // - Python facial recognition
    // - C# attendance API
    // - PHP facilities portal
    //
    // Example:
    //
    // EMP001
    // --------------------------------------------------

    public string EmployeeId { get; set; } =
        string.Empty;


    // Employee's display name.
    public string Name { get; set; } =
        string.Empty;


    // Department within the organisation.
    public string Department { get; set; } =
        string.Empty;


    // Employee's job role.
    public string Role { get; set; } =
        string.Empty;


    // --------------------------------------------------
    // Employee status
    // --------------------------------------------------
    //
    // Inactive employees remain in the database
    // for historical/audit purposes but cannot
    // submit new attendance events.
    // --------------------------------------------------

    public bool Active { get; set; } = true;


    // --------------------------------------------------
    // Navigation property
    // --------------------------------------------------
    //
    // Gives EF Core access to all attendance
    // records belonging to this employee.
    // --------------------------------------------------

    public ICollection<AttendanceRecord>
        AttendanceRecords { get; set; } =
            new List<AttendanceRecord>();
}