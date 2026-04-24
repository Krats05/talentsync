<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing — TalentSync</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: #f8fafc; }
        .pricing-page { max-width: 1200px; margin: 0 auto; padding: 48px 24px 80px; }

        /* Hero */
        .pricing-hero { text-align: center; margin-bottom: 48px; }
        .pricing-eyebrow { display: inline-block; padding: 4px 14px; background: #ecfdf5; color: #047857; font-size: 12px; font-weight: 700; letter-spacing: 1.5px; border-radius: 999px; margin-bottom: 16px; }
        .pricing-title { font-size: 44px; font-weight: 800; color: #0f172a; margin: 0 0 12px; }
        .pricing-sub { font-size: 18px; color: #64748b; margin: 0 auto; max-width: 560px; line-height: 1.5; }

        /* Tier cards */
        .tier-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 40px; }
        .tier-card { background: #fff; border-radius: 16px; padding: 32px 28px; border: 1px solid #e2e8f0; position: relative; transition: transform 0.15s, box-shadow 0.15s; }
        .tier-card:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(15,23,42,0.08); }
        .tier-card.featured { border: 2px solid #0f2a4f; box-shadow: 0 12px 36px rgba(15,42,79,0.12); }
        .tier-card.featured .tier-badge { display: inline-block; }
        .tier-badge { display: none; position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #00bfa5; color: #0f172a; font-size: 11px; font-weight: 700; padding: 4px 14px; border-radius: 999px; letter-spacing: 1px; }
        .tier-name { font-size: 13px; font-weight: 700; letter-spacing: 2px; color: #64748b; margin: 0 0 8px; text-transform: uppercase; }
        .tier-best { font-size: 13px; color: #94a3b8; margin: 0 0 20px; }
        .tier-price { font-size: 56px; font-weight: 800; color: #0f172a; line-height: 1; }
        .tier-price-per { font-size: 16px; color: #64748b; font-weight: 500; margin-left: 6px; }
        .tier-billing { font-size: 12px; color: #94a3b8; margin: 6px 0 24px; }
        .tier-cta { display: block; text-align: center; padding: 12px 18px; border-radius: 10px; font-size: 14px; font-weight: 700; text-decoration: none; transition: all 0.15s; margin-bottom: 28px; }
        .tier-cta-primary { background: #0f2a4f; color: #fff; }
        .tier-cta-primary:hover { background: #1e3a5f; }
        .tier-cta-secondary { background: #f1f5f9; color: #0f172a; border: 1px solid #e2e8f0; }
        .tier-cta-secondary:hover { background: #e2e8f0; }
        .tier-features { list-style: none; padding: 0; margin: 0; }
        .tier-features li { font-size: 14px; color: #334155; padding: 6px 0 6px 28px; position: relative; line-height: 1.4; }
        .tier-features li::before { content: "✓"; position: absolute; left: 0; top: 6px; color: #00bfa5; font-weight: 800; font-size: 14px; }
        .tier-features li.muted { color: #94a3b8; }
        .tier-features li.muted::before { content: "—"; color: #cbd5e1; }

        /* Trial banner */
        .trial-banner { background: linear-gradient(135deg, #0f2a4f 0%, #1e3a5f 100%); color: #fff; border-radius: 16px; padding: 32px; margin-top: 56px; text-align: center; }
        .trial-banner h2 { font-size: 26px; margin: 0 0 10px; }
        .trial-banner p { font-size: 15px; color: #cbd5e1; margin: 0 0 22px; }
        .trial-cta { display: inline-block; background: #00bfa5; color: #0f172a; padding: 12px 28px; border-radius: 10px; font-weight: 700; text-decoration: none; transition: all 0.15s; }
        .trial-cta:hover { background: #00d6b9; transform: translateY(-1px); }

        /* FAQ */
        .faq-section { margin-top: 64px; }
        .faq-title { font-size: 28px; font-weight: 700; color: #0f172a; text-align: center; margin: 0 0 32px; }
        .faq-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .faq-item { background: #fff; border-radius: 12px; padding: 22px 24px; border: 1px solid #e2e8f0; }
        .faq-q { font-size: 15px; font-weight: 700; color: #0f172a; margin: 0 0 8px; }
        .faq-a { font-size: 14px; color: #64748b; line-height: 1.6; margin: 0; }

        /* Candidate callout */
        .candidate-callout { display: flex; align-items: center; gap: 24px; background: #f0fdfa; border: 1px solid #99f6e4; border-radius: 16px; padding: 28px 32px; margin-top: 56px; }
        .candidate-callout-icon { width: 56px; height: 56px; flex-shrink: 0; background: #00bfa5; color: #0f172a; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 26px; font-weight: 700; }
        .candidate-callout-text { flex: 1; }
        .candidate-callout-text h3 { font-size: 18px; color: #0f172a; margin: 0 0 4px; font-weight: 700; }
        .candidate-callout-text p { font-size: 14px; color: #047857; margin: 0; line-height: 1.5; }
        .candidate-callout-cta { background: #0f2a4f; color: #fff; padding: 12px 22px; border-radius: 10px; text-decoration: none; font-size: 14px; font-weight: 700; flex-shrink: 0; transition: all 0.15s; }
        .candidate-callout-cta:hover { background: #1e3a5f; }

        @media (max-width: 900px) {
            .tier-grid { grid-template-columns: 1fr; }
            .faq-grid { grid-template-columns: 1fr; }
            .pricing-title { font-size: 32px; }
            .candidate-callout { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<main class="pricing-page">

    <section class="pricing-hero">
        <span class="pricing-eyebrow">PRICING · FOR EMPLOYERS</span>
        <h1 class="pricing-title">Simple, transparent pricing</h1>
        <p class="pricing-sub">Powerful AI recruitment for IT companies of every size. Start free for 14 days — no credit card required.</p>
    </section>

    <section class="tier-grid">

        <!-- STARTER -->
        <div class="tier-card">
            <p class="tier-name">Starter</p>
            <p class="tier-best">For small teams hiring occasionally</p>
            <div>
                <span class="tier-price">$29</span>
                <span class="tier-price-per">/month</span>
            </div>
            <p class="tier-billing">billed monthly · cancel anytime</p>
            <a href="billing.php?plan=starter" class="tier-cta tier-cta-secondary">Get Started</a>
            <ul class="tier-features">
                <li>Up to 5 active jobs</li>
                <li>50 applications / month</li>
                <li>AI Job Posting Assistant</li>
                <li>Basic HR Insights</li>
                <li>Email support</li>
                <li class="muted">AI Candidate Scoring</li>
                <li class="muted">AI Meeting Notes</li>
                <li class="muted">API access</li>
            </ul>
        </div>

        <!-- GROWTH (featured) -->
        <div class="tier-card featured">
            <span class="tier-badge">MOST POPULAR</span>
            <p class="tier-name">Growth</p>
            <p class="tier-best">For growing IT companies</p>
            <div>
                <span class="tier-price">$89</span>
                <span class="tier-price-per">/month</span>
            </div>
            <p class="tier-billing">billed monthly · cancel anytime</p>
            <a href="billing.php?plan=growth" class="tier-cta tier-cta-primary">Start Free Trial</a>
            <ul class="tier-features">
                <li>Up to 20 active jobs</li>
                <li>500 applications / month</li>
                <li>AI Job Posting Assistant</li>
                <li>Full HR Insights Dashboard</li>
                <li><strong>AI Candidate Scoring</strong></li>
                <li><strong>AI Meeting Notes</strong></li>
                <li>Priority email support</li>
                <li class="muted">API access</li>
            </ul>
        </div>

        <!-- PRO -->
        <div class="tier-card">
            <p class="tier-name">Pro</p>
            <p class="tier-best">For agencies & power users</p>
            <div>
                <span class="tier-price">$249</span>
                <span class="tier-price-per">/month</span>
            </div>
            <p class="tier-billing">billed monthly · cancel anytime</p>
            <a href="billing.php?plan=pro" class="tier-cta tier-cta-secondary">Contact Sales</a>
            <ul class="tier-features">
                <li><strong>Unlimited</strong> active jobs</li>
                <li><strong>Unlimited</strong> applications</li>
                <li>Everything in Growth, plus:</li>
                <li>API access</li>
                <li>Custom O*NET mappings</li>
                <li>SSO (Google / Microsoft)</li>
                <li>Dedicated account manager</li>
                <li>SLA guarantees</li>
            </ul>
        </div>
    </section>

    <section class="trial-banner">
        <h2>Try TalentSync free for 14 days</h2>
        <p>Full access to every AI feature. No credit card required. Cancel anytime.</p>
        <a href="signup.php" class="trial-cta">Start Free Trial →</a>
    </section>

    <section class="candidate-callout">
        <div class="candidate-callout-icon">👋</div>
        <div class="candidate-callout-text">
            <h3>Looking for a job? TalentSync is always free for candidates.</h3>
            <p>Browse open roles, apply with one click, and get AI-powered job recommendations — at no cost, ever.</p>
        </div>
        <a href="browse_jobs.php" class="candidate-callout-cta">Browse Jobs →</a>
    </section>

    <section class="faq-section">
        <h2 class="faq-title">Frequently asked questions</h2>
        <div class="faq-grid">
            <div class="faq-item">
                <p class="faq-q">Do I need a credit card to start?</p>
                <p class="faq-a">No. Sign up with your work email and start the 14-day trial — we'll only ask for billing details when you're ready to upgrade.</p>
            </div>
            <div class="faq-item">
                <p class="faq-q">Can I change plans later?</p>
                <p class="faq-a">Yes — upgrade or downgrade at any time from the Billing page. Changes take effect at the next billing cycle.</p>
            </div>
            <div class="faq-item">
                <p class="faq-q">What counts as an "application"?</p>
                <p class="faq-a">Each unique candidate that applies to one of your jobs counts as one application, regardless of how many AI Analyses you run on them.</p>
            </div>
            <div class="faq-item">
                <p class="faq-q">Is my candidate data private?</p>
                <p class="faq-a">Yes. PII is stripped before any AI request, and we never train on your data. We're SOC 2 Type II compliant (in progress).</p>
            </div>
            <div class="faq-item">
                <p class="faq-q">Do you offer non-profit or educational discounts?</p>
                <p class="faq-a">Yes — universities, career services, and non-profits get 40% off all plans. Contact us for a code.</p>
            </div>
            <div class="faq-item">
                <p class="faq-q">When does payment processing go live?</p>
                <p class="faq-a">Stripe integration launches Q3 2026. Until then, all features are free during the beta period.</p>
            </div>
        </div>
    </section>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
