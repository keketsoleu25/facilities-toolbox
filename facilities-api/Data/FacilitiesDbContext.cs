using FacilitiesApi.Models;
using Microsoft.EntityFrameworkCore;

namespace FacilitiesApi.Data;


// --------------------------------------------------
// FacilitiesDbContext
// --------------------------------------------------
//
// Central Entity Framework Core database context.
//
// Current tables:
//
// - Employees
// - AttendanceRecords
// --------------------------------------------------

public class FacilitiesDbContext : DbContext
{
    public FacilitiesDbContext(
        DbContextOptions<FacilitiesDbContext> options
    ) : base(options)
    {
    }


    // Employee table.
    public DbSet<Employee> Employees =>
        Set<Employee>();


    // Attendance table.
    public DbSet<AttendanceRecord> AttendanceRecords =>
        Set<AttendanceRecord>();


    // --------------------------------------------------
    // Database model configuration
    // --------------------------------------------------

    protected override void OnModelCreating(
        ModelBuilder modelBuilder
    )
    {
        base.OnModelCreating(modelBuilder);


        // --------------------------------------------------
        // Employee configuration
        // --------------------------------------------------

        modelBuilder.Entity<Employee>(
            employee =>
            {
                // EmployeeId must be unique.
                employee
                    .HasIndex(item => item.EmployeeId)
                    .IsUnique();


                // Tell EF that EmployeeId can be used
                // as a principal key for relationships.
                employee
                    .HasAlternateKey(
                        item => item.EmployeeId
                    );


                // Basic field requirements.
                employee
                    .Property(item => item.EmployeeId)
                    .IsRequired();

                employee
                    .Property(item => item.Name)
                    .IsRequired();


                // --------------------------------------------------
                // Seed development employee
                // --------------------------------------------------
                //
                // EMP001 already exists in our historical
                // attendance records.
                //
                // Seeding it before creating the foreign key
                // allows those existing records to remain valid.
                // --------------------------------------------------

                employee.HasData(
                    new Employee
                    {
                        Id = 1,
                        EmployeeId = "EMP001",
                        Name = "Development Employee",
                        Department = "Facilities",
                        Role = "Technician",
                        Active = true
                    }
                );
            }
        );


        // --------------------------------------------------
        // Attendance relationship
        // --------------------------------------------------

        modelBuilder.Entity<AttendanceRecord>(
            attendance =>
            {
                attendance
                    .HasOne(record => record.Employee)
                    .WithMany(
                        employee =>
                            employee.AttendanceRecords
                    )
                    .HasForeignKey(
                        record => record.EmployeeId
                    )
                    .HasPrincipalKey(
                        employee => employee.EmployeeId
                    )
                    .OnDelete(
                        DeleteBehavior.Restrict
                    );
            }
        );
    }
}