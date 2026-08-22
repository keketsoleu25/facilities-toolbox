namespace FacilitiesApi.Models;


// --------------------------------------------------
// AttendanceRecord
// --------------------------------------------------
//
// Represents one permanent attendance event.
//
// Examples:
//
// EMP001 -> IN
// EMP001 -> OUT
//
// EmployeeId now references a real Employee.
// --------------------------------------------------

public class AttendanceRecord
{
    // Database primary key.
    public int Id { get; set; }


    // --------------------------------------------------
    // Employee foreign key
    // --------------------------------------------------
    //
    // This value must match an Employee.EmployeeId.
    // --------------------------------------------------

    public string EmployeeId { get; set; } =
        string.Empty;


    // Attendance action.
    //
    // Currently supported:
    //
    // IN
    // OUT
    public string Action { get; set; } =
        string.Empty;


    // Store timestamps in UTC.
    public DateTime Timestamp { get; set; }


    // --------------------------------------------------
    // Employee navigation property
    // --------------------------------------------------
    //
    // EF Core uses this relationship to navigate
    // from an attendance record to its employee.
    // --------------------------------------------------

    public Employee Employee { get; set; } =
        null!;
}