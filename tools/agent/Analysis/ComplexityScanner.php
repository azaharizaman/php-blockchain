<?php

declare(strict_types=1);

namespace Blockchain\Agent\Analysis;

/**
 * ComplexityScanner analyzes cyclomatic complexity of PHP code.
 *
 * This analyzer scans PHP files and identifies methods with high cyclomatic
 * complexity that would benefit from refactoring. It calculates complexity
 * based on control flow structures and generates actionable suggestions.
 *
 * @package Blockchain\Agent\Analysis
 */
class ComplexityScanner
{
    /**
     * Complexity threshold above which methods are flagged.
     */
    private int $complexityThreshold;

    /**
     * Project root directory.
     */
    private string $projectRoot;

    /**
     * Directories to scan (relative to project root).
     *
     * @var array<string>
     */
    private array $scanPaths;

    /**
     * Patterns to exclude from scanning.
     *
     * @var array<string>
     */
    private array $excludePatterns;

    /**
     * Create a new ComplexityScanner.
     *
     * @param string $projectRoot Project root directory
     * @param int $complexityThreshold Complexity threshold (default: 10)
     * @param array<string> $scanPaths Paths to scan (default: ['src/', 'tools/'])
     * @param array<string> $excludePatterns Patterns to exclude
     */
    public function __construct(
        string $projectRoot,
        int $complexityThreshold = 10,
        array $scanPaths = ['src/', 'tools/'],
        array $excludePatterns = ['*/vendor/*', '*/tests/*', '*/node_modules/*']
    ) {
        $this->projectRoot = rtrim($projectRoot, '/');
        $this->complexityThreshold = $complexityThreshold;
        $this->scanPaths = $scanPaths;
        $this->excludePatterns = $excludePatterns;
    }

    /**
     * Scan project for high-complexity methods.
     *
     * @return array<RefactoringSuggestion>
     */
    public function scan(): array
    {
        $suggestions = [];

        foreach ($this->scanPaths as $path) {
            $fullPath = $this->projectRoot . '/' . ltrim($path, '/');
            
            if (!is_dir($fullPath)) {
                continue;
            }

            $suggestions = array_merge(
                $suggestions,
                $this->scanDirectory($fullPath)
            );
        }

        return $suggestions;
    }

    /**
     * Scan a directory recursively for PHP files.
     *
     * @param string $directory Directory to scan
     * @return array<RefactoringSuggestion>
     */
    private function scanDirectory(string $directory): array
    {
        $suggestions = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getPathname();

            // Check exclusion patterns
            if ($this->shouldExclude($filePath)) {
                continue;
            }

            $fileSuggestions = $this->analyzeFile($filePath);
            $suggestions = array_merge($suggestions, $fileSuggestions);
        }

        return $suggestions;
    }

    /**
     * Check if a file should be excluded based on patterns.
     *
     * @param string $filePath File path to check
     * @return bool True if should be excluded
     */
    private function shouldExclude(string $filePath): bool
    {
        foreach ($this->excludePatterns as $pattern) {
            if (fnmatch($pattern, $filePath)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Analyze a PHP file for complexity issues.
     *
     * @param string $filePath Path to PHP file
     * @return array<RefactoringSuggestion>
     */
    private function analyzeFile(string $filePath): array
    {
        $suggestions = [];
        $content = file_get_contents($filePath);
        
        if ($content === false) {
            return $suggestions;
        }

        $tokens = token_get_all($content);
        $methods = $this->extractMethods($tokens, $content);

        foreach ($methods as $method) {
            $complexity = $this->calculateComplexity($method['tokens']);
            
            if ($complexity >= $this->complexityThreshold) {
                $relativePath = $this->getRelativePath($filePath);
                
                $suggestions[] = new RefactoringSuggestion(
                    id: 'complexity_' . md5($filePath . '_' . $method['name']),
                    type: 'complexity',
                    filePath: $relativePath,
                    title: "High complexity in method '{$method['name']}'",
                    description: $this->generateComplexityDescription($method['name'], $complexity),
                    risk: $this->calculateRisk($complexity),
                    startLine: $method['start_line'],
                    endLine: $method['end_line'],
                    currentMetric: (float) $complexity,
                    expectedMetric: (float) $this->complexityThreshold - 1
                );
            }
        }

        return $suggestions;
    }

    /**
     * Extract methods from tokens.
     *
     * @param array<mixed> $tokens PHP tokens
     * @param string $content File content
     * @return array<array<string, mixed>>
     */
    private function extractMethods(array $tokens, string $content): array
    {
        $methods = [];
        $contextStack = [];
        $currentMethod = null;

        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            // Detect function/method declaration
            if (is_array($token) && $token[0] === T_FUNCTION) {
                // Determine if this is a named function or a closure
                $methodName = null;
                $isClosure = true;
                for ($j = $i + 1; $j < count($tokens); $j++) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                        $methodName = $tokens[$j][1];
                        $isClosure = false;
                        break;
                    }
                    // If we hit a '(' before a T_STRING, it's a closure
                    if ($tokens[$j] === '(') {
                        break;
                    }
                }

                // Push new context for function/closure
                $contextStack[] = [
                    'name' => $methodName,
                    'start_line' => is_array($token) ? $token[2] : 0,
                    'tokens' => [],
                    'braceLevel' => 0,
                    'isClosure' => $isClosure,
                ];

                // If this is a named method, set as currentMethod
                if ($methodName !== null && !$isClosure) {
                    $currentMethod = &$contextStack[array_key_last($contextStack)];
                }
            }

            // Track braces for all contexts
            if (!empty($contextStack)) {
                if ($token === '{') {
                    $contextStack[array_key_last($contextStack)]['braceLevel']++;
                } elseif ($token === '}') {
                    $contextStack[array_key_last($contextStack)]['braceLevel']--;

                    // If the current context's braces are closed
                    if ($contextStack[array_key_last($contextStack)]['braceLevel'] === 0) {
                        $endedContext = array_pop($contextStack);

                        // If this was a named method, finalize it
                        if ($endedContext['name'] !== null && !$endedContext['isClosure']) {
                            $endedContext['end_line'] = $this->getLineNumber($tokens, $i);
                            $methods[] = $endedContext;
                            $currentMethod = null;
                        }
                    }
                }

                // Add token to all open contexts
                foreach ($contextStack as &$ctx) {
                    $ctx['tokens'][] = $token;
                }
                // Also add to the just-ended context if it was closed by this token
                if (isset($endedContext) && isset($endedContext['tokens'])) {
                    $endedContext['tokens'][] = $token;
                    unset($endedContext);
                }
            }
        }

        return $methods;
    }

    /**
     * Calculate cyclomatic complexity from tokens.
     *
     * Complexity = 1 (base) + decision points (if, for, while, case, etc.)
     *
     * @param array<mixed> $tokens Method tokens
     * @return int Cyclomatic complexity
     */
    private function calculateComplexity(array $tokens): int
    {
        $complexity = 1; // Base complexity

        $complexityTokens = [
            T_IF,
            T_ELSEIF,
            T_FOR,
            T_FOREACH,
            T_WHILE,
            T_DO,
            T_CASE,
            T_CATCH,
            T_BOOLEAN_AND,
            T_BOOLEAN_OR,
            T_LOGICAL_AND,
            T_LOGICAL_OR,
        ];

        foreach ($tokens as $token) {
            if (is_array($token) && in_array($token[0], $complexityTokens, true)) {
                $complexity++;
            } elseif ($token === '?') {
                // Ternary operator
                $complexity++;
            }
        }

        return $complexity;
    }

    /**
     * Get line number from token position.
     *
     * @param array<mixed> $tokens All tokens
     * @param int $position Token position
     * @return int Line number
     */
    private function getLineNumber(array $tokens, int $position): int
    {
        for ($i = $position; $i >= 0; $i--) {
            if (is_array($tokens[$i]) && isset($tokens[$i][2])) {
                return $tokens[$i][2];
            }
        }
        return 0;
    }

    /**
     * Generate description for complexity issue.
     *
     * @param string $methodName Method name
     * @param int $complexity Complexity value
     * @return string Description
     */
    private function generateComplexityDescription(string $methodName, int $complexity): string
    {
        return sprintf(
            "Method '%s' has a cyclomatic complexity of %d, exceeding the threshold of %d. " .
            "Consider breaking this method into smaller, focused methods. High complexity " .
            "makes code harder to understand, test, and maintain. Refactor by extracting " .
            "logical segments into separate methods with clear responsibilities.",
            $methodName,
            $complexity,
            $this->complexityThreshold
        );
    }

    /**
     * Calculate risk level based on complexity.
     *
     * @param int $complexity Complexity value
     * @return string Risk level ('low', 'medium', 'high')
     */
    private function calculateRisk(int $complexity): string
    {
        if ($complexity >= 20) {
            return 'high';
        } elseif ($complexity >= 15) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Get file path relative to project root.
     *
     * @param string $filePath Absolute file path
     * @return string Relative path
     */
    private function getRelativePath(string $filePath): string
    {
        return str_replace($this->projectRoot . '/', '', $filePath);
    }
}
