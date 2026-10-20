<?php
/**
 * Educational, standalone example adapted from a legacy PHPRunner provisioning workflow.
 * NOT a production provisioning service. Does not modify the database or reload Asterisk.
 * Usage: php generate_tenant.php 1001 /path/to/staging
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || $argc !== 3) {
    fwrite(STDERR, "Usage: php generate_tenant.php PREFIX STAGING_DIRECTORY\n");
    exit(1);
}
$prefix = $argv[1];
if (!preg_match('/^[0-9]{4}$/D', $prefix)) {
    fwrite(STDERR, "Prefix must contain exactly four digits.\n");
    exit(1);
}
$base = realpath($argv[2]);
if ($base === false || !is_dir($base) || is_link($argv[2]) || !is_writable($base)) {
    fwrite(STDERR, "Staging directory must already exist and be writable.\n");
    exit(1);
}
$contexts = ['outgoing','incoming','from_trunk','queue','queueout','offpeak','ivr','local','national','international'];
$lines = ["; Generated example for tenant $prefix — review before using", "[blf-$prefix]", "exten => _X.,hint,PJSIP/$prefix*\${EXTEN}", ''];
foreach ($contexts as $context) {
    $lines[] = "[$context-$prefix]";
    $lines[] = 'switch => Realtime/${CONTEXT}@extensions';
    if ($context === 'outgoing') $lines[] = "include => blf-$prefix";
    $lines[] = '';
}
$target = $base . DIRECTORY_SEPARATOR . "$prefix.conf";
if (file_exists($target) || is_link($target)) {
    fwrite(STDERR, "Target already exists; refusing to overwrite.\n");
    exit(1);
}
$tmp = tempnam($base, '.tenant-');
if ($tmp === false) throw new RuntimeException('Unable to create staging file');
try {
    if (file_put_contents($tmp, implode("\n", $lines) . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Write failed');
    }
    chmod($tmp, 0640);
    if (!rename($tmp, $target)) throw new RuntimeException('Move failed');
} finally {
    if (is_file($tmp)) unlink($tmp);
}
echo "Generated $target\n";
