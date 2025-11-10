<?php

function loadEnv(string $path): array {
    $vars = [];
    if (!file_exists($path)) return $vars;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) continue;
        $key = trim($parts[0]);
        $val = trim($parts[1]);
        if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
            $val = substr($val, 1, -1);
        }
        $vars[$key] = $val;
    }
    return $vars;
}

function env(array $env, string $key, $default = null) {
    return $env[$key] ?? $default;
}

function loadEnvIntoGlobals(string $path): array {
    $vars = loadEnv($path);
    foreach ($vars as $k => $v) {
        $_ENV[$k] = $v;
        @putenv($k . '=' . $v);
    }
    return $vars;
}
