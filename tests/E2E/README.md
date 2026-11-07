# Offline Jenkins CertPSU E2E simulation

The root `Jenkinsfile` runs `tests/E2E/MockWordPressCertPSUE2ETest.php`
on a regular Jenkins agent. This test uses existing WordPress-function stubs
from `tests/bootstrap.php` and automatically creates mock data for a
Tutor LMS course, learner, postmeta/usermeta, Action Scheduler queue and
CertPSU API responses. There are **no staging or production connections**.

## Jenkins job setup

- Create a **Pipeline from SCM** job with repository
  `https://github.com/wachiravit-thitagran/CertPSU-WPPlugin.git`
- Branch: `*/main`; script path: `Jenkinsfile`
- Agent: any agent with **PHP >= 8.2**, Composer, curl and Git
- The agent must have access to Composer package sources (or a populated
  Composer cache/mirror). Jenkins also needs outbound HTTPS to GitHub API
  if publishing statuses.
- Install Jenkins **Pipeline** and **Credentials Binding** plugins.
- Add a secret-text credential ID `github-certpsu-status-token` containing a
  GitHub token scoped to this repository with **Commit statuses: Read/write**.
  No GitHub Checks plugin is needed.
- **No build parameters**. No `WP_ROOT`, `E2E_COURSE_ID`, `E2E_USER_ID`,
  Docker, WordPress, MySQL, WP-CLI or CertPSU API key.

## Behavior

Jenkins checks out the source, runs `composer install`, and runs the
isolated PHPUnit E2E simulation. Tests cover:
1. Enabled course-completion event enqueues the expected issuance job.
2. Fake participant addition and certificate release populate learner state.
3. Repeated handler processing is idempotent and does not call the API again.
4. Disabled course does not enqueue issuance.
5. API failure schedules retry and does not mark a certificate released.

The GitHub Commit Status context is `certpsu/mock-e2e`, deliberately distinct
from the real staging E2E status `certpsu/staging-e2e`.
A JUnit XML artifact is archived for diagnostics; no certificate is issued.

## Scope and limitations

This is a **mocked integration-flow test**, not an E2E test against a real
WordPress runtime, database, background worker or the CertPSU service.
Passing it proves the orchestrated plugin logic works against mocks; it
**does not establish production readiness or real API compatibility**.

The separate legacy `tests/e2e/staging.php` script remains available for
a future explicitly configured staging job, but the root Jenkinsfile
does not invoke it.
