// Run this pipeline only on a Jenkins agent that has private-network access
// to the staging WordPress installation and WP-CLI installed.
pipeline {
    agent { label 'certpsu-staging' }
    options {
        timestamps()
        timeout(time: 12, unit: 'MINUTES')
        disableConcurrentBuilds()
        skipDefaultCheckout(true)
    }
    parameters {
        string(name: 'WP_ROOT', defaultValue: '/var/www/html', description: 'WordPress root on the private staging Jenkins agent')
        string(name: 'E2E_COURSE_ID', defaultValue: '', description: 'ID of a dedicated staging test course (slug must start e2e-certpsu)')
        string(name: 'E2E_USER_ID', defaultValue: '', description: 'ID of a fresh dedicated staging test learner (login must start e2e-certpsu)')
    }
    environment {
        WP_CLI_ALLOW_ROOT = '0'
    }
    stages {
        stage('Checkout') {
            steps { checkout scm }
        }
        stage('Preflight') {
            steps {
                sh '''
                    set -eu
                    command -v wp >/dev/null
                    test -n "$E2E_COURSE_ID" && test -n "$E2E_USER_ID"
                    case "$E2E_COURSE_ID:$E2E_USER_ID" in
                        *[!0-9:]*|'':*) echo "Positive numeric fixture IDs required"; exit 2;;
                    esac
                    test -f "$WP_ROOT/wp-config.php"
                    E2E_PHASE=preflight wp --path="$WP_ROOT" eval-file "$WORKSPACE/tests/e2e/staging.php"
                '''
            }
        }
        stage('Trigger real completion event') {
            steps {
                sh '''
                    set -eu
                    E2E_PHASE=trigger wp --path="$WP_ROOT" eval-file "$WORKSPACE/tests/e2e/staging.php"
                '''
            }
        }
        stage('Wait for Action Scheduler and CertPSU API') {
            steps {
                // No direct public ingress to staging is needed. Its own scheduler
                // must process the queued issuance, as it would in production.
                sh '''
                    set -eu
                    n=0
                    while [ "$n" -lt 24 ]; do
                        if E2E_PHASE=verify wp --path="$WP_ROOT" eval-file "$WORKSPACE/tests/e2e/staging.php"; then
                            exit 0
                        fi
                        n=$((n + 1))
                        sleep 10
                    done
                    echo "Certificate release was not observed within 240 seconds"
                    exit 1
                '''
            }
        }
        stage('Idempotency') {
            steps {
                sh '''
                    set -eu
                    E2E_PHASE=repeat wp --path="$WP_ROOT" eval-file "$WORKSPACE/tests/e2e/staging.php"
                '''
            }
        }
    }
    post {
        always { echo 'CertPSU staging E2E completed. Inspect Jenkins console for the verification outcome.' }
    }
}
