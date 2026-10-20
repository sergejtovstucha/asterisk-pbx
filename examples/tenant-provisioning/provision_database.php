<?php
/**
 * Example only. Stages tenant contexts and sample routes in MariaDB.
 * Needs an existing company ID; does NOT create a company, update filesystem, or contact AMI.
 * CLI requires --execute explicitly. Run on disposable test DB only.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || $argc !== 4 || $argv[1] !== '--execute') {
    fwrite(STDERR, "Usage: php provision_database.php --execute COMPANY_ID PREFIX\n");
    exit(1);
}
$companyId = filter_var($argv[2], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
$prefix = $argv[3];
if ($companyId === false || !preg_match('/^[0-9]{4}$/D', $prefix)) {
    fwrite(STDERR, "Invalid company ID or four-digit prefix.\n"); exit(1);
}
foreach (['PBX_DB_DSN', 'PBX_DB_USER', 'PBX_DB_PASSWORD'] as $env) {
    if (getenv($env) === false) { fwrite(STDERR, "Missing environment variable $env\n"); exit(1); }
}
$contexts = ['incoming', 'outgoing', 'blf', 'from_trunk', 'offpeak', 'ivr', 'queue', 'queueout'];
$routes = [
    'incoming' => [['_X!', 1, 'Set', 'CALLERID(num)=${CALLERID(num)}'], ['_X!', 2, 'Goto', 'landing_call,${EXTEN},1000']],
    'outgoing' => [['_+X!', 1, 'Goto', 'outgoing,00${EXTEN:1},1'], ['_X!', 1, 'Goto', 'outgoing,${EXTEN},1']],
    'ivr' => [['t', 1, 'Hangup', ''], ['i', 1, 'Hangup', '']],
    'queue' => [['_X!', 1, 'Goto', 'queue,${EXTEN},1']],
];
try {
    $db = new PDO(getenv('PBX_DB_DSN'), getenv('PBX_DB_USER'), getenv('PBX_DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $db->beginTransaction();
    $check = $db->prepare('SELECT id FROM dialplan WHERE company_id = ? AND context = ? LIMIT 1');
    $insertContext = $db->prepare('INSERT INTO dialplan (company_id, context) VALUES (?, ?)');
    $insertRoute = $db->prepare('INSERT INTO extensions (company_id, context, exten, priority, app, appdata) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($contexts as $type) {
        $context = "$type-$prefix";
        $check->execute([$companyId, $context]);
        if ($check->fetchColumn() !== false) {
            throw new RuntimeException("Context already exists: $context; no changes committed");
        }
        $insertContext->execute([$companyId, $context]);
        $id = (int)$db->lastInsertId();
        foreach ($routes[$type] ?? [] as [$exten, $priority, $app, $appdata]) {
            $insertRoute->execute([$companyId, $id, $exten, $priority, $app, $appdata]);
        }
    }
    $db->commit();
    echo "Inserted example contexts/routes for prefix $prefix (company ID $companyId).\n";
    echo "DB transaction committed. File activation and AMI reload NOT performed.\n";
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    fwrite(STDERR, "Provisioning failed; DB transaction rolled back if active: " . $e->getMessage() . "\n");
    exit(1);
}
