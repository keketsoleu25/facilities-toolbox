using FacilitiesApi.Data;
using FacilitiesApi.Models;
using Microsoft.EntityFrameworkCore;

namespace FacilitiesApi.Services;


// --------------------------------------------------
// AttendanceService
// --------------------------------------------------
//
// Contains all attendance business rules.
//
// Responsibilities:
//
// - validate employee IDs
// - verify employees exist
// - verify employees are active
// - prevent duplicate IN / OUT states
// - save attendance permanently to PostgreSQL
// --------------------------------------------------

public class AttendanceService
{
    // EF Core database context.
    private readonly FacilitiesDbContext _database;


    // --------------------------------------------------
    // Constructor
    // --------------------------------------------------
    //
    // ASP.NET Core injects FacilitiesDbContext
    // automatically through dependency injection.
    // --------------------------------------------------

    public AttendanceService(
        FacilitiesDbContext database
    )
    {
        _database = database;
    }


    // --------------------------------------------------
    // Get all attendance records
    // --------------------------------------------------

    public async Task<List<AttendanceRecord>> GetAllAsync()
    {
        return await _database
            .AttendanceRecords
            .OrderBy(record => record.Timestamp)
            .ToListAsync();
    }


    // --------------------------------------------------
    // Create attendance event
    // --------------------------------------------------

    public async Task<AttendanceRecord> CreateAsync(
        string employeeId,
        string action
    )
    {
        // --------------------------------------------------
        // Normalise incoming values
        // --------------------------------------------------

        employeeId = employeeId.Trim();

        action = action
            .Trim()
            .ToUpperInvariant();


        // --------------------------------------------------
        // Validate employee ID
        // --------------------------------------------------

        if (string.IsNullOrWhiteSpace(employeeId))
        {
            throw new ArgumentException(
                "Employee ID is required."
            );
        }


        // --------------------------------------------------
        // Validate attendance action
        // --------------------------------------------------

        if (action != "IN" && action != "OUT")
        {
            throw new ArgumentException(
                "Attendance action must be IN or OUT."
            );
        }


        // --------------------------------------------------
        // Verify employee exists
        // --------------------------------------------------

        var employee = await _database
            .Employees
            .FirstOrDefaultAsync(
                item =>
                    item.EmployeeId == employeeId
            );


        // Reject IDs that do not belong to
        // registered employees.
        if (employee is null)
        {
            throw new ArgumentException(
                $"Employee {employeeId} does not exist."
            );
        }


        // --------------------------------------------------
        // Verify employee is active
        // --------------------------------------------------

        if (!employee.Active)
        {
            throw new InvalidOperationException(
                $"Employee {employeeId} is inactive."
            );
        }


        // --------------------------------------------------
        // Find employee's most recent attendance event
        // --------------------------------------------------

        var lastRecord = await _database
            .AttendanceRecords
            .Where(
                record =>
                    record.EmployeeId == employeeId
            )
            .OrderByDescending(
                record => record.Timestamp
            )
            .FirstOrDefaultAsync();


        // --------------------------------------------------
        // Prevent duplicate attendance state
        // --------------------------------------------------
        //
        // Invalid:
        //
        // IN -> IN
        // OUT -> OUT
        //
        // Valid:
        //
        // IN -> OUT -> IN -> OUT
        // --------------------------------------------------

        if (lastRecord?.Action == action)
        {
            throw new InvalidOperationException(
                $"Employee {employeeId} is already clocked {action}."
            );
        }


        // --------------------------------------------------
        // Create new attendance record
        // --------------------------------------------------

        var record = new AttendanceRecord
        {
            EmployeeId = employeeId,
            Action = action,

            // Store a consistent UTC timestamp.
            Timestamp = DateTime.UtcNow
        };


        // --------------------------------------------------
        // Persist record to PostgreSQL
        // --------------------------------------------------

        _database.AttendanceRecords.Add(record);

        await _database.SaveChangesAsync();


        return record;
    }
}