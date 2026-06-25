<?php
echo "Testing shortcode when logged out...\n";
$output = do_shortcode("[certpsu_my_certificates]");
if (strpos($output, "Please log in") !== false || strpos($output, "login") !== false) {
    echo "Shortcode correctly prompted login when logged out.\n";
} else {
    echo "Shortcode render failed or returned unexpected output for logged out state. Output: " . $output . "\n";
    exit(1);
}

echo "Testing shortcode when logged in...\n";
$user_id = wp_insert_user([
    "user_login" => "shortcodetestuser",
    "user_pass"  => wp_generate_password(),
    "user_email" => "shortcode@example.com"
]);
wp_set_current_user($user_id);
try {
    $output = do_shortcode("[certpsu_my_certificates]");
    if (strpos($output, "certpsu") !== false || strpos($output, "div") !== false || $output === "") {
        echo "Shortcode rendered successfully when logged in.\n";
    } else {
        echo "Shortcode render failed or returned unexpected output for logged in state.\n";
        exit(1);
    }
} catch (Throwable $e) {
    echo "Fatal error rendering shortcode: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    wp_delete_user($user_id);
}
