namespace FacilitiesApi.Dtos;

// --------------------------------------------------
// OperationalAlertResponse
// --------------------------------------------------
//
// Small read-only alert item used by the command centre.
// Alerts are derived from current attendance intelligence;
// they are not persisted yet in v0.2.
// --------------------------------------------------

public class OperationalAlertResponse
{
    public string Severity { get; set; } = "INFO";
    public string Code { get; set; } = string.Empty;
    public string Title { get; set; } = string.Empty;
    public string Message { get; set; } = string.Empty;
}
