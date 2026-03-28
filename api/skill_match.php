<?php
/**
 * skill_match.php — Skill Matching Engine
 *
 * Implements two analytical methods:
 *
 * 1. JACCARD SIMILARITY INDEX
 *    Measures set overlap between applicant skills and job-required skills.
 *    Formula: J(A,B) = |A ∩ B| / |A ∪ B|
 *    Range: 0.0 (no overlap) to 1.0 (perfect match)
 *
 * 2. WEIGHTED SKILL SCORE (O*NET Importance-Based)
 *    Uses O*NET's Importance scale (scale_id = 'IM') data_value as weights.
 *    Formula: WS = Σ(matched_skill_weight) / Σ(all_required_skill_weights)
 *    Gives higher credit for matching high-importance skills.
 *
 * Both scores are combined into a COMPOSITE MATCH SCORE:
 *    Composite = (0.4 × Jaccard) + (0.6 × Weighted)
 *    The weighted score gets more influence because skill importance matters.
 */

require_once __DIR__ . '/../config/db.php';

/**
 * Normalize a skill name for comparison:
 * - lowercase, trim whitespace, remove special chars
 */
function normalize_skill(string $skill): string {
    $skill = strtolower(trim($skill));
    $skill = preg_replace('/[^a-z0-9\s\+\#\.]/', '', $skill);
    $skill = preg_replace('/\s+/', ' ', $skill);
    return $skill;
}

/**
 * Parse comma-separated skills string into a normalized set
 */
function parse_skills(string $skillsStr): array {
    if (trim($skillsStr) === '') return [];
    $skills = array_map('normalize_skill', explode(',', $skillsStr));
    return array_values(array_unique(array_filter($skills)));
}

/**
 * Calculate Jaccard Similarity Index between two skill sets
 * J(A,B) = |A ∩ B| / |A ∪ B|
 *
 * @param array $setA Applicant skills (normalized)
 * @param array $setB Job-required skills (normalized)
 * @return float Score between 0.0 and 1.0
 */
function jaccard_similarity(array $setA, array $setB): float {
    if (empty($setA) && empty($setB)) return 0.0;

    $intersection = array_intersect($setA, $setB);
    $union = array_unique(array_merge($setA, $setB));

    if (count($union) === 0) return 0.0;

    return count($intersection) / count($union);
}

/**
 * Calculate Weighted Skill Score using O*NET importance values
 * WS = Σ(matched_skill_weight) / Σ(all_required_skill_weights)
 *
 * For tech skills (no O*NET weight), a default weight of 3.0 is used.
 * For general skills, the O*NET data_value (Importance scale) is used.
 *
 * @param array $applicantSkills Normalized applicant skill names
 * @param array $jobSkills Array of ['skill_name' => ..., 'skill_type' => ..., 'weight' => ...]
 * @return float Score between 0.0 and 1.0
 */
function weighted_skill_score(array $applicantSkills, array $jobSkills): float {
    if (empty($jobSkills)) return 0.0;

    $totalWeight = 0.0;
    $matchedWeight = 0.0;

    foreach ($jobSkills as $js) {
        $weight = (float)($js['weight'] ?? 3.0);
        $totalWeight += $weight;

        $normalizedJobSkill = normalize_skill($js['skill_name']);

        // Check if applicant has this skill (fuzzy: partial match)
        foreach ($applicantSkills as $as) {
            if ($normalizedJobSkill === $as
                || strpos($as, $normalizedJobSkill) !== false
                || strpos($normalizedJobSkill, $as) !== false) {
                $matchedWeight += $weight;
                break;
            }
        }
    }

    if ($totalWeight === 0.0) return 0.0;

    return $matchedWeight / $totalWeight;
}

/**
 * Calculate composite match score for an applicant against a job
 *
 * @param int $jobId The job to match against
 * @param string $applicantSkillsStr Comma-separated applicant skills
 * @param mysqli $conn Database connection
 * @return array ['jaccard' => float, 'weighted' => float, 'composite' => float, 'matched' => [], 'missing' => []]
 */
function calculate_match_score(int $jobId, string $applicantSkillsStr, mysqli $conn): array {
    $applicantSkills = parse_skills($applicantSkillsStr);

    if (empty($applicantSkills)) {
        return [
            'jaccard'    => 0.0,
            'weighted'   => 0.0,
            'composite'  => 0.0,
            'matched'    => [],
            'missing'    => [],
            'applicant_count' => 0,
            'job_count'  => 0,
        ];
    }

    // Fetch job skills for this job
    $stmt = $conn->prepare("
        SELECT skill_name, skill_type
        FROM job_skills
        WHERE job_id = ?
    ");
    $stmt->bind_param("i", $jobId);
    $stmt->execute();
    $res = $stmt->get_result();

    $jobSkillsRaw = [];
    $jobSkillNames = [];
    while ($row = $res->fetch_assoc()) {
        // Default weight: tech skills = 3.0, general skills = 3.5 (slightly more important)
        $row['weight'] = ($row['skill_type'] === 'general') ? 3.5 : 3.0;
        $jobSkillsRaw[] = $row;
        $jobSkillNames[] = normalize_skill($row['skill_name']);
    }
    $stmt->close();

    // Try to get O*NET importance weights for general skills
    $job_stmt = $conn->prepare("SELECT onet_soc_code FROM jobs WHERE job_id = ? LIMIT 1");
    $job_stmt->bind_param("i", $jobId);
    $job_stmt->execute();
    $job_res = $job_stmt->get_result();
    $jobRow = $job_res->fetch_assoc();
    $job_stmt->close();

    if ($jobRow && $jobRow['onet_soc_code']) {
        $onetCode = $jobRow['onet_soc_code'];
        $w_stmt = $conn->prepare("
            SELECT cmr.element_name, s.data_value
            FROM skills s
            JOIN content_model_reference cmr ON s.element_id = cmr.element_id
            WHERE s.onetsoc_code = ? AND s.scale_id = 'IM'
        ");
        $w_stmt->bind_param("s", $onetCode);
        $w_stmt->execute();
        $w_res = $w_stmt->get_result();

        $onetWeights = [];
        while ($w = $w_res->fetch_assoc()) {
            $onetWeights[normalize_skill($w['element_name'])] = (float)$w['data_value'];
        }
        $w_stmt->close();

        // Apply O*NET weights to matching job skills
        foreach ($jobSkillsRaw as &$js) {
            $normalized = normalize_skill($js['skill_name']);
            if (isset($onetWeights[$normalized])) {
                $js['weight'] = $onetWeights[$normalized];
            }
        }
        unset($js);
    }

    // --- ALGORITHM 1: Jaccard Similarity ---
    $jaccard = jaccard_similarity($applicantSkills, $jobSkillNames);

    // --- ALGORITHM 2: Weighted Skill Score ---
    $weighted = weighted_skill_score($applicantSkills, $jobSkillsRaw);

    // --- COMPOSITE SCORE ---
    // 40% Jaccard (set overlap) + 60% Weighted (importance-adjusted)
    $composite = (0.4 * $jaccard) + (0.6 * $weighted);

    // Determine matched and missing skills
    $matched = [];
    $missing = [];
    foreach ($jobSkillNames as $js) {
        $found = false;
        foreach ($applicantSkills as $as) {
            if ($js === $as || strpos($as, $js) !== false || strpos($js, $as) !== false) {
                $found = true;
                break;
            }
        }
        if ($found) {
            $matched[] = $js;
        } else {
            $missing[] = $js;
        }
    }

    return [
        'jaccard'         => round($jaccard, 4),
        'weighted'        => round($weighted, 4),
        'composite'       => round($composite, 4),
        'matched'         => $matched,
        'missing'         => $missing,
        'applicant_count' => count($applicantSkills),
        'job_count'       => count($jobSkillNames),
    ];
}

// --- API endpoint (optional: call directly for JSON response) ---
if (basename($_SERVER['PHP_SELF']) === 'skill_match.php') {
    header('Content-Type: application/json');
    session_start();

    $jobId = (int)($_GET['job_id'] ?? 0);
    $appId = (int)($_GET['application_id'] ?? 0);

    if ($jobId <= 0 || $appId <= 0) {
        echo json_encode(['error' => 'Missing job_id or application_id']);
        exit;
    }

    // Fetch applicant's skills from application
    $stmt = $conn->prepare("SELECT skills FROM applications WHERE application_id = ?");
    $stmt->bind_param("i", $appId);
    $stmt->execute();
    $res = $stmt->get_result();
    $app = $res->fetch_assoc();
    $stmt->close();

    if (!$app) {
        echo json_encode(['error' => 'Application not found']);
        exit;
    }

    $result = calculate_match_score($jobId, $app['skills'] ?? '', $conn);
    echo json_encode($result);
}
