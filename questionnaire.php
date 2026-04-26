<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId   = (int)$_SESSION['user_id'];
$fullName = $_SESSION['full_name'] ?? 'Candidate';
$role     = $_SESSION['role'] ?? '';

if ($role !== 'Applicant' && $role !== 'applicant') {
    header("Location: dashboard_hr.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Job Recommendation Questionnaire — TalentSync</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%); min-height: 100vh; }

        .wiz-shell { max-width: 760px; margin: 0 auto; padding: 32px 24px 80px; }

        /* Top bar */
        .wiz-topbar { display: flex; align-items: center; gap: 14px; margin-bottom: 32px; }
        .wiz-back { font-size: 14px; color: #64748b; text-decoration: none; font-weight: 600; padding: 6px 12px; border-radius: 8px; transition: background .15s; }
        .wiz-back:hover { background: #e2e8f0; }
        .wiz-progress-wrap { flex: 1; }
        .wiz-progress-text { font-size: 11px; font-weight: 700; color: #64748b; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 6px; }
        .wiz-progress-track { height: 6px; background: #e2e8f0; border-radius: 999px; overflow: hidden; }
        .wiz-progress-fill { height: 100%; background: linear-gradient(90deg, #00bfa5, #2563eb); border-radius: 999px; transition: width .4s cubic-bezier(.2,.8,.2,1); }

        /* Step card */
        .wiz-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 24px; padding: 48px 40px; box-shadow: 0 12px 40px rgba(15,42,79,.06); position: relative; min-height: 380px; display: flex; flex-direction: column; }
        .wiz-step { display: none; animation: fadeInUp .4s cubic-bezier(.2,.8,.2,1); }
        .wiz-step.active { display: flex; flex-direction: column; flex: 1; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .wiz-step-num { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 700; color: #00bfa5; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 12px; }
        .wiz-step-num::before { content: ""; display: inline-block; width: 18px; height: 2px; background: #00bfa5; }

        .wiz-question { font-size: 28px; font-weight: 800; color: #0f172a; line-height: 1.2; margin: 0 0 8px; }
        .wiz-hint { font-size: 15px; color: #64748b; margin: 0 0 32px; line-height: 1.5; }

        /* Inputs */
        .wiz-input, .wiz-textarea {
            width: 100%; padding: 18px 20px; font-size: 18px; color: #0f172a;
            border: 2px solid #e2e8f0; border-radius: 14px; background: #f8fafc;
            font-family: inherit; outline: none; transition: all .2s; box-sizing: border-box;
        }
        .wiz-textarea { resize: vertical; min-height: 120px; line-height: 1.5; }
        .wiz-input:focus, .wiz-textarea:focus { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 4px rgba(37,99,235,.08); }

        /* Choice cards (for enum questions) */
        .wiz-choices { display: grid; gap: 12px; }
        .wiz-choices.cols-2 { grid-template-columns: repeat(2, 1fr); }
        .wiz-choices.cols-3 { grid-template-columns: repeat(3, 1fr); }
        .wiz-choices.cols-4 { grid-template-columns: repeat(2, 1fr); }
        @media (min-width: 720px) { .wiz-choices.cols-4 { grid-template-columns: repeat(4, 1fr); } }

        .wiz-choice {
            display: block; padding: 22px 20px; background: #fff; border: 2px solid #e2e8f0;
            border-radius: 16px; cursor: pointer; transition: all .2s; text-align: left;
            position: relative;
        }
        .wiz-choice:hover { border-color: #94a3b8; transform: translateY(-1px); }
        .wiz-choice input[type="radio"] { position: absolute; opacity: 0; pointer-events: none; }
        .wiz-choice .choice-icon { font-size: 28px; line-height: 1; margin-bottom: 8px; display: block; }
        .wiz-choice .choice-title { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 2px; }
        .wiz-choice .choice-desc { font-size: 13px; color: #64748b; line-height: 1.4; }
        .wiz-choice.selected,
        .wiz-choice:has(input:checked) {
            border-color: #2563eb; background: #eff6ff; box-shadow: 0 4px 14px rgba(37,99,235,.12);
        }
        .wiz-choice.selected .choice-title,
        .wiz-choice:has(input:checked) .choice-title { color: #1e40af; }

        /* Checkmark badge — only shown for checkbox cards when checked */
        .choice-check {
            position: absolute; top: 12px; right: 12px;
            width: 22px; height: 22px; border-radius: 50%;
            background: #2563eb; color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700;
            opacity: 0; transform: scale(.5);
            transition: all .2s cubic-bezier(.2, 1.4, .5, 1);
        }
        .wiz-choice:has(input[type="checkbox"]:checked) .choice-check {
            opacity: 1; transform: scale(1);
        }

        /* Skip link */
        .wiz-skip { display: inline-block; font-size: 13px; color: #94a3b8; text-decoration: none; font-weight: 600; margin-top: 8px; }
        .wiz-skip:hover { color: #64748b; text-decoration: underline; }

        /* Footer nav */
        .wiz-nav { display: flex; gap: 12px; align-items: center; margin-top: auto; padding-top: 32px; }
        .wiz-btn {
            padding: 14px 28px; border-radius: 12px; font-size: 15px; font-weight: 700;
            border: none; cursor: pointer; transition: all .15s; text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .wiz-btn-primary { background: #0f2a4f; color: #fff; }
        .wiz-btn-primary:hover { background: #1e3a5f; transform: translateY(-1px); }
        .wiz-btn-primary:disabled { background: #cbd5e1; cursor: not-allowed; transform: none; }
        .wiz-btn-secondary { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .wiz-btn-secondary:hover { background: #e2e8f0; }
        .wiz-nav-spacer { flex: 1; }
        .wiz-key-hint { font-size: 12px; color: #94a3b8; margin-right: 8px; }
        .wiz-key-hint kbd { background: #fff; border: 1px solid #cbd5e1; border-radius: 4px; padding: 1px 6px; font-family: inherit; font-size: 11px; box-shadow: 0 1px 0 #cbd5e1; }

        /* Error message */
        .wiz-error { color: #dc2626; font-size: 13px; font-weight: 600; margin-top: 10px; padding: 10px 14px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; display: none; }
        .wiz-error.show { display: block; animation: shake .4s; }
        @keyframes shake {
            0%,100% { transform: translateX(0); }
            25% { transform: translateX(-4px); }
            75% { transform: translateX(4px); }
        }

        /* Final loading + summary states */
        .wiz-loading { text-align: center; padding: 60px 20px; }
        .wiz-loading-spinner { width: 56px; height: 56px; border: 4px solid #e2e8f0; border-top-color: #2563eb; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .wiz-loading h3 { font-size: 18px; color: #0f172a; margin: 0 0 6px; }
        .wiz-loading p { color: #64748b; font-size: 14px; }

        /* Mobile */
        @media (max-width: 640px) {
            .wiz-shell { padding: 16px 12px 40px; }
            .wiz-card { padding: 28px 20px; border-radius: 18px; }
            .wiz-question { font-size: 22px; }
            .wiz-hint { font-size: 14px; margin-bottom: 24px; }
            .wiz-input, .wiz-textarea { font-size: 16px; padding: 14px 16px; }
            .wiz-choices.cols-2, .wiz-choices.cols-3 { grid-template-columns: 1fr; }
            .wiz-choice { padding: 16px; }
            .wiz-btn { padding: 12px 18px; font-size: 14px; }
            .wiz-nav { flex-wrap: wrap; }
            .wiz-key-hint { display: none; }
        }
    </style>
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<main class="wiz-shell">

    <div class="wiz-topbar">
        <a href="dashboard_applicant.php" class="wiz-back">← Back to Dashboard</a>
        <div class="wiz-progress-wrap">
            <div class="wiz-progress-text"><span id="progressLabel">Step 1 of 6</span></div>
            <div class="wiz-progress-track"><div class="wiz-progress-fill" id="progressFill" style="width: 16.66%;"></div></div>
        </div>
    </div>

    <div class="wiz-card">

        <!-- ─── STEP 1: Role Type ──────────────────────────────────── -->
        <div class="wiz-step active" data-step="1">
            <span class="wiz-step-num">Question 1</span>
            <h2 class="wiz-question">What role are you looking for?</h2>
            <p class="wiz-hint">Be specific — the more focused, the better the match.</p>
            <input type="text" id="role_type" class="wiz-input"
                   placeholder="e.g. Senior Backend Engineer, Data Analyst, Product Manager"
                   maxlength="100" autofocus>
            <div class="wiz-error" id="error-1">Please tell us what role you're looking for.</div>
            <div class="wiz-nav">
                <span class="wiz-nav-spacer"></span>
                <span class="wiz-key-hint">Press <kbd>Enter ↵</kbd></span>
                <button type="button" class="wiz-btn wiz-btn-primary" data-next="1">Continue →</button>
            </div>
        </div>

        <!-- ─── STEP 2: Experience ────────────────────────────────── -->
        <div class="wiz-step" data-step="2">
            <span class="wiz-step-num">Question 2</span>
            <h2 class="wiz-question">What's your experience level?</h2>
            <p class="wiz-hint">This helps us filter out roles that aren't aligned with your seniority.</p>
            <div class="wiz-choices cols-3">
                <label class="wiz-choice"><input type="radio" name="experience" value="Entry">
                    <span class="choice-icon">🌱</span>
                    <div class="choice-title">Entry</div>
                    <div class="choice-desc">0–2 years of experience</div>
                </label>
                <label class="wiz-choice"><input type="radio" name="experience" value="Mid">
                    <span class="choice-icon">🚀</span>
                    <div class="choice-title">Mid</div>
                    <div class="choice-desc">3–5 years of experience</div>
                </label>
                <label class="wiz-choice"><input type="radio" name="experience" value="Senior">
                    <span class="choice-icon">⭐</span>
                    <div class="choice-title">Senior</div>
                    <div class="choice-desc">6+ years of experience</div>
                </label>
            </div>
            <div class="wiz-error" id="error-2">Please choose your experience level.</div>
            <div class="wiz-nav">
                <button type="button" class="wiz-btn wiz-btn-secondary" data-prev="2">← Back</button>
                <span class="wiz-nav-spacer"></span>
                <button type="button" class="wiz-btn wiz-btn-primary" data-next="2">Continue →</button>
            </div>
        </div>

        <!-- ─── STEP 3: Skills ────────────────────────────────────── -->
        <div class="wiz-step" data-step="3">
            <span class="wiz-step-num">Question 3 · Required</span>
            <h2 class="wiz-question">What are your top skills?</h2>
            <p class="wiz-hint">List the technologies, tools, and methods you know best — comma-separated. This is the strongest signal for matching.</p>
            <textarea id="skills" class="wiz-textarea" rows="5"
                      placeholder="e.g. Python, AWS, PostgreSQL, React, Docker, Kubernetes, Git"
                      minlength="3" required></textarea>
            <div class="wiz-error" id="error-3">Please list at least a few of your top skills — we use this to match against open roles.</div>
            <div class="wiz-nav">
                <button type="button" class="wiz-btn wiz-btn-secondary" data-prev="3">← Back</button>
                <span class="wiz-nav-spacer"></span>
                <button type="button" class="wiz-btn wiz-btn-primary" data-next="3">Continue →</button>
            </div>
        </div>

        <!-- ─── STEP 4: Location (multi-select) ───────────────────── -->
        <div class="wiz-step" data-step="4">
            <span class="wiz-step-num">Question 4 · Multi-select</span>
            <h2 class="wiz-question">Where do you want to work?</h2>
            <p class="wiz-hint">Select <strong>all that apply</strong> — pick one or several. We'll match against any of your choices.</p>
            <div class="wiz-choices cols-3">
                <label class="wiz-choice"><input type="checkbox" name="location" value="Remote">
                    <span class="choice-check">✓</span>
                    <span class="choice-icon">🏠</span>
                    <div class="choice-title">Remote</div>
                    <div class="choice-desc">Work from anywhere</div>
                </label>
                <label class="wiz-choice"><input type="checkbox" name="location" value="Hybrid">
                    <span class="choice-check">✓</span>
                    <span class="choice-icon">🔀</span>
                    <div class="choice-title">Hybrid</div>
                    <div class="choice-desc">Mix of office & remote</div>
                </label>
                <label class="wiz-choice"><input type="checkbox" name="location" value="On-site">
                    <span class="choice-check">✓</span>
                    <span class="choice-icon">🏢</span>
                    <div class="choice-title">On-site</div>
                    <div class="choice-desc">In the office full-time</div>
                </label>
            </div>
            <div class="wiz-error" id="error-4">Please pick at least one work-location preference.</div>
            <div class="wiz-nav">
                <button type="button" class="wiz-btn wiz-btn-secondary" data-prev="4">← Back</button>
                <span class="wiz-nav-spacer"></span>
                <button type="button" class="wiz-btn wiz-btn-primary" data-next="4">Continue →</button>
            </div>
        </div>

        <!-- ─── STEP 5: Salary ────────────────────────────────────── -->
        <div class="wiz-step" data-step="5">
            <span class="wiz-step-num">Question 5</span>
            <h2 class="wiz-question">What's your expected salary range?</h2>
            <p class="wiz-hint">USD per year. We'll prioritize jobs whose salary band overlaps with yours.</p>
            <div class="wiz-choices cols-4">
                <label class="wiz-choice"><input type="radio" name="salary" value="$40,000 - $60,000">
                    <div class="choice-title">$40K – $60K</div>
                </label>
                <label class="wiz-choice"><input type="radio" name="salary" value="$60,001 - $80,000">
                    <div class="choice-title">$60K – $80K</div>
                </label>
                <label class="wiz-choice"><input type="radio" name="salary" value="$80,001 - $100,000">
                    <div class="choice-title">$80K – $100K</div>
                </label>
                <label class="wiz-choice"><input type="radio" name="salary" value="$100,001+">
                    <div class="choice-title">$100K+</div>
                </label>
            </div>
            <div class="wiz-error" id="error-5">Please pick your expected salary range.</div>
            <div class="wiz-nav">
                <button type="button" class="wiz-btn wiz-btn-secondary" data-prev="5">← Back</button>
                <span class="wiz-nav-spacer"></span>
                <button type="button" class="wiz-btn wiz-btn-primary" data-next="5">Continue →</button>
            </div>
        </div>

        <!-- ─── STEP 6: Industries (optional) ─────────────────────── -->
        <div class="wiz-step" data-step="6">
            <span class="wiz-step-num">Question 6 · Optional</span>
            <h2 class="wiz-question">Any industries you prefer?</h2>
            <p class="wiz-hint">Tell us the industries you'd like to work in — or skip if you're open to anything.</p>
            <textarea id="industries" class="wiz-textarea" rows="3"
                      placeholder="e.g. healthcare, fintech, education, climate tech"></textarea>
            <div class="wiz-nav">
                <button type="button" class="wiz-btn wiz-btn-secondary" data-prev="6">← Back</button>
                <span class="wiz-nav-spacer"></span>
                <a href="#" class="wiz-skip" id="skipBtn">Skip →</a>
                <button type="button" class="wiz-btn wiz-btn-primary" id="submitBtn">✨ Get My Recommendations</button>
            </div>
        </div>

        <!-- ─── STEP 7: Loading state ─────────────────────────────── -->
        <div class="wiz-step" data-step="7">
            <div class="wiz-loading">
                <div class="wiz-loading-spinner"></div>
                <h3>Claude is analyzing the open jobs…</h3>
                <p>Matching your skills, experience, location, and salary against every open role.</p>
            </div>
        </div>

    </div>

</main>

<script>
(function () {
    const TOTAL_STEPS = 6;
    let currentStep = 1;

    const fillEl   = document.getElementById('progressFill');
    const labelEl  = document.getElementById('progressLabel');

    function setProgress(step) {
        const pct = (step / TOTAL_STEPS) * 100;
        fillEl.style.width = pct + '%';
        labelEl.textContent = `Step ${step} of ${TOTAL_STEPS}`;
    }

    function showStep(n) {
        document.querySelectorAll('.wiz-step').forEach(s => s.classList.remove('active'));
        const target = document.querySelector(`.wiz-step[data-step="${n}"]`);
        if (target) target.classList.add('active');
        currentStep = n;
        if (n <= TOTAL_STEPS) setProgress(n);
        // Auto-focus first input/radio of the step
        setTimeout(() => {
            const firstInput = target?.querySelector('input:not([type=radio]), textarea');
            if (firstInput) firstInput.focus();
        }, 100);
    }

    function showError(stepNum) {
        const err = document.getElementById('error-' + stepNum);
        if (err) {
            err.classList.add('show');
            setTimeout(() => err.classList.remove('show'), 3000);
        }
    }

    function validate(step) {
        if (step === 1) {
            return document.getElementById('role_type').value.trim().length >= 2;
        } else if (step === 2) {
            return !!document.querySelector('input[name="experience"]:checked');
        } else if (step === 3) {
            return document.getElementById('skills').value.trim().length >= 3;
        } else if (step === 4) {
            // Multi-select — at least one location must be checked
            return document.querySelectorAll('input[name="location"]:checked').length > 0;
        } else if (step === 5) {
            return !!document.querySelector('input[name="salary"]:checked');
        }
        return true;
    }

    // Next buttons
    document.querySelectorAll('[data-next]').forEach(btn => {
        btn.addEventListener('click', () => {
            const step = parseInt(btn.dataset.next);
            if (validate(step)) {
                showStep(step + 1);
            } else {
                showError(step);
            }
        });
    });

    // Back buttons
    document.querySelectorAll('[data-prev]').forEach(btn => {
        btn.addEventListener('click', () => {
            const step = parseInt(btn.dataset.prev);
            showStep(step - 1);
        });
    });

    // Auto-advance on radio choice (single-select)
    document.querySelectorAll('.wiz-choice input[type="radio"]').forEach(r => {
        r.addEventListener('change', () => {
            const step = parseInt(r.closest('.wiz-step').dataset.step);
            // Visual feedback: pause briefly so the user sees their selection register
            setTimeout(() => {
                if (validate(step)) showStep(step + 1);
            }, 250);
        });
    });
    // Checkbox choices (multi-select) — DO NOT auto-advance, user clicks Continue
    // Just hide any error message once they pick at least one
    document.querySelectorAll('.wiz-choice input[type="checkbox"]').forEach(c => {
        c.addEventListener('change', () => {
            const step = parseInt(c.closest('.wiz-step').dataset.step);
            const err = document.getElementById('error-' + step);
            if (err && validate(step)) err.classList.remove('show');
        });
    });

    // Enter key to advance from text/textarea
    document.getElementById('role_type').addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); document.querySelector('[data-next="1"]').click(); }
    });
    document.getElementById('skills').addEventListener('keydown', e => {
        if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) { e.preventDefault(); document.querySelector('[data-next="3"]').click(); }
    });

    // Skip industries
    document.getElementById('skipBtn').addEventListener('click', e => {
        e.preventDefault();
        document.getElementById('industries').value = '';
        document.getElementById('submitBtn').click();
    });

    // Submit
    document.getElementById('submitBtn').addEventListener('click', async () => {
        showStep(7); // loading state

        // Collect all checked locations (multi-select) and join with comma
        const locations = Array.from(document.querySelectorAll('input[name="location"]:checked'))
            .map(c => c.value);

        const payload = {
            role_type:  document.getElementById('role_type').value.trim(),
            experience: document.querySelector('input[name="experience"]:checked')?.value || '',
            skills:     document.getElementById('skills').value.trim(),
            location:   locations.join(', '),
            salary:     document.querySelector('input[name="salary"]:checked')?.value || '',
            industries: document.getElementById('industries').value.trim(),
        };

        try {
            const resp = await fetch('api/job_recommendations.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || '' },
                body: JSON.stringify(payload),
            });
            const raw = await resp.text();
            let data;
            try { data = JSON.parse(raw); }
            catch { data = { success: false, message: 'Invalid response from AI.' }; }

            if (data.success) {
                window.location.href = 'dashboard_applicant.php?ai_recs=1';
            } else {
                alert(data.message || 'Sorry, something went wrong.');
                showStep(6);
            }
        } catch (err) {
            console.error(err);
            alert('Network error. Please try again.');
            showStep(6);
        }
    });

    setProgress(1);
})();
</script>

<?php include 'includes/footer.php'; ?>

</body>
</html>
