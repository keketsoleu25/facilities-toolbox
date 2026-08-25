namespace FacilitiesApi.Models;

// --------------------------------------------------
// Department
// --------------------------------------------------
//
// Represents an operational department inside a site or
// building. v0.3 keeps the relationship optional so the
// existing employee data can migrate safely.
// --------------------------------------------------

public class Department
{
    public int Id { get; set; }

    // Stable business code used by APIs and the portal.
    public string DepartmentCode { get; set; } = string.Empty;

    public string Name { get; set; } = string.Empty;

    // Optional physical placement.
    public int? SiteId { get; set; }
    public Site? Site { get; set; }

    public int? BuildingId { get; set; }
    public Building? Building { get; set; }

    public bool Active { get; set; } = true;

    public ICollection<Employee> Employees { get; set; } =
        new List<Employee>();
}
