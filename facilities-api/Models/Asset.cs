namespace FacilitiesApi.Models;

// --------------------------------------------------
// Asset
// --------------------------------------------------
//
// Represents one physical asset managed by Facilities
// Toolbox. Assets belong to a site and may optionally be
// narrowed to a specific building.
//
// Examples:
// - HVAC unit
// - generator
// - pump
// - fire extinguisher
// - access-control reader
// - vehicle
// --------------------------------------------------

public class Asset
{
    // Database primary key.
    public int Id { get; set; }

    // Stable business code used by operators and APIs.
    // Example: AST001
    public string AssetCode { get; set; } = string.Empty;

    // Human-readable asset name.
    public string Name { get; set; } = string.Empty;

    // Broad operational category such as HVAC or Electrical.
    public string Category { get; set; } = string.Empty;

    // Optional manufacturer / serial information.
    public string SerialNumber { get; set; } = string.Empty;

    // Short description of where the asset is physically found.
    public string LocationNote { get; set; } = string.Empty;

    // Assets can be retired without deleting maintenance history.
    public bool Active { get; set; } = true;

    // Every managed asset belongs to one site.
    public int SiteId { get; set; }
    public Site Site { get; set; } = null!;

    // Building is optional for outdoor / site-wide assets.
    public int? BuildingId { get; set; }
    public Building? Building { get; set; }

    // Operational history attached to the asset.
    public ICollection<MaintenanceRequest> MaintenanceRequests { get; set; } =
        new List<MaintenanceRequest>();

    public ICollection<Inspection> Inspections { get; set; } =
        new List<Inspection>();
}
