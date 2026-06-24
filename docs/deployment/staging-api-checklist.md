# Staging API And Deployment Verification Checklist

Use this before promoting API or storage-related work beyond staging.

## API Smoke Test

- Create a Sanctum personal access token with the minimum required abilities.
- Confirm a request without token returns `401`.
- Confirm a token without RBAC capability returns `403`.
- Confirm a token without matching token ability returns `403`.
- Confirm a valid token can call the intended `/api/v1` endpoint.
- Confirm validation errors use the documented envelope.
- Confirm direct ID access is rejected for out-of-scope project/worker/assigned data.

## R2 And Private Files

- Configure production-like S3/R2 endpoint, bucket, key, secret, and region behavior.
- Upload a quotation attachment.
- Upload a progress or attendance photo.
- Confirm private files cannot be fetched directly without an authorized route.
- Confirm signed or authorized download URLs expire as expected.
- Confirm file deletion/replacement behavior matches the app workflow.

## PDF Runtime

- Confirm Chromium/Browsershot path exists in the runtime.
- Generate a quotation PDF.
- Generate an invoice PDF.
- Confirm PDF output does not expose private file URLs.
- Confirm queue or sync rendering timeout is acceptable.

## Queue And Cache

- Confirm queue worker is running.
- Confirm workflow notifications dispatch.
- Confirm failed jobs are visible.
- Confirm Redis/cache settings match the deployment plan if Redis is used.

## Cloudflare Proxy/WAF

- Confirm HTTPS, proxy, and DNS are correct.
- Confirm API routes are not cached unless explicitly designed.
- Confirm upload/PDF routes are not blocked by WAF rules.
- Confirm rate limits do not block normal staging use.
- Confirm allowed CORS origins are narrow.

## Staging Sign-off

- Run focused feature tests for changed modules.
- Run a browser smoke test for affected Inertia pages.
- Check Laravel logs for 4xx/5xx patterns.
- Check API audit/activity logs for redaction.
- Record unresolved issues before production promotion.
