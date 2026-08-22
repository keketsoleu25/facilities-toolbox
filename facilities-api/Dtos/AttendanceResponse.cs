namespace FacilitiesApi.Dtos;


// --------------------------------------------------
// AttendanceResponse
// --------------------------------------------------
//
// This object represents attendance data that is
// safe to return through our HTTP API.
//
// We deliberately do NOT return Entity Framework
// database entities directly.
//
// Why?
//
// AttendanceRecord has an Employee relationship.
// Employee has AttendanceRecords.
//
// Returning those entities directly can create:
//
// AttendanceRecord
//      -> Employee
//      -> AttendanceRecords
//      -> Employee
//      -> ...
//
// which creates a JSON reference cycle.
//
// A DTO gives the API a clean, predictable response.
// --------------------------------------------------

public class AttendanceResponse
{
    // Database ID of the attendance event.
    public int Id { get; set; }


    // Stable employee identifier.
    public string EmployeeId { get; set; } =
        string.Empty;


    // Attendance state:
    //
    // IN
    // OUT
    public string Action { get; set; } =
        string.Empty;


    // UTC time when the attendance event occurred.
    public DateTime Timestamp { get; set; }
}