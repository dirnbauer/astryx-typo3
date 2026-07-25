<?php

/**
 * Apply the additive schema changes for this extension's content elements.
 *
 * Why this exists rather than `extension:setup`
 * ---------------------------------------------
 * tt_content in this installation carries well over five hundred columns —
 * Desiderio's 244 elements, Innesto's, and every plugin's. InnoDB checks, at
 * ALTER time, whether a row could exceed half a page (8126 bytes) and counts
 * every variable-length column's worst case toward that. The table is past
 * that line, so MariaDB refuses to add ANY column, of any type, including a
 * two-byte integer. `extension:setup` reports success and silently applies
 * nothing.
 *
 * With ROW_FORMAT=DYNAMIC — which this table already uses — that check is
 * conservative: variable-length columns overflow to off-page storage at
 * runtime, so the real rows fit comfortably. Turning innodb_strict_mode off
 * for the duration of the migration downgrades the check to a warning, which
 * is MariaDB's documented behaviour for exactly this case.
 *
 * The trade-off, stated plainly: a single record that filled several hundred
 * long text columns at once could still fail to write. A content element uses
 * the handful of fields its own type declares and leaves the rest empty, so
 * this does not happen in practice — but it is the reason the setting is not
 * simply turned off permanently.
 *
 * Only additive statements are applied. Nothing is altered, nothing dropped.
 *
 *   ddev exec php packages/desiderio_grande/Build/Scripts/apply-schema.php
 *   ddev exec php packages/desiderio_grande/Build/Scripts/apply-schema.php --apply
 */

use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Schema\SchemaMigrator;
use TYPO3\CMS\Core\Database\Schema\SqlReader;
use TYPO3\CMS\Core\Utility\GeneralUtility;

require '/var/www/html/vendor/autoload.php';
SystemEnvironmentBuilder::run(0, SystemEnvironmentBuilder::REQUESTTYPE_CLI);
$container = Bootstrap::init(new \Composer\Autoload\ClassLoader());

/** Additive only: never "change", which would rewrite existing columns. */
const SAFE_GROUPS = ['create_table', 'add'];

$apply = in_array('--apply', $argv, true);

$sqlReader = $container->get(SqlReader::class);
$statements = $sqlReader->getCreateTableStatementArray($sqlReader->getTablesDefinitionString());
$migrator = $container->get(SchemaMigrator::class);

$selected = [];
$counts = [];
foreach ($migrator->getUpdateSuggestions($statements) as $groups) {
    foreach ($groups as $type => $sqlList) {
        $counts[$type] = ($counts[$type] ?? 0) + count($sqlList);
        if (!in_array($type, SAFE_GROUPS, true)) {
            continue;
        }
        foreach ($sqlList as $hash => $sql) {
            $selected[$hash] = $sql;
        }
    }
}

echo 'pending by type: ', json_encode($counts), "\n";
echo 'additive statements: ', count($selected), "\n";

if ($selected === []) {
    echo "Schema is up to date.\n";
    exit(0);
}

if (!$apply) {
    foreach (array_slice($selected, 0, 5) as $sql) {
        echo '  ', substr($sql, 0, 110), "\n";
    }
    echo "\nRun with --apply to execute.\n";
    exit(0);
}

$connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tt_content');
$strict = $connection->executeQuery('SELECT @@innodb_strict_mode')->fetchOne();
$connection->executeStatement('SET SESSION innodb_strict_mode = OFF');

try {
    $result = $migrator->migrate($statements, $selected);
} finally {
    $connection->executeStatement('SET SESSION innodb_strict_mode = ' . ((int)$strict === 1 ? 'ON' : 'OFF'));
}

$failed = array_filter($result);
foreach (array_slice($failed, 0, 3) as $error) {
    echo 'FAILED: ', substr((string)$error, 0, 180), "\n";
}
printf("applied %d statement(s), %d failed\n", count($selected) - count($failed), count($failed));
exit($failed === [] ? 0 : 1);
