using FacilitiesApi.Services;
using Microsoft.AspNetCore.Mvc;

namespace FacilitiesApi.Controllers;

// --------------------------------------------------
// OperationalAlertsController
// --------------------------------------------------
//
// Read-only command-centre alert endpoint.
// Route:
// GET /api/operational-alerts
// --------------------------------------------------

[ApiController]
[Route("api/operational-alerts")]
public class OperationalAlertsController : ControllerBase
{
    private readonly OperationalAlertsService _alertsService;

    public OperationalAlertsController(
        OperationalAlertsService alertsService
    )
    {
        _alertsService = alertsService;
    }

    [HttpGet]
    public async Task<IActionResult> GetAlerts()
    {
        var alerts = await _alertsService.GetAlertsAsync();
        return Ok(alerts);
    }
}
