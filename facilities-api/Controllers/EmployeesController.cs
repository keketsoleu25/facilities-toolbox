using FacilitiesApi.Data;
using FacilitiesApi.Models;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace FacilitiesApi.Controllers;


// --------------------------------------------------
// EmployeesController
// --------------------------------------------------
//
// Provides management endpoints for employees.
//
// Current routes:
//
// GET    /api/employees
// GET    /api/employees/{employeeId}
// POST   /api/employees
// PUT    /api/employees/{employeeId}
// PATCH  /api/employees/{employeeId}/status
//
// The PHP management portal will use these endpoints.
// --------------------------------------------------

[ApiController]
[Route("api/[controller]")]
public class EmployeesController : ControllerBase
{
    private readonly FacilitiesDbContext _database;


    // --------------------------------------------------
    // Constructor
    // --------------------------------------------------
    //
    // ASP.NET Core injects FacilitiesDbContext here.
    // --------------------------------------------------

    public EmployeesController(
        FacilitiesDbContext database
    )
    {
        _database = database;
    }


    // --------------------------------------------------
    // GET /api/employees
    // --------------------------------------------------
    //
    // Returns all employees ordered by EmployeeId.
    // --------------------------------------------------

    [HttpGet]
    public async Task<IActionResult> GetAll()
    {
        var employees = await _database
            .Employees
            .OrderBy(employee => employee.EmployeeId)
            .Select(employee => new
            {
                employee.Id,
                employee.EmployeeId,
                employee.Name,
                employee.Department,
                employee.Role,
                employee.Active
            })
            .ToListAsync();

        return Ok(employees);
    }


    // --------------------------------------------------
    // GET /api/employees/{employeeId}
    // --------------------------------------------------
    //
    // Returns one employee by business ID.
    //
    // Example:
    //
    // GET /api/employees/EMP001
    // --------------------------------------------------

    [HttpGet("{employeeId}")]
    public async Task<IActionResult> GetById(
        string employeeId
    )
    {
        var employee = await _database
            .Employees
            .FirstOrDefaultAsync(
                item =>
                    item.EmployeeId == employeeId
            );

        if (employee is null)
        {
            return NotFound(new
            {
                error =
                    $"Employee {employeeId} was not found."
            });
        }

        return Ok(new
        {
            employee.Id,
            employee.EmployeeId,
            employee.Name,
            employee.Department,
            employee.Role,
            employee.Active
        });
    }


    // --------------------------------------------------
    // POST /api/employees
    // --------------------------------------------------
    //
    // Creates a new employee.
    //
    // Example JSON:
    //
    // {
    //   "employeeId": "EMP002",
    //   "name": "Jane Doe",
    //   "department": "Cleaning",
    //   "role": "Supervisor"
    // }
    // --------------------------------------------------

    [HttpPost]
    public async Task<IActionResult> Create(
        [FromBody] CreateEmployeeRequest request
    )
    {
        // Normalise incoming values.
        var employeeId =
            request.EmployeeId.Trim();

        var name =
            request.Name.Trim();

        var department =
            request.Department.Trim();

        var role =
            request.Role.Trim();


        // --------------------------------------------------
        // Validate required fields
        // --------------------------------------------------

        if (string.IsNullOrWhiteSpace(employeeId))
        {
            return BadRequest(new
            {
                error = "Employee ID is required."
            });
        }

        if (string.IsNullOrWhiteSpace(name))
        {
            return BadRequest(new
            {
                error = "Employee name is required."
            });
        }


        // --------------------------------------------------
        // Prevent duplicate employee IDs
        // --------------------------------------------------

        var alreadyExists = await _database
            .Employees
            .AnyAsync(
                employee =>
                    employee.EmployeeId == employeeId
            );

        if (alreadyExists)
        {
            return Conflict(new
            {
                error =
                    $"Employee {employeeId} already exists."
            });
        }


        // --------------------------------------------------
        // Create employee
        // --------------------------------------------------

        var employee = new Employee
        {
            EmployeeId = employeeId,
            Name = name,
            Department = department,
            Role = role,
            Active = true
        };


        // Stage the database insert.
        _database.Employees.Add(employee);


        // Persist employee to PostgreSQL.
        await _database.SaveChangesAsync();


        return CreatedAtAction(
            nameof(GetById),
            new
            {
                employeeId = employee.EmployeeId
            },
            new
            {
                employee.Id,
                employee.EmployeeId,
                employee.Name,
                employee.Department,
                employee.Role,
                employee.Active
            }
        );
    }


    // --------------------------------------------------
    // PUT /api/employees/{employeeId}
    // --------------------------------------------------
    //
    // Updates editable employee profile fields.
    //
    // EmployeeId itself remains stable.
    // --------------------------------------------------

    [HttpPut("{employeeId}")]
    public async Task<IActionResult> Update(
        string employeeId,
        [FromBody] UpdateEmployeeRequest request
    )
    {
        var employee = await _database
            .Employees
            .FirstOrDefaultAsync(
                item =>
                    item.EmployeeId == employeeId
            );

        if (employee is null)
        {
            return NotFound(new
            {
                error =
                    $"Employee {employeeId} was not found."
            });
        }


        // Update profile information.
        employee.Name =
            request.Name.Trim();

        employee.Department =
            request.Department.Trim();

        employee.Role =
            request.Role.Trim();


        if (string.IsNullOrWhiteSpace(employee.Name))
        {
            return BadRequest(new
            {
                error = "Employee name is required."
            });
        }


        // Persist profile changes.
        await _database.SaveChangesAsync();


        return Ok(new
        {
            employee.Id,
            employee.EmployeeId,
            employee.Name,
            employee.Department,
            employee.Role,
            employee.Active
        });
    }


    // --------------------------------------------------
    // PATCH /api/employees/{employeeId}/status
    // --------------------------------------------------
    //
    // Activates or deactivates an employee.
    //
    // We do NOT delete employees because their
    // historical attendance must remain valid.
    // --------------------------------------------------

    [HttpPatch("{employeeId}/status")]
    public async Task<IActionResult> UpdateStatus(
        string employeeId,
        [FromBody] UpdateEmployeeStatusRequest request
    )
    {
        var employee = await _database
            .Employees
            .FirstOrDefaultAsync(
                item =>
                    item.EmployeeId == employeeId
            );

        if (employee is null)
        {
            return NotFound(new
            {
                error =
                    $"Employee {employeeId} was not found."
            });
        }


        // Change employee status.
        employee.Active =
            request.Active;


        // Persist status change.
        await _database.SaveChangesAsync();


        return Ok(new
        {
            employee.Id,
            employee.EmployeeId,
            employee.Name,
            employee.Department,
            employee.Role,
            employee.Active
        });
    }
}


// --------------------------------------------------
// CreateEmployeeRequest
// --------------------------------------------------
//
// Represents the body used when creating
// a new employee.
// --------------------------------------------------

public class CreateEmployeeRequest
{
    public string EmployeeId { get; set; } =
        string.Empty;

    public string Name { get; set; } =
        string.Empty;

    public string Department { get; set; } =
        string.Empty;

    public string Role { get; set; } =
        string.Empty;
}


// --------------------------------------------------
// UpdateEmployeeRequest
// --------------------------------------------------

public class UpdateEmployeeRequest
{
    public string Name { get; set; } =
        string.Empty;

    public string Department { get; set; } =
        string.Empty;

    public string Role { get; set; } =
        string.Empty;
}


// --------------------------------------------------
// UpdateEmployeeStatusRequest
// --------------------------------------------------

public class UpdateEmployeeStatusRequest
{
    public bool Active { get; set; }
}