// Disposable WordPress + MySQL E2E with real Tutor LMS plugins and mocked external CertPSU API.
def publishGitHubStatus(String state, String description) {
    // GitHub Checks plugin is not required. This uses the Commit Status API.
    try {
      withCredentials([string(credentialsId: 'github-certpsu-status-token', variable: 'GH_STATUS_TOKEN')]) {
        withEnv(["CERTPSU_STATUS_STATE=${state}", "CERTPSU_STATUS_DESCRIPTION=${description}"]) {
            sh '''
                set +x
                set -eu
                SHA="$(git rev-parse HEAD)"
                printf '{"state":"%s","description":"%s","context":"certpsu/wordpress-mysql-e2e"}' "$CERTPSU_STATUS_STATE" "$CERTPSU_STATUS_DESCRIPTION" |
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
    parameters {
        choice(name: 'CERTPSU_E2E_MODE', choices: ['mock', 'dev'], description: 'mock = simulated CertPSU API; dev = real CertPSU staging API (creates test certificates)')
        string(name: 'CERTPSU_DEV_URL', defaultValue: '', description: 'HTTPS base URL of CertPSU dev/staging API, e.g. https://certpsu-staging.example.org')
        string(name: 'CERTPSU_DEV_TEMPLATE_ID', defaultValue: '', description: 'Existing certificate template ID on CertPSU dev service')
        string(name: 'CERTPSU_E2E_TEST_EMAIL', defaultValue: '', description: 'Controlled test inbox for dev certificates (not a real learner)')
        booleanParam(name: 'CONFIRM_DEV_ISSUANCE', defaultValue: false, description: 'I authorize issuance of test certificates against the specified dev service')
    }
    options {
        timestamps()
        timeout(time: 25, unit: 'MINUTES')
        disableConcurrentBuilds()
        skipDefaultCheckout(true)
    }
    stages {
        stage('Checkout') {
            steps {
                checkout scm
                script { publishGitHubStatus('pending', 'CertPSU WordPress/MySQL E2E running') }
            }
        }
        stage('Validate API target') {
            steps {
                script {
                    if (params.CERTPSU_E2E_MODE == 'dev') {
                        if (!params.CONFIRM_DEV_ISSUANCE) {
                            error('Dev mode requires CONFIRM_DEV_ISSUANCE; it creates external test certificates.')
                        }
                        if (!params.CERTPSU_DEV_URL?.trim() || !params.CERTPSU_DEV_TEMPLATE_ID?.trim() || !params.CERTPSU_E2E_TEST_EMAIL?.trim()) {
                            error('Dev mode needs URL, template ID and test email.')
                        }
                        if (!(params.CERTPSU_DEV_URL ==~ /^https:\/\/[^\s\/]+(?:\/[^\s?#]*)?\/?$/)) {
                            error('CERTPSU_DEV_URL must be HTTPS with no query string, credentials or fragment.')
                        }
                        def host = new java.net.URI(params.CERTPSU_DEV_URL).host
                        if (!host || host.equalsIgnoreCase('cert.psu.ac.th') || host.toLowerCase().endsWith('.cert.psu.ac.th')) {
                            error('Production cert.psu.ac.th host is not allowed for dev E2E.')
                        }
                    }
                }
            }
        }
        stage('Docker prerequisites') {
            steps {
                sh '''
                    set -eu
                    command -v docker >/dev/null
                    docker info >/dev/null
                    docker compose version
                '''
            }
        }
        stage('Build plugin packages') {
            steps {
                sh '''
                    set -eu
                    docker build -f tests/E2E/Dockerfile -t "certpsu-e2e-runtime:${BUILD_NUMBER}" .
                    docker run --rm --volume "$PWD:/workspace" --workdir /workspace \
                        "certpsu-e2e-runtime:${BUILD_NUMBER}" \
                        sh -ec 'composer install --no-interaction --prefer-dist --no-progress && bash bin/build.sh certpsu-connector certpsu-tutorlms'
                    ls dist/certpsu-connector-*.zip dist/certpsu-tutorlms-*.zip
                '''
            }
        }
        stage('Start disposable WordPress + MySQL') {
            steps {
                sh '''
                    set -eu
                    docker compose -f tests/E2E/compose.yaml -p "certpsue2e${BUILD_NUMBER}" up -d db wordpress
                    n=0
                    until docker compose -f tests/E2E/compose.yaml -p "certpsue2e${BUILD_NUMBER}" run --rm cli core version >/dev/null 2>&1; do
                        n=$((n+1))
                        if [ "$n" -ge 24 ]; then
                            echo 'WordPress did not initialize'
                            exit 1
                        fi
                        sleep 5
                    done
                '''
            }
        }
        stage('Install WordPress and plugins') {
            steps {
                sh '''
                    set -eu
                    COMPOSE_PROJECT_NAME="certpsue2e${BUILD_NUMBER}"
                    export COMPOSE_PROJECT_NAME
                    docker compose -f tests/E2E/compose.yaml run --rm cli core install \
                        --url=http://wordpress --title="CertPSU Docker E2E" \
                        --admin_user=e2e_admin --admin_password=e2e_only_password \
                        --admin_email=admin@example.test --skip-email
                    docker compose -f tests/E2E/compose.yaml run --rm cli plugin install tutor --activate
                    CONNECTOR_ZIP="$(ls dist/certpsu-connector-*.zip | head -n 1)"
                    docker compose -f tests/E2E/compose.yaml run --rm cli plugin install "/workspace/$CONNECTOR_ZIP" --activate
                    BRIDGE_ZIP="$(ls dist/certpsu-tutorlms-*.zip | head -n 1)"
                    docker compose -f tests/E2E/compose.yaml run --rm cli plugin install "/workspace/$BRIDGE_ZIP" --activate
                    if [ "$CERTPSU_E2E_MODE" = "mock" ]; then
                        docker compose -f tests/E2E/compose.yaml run --rm --entrypoint sh cli -ec \
                            'mkdir -p /var/www/html/wp-content/mu-plugins &&
                             cp /workspace/tests/E2E/certpsu-api-mock.php /var/www/html/wp-content/mu-plugins/certpsu-api-mock.php'
                    fi
                '''
            }
        }
        stage('Real WordPress/MySQL integration E2E') {
            steps {
                sh '''
                    set -eu
                    COMPOSE_PROJECT_NAME="certpsue2e${BUILD_NUMBER}"
                    export COMPOSE_PROJECT_NAME
                    if [ "$CERTPSU_E2E_MODE" = "mock" ]; then
                        docker compose -f tests/E2E/compose.yaml run --rm \
                            --env CERTPSU_E2E_MODE=mock cli \
                            eval-file /workspace/tests/E2E/wordpress-mysql.php
                    fi
                '''
            }
        }
        stage('CertPSU real dev service E2E') {
            when { expression { params.CERTPSU_E2E_MODE == 'dev' } }
            steps {
                withCredentials([string(credentialsId: 'certpsu-dev-api-key', variable: 'CERTPSU_DEV_API_KEY')]) {
                    withEnv([
                        "CERTPSU_DEV_URL=${params.CERTPSU_DEV_URL}",
                        "CERTPSU_DEV_TEMPLATE_ID=${params.CERTPSU_DEV_TEMPLATE_ID}",
                        "CERTPSU_E2E_TEST_EMAIL=${params.CERTPSU_E2E_TEST_EMAIL}"
                    ]) {
                        sh '''
                            set +x
                            set -eu
                            COMPOSE_PROJECT_NAME="certpsue2e${BUILD_NUMBER}"
                            export COMPOSE_PROJECT_NAME
                            docker compose -f tests/E2E/compose.yaml run --rm \
                                --env CERTPSU_E2E_MODE=dev \
                                --env CERTPSU_DEV_URL \
                                --env CERTPSU_DEV_TEMPLATE_ID \
                                --env CERTPSU_E2E_TEST_EMAIL \
                                --env CERTPSU_DEV_API_KEY \
                                cli eval-file /workspace/tests/E2E/wordpress-mysql.php
                        '''
                    }
                }
            }
        }
    }
    post {
        success {
            script { publishGitHubStatus('success', 'CertPSU WordPress/MySQL E2E passed') }
        }
        unsuccessful {
            script { publishGitHubStatus('failure', 'CertPSU WordPress/MySQL E2E failed') }
        }
        always {
            sh '''
                set +e
                docker compose -f tests/E2E/compose.yaml -p "certpsue2e${BUILD_NUMBER}" logs --no-color --tail=80 > certpsu-e2e-compose.log 2>&1
                docker compose -f tests/E2E/compose.yaml -p "certpsue2e${BUILD_NUMBER}" down -v --remove-orphans
            '''
            archiveArtifacts artifacts: 'certpsu-e2e-compose.log', allowEmptyArchive: true
            echo 'Disposable WordPress/MySQL E2E completed. CertPSU endpoint mode is determined by CERTPSU_E2E_MODE.'
        }
    }
}
