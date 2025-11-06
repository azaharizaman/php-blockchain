# 1. PRD Analysis and Decomposition (Structure Reinforced)
- **Goal:** Decompose the comprehensive PRD (Product Requirements Document) found in the root directory into manageable 'Sub-PRD' files, following the Epics -> Features -> Tasks hierarchy previously defined.
- **Input:** Read and analyze the full PRD document (Assume the filename is `PRD.md` or adjust the filename path if different, e.g., `./docs/PRD.md`).
- **Output Location:** Store the new breakdown files in the dedicated directory: `./docs/prd/`.
- **Output Format / Acceptance Criteria:**
    1.  Create a high-level overview file: `./docs/prd/00-PRD-BREAKDOWN.md`. This file should contain a single, numbered list of all major Epics identified in the PRD.
    2.  For each major Epic (e.g., "Core Utilities", "Ethereum Driver"), create a corresponding markdown file (e.g., `./docs/prd/01-CORE-UTILITIES-EPIC.md`).
    3.  **Mandatory Structure for each Epic file (e.g., 01-CORE-UTILITIES-EPIC.md):**
        * **Heading:** `## Epic: [Epic Name]`
        * **Section 1:** `### 🎯 Goal & Value Proposition` (A brief summary of what this Epic achieves.)
        * **Section 2:** `### ⚙️ Features & Requirements` (A detailed, numbered list of every feature belonging to this Epic.)
        * **Section 3:** `### 🤝 Module Mapping & Dependencies` (Specify the PHP namespace/module affected, e.g., `Zaman\Blockchain\Core`, and list any other Epics this one depends on.)
        * **Section 4:** `### ✅ Acceptance Criteria` (High-level criteria for marking the Epic as complete.)

# 2. Task Checklist Generation (Feature-to-Module Mapping)
- **Goal:** Create an initial, actionable checklist for the first two modules identified: "Core Utilities" and "Ethereum Driver".
- **Input:** Use the feature lists defined in the new Sub-PRD markdown files.
- **Output Location:** Store the checklists in the designated project management directory: `./tasks/`.
- **Output Format:**
    1.  Create a YAML file for each of the two initial modules:
        * `./tasks/CORE_UTILITIES_TASKS.yaml`
        * `./tasks/ETHEREUM_DRIVER_TASKS.yaml`
    2.  The YAML schema for each file must strictly follow this structure:

    ```yaml
    # tasks/CORE_UTILITIES_TASKS.yaml
    module: CoreUtilities
    epic: PHP Blockchain Core Utilities
    tasks:
      - id: CU-001
        description: [Detailed Task for the Feature]
        status: TODO
        priority: High
        estimated_effort: 2h
        depends_on: []
    # ... continue for all features in this Epic
    ```

- **Constraint**: Ensure the generated task files are syntactically correct YAML.
- **Final Action**: After generating all files, review the repository status and confirm the new docs/prd/ and tasks/ directories are populated and ready for committing.