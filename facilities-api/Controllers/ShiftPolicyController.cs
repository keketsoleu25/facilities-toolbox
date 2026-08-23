using FacilitiesApi.Configuration;
using Microsoft.AspNetCore.Mvc;
using Microsoft.Extensions.Options;

namespace FacilitiesApi.Controllers;

// --------------------------------------------------
// ShiftPolicyController
// --------------------------------------------------
//
// Exposes the effective attendance policy currently used
// by punctuality, alerts and reports.
//
// Route:
// GET /api/shift-policy
// --------------------------------------------------

[ApiController]
[Route("api/shift-policy")]
public class ShiftPolicyController : ControllerBase
{
    private readonly ShiftPolicyOptions _policy;

    public ShiftPolicyController(IOptions<ShiftPolicyOptions> policy)
    {
        _policy = policy.Value;
    }

    [HttpGet]
    public IActionResult GetPolicy()
    {
        return Ok(new
        {
            startTime = _policy.StartTime,
            graceMinutes = _policy.GraceMinutes,
            missingCheckInAlertTime = _policy.MissingCheckInAlertTime,
            longSessionHours = _policy.LongSessionHours,
            minimumAttendanceRate = _policy.MinimumAttendanceRate
        });
    }
}
