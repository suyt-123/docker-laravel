# Security Audit

## Finding 1

- Risk Level: High
- Vulnerability: Public arbitrary file upload for quotation attachments.
- Attack Scenario: An authenticated user with `sales.quotations.update.tenant` uploads an HTML/SVG/scriptable or otherwise unsafe file as a quotation attachment. Because files are stored on the public disk and exposed through `/storage`, another user can open the uploaded file directly.
- Impact: Stored XSS, phishing content, malware hosting, or unsafe content delivery from the application origin/storage origin. If the web server ever executes uploaded PHP-like content due to misconfiguration, impact could become RCE.
- Affected Files:
  - `app/Http/Controllers/QuotationController.php`
  - `routes/web.php`
  - `config/filesystems.php`
- Recommendation: Restrict quotation attachments with an explicit MIME/extension allowlist, store only non-executable business document/image types, and keep public access intentional. Prefer private storage with authorized download routes for sensitive contracts.

## Finding 2

- Risk Level: High
- Vulnerability: User role assignment is coupled to basic user create/update permission.
- Attack Scenario: A custom role or delegated admin is granted `security.users.create.tenant` or `security.users.update.tenant` for account administration, but not role administration. That user can still submit `roles[]` to assign privileged roles to a new or existing account.
- Impact: Privilege escalation to admin/owner-equivalent roles if role IDs are known or enumerable.
- Affected Files:
  - `app/Http/Controllers/UserController.php`
  - `app/Http/Requests/StoreUserRequest.php`
  - `app/Http/Requests/UpdateUserRequest.php`
  - `routes/web.php`
- Recommendation: Require a distinct capability such as `security.roles.assign_capabilities.tenant` before syncing user roles. Allow profile/user field edits independently from role assignment where business needs require that split.

## Finding 3

- Risk Level: Medium
- Vulnerability: Public self-registration is enabled for an internal/business application.
- Attack Scenario: An unauthenticated attacker registers many accounts or creates a foothold account that may later gain access through misconfiguration, weak default role assignment, or operational error.
- Impact: Account spam, increased attack surface, potential workflow abuse. Current route-level capability checks reduce direct data exposure because newly registered users have no role by default.
- Affected Files:
  - `routes/auth.php`
  - `app/Http/Controllers/Auth/RegisteredUserController.php`
- Recommendation: Disable public registration in production unless explicitly needed. Prefer admin-created users or invitation-based onboarding.

## Finding 4

- Risk Level: Medium
- Vulnerability: No Laravel Policy layer and inconsistent object-level authorization strategy.
- Attack Scenario: Future routes or controller actions may rely only on broad route capability checks and forget per-record visibility checks. Current scoped resources use `DataScope`, but many tenant-wide resources have no model policy guardrail.
- Impact: IDOR or cross-tenant data exposure if multi-tenant separation is expanded or route capabilities are over-granted.
- Affected Files:
  - `app/Auth/DataScope.php`
  - `app/Http/Controllers/*`
  - missing `app/Policies`
- Recommendation: Add policies or consistent controller-level scope methods for view/create/update/delete. Enforce tenant ownership at query and route-model binding boundaries.

## Finding 5

- Risk Level: Medium
- Vulnerability: Development defaults expose operational services in Docker.
- Attack Scenario: A developer or staging host starts Docker with default port mappings. PostgreSQL, Redis, MinIO, Mailpit, Adminer, and Vite are reachable on host ports if firewalling is weak.
- Impact: Database/admin console exposure, credential brute force, local object storage compromise, mail inspection exposure.
- Affected Files:
  - `docker-compose.yml`
  - `.env.example`
- Recommendation: Keep Docker defaults for local only. For shared environments, bind internal services to localhost or remove published ports, set strong credentials, and put admin tooling behind VPN/IP allowlists.

## Finding 6

- Risk Level: Low
- Vulnerability: Debug/local settings are present in `.env.example` and current local `.env`.
- Attack Scenario: Deployment copies `.env.example` or local `.env` into production without hardening.
- Impact: Verbose errors, disabled session encryption, weak local service credentials.
- Affected Files:
  - `.env.example`
  - local `.env`
- Recommendation: Maintain a production environment checklist that enforces `APP_DEBUG=false`, strong secrets, HTTPS cookies, encrypted sessions where appropriate, and production mail/storage credentials.

## Finding 7

- Risk Level: Low
- Vulnerability: Frontend uses `dangerouslySetInnerHTML` for pagination labels.
- Attack Scenario: If pagination labels ever contain attacker-controlled HTML, the frontend could render it unsafely.
- Impact: XSS risk is currently low because Laravel pagination labels are framework-controlled.
- Affected Files:
  - `resources/js/Pages/*/Index.jsx`
- Recommendation: Prefer rendering sanitized/known labels, or centralize pagination rendering so only trusted labels are passed to `dangerouslySetInnerHTML`.

## OWASP Top 10 Coverage

- A01 Broken Access Control: Findings 2 and 4.
- A02 Cryptographic Failures: Finding 6.
- A03 Injection: No exploitable SQL injection found in scanned raw SQL/query usage.
- A04 Insecure Design: Findings 2, 3, and 4.
- A05 Security Misconfiguration: Findings 5 and 6.
- A06 Vulnerable Components: No package upgrade performed; dependency CVE audit was not completed because the task scope forbids package upgrades without approval.
- A07 Authentication Failures: Login throttling exists; public registration remains a risk.
- A08 Software and Data Integrity Failures: No unsafe update/install pipeline reviewed.
- A09 Logging and Monitoring Failures: Activity logging exists and redacts password/remember token.
- A10 SSRF: No direct URL-fetching user input path found in reviewed code.
