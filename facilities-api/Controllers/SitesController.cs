using FacilitiesApi.Data;
using FacilitiesApi.Models;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace FacilitiesApi.Controllers;

// --------------------------------------------------
// SitesController
// --------------------------------------------------
//
// Manages physical facilities sites.
//
// Routes:
// GET   /api/sites
// POST  /api/sites
// PUT   /api/sites/{siteCode}
// PATCH /api/sites/{siteCode}/status
// --------------------------------------------------

[ApiController]
[Route("api/sites")]
public class SitesController : ControllerBase
{
    private readonly FacilitiesDbContext _database;

    public SitesController(FacilitiesDbContext database)
    {
        _database = database;
    }

    [HttpGet]
    public async Task<IActionResult> GetAll()
    {
        var sites = await _database.Sites
            .AsNoTracking()
            .OrderBy(site => site.SiteCode)
            .Select(site => new
            {
                site.Id,
                site.SiteCode,
                site.Name,
                site.Address,
                site.Active,
                BuildingCount = site.Buildings.Count
            })
            .ToListAsync();

        return Ok(sites);
    }

    [HttpPost]
    public async Task<IActionResult> Create(
        [FromBody] CreateSiteRequest request
    )
    {
        var siteCode = request.SiteCode.Trim().ToUpperInvariant();
        var name = request.Name.Trim();
        var address = request.Address.Trim();

        if (string.IsNullOrWhiteSpace(siteCode) ||
            string.IsNullOrWhiteSpace(name))
        {
            return BadRequest(new
            {
                error = "Site code and name are required."
            });
        }

        if (await _database.Sites.AnyAsync(
            site => site.SiteCode == siteCode))
        {
            return Conflict(new
            {
                error = $"Site {siteCode} already exists."
            });
        }

        var site = new Site
        {
            SiteCode = siteCode,
            Name = name,
            Address = address,
            Active = true
        };

        _database.Sites.Add(site);
        await _database.SaveChangesAsync();

        return Ok(new
        {
            site.Id,
            site.SiteCode,
            site.Name,
            site.Address,
            site.Active
        });
    }

    [HttpPut("{siteCode}")]
    public async Task<IActionResult> Update(
        string siteCode,
        [FromBody] UpdateSiteRequest request
    )
    {
        var normalizedCode = siteCode.Trim().ToUpperInvariant();

        var site = await _database.Sites
            .FirstOrDefaultAsync(item => item.SiteCode == normalizedCode);

        if (site is null)
        {
            return NotFound(new
            {
                error = $"Site {normalizedCode} was not found."
            });
        }

        var name = request.Name.Trim();

        if (string.IsNullOrWhiteSpace(name))
        {
            return BadRequest(new
            {
                error = "Site name is required."
            });
        }

        site.Name = name;
        site.Address = request.Address.Trim();

        await _database.SaveChangesAsync();

        return Ok(site);
    }

    [HttpPatch("{siteCode}/status")]
    public async Task<IActionResult> UpdateStatus(
        string siteCode,
        [FromBody] UpdateSiteStatusRequest request
    )
    {
        var normalizedCode = siteCode.Trim().ToUpperInvariant();

        var site = await _database.Sites
            .FirstOrDefaultAsync(item => item.SiteCode == normalizedCode);

        if (site is null)
        {
            return NotFound(new
            {
                error = $"Site {normalizedCode} was not found."
            });
        }

        site.Active = request.Active;
        await _database.SaveChangesAsync();

        return Ok(new
        {
            site.SiteCode,
            site.Active
        });
    }
}

public class CreateSiteRequest
{
    public string SiteCode { get; set; } = string.Empty;
    public string Name { get; set; } = string.Empty;
    public string Address { get; set; } = string.Empty;
}

public class UpdateSiteRequest
{
    public string Name { get; set; } = string.Empty;
    public string Address { get; set; } = string.Empty;
}

public class UpdateSiteStatusRequest
{
    public bool Active { get; set; }
}
