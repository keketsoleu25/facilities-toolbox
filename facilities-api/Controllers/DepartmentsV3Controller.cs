using FacilitiesApi.Data;
using FacilitiesApi.Models;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace FacilitiesApi.Controllers;

// --------------------------------------------------
// DepartmentsV3Controller
// --------------------------------------------------
//
// Structured department management for v0.3.
// Kept on /api/departments-v3 so the v0.2 analytics
// department endpoint can continue working unchanged.
// --------------------------------------------------

[ApiController]
[Route("api/departments-v3")]
public class DepartmentsV3Controller : ControllerBase
{
    private readonly FacilitiesDbContext _database;

    public DepartmentsV3Controller(FacilitiesDbContext database)
    {
        _database = database;
    }

    [HttpGet]
    public async Task<IActionResult> GetAll()
    {
        var departments = await _database.Departments
            .AsNoTracking()
            .Include(department => department.Site)
            .Include(department => department.Building)
            .OrderBy(department => department.DepartmentCode)
            .Select(department => new
            {
                department.Id,
                department.DepartmentCode,
                department.Name,
                department.Active,
                department.SiteId,
                SiteCode = department.Site != null
                    ? department.Site.SiteCode
                    : null,
                SiteName = department.Site != null
                    ? department.Site.Name
                    : null,
                department.BuildingId,
                BuildingCode = department.Building != null
                    ? department.Building.BuildingCode
                    : null,
                BuildingName = department.Building != null
                    ? department.Building.Name
                    : null,
                EmployeeCount = department.Employees.Count
            })
            .ToListAsync();

        return Ok(departments);
    }

    [HttpPost]
    public async Task<IActionResult> Create(
        [FromBody] CreateDepartmentV3Request request
    )
    {
        var departmentCode =
            request.DepartmentCode.Trim().ToUpperInvariant();

        var name = request.Name.Trim();

        if (string.IsNullOrWhiteSpace(departmentCode) ||
            string.IsNullOrWhiteSpace(name))
        {
            return BadRequest(new
            {
                error = "Department code and name are required."
            });
        }

        if (await _database.Departments.AnyAsync(
            department =>
                department.DepartmentCode == departmentCode))
        {
            return Conflict(new
            {
                error = $"Department {departmentCode} already exists."
            });
        }

        Site? site = null;
        Building? building = null;

        if (!string.IsNullOrWhiteSpace(request.SiteCode))
        {
            var siteCode = request.SiteCode.Trim().ToUpperInvariant();

            site = await _database.Sites
                .FirstOrDefaultAsync(item => item.SiteCode == siteCode);

            if (site is null)
            {
                return BadRequest(new
                {
                    error = $"Site {siteCode} does not exist."
                });
            }
        }

        if (!string.IsNullOrWhiteSpace(request.BuildingCode))
        {
            var buildingCode =
                request.BuildingCode.Trim().ToUpperInvariant();

            building = await _database.Buildings
                .FirstOrDefaultAsync(
                    item => item.BuildingCode == buildingCode
                );

            if (building is null)
            {
                return BadRequest(new
                {
                    error = $"Building {buildingCode} does not exist."
                });
            }

            if (site is not null && building.SiteId != site.Id)
            {
                return BadRequest(new
                {
                    error = "Selected building does not belong to the selected site."
                });
            }

            site ??= await _database.Sites
                .FirstAsync(item => item.Id == building.SiteId);
        }

        var department = new Department
        {
            DepartmentCode = departmentCode,
            Name = name,
            SiteId = site?.Id,
            BuildingId = building?.Id,
            Active = true
        };

        _database.Departments.Add(department);
        await _database.SaveChangesAsync();

        return Ok(new
        {
            department.Id,
            department.DepartmentCode,
            department.Name,
            department.Active,
            SiteCode = site?.SiteCode,
            BuildingCode = building?.BuildingCode
        });
    }

    [HttpPatch("{departmentCode}/status")]
    public async Task<IActionResult> UpdateStatus(
        string departmentCode,
        [FromBody] UpdateDepartmentStatusRequest request
    )
    {
        var normalizedCode =
            departmentCode.Trim().ToUpperInvariant();

        var department = await _database.Departments
            .FirstOrDefaultAsync(
                item => item.DepartmentCode == normalizedCode
            );

        if (department is null)
        {
            return NotFound(new
            {
                error = $"Department {normalizedCode} was not found."
            });
        }

        department.Active = request.Active;
        await _database.SaveChangesAsync();

        return Ok(new
        {
            department.DepartmentCode,
            department.Active
        });
    }
}

public class CreateDepartmentV3Request
{
    public string DepartmentCode { get; set; } = string.Empty;
    public string Name { get; set; } = string.Empty;
    public string SiteCode { get; set; } = string.Empty;
    public string BuildingCode { get; set; } = string.Empty;
}

public class UpdateDepartmentStatusRequest
{
    public bool Active { get; set; }
}
