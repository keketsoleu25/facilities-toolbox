using FacilitiesApi.Models;
using Microsoft.EntityFrameworkCore;

namespace FacilitiesApi.Data;

// --------------------------------------------------
// FacilitiesDbContext
// --------------------------------------------------
//
// Central Entity Framework Core database context.
//
// v0.3 begins modelling the physical facilities layer:
//
// - Sites
// - Buildings
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

    // Physical facilities tables.
    public DbSet<Site> Sites => Set<Site>();
    public DbSet<Building> Buildings => Set<Building>();

    // Workforce tables.
    public DbSet<Employee> Employees => Set<Employee>();
    public DbSet<AttendanceRecord> AttendanceRecords =>
        Set<AttendanceRecord>();

    protected override void OnModelCreating(
        ModelBuilder modelBuilder
    )
    {
        base.OnModelCreating(modelBuilder);

        // --------------------------------------------------
        // Site configuration
        // --------------------------------------------------

        modelBuilder.Entity<Site>(site =>
        {
            site
                .HasIndex(item => item.SiteCode)
                .IsUnique();

            site
                .Property(item => item.SiteCode)
                .IsRequired();

            site
                .Property(item => item.Name)
                .IsRequired();
        });

        // --------------------------------------------------
        // Building configuration
        // --------------------------------------------------

        modelBuilder.Entity<Building>(building =>
        {
            building
                .HasIndex(item => item.BuildingCode)
                .IsUnique();

            building
                .Property(item => item.BuildingCode)
                .IsRequired();

            building
                .Property(item => item.Name)
                .IsRequired();

            // A site can contain many buildings.
            // Restrict deletion so a parent site cannot be
            // removed while buildings still reference it.
            building
                .HasOne(item => item.Site)
                .WithMany(site => site.Buildings)
                .HasForeignKey(item => item.SiteId)
                .OnDelete(DeleteBehavior.Restrict);
        });

        // --------------------------------------------------
        // Employee configuration
        // --------------------------------------------------

        modelBuilder.Entity<Employee>(employee =>
        {
            employee
                .HasIndex(item => item.EmployeeId)
                .IsUnique();

            employee
                .HasAlternateKey(item => item.EmployeeId);

            employee
                .Property(item => item.EmployeeId)
                .IsRequired();

            employee
                .Property(item => item.Name)
                .IsRequired();

            // Historical attendance already references EMP001,
            // so the development employee remains seeded.
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
        });

        // --------------------------------------------------
        // Attendance relationship
        // --------------------------------------------------

        modelBuilder.Entity<AttendanceRecord>(attendance =>
        {
            attendance
                .HasOne(record => record.Employee)
                .WithMany(employee => employee.AttendanceRecords)
                .HasForeignKey(record => record.EmployeeId)
                .HasPrincipalKey(employee => employee.EmployeeId)
                .OnDelete(DeleteBehavior.Restrict);
        });
    }
}
