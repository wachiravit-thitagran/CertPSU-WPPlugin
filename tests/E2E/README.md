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
- Agent: any Linux agent with **Docker CLI + accessible Docker daemon**, curl and Git. PHP and Composer run inside the image built from `tests/E2E/Dockerfile`.
- Docker needs permission to build and run images. On first build it downloads base images and Debian packages; the container also downloads Composer packages (unless you have internal mirrors/caches). Jenkins needs outbound HTTPS to GitHub API only if publishing statuses.
- Install Jenkins **Pipeline** and **Credentials Binding** plugins.
- Add a secret-text credential ID `github-certpsu-status-token` containing a
  GitHub token scoped to this repository with **Commit statuses: Read/write**.
  No GitHub Checks plugin is needed.
- **No build parameters**. No `WP_ROOT`, `E2E_COURSE_ID`, `E2E_USER_ID`,
  Docker, WordPress, MySQL, WP-CLI or CertPSU API key.

## Behavior

Jenkins checks out the source, builds a PHP 8.3 Docker image, then runs `composer install` and PHPUnit **inside a temporary container** against the checked-out source. Tests cover:
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

## Troubleshooting

- If `command -v docker` fails, install Docker on the Jenkins **agent** or run the job on a node that already supports containers. This does not require PHP on the host.
- If `docker info` fails, grant the Jenkins agent user access to the Docker daemon through a controlled agent configuration; Docker daemon access is highly privileged. Prefer a dedicated isolated agent rather than granting broad access on shared machines.
- If Docker image download/apt/Composer downloads fail, configure an internal registry and package mirrors. A completely air-gapped agent needs prebuilt/cached images and Composer dependencies.
- Missing `github-certpsu-status-token` is non-blocking; to publish commit statuses, add a secret-text token with Commit statuses read/write. SCM/Branch Source may independently emit GitHub 403 until its own credential is fixed.
