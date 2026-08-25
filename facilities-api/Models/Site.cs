namespace FacilitiesApi.Models;

// --------------------------------------------------
// Site
// --------------------------------------------------
//
// Represents one physical facilities location managed
// by Facilities Toolbox.
//
// Examples:
// - Head Office
// - Warehouse
// - School Campus
// - Residential Complex
//
// A site can contain multiple buildings.
// --------------------------------------------------

public class Site
{
    // Database primary key.
    public int Id { get; set; }

    // Stable business identifier used by the API and UI.
    // Example: SITE001
    public string SiteCode { get; set; } = string.Empty;

    // Human-readable site name.
    public string Name { get; set; } = string.Empty;

    // Optional address or location description.
    public string Address { get; set; } = string.Empty;

    // Allows a site to be retired without deleting
    // historical operational records.
    public bool Active { get; set; } = true;

    // Navigation collection for buildings at this site.
    public ICollection<Building> Buildings { get; set; } =
        new List<Building>();
}
