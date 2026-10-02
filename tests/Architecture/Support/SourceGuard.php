<?php

namespace Tests\Architecture\Support;

use ReflectionClass;

final class SourceGuard
{
    public static function normalizePath(string $path): string
    {
        return preg_replace('#/+#', '/', str_replace('\\', '/', $path)) ?? $path;
    }

    public static function relativePath(string $base, string $path): string
    {
        $base = rtrim(self::normalizePath($base), '/').'/';

        return str_starts_with(self::normalizePath($path), $base)
            ? substr(self::normalizePath($path), strlen($base))
            : self::normalizePath($path);
    }

    public static function isController(string $path): bool
    {
        return str_contains('/'.self::normalizePath($path), '/Http/Controllers/');
    }

    public static function isApiController(string $path): bool
    {
        return str_contains('/'.self::normalizePath($path), '/Http/Controllers/Api/V1/');
    }

    /** @return list<string> */
    public static function controllerViolations(string $source): array
    {
        $patterns = [
            'repository dependency' => '/Repositories\\\\/',
            'model static query' => '/\b[A-Z][A-Za-z0-9_]*::(?:query|find|findOrFail|where|whereIn|with|create)\s*\(/',
            'facade persistence' => '/\b(?:DB|Storage)::/',
            'relation loading' => '/->load(?:Missing)?\s*\(/',
            'relation terminal query' => '/->\w+\s*\(\s*\)\s*->(?:get|first|firstOrFail|find|findOrFail|exists|count|paginate|pluck|update|delete)\s*\(/',
        ];

        return self::matchedRules($source, $patterns);
    }

    /** @return list<string> */
    public static function persistenceViolations(string $source, bool $allowTransactions = false): array
    {
        $patterns = [
            'model static query' => '/\b[A-Z][A-Za-z0-9_]*::(?:query|find|findOrFail|where|whereIn|with|create)\s*\(/',
            'query builder' => '/\bDB::(?:table|select|statement|insert|update|delete)\s*\(/',
            'storage access' => '/\bStorage::/',
            'relation loading' => '/->load(?:Missing)?\s*\(/',
            'model persistence' => '/\$(?:user|task|project|label|comment|attachment|membership|media|token|notification|row|existing)->(?:save|delete|restore|refresh|paginate)\s*\(/',
            'relation terminal query' => '/->\w+\s*\(\s*\)\s*->(?:get|first|firstOrFail|find|findOrFail|exists|count|paginate|pluck|update|delete)\s*\(/',
        ];

        if (! $allowTransactions) {
            $patterns['database facade'] = '/\bDB::/';
        }

        return self::matchedRules($source, $patterns);
    }

    /** @return array<string, list<string>> */
    public static function publicMethodBoundaryCalls(string $source): array
    {
        $calls = [];

        foreach (self::publicMethodBodies($source) as $method => $body) {
            preg_match_all('/\$this->([A-Za-z_][A-Za-z0-9_]*)->([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $body, $matches);
            $calls[$method] = array_values(array_unique($matches[1]));
        }

        return $calls;
    }

    /** @return list<string> */
    public static function moduleDependencies(string $source): array
    {
        preg_match_all('/(?:\\\\)?Modules\\\\([A-Za-z]+)\\\\/', $source, $matches);
        $dependencies = array_values(array_unique($matches[1]));
        sort($dependencies);

        return $dependencies;
    }

    public static function resolvesModuleFromContainer(string $source): bool
    {
        return preg_match('/(?:app|resolve)\s*\(\s*[\'\"]?(?:\\\\)?Modules\\\\|->make\s*\(\s*[\'\"]?(?:\\\\)?Modules\\\\/', $source) === 1;
    }

    public static function hasApprovedApiActionReturnTypes(string $class): bool
    {
        $reflection = new ReflectionClass($class);
        $source = file_get_contents($reflection->getFileName());
        $declaredMethods = array_keys(self::publicMethodBodies(is_string($source) ? $source : ''));
        $allowed = [
            'Illuminate\\Http\\JsonResponse',
            'Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection',
            'Illuminate\\Http\\Resources\\Json\\JsonResource',
            'Symfony\\Component\\HttpFoundation\\StreamedResponse',
        ];

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || ! in_array($method->getName(), $declaredMethods, true)) {
                continue;
            }

            $returnType = $method->getReturnType();
            if (! $returnType instanceof \ReflectionNamedType) {
                return false;
            }

            $name = $returnType->getName();
            if (! in_array($name, $allowed, true) && ! is_subclass_of($name, 'Illuminate\\Http\\Resources\\Json\\JsonResource')) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, string> $patterns @return list<string> */
    private static function matchedRules(string $source, array $patterns): array
    {
        $violations = [];

        foreach ($patterns as $rule => $pattern) {
            if (preg_match($pattern, $source) === 1) {
                $violations[] = $rule;
            }
        }

        return $violations;
    }

    /** @return array<string, string> */
    private static function publicMethodBodies(string $source): array
    {
        $tokens = token_get_all($source);
        $methods = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            if (! is_array($tokens[$index]) || $tokens[$index][0] !== T_PUBLIC) {
                continue;
            }

            while (++$index < $count && (! is_array($tokens[$index]) || $tokens[$index][0] !== T_FUNCTION)) {
                if ($tokens[$index] === ';') {
                    continue 2;
                }
            }

            while (++$index < $count && (! is_array($tokens[$index]) || $tokens[$index][0] !== T_STRING)) {
            }
            $method = is_array($tokens[$index] ?? null) ? $tokens[$index][1] : '';

            while (++$index < $count && $tokens[$index] !== '{') {
            }
            $depth = 1;
            $body = '';

            while (++$index < $count && $depth > 0) {
                $token = $tokens[$index];
                $text = is_array($token) ? $token[1] : $token;
                $depth += $text === '{' ? 1 : ($text === '}' ? -1 : 0);
                if ($depth > 0) {
                    $body .= $text;
                }
            }

            if ($method !== '') {
                $methods[$method] = $body;
            }
        }

        return $methods;
    }
}
