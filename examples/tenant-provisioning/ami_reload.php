<?php
/** Demonstration AMI command client. Separate explicit step, not an automatic installer. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || $argc !== 2 || $argv[1] !== '--execute') {
    fwrite(STDERR, "Usage: php ami_reload.php --execute\n"); exit(1);
}
$host = getenv('PBX_AMI_HOST') ?: '127.0.0.1';
$port = filter_var(getenv('PBX_AMI_PORT') ?: '5038', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>65535]]);
$user = getenv('PBX_AMI_USER'); $pass = getenv('PBX_AMI_PASSWORD');
if (!$port || !$user || !$pass || strpbrk($user, "\r\n") !== false || strpbrk($pass, "\r\n") !== false) {
    fwrite(STDERR, "Set PBX_AMI_USER/PBX_AMI_PASSWORD and valid port; line breaks are not allowed.\n"); exit(1);
}
$fp = @stream_socket_client("tcp://$host:$port", $errno, $errstr, 5);
if (!$fp) { fwrite(STDERR, "AMI connection failed: $errstr\n"); exit(1); }
stream_set_timeout($fp, 5);
function amiAction($fp, array $fields): array {
    foreach ($fields as $key => $value) fwrite($fp, "$key: $value\r\n");
    fwrite($fp, "\r\n");
    $lines = [];
    while (($line = fgets($fp, 8192)) !== false) {
        if (trim($line) === '') break;
        $lines[] = rtrim($line, "\r\n");
    }
    if (feof($fp) || stream_get_meta_data($fp)['timed_out']) throw new RuntimeException('AMI read timed out or closed');
    return $lines;
}
function hasField(array $lines, string $expected): bool {
    return in_array($expected, $lines, true);
}
try {
    fgets($fp, 8192); // AMI greeting
    $login = amiAction($fp, ['Action'=>'Login', 'Username'=>$user, 'Secret'=>$pass, 'Events'=>'off']);
    if (!hasField($login, 'Response: Success')) throw new RuntimeException('AMI login denied');
    $cmd = amiAction($fp, ['Action'=>'Command', 'Command'=>'dialplan reload']);
    if (!hasField($cmd, 'Response: Follows')) throw new RuntimeException('AMI command not acknowledged');
    // AMI Command replies may contain multi-line output until --END COMMAND--.
    $output = [];
    while (($line = fgets($fp, 8192)) !== false) {
        if (trim($line) === '--END COMMAND--') break;
        $output[] = $line;
        if (count($output) > 2000) throw new RuntimeException('Unexpectedly long AMI reply');
    }
    if (feof($fp) || stream_get_meta_data($fp)['timed_out']) throw new RuntimeException('Incomplete AMI command output');
    echo "AMI acknowledged dialplan reload; verify Asterisk CLI / logs before reporting success.\n";
    amiAction($fp, ['Action'=>'Logoff']);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n"); fclose($fp); exit(1);
}
fclose($fp);
