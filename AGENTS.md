# AGENTS.md - Developer & AI Agent Guidelines for Convota

This document provides operational context, architectural principles, security guidelines, testing protocols, and mandatory visual verification procedures for AI agents and developers modifying the **Convota** codebase.

---

> [!CAUTION]
> ### MANDATORY PLAYWRIGHT & BROWSER EXECUTION RULES
>
> 1. **NO LOCAL BROWSERS ALLOWED:** You are strictly forbidden from installing or running Playwright, Chromium, Chrome, or any headless browser locally inside this container. ALL browser automation, rendering, and screenshot tasks MUST be executed remotely via the Playwright HTTP API server at `http://10.100.9.111:3000/execute`.
> 2. **MANDATORY 2GB MEMORY LIMIT & 15-MINUTE TIMEOUT:** Whenever you execute any Python script or shell command that communicates with the remote Playwright server (`http://10.100.9.111:3000/execute`), you MUST wrap the command in a subshell enforcing a hard 2 GB virtual memory limit (`ulimit -v 2097152`) and a 15-minute execution timeout (`900s` / `15m`) so a runaway response never exhausts container RAM:
>    ```bash
>    (ulimit -v 2097152 && timeout -k 10 15m python3 -c "...")
>    ```
> 3. **DEFENSIVE HTTP HANDLING:** Always include explicit timeouts (e.g., `timeout=(10, 900)` in Python `requests`, or `--connect-timeout 10 --max-time 900` in `curl`) on all HTTP requests, and never load unbounded responses into memory without limits.

---

## 1. System Philosophy & Core Axioms

1. **The Axiom of Representation**:
   - *The vote belongs to the organization; the designated representative is the authenticated person exercising that organization's voting right.*
   - Presence and voting credentials belong to the organization collective. Designating a representative binds the organization's voting power to that authenticated individual for the active meeting.

2. **Absolute Subpath and Subdomain Portability**:
   - The application must operate seamlessly either under a subpath (e.g., `https://server.domain/convota/`) or directly on a root domain/subdomain (e.g., `https://convota.domain/`).
   - **Never hardcode absolute paths** in HTML links, CSS assets, JavaScript Fetch/XHR calls, form action attributes, redirects, or email links.
   - Always use the URL helpers:
     - `url('/path')` for relative internal paths.
     - `full_url('/path')` or `Mailer::buildFullUrl('/path')` for email links, discovering protocol, domain, port, and subpath dynamically from request headers (`X-Forwarded-Proto`, `X-Forwarded-Host`, `X-Forwarded-Prefix`, `REQUEST_URI`).

3. **Decoupled Privacy-Preserving Secret Ballots**:
   - Open votes record the organization identity and designated voter for roll-call transparency.
   - Secret ballots strictly decouple the ballot from the voter. Once an eligible organization casts a ballot, their participation is recorded (to prevent double voting), but the ballot itself contains **zero** organization IDs or user IDs. Voters receive a cryptographic receipt hash (SHA-256) to verify ballot inclusion without compromising anonymity.

4. **Tamper-Evident SHA-256 Audit Trail**:
   - All administrative actions, roster mutations, presence check-ins, proposal status changes, and email deliveries are cryptographically chained in `audit_log` using SHA-256 entry hashes referencing the previous block hash.
   - Email deliveries must log their status: `email send : SUCCESS` or `email send : FAILED` with detailed failure diagnostics.

5. **Data Privacy & Domain Isolation**:
   - Default administrative and test emails must strictly use `@email.vlsi.gr` (e.g., `adminconvota@email.vlsi.gr`, `delegate@email.vlsi.gr`).
   - Never use real third-party domains in automated tests, seed data, or mock delegations to avoid accidental email dispatch to external entities.

---

## 2. Codebase Organization

```
/projects/convota/
├── config/
│   ├── app.php                # System settings, session config, mail driver config
│   └── database.php           # SQLite PDO connection & WAL mode initialization
├── convota.conf               # Frontend Nginx reverse proxy location snippet (/convota/)
├── data/
│   └── convota.sqlite         # SQLite database in WAL mode
├── documentation/
│   ├── ADMIN_GUIDE.md         # Comprehensive operational guide for administrators
│   └── MEMBER_GUIDE.md        # Comprehensive user guide for delegates and members
├── docs/                      # Indicative architecture diagrams and UI vector assets
├── favicon.ico, favicon.png, favicon.svg # Application favicons
├── public/
│   ├── assets/
│   │   ├── css/app.css        # Responsive layout & theme styling
│   │   └── js/app.js          # Dynamic quorum polling, copy URL, and UI interactions
│   └── index.php              # Front controller & request router
├── src/
│   ├── Auth.php               # Authentication, password hashing, session management
│   ├── Csrf.php               # CSRF protection tokens and header verification
│   ├── Database.php           # PDO singleton wrapper
│   ├── Mailer.php             # Email delivery via mail() or SMTP, with base URL discovery
│   ├── Models/                # Domain models (Organization, Member, Meeting, Proposal, Ballot, AuditLog)
│   └── Views/                 # Server-side HTML views and layouts
├── tests/
│   ├── run_all_tests.php      # Main test runner executing all test phases
│   ├── test_phase1.php        # Config, Database, Auth, AuditLog models
│   ├── test_phase2.php        # Organizations, Roster, CSV Conflict Engine, Email Logging
│   ├── test_phase3.php        # Assembly Meetings, Presence, Dynamic Quorum, Reminder Links
│   ├── test_phase4.php        # Voting Engine, Decoupled Ballots, Cryptographic Receipts
│   ├── test_phase5.php        # REST APIs, Web Dispatcher, CSRF & Security Headers
│   └── test_nginx_routing.php # Nginx subpath URL helper & Mailer full URL discovery
└── pull-push.sh               # Git synchronization helper script
```

---

## 3. Git Workflow & Synchronization

1. **Always `git pull` Before Changes**:
   - The repository is connected to remote GitHub (`origin`). Always run `git pull` to fetch and rebase/merge any upstream changes before editing files.
2. **Commit & Push Protocol**:
   - Run the automated test suite (`php tests/run_all_tests.php`) to confirm all tests pass.
   - Stage modified files (`git add <files>`).
   - Create clean, semantic commit messages (`git commit -m "..."`).
   - Push to remote (`git push origin main`).
3. **Mandatory Reporting of Git Commit ID**:
   - Every time code changes, styling, or documentation fixes are committed to git, AI agents and engineers MUST explicitly report the Git Commit ID (SHA hash) in their final response to the user.

---

## 4. Verification & Testing

Always run the automated test suite after modifying backend or frontend code:
```bash
php tests/run_all_tests.php
```

Ensure that:
1. Database migrations and model relationships pass without errors.
2. CSV imports correctly distinguish new lines from identical lines without unnecessary verbose reporting.
3. Decoupled secret ballots produce verifiable receipt hashes and maintain zero linkage to voter identity.
4. Quorum calculation accurately ignores Observer organizations (0 votes) in the numerator.
5. All URL helper tests pass for both root and `/convota` subpath deployments.

---

## 5. Mandatory Playwright Visual Testing Protocol & Execution Guide

Whenever you create or modify HTML, CSS, JavaScript UI layout, or server templates in Convota, you **MUST** immediately execute visual inspection using the central Playwright service. Tasks are **never** considered complete without visually inspecting the resulting screenshots with the `view_file` tool.

### 5.1 Playwright Service Specifications
- **Dedicated Endpoint:** `http://10.100.9.111:3000/execute`
- **Method:** `POST`
- **Content-Type:** `application/json`
- **Engine:** Playwright Chromium headless engine running on Ubuntu VM (`10.100.9.111`).
- **Access Restrictions:**
  - AI agents do **NOT** have SSH access to `10.100.9.111`.
  - If a test requires additional fonts or browser setup, report it explicitly to the user.

### 5.2 Screenshot Storage & Naming
- **Directory:** `/tmp/playwright_screenshots/` exclusively (never commit or stage screenshots in git repositories).
- **Naming Pattern:**
  - Desktop: `/tmp/playwright_screenshots/view-desktop.<YYYYMMDD_HHMMSS>.<tool_or_subsystem>.png`
  - Mobile: `/tmp/playwright_screenshots/view-mobile.<YYYYMMDD_HHMMSS>.<tool_or_subsystem>.png`

### 5.3 Viewport Standards
1. **Desktop:** `1920x1080` (`viewport: { width: 1920, height: 1080 }`).
2. **Mobile:** Modern Android smartphone simulation (`device: "Pixel 5"`, ~393x851 with touch enabled).

### 5.4 Remote Playwright HTTP API Specification

#### Request Payload Fields:
- `url` (string, required): Absolute target URL.
- `viewport` (object): `{"width": 1920, "height": 1080}`.
- `device` (string, optional): Emulates registered Playwright device (e.g. `'Pixel 5'`).
- `fullPage` (boolean, optional): Default `false`. If `true`, captures full scrollable page height.
- `cookies` (array, optional): Array of `{"name": "...", "value": "...", "domain": "...", "path": "/"}`.
- `timeout` (integer, optional): Overall timeout in milliseconds (default `30000`).
- `actions` (array, optional): Ordered list of user interactions before screenshot:
  - `{"type": "wait", "selector": ".card-custom", "timeout": 8000}`
  - `{"type": "sleep", "ms": 500}`
  - `{"type": "click", "selector": "#btnSubmit", "button": "left"}` (supports left, right, middle)
  - `{"type": "right_click", "selector": ".pill"}`
  - `{"type": "fill", "selector": "input[name='key']", "value": "..."}`
  - `{"type": "press", "selector": "input[name='search']", "key": "Enter"}`
  - `{"type": "hover", "selector": ".dropdown"}`
  - `{"type": "scroll", "x": 0, "y": 800}`
  - `{"type": "scroll_into_view", "selector": "#targetRow"}`
  - `{"type": "navigate", "url": "https://..."}`
  - `{"type": "evaluate", "script": "document.querySelector('...').classList.add('show')"}`

#### Response Telemetry Validation:
- `image_base64`: Base64-encoded PNG image data.
- `overflow_elements`: Array of DOM elements where `scrollWidth > clientWidth` or bounding box overflows viewport. **Must be empty `[]`**; non-empty indicates broken mobile horizontal overflow.
- `page_js_errors`: Array of uncaught JS exceptions (`window.onerror`). **Must be empty `[]`**.
- `console_logs`: Browser console logs.

### 5.5 5-Point Vision Checklist (`view_file`)
All visual inspection screenshots must be evaluated against the following criteria:
1. **Zero Horizontal Page Overflow**:
   - The entire page body must never scroll horizontally on mobile.
   - Tables with wide data sets must be wrapped in `.table-responsive` cards allowing strict internal horizontal scrolling (`overflow-x: auto`).
   - `overflow_elements` in API response must be empty (`[]`).
2. **Alignment & Visibility of Headers & Badges**:
   - Card headers, navigation bars, and status indicators must maintain crisp alignment and high contrast.
3. **Readability & Touch Target Heights**:
   - Interactive buttons, tab selectors, and links must have a touch target height of at least 36px (preferably >= 44px on mobile devices).
4. **Safety of Floating Elements**:
   - Badges, floating action buttons, modals, and sticky bars must not overlap form fields, text, or table rows.
5. **Form Field Alignment**:
   - Form inputs, labels, and helper text must align strictly without jagged vertical offsets caused by multi-line labels. Desktop forms should use balanced multi-column grids and collapse to full-width stacks on mobile.

### 5.6 Standard Test Credentials & SSO
- Test Administrator: `adminconvota@email.vlsi.gr` / `AdminConvota2026!`
- Test Delegate: `delegate@email.vlsi.gr`
- System Test Instructor: `eceprofessor` / `ProfPass_99999_SuperSecret!`
- System Test Student: `ece99999` / `TestPass_99999_Secret!` (`ece99999@email.vlsi.gr` / AEM: `99999`)
- **Strict Directive:** Never use fake student emails or unauthorized external domains. Strictly use `@email.vlsi.gr`.

### 5.7 Execution Subshell Wrapper Pattern
Always execute remote Playwright calls within the mandatory resource containment subshell:
```bash
(ulimit -v 2097152 && timeout -k 10 15m python3 -c '
import os, json, base64, requests
os.makedirs("/tmp/playwright_screenshots", exist_ok=True)
payload = {
    "url": "https://arch.ece.uowm.gr/convota/login",
    "viewport": {"width": 1920, "height": 1080}
}
resp = requests.post("http://10.100.9.111:3000/execute", json=payload, timeout=(10, 900))
data = resp.json()
if data.get("success"):
    img = base64.b64decode(data.get("image_base64") or data.get("screenshot"))
    with open("/tmp/playwright_screenshots/view-desktop.login.png", "wb") as f:
        f.write(img)
    print("Screenshot saved successfully!")
')
```

---

## 6. Context Intake & Memory Protocol

Before beginning any task and upon completion:
1. **Start of Task**: Read recent entries in `/root/AI_agent-cli.log` and `/root/AI_agent-cli_memories.md` to understand recent architectural decisions, bug fixes, and system constraints.
2. **End of Task**:
   - Append a one-line entry to `/root/AI_agent-cli.log` (`YYYY-MM-DD HH:mm:ss — <type>(convota): <summary> (commit <hash>)`).
   - Append a detailed section to `/root/AI_agent-cli_memories.md`.
