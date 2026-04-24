<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TalentSync Homepage</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/homepage.css?v=13">
</head>
<body>

    <!-- Top Navbar -->
    <?php require_once 'includes/navbar.php'; ?>


    <!-- Product Description -->
    <section class="main-description">
        <div class="MainDes-left">
            <h1>
            <span class="gradient-word">O*NET-Integrated</span> Recruitment System<br />
            For IT Companies
            </h1>

            <p>
            Centralize candidate data and standardize hiring process. Reduce time-to-hire,
            eliminate administrative delays, and support HR managers and hiring teams in
            making accurate hiring decisions.
            </p>

            <p class="hero-audience">
                <span class="hero-audience-tag hero-audience-employer">Subscription</span> for employers
                <span class="hero-audience-divider">·</span>
                <span class="hero-audience-tag hero-audience-candidate">Always free</span> for candidates
            </p>

            <div class="hero-cta-row">
                <div class="hero-cta-block">
                    <a href="api/demo_login.php" class="browse-btn hero-cta-employer" title="One-click into a populated HR dashboard">
                        <span class="hp-cta-pulse"></span>
                        Try HR Demo
                    </a>
                    <span class="hero-cta-caption">For HR Managers · No signup needed</span>
                </div>
                <div class="hero-cta-block">
                    <a href="browse_jobs.php" class="browse-btn hero-cta-candidate">Browse Jobs</a>
                    <span class="hero-cta-caption">For Job Seekers · Free forever</span>
                </div>
            </div>
        </div>

        <div class="MainDes-right">
            <img src="assets/img/homepage_img.png" alt="homepage_img">
        </div>
    </section>


    <!-- ════════════════ STATS BAND ════════════════ -->
    <section class="hp-section">
        <div class="hp-section-head reveal">
            <span class="hp-eyebrow">Real impact</span>
            <h2 class="hp-h2">Hiring at the speed of the internet</h2>
            <p class="hp-lead">Built so your team spends less time on busywork and more time on candidates.</p>
        </div>
        <div class="hp-stats-grid">
            <div class="hp-stat-card reveal">
                <div class="hp-stat-num">30</div>
                <p class="hp-stat-label">seconds to post a complete job</p>
                <p class="hp-stat-quote">Type what you're hiring for in plain English. AI Chatbox drafts the description and pulls O*NET-standard skills. No more blank-page paralysis.</p>
            </div>
            <div class="hp-stat-card reveal">
                <div class="hp-stat-num">6</div>
                <p class="hp-stat-label">AI agents working alongside you</p>
                <p class="hp-stat-quote">From job posting to interview notes — every step of hiring is augmented. Recruiters stay in control; AI handles the heavy lifting.</p>
            </div>
            <div class="hp-stat-card reveal">
                <div class="hp-stat-num">97%</div>
                <p class="hp-stat-label">cheaper than enterprise ATS tools</p>
                <p class="hp-stat-quote">Workday and Greenhouse start at $30K+/year. TalentSync starts at $29/month — same AI capabilities, built for SMB budgets.</p>
            </div>
        </div>
    </section>


    <!-- ════════════════ AI FEATURES GRID ════════════════ -->
    <section class="hp-section hp-section-alt">
        <div class="hp-section-head reveal">
            <span class="hp-eyebrow">A suite of AI agents</span>
            <h2 class="hp-h2">Six AI features that replace your spreadsheets</h2>
            <p class="hp-lead">Each one focused on a single recruiter pain point. All powered by Claude.</p>
        </div>
        <div class="hp-features-grid">
            <div class="hp-feature reveal">
                <div class="hp-feature-icon">💬</div>
                <h3 class="hp-feature-title">AI Job Posting Assistant</h3>
                <p class="hp-feature-desc">Type "I need a senior backend engineer" — get a draft job posting with O*NET-standard skills in 30 seconds.</p>
            </div>
            <div class="hp-feature reveal">
                <div class="hp-feature-icon">🎯</div>
                <h3 class="hp-feature-title">AI Job Recommendations</h3>
                <p class="hp-feature-desc">Applicants answer 6 questions; Claude returns the top 5 matches from your open roles, ranked.</p>
            </div>
            <div class="hp-feature reveal">
                <div class="hp-feature-icon">📊</div>
                <h3 class="hp-feature-title">AI HR Insights Dashboard</h3>
                <p class="hp-feature-desc">5-stage funnel + Claude-generated severity-coded cards: critical, warning, healthy.</p>
            </div>
            <div class="hp-feature reveal">
                <div class="hp-feature-icon">⭐</div>
                <h3 class="hp-feature-title">AI Candidate Scoring</h3>
                <p class="hp-feature-desc">Composite score (Jaccard + Weighted) plus a four-section AI report with hire/no-hire recommendation.</p>
            </div>
            <div class="hp-feature reveal">
                <div class="hp-feature-icon">🎙️</div>
                <h3 class="hp-feature-title">AI Interview Notes</h3>
                <p class="hp-feature-desc">Live transcription during the interview, then Claude turns it into a Dialogue + Key Points summary.</p>
            </div>
            <div class="hp-feature reveal">
                <div class="hp-feature-icon">🛡️</div>
                <h3 class="hp-feature-title">Verified HR Accounts</h3>
                <p class="hp-feature-desc">Free email is welcome — startups without a company domain just provide a company name and website. Each HR profile shows a "Verified company" badge so candidates know who they're talking to.</p>
            </div>
        </div>
    </section>


    <!-- ════════════════ HOW IT WORKS ════════════════ -->
    <section class="hp-section hp-howit">
        <div class="hp-section-head reveal">
            <span class="hp-eyebrow">How it works</span>
            <h2 class="hp-h2">From job posting to hire — in a fraction of the time</h2>
            <p class="hp-lead">Three steps. No training. No spreadsheets.</p>
        </div>
        <div class="hp-steps">
            <div class="hp-step reveal">
                <div class="hp-step-num">1</div>
                <h3 class="hp-step-title">Post a job in 30 seconds</h3>
                <p class="hp-step-desc">Open the AI Chatbox, type what you're hiring for in plain English. We pull O*NET-standard skills, draft the description, and publish it for review.</p>
                <span class="hp-step-meta">Replaces 15 minutes</span>
            </div>
            <div class="hp-step reveal">
                <div class="hp-step-num">2</div>
                <h3 class="hp-step-title">AI scores every applicant</h3>
                <p class="hp-step-desc">As applications arrive, TalentSync ranks them with a composite score and flags risks. Click AI Analysis for a four-section report on any candidate.</p>
                <span class="hp-step-meta">Replaces hours of manual screening</span>
            </div>
            <div class="hp-step reveal">
                <div class="hp-step-num">3</div>
                <h3 class="hp-step-title">AI summarizes interviews</h3>
                <p class="hp-step-desc">Hit Start before the interview, Stop after. Get a clean Dialogue + Key Points summary, automatically saved to the candidate's record.</p>
                <span class="hp-step-meta">Replaces handwritten notes</span>
            </div>
        </div>
    </section>


    <!-- ════════════════ FINAL CTA ════════════════ -->
    <section class="hp-finalcta">
        <div class="hp-section">
            <span class="hp-eyebrow">Ready to start?</span>
            <h2 class="hp-h2">See TalentSync in action — no signup required</h2>
            <p class="hp-lead">One click drops you into a populated HR dashboard with real demo data and live AI features.</p>
            <div class="hp-finalcta-buttons">
                <a href="api/demo_login.php" class="hp-cta-primary">
                    <span class="hp-cta-pulse"></span>
                    Try Live Demo
                </a>
                <a href="pricing.php" class="hp-cta-secondary">View Pricing</a>
            </div>
        </div>
    </section>


    <!-- Footer -->
    <?php require_once 'includes/footer.php'; ?>

    <!-- ════════════════ ANIMATION JS ════════════════ -->
    <script>
    (function () {
        // Stagger child elements inside grids — apply reveal-delay-N
        const grids = document.querySelectorAll('.hp-stats-grid, .hp-features-grid, .hp-steps');
        grids.forEach(grid => {
            Array.from(grid.children).forEach((el, i) => {
                if (el.classList.contains('reveal')) {
                    el.classList.add('reveal-delay-' + Math.min(i + 1, 6));
                }
            });
        });

        // Scroll-triggered reveal via IntersectionObserver
        const supportsIO = 'IntersectionObserver' in window;
        const reveals = document.querySelectorAll('.reveal');
        if (supportsIO) {
            const io = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        // Trigger number counters when stat card becomes visible
                        const num = entry.target.querySelector('.hp-stat-num');
                        if (num && !num.dataset.animated) {
                            animateCount(num);
                            num.dataset.animated = '1';
                        }
                        io.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.15, rootMargin: '0px 0px -60px 0px' });
            reveals.forEach(el => io.observe(el));
        } else {
            // No IO support — just show everything
            reveals.forEach(el => el.classList.add('is-visible'));
        }

        // Number counter — parses the visible text and counts up
        function animateCount(el) {
            const raw = el.textContent.trim();
            // Detect format: pure number, X/Y, or number with %
            const fractionMatch = raw.match(/^(\d+)\s*\/\s*(\d+)$/);
            const percentMatch = raw.match(/^(\d+)%$/);
            const intMatch     = raw.match(/^(\d+)$/);

            let from = 0, to, formatter;
            if (fractionMatch) {
                to = parseInt(fractionMatch[1], 10);
                const denom = fractionMatch[2];
                formatter = v => Math.floor(v) + ' / ' + denom;
            } else if (percentMatch) {
                to = parseInt(percentMatch[1], 10);
                formatter = v => Math.floor(v) + '%';
            } else if (intMatch) {
                to = parseInt(intMatch[1], 10);
                formatter = v => Math.floor(v).toString();
            } else {
                return; // unrecognized format, skip
            }

            const duration = 1200;
            const start = performance.now();
            function tick(now) {
                const elapsed = now - start;
                const t = Math.min(1, elapsed / duration);
                // ease-out cubic
                const eased = 1 - Math.pow(1 - t, 3);
                el.textContent = formatter(from + (to - from) * eased);
                if (t < 1) requestAnimationFrame(tick);
                else el.textContent = formatter(to);
            }
            el.textContent = formatter(0);
            requestAnimationFrame(tick);
        }
    })();
    </script>
</body>
</html>