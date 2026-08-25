using FacilitiesApi.Services;
using Microsoft.AspNetCore.Mvc;

namespace FacilitiesApi.Controllers;


// --------------------------------------------------
// DashboardController
// --------------------------------------------------
//
// Provides the live operational snapshot used by the
// Facilities Toolbox management dashboard.
// --------------------------------------------------

[ApiController]
[Route("api/[controller]")]
public class DashboardController : ControllerBase
{
    private readonly DashboardService _dashboardService;

    public DashboardController(
        DashboardService dashboardService
    )
    {
        _dashboardService = dashboardService;
    }


    // --------------------------------------------------
    // GET /api/dashboard
    // --------------------------------------------------

    [HttpGet]
    public async Task<IActionResult> Get()
    {
        var dashboard =
            await _dashboardService.GetSnapshotAsync();

        return Ok(dashboard);
    }
}
