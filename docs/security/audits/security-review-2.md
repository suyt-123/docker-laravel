# Second-Round Red Team Review

## Attacker Assumptions

- Attacker may be unauthenticated, newly registered, or authenticated with a low/delegated role.
- Attacker can enumerate numeric IDs through visible links, pagination, or guessed route-model IDs.
- Attacker will target file uploads, role assignment, broad tenant capabilities, and business workflow actions.

## Recheck Results

## Authentication

- Login has rate limiting.
- Password reset uses Laravel broker.
- Public registration remains a medium-risk exposure for an internal system because it allows unauthenticated account creation.

## Authorization and IDOR

- Route-level capability checks are broadly present on business routes.
- `DataScope` protects assigned/own views for projects, dispatches, workers, progress logs, and attendance records.
- User role assignment is the highest-risk escalation path because role sync currently follows user create/update permission instead of a distinct role-assignment permission.
- No `app/Policies` directory was found, so future controller actions lack a framework-enforced object-authorization safety net.

## Admin Routes

- User, role, activity log, and system setting routes are behind `auth` and capability middleware.
- Role create/update requires `security.roles.assign_capabilities.tenant`.
- User create/update does not yet enforce that same capability before syncing roles.

## API

- No `routes/api.php` found.
- No separate token/JWT API surface was identified in this pass.

## Upload

- Progress photos and attendance photos use `image` validation.
- Quotation attachments allow any file type up to 10 MB and store on the public disk. This is exploitable by an authenticated quotation updater.

## Business Logic Abuse

- Quotation workflow state transitions validate basic status preconditions.
- Attendance creation flags anomalies rather than preventing all questionable inputs, which appears intentional but should be monitored.
- Equipment transactions can be created by users with `equipment.transactions.create.tenant`; object-level project/worker/work crew scope should be revisited in a future policy pass.

## Final Red Team Priority

1. High: restrict public quotation attachment uploads.
2. High: require explicit role-assignment capability before syncing user roles.
3. Medium: disable public registration outside controlled onboarding.
4. Medium: add consistent policy/object-level authorization for route-model-bound resources.
