namespace FacilitiesApi.Models;

// --------------------------------------------------
// MaintenanceRequest
// --------------------------------------------------
//
// Captures a reported fault, defect or maintenance need.
// It is intentionally separate from WorkOrder so operators
// can record a problem before deciding how it will be done.
// --------------------------------------------------

public class MaintenanceRequest
{
    public int Id { get; set; }

    // Stable operator-facing reference.
    // Example: MRQ-2026-0001
    public string RequestCode { get; set; } = string.Empty;

    public string Title { get; set; } = string.Empty;
    public string Description { get; set; } = string.Empty;

    // LOW, MEDIUM, HIGH or CRITICAL for v0.3.
    public string Priority { get; set; } = "MEDIUM";

    // OPEN, TRIAGED, IN_PROGRESS, RESOLVED or CLOSED.
    public string Status { get; set; } = "OPEN";

    public DateTime ReportedAt { get; set; } = DateTime.UtcNow;

    // Optional employee who reported the issue.
    public string? ReportedByEmployeeId { get; set; }
    public Employee? ReportedByEmployee { get; set; }

    // Every request is attached to one managed asset.
    public int AssetId { get; set; }
    public Asset Asset { get; set; } = null!;

    // A request can later create one or more work orders.
    public ICollection<WorkOrder> WorkOrders { get; set; } =
        new List<WorkOrder>();
}
