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
                    docker compose -f tests/E2E/compose.yaml run --rm cli plugin install /workspace/dist/certpsu-connector-*.zip --activate
                    docker compose -f tests/E2E/compose.yaml run --rm cli plugin install /workspace/dist/certpsu-tutorlms-*.zip --activate
                    docker compose -f tests/E2E/compose.yaml run --rm --entrypoint sh cli -ec \
                        'mkdir -p /var/www/html/wp-content/mu-plugins &&
                         cp /workspace/tests/E2E/certpsu-api-mock.php /var/www/html/wp-content/mu-plugins/certpsu-api-mock.php'
                '''
            }
        }
        stage('Real WordPress/MySQL integration E2E') {
            steps {
                sh '''
                    set -eu
                    COMPOSE_PROJECT_NAME="certpsue2e${BUILD_NUMBER}"
                    export COMPOSE_PROJECT_NAME
                    docker compose -f tests/E2E/compose.yaml run --rm cli eval-file /workspace/tests/E2E/wordpress-mysql.php
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
            sh '''
                set +e
                docker compose -f tests/E2E/compose.yaml -p "certpsue2e${BUILD_NUMBER}" logs --no-color --tail=80 > certpsu-e2e-compose.log 2>&1
                docker compose -f tests/E2E/compose.yaml -p "certpsue2e${BUILD_NUMBER}" down -v --remove-orphans
            '''
            archiveArtifacts artifacts: 'certpsu-e2e-compose.log', allowEmptyArchive: true
            echo 'WordPress, MySQL and Tutor LMS ran in disposable containers. CertPSU API was mocked.'
        }
    }
}
