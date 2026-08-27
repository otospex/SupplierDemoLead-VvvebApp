<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This audit must be run from the command line.\n");
    exit(2);
}

$rules = [
    'FABRICATED_PROOF' => [
        'pattern' => '/(?:plus de\s+250\s+organisations|250\+\s+organisations)/iu',
        'message' => 'Unsupported customer-count proof must be removed or registered with evidence.',
    ],
    'PLACEHOLDER_BRAND' => [
        'pattern' => '/\b(?:ACMECORP|CUBIX|NEXUS|DELTA)\b/u',
        'message' => 'Demo brand or customer logo must not appear in publishable content.',
    ],
    'CARBON_SUPERLATIVE' => [
        'pattern' => '/(?:impact carbone|empreinte carbone).{0,40}(?:plus bas(?:se)? que tout le monde|le plus faible|la plus faible)/iu',
        'message' => 'Comparative carbon claims require a scoped comparative lifecycle assessment.',
    ],
    'UNSCOPED_CERTIFICATION' => [
        'pattern' => '/\b(?:nous|notre|nos|indépendant digital)\b.{0,80}(?:certifi(?:é|ée|és|ées)|SecNumCloud|HDS|ISO\s*27001)/iu',
        'message' => 'A certification claim must name the holder, service scope, status, source, and date.',
    ],
    'ABSOLUTE_EXTRATERRITORIAL' => [
        'pattern' => '/(?:à l.abri|immun(?:e|isé|isée)|sans exposition|garanti(?:e)?).{0,50}(?:CLOUD Act|lois? extraterritoriales?)/iu',
        'message' => 'Absolute protection from extraterritorial law is not a defensible blanket guarantee.',
    ],
];

$reportAllowances = false;
$targets = [];
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--report-allowances') {
        $reportAllowances = true;
        continue;
    }
    $targets[] = $argument;
}

if ($targets === []) {
    fwrite(STDERR, "Usage: php scripts/editorial-audit.php [--report-allowances] <file-or-directory> [...]\n");
    exit(2);
}

/** @return string[] */
function collectEditorialFiles(array $targets): array
{
    $files = [];
    $allowedExtensions = ['html', 'md', 'sql'];

    foreach ($targets as $target) {
        if (! file_exists($target)) {
            fwrite(STDERR, "Missing audit target: {$target}\n");
            exit(2);
        }

        if (is_file($target)) {
            $extension = strtolower((string) pathinfo($target, PATHINFO_EXTENSION));
            if (in_array($extension, $allowedExtensions, true)) {
                $files[] = $target;
            }
            continue;
        }

        $directory = new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                $directory,
                static function (SplFileInfo $entry): bool {
                    if (! $entry->isDir()) {
                        return true;
                    }

                    return ! in_array($entry->getFilename(), ['.git', 'vendor', 'backup', 'node_modules'], true);
                }
            )
        );

        foreach ($iterator as $entry) {
            if (! $entry->isFile()) {
                continue;
            }
            $path = $entry->getPathname();
            if (strpos(str_replace('\\', '/', $path), '/scripts/tests/') !== false) {
                continue;
            }
            $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($extension, $allowedExtensions, true)) {
                $files[] = $path;
            }
        }
    }

    $files = array_values(array_unique($files));
    sort($files);

    return $files;
}

function validAllowance(string $line, string $rule): ?string
{
    $pattern = '/editorial-audit:\s*allow\s+' . preg_quote($rule, '/') . '\s+evidence=([^\s<]+)/i';
    if (preg_match($pattern, $line, $matches) !== 1) {
        return null;
    }

    $evidence = trim($matches[1]);
    return $evidence === '' ? null : $evidence;
}

function validUtf8(string $line): string
{
    if (preg_match('//u', $line) === 1) {
        return $line;
    }

    if (function_exists('iconv')) {
        $clean = iconv('UTF-8', 'UTF-8//IGNORE', $line);
        if ($clean !== false) {
            return $clean;
        }
    }

    return preg_replace('/[^\x20-\x7E]/', '', $line) ?? $line;
}

$violations = 0;
$allowances = 0;

foreach (collectEditorialFiles($targets) as $file) {
    $handle = fopen($file, 'rb');
    if ($handle === false) {
        fwrite(STDERR, "Could not read audit target: {$file}\n");
        exit(2);
    }

    $lineNumber = 0;
    while (($rawLine = fgets($handle)) !== false) {
        $lineNumber++;
        $line = validUtf8($rawLine);

        foreach ($rules as $rule => $definition) {
            if (preg_match($definition['pattern'], $line) !== 1) {
                continue;
            }

            $evidence = validAllowance($line, $rule);
            if ($evidence !== null) {
                $allowances++;
                if ($reportAllowances) {
                    echo "{$file}:{$lineNumber} [ALLOW {$rule}] evidence={$evidence}\n";
                }
                continue;
            }

            $violations++;
            echo "{$file}:{$lineNumber} [{$rule}] {$definition['message']}\n";
        }
    }
    fclose($handle);
}

if ($violations > 0) {
    fwrite(STDERR, "Editorial audit failed: {$violations} blocking claim(s), {$allowances} evidenced allowance(s).\n");
    exit(1);
}

echo "Editorial audit passed: 0 blocking claims, {$allowances} evidenced allowance(s).\n";
