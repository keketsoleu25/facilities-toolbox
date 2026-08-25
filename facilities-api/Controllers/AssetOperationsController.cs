using FacilitiesApi.Data;
using FacilitiesApi.Models;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace FacilitiesApi.Controllers;

// --------------------------------------------------
// AssetOperationsController
// --------------------------------------------------
//
// First true facilities-maintenance API surface.
//
// Routes:
// GET  /api/assets
// GET  /api/assets/overview
// POST /api/assets
// POST /api/assets/{assetCode}/maintenance-requests
// POST /api/maintenance-requests/{requestCode}/work-orders
// POST /api/assets/{assetCode}/inspections
// --------------------------------------------------

[ApiController]
[Route("api")]
public class AssetOperationsController : ControllerBase
{
    private readonly FacilitiesDbContext _database;

    public AssetOperationsController(FacilitiesDbContext database)
    {
        _database = database;
    }

    // --------------------------------------------------
    // GET /api/assets
    // --------------------------------------------------

    [HttpGet("assets")]
    public async Task<IActionResult> GetAssets()
    {
        var assets = await _database.Assets
            .AsNoTracking()
            .Include(asset => asset.Site)
            .Include(asset => asset.Building)
            .OrderBy(asset => asset.AssetCode)
            .Select(asset => new
            {
                asset.Id,
                asset.AssetCode,
                asset.Name,
                asset.Category,
                asset.SerialNumber,
                asset.LocationNote,
                asset.Active,
                asset.SiteId,
                siteCode = asset.Site.SiteCode,
                siteName = asset.Site.Name,
                asset.BuildingId,
                buildingCode = asset.Building != null
                    ? asset.Building.BuildingCode
                    : null,
                buildingName = asset.Building != null
                    ? asset.Building.Name
                    : null,
                openRequests = asset.MaintenanceRequests.Count(request =>
                    request.Status != "RESOLVED" &&
                    request.Status != "CLOSED")
            })
            .ToListAsync();

        return Ok(assets);
    }

    // --------------------------------------------------
    // GET /api/assets/overview
    // --------------------------------------------------
    //
    // Small command-centre snapshot for the PHP portal.
    // --------------------------------------------------

    [HttpGet("assets/overview")]
    public async Task<IActionResult> GetOverview()
    {
        var totalAssets = await _database.Assets.CountAsync();
        var activeAssets = await _database.Assets.CountAsync(item => item.Active);

        var openRequests = await _database.MaintenanceRequests.CountAsync(
            item => item.Status != "RESOLVED" && item.Status != "CLOSED"
        );

        var criticalRequests = await _database.MaintenanceRequests.CountAsync(
            item =>
                item.Priority == "CRITICAL" &&
                item.Status != "RESOLVED" &&
                item.Status != "CLOSED"
        );

        var openWorkOrders = await _database.WorkOrders.CountAsync(
            item => item.Status != "COMPLETED" && item.Status != "CANCELLED"
        );

        var failedInspections = await _database.Inspections.CountAsync(
            item => item.Result == "FAIL"
        );

        return Ok(new
        {
            totalAssets,
            activeAssets,
            openRequests,
            criticalRequests,
            openWorkOrders,
            failedInspections
        });
    }

    // --------------------------------------------------
    // POST /api/assets
    // --------------------------------------------------

    [HttpPost("assets")]
    public async Task<IActionResult> CreateAsset(
        [FromBody] CreateAssetRequest request
    )
    {
        var assetCode = request.AssetCode.Trim().ToUpperInvariant();
        var name = request.Name.Trim();
        var siteCode = request.SiteCode.Trim().ToUpperInvariant();
        var buildingCode = request.BuildingCode.Trim().ToUpperInvariant();

        if (string.IsNullOrWhiteSpace(assetCode) ||
            string.IsNullOrWhiteSpace(name) ||
            string.IsNullOrWhiteSpace(siteCode))
        {
            return BadRequest(new
            {
                error = "Asset code, name and site are required."
            });
        }

        if (await _database.Assets.AnyAsync(item => item.AssetCode == assetCode))
        {
            return Conflict(new
            {
                error = $"Asset {assetCode} already exists."
            });
        }

        var site = await _database.Sites
            .FirstOrDefaultAsync(item => item.SiteCode == siteCode);

        if (site is null)
        {
            return BadRequest(new
            {
                error = $"Site {siteCode} does not exist."
            });
        }

        Building? building = null;

        if (!string.IsNullOrWhiteSpace(buildingCode))
        {
            building = await _database.Buildings
                .FirstOrDefaultAsync(item => item.BuildingCode == buildingCode);

            if (building is null)
            {
                return BadRequest(new
                {
                    error = $"Building {buildingCode} does not exist."
                });
            }

            if (building.SiteId != site.Id)
            {
                return BadRequest(new
                {
                    error = "Selected building does not belong to the selected site."
                });
            }
        }

        var asset = new Asset
        {
            AssetCode = assetCode,
            Name = name,
            Category = request.Category.Trim(),
            SerialNumber = request.SerialNumber.Trim(),
            LocationNote = request.LocationNote.Trim(),
            SiteId = site.Id,
            BuildingId = building?.Id,
            Active = true
        };

        _database.Assets.Add(asset);
        await _database.SaveChangesAsync();

        return Ok(new
        {
            asset.Id,
            asset.AssetCode,
            asset.Name,
            site = site.Name,
            building = building?.Name,
            asset.Active
        });
    }

    // --------------------------------------------------
    // POST /api/assets/{assetCode}/maintenance-requests
    // --------------------------------------------------

    [HttpPost("assets/{assetCode}/maintenance-requests")]
    public async Task<IActionResult> CreateMaintenanceRequest(
        string assetCode,
        [FromBody] CreateMaintenanceRequestRequest request
    )
    {
        var normalizedAssetCode = assetCode.Trim().ToUpperInvariant();

        var asset = await _database.Assets
            .FirstOrDefaultAsync(item => item.AssetCode == normalizedAssetCode);

        if (asset is null)
        {
            return NotFound(new
            {
                error = $"Asset {normalizedAssetCode} was not found."
            });
        }

        var title = request.Title.Trim();

        if (string.IsNullOrWhiteSpace(title))
        {
            return BadRequest(new { error = "Request title is required." });
        }

        var priority = NormalisePriority(request.Priority);

        if (priority is null)
        {
            return BadRequest(new
            {
                error = "Priority must be LOW, MEDIUM, HIGH or CRITICAL."
            });
        }

        var requestCode = $"MRQ-{DateTime.UtcNow:yyyyMMddHHmmssfff}";

        var maintenanceRequest = new MaintenanceRequest
        {
            RequestCode = requestCode,
            Title = title,
            Description = request.Description.Trim(),
            Priority = priority,
            Status = "OPEN",
            ReportedAt = DateTime.UtcNow,
            ReportedByEmployeeId = NullIfBlank(request.ReportedByEmployeeId),
            AssetId = asset.Id
        };

        _database.MaintenanceRequests.Add(maintenanceRequest);
        await _database.SaveChangesAsync();

        return Ok(new
        {
            maintenanceRequest.RequestCode,
            maintenanceRequest.Title,
            maintenanceRequest.Priority,
            maintenanceRequest.Status,
            asset.AssetCode
        });
    }

    // --------------------------------------------------
    // POST /api/maintenance-requests/{requestCode}/work-orders
    // --------------------------------------------------

    [HttpPost("maintenance-requests/{requestCode}/work-orders")]
    public async Task<IActionResult> CreateWorkOrder(
        string requestCode,
        [FromBody] CreateWorkOrderRequest request
    )
    {
        var normalizedRequestCode = requestCode.Trim().ToUpperInvariant();

        var maintenanceRequest = await _database.MaintenanceRequests
            .FirstOrDefaultAsync(item => item.RequestCode == normalizedRequestCode);

        if (maintenanceRequest is null)
        {
            return NotFound(new
            {
                error = $"Maintenance request {normalizedRequestCode} was not found."
            });
        }

        var title = request.Title.Trim();

        if (string.IsNullOrWhiteSpace(title))
        {
            return BadRequest(new { error = "Work order title is required." });
        }

        var workOrderCode = $"WO-{DateTime.UtcNow:yyyyMMddHHmmssfff}";

        var workOrder = new WorkOrder
        {
            WorkOrderCode = workOrderCode,
            Title = title,
            Status = string.IsNullOrWhiteSpace(request.AssignedEmployeeId)
                ? "OPEN"
                : "ASSIGNED",
            CreatedAt = DateTime.UtcNow,
            AssignedEmployeeId = NullIfBlank(request.AssignedEmployeeId),
            MaintenanceRequestId = maintenanceRequest.Id
        };

        maintenanceRequest.Status = "IN_PROGRESS";

        _database.WorkOrders.Add(workOrder);
        await _database.SaveChangesAsync();

        return Ok(new
        {
            workOrder.WorkOrderCode,
            workOrder.Title,
            workOrder.Status,
            workOrder.AssignedEmployeeId,
            maintenanceRequest.RequestCode
        });
    }

    // --------------------------------------------------
    // POST /api/assets/{assetCode}/inspections
    // --------------------------------------------------

    [HttpPost("assets/{assetCode}/inspections")]
    public async Task<IActionResult> CreateInspection(
        string assetCode,
        [FromBody] CreateInspectionRequest request
    )
    {
        var normalizedAssetCode = assetCode.Trim().ToUpperInvariant();

        var asset = await _database.Assets
            .FirstOrDefaultAsync(item => item.AssetCode == normalizedAssetCode);

        if (asset is null)
        {
            return NotFound(new
            {
                error = $"Asset {normalizedAssetCode} was not found."
            });
        }

        var result = request.Result.Trim().ToUpperInvariant();

        if (result != "PASS" && result != "ATTENTION" && result != "FAIL")
        {
            return BadRequest(new
            {
                error = "Inspection result must be PASS, ATTENTION or FAIL."
            });
        }

        var inspection = new Inspection
        {
            InspectionCode = $"INS-{DateTime.UtcNow:yyyyMMddHHmmssfff}",
            InspectedAt = DateTime.UtcNow,
            Result = result,
            Notes = request.Notes.Trim(),
            InspectorEmployeeId = NullIfBlank(request.InspectorEmployeeId),
            AssetId = asset.Id
        };

        _database.Inspections.Add(inspection);
        await _database.SaveChangesAsync();

        return Ok(new
        {
            inspection.InspectionCode,
            inspection.Result,
            asset.AssetCode,
            inspection.InspectedAt
        });
    }

    private static string? NormalisePriority(string value)
    {
        var priority = value.Trim().ToUpperInvariant();

        return priority is "LOW" or "MEDIUM" or "HIGH" or "CRITICAL"
            ? priority
            : null;
    }

    private static string? NullIfBlank(string? value)
    {
        var normalised = value?.Trim();
        return string.IsNullOrWhiteSpace(normalised) ? null : normalised;
    }
}

// --------------------------------------------------
// Request DTOs kept local to the controller for v0.3.
// They can be split into Dtos/ when this module expands.
// --------------------------------------------------

public class CreateAssetRequest
{
    public string AssetCode { get; set; } = string.Empty;
    public string Name { get; set; } = string.Empty;
    public string Category { get; set; } = string.Empty;
    public string SerialNumber { get; set; } = string.Empty;
    public string LocationNote { get; set; } = string.Empty;
    public string SiteCode { get; set; } = string.Empty;
    public string BuildingCode { get; set; } = string.Empty;
}

public class CreateMaintenanceRequestRequest
{
    public string Title { get; set; } = string.Empty;
    public string Description { get; set; } = string.Empty;
    public string Priority { get; set; } = "MEDIUM";
    public string? ReportedByEmployeeId { get; set; }
}

public class CreateWorkOrderRequest
{
    public string Title { get; set; } = string.Empty;
    public string? AssignedEmployeeId { get; set; }
}

public class CreateInspectionRequest
{
    public string Result { get; set; } = "PASS";
    public string Notes { get; set; } = string.Empty;
    public string? InspectorEmployeeId { get; set; }
}
