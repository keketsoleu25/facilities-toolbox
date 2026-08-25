using FacilitiesApi.Data;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace FacilitiesApi.Controllers;

// --------------------------------------------------
// DepartmentsController
// --------------------------------------------------
//
// v0.2 does not need a separate Department table yet.
// Departments are derived from employee records so the
// product can expose useful team intelligence without a
// database migration.
//
// Route:
// GET /api/departments
// --------------------------------------------------

[ApiController]
[Route("api/[controller]")]
public class DepartmentsController : ControllerBase
{
    private readonly FacilitiesDbContext _database;

    public DepartmentsController(FacilitiesDbContext database)
    {
        _database = database;
    }

    [HttpGet]
    public async Task<IActionResult> GetAll()
    {
        var employees = await _database
            .Employees
            .AsNoTracking()
            .OrderBy(employee => employee.EmployeeId)
            .ToListAsync();

        var departments = employees
            .GroupBy(employee =>
                string.IsNullOrWhiteSpace(employee.Department)
                    ? "Unassigned"
                    : employee.Department.Trim()
            )
            .OrderBy(group => group.Key)
            .Select(group => new
            {
                department = group.Key,
                totalEmployees = group.Count(),
                activeEmployees = group.Count(employee => employee.Active),
                inactiveEmployees = group.Count(employee => !employee.Active),
                roles = group
                    .Where(employee => !string.IsNullOrWhiteSpace(employee.Role))
                    .Select(employee => employee.Role.Trim())
                    .Distinct()
                    .OrderBy(role => role)
                    .ToList(),
                employees = group
                    .Select(employee => new
                    {
                        employee.EmployeeId,
                        employee.Name,
                        employee.Role,
                        employee.Active
                    })
                    .ToList()
            })
            .ToList();

        return Ok(departments);
    }
}
