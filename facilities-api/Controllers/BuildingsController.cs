using FacilitiesApi.Data;
using FacilitiesApi.Models;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace FacilitiesApi.Controllers;

// --------------------------------------------------
// BuildingsController
// --------------------------------------------------
//
// Manages buildings that belong to facilities sites.
// --------------------------------------------------

[ApiController]
[Route("api/buildings")]
public class BuildingsController : ControllerBase
{
    private readonly FacilitiesDbContext _database;

    public BuildingsController(FacilitiesDbContext database)
    {
        _database = database;
    }

    [HttpGet]
    public async Task<IActionResult> GetAll()
    {
        var buildings = await _database.Buildings
            .AsNoTracking()
            .Include(building => building.Site)
            .OrderBy(building => building.BuildingCode)
            .Select(building => new
            {
                building.Id,
                building.BuildingCode,
                building.Name,
                building.Description,
                building.Active,
                building.SiteId,
                SiteCode = building.Site.SiteCode,
                SiteName = building.Site.Name
            })
            .ToListAsync();

        return Ok(buildings);
    }

    [HttpPost]
    public async Task<IActionResult> Create(
        [FromBody] CreateBuildingRequest request
    )
    {
        var buildingCode = request.BuildingCode.Trim().ToUpperInvariant();
        var siteCode = request.SiteCode.Trim().ToUpperInvariant();
        var name = request.Name.Trim();

        if (string.IsNullOrWhiteSpace(buildingCode) ||
            string.IsNullOrWhiteSpace(siteCode) ||
            string.IsNullOrWhiteSpace(name))
        {
            return BadRequest(new
            {
                error = "Building code, site code and name are required."
            });
        }

        if (await _database.Buildings.AnyAsync(
            building => building.BuildingCode == buildingCode))
        {
            return Conflict(new
            {
                error = $"Building {buildingCode} already exists."
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

        var building = new Building
        {
            BuildingCode = buildingCode,
            Name = name,
            Description = request.Description.Trim(),
            SiteId = site.Id,
            Active = true
        };

        _database.Buildings.Add(building);
        await _database.SaveChangesAsync();

        return Ok(new
        {
            building.Id,
            building.BuildingCode,
            building.Name,
            building.Description,
            building.Active,
            SiteCode = site.SiteCode,
            SiteName = site.Name
        });
    }

    [HttpPatch("{buildingCode}/status")]
    public async Task<IActionResult> UpdateStatus(
        string buildingCode,
        [FromBody] UpdateBuildingStatusRequest request
    )
    {
        var normalizedCode = buildingCode.Trim().ToUpperInvariant();

        var building = await _database.Buildings
            .FirstOrDefaultAsync(
                item => item.BuildingCode == normalizedCode
            );

        if (building is null)
        {
            return NotFound(new
            {
                error = $"Building {normalizedCode} was not found."
            });
        }

        building.Active = request.Active;
        await _database.SaveChangesAsync();

        return Ok(new
        {
            building.BuildingCode,
            building.Active
        });
    }
}

public class CreateBuildingRequest
{
    public string BuildingCode { get; set; } = string.Empty;
    public string SiteCode { get; set; } = string.Empty;
    public string Name { get; set; } = string.Empty;
    public string Description { get; set; } = string.Empty;
}

public class UpdateBuildingStatusRequest
{
    public bool Active { get; set; }
}
