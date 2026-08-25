namespace FacilitiesApi.Configuration;

// --------------------------------------------------
// ShiftPolicyOptions
// --------------------------------------------------
//
// Central configurable attendance policy for v0.2.
//
// These values come from appsettings.json so shift
// behaviour is no longer hard-coded inside controllers.
// A later version can move rules into PostgreSQL per
// site, department or employee.
// --------------------------------------------------

public class ShiftPolicyOptions
{
    public const string SectionName = "ShiftPolicy";

    // Local South African start time in HH:mm format.
    public string StartTime { get; set; } = "08:00";

    // Employees arriving within this grace window are
    // treated as on time.
    public int GraceMinutes { get; set; } = 15;

    // Missing check-in alerts begin after this local time.
    public string MissingCheckInAlertTime { get; set; } = "09:00";

    // Open sessions beyond this duration are highlighted.
    public double LongSessionHours { get; set; } = 10;

    // Command-centre health threshold.
    public double MinimumAttendanceRate { get; set; } = 80;
}
