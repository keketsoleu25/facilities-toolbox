namespace FacilitiesApi.Models;

// --------------------------------------------------
// Building
// --------------------------------------------------
//
// Represents one building that belongs to a Site.
// This is the first step toward modelling real physical
// facilities instead of treating attendance as isolated
// employee data.
// --------------------------------------------------

public class Building
{
    // Database primary key.
    public int Id { get; set; }

    // Stable business identifier used by the API and UI.
    // Example: BLD001
    public string BuildingCode { get; set; } = string.Empty;

    // Human-readable building name.
    public string Name { get; set; } = string.Empty;

    // Optional description such as purpose or location
    // inside the wider site.
    public string Description { get; set; } = string.Empty;

    // Buildings can be deactivated without deleting
    // historical data.
    public bool Active { get; set; } = true;

    // Foreign key to the parent Site.
    public int SiteId { get; set; }

    // EF Core navigation property to the parent Site.
    public Site Site { get; set; } = null!;
}
