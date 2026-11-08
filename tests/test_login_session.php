<?php
/**
 * Integration test: php tests/test_login_session.php [login-url]
 * Requires the application and its database to be running.
 */
$url = isset($argv[1]) ? $argv[1] : 'http://localhost/simpleton/index.php/auth/login';

function request_login($url, $cookie = NULL, $post = NULL)
{
    $headers = array();
    if ($cookie !== NULL) {
        $headers[] = 'Cookie: '.$cookie;
    }
    if ($post !== NULL) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    $options = array(
        'method' => $post === NULL ? 'GET' : 'POST',
        'header' => implode("\r\n", $headers),
        'ignore_errors' => TRUE,
        'follow_location' => 0,
        'timeout' => 10,
    );
    if ($post !== NULL) {
        $options['content'] = http_build_query($post);
    }
    $body = file_get_contents($url, FALSE, stream_context_create(array('http' => $options)));
    if ($body === FALSE || !isset($http_response_header)) {
        throw new RuntimeException('Request to login page failed.');
    }
    return array($body, $http_response_header);
}

function session_cookie($headers)
{
    foreach ($headers as $header) {
        if (preg_match('/^Set-Cookie:\s*(ci_session=([0-9a-f]{40}));/i', $header, $matches)) {
            return $matches[1];
        }
    }
    return NULL;
}

try {
    list($body, $headers) = request_login($url);
    $cookie = session_cookie($headers);
    if ($cookie === NULL) {
        throw new RuntimeException('Login page did not issue a valid session cookie.');
    }
    list($body, $headers) = request_login($url, $cookie);
    $next_cookie = session_cookie($headers);
    if ($next_cookie !== NULL && $next_cookie !== $cookie) {
        throw new RuntimeException('Session cookie changed between requests.');
    }

    request_login($url, $cookie, array(
        'identity' => 'nonexistent-session-test',
        'password' => 'invalid-session-test',
    ));
    list($body) = request_login($url, $cookie);
    if (strpos($body, 'login-alert') === FALSE) {
        throw new RuntimeException('Login error message was lost after the redirect.');
    }
    echo "OK: session cookie and login error survive subsequent requests.\n";
} catch (Exception $exception) {
    fwrite(STDERR, 'FAIL: '.$exception->getMessage()."\n");
    exit(1);
}
