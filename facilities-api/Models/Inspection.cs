namespace FacilitiesApi.Models;

// --------------------------------------------------
// Inspection
// --------------------------------------------------
//
// Records an operational inspection of one managed asset.
// Inspections are deliberately simple in v0.3 and can later
// evolve into checklist templates and compliance schedules.
// --------------------------------------------------

public class Inspection
{
    public int Id { get; set; }

    // Stable reference shown in the operations portal.
    // Example: INS-2026-0001
    public string InspectionCode { get; set; } = string.Empty;

    public DateTime InspectedAt { get; set; } = DateTime.UtcNow;

    // PASS, ATTENTION or FAIL for the first implementation.
    public string Result { get; set; } = "PASS";

    public string Notes { get; set; } = string.Empty;

    // Optional employee who performed the inspection.
    public string? InspectorEmployeeId { get; set; }
    public Employee? InspectorEmployee { get; set; }

    public int AssetId { get; set; }
    public Asset Asset { get; set; } = null!;
}
