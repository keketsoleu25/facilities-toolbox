using FacilitiesApi.Models;
using Microsoft.EntityFrameworkCore;

namespace FacilitiesApi.Data;

// --------------------------------------------------
// FacilitiesDbContext
// --------------------------------------------------
//
// Central Entity Framework Core database context.
//
// v0.3 Operations Core now models:
// - Sites
// - Buildings
// - Departments
// - Shifts / ShiftAssignments
// - Employees / AttendanceRecords
// - Assets
// - MaintenanceRequests
// - WorkOrders
// - Inspections
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
    public DbSet<Department> Departments => Set<Department>();

    // Asset and maintenance operations tables.
    public DbSet<Asset> Assets => Set<Asset>();
    public DbSet<MaintenanceRequest> MaintenanceRequests =>
        Set<MaintenanceRequest>();
    public DbSet<WorkOrder> WorkOrders => Set<WorkOrder>();
    public DbSet<Inspection> Inspections => Set<Inspection>();

    // Workforce scheduling tables.
    public DbSet<Shift> Shifts => Set<Shift>();
    public DbSet<ShiftAssignment> ShiftAssignments =>
        Set<ShiftAssignment>();

    // Workforce and attendance tables.
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
            site.HasIndex(item => item.SiteCode).IsUnique();
            site.Property(item => item.SiteCode).IsRequired();
            site.Property(item => item.Name).IsRequired();
        });

        // --------------------------------------------------
        // Building configuration
        // --------------------------------------------------

        modelBuilder.Entity<Building>(building =>
        {
            building.HasIndex(item => item.BuildingCode).IsUnique();
            building.Property(item => item.BuildingCode).IsRequired();
            building.Property(item => item.Name).IsRequired();

            building
                .HasOne(item => item.Site)
                .WithMany(site => site.Buildings)
                .HasForeignKey(item => item.SiteId)
                .OnDelete(DeleteBehavior.Restrict);
        });

        // --------------------------------------------------
        // Department configuration
        // --------------------------------------------------

        modelBuilder.Entity<Department>(department =>
        {
            department.HasIndex(item => item.DepartmentCode).IsUnique();
            department.Property(item => item.DepartmentCode).IsRequired();
            department.Property(item => item.Name).IsRequired();

            department
                .HasOne(item => item.Site)
                .WithMany()
                .HasForeignKey(item => item.SiteId)
                .OnDelete(DeleteBehavior.Restrict);

            department
                .HasOne(item => item.Building)
                .WithMany()
                .HasForeignKey(item => item.BuildingId)
                .OnDelete(DeleteBehavior.Restrict);
        });

        // --------------------------------------------------
        // Asset configuration
        // --------------------------------------------------

        modelBuilder.Entity<Asset>(asset =>
        {
            asset.HasIndex(item => item.AssetCode).IsUnique();
            asset.Property(item => item.AssetCode).IsRequired();
            asset.Property(item => item.Name).IsRequired();

            asset
                .HasOne(item => item.Site)
                .WithMany()
                .HasForeignKey(item => item.SiteId)
                .OnDelete(DeleteBehavior.Restrict);

            asset
                .HasOne(item => item.Building)
                .WithMany()
                .HasForeignKey(item => item.BuildingId)
                .OnDelete(DeleteBehavior.Restrict);
        });

        // --------------------------------------------------
        // Maintenance request configuration
        // --------------------------------------------------

        modelBuilder.Entity<MaintenanceRequest>(request =>
        {
            request.HasIndex(item => item.RequestCode).IsUnique();
            request.Property(item => item.RequestCode).IsRequired();
            request.Property(item => item.Title).IsRequired();

            request
                .HasOne(item => item.Asset)
                .WithMany(asset => asset.MaintenanceRequests)
                .HasForeignKey(item => item.AssetId)
                .OnDelete(DeleteBehavior.Restrict);

            request
                .HasOne(item => item.ReportedByEmployee)
                .WithMany()
                .HasForeignKey(item => item.ReportedByEmployeeId)
                .HasPrincipalKey(employee => employee.EmployeeId)
                .OnDelete(DeleteBehavior.Restrict);
        });

        // --------------------------------------------------
        // Work order configuration
        // --------------------------------------------------

        modelBuilder.Entity<WorkOrder>(workOrder =>
        {
            workOrder.HasIndex(item => item.WorkOrderCode).IsUnique();
            workOrder.Property(item => item.WorkOrderCode).IsRequired();
            workOrder.Property(item => item.Title).IsRequired();

            workOrder
                .HasOne(item => item.MaintenanceRequest)
                .WithMany(request => request.WorkOrders)
                .HasForeignKey(item => item.MaintenanceRequestId)
                .OnDelete(DeleteBehavior.Restrict);

            workOrder
                .HasOne(item => item.AssignedEmployee)
                .WithMany()
                .HasForeignKey(item => item.AssignedEmployeeId)
                .HasPrincipalKey(employee => employee.EmployeeId)
                .OnDelete(DeleteBehavior.Restrict);
        });

        // --------------------------------------------------
        // Inspection configuration
        // --------------------------------------------------

        modelBuilder.Entity<Inspection>(inspection =>
        {
            inspection.HasIndex(item => item.InspectionCode).IsUnique();
            inspection.Property(item => item.InspectionCode).IsRequired();

            inspection
                .HasOne(item => item.Asset)
                .WithMany(asset => asset.Inspections)
                .HasForeignKey(item => item.AssetId)
                .OnDelete(DeleteBehavior.Restrict);

            inspection
                .HasOne(item => item.InspectorEmployee)
                .WithMany()
                .HasForeignKey(item => item.InspectorEmployeeId)
                .HasPrincipalKey(employee => employee.EmployeeId)
                .OnDelete(DeleteBehavior.Restrict);
        });

        // --------------------------------------------------
        // Shift configuration
        // --------------------------------------------------

        modelBuilder.Entity<Shift>(shift =>
        {
            shift.HasIndex(item => item.ShiftCode).IsUnique();
            shift.Property(item => item.ShiftCode).IsRequired();
            shift.Property(item => item.Name).IsRequired();
        });

        // --------------------------------------------------
        // Employee configuration
        // --------------------------------------------------

        modelBuilder.Entity<Employee>(employee =>
        {
            employee.HasIndex(item => item.EmployeeId).IsUnique();
            employee.HasAlternateKey(item => item.EmployeeId);
            employee.Property(item => item.EmployeeId).IsRequired();
            employee.Property(item => item.Name).IsRequired();

            employee
                .HasOne(item => item.DepartmentRecord)
                .WithMany(department => department.Employees)
                .HasForeignKey(item => item.DepartmentId)
                .OnDelete(DeleteBehavior.Restrict);

            employee.HasData(
                new Employee
                {
                    Id = 1,
                    EmployeeId = "EMP001",
                    Name = "Development Employee",
                    Department = "Facilities",
                    DepartmentId = null,
                    Role = "Technician",
                    Active = true
                }
            );
        });

        // --------------------------------------------------
        // Shift assignment configuration
        // --------------------------------------------------

        modelBuilder.Entity<ShiftAssignment>(assignment =>
        {
            assignment.Property(item => item.EmployeeId).IsRequired();

            assignment
                .HasOne(item => item.Employee)
                .WithMany(employee => employee.ShiftAssignments)
                .HasForeignKey(item => item.EmployeeId)
                .HasPrincipalKey(employee => employee.EmployeeId)
                .OnDelete(DeleteBehavior.Restrict);

            assignment
                .HasOne(item => item.Shift)
                .WithMany(shift => shift.Assignments)
                .HasForeignKey(item => item.ShiftId)
                .OnDelete(DeleteBehavior.Restrict);

            assignment
                .HasIndex(item => new
                {
                    item.EmployeeId,
                    item.ShiftId,
                    item.EffectiveFrom
                })
                .IsUnique();
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
