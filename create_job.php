<?php
/**
 * create_job.php — O*NET Job Profile Builder
 * @author Vaishnavi Pushparaj Samani
 * 
 * Database: talentsync_db
 * Tables used: jobs, job_skills, occupation_data, skills
 */

session_start();
require_once __DIR__ . '/config/db.php';

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ══════════════════════════════════════════════════════════════════════════════
// TEMPORARY: Bypass login for testing (remove when Lee finishes login.php)
// ══════════════════════════════════════════════════════════════════════════════
//$userId = 2;  // Hardcoded test user


// ── Session guard (COMMENTED OUT FOR TESTING) ─────────────────────────────────
if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
    $userId = (int)$_SESSION['user']['user_id'];
} elseif (isset($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
} else {
    header('Location: login.php');
    exit;
}


// ── Edit mode: load existing job ─────────────────────────────────────────────
$editMode   = false;
$editJob    = null;
$editSkills = [];

$jobIdParam = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
if ($jobIdParam > 0) {
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE job_id = ? AND user_id = ? LIMIT 1");
    $stmt->bind_param('ii', $jobIdParam, $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $editMode = true;
        $editJob  = $row;
        
        // Load skills
        $sk = $conn->prepare("SELECT skill_name, source FROM job_skills WHERE job_id = ?");
        $sk->bind_param('i', $jobIdParam);
        $sk->execute();
        $skRes = $sk->get_result();
        while ($sr = $skRes->fetch_assoc()) $editSkills[] = $sr;
        $sk->close();
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo $editMode ? 'Edit Job' : 'Create Job'; ?> – TalentSync</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js"></script>

    <style>
        .container { max-width: 860px; margin: 0 auto; padding: 36px 24px 100px; }
        .page-header { margin-bottom: 32px; }
        .page-title { font-size: 26px; font-weight: 800; color: #0f172a; margin: 0 0 6px; }
        .page-subtitle { font-size: 15px; color: #64748b; margin: 0; }
        .back-link { display: inline-flex; align-items: center; gap: 6px; font-size: 14px; color: #2563eb; text-decoration: none; font-weight: 600; margin-bottom: 24px; }
        .back-link:hover { text-decoration: underline; }

        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 32px; box-shadow: 0 1px 6px rgba(0,0,0,.05); margin-bottom: 24px; }
        .section-title { font-size: 15px; font-weight: 700; color: #0f172a; margin: 0 0 20px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; }
        .step-badge { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: #2563eb; color: #fff; font-size: 12px; font-weight: 700; margin-right: 8px; }

        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 7px; }
        .form-label .required { color: #ef4444; margin-left: 2px; }
        .form-control { width: 100%; box-sizing: border-box; height: 42px; padding: 0 14px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 14px; color: #0f172a; background: #f8fafc; outline: none; transition: border-color .2s; }
        .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); background: #fff; }
        textarea.form-control { height: auto; padding: 12px 14px; resize: vertical; }
        .hint { font-size: 12px; color: #94a3b8; margin-top: 5px; }

        .select2-container--default .select2-selection--single { height: 42px; border: 1px solid #cbd5e1; border-radius: 10px; background: #f8fafc; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 42px; padding-left: 14px; font-size: 14px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 42px; }
        .select2-container { width: 100% !important; }

        #skills-container { min-height: 80px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 10px; background: #f8fafc; display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 8px; }
        .skill-tag { display: inline-flex; align-items: center; gap: 6px; background: #dbeafe; color: #1e40af; border-radius: 999px; padding: 4px 12px; font-size: 13px; font-weight: 500; }
        .skill-tag.user-defined { background: #fef9c3; color: #854d0e; }
        .skill-tag button { background: none; border: none; cursor: pointer; padding: 0; line-height: 1; font-size: 15px; color: inherit; opacity: .7; }
        .skill-tag button:hover { opacity: 1; }

        .skills-add-row { display: flex; gap: 10px; align-items: center; }
        #custom-skill-input { flex: 1; height: 38px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; }
        #custom-skill-input:focus { border-color: #2563eb; }

        .radio-group { display: flex; gap: 12px; flex-wrap: wrap; }
        .radio-option { display: flex; align-items: center; gap: 8px; padding: 10px 18px; border: 2px solid #e2e8f0; border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 500; transition: border-color .2s; }
        .radio-option input[type="radio"] { accent-color: #2563eb; }
        .radio-option:has(input:checked) { border-color: #2563eb; background: #eff6ff; color: #1e40af; }

        .form-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 8px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; height: 42px; padding: 0 22px; border-radius: 10px; font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; border: 1px solid #cbd5e1; background: #f8fafc; color: #0f172a; transition: background .2s; }
        .btn:hover { background: #f1f5f9; }
        .btn-primary { background: #2563eb; color: #fff; border-color: #2563eb; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-sm { height: 34px; padding: 0 14px; font-size: 13px; }

        .onet-loading { font-size: 13px; color: #94a3b8; padding: 4px 0; display: none; }
        .divider { border: none; border-top: 1px solid #f1f5f9; margin: 20px 0; }
        .flash { padding: 12px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .flash-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<main class="container">
    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

    <header class="page-header">
        <h1 class="page-title"><?php echo $editMode ? 'Edit Job Profile' : 'Create Job Profile'; ?></h1>
        <p class="page-subtitle">Use the O*NET Translator to pre-fill skills from government data.</p>
    </header>

    <div id="flash-area"></div>

    <form id="job-form" method="POST" action="api/save_job.php">
        <?php if ($editMode): ?>
            <input type="hidden" name="job_id" value="<?php echo $editJob['job_id']; ?>" />
        <?php endif; ?>

        <!-- STEP 1: O*NET Search -->
        <div class="card">
            <h2 class="section-title">
                <span class="step-badge">1</span>
                O*NET Occupation Lookup
            </h2>
            <div class="form-group">
                <label class="form-label">Search Occupation</label>
                <select id="onet-search" name="onet_soc_code">
                    <?php if ($editMode && $editJob['onet_soc_code']): ?>
                        <option value="<?php echo e($editJob['onet_soc_code']); ?>" selected>
                            <?php echo e($editJob['onet_soc_code']); ?>
                        </option>
                    <?php else: ?>
                        <option value="">— Type to search (e.g. "Software Developer") —</option>
                    <?php endif; ?>
                </select>
                <p class="hint">Selecting an occupation will auto-populate skills.</p>
                <div class="onet-loading" id="onet-loading">⏳ Loading skills...</div>
            </div>
        </div>

        <!-- STEP 2: Job Details -->
        <div class="card">
            <h2 class="section-title">
                <span class="step-badge">2</span>
                Job Details
            </h2>
            <div class="form-group">
                <label class="form-label">Job Title <span class="required">*</span></label>
                <input id="job_title" type="text" name="job_title" class="form-control"
                       placeholder="e.g. Senior Software Engineer"
                       value="<?php echo e($editJob['job_title'] ?? ''); ?>" required />
                <p class="hint">This is the title candidates will see.</p>
            </div>
            <div class="form-group">
                <label class="form-label">Job Description</label>
                <textarea name="description" class="form-control" rows="5"
                          placeholder="Describe the role, responsibilities, team..."><?php echo e($editJob['description'] ?? ''); ?></textarea>
            </div>
        </div>

        <!-- STEP 3: Skills -->
        <div class="card">
            <h2 class="section-title">
                <span class="step-badge">3</span>
                Required Skills
            </h2>
            <div id="skills-container">
                <?php foreach ($editSkills as $sk):
                    $cls = ($sk['source'] === 'User_Defined') ? 'skill-tag user-defined' : 'skill-tag';
                ?>
                    <span class="<?php echo $cls; ?>" data-skill="<?php echo e($sk['skill_name']); ?>">
                        <?php echo e($sk['skill_name']); ?>
                        <button type="button" onclick="removeSkill(this)">×</button>
                    </span>
                <?php endforeach; ?>
            </div>
            <p class="hint" id="skills-hint">
                <?php echo empty($editSkills) ? 'No skills yet. Select O*NET occupation or add custom skills.' : ''; ?>
            </p>

            <div id="skills-hidden-inputs">
                <?php foreach ($editSkills as $sk): ?>
                    <input type="hidden" name="skills[]" value="<?php echo e($sk['skill_name']); ?>" />
                <?php endforeach; ?>
            </div>

            <hr class="divider" />

            <p class="form-label">Add Custom Skill</p>
            <div class="skills-add-row">
                <input id="custom-skill-input" type="text" placeholder="e.g. React, AWS, Terraform…" />
                <button type="button" class="btn btn-sm" onclick="addCustomSkill()">+ Add</button>
            </div>
            <p class="hint">Custom tags fill gaps not covered by O*NET.</p>
        </div>

        <!-- STEP 4: Status -->
        <div class="card">
            <h2 class="section-title">
                <span class="step-badge">4</span>
                Publication Status
            </h2>
            <div class="radio-group">
                <?php
                $currentStatus = $editJob['status'] ?? 'Draft';
                foreach (['Draft' => 'Save as Draft', 'Open' => 'Publish as Open', 'Closed' => 'Mark as Closed'] as $val => $label):
                ?>
                    <label class="radio-option">
                        <input type="radio" name="status" value="<?php echo $val; ?>"
                               <?php echo ($currentStatus === $val) ? 'checked' : ''; ?> />
                        <?php echo $label; ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="hint" style="margin-top:12px;">Draft jobs are visible only to HR.</p>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <?php echo $editMode ? '💾 Save Changes' : '💾 Save Job'; ?>
            </button>
            <a href="dashboard.php" class="btn">Cancel</a>
        </div>
    </form>
</main>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#onet-search').select2({
        placeholder: '— Type to search (e.g. "Software Developer") —',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: 'api/search_onet.php',
            dataType: 'json',
            delay: 300,
            data: function(params) { return { q: params.term }; },
            processResults: function(data) {
                return {
                    results: data.map(function(item) {
                        return { id: item.code, text: item.title + ' (' + item.code + ')' };
                    })
                };
            },
            cache: true
        }
    });

    $('#onet-search').on('select2:select', function(e) {
        fetchOnetSkills(e.params.data.id);
    });

    $('#onet-search').on('select2:clear', function() {
        document.querySelectorAll('.skill-tag:not(.user-defined)').forEach(function(tag) {
            tag.remove();
        });
        syncHiddenInputs();
    });
});

function fetchOnetSkills(socCode) {
    var loading = document.getElementById('onet-loading');
    loading.style.display = 'block';

    fetch('api/get_skills.php?soc_code=' + encodeURIComponent(socCode))
        .then(function(r) { return r.json(); })
        .then(function(skills) {
            loading.style.display = 'none';
            document.querySelectorAll('.skill-tag:not(.user-defined)').forEach(function(t) { t.remove(); });

            if (!Array.isArray(skills) || skills.length === 0) {
                showHint('No O*NET skills found. Add custom skills below.');
                return;
            }

            skills.forEach(function(skillName) {
                addSkillTag(skillName, false);
            });
            showHint('');
            syncHiddenInputs();
        })
        .catch(function() {
            loading.style.display = 'none';
            showHint('Could not load skills. Check database connection.');
        });
}

function addSkillTag(skillName, isUserDefined) {
    skillName = skillName.trim();
    if (!skillName) return;

    var existing = document.querySelectorAll('#skills-container .skill-tag');
    for (var i = 0; i < existing.length; i++) {
        if (existing[i].dataset.skill.toLowerCase() === skillName.toLowerCase()) return;
    }

    var container = document.getElementById('skills-container');
    var span = document.createElement('span');
    span.className = 'skill-tag' + (isUserDefined ? ' user-defined' : '');
    span.dataset.skill = skillName;
    span.innerHTML = skillName + ' <button type="button" onclick="removeSkill(this)">×</button>';
    container.appendChild(span);
}

function removeSkill(btn) {
    btn.parentElement.remove();
    syncHiddenInputs();
    var container = document.getElementById('skills-container');
    if (container.querySelectorAll('.skill-tag').length === 0) {
        showHint('No skills added yet.');
    }
}

function addCustomSkill() {
    var input = document.getElementById('custom-skill-input');
    var val = input.value.trim();
    if (!val) return;
    addSkillTag(val, true);
    input.value = '';
    showHint('');
    syncHiddenInputs();
}

document.getElementById('custom-skill-input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        addCustomSkill();
    }
});

function syncHiddenInputs() {
    var hiddenDiv = document.getElementById('skills-hidden-inputs');
    hiddenDiv.innerHTML = '';
    document.querySelectorAll('#skills-container .skill-tag').forEach(function(tag) {
        var inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'skills[]';
        inp.value = tag.dataset.skill;
        hiddenDiv.appendChild(inp);
    });
}

function showHint(msg) {
    document.getElementById('skills-hint').textContent = msg;
}

document.getElementById('job-form').addEventListener('submit', function(e) {
    var title = document.getElementById('job_title').value.trim();
    if (!title) {
        e.preventDefault();
        var flash = document.getElementById('flash-area');
        flash.innerHTML = '<div class="flash flash-error">⚠ Job Title is required.</div>';
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }
    syncHiddenInputs();
});
</script>
</body>
</html>