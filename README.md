# College Hostel Management System (Core PHP + PDO + MySQL)

## Path
`F:\GOOGLE Antigravity\BCA\hostel-management`

## Stack
- Core PHP (OOP style classes)
- MySQL
- PDO prepared statements
- Bootstrap 5
- DataTables, SweetAlert2, Select2, Toastr, Chart.js

## Setup
1. Create DB and tables:
   - Import [`database/schema.sql`](./database/schema.sql)
2. Configure DB credentials:
   - Edit [`config/config.php`](./config/config.php)
3. Create super admin user:
   - Run: `php database/seed_admin.php`
   - Default: `admin@hostel.local / Admin@123`
4. Serve project root as `/hostel-management`.

## Security Implemented
- Session auth with role checks (`super_admin`, `warden`, `class_coordinator`, `staff`)
- Inactivity auto logout after 30 minutes
- CSRF token on form actions
- PDO prepared statements only
- `htmlspecialchars` escaping helper
- File upload MIME + 2MB checks
- Module pages enforce login/role checks

## Modules Implemented
- Auth
- Dashboard
- Students (unique phone/email checks, uploads, profile with history)
- Rooms + Beds (visual occupancy grid)
- Allotment (allot/vacate/transfer + letter print endpoint)
- Leave (CC -> Warden multilevel approval + timeline + audit trail)
- Complaints
- Visitors
- Staff
- Notices
- Reports (CSV + PDF fallback print / FPDF if installed)
- Audit logs

## FPDF
If you want native PDF generation, place official `fpdf.php` in:
`vendor/fpdf/fpdf.php`

The project auto-loads it if present; otherwise printable HTML fallback is used.
