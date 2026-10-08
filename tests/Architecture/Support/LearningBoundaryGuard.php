<?php

namespace Tests\Architecture\Support;

/** Focused source checks for the two R1 learning modules, not a general PHP analyser. */
final class LearningBoundaryGuard
{
    public const MODULES = ['LearningCatalog', 'LearningInsights'];

    public const PUBLIC_CATALOG_CLASSES = [
        'Modules\\LearningCatalog\\Contracts\\PublishedLearningEntryFeed',
        'Modules\\LearningCatalog\\Data\\PublishedLearningEntryData',
        'Modules\\LearningCatalog\\Events\\LearningEntryPublished',
    ];

    /** @return list<string> */
    public static function dependencyViolations(?string $owner, string $source): array
    {
        $references = self::references($source);
        $violations = [];

        if ($owner !== null && $references['namespace'] !== ''
            && ! str_starts_with($references['namespace'].'\\', 'Modules\\'.$owner.'\\')) {
            $violations[] = 'namespace does not belong to '.$owner;
        }

        foreach ($references['classes'] as $class) {
            if (str_starts_with(strtolower($class), 'app\\')) {
                if ($owner !== null) {
                    $violations[] = 'host application dependency: '.$class;
                }

                continue;
            }

            if (! str_starts_with(strtolower($class), 'modules\\')) {
                continue;
            }

            $module = explode('\\', $class)[1] ?? '';

            if ($owner === null) {
                if (in_array(strtolower($module), array_map(strtolower(...), self::MODULES), true)) {
                    $violations[] = 'production dependency on learning module: '.$class;
                }

                continue;
            }

            if (strcasecmp($module, $owner) === 0) {
                continue;
            }

            if ($owner === 'LearningInsights' && in_array($class, self::PUBLIC_CATALOG_CLASSES, true)) {
                continue;
            }

            $violations[] = 'forbidden module dependency: '.$class;
        }

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    public static function tableViolations(?string $owner, string $source): array
    {
        $code = self::references($source)['code'];
        $tables = [];
        $violations = [];
        $ownedTable = match ($owner) {
            'LearningCatalog' => 'r1_learning_entries',
            'LearningInsights' => 'r1_learning_insight_entries',
            default => null,
        };

        // Literal table ownership includes queries, schema operations, joins and explicit FK targets.
        preg_match_all('/(?:(?:->|::)\s*(?:table|from|join|leftJoin|rightJoin|crossJoin|constrained|on)|\bSchema::\s*(?:create|drop|dropIfExists|hasTable|rename))\s*\(\s*([\'"])([^\'"]+)\1/', $code, $matches);
        $tables = array_merge($tables, $matches[2]);
        preg_match_all('/\$table\s*=\s*([\'"])([^\'"]+)\1/', $code, $matches);
        $tables = array_merge($tables, $matches[2]);

        foreach (array_unique($tables) as $table) {
            $table = preg_split('/\s+(?:as\s+)?/i', trim($table))[0];

            if (($owner !== null && $table !== $ownedTable)
                || ($owner === null && in_array($table, ['r1_learning_entries', 'r1_learning_insight_entries'], true))) {
                $violations[] = 'table ownership: '.$table;
            }
        }

        if ($owner === null && preg_match('/\br1_learning_(?:entries|insight_entries)\b/', $code)) {
            $violations[] = 'production source references a learning table';
        }

        if ($owner !== null) {
            // The lab has no raw SQL, dynamic table names or FK relationships to infer.
            if (preg_match('/\bDB::\s*(?:select|selectOne|statement|unprepared|insert|update|delete)\s*\(/', $code)) {
                $violations[] = 'raw SQL is outside the learning lab';
            }
            if (preg_match('/(?:(?:->|::)\s*(?:table|from|join|leftJoin|rightJoin|crossJoin|constrained|on)|\bSchema::\s*(?:create|drop|dropIfExists|hasTable|rename))\s*\(\s*(?![\s\'"])/', $code)) {
                $violations[] = 'dynamic table reference';
            }
            if (preg_match('/->\s*(?:foreignId|foreignUuid|foreignUlid|foreign|constrained)\s*\(/', $code)) {
                $violations[] = 'foreign keys are outside the learning lab';
            }
        }

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    public static function layerViolations(string $path, string $source): array
    {
        $path = SourceGuard::normalizePath($path);
        $references = self::references($source);
        $code = $references['code'];
        $violations = [];
        $isService = str_contains($path, '/Services/');
        $isListener = str_contains($path, '/Listeners/');

        if ($isService || $isListener) {
            if (preg_match('/::\s*(?:query|find|findOrFail|where|whereIn|with|create|firstOrCreate)\s*\(|->\s*(?:save|delete|restore|refresh|load|loadMissing|paginate)\s*\(/', $code)) {
                $violations[] = 'persistence belongs to the repository';
            }
            preg_match_all('/\bDB::\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $code, $matches);
            foreach ($matches[1] as $method) {
                if (! $isService || $method !== 'transaction') {
                    $violations[] = 'only the top-level service owns a DB transaction';
                }
            }
            if (preg_match('/\bStorage::|->\s*(?:get|first|firstOrFail|exists|count|pluck|update)\s*\(/', $code)) {
                $violations[] = 'direct query or storage access';
            }
        }

        if (str_contains($path, '/Repositories/Contracts/')) {
            foreach ($references['classes'] as $class) {
                if (in_array($class, ['Illuminate\\Database\\Eloquent\\Builder', 'Illuminate\\Database\\Query\\Builder'], true)
                    || str_starts_with($class, 'Illuminate\\Database\\Eloquent\\Relations\\')) {
                    $violations[] = 'repository contract leaks a query builder';
                }
            }
        }

        if (str_contains($path, '/Repositories/Eloquent/')
            && preg_match('/(?:\bDB::|->)\s*(?:transaction|beginTransaction|commit|rollBack)\s*\(/', $code)) {
            $violations[] = 'repository must be transaction-neutral';
        }

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    public static function uiViolations(string $path, string $source): array
    {
        $path = '/'.SourceGuard::normalizePath($path);
        $references = self::references($source);
        $violations = [];

        if (preg_match('#/(?:routes|Livewire)/|/Http/Controllers/|/resources/views/|\.blade\.php$#i', $path)) {
            $violations[] = 'learning module must not expose HTTP or UI files';
        }

        foreach ($references['classes'] as $class) {
            if ($class === 'Illuminate\\Support\\Facades\\Route'
                || str_starts_with($class, 'Illuminate\\Routing\\')
                || $class === 'Illuminate\\Foundation\\Support\\Providers\\RouteServiceProvider'
                || str_starts_with($class, 'Livewire\\')) {
                $violations[] = 'learning module must not register routes or Livewire';
            }
        }

        if (preg_match('/\bRoute::|->\s*(?:loadRoutesFrom|loadViewsFrom|routes)\s*\(|\b(?:app|resolve)\s*\(\s*[\'"]router[\'"]/', $references['code'])) {
            $violations[] = 'learning module must not register routes or views';
        }

        return array_values(array_unique($violations));
    }

    /**
     * Resolve literal PHP references and class imports (including grouped imports).
     * Arbitrary dynamic PHP execution is deliberately not interpreted by this guard.
     *
     * @return array{namespace: string, classes: list<string>, code: string}
     */
    private static function references(string $source): array
    {
        $tokens = token_get_all($source);
        $imports = [];
        $classes = [];
        $ignored = [];
        $namespace = '';
        $depth = 0;
        $namespaceDepth = 0;
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if ($token === '{') {
                $depth++;
            } elseif ($token === '}') {
                $depth--;
            }

            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $declaration = '';
                $ignored[$index] = true;

                while (++$index < $count) {
                    $text = is_array($tokens[$index]) ? $tokens[$index][1] : $tokens[$index];
                    $ignored[$index] = true;

                    if ($text === ';' || $text === '{') {
                        if ($text === '{') {
                            $depth++;
                            $namespaceDepth = $depth;
                        }
                        break;
                    }

                    $declaration .= $text;
                }

                $namespace = trim($declaration);
            } elseif ($token[0] === T_USE && $depth === $namespaceDepth) {
                $declaration = '';
                $ignored[$index] = true;

                while (++$index < $count) {
                    $text = is_array($tokens[$index]) ? $tokens[$index][1] : $tokens[$index];
                    $ignored[$index] = true;

                    if ($text === ';') {
                        break;
                    }
                    if (! is_array($tokens[$index]) || ! in_array($tokens[$index][0], [T_COMMENT, T_DOC_COMMENT], true)) {
                        $declaration .= $text;
                    }
                }

                foreach (self::imports($declaration) as $alias => $class) {
                    $imports[$alias] = $class;
                    $classes[] = $class;
                }
            }
        }

        $code = '';

        foreach ($tokens as $index => $token) {
            if (isset($ignored[$index])) {
                continue;
            }
            if (! is_array($token)) {
                $code .= $token;

                continue;
            }

            [$kind, $text] = $token;

            if (in_array($kind, [T_COMMENT, T_DOC_COMMENT], true)) {
                $code .= ' ';

                continue;
            }

            if ($kind === T_CONSTANT_ENCAPSED_STRING) {
                $literal = str_replace(['\\\\', '\\"', "\\'"], ['\\', '"', "'"], substr($text, 1, -1));

                if (preg_match('/^\\\\?(?:Modules|App)\\\\[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/i', $literal)) {
                    $classes[] = ltrim($literal, '\\');
                }
            } elseif (in_array($kind, [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)
                || ($kind === T_STRING && isset($imports[strtolower($text)]))) {
                $class = self::resolve($text, $imports, $namespace);
                $classes[] = $class;

                $text = match ($class) {
                    'Illuminate\\Support\\Facades\\DB' => 'DB',
                    'Illuminate\\Support\\Facades\\Storage' => 'Storage',
                    'Illuminate\\Support\\Facades\\Route' => 'Route',
                    'Illuminate\\Support\\Facades\\Schema' => 'Schema',
                    default => $text,
                };
            }

            $code .= $text;
        }

        return ['namespace' => $namespace, 'classes' => array_values(array_unique($classes)), 'code' => $code];
    }

    /** @return array<string, string> */
    private static function imports(string $declaration): array
    {
        $declaration = trim(preg_replace('/\s+/', ' ', $declaration));
        $prefix = '';

        if (str_contains($declaration, '{')) {
            [$prefix, $declaration] = explode('{', $declaration, 2);
            $declaration = rtrim(trim($declaration), '}');
        }

        $imports = [];

        foreach (explode(',', $declaration) as $import) {
            $import = preg_replace('/^(?:function|const)\s+/', '', trim($import));
            $parts = preg_split('/\s+as\s+/i', $import);
            $class = ltrim(trim($prefix).trim($parts[0]), '\\');
            $alias = $parts[1] ?? substr($class, strrpos('\\'.$class, '\\'));
            $imports[strtolower(trim($alias))] = $class;
        }

        return $imports;
    }

    /** @param array<string, string> $imports */
    private static function resolve(string $name, array $imports, string $namespace): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }
        if (str_starts_with($name, 'namespace\\')) {
            return $namespace.'\\'.substr($name, strlen('namespace\\'));
        }

        $parts = explode('\\', $name, 2);
        $import = $imports[strtolower($parts[0])] ?? null;

        if ($import !== null) {
            return $import.(isset($parts[1]) ? '\\'.$parts[1] : '');
        }

        return str_starts_with($name, 'Modules\\') || str_starts_with($name, 'App\\') || $namespace === ''
            ? $name
            : $namespace.'\\'.$name;
    }
}
