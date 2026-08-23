using FacilitiesApi.Data;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace FacilitiesApi.Controllers;

// --------------------------------------------------
// EmployeePlacementController
// --------------------------------------------------
//
// Connects an existing employee to the structured v0.3
// facilities hierarchy through Department.
//
// The Department model already knows its Site / Building,
// so assigning a Department gives the employee physical
// operational context without duplicating location fields.
// --------------------------------------------------

[ApiController]
[Route("api/employee-placement")]
public class EmployeePlacementController : ControllerBase
{
    private readonly FacilitiesDbContext _database;

    public EmployeePlacementController(FacilitiesDbContext database)
    {
        _database = database;
    }

    // --------------------------------------------------
    // PATCH /api/employee-placement/{employeeId}
    // --------------------------------------------------
    //
    // Example body:
    // {
    //   "departmentId": 3
    // }
    // --------------------------------------------------

    [HttpPatch("{employeeId}")]
    public async Task<IActionResult> AssignDepartment(
        string employeeId,
        [FromBody] AssignDepartmentRequest request
    )
    {
        var employee = await _database
            .Employees
            .FirstOrDefaultAsync(item => item.EmployeeId == employeeId);

        if (employee is null)
        {
            return NotFound(new
            {
                error = $"Employee {employeeId} was not found."
            });
        }

        var department = await _database
            .Departments
            .Include(item => item.Site)
            .Include(item => item.Building)
            .FirstOrDefaultAsync(item => item.Id == request.DepartmentId);

        if (department is null)
        {
            return BadRequest(new
            {
                error = "The selected department does not exist."
            });
        }

        if (!department.Active)
        {
            return Conflict(new
            {
                error = "The selected department is inactive."
            });
        }

        // Store the structured relationship.
        employee.DepartmentId = department.Id;

        // Keep the legacy string synchronized while v0.2 pages
        // are still being migrated to the structured model.
        employee.Department = department.Name;

        await _database.SaveChangesAsync();

        return Ok(new
        {
            employee.EmployeeId,
            employee.Name,
            departmentId = department.Id,
            departmentCode = department.DepartmentCode,
            department = department.Name,
            site = department.Site?.Name,
            building = department.Building?.Name
        });
    }
}

public class AssignDepartmentRequest
{
    public int DepartmentId { get; set; }
}
