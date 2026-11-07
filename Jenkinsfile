// Offline CertPSU E2E simulation. No WordPress, DB, private staging or real CertPSU service.
def publishGitHubStatus(String state, String description) {
    // GitHub Checks plugin is not required. This uses the Commit Status API.
    try {
      withCredentials([string(credentialsId: 'github-certpsu-status-token', variable: 'GH_STATUS_TOKEN')]) {
        withEnv(["CERTPSU_STATUS_STATE=${state}", "CERTPSU_STATUS_DESCRIPTION=${description}"]) {
            sh '''
                set +x
                set -eu
                SHA="$(git rev-parse HEAD)"
                printf '{"state":"%s","description":"%s","context":"certpsu/mock-e2e"}' "$CERTPSU_STATUS_STATE" "$CERTPSU_STATUS_DESCRIPTION" |
                    curl --fail --silent --show-error --retry 2 --request POST \
                        --header "Accept: application/vnd.github+json" \
                        --header "Authorization: Bearer $GH_STATUS_TOKEN" \
                        --header "X-GitHub-Api-Version: 2022-11-28" \
                        --header "Content-Type: application/json" \
                        --data-binary @- \
                        "https://api.github.com/repos/wachiravit-thitagran/CertPSU-WPPlugin/statuses/$SHA" >/dev/null
            '''
        }
      }
    } catch (Exception error) {
        echo "WARNING: Could not publish GitHub commit status (non-blocking): ${error.getClass().getSimpleName()}"
    }
}

pipeline {
    agent any
    options {
        timestamps()
        timeout(time: 15, unit: 'MINUTES')
        disableConcurrentBuilds()
        skipDefaultCheckout(true)
    }
    stages {
        stage('Checkout') {
            steps {
                checkout scm
                script { publishGitHubStatus('pending', 'CertPSU offline mock E2E running') }
            }
        }
        stage('Prepare isolated mock runtime') {
            steps {
                sh '''
                    set -eu
                    command -v php
                    command -v composer
                    command -v curl
                    php -v
                    composer install --no-interaction --prefer-dist --no-progress
                    test -f vendor/autoload.php
                    test -x vendor/bin/phpunit
                '''
            }
        }
        stage('WordPress + Tutor LMS + CertPSU simulated E2E') {
            steps {
                sh '''
                    set -eu
                    mkdir -p build/test-results
                    vendor/bin/phpunit --configuration phpunit.xml.dist \
                        --log-junit build/test-results/certpsu-mock-e2e.xml \
                        tests/E2E/MockWordPressCertPSUE2ETest.php
                '''
            }
        }
    }
    post {
        success {
            script { publishGitHubStatus('success', 'CertPSU offline mock E2E passed') }
        }
        unsuccessful {
            script { publishGitHubStatus('failure', 'CertPSU offline mock E2E failed') }
        }
        always {
            archiveArtifacts artifacts: 'build/test-results/*.xml', allowEmptyArchive: true
            echo 'All fixtures are in memory; no staging WordPress or CertPSU API was contacted.'
        }
    }
}
