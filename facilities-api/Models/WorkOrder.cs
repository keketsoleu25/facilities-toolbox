namespace FacilitiesApi.Models;

// --------------------------------------------------
// WorkOrder
// --------------------------------------------------
//
// Represents approved maintenance work created from a
// reported maintenance request. This separates the problem
// report from the actual execution record.
// --------------------------------------------------

public class WorkOrder
{
    public int Id { get; set; }

    // Stable operator-facing reference.
    // Example: WO-2026-0001
    public string WorkOrderCode { get; set; } = string.Empty;

    public string Title { get; set; } = string.Empty;

    // OPEN, ASSIGNED, IN_PROGRESS, COMPLETED or CANCELLED.
    public string Status { get; set; } = "OPEN";

    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime? CompletedAt { get; set; }

    // Optional employee responsible for the work.
    public string? AssignedEmployeeId { get; set; }
    public Employee? AssignedEmployee { get; set; }

    // Source maintenance request.
    public int MaintenanceRequestId { get; set; }
    public MaintenanceRequest MaintenanceRequest { get; set; } = null!;
}
