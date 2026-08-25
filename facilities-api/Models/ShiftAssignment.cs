namespace FacilitiesApi.Models;

// --------------------------------------------------
// ShiftAssignment
// --------------------------------------------------
//
// Connects one employee to one shift for a defined period.
// Keeping assignments separate preserves history when an
// employee later changes shift.
// --------------------------------------------------

public class ShiftAssignment
{
    public int Id { get; set; }

    // EmployeeId uses the same business identifier used by
    // attendance and recognition (for example EMP001).
    public string EmployeeId { get; set; } = string.Empty;
    public Employee Employee { get; set; } = null!;

    public int ShiftId { get; set; }
    public Shift Shift { get; set; } = null!;

    public DateOnly EffectiveFrom { get; set; }

    public DateOnly? EffectiveTo { get; set; }

    public bool Active { get; set; } = true;
}
