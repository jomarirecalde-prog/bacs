<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>BACS System Update Report</title>
    @include('reports.partials.print-theme')
    <style>
        h2.section { font-size: 13px; font-weight: bold; color: #047857; margin: 18px 0 8px; border-bottom: 1px solid #cfd6d3; padding-bottom: 4px; }
        h3.sub { font-size: 11px; font-weight: bold; margin: 12px 0 6px; color: #10201b; }
        p, li { line-height: 1.55; margin: 0 0 8px; }
        ul { margin: 0 0 10px 18px; padding: 0; }
        .overview { background: #f0fdf4; border: 1px solid #a7f3d0; padding: 10px 12px; margin-bottom: 14px; }
        table.menu-ref { margin-top: 8px; font-size: 10px; }
        table.doc-control td { border: 0; padding: 3px 8px 3px 0; }
        table.doc-control td:first-child { font-weight: bold; width: 120px; color: #065f46; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <div class="doc-header">
        <h1>BACS System Update Report</h1>
        <h2>Plain-language summary for management, HR, IT, and end users</h2>
    </div>
    <div class="doc-rule-gold"></div>

    <p class="meta">
        <strong>Coverage:</strong> Evening of November 13, 2026 through November 14, 2026 (morning)<br>
        <strong>Report date:</strong> November 14, 2026<br>
        <strong>System:</strong> BACS (HR and attendance platform)
    </p>

    <div class="overview">
        <strong>Overview</strong><br>
        This period added new employee request types (overtime attachment reports and official business), clearer approval setup for administrators, a map showing attendance station locations, in-app email configuration, and fixes so maps and pages work reliably when moving around the system on the live website.
    </div>

    <h2 class="section">Part 1 — Updates from Last Night (November 13, Evening)</h2>

    <h3 class="sub">1. Approval settings reorganized (Pardon vs. other requests)</h3>
    <p><strong>What changed:</strong> Attendance pardon (late or undertime corrections) now has its own approval workflow screen in admin settings, separate from the combined approval workflow page used for leave, travel, overtime, and similar items.</p>
    <p><strong>Why it matters:</strong> Administrators can configure pardon routing without mixing it with other workflows.</p>
    <p><strong>Who is affected:</strong> Admins and HR configure pardon endorsers and approvers on the dedicated section. Employees and approvers should see the same routing behavior as before; only where settings are managed has changed.</p>

    <h3 class="sub">2. Easier editing of approval workflow lists</h3>
    <p>When setting up who endorses or approves requests, administrators can remove people from the list with a clear control. Saving the list respects exactly what is on screen, so empty slots are not accidentally filled from old data.</p>

    <h3 class="sub">3. New: Overtime (OT) Attachment Report</h3>
    <p>Employees can file <strong>OT Attachment Reports</strong>—documentation of overtime work (dates, activities, and supporting details)—that go through the same style of approval chain used for other HR requests.</p>
    <ul>
        <li><strong>Employees</strong> create and track reports from <em>OT Attachment Reports</em> in the employee menu; status and approval progress are visible on DTR and official time views where overtime appears.</li>
        <li><strong>Approvers</strong> review items under endorsement or approval duties, consistent with leave or travel.</li>
        <li><strong>Payroll and admin</strong> review overtime with better alignment to employee-submitted reports and can export a PDF of the official form with company letterhead.</li>
    </ul>
    <p><strong>Same evening:</strong> Employees may file an OT attachment report even if they did not file Official Time first for that day. The system can create or link the related payroll overtime entry when the report is saved.</p>

    <h3 class="sub">4. New: Official Business (OB) module</h3>
    <p>A full <strong>Official Business</strong> request type for work-related trips or activities outside the usual workplace, with participants, destination, dates, and approval similar to Travel Orders.</p>
    <ul>
        <li><strong>Employees</strong> use <em>Official Business</em> to create requests, track my requests, endorsement, approval, and OB participation tabs, and see summary counts (pending, approved, rejected, and so on).</li>
        <li><strong>After approval,</strong> official business can feed into attendance and payroll so those days are treated correctly on DTR and payroll attendance.</li>
        <li><strong>Admins</strong> use <em>Official Business Management</em> to oversee requests. Official forms can be printed for filing.</li>
    </ul>

    <h3 class="sub">5. Station location map (admin)</h3>
    <p>A new admin page shows where attendance stations were last seen on a map, using location data the station sends when it is online. Station detail pages reflect last known location.</p>

    <h3 class="sub">6. Timekeeping options per employee</h3>
    <p>Each employee can be marked whether they must use timekeeping (stations or scans) or are exempt. Admins set this on the employee profile; exempt staff are not forced through the same station rules as others.</p>

    <h2 class="section page-break">Part 2 — Updates from Today (November 14, Morning)</h2>

    <h3 class="sub">1. Station map not showing — fixed</h3>
    <p>On the live site, opening <em>Station Locations</em> sometimes showed no map when admins opened the page from the sidebar without a full page reload. The map now loads reliably when navigating inside the app.</p>

    <h3 class="sub">2. Website assets on subdirectory hosting — fixed</h3>
    <p>When BACS runs in a subfolder on the server, some scripts and styles could load from the wrong path and break pages including the map. Build and asset paths now follow the correct site URL for production hosting.</p>

    <h3 class="sub">3. Admin email settings and status emails</h3>
    <p>Administrators can configure outgoing email from <strong>Settings</strong>:</p>
    <ul>
        <li>Choose how mail is sent (email server, log-only for testing, or sendmail).</li>
        <li>Enter server host, port, encryption, username, and password (passwords stored securely).</li>
        <li>See whether the app uses saved in-app settings or default environment settings.</li>
    </ul>
    <p>When important requests move to a new status (approved, rejected, and similar), the system can send <strong>transaction update</strong> emails across major request types: leave, travel, official business, official time, overtime attachment, pardon, payroll overtime approval, and related workflows. Use <em>Email notification preview</em> in admin to send a test after saving.</p>

    <h2 class="section">Quick reference — menus and features</h2>
    <table class="menu-ref">
        <thead>
            <tr>
                <th>Area</th>
                <th>Menu or feature</th>
                <th>Who</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>Employee</td><td>OT Attachment Reports</td><td>Employees filing OT paperwork</td></tr>
            <tr><td>Employee</td><td>Official Business</td><td>Employees filing OB</td></tr>
            <tr><td>Employee</td><td>DTR / dashboard</td><td>OT and OB-related context</td></tr>
            <tr><td>Approver</td><td>OB approvals</td><td>Endorsers / approvers for official business</td></tr>
            <tr><td>Approver</td><td>OT attachment approvals</td><td>Endorsers / approvers for OT reports</td></tr>
            <tr><td>Admin</td><td>Official Business Management</td><td>HR / admin oversight</td></tr>
            <tr><td>Admin</td><td>Station Locations (map)</td><td>IT / admin monitoring stations</td></tr>
            <tr><td>Admin</td><td>Settings → Email</td><td>Configuring outbound mail</td></tr>
            <tr><td>Admin</td><td>Approval workflow (split)</td><td>Pardon separate; other types on unified pages</td></tr>
        </tbody>
    </table>

    <h2 class="section">Suggested actions</h2>
    <h3 class="sub">For administrators</h3>
    <ol style="margin-left:18px;">
        <li>Review approval workflows for pardon, OT attachment, and official business.</li>
        <li>Configure email in Settings and send a test from email notification preview.</li>
        <li>Open Station Locations and confirm the map loads from the sidebar on the live site.</li>
        <li>Review employee timekeeping exempt vs. required flags.</li>
        <li>Announce OT Attachment Reports and Official Business to staff.</li>
    </ol>
    <h3 class="sub">For employees and approvers</h3>
    <ul>
        <li>Use OT Attachment Reports for documented overtime; use Official Business for approved off-site or official activity when policy requires it.</li>
        <li>Check endorsement and approval queues for the two new types.</li>
        <li>Watch for email updates when request status changes (once admin email is configured).</li>
    </ul>

    <h3 class="sub">Earlier on November 13 (afternoon)</h3>
    <p class="muted">Attendance correction (pardon) requests gained support for file attachments when employees submit corrections; admins can view attachments when reviewing a request.</p>

    <h2 class="section">Document control</h2>
    <table class="doc-control">
        <tr><td>Report date</td><td>November 14, 2026</td></tr>
        <tr><td>Audience</td><td>Management, HR, IT support, end-user training</td></tr>
        <tr><td>Format</td><td>Non-technical summary of deployed changes</td></tr>
    </table>
</body>
</html>
