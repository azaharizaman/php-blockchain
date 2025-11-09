<?php

declare(strict_types=1);

namespace Blockchain\Agent\Analysis;

/**
 * RefactoringSuggestion represents a single refactoring suggestion.
 *
 * This data structure encapsulates all information about a suggested
 * code improvement including location, rationale, risk, and patch data.
 *
 * @package Blockchain\Agent\Analysis
 */
class RefactoringSuggestion
{
    /**
     * Unique identifier for this suggestion.
     */
    private string $id;

    /**
     * Type of refactoring (e.g., 'complexity', 'unused_code', 'duplication').
     */
    private string $type;

    /**
     * File path relative to project root.
     */
    private string $filePath;

    /**
     * Starting line number (optional).
     */
    private ?int $startLine;

    /**
     * Ending line number (optional).
     */
    private ?int $endLine;

    /**
     * Human-readable title/summary.
     */
    private string $title;

    /**
     * Detailed description and rationale.
     */
    private string $description;

    /**
     * Risk level: 'low', 'medium', 'high'.
     */
    private string $risk;

    /**
     * Current code complexity metric (if applicable).
     */
    private ?float $currentMetric;

    /**
     * Expected improved metric after refactoring.
     */
    private ?float $expectedMetric;

    /**
     * Git patch content for this suggestion.
     */
    private ?string $patch;

    /**
     * Create a new RefactoringSuggestion.
     *
     * @param string $id Unique identifier
     * @param string $type Type of refactoring
     * @param string $filePath File path relative to project root
     * @param string $title Title/summary
     * @param string $description Detailed description
     * @param string $risk Risk level ('low', 'medium', 'high')
     * @param int|null $startLine Starting line number
     * @param int|null $endLine Ending line number
     * @param float|null $currentMetric Current metric value
     * @param float|null $expectedMetric Expected metric after refactoring
     * @param string|null $patch Git patch content
     */
    public function __construct(
        string $id,
        string $type,
        string $filePath,
        string $title,
        string $description,
        string $risk = 'medium',
        ?int $startLine = null,
        ?int $endLine = null,
        ?float $currentMetric = null,
        ?float $expectedMetric = null,
        ?string $patch = null
    ) {
        $this->id = $id;
        $this->type = $type;
        $this->filePath = $filePath;
        $this->title = $title;
        $this->description = $description;
        $this->risk = $risk;
        $this->startLine = $startLine;
        $this->endLine = $endLine;
        $this->currentMetric = $currentMetric;
        $this->expectedMetric = $expectedMetric;
        $this->patch = $patch;
    }

    /**
     * Get suggestion ID.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get refactoring type.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get file path.
     *
     * @return string
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Get starting line number.
     *
     * @return int|null
     */
    public function getStartLine(): ?int
    {
        return $this->startLine;
    }

    /**
     * Get ending line number.
     *
     * @return int|null
     */
    public function getEndLine(): ?int
    {
        return $this->endLine;
    }

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get risk level.
     *
     * @return string
     */
    public function getRisk(): string
    {
        return $this->risk;
    }

    /**
     * Get current metric value.
     *
     * @return float|null
     */
    public function getCurrentMetric(): ?float
    {
        return $this->currentMetric;
    }

    /**
     * Get expected metric after refactoring.
     *
     * @return float|null
     */
    public function getExpectedMetric(): ?float
    {
        return $this->expectedMetric;
    }

    /**
     * Get git patch content.
     *
     * @return string|null
     */
    public function getPatch(): ?string
    {
        return $this->patch;
    }

    /**
     * Set git patch content.
     *
     * @param string $patch Patch content
     * @return void
     */
    public function setPatch(string $patch): void
    {
        $this->patch = $patch;
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'file_path' => $this->filePath,
            'start_line' => $this->startLine,
            'end_line' => $this->endLine,
            'title' => $this->title,
            'description' => $this->description,
            'risk' => $this->risk,
            'current_metric' => $this->currentMetric,
            'expected_metric' => $this->expectedMetric,
            'has_patch' => $this->patch !== null,
        ];
    }
}
