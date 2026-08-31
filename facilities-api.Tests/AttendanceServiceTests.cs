using FacilitiesApi.Data;
using FacilitiesApi.Models;
using FacilitiesApi.Services;
using Microsoft.EntityFrameworkCore;

namespace FacilitiesApi.Tests;

public class AttendanceServiceTests
{
    private static FacilitiesDbContext CreateDatabase()
    {
        var options = new DbContextOptionsBuilder<FacilitiesDbContext>()
            .UseInMemoryDatabase(Guid.NewGuid().ToString())
            .Options;

        return new FacilitiesDbContext(options);
    }

    [Fact]
    public async Task CreateAsync_RejectsBlankEmployeeId()
    {
        await using var database = CreateDatabase();
        var service = new AttendanceService(database);

        var exception = await Assert.ThrowsAsync<ArgumentException>(
            () => service.CreateAsync("   ", "IN")
        );

        Assert.Equal("Employee ID is required.", exception.Message);
    }

    [Fact]
    public async Task CreateAsync_RejectsUnsupportedAction()
    {
        await using var database = CreateDatabase();
        var service = new AttendanceService(database);

        var exception = await Assert.ThrowsAsync<ArgumentException>(
            () => service.CreateAsync("EMP100", "BREAK")
        );

        Assert.Equal("Attendance action must be IN or OUT.", exception.Message);
    }

    [Fact]
    public async Task CreateAsync_RejectsUnknownEmployee()
    {
        await using var database = CreateDatabase();
        var service = new AttendanceService(database);

        var exception = await Assert.ThrowsAsync<ArgumentException>(
            () => service.CreateAsync("EMP404", "IN")
        );

        Assert.Equal("Employee EMP404 does not exist.", exception.Message);
    }

    [Fact]
    public async Task CreateAsync_RejectsInactiveEmployee()
    {
        await using var database = CreateDatabase();
        database.Employees.Add(new Employee
        {
            EmployeeId = "EMP200",
            Name = "Inactive Employee",
            Department = "Facilities",
            Role = "Technician",
            Active = false
        });
        await database.SaveChangesAsync();

        var service = new AttendanceService(database);

        var exception = await Assert.ThrowsAsync<InvalidOperationException>(
            () => service.CreateAsync("EMP200", "IN")
        );

        Assert.Equal("Employee EMP200 is inactive.", exception.Message);
    }

    [Fact]
    public async Task CreateAsync_NormalisesInputAndPersistsValidAttendance()
    {
        await using var database = CreateDatabase();
        database.Employees.Add(new Employee
        {
            EmployeeId = "EMP300",
            Name = "Active Employee",
            Department = "Facilities",
            Role = "Supervisor",
            Active = true
        });
        await database.SaveChangesAsync();

        var service = new AttendanceService(database);
        var record = await service.CreateAsync("  EMP300  ", " in ");

        Assert.Equal("EMP300", record.EmployeeId);
        Assert.Equal("IN", record.Action);
        Assert.Single(await database.AttendanceRecords.ToListAsync());
    }

    [Fact]
    public async Task CreateAsync_PreventsDuplicateAttendanceState()
    {
        await using var database = CreateDatabase();
        database.Employees.Add(new Employee
        {
            EmployeeId = "EMP400",
            Name = "Active Employee",
            Department = "Facilities",
            Role = "Technician",
            Active = true
        });
        await database.SaveChangesAsync();

        var service = new AttendanceService(database);
        await service.CreateAsync("EMP400", "IN");

        var exception = await Assert.ThrowsAsync<InvalidOperationException>(
            () => service.CreateAsync("EMP400", "IN")
        );

        Assert.Equal("Employee EMP400 is already clocked IN.", exception.Message);
        Assert.Single(await database.AttendanceRecords.ToListAsync());
    }

    [Fact]
    public async Task CreateAsync_AllowsAlternatingInAndOutStates()
    {
        await using var database = CreateDatabase();
        database.Employees.Add(new Employee
        {
            EmployeeId = "EMP500",
            Name = "Active Employee",
            Department = "Facilities",
            Role = "Technician",
            Active = true
        });
        await database.SaveChangesAsync();

        var service = new AttendanceService(database);
        await service.CreateAsync("EMP500", "IN");
        var clockOut = await service.CreateAsync("EMP500", "OUT");

        Assert.Equal("OUT", clockOut.Action);
        Assert.Equal(2, await database.AttendanceRecords.CountAsync());
    }
}
