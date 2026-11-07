# CertPSU private staging E2E (Jenkins)

The root `Jenkinsfile` is intended for a Jenkins **Pipeline from SCM** job.
Jenkins must run the job on an agent labeled `certpsu-staging` that can
reach the staging WordPress site, has PHP and WP-CLI installed, and has
filesystem access to the WordPress installation. Nothing calls staging from
GitHub or from a public internet runner.

## One-time staging setup

1. Set `WP_ENVIRONMENT_TYPE=staging` in staging's `wp-config.php`.
2. Install and activate Tutor LMS, CertPSU Connector and CertPSU TutorLMS Bridge
   on staging (the pipeline tests installed plugins; it does not deploy them).
3. Create a **dedicated course**, with slug beginning `e2e-certpsu`. Enable
   CertPSU, provide a valid staging CertPSU template, and enable auto-release.
4. Create a **fresh dedicated learner** with login beginning `e2e-certpsu`,
   a real deliverable test email address, and no prior CertPSU issuance for
   the course. Rotate/create another dedicated learner for each successful run.
5. Ensure WP Cron / Action Scheduler workers can process background jobs on
   staging and that CertPSU API keys and API endpoint point to a **test**
   environment or otherwise permitted test certificate program. The pipeline
   actually creates a CertPSU class/participant and releases a certificate.
6. Give the Jenkins agent access to WordPress files and the same DB configuration
   as staging. Run WP-CLI as the appropriate unprivileged OS user.

Configure a Pipeline from SCM job pointing at this repository and the
`Jenkinsfile` in the repository root. Set the three job parameters:
`WP_ROOT` (absolute filesystem path to WordPress), `E2E_COURSE_ID`,
and `E2E_USER_ID`. Use a Jenkins agent within the private network;
do **not** expose staging publicly or put the CertPSU API key in the
Jenkinsfile. For multiple builds, supply a fresh test learner because
issued certificates must remain idempotent.

## What it checks

- Correct staging environment and fixture guards, active plugins/settings.
- Tutor LMS completion hook queues the real Action Scheduler issuance job.
- The staging scheduler/API actually creates class and participant and
  releases a certificate, with state persisted in learner meta.
- Replaying the issuance handler does not alter an already released record.

The test intentionally does not claim to prove email delivery, PDF rendering,
or certificate link accessibility (the API may omit `certificate_url`).
It fails rather than silently passing when the scheduler or CertPSU API is down.

The pipeline does **not** deploy or modify plugin source on staging, and does
not automatically delete real CertPSU certificate records.

## Report E2E status to GitHub (no GitHub Checks plugin)

In Jenkins > Manage Jenkins > Credentials, add a **Secret text** credential
with ID `github-certpsu-status-token`. Its value should be a fine-grained
GitHub token restricted to `wachiravit-thitagran/CertPSU-WPPlugin`, with
**Commit statuses: Read and write** permission. Configure an expiration and
rotate the token according to your organization policy.

The agent needs HTTPS **outbound** access to `api.github.com` and `curl`.
There is no inbound GitHub -> staging requirement. The Jenkinsfile reports
`certpsu/staging-e2e` as pending after checkout, then success or failure
when the build finishes, using the GitHub Commit Status API. Jenkins must
have the Credentials Binding plugin; the GitHub Checks plugin is **not**
required. If reporting fails, inspect the Jenkins console and token
permissions. This status appears on the SHA Jenkins actually checked out.

For the Jenkins Pipeline-from-SCM job, use branch `*/main` and script
path `Jenkinsfile`. Jenkins is not automatically triggered by GitHub:
configure an appropriate SCM polling schedule or build manually. The job
must have fresh test fixtures for each successful E2E run.
