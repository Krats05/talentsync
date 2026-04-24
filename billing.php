<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=" . urlencode("billing.php"));
    exit;
}

$selectedPlan = $_GET['plan'] ?? '';
$validPlans = ['starter', 'growth', 'pro'];
$showSuccess = in_array($selectedPlan, $validPlans);

$navName = htmlspecialchars($_SESSION['full_name'] ?? 'User', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing & Plan — TalentSync</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: #f8fafc; }
        .billing-page { max-width: 1000px; margin: 0 auto; padding: 40px 24px 80px; }
        .billing-header { margin-bottom: 28px; }
        .billing-eyebrow { font-size: 12px; font-weight: 700; letter-spacing: 1.5px; color: #64748b; }
        .billing-title { font-size: 32px; font-weight: 800; color: #0f172a; margin: 6px 0 0; }

        /* Success modal */
        .success-banner { background: #ecfdf5; border: 1px solid #10b981; border-radius: 12px; padding: 18px 22px; margin-bottom: 24px; display: flex; gap: 14px; align-items: center; }
        .success-icon { width: 40px; height: 40px; background: #10b981; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .success-text strong { display: block; color: #064e3b; font-size: 15px; margin-bottom: 2px; }
        .success-text span { color: #047857; font-size: 13px; }

        /* Current plan card */
        .current-plan { background: linear-gradient(135deg, #0f2a4f 0%, #1e3a5f 100%); color: #fff; border-radius: 16px; padding: 32px; margin-bottom: 32px; display: grid; grid-template-columns: 1fr auto; gap: 20px; align-items: center; }
        .current-plan-label { font-size: 12px; font-weight: 700; letter-spacing: 2px; color: #00bfa5; }
        .current-plan-name { font-size: 32px; font-weight: 800; margin: 6px 0 4px; }
        .current-plan-meta { color: #cbd5e1; font-size: 14px; margin: 0; }
        .current-plan-actions { display: flex; flex-direction: column; gap: 8px; align-items: flex-end; }
        .btn-manage { background: rgba(255,255,255,0.1); color: #fff; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; border: 1px solid rgba(255,255,255,0.2); transition: all 0.15s; }
        .btn-manage:hover { background: rgba(255,255,255,0.18); }

        /* Usage section */
        .section-title { font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 14px; }
        .usage-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 32px; }
        .usage-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; }
        .usage-label { font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .usage-value { font-size: 28px; font-weight: 700; color: #0f172a; margin: 6px 0; }
        .usage-bar { height: 6px; background: #e2e8f0; border-radius: 999px; overflow: hidden; margin-top: 10px; }
        .usage-bar-fill { height: 100%; background: #00bfa5; border-radius: 999px; }
        .usage-meta { font-size: 11px; color: #94a3b8; margin-top: 6px; }

        /* Plan options */
        .plan-options { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 28px; }
        .plan-list { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 16px; }
        .plan-option { border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 18px; transition: all 0.15s; cursor: pointer; }
        .plan-option:hover { border-color: #cbd5e1; }
        .plan-option.current { border-color: #00bfa5; background: #f0fdfa; }
        .plan-option-name { font-size: 13px; font-weight: 700; color: #64748b; letter-spacing: 1.5px; text-transform: uppercase; }
        .plan-option-price { font-size: 24px; font-weight: 800; color: #0f172a; margin: 6px 0 4px; }
        .plan-option-price span { font-size: 13px; color: #64748b; font-weight: 500; }
        .plan-option-tag { display: inline-block; padding: 3px 10px; background: #00bfa5; color: #0f172a; font-size: 10px; font-weight: 700; border-radius: 999px; letter-spacing: 1px; margin-top: 8px; }
        .plan-option-btn { display: block; text-align: center; padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; margin-top: 14px; transition: all 0.15s; }
        .btn-upgrade { background: #0f2a4f; color: #fff; }
        .btn-upgrade:hover { background: #1e3a5f; }
        .btn-current { background: #f1f5f9; color: #64748b; cursor: default; }

        /* Billing history */
        .history-empty { background: #fff; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 32px; text-align: center; color: #94a3b8; font-size: 14px; }

        /* Coming soon notice */
        .coming-soon { background: #fef3c7; border: 1px solid #f59e0b; border-radius: 10px; padding: 14px 18px; margin-bottom: 24px; display: flex; gap: 12px; align-items: center; font-size: 13px; color: #78350f; }
        .coming-soon strong { color: #92400e; }

        @media (max-width: 800px) {
            .current-plan { grid-template-columns: 1fr; }
            .current-plan-actions { align-items: flex-start; }
            .usage-grid { grid-template-columns: 1fr; }
            .plan-list { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<main class="billing-page">

    <header class="billing-header">
        <p class="billing-eyebrow">ACCOUNT</p>
        <h1 class="billing-title">Billing & Plan</h1>
    </header>

    <?php if ($showSuccess): ?>
        <div class="success-banner">
            <div class="success-icon">✓</div>
            <div class="success-text">
                <strong>Plan request received</strong>
                <span>You selected the <strong><?= ucfirst($selectedPlan) ?></strong> plan. Stripe billing launches Q3 2026 — we'll email you when it's live.</span>
            </div>
        </div>
    <?php endif; ?>

    <div class="coming-soon">
        <span style="font-size:18px;">🛠️</span>
        <div><strong>Beta period:</strong> All features are free until Stripe billing launches in Q3 2026. The plans below show what your subscription will cost going forward.</div>
    </div>

    <!-- Current Plan -->
    <section class="current-plan">
        <div>
            <p class="current-plan-label">YOUR CURRENT PLAN</p>
            <h2 class="current-plan-name">Free Beta</h2>
            <p class="current-plan-meta">Welcome, <?= $navName ?>. You have full access to every AI feature during the beta.</p>
        </div>
        <div class="current-plan-actions">
            <a href="pricing.php" class="btn-manage">View All Plans</a>
        </div>
    </section>

    <!-- Usage -->
    <h3 class="section-title">Usage this month</h3>
    <div class="usage-grid">
        <div class="usage-card">
            <p class="usage-label">Active Jobs</p>
            <p class="usage-value">14</p>
            <div class="usage-bar"><div class="usage-bar-fill" style="width: 100%; background: #00bfa5;"></div></div>
            <p class="usage-meta">Unlimited during beta</p>
        </div>
        <div class="usage-card">
            <p class="usage-label">Applications</p>
            <p class="usage-value">40</p>
            <div class="usage-bar"><div class="usage-bar-fill" style="width: 8%;"></div></div>
            <p class="usage-meta">Unlimited during beta</p>
        </div>
        <div class="usage-card">
            <p class="usage-label">AI Calls (this month)</p>
            <p class="usage-value">132</p>
            <div class="usage-bar"><div class="usage-bar-fill" style="width: 26%;"></div></div>
            <p class="usage-meta">Unlimited during beta</p>
        </div>
    </div>

    <!-- Plan options -->
    <section class="plan-options">
        <h3 class="section-title">Switch plan</h3>
        <p style="color:#64748b; font-size:13px; margin:0 0 4px;">Choose a plan to switch to when billing launches in Q3 2026.</p>
        <div class="plan-list">
            <div class="plan-option">
                <p class="plan-option-name">Starter</p>
                <p class="plan-option-price">$29 <span>/mo</span></p>
                <p style="color:#64748b; font-size:12px; margin:0;">Up to 5 jobs · 50 apps/mo</p>
                <a href="?plan=starter" class="plan-option-btn btn-upgrade">Choose Starter</a>
            </div>
            <div class="plan-option">
                <p class="plan-option-name">Growth</p>
                <p class="plan-option-price">$89 <span>/mo</span></p>
                <p style="color:#64748b; font-size:12px; margin:0;">Up to 20 jobs · 500 apps/mo</p>
                <span class="plan-option-tag">RECOMMENDED</span>
                <a href="?plan=growth" class="plan-option-btn btn-upgrade">Choose Growth</a>
            </div>
            <div class="plan-option">
                <p class="plan-option-name">Pro</p>
                <p class="plan-option-price">$249 <span>/mo</span></p>
                <p style="color:#64748b; font-size:12px; margin:0;">Unlimited everything + API</p>
                <a href="?plan=pro" class="plan-option-btn btn-upgrade">Choose Pro</a>
            </div>
        </div>
    </section>

    <!-- Billing history -->
    <h3 class="section-title" style="margin-top:32px;">Billing history</h3>
    <div class="history-empty">
        No invoices yet. Your first invoice will appear here once the Q3 2026 billing launch goes live.
    </div>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
