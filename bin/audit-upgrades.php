#!/usr/bin/env php
<?php
declare(strict_types=1);


date_default_timezone_set('UTC');

$rootDir = dirname(__DIR__);

$results = [];
$detectedRoutes = [];
$warnings = [];
$testSummary = null;

$results['A.Repository'] = checkRepository($rootDir);
$results['B.REST'] = checkRestEndpoints($rootDir, $detectedRoutes);
$results['C.Admin Side-Effects'] = checkAdminHandlers($rootDir);
$results['D.Notifications'] = checkNotifications($rootDir);
$results['E.Dashboard Contract'] = checkDashboardContract($rootDir);
$results['F.Builders'] = checkBuilders($rootDir);
$results['G.Security & A11y'] = checkSecurityAndAccessibility($rootDir, $warnings);

$phpunitResult = runComposerTestsIfAvailable($rootDir, $warnings);
if ($phpunitResult !== null) {
    $testSummary = $phpunitResult['lines'];
}

foreach ($results as $section => $data) {
    foreach ($data['notes'] as $note) {
        if (str_starts_with($note, 'TODO:')) {
            $warnings[] = $note;
        }
    }
}

$timestamp = gmdate('Y-m-d H:i:s') . ' UTC';

$hasFailures = false;
foreach ($results as $data) {
    if (!$data['pass']) {
        $hasFailures = true;
        break;
    }
}

$testsFailed = $phpunitResult !== null && (int) $phpunitResult['exitCode'] !== 0;

$summaryLines = [];
$summaryLines[] = sprintf('ArtPulse Upgrade Audit – %s', $timestamp);
foreach ($results as $section => $data) {
    $statusIcon = $data['pass'] ? '✅' : '❌';
    $detail = implode('; ', $data['notes']);
    $summaryLines[] = sprintf('%-24s %s  (%s)', $section, $statusIcon, $detail);
}

if ($phpunitResult === null) {
    $summaryLines[] = 'PHPUnit: not executed (binary not available)';
} else {
    $summaryLines[] = sprintf('PHPUnit: %s (exit code %d)', $phpunitResult['exitCode'] === 0 ? 'completed' : 'completed with issues', $phpunitResult['exitCode']);
    if ($testsFailed) {
        $summaryLines[] = 'PHPUnit failures detected during audit run.';
    }
}

$summaryLines[] = sprintf('Audit result: %s', ($hasFailures || $testsFailed) ? 'FAIL' : 'PASS');

$reportPath = $rootDir . '/reports/upgrade_audit.md';
if (!is_dir(dirname($reportPath))) {
    mkdir(dirname($reportPath), 0777, true);
}

$markdown = [];
$markdown[] = '# ArtPulse Upgrade Audit Report';
$markdown[] = '';
$markdown[] = sprintf('*Generated: %s*', $timestamp);
$markdown[] = sprintf('*Audit result: %s*', ($hasFailures || $testsFailed) ? 'FAIL' : 'PASS');
$markdown[] = '';
$markdown[] = '| Checklist Item | Status | Details |';
$markdown[] = '| --- | --- | --- |';
foreach ($results as $section => $data) {
    $statusIcon = $data['pass'] ? '✅' : '❌';
    $details = $data['notes'];
    if (empty($details)) {
        $details = ['—'];
    }
    $markdown[] = sprintf('| %s | %s | %s |', $section, $statusIcon, escapeTableCell(implode('<br>', $details)));
}
$markdown[] = '';

if (!empty($detectedRoutes)) {
    $markdown[] = '## Detected Routes';
    $markdown[] = '';
    foreach ($detectedRoutes as $route) {
        $markdown[] = sprintf('- `%s`', $route);
    }
    $markdown[] = '';
}

$markdown[] = '## Sample Requests';
$markdown[] = '';
$markdown[] = '```bash';
$markdown[] = 'curl -X POST "$WP/site/wp-json/artpulse/v1/upgrade-reviews" \\';
$markdown[] = '  -H "X-WP-Nonce: <nonce>" \\';
$markdown[] = '  -H "Content-Type: application/json" \\';
$markdown[] = "  -d '{\"type\":\"artist\",\"note\":\"Please upgrade me\"}'";
$markdown[] = '```';
$markdown[] = '';
$markdown[] = '```bash';
$markdown[] = 'curl "$WP/site/wp-json/artpulse/v1/upgrade-reviews?mine=1" \\';
$markdown[] = '  -H "X-WP-Nonce: <nonce>"';
$markdown[] = '```';
$markdown[] = '';

if (!empty($warnings)) {
    $markdown[] = '## Warnings & TODOs';
    $markdown[] = '';
    foreach (array_unique($warnings) as $warning) {
        $markdown[] = sprintf('- %s', $warning);
    }
    $markdown[] = '';
}

if ($testSummary !== null) {
    $markdown[] = '## PHPUnit Summary';
    $markdown[] = '';
    foreach ($testSummary as $line) {
        $markdown[] = $line;
    }
    $markdown[] = '';
}

file_put_contents($reportPath, implode(PHP_EOL, $markdown) . PHP_EOL);

$summaryLines[] = sprintf('Report written to: %s', relativePath($rootDir, $reportPath));

echo implode(PHP_EOL, $summaryLines) . PHP_EOL;

$exitCode = ($hasFailures || $testsFailed) ? 1 : 0;

exit($exitCode);

function checkRepository(string $rootDir): array
{
    $notes = [];
    $pass = true;
    $path = $rootDir . '/src/Core/UpgradeReviewRepository.php';

    if (!file_exists($path)) {
        $notes[] = 'File missing';
        return ['pass' => false, 'notes' => $notes];
    }

    $contents = file_get_contents($path) ?: '';

    if (preg_match('/function\s+find_pending\s*\(\s*int\s+\$user_id\s*,\s*string\s+\$type\s*\)\s*:\s*\??int/i', $contents)) {
        $notes[] = 'find_pending signature OK';
    } else {
        $notes[] = 'Missing or mismatched find_pending signature';
        $pass = false;
    }

    if (preg_match('/function\s+create\s*\([^)]*\)\s*:\s*int\s*\|\s*(?:\\\\)?WP_Error/i', $contents)) {
        $notes[] = 'create signature includes int|WP_Error';
    } else {
        $notes[] = 'create signature missing int|WP_Error';
        $pass = false;
    }

    if (preg_match('/function\s+approve\s*\([^\)]*\)\s*:\s*bool/i', $contents)) {
        $notes[] = 'approve returns bool';
    } else {
        $notes[] = 'approve signature mismatch';
        $pass = false;
    }

    if (preg_match('/function\s+deny\s*\([^\)]*\)\s*:\s*bool/i', $contents)) {
        $notes[] = 'deny returns bool';
    } else {
        $notes[] = 'deny signature mismatch';
        $pass = false;
    }

    if (preg_match('/ap_duplicate_pending/', $contents) && preg_match('/find_pending\s*\(/', $contents)) {
        $notes[] = 'Duplicate pending protection detected';
    } else {
        $notes[] = 'Duplicate pending protection not detected';
        $pass = false;
    }

    if (preg_match('/do_action\s*\(\s*[\'"]artpulse\/upgrade_review\/approved[\'\"]/', $contents)) {
        $notes[] = 'approve fires artpulse/upgrade_review/approved';
    } else {
        $notes[] = 'approve hook not detected';
        $pass = false;
    }

    if (preg_match('/do_action\s*\(\s*[\'"]artpulse\/upgrade_review\/denied[\'\"]/', $contents)) {
        $notes[] = 'deny fires artpulse/upgrade_review/denied';
    } else {
        $notes[] = 'deny hook not detected';
        $pass = false;
    }

    return ['pass' => $pass, 'notes' => $notes];
}

function checkRestEndpoints(string $rootDir, array &$detectedRoutes): array
{
    $notes = [];
    $pass = true;
    $path = $rootDir . '/src/Rest/UpgradeReviewsController.php';

    if (!file_exists($path)) {
        $notes[] = 'REST controller missing';
        return ['pass' => false, 'notes' => $notes];
    }

    $contents = file_get_contents($path) ?: '';

    if (preg_match('/register_rest_route/', $contents) && preg_match("/['\"]\\/upgrade-reviews['\"]/", $contents)) {
        $notes[] = 'register_rest_route for /upgrade-reviews found';
    } else {
        $notes[] = 'register_rest_route for /upgrade-reviews missing';
        $pass = false;
    }

    if (preg_match('/WP_REST_Server::CREATABLE/', $contents) || preg_match("/'methods'\s*=>\s*['\"]POST['\"]/", $contents)) {
        $notes[] = 'POST create route detected';
        $detectedRoutes[] = 'POST artpulse/v1/upgrade-reviews';
    } else {
        $notes[] = 'POST route not detected';
        $pass = false;
    }

    if (preg_match('/WP_REST_Server::READABLE/', $contents) || preg_match("/'methods'\s*=>\s*['\"]GET['\"]/", $contents)) {
        $notes[] = 'GET list route detected';
        $detectedRoutes[] = 'GET artpulse/v1/upgrade-reviews?mine=1';
    } else {
        $notes[] = 'GET route not detected';
        $pass = false;
    }

    if (preg_match('/X-WP-Nonce/i', $contents) || preg_match('/check_ajax_referer\s*\(\s*[\'"]wp_rest[\'"]/', $contents) || preg_match('/get_header\s*\(\s*[\'"]x-wp-nonce[\'"]/', $contents)) {
        $notes[] = 'Nonce verification detected';
    } else {
        $notes[] = 'Nonce verification not detected';
        $pass = false;
    }

    if (preg_match('/FormRateLimiter::enforce/', $contents)) {
        $notes[] = 'Rate limiting via FormRateLimiter detected';
    } else {
        $notes[] = 'Rate limiting not detected';
        $pass = false;
    }

    if (preg_match("/'status'\s*=>\s*409/", $contents) || preg_match('/409/', $contents)) {
        $notes[] = 'Duplicate error mapped to HTTP 409';
    } else {
        $notes[] = 'HTTP 409 mapping not detected';
        $pass = false;
    }

    if (preg_match("/'status'\s*=>\s*400/", $contents)) {
        $notes[] = 'HTTP 400 mapping detected';
    } else {
        $notes[] = 'HTTP 400 mapping not detected';
        $pass = false;
    }

    if (preg_match('/rest_authorization_required_code\s*\(/', $contents) || preg_match('/\'status\'\s*=>\s*403/', $contents)) {
        $notes[] = 'Unauthenticated error handling detected';
    } else {
        $notes[] = 'Unauthenticated error handling not detected';
        $pass = false;
    }

    return ['pass' => $pass, 'notes' => $notes];
}

function checkAdminHandlers(string $rootDir): array
{
    $notes = [];
    $pass = true;
    $path = $rootDir . '/src/Core/UpgradeReviewHandlers.php';

    if (!file_exists($path)) {
        $notes[] = 'UpgradeReviewHandlers missing';
        return ['pass' => false, 'notes' => $notes];
    }

    $contents = file_get_contents($path) ?: '';

    if (preg_match('/add_action\s*\(\s*[\'"]artpulse\/upgrade_review\/approved[\'\"]/', $contents)) {
        $notes[] = 'Approved hook handler registered';
    } else {
        $notes[] = 'Approved hook handler not detected';
        $pass = false;
    }

    if (preg_match('/add_action\s*\(\s*[\'"]artpulse\/upgrade_review\/denied[\'\"]/', $contents)) {
        $notes[] = 'Denied hook handler registered';
    } else {
        $notes[] = 'Denied hook handler not detected';
        $pass = false;
    }

    if (preg_match('/get_or_create_profile_post/', $contents)) {
        $notes[] = 'Helper get_or_create_profile_post detected';
    } else {
        $notes[] = 'Profile helper not detected';
        $pass = false;
    }

    if (preg_match('/grant/i', $contents) || preg_match('/add_cap/i', $contents) || preg_match('/add_role/i', $contents)) {
        $notes[] = 'Capability/role grant logic spotted';
    } else {
        $notes[] = 'Capability grant logic not detected';
        $pass = false;
    }

    return ['pass' => $pass, 'notes' => $notes];
}

function checkNotifications(string $rootDir): array
{
    $notes = [];
    $pass = true;

    $handlersPath = $rootDir . '/src/Core/UpgradeReviewHandlers.php';
    $hasEmail = false;
    $hasNotification = false;

    if (file_exists($handlersPath)) {
        $contents = file_get_contents($handlersPath) ?: '';
        $hasEmail = $hasEmail || preg_match('/wp_mail\s*\(/', $contents);
        $hasNotification = $hasNotification || preg_match('/notify/i', $contents);
    }

    $notificationsPath = $rootDir . '/src/Core/Notifications';
    if (is_dir($notificationsPath)) {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($notificationsPath, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match('/\.php$/', $file->getFilename())) {
                $content = file_get_contents($file->getPathname()) ?: '';
                $hasEmail = $hasEmail || preg_match('/wp_mail\s*\(/', $content);
                $hasNotification = $hasNotification || preg_match('/notify/i', $content) || preg_match('/in_app/i', $content);
            }
        }
    }

    if ($hasEmail) {
        $notes[] = 'Email notification logic detected';
    } else {
        $notes[] = 'Email notification logic not detected';
        $pass = false;
    }

    if ($hasNotification) {
        $notes[] = 'In-app notification or stub detected';
    } else {
        $notes[] = 'In-app notification logic not detected';
        $pass = false;
    }

    return ['pass' => $pass, 'notes' => $notes];
}

function checkDashboardContract(string $rootDir): array
{
    $notes = [];
    $pass = true;
    $path = $rootDir . '/src/Core/UserDashboardManager.php';

    if (!file_exists($path)) {
        $notes[] = 'UserDashboardManager missing';
        return ['pass' => false, 'notes' => $notes];
    }

    $contents = file_get_contents($path) ?: '';

    if (preg_match('/function\s+getDashboardData\s*\(/', $contents)) {
        $notes[] = 'getDashboardData() present';
    } else {
        $notes[] = 'getDashboardData() missing';
        $pass = false;
    }

    $requiredKeys = ['upgrade', 'requests', 'can_request', 'profile', 'builder_url'];
    foreach ($requiredKeys as $key) {
        if (preg_match('/\'' . preg_quote($key, '/') . '\'/', $contents)) {
            $notes[] = sprintf('Key `%s` detected', $key);
        } else {
            $notes[] = sprintf('Key `%s` not detected', $key);
            $pass = false;
        }
    }

    if (preg_match('/[\"\']autocreate[\"\']\s*(=|=>)\s*[\"\']?1/', $contents) || preg_match('/autocreate=1/', $contents)) {
        $notes[] = 'builder_url includes autocreate parameter';
    } else {
        $notes[] = 'autocreate parameter not detected';
        $pass = false;
    }

    if (preg_match('/redirect/', $contents)) {
        $notes[] = 'Redirect parameter present';
    }

    return ['pass' => $pass, 'notes' => $notes];
}

function checkBuilders(string $rootDir): array
{
    $notes = [];
    $pass = true;

    $builderFiles = [
        $rootDir . '/src/Frontend/ArtistBuilderShortcode.php',
        $rootDir . '/src/Frontend/OrgBuilderShortcode.php',
    ];

    foreach ($builderFiles as $file) {
        $name = basename($file);
        if (!file_exists($file)) {
            $notes[] = sprintf('%s missing', $name);
            $pass = false;
            continue;
        }
        $contents = file_get_contents($file) ?: '';
        if (preg_match('/autocreate/i', $contents)) {
            $notes[] = sprintf('%s handles autocreate parameter', $name);
        } else {
            $notes[] = sprintf('%s autocreate handling not detected', $name);
            $pass = false;
        }
        if (preg_match('/redirect/i', $contents)) {
            $notes[] = sprintf('%s redirect handling detected', $name);
        } else {
            $notes[] = sprintf('%s redirect handling not detected', $name);
            $pass = false;
        }
    }

    return ['pass' => $pass, 'notes' => $notes];
}

function checkSecurityAndAccessibility(string $rootDir, array &$warnings): array
{
    $notes = [];
    $pass = true;

    $restPath = $rootDir . '/src/Rest/UpgradeReviewsController.php';
    if (file_exists($restPath)) {
        $restContents = file_get_contents($restPath) ?: '';
        if (preg_match('/verify_request_nonce|check_ajax_referer|X-WP-Nonce/i', $restContents)) {
            $notes[] = 'Nonce required for POST endpoint';
        } else {
            $notes[] = 'Nonce requirement not detected';
            $pass = false;
        }
        if (preg_match('/FormRateLimiter::enforce/', $restContents)) {
            $notes[] = 'FormRateLimiter used on create';
        } else {
            $notes[] = 'FormRateLimiter not found';
            $pass = false;
        }
    } else {
        $notes[] = 'REST controller missing';
        $pass = false;
    }

    $ariaMatches = findPatternInPaths($rootDir, ['templates', 'assets'], '/aria-(live|label)/i');
    if ($ariaMatches > 0) {
        $notes[] = 'ARIA attributes detected in UI components';
    } else {
        $notes[] = 'ARIA attributes not detected (manual review suggested)';
        $warnings[] = 'TODO: Confirm ARIA labels and live regions manually.';
    }

    return ['pass' => $pass, 'notes' => $notes];
}

function runComposerTestsIfAvailable(string $rootDir, array &$warnings): ?array
{
    $composerJson = $rootDir . '/composer.json';
    $phpunitBinary = $rootDir . '/vendor/bin/phpunit';
    $phpunitBinaryWin = $rootDir . '/vendor/bin/phpunit.bat';

    if (!file_exists($composerJson) || (!file_exists($phpunitBinary) && !file_exists($phpunitBinaryWin))) {
        return null;
    }

    $command = 'cd ' . escapeshellarg($rootDir) . ' && composer test';
    $output = [];
    $exitCode = 0;
    exec($command . ' 2>&1', $output, $exitCode);

    if ($exitCode !== 0) {
        $warnings[] = 'TODO: Review PHPUnit failures detected during audit run.';
    }

    $maxLines = 20;
    if (count($output) > $maxLines) {
        $output = array_slice($output, -$maxLines);
        array_unshift($output, sprintf('... (trimmed to last %d lines) ...', $maxLines));
    }

    return [
        'exitCode' => $exitCode,
        'lines'    => $output,
    ];
}

function findPatternInPaths(string $rootDir, array $directories, string $pattern): int
{
    $count = 0;
    foreach ($directories as $dir) {
        $path = $rootDir . '/' . $dir;
        if (!is_dir($path)) {
            continue;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match('/\.(php|js|jsx|tsx|vue)$/i', $file->getFilename())) {
                $content = file_get_contents($file->getPathname());
                if ($content !== false && preg_match($pattern, $content)) {
                    $count++;
                }
            }
        }
    }
    return $count;
}

function escapeTableCell(string $text): string
{
    return str_replace('|', '\\|', $text);
}

function relativePath(string $rootDir, string $path): string
{
    if (str_starts_with($path, $rootDir)) {
        return ltrim(substr($path, strlen($rootDir)), '/\\');
    }
    return $path;
}
