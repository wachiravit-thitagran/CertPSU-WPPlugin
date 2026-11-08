# Jenkins E2E: disposable WordPress + MySQL

The repository root `Jenkinsfile` provisions an isolated Docker Compose
stack that runs **real WordPress, MySQL 8.0, Tutor LMS, CertPSU Connector
and CertPSU TutorLMS Bridge**. Only external CertPSU API responses are
mocked using a test-only WordPress MU plugin and a reserved `.invalid`
hostname. Nothing talks to the private staging site or production CertPSU.

## Jenkins configuration

Configure a multibranch or Pipeline from SCM job:
- Repository: `https://github.com/wachiravit-thitagran/CertPSU-WPPlugin.git`
- Branch: `main`; script path: `Jenkinsfile`
- Jenkins agent: Linux with Docker Engine, Compose v2 plugin, Git and access
  to the Docker daemon. PHP, MySQL, WP-CLI and Composer are **not**
  required on the host.
- Jenkins must be able to pull `mysql:8.0`, `wordpress:php8.3-apache`,
  `wordpress:cli-php8.3`, `php:8.3-cli-bookworm`, and `composer:2`,
  and download WordPress.org Tutor LMS, apt and Composer dependencies.
  Use internal registries/package mirrors in network-isolated deployments.
- Optional GitHub Commit Status: add Jenkins secret-text credential
  `github-certpsu-status-token` with `Commit statuses: Read/write`.
  Status context is `certpsu/wordpress-mysql-e2e` and status reporting is
  best-effort (missing credentials do not prevent E2E).
- No `WP_ROOT`, `E2E_COURSE_ID`, `E2E_USER_ID`, API keys or other
  build parameters are needed.

## Steps

1. Build a disposable PHP/Composer image and create installable plugin ZIPs.
2. Start isolated WordPress + MySQL containers.
3. Install WordPress with an E2E-only admin and activate Tutor LMS and
   both CertPSU plugins.
4. Copy `certpsu-api-mock.php` into WordPress's MU plugins directory.
5. Use WP-CLI to create a course and learner, fire the real Tutor LMS
   completion hook and verify a queued Action Scheduler task.
6. Run the real Action Scheduler runner and verify class/participant/release
   state persisted to MySQL-backed WordPress user metadata.
7. Replay the issuance handler to assert idempotency and verify the mock API
   saw exactly three issuance requests.
8. On every build result, collect Compose logs and run `down -v` to remove
   disposable database and WordPress volumes.

All test users, passwords, emails, and CertPSU IDs are synthetic.

## Limitations

This is a **container-based integration E2E** for WordPress and MySQL,
not proof of connectivity to `cert.psu.ac.th`, email/PDF delivery or
production approval workflows. The test fires the Tutor completion hook;
it does not simulate browser-based lesson/quiz progression.

If `docker info` fails, fix access on a **dedicated isolated Jenkins agent**.
Access to the Docker daemon is highly privileged. If image or package
downloads fail, use approved mirrors or cached images.
