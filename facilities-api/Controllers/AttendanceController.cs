using FacilitiesApi.Dtos;
using FacilitiesApi.Models;
using FacilitiesApi.Services;
using Microsoft.AspNetCore.Mvc;

namespace FacilitiesApi.Controllers;


// --------------------------------------------------
// AttendanceController
// --------------------------------------------------
//
// API endpoints:
//
// GET  /api/attendance
// POST /api/attendance
//
// Database entities are converted into DTOs before
// being returned as JSON.
// --------------------------------------------------

[ApiController]
[Route("api/[controller]")]
public class AttendanceController : ControllerBase
{
    private readonly AttendanceService _attendanceService;


    // --------------------------------------------------
    // Constructor
    // --------------------------------------------------

    public AttendanceController(
        AttendanceService attendanceService
    )
    {
        _attendanceService = attendanceService;
    }


    // --------------------------------------------------
    // GET /api/attendance
    // --------------------------------------------------
    //
    // Used by our PHP Facilities dashboard.
    // --------------------------------------------------

    [HttpGet]
    public async Task<IActionResult> GetAll()
    {
        // Load database records.
        var records =
            await _attendanceService.GetAllAsync();


        // Convert EF Core entities into simple DTOs.
        //
        // This prevents navigation properties from
        // causing JSON reference cycles.
        var response = records
            .Select(ToResponse)
            .ToList();


        return Ok(response);
    }


    // --------------------------------------------------
    // POST /api/attendance
    // --------------------------------------------------
    //
    // Expected request:
    //
    // {
    //     "employeeId": "EMP001",
    //     "action": "IN"
    // }
    // --------------------------------------------------

    [HttpPost]
    public async Task<IActionResult> Create(
        [FromBody] CreateAttendanceRequest request
    )
    {
        try
        {
            // Validate business rules and persist the
            // attendance event to PostgreSQL.
            var record =
                await _attendanceService.CreateAsync(
                    request.EmployeeId,
                    request.Action
                );


            // Do NOT return the Entity Framework object
            // directly.
            //
            // Return our safe API DTO instead.
            return Ok(
                ToResponse(record)
            );
        }

        catch (ArgumentException exception)
        {
            // Invalid input:
            //
            // - employee does not exist
            // - missing employee ID
            // - invalid action
            return BadRequest(new
            {
                error = exception.Message
            });
        }

        catch (InvalidOperationException exception)
        {
            // Business rule conflict:
            //
            // - employee already IN
            // - employee already OUT
            // - employee inactive
            return Conflict(new
            {
                error = exception.Message
            });
        }
    }


    // --------------------------------------------------
    // Convert database entity to API response
    // --------------------------------------------------
    //
    // Keeping this conversion in one method ensures
    // GET and POST return the same JSON structure.
    // --------------------------------------------------

    private static AttendanceResponse ToResponse(
        AttendanceRecord record
    )
    {
        return new AttendanceResponse
        {
            Id = record.Id,
            EmployeeId = record.EmployeeId,
            Action = record.Action,
            Timestamp = record.Timestamp
        };
    }
}


// --------------------------------------------------
// CreateAttendanceRequest
// --------------------------------------------------
//
// Represents JSON received from Python or another
// client when recording attendance.
// --------------------------------------------------

public class CreateAttendanceRequest
{
    public string EmployeeId { get; set; } =
        string.Empty;

    public string Action { get; set; } =
        string.Empty;
}