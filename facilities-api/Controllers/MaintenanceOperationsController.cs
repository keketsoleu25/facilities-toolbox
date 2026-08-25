using FacilitiesApi.Data;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace FacilitiesApi.Controllers;

// --------------------------------------------------
// MaintenanceOperationsController
// --------------------------------------------------
//
// Command-centre API for facilities maintenance execution.
//
// The AssetOperationsController handles intake:
// - assets
// - faults
// - inspections
// - initial work-order creation
//
// This controller handles what happens after intake:
// - backlog visibility
// - technician assignment
// - work-order state transitions
// - maintenance request state transitions
// - completion / MTTR-style intelligence
// - asset maintenance history
// --------------------------------------------------

[ApiController]
[Route("api/maintenance")]
public class MaintenanceOperationsController : ControllerBase
{
    private readonly FacilitiesDbContext _database;

    public MaintenanceOperationsController(FacilitiesDbContext database)
    {
        _database = database;
    }

    // --------------------------------------------------
    // GET /api/maintenance/overview
    // --------------------------------------------------
    //
    // Management-level maintenance KPIs.
    // --------------------------------------------------

    [HttpGet("overview")]
    public async Task<IActionResult> GetOverview()
    {
        var requests = await _database.MaintenanceRequests
            .AsNoTracking()
            .ToListAsync();

        var workOrders = await _database.WorkOrders
            .AsNoTracking()
            .ToListAsync();

        var openRequests = requests.Count(item =>
            item.Status != "RESOLVED" && item.Status != "CLOSED");

        var criticalOpen = requests.Count(item =>
            item.Priority == "CRITICAL" &&
            item.Status != "RESOLVED" &&
            item.Status != "CLOSED");

        var openWorkOrders = workOrders.Count(item =>
            item.Status != "COMPLETED" && item.Status != "CANCELLED");

        var unassignedWorkOrders = workOrders.Count(item =>
            item.Status != "COMPLETED" &&
            item.Status != "CANCELLED" &&
            string.IsNullOrWhiteSpace(item.AssignedEmployeeId));

        var inProgressWorkOrders = workOrders.Count(item =>
            item.Status == "IN_PROGRESS");

        var completedWorkOrders = workOrders
            .Where(item =>
                item.Status == "COMPLETED" &&
                item.CompletedAt.HasValue &&
                item.CompletedAt.Value >= item.CreatedAt)
            .ToList();

        // Mean time to complete is intentionally simple for v0.3:
        // created timestamp -> completed timestamp.
        // Later versions can separate response time, repair time,
        // waiting-on-parts time and SLA pauses.
        var averageCompletionHours = completedWorkOrders.Count == 0
            ? 0
            : Math.Round(
                completedWorkOrders.Average(item =>
                    (item.CompletedAt!.Value - item.CreatedAt).TotalHours),
                2
            );

        return Ok(new
        {
            openRequests,
            criticalOpen,
            openWorkOrders,
            unassignedWorkOrders,
            inProgressWorkOrders,
            completedWorkOrders = completedWorkOrders.Count,
            averageCompletionHours
        });
    }

    // --------------------------------------------------
    // GET /api/maintenance/requests
    // --------------------------------------------------
    //
    // Full maintenance backlog with asset + location context.
    // --------------------------------------------------

    [HttpGet("requests")]
    public async Task<IActionResult> GetRequests()
    {
        var requests = await _database.MaintenanceRequests
            .AsNoTracking()
            .Include(item => item.Asset)
                .ThenInclude(asset => asset.Site)
            .Include(item => item.Asset)
                .ThenInclude(asset => asset.Building)
            .Include(item => item.WorkOrders)
            .OrderByDescending(item => item.ReportedAt)
            .Select(item => new
            {
                item.Id,
                item.RequestCode,
                item.Title,
                item.Description,
                item.Priority,
                item.Status,
                item.ReportedAt,
                item.ReportedByEmployeeId,
                assetCode = item.Asset.AssetCode,
                assetName = item.Asset.Name,
                siteCode = item.Asset.Site.SiteCode,
                siteName = item.Asset.Site.Name,
                buildingCode = item.Asset.Building != null
                    ? item.Asset.Building.BuildingCode
                    : null,
                buildingName = item.Asset.Building != null
                    ? item.Asset.Building.Name
                    : null,
                workOrderCount = item.WorkOrders.Count,
                hasOpenWorkOrder = item.WorkOrders.Any(order =>
                    order.Status != "COMPLETED" &&
                    order.Status != "CANCELLED")
            })
            .ToListAsync();

        return Ok(requests);
    }

    // --------------------------------------------------
    // PATCH /api/maintenance/requests/{requestCode}/status
    // --------------------------------------------------

    [HttpPatch("requests/{requestCode}/status")]
    public async Task<IActionResult> UpdateRequestStatus(
        string requestCode,
        [FromBody] UpdateMaintenanceRequestStatusRequest request
    )
    {
        var normalizedCode = requestCode.Trim().ToUpperInvariant();
        var status = request.Status.Trim().ToUpperInvariant();

        if (status is not ("OPEN" or "TRIAGED" or "IN_PROGRESS" or "RESOLVED" or "CLOSED"))
        {
            return BadRequest(new
            {
                error = "Request status must be OPEN, TRIAGED, IN_PROGRESS, RESOLVED or CLOSED."
            });
        }

        var maintenanceRequest = await _database.MaintenanceRequests
            .FirstOrDefaultAsync(item => item.RequestCode == normalizedCode);

        if (maintenanceRequest is null)
        {
            return NotFound(new
            {
                error = $"Maintenance request {normalizedCode} was not found."
            });
        }

        maintenanceRequest.Status = status;
        await _database.SaveChangesAsync();

        return Ok(new
        {
            maintenanceRequest.RequestCode,
            maintenanceRequest.Status
        });
    }

    // --------------------------------------------------
    // GET /api/maintenance/work-orders
    // --------------------------------------------------
    //
    // Execution board for all work orders.
    // --------------------------------------------------

    [HttpGet("work-orders")]
    public async Task<IActionResult> GetWorkOrders()
    {
        var workOrders = await _database.WorkOrders
            .AsNoTracking()
            .Include(item => item.MaintenanceRequest)
                .ThenInclude(request => request.Asset)
                    .ThenInclude(asset => asset.Site)
            .Include(item => item.MaintenanceRequest)
                .ThenInclude(request => request.Asset)
                    .ThenInclude(asset => asset.Building)
            .Include(item => item.AssignedEmployee)
            .OrderByDescending(item => item.CreatedAt)
            .Select(item => new
            {
                item.Id,
                item.WorkOrderCode,
                item.Title,
                item.Status,
                item.CreatedAt,
                item.CompletedAt,
                item.AssignedEmployeeId,
                assignedEmployeeName = item.AssignedEmployee != null
                    ? item.AssignedEmployee.Name
                    : null,
                requestCode = item.MaintenanceRequest.RequestCode,
                priority = item.MaintenanceRequest.Priority,
                assetCode = item.MaintenanceRequest.Asset.AssetCode,
                assetName = item.MaintenanceRequest.Asset.Name,
                siteName = item.MaintenanceRequest.Asset.Site.Name,
                buildingName = item.MaintenanceRequest.Asset.Building != null
                    ? item.MaintenanceRequest.Asset.Building.Name
                    : null,
                ageHours = Math.Round(
                    (DateTime.UtcNow - item.CreatedAt).TotalHours,
                    1
                )
            })
            .ToListAsync();

        return Ok(workOrders);
    }

    // --------------------------------------------------
    // PATCH /api/maintenance/work-orders/{workOrderCode}/assign
    // --------------------------------------------------

    [HttpPatch("work-orders/{workOrderCode}/assign")]
    public async Task<IActionResult> AssignWorkOrder(
        string workOrderCode,
        [FromBody] AssignWorkOrderRequest request
    )
    {
        var normalizedCode = workOrderCode.Trim().ToUpperInvariant();
        var employeeId = request.EmployeeId.Trim();

        var workOrder = await _database.WorkOrders
            .FirstOrDefaultAsync(item => item.WorkOrderCode == normalizedCode);

        if (workOrder is null)
        {
            return NotFound(new
            {
                error = $"Work order {normalizedCode} was not found."
            });
        }

        var employee = await _database.Employees
            .FirstOrDefaultAsync(item => item.EmployeeId == employeeId);

        if (employee is null || !employee.Active)
        {
            return BadRequest(new
            {
                error = "Assigned employee must exist and be active."
            });
        }

        if (workOrder.Status is "COMPLETED" or "CANCELLED")
        {
            return Conflict(new
            {
                error = "Completed or cancelled work orders cannot be reassigned."
            });
        }

        workOrder.AssignedEmployeeId = employee.EmployeeId;

        if (workOrder.Status == "OPEN")
        {
            workOrder.Status = "ASSIGNED";
        }

        await _database.SaveChangesAsync();

        return Ok(new
        {
            workOrder.WorkOrderCode,
            workOrder.Status,
            workOrder.AssignedEmployeeId,
            assignedEmployeeName = employee.Name
        });
    }

    // --------------------------------------------------
    // PATCH /api/maintenance/work-orders/{workOrderCode}/status
    // --------------------------------------------------
    //
    // Legal flow is deliberately constrained so operational
    // history remains understandable.
    // --------------------------------------------------

    [HttpPatch("work-orders/{workOrderCode}/status")]
    public async Task<IActionResult> UpdateWorkOrderStatus(
        string workOrderCode,
        [FromBody] UpdateWorkOrderStatusRequest request
    )
    {
        var normalizedCode = workOrderCode.Trim().ToUpperInvariant();
        var targetStatus = request.Status.Trim().ToUpperInvariant();

        if (targetStatus is not ("OPEN" or "ASSIGNED" or "IN_PROGRESS" or "COMPLETED" or "CANCELLED"))
        {
            return BadRequest(new
            {
                error = "Work-order status must be OPEN, ASSIGNED, IN_PROGRESS, COMPLETED or CANCELLED."
            });
        }

        var workOrder = await _database.WorkOrders
            .Include(item => item.MaintenanceRequest)
            .FirstOrDefaultAsync(item => item.WorkOrderCode == normalizedCode);

        if (workOrder is null)
        {
            return NotFound(new
            {
                error = $"Work order {normalizedCode} was not found."
            });
        }

        if (targetStatus is "ASSIGNED" or "IN_PROGRESS" &&
            string.IsNullOrWhiteSpace(workOrder.AssignedEmployeeId))
        {
            return Conflict(new
            {
                error = "Assign an employee before moving this work order into execution."
            });
        }

        workOrder.Status = targetStatus;

        if (targetStatus == "COMPLETED")
        {
            workOrder.CompletedAt = DateTime.UtcNow;
            workOrder.MaintenanceRequest.Status = "RESOLVED";
        }
        else
        {
            workOrder.CompletedAt = null;

            if (targetStatus == "IN_PROGRESS")
            {
                workOrder.MaintenanceRequest.Status = "IN_PROGRESS";
            }
        }

        await _database.SaveChangesAsync();

        return Ok(new
        {
            workOrder.WorkOrderCode,
            workOrder.Status,
            workOrder.CompletedAt,
            requestCode = workOrder.MaintenanceRequest.RequestCode,
            requestStatus = workOrder.MaintenanceRequest.Status
        });
    }

    // --------------------------------------------------
    // GET /api/maintenance/assets/{assetCode}/history
    // --------------------------------------------------
    //
    // One asset's operational maintenance history.
    // --------------------------------------------------

    [HttpGet("assets/{assetCode}/history")]
    public async Task<IActionResult> GetAssetHistory(string assetCode)
    {
        var normalizedCode = assetCode.Trim().ToUpperInvariant();

        var asset = await _database.Assets
            .AsNoTracking()
            .Include(item => item.Site)
            .Include(item => item.Building)
            .FirstOrDefaultAsync(item => item.AssetCode == normalizedCode);

        if (asset is null)
        {
            return NotFound(new { error = $"Asset {normalizedCode} was not found." });
        }

        var requests = await _database.MaintenanceRequests
            .AsNoTracking()
            .Where(item => item.AssetId == asset.Id)
            .OrderByDescending(item => item.ReportedAt)
            .Select(item => new
            {
                type = "REQUEST",
                code = item.RequestCode,
                title = item.Title,
                status = item.Status,
                priority = item.Priority,
                timestamp = item.ReportedAt
            })
            .ToListAsync();

        var inspections = await _database.Inspections
            .AsNoTracking()
            .Where(item => item.AssetId == asset.Id)
            .OrderByDescending(item => item.InspectedAt)
            .Select(item => new
            {
                type = "INSPECTION",
                code = item.InspectionCode,
                title = item.Result,
                status = item.Result,
                priority = "",
                timestamp = item.InspectedAt
            })
            .ToListAsync();

        var timeline = requests
            .Concat(inspections)
            .OrderByDescending(item => item.timestamp)
            .ToList();

        return Ok(new
        {
            asset = new
            {
                asset.AssetCode,
                asset.Name,
                asset.Category,
                site = asset.Site.Name,
                building = asset.Building != null ? asset.Building.Name : null,
                asset.LocationNote
            },
            timeline
        });
    }
}

public class UpdateMaintenanceRequestStatusRequest
{
    public string Status { get; set; } = string.Empty;
}

public class AssignWorkOrderRequest
{
    public string EmployeeId { get; set; } = string.Empty;
}

public class UpdateWorkOrderStatusRequest
{
    public string Status { get; set; } = string.Empty;
}
