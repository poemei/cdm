<?php
declare(strict_types=1);

final class cdm_model
{
    private function modulesRoot(): string
    {
        // ChAoS MVC Core resolves userland modules via USERROOT . '/modules'.
        if (!defined('USERROOT')) {
            throw new RuntimeException('ChAoS MVC USERROOT is unavailable.');
        }
        $root = realpath(USERROOT . '/modules');
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException('Installed module directory could not be located.');
        }
        return $root;
    }

    private function coreContracts(): array
    {
        // Read the installed Core, not a GitHub version or version-number table.
        $app = defined('APPROOT') ? realpath(APPROOT) : false;
        $router = $app ? $app . '/core/router.php' : '';
        $controller = $app ? $app . '/core/controller.php' : '';
        $admin = $app ? $app . '/controllers/admin.php' : '';
        $read = static function (string $path): ?string {
            if (!$path || is_link($path) || !is_file($path) || filesize($path) > 1048576) {
                return null;
            }
            $content = file_get_contents($path);
            return $content === false ? null : $content;
        };
        $routerSource = $read($router);
        $controllerSource = $read($controller);
        $adminSource = $read($admin);
        return [
            'module_controller' => $routerSource !== null
                && strpos($routerSource, 'USERROOT') !== false
                && strpos($routerSource, '/modules/') !== false
                && strpos($routerSource, '/controllers/') !== false,
            'manifest' => $routerSource !== null
                && strpos($routerSource, '/module.json') !== false,
            'view_api' => $controllerSource !== null
                && preg_match('/function\\s+view\\s*\\(/', $controllerSource) === 1,
            'admin_dispatch' => $adminSource !== null
                && strpos($adminSource, 'USERROOT') !== false
                && strpos($adminSource, '/controllers/') !== false,
            'sources' => [
                'app/core/router.php' => $routerSource !== null,
                'app/core/controller.php' => $controllerSource !== null,
                'app/controllers/admin.php' => $adminSource !== null,
            ],
        ];
    }

    public function modules(): array
    {
        $root = $this->modulesRoot();
        $names = [];
        foreach (new DirectoryIterator($root) as $entry) {
            if (!$entry->isDir() || $entry->isDot() || $entry->isLink()) {
                continue;
            }
            $name = $entry->getFilename();
            if (preg_match('/^[a-z][a-z0-9_-]*$/', $name) && is_file($entry->getPathname() . '/module.json')) {
                $names[] = $name;
            }
        }
        sort($names, SORT_STRING);
        return $names;
    }

    public function diagnose(string $name): array
    {
        if (!in_array($name, $this->modules(), true)) {
            throw new InvalidArgumentException('Select an installed module from the list.');
        }
        $root = $this->modulesRoot();
        $directory = realpath($root . '/' . $name);
        if ($directory === false || dirname($directory) !== $root) {
            throw new RuntimeException('Module directory is not accessible.');
        }
        $metadataFile = $directory . '/module.json';
        if (is_link($metadataFile) || !is_file($metadataFile)) {
            throw new RuntimeException('Module metadata is not a regular file.');
        }
        $raw = file_get_contents($metadataFile);
        if ($raw === false || strlen($raw) > 1048576) {
            throw new RuntimeException('Module metadata could not be read safely.');
        }
        $contracts = $this->coreContracts();
        $config = json_decode($raw, true);
        $findings = [];
        if (!is_array($config) || json_last_error() !== JSON_ERROR_NONE) {
            $findings[] = [
                'route' => 'N/A', 'code' => 'module.json:1',
                'summary' => 'Module metadata is not valid JSON.',
                'evidence' => json_last_error_msg(),
                'repair' => 'Correct the JSON syntax and validate module.json.',
                'status' => 'CONFIRMED',
            ];
        }

        // Controller inspection is independent of manifest validity.
        {
            $controllerFile = $directory . '/controllers/' . $name . '.php';
            if ($contracts['module_controller'] && (!is_file($controllerFile) || is_link($controllerFile))) {
                $findings[] = [
                    'route' => '/' . $name, 'code' => 'controllers/' . $name . '.php',
                    'summary' => 'Expected module controller is missing.',
                    'evidence' => 'No regular controller file at the conventional module path.',
                    'repair' => 'Check the module controller path against the installed Core routing contract.',
                    'status' => 'OBSERVED',
                ];
            } elseif (is_file($controllerFile) && !is_link($controllerFile)) {
                $source = file_get_contents($controllerFile);
                if ($source !== false && strlen($source) <= 1048576) {
                    // Parse without executing module code. PHP 7.4+ TOKEN_PARSE
                    // raises ParseError for malformed PHP source.
                    try {
                        token_get_all($source, TOKEN_PARSE);
                    } catch (ParseError $exception) {
                        $findings[] = [
                            'route' => '/' . $name,
                            'code' => 'controllers/' . $name . '.php:' . $exception->getLine(),
                            'summary' => 'Controller contains a PHP syntax error.',
                            'evidence' => $exception->getMessage(),
                            'repair' => 'Correct the PHP syntax at the reported line and rerun diagnostics.',
                            'status' => 'CONFIRMED',
                        ];
                    }
                    // Conservative view-argument check. This is not full data-flow analysis.
                    // Inspect even when PHP syntax parsing failed.
                    $lines = explode("\n", $source);
                    foreach ($lines as $i => $line) {
                        if (!$contracts['view_api'] || !preg_match('/\\$this\\s*->\\s*view\\s*\\(.*?,\\s*\\$data\\s*\\)/', $line)) {
                            continue;
                        }
                        $preceding = implode("\n", array_slice($lines, 0, $i));
                        // Avoid claiming an undefined variable when a prior assignment,
                        // parameter, or explicit global declaration may supply it.
                        if (preg_match('/\\$data\\s*(?:=(?!=)|\\+=|\\.=|\\[)/', $preceding)
                            || preg_match('/\\b(?:function|fn)\\b[^\\n]*\\([^)]*\\$data\\b/', $preceding)
                            || preg_match('/\\bglobal\\s+\\$data\\b/', $preceding)) {
                            continue;
                        }
                        $findings[] = [
                            'route' => '/' . $name,
                            'code' => 'controllers/' . $name . '.php:' . ($i + 1),
                            'summary' => 'View receives a potentially undefined $data variable.',
                            'evidence' => 'No preceding $data assignment or declaration recognized before: ' . trim($line),
                            'repair' => 'Initialize $data before calling view(), or pass the intended defined value.',
                            'status' => 'POTENTIAL MISMATCH',
                        ];
                    }
                    $usesParams = preg_match('/\$params\s*\[/', $source) === 1;
                    $indexParameters = $config['index']['parameters'] ?? null;
                    if (is_array($config) && $usesParams && $indexParameters === false) {
                        $lines = explode("\n", $source);
                        $lineNumber = 1;
                        foreach ($lines as $i => $line) {
                            if (preg_match('/\$params\s*\[/', $line)) {
                                $lineNumber = $i + 1;
                                break;
                            }
                        }
                        $findings[] = [
                            'route' => '/' . $name . '/{parameter}',
                            'code' => 'controllers/' . $name . '.php:' . $lineNumber,
                            'summary' => 'Controller accesses route parameters while module metadata disables index parameters.',
                            'evidence' => 'module.json index.parameters=false; source line ' . $lineNumber . ': ' . trim($lines[$lineNumber - 1]),
                            'repair' => 'If the index route accepts slug parameters, enable index.parameters and test the route. Otherwise remove the unsupported parameter dependency.',
                            'status' => 'POTENTIAL MISMATCH',
                        ];
                    }
                }
            }
        }
        return [
            'module' => $name,
            'findings' => $findings,
            'inspected' => ['module.json', 'controllers/' . $name . '.php'],
            'core_contracts' => $contracts,
            'note' => 'Checks use inspected installed Core capabilities where available. Unrecognized contracts are not treated as violations. No finding does not establish module correctness.',
        ];
    }
}