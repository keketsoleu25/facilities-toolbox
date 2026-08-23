using FacilitiesApi.Data;
using FacilitiesApi.Models;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace FacilitiesApi.Controllers;

// --------------------------------------------------
// ShiftsController
// --------------------------------------------------
//
// Manages reusable shifts and historical employee
// assignments.
// --------------------------------------------------

[ApiController]
[Route("api/shifts")]
public class ShiftsController : ControllerBase
{
    private readonly FacilitiesDbContext _database;

    public ShiftsController(FacilitiesDbContext database)
    {
        _database = database;
    }

    [HttpGet]
    public async Task<IActionResult> GetAll()
    {
        var shifts = await _database.Shifts
            .AsNoTracking()
            .OrderBy(shift => shift.ShiftCode)
            .Select(shift => new
            {
                shift.Id,
                shift.ShiftCode,
                shift.Name,
                shift.StartTime,
                shift.EndTime,
                shift.GraceMinutes,
                shift.Active,
                ActiveAssignments = shift.Assignments.Count(
                    assignment => assignment.Active
                )
            })
            .ToListAsync();

        return Ok(shifts);
    }

    [HttpPost]
    public async Task<IActionResult> Create(
        [FromBody] CreateShiftRequest request
    )
    {
        var shiftCode = request.ShiftCode.Trim().ToUpperInvariant();
        var name = request.Name.Trim();

        if (string.IsNullOrWhiteSpace(shiftCode) ||
            string.IsNullOrWhiteSpace(name))
        {
            return BadRequest(new
            {
                error = "Shift code and name are required."
            });
        }

        if (!TimeSpan.TryParse(request.StartTime, out var startTime) ||
            !TimeSpan.TryParse(request.EndTime, out var endTime))
        {
            return BadRequest(new
            {
                error = "Start and end times must use HH:mm format."
            });
        }

        if (await _database.Shifts.AnyAsync(
            shift => shift.ShiftCode == shiftCode))
        {
            return Conflict(new
            {
                error = $"Shift {shiftCode} already exists."
            });
        }

        var shift = new Shift
        {
            ShiftCode = shiftCode,
            Name = name,
            StartTime = startTime,
            EndTime = endTime,
            GraceMinutes = Math.Max(0, request.GraceMinutes),
            Active = true
        };

        _database.Shifts.Add(shift);
        await _database.SaveChangesAsync();

        return Ok(new
        {
            shift.Id,
            shift.ShiftCode,
            shift.Name,
            shift.StartTime,
            shift.EndTime,
            shift.GraceMinutes,
            shift.Active
        });
    }

    [HttpGet("assignments")]
    public async Task<IActionResult> GetAssignments()
    {
        var assignments = await _database.ShiftAssignments
            .AsNoTracking()
            .Include(assignment => assignment.Employee)
            .Include(assignment => assignment.Shift)
            .OrderByDescending(assignment => assignment.EffectiveFrom)
            .Select(assignment => new
            {
                assignment.Id,
                assignment.EmployeeId,
                EmployeeName = assignment.Employee.Name,
                assignment.ShiftId,
                ShiftCode = assignment.Shift.ShiftCode,
                ShiftName = assignment.Shift.Name,
                assignment.EffectiveFrom,
                assignment.EffectiveTo,
                assignment.Active
            })
            .ToListAsync();

        return Ok(assignments);
    }

    [HttpPost("assignments")]
    public async Task<IActionResult> Assign(
        [FromBody] CreateShiftAssignmentRequest request
    )
    {
        var employeeId = request.EmployeeId.Trim().ToUpperInvariant();
        var shiftCode = request.ShiftCode.Trim().ToUpperInvariant();

        var employee = await _database.Employees
            .FirstOrDefaultAsync(item => item.EmployeeId == employeeId);

        if (employee is null)
        {
            return BadRequest(new
            {
                error = $"Employee {employeeId} does not exist."
            });
        }

        var shift = await _database.Shifts
            .FirstOrDefaultAsync(item => item.ShiftCode == shiftCode);

        if (shift is null)
        {
            return BadRequest(new
            {
                error = $"Shift {shiftCode} does not exist."
            });
        }

        if (!DateOnly.TryParse(request.EffectiveFrom, out var effectiveFrom))
        {
            return BadRequest(new
            {
                error = "EffectiveFrom must use YYYY-MM-DD format."
            });
        }

        // Close any currently active assignment before creating
        // the replacement. Historical assignments stay intact.
        var activeAssignments = await _database.ShiftAssignments
            .Where(assignment =>
                assignment.EmployeeId == employeeId &&
                assignment.Active)
            .ToListAsync();

        foreach (var existing in activeAssignments)
        {
            existing.Active = false;
            existing.EffectiveTo = effectiveFrom.AddDays(-1);
        }

        var assignment = new ShiftAssignment
        {
            EmployeeId = employeeId,
            ShiftId = shift.Id,
            EffectiveFrom = effectiveFrom,
            EffectiveTo = null,
            Active = true
        };

        _database.ShiftAssignments.Add(assignment);
        await _database.SaveChangesAsync();

        return Ok(new
        {
            assignment.Id,
            assignment.EmployeeId,
            shift.ShiftCode,
            shift.Name,
            assignment.EffectiveFrom,
            assignment.Active
        });
    }
}

public class CreateShiftRequest
{
    public string ShiftCode { get; set; } = string.Empty;
    public string Name { get; set; } = string.Empty;
    public string StartTime { get; set; } = "08:00";
    public string EndTime { get; set; } = "17:00";
    public int GraceMinutes { get; set; } = 15;
}

public class CreateShiftAssignmentRequest
{
    public string EmployeeId { get; set; } = string.Empty;
    public string ShiftCode { get; set; } = string.Empty;
    public string EffectiveFrom { get; set; } = string.Empty;
}
