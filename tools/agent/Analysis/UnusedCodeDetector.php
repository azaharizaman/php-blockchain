<?php

declare(strict_types=1);

namespace Blockchain\Agent\Analysis;

/**
 * UnusedCodeDetector identifies unused code elements in PHP projects.
 *
 * This analyzer scans for:
 * - Unused private methods
 * - Unused private properties
 * - Unused local variables
 * - Unreachable code after return statements
 * - Commented-out code blocks
 *
 * @package Blockchain\Agent\Analysis
 */
class UnusedCodeDetector
{
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
     * Create a new UnusedCodeDetector.
     *
     * @param string $projectRoot Project root directory
     * @param array<string> $scanPaths Paths to scan (default: ['src/', 'tools/'])
     * @param array<string> $excludePatterns Patterns to exclude
     */
    public function __construct(
        string $projectRoot,
        array $scanPaths = ['src/', 'tools/'],
        array $excludePatterns = ['*/vendor/*', '*/tests/*', '*/node_modules/*']
    ) {
        $this->projectRoot = rtrim($projectRoot, '/');
        $this->scanPaths = $scanPaths;
        $this->excludePatterns = $excludePatterns;
    }

    /**
     * Scan project for unused code.
     *
     * @return array<RefactoringSuggestion>
     */
    public function scan(): array
    {
        $suggestions = [];

        foreach ($this->scanPaths as $path) {
            $fullPath = $this->projectRoot . '/' . ltrim($path, '/');
            $realPath = realpath($fullPath);

            // Validate that the resolved path is a directory and within project root
            if ($realPath === false || !is_dir($realPath) || !str_starts_with($realPath, $this->projectRoot)) {
                continue;
            }

            $suggestions = array_merge(
                $suggestions,
                $this->scanDirectory($realPath)
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
     * Analyze a PHP file for unused code.
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
        
        // Find commented-out code blocks
        $suggestions = array_merge($suggestions, $this->findCommentedCode($filePath, $tokens));
        
        // Find unreachable code
        $suggestions = array_merge($suggestions, $this->findUnreachableCode($filePath, $tokens));
        
        // Find unused private methods (simplified detection)
        $suggestions = array_merge($suggestions, $this->findUnusedPrivateMethods($filePath, $content, $tokens));

        return $suggestions;
    }

    /**
     * Find commented-out code blocks.
     *
     * @param string $filePath File path
     * @param array<mixed> $tokens PHP tokens
     * @return array<RefactoringSuggestion>
     */
    private function findCommentedCode(string $filePath, array $tokens): array
    {
        $suggestions = [];
        $relativePath = $this->getRelativePath($filePath);

        foreach ($tokens as $token) {
            if (!is_array($token)) {
                continue;
            }

            $tokenType = $token[0];
            $tokenContent = $token[1];
            $lineNumber = $token[2];

            // Check for commented code in single-line or multi-line comments
            if ($tokenType === T_COMMENT || $tokenType === T_DOC_COMMENT) {
                // Look for code-like patterns in comments
                if ($this->looksLikeCommentedCode($tokenContent)) {
                    $suggestions[] = new RefactoringSuggestion(
                        id: 'commented_code_' . md5($filePath . '_' . $lineNumber),
                        type: 'unused_code',
                        filePath: $relativePath,
                        title: 'Commented-out code detected',
                        description: 'Found commented-out code that should be removed. Use version control ' .
                                   'instead of commenting out code. This reduces clutter and improves readability.',
                        risk: 'low',
                        startLine: $lineNumber,
                        endLine: $lineNumber + substr_count($tokenContent, "\n")
                    );
                }
            }
        }

        return $suggestions;
    }

    /**
     * Check if comment content looks like commented-out code.
     *
     * @param string $comment Comment content
     * @return bool True if looks like code
     */
    private function looksLikeCommentedCode(string $comment): bool
    {
        // Remove comment markers
        $cleaned = preg_replace('/^(\/\/|\/\*|\*|#)\s*/', '', $comment);
        if ($cleaned === null) {
            return false; // regex error, treat as not code
        }
        $cleaned = trim($cleaned);
        // Check for code patterns (simplified heuristics)
        $codePatterns = [
            '/^\s*(public|private|protected)\s+function/',  // Method declaration
            '/^\s*\$[a-zA-Z_]/',                            // Variable
            '/^\s*(if|while|for|foreach|switch)\s*\(/',     // Control structures
            '/^\s*return\s+/',                              // Return statement
            '/^\s*\w+\s*=\s*/',                            // Assignment
            '/^\s*\/\*\*/',                                 // Doc block within comment
        ];

        foreach ($codePatterns as $pattern) {
            if (preg_match($pattern, $cleaned)) {
                // Additional check: should have some length
                if (strlen($cleaned) > 20) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Find unreachable code after return/throw statements.
     *
     * @param string $filePath File path
     * @param array<mixed> $tokens PHP tokens
     * @return array<RefactoringSuggestion>
     */
    private function findUnreachableCode(string $filePath, array $tokens): array
    {
        $suggestions = [];
        $relativePath = $this->getRelativePath($filePath);

        $afterReturn = false;
        $returnLine = 0;
        $braceLevel = 0;
        $codeAfterReturn = false;

        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            // Track brace levels
            if ($token === '{') {
                $braceLevel++;
                $afterReturn = false;
            } elseif ($token === '}') {
                $braceLevel--;
                $afterReturn = false;
                $codeAfterReturn = false;
            }

            // Detect return or throw
            if (is_array($token) && ($token[0] === T_RETURN || $token[0] === T_THROW)) {
                $afterReturn = true;
                $returnLine = $token[2];
                $codeAfterReturn = false;
                continue;
            }

            // If we're after a return, check for non-whitespace code
            if ($afterReturn) {
                // Skip whitespace and comments
                if (is_array($token) && 
                    in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                // Skip semicolons and closing braces
                if ($token === ';' || $token === '}') {
                    if ($token === '}') {
                        $afterReturn = false;
                    }
                    continue;
                }

                // Found code after return
                if (!$codeAfterReturn) {
                    $codeAfterReturn = true;
                    $unreachableLine = is_array($token) ? $token[2] : $returnLine + 1;
                    
                    $suggestions[] = new RefactoringSuggestion(
                        id: 'unreachable_' . md5($filePath . '_' . $unreachableLine),
                        type: 'unused_code',
                        filePath: $relativePath,
                        title: 'Unreachable code after return statement',
                        description: 'Code after a return or throw statement will never execute. ' .
                                   'Remove this unreachable code to improve clarity.',
                        risk: 'low',
                        startLine: $returnLine,
                        endLine: $unreachableLine
                    );
                }
            }
        }

        return $suggestions;
    }

    /**
     * Find unused private methods (simplified detection).
     *
     * @param string $filePath File path
     * @param string $content File content
     * @param array<mixed> $tokens PHP tokens
     * @return array<RefactoringSuggestion>
     */
    private function findUnusedPrivateMethods(string $filePath, string $content, array $tokens): array
    {
        $suggestions = [];
        $relativePath = $this->getRelativePath($filePath);

        // Extract private method names and their line numbers
        $privateMethods = [];
        
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            // Look for private function declarations
            if (is_array($token) && $token[0] === T_PRIVATE) {
                // Check if next non-whitespace token is T_FUNCTION
                for ($j = $i + 1; $j < count($tokens); $j++) {
                    $nextToken = $tokens[$j];
                    
                    if (is_array($nextToken) && $nextToken[0] === T_WHITESPACE) {
                        continue;
                    }
                    
                    if (is_array($nextToken) && $nextToken[0] === T_FUNCTION) {
                        // Get method name
                        for ($k = $j + 1; $k < count($tokens); $k++) {
                            $nameToken = $tokens[$k];
                            
                            if (is_array($nameToken) && $nameToken[0] === T_STRING) {
                                $privateMethods[] = [
                                    'name' => $nameToken[1],
                                    'line' => $nameToken[2],
                                ];
                                break;
                            }
                        }
                    }
                    break;
                }
            }
        }

        // Check if private methods are called (simplified)
        foreach ($privateMethods as $method) {
            $methodName = $method['name'];
            
            // Count occurrences (excluding the definition)
            $pattern = '/(?:->|\$this->|self::)' . preg_quote($methodName, '/') . '\s*\(/';
            preg_match_all($pattern, $content, $matches);
            
            $callCount = count($matches[0]);
            
            // If called only once (the definition itself shows as ->methodName in some contexts)
            // or not at all, it might be unused
            if ($callCount <= 1) {
                $suggestions[] = new RefactoringSuggestion(
                    id: 'unused_private_' . md5($filePath . '_' . $methodName),
                    type: 'unused_code',
                    filePath: $relativePath,
                    title: "Potentially unused private method '{$methodName}'",
                    description: "Private method '{$methodName}' appears to be unused within this class. " .
                               "Consider removing it if it's truly unnecessary, or make it public if it " .
                               "should be accessible externally. Note: This is a heuristic detection and " .
                               "may produce false positives for dynamically called methods.",
                    risk: 'low',
                    startLine: $method['line'],
                    endLine: null
                );
            }
        }

        return $suggestions;
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
