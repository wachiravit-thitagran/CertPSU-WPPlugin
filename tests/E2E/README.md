# Jenkins E2E: disposable WordPress + MySQL

The repository root `Jenkinsfile` provisions an isolated Docker Compose
stack that runs **real WordPress, MySQL 8.0, Tutor LMS, CertPSU Connector
and CertPSU TutorLMS Bridge**. The external CertPSU API is selectable: default `mock` uses a test-only WordPress MU plugin and a reserved `.invalid` hostname, while `dev` contacts a configured HTTPS CertPSU staging service. Production `cert.psu.ac.th` is explicitly blocked.

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
- No `WP_ROOT`, `E2E_COURSE_ID`, or `E2E_USER_ID` is needed. In `mock` mode, no additional values are required.
- For `dev` mode, first create a **Secret text** Jenkins Credential with ID `certpsu-dev-api-key` containing the dedicated staging API key. The token is never stored in Git or a build parameter.

### Build with Parameters

| Parameter | Default | Purpose |
| --- | --- | --- |
| `CERTPSU_E2E_MODE` | `mock` | `mock` = fake API; `dev` = actual staging CertPSU HTTP API |
| `CERTPSU_DEV_URL` | empty | HTTPS staging API base URL, including port if needed; **no `/v2` suffix** |
| `CERTPSU_DEV_TEMPLATE_ID` | empty | Existing certificate template ID in the selected staging service |
| `CERTPSU_E2E_TEST_EMAIL` | empty | Controlled inbox for generated test certificates |
| `CONFIRM_DEV_ISSUANCE` | false | Must be enabled for `dev` mode; this creates real staging data |

When switching to `dev`, the Jenkins agent's Docker networking must be able to reach the staging API URL. The test creates a new WordPress learner for every run and sends the real staging service three calls: create class, add participant, release participant. It checks that the returned class/participant IDs and issuance status were persisted to WordPress. **It does not delete remote certificates, guarantee PDF/email delivery, or verify endorser approval**. Do not use production credentials. The default remains `mock` for automatic builds.

## Steps

1. Build a disposable PHP/Composer image and create installable plugin ZIPs.
2. Start isolated WordPress + MySQL containers.
3. Install WordPress with an E2E-only admin and activate Tutor LMS and
   both CertPSU plugins.
4. In `mock` mode only, copy `certpsu-api-mock.php` into WordPress's MU plugins directory. In `dev`, connect via the configured HTTPS staging API URL and Jenkins secret.
5. Use WP-CLI to create a course and learner, fire the real Tutor LMS
   completion hook and verify a queued Action Scheduler task.
6. Run the real Action Scheduler runner and verify class/participant/release
   state persisted to MySQL-backed WordPress user metadata.
7. Replay the issuance handler to assert the persisted result remains unchanged; mock mode additionally verifies exactly three simulated API calls.
8. On every build result, collect Compose logs and run `down -v` to remove
   disposable database and WordPress volumes.

All test users, passwords, emails, and CertPSU IDs are synthetic.

## Limitations

This is a **container-based integration E2E** for WordPress and MySQL,
not proof of connectivity to the production `cert.psu.ac.th`, email/PDF delivery or
production approval workflows. `dev` mode verifies integration with the configured staging HTTP endpoint only. The test fires the Tutor completion hook;
it does not simulate browser-based lesson/quiz progression.

If `docker info` fails, fix access on a **dedicated isolated Jenkins agent**.
Access to the Docker daemon is highly privileged. If image or package
downloads fail, use approved mirrors or cached images.
