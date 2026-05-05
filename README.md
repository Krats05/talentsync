# TalentSync — AI-Powered Recruitment Platform for SMB IT Companies

[![Live Demo](https://img.shields.io/badge/Live%20Demo-talentsync.duckdns.org-00bfa5?style=for-the-badge)](https://talentsync.duckdns.org/talentsync/)
[![HTTPS](https://img.shields.io/badge/HTTPS-Let's%20Encrypt-success?style=for-the-badge)](https://talentsync.duckdns.org/talentsync/)
[![AWS](https://img.shields.io/badge/Hosted%20on-AWS%20EC2%20%2B%20RDS-orange?style=for-the-badge)](https://aws.amazon.com/)
[![Powered by Claude](https://img.shields.io/badge/AI-Claude%20Sonnet%204.6%20%2B%20Haiku%204.5-7c3aed?style=for-the-badge)](https://www.anthropic.com/)

> **TalentSync** is an end-to-end AI recruitment platform built for small and mid-sized IT companies that can't afford enterprise tools like Workday or Greenhouse ($30K+/year). It ships **6 AI agents powered by Claude** that handle the entire hiring journey — from job posting to interview summarization — at $29/month. Built on AWS with HTTPS, integrated with the U.S. Department of Labor's O\*NET dataset, and tested across 49 cases (100% pass rate).

---

## 🌐 Live Demo

**[https://talentsync.duckdns.org/talentsync/](https://talentsync.duckdns.org/talentsync/)**

### Test Credentials

| Role | Email | Password |
|------|-------|----------|
| HR Manager | `test.hr@gmail.com` | `Test@123` |
| Applicant | `test.applicant@gmail.com` | `Test@123` |

### One-click HR Demo (no signup)
Click **"Try HR Demo"** on the homepage, or navigate directly to:
**[https://talentsync.duckdns.org/talentsync/api/demo_login.php](https://talentsync.duckdns.org/talentsync/api/demo_login.php)**

Drops you into a populated HR dashboard with **14 jobs and 37 applications** seeded for demo purposes.

---

## ✨ Six AI Agents

| # | Feature | Model | What it does |
|---|---------|-------|--------------|
| 1 | **AI Job Posting Assistant** | Claude Haiku 4.5 | Type "I need a senior backend engineer" → get a complete O\*NET-standardized job posting in 30 seconds |
| 2 | **AI Job Recommendations** | Claude Haiku 4.5 | Applicants answer 6 wizard-style questions → Claude returns the top 5 ranked matches with reasoning |
| 3 | **AI HR Insights Dashboard** | Claude Sonnet 4.6 | Analyzes the entire hiring pipeline and surfaces severity-coded actionable insights (critical / warning / healthy) |
| 4 | **AI Candidate Scoring** | Claude Sonnet 4.6 | Hybrid scoring: 0.4 × Jaccard similarity + 0.6 × Weighted skill score, plus a 4-section AI qualitative report |
| 5 | **AI Interview Notes** | Claude Sonnet 4.6 | Live in-browser transcription via Web Speech API + Claude-generated Dialogue and Summary HTML |
| 6 | **Verified HR Onboarding** | Form-based | Free email is welcome — startups verify legitimacy via company name + website |

---

## 🏗️ Architecture

```
                    Browser (HR / Applicant)
                            │
                  https://talentsync.duckdns.org/talentsync/
                            │
                            ▼
                ┌─────────────────────────────┐
                │  DuckDNS (free DNS)         │
                │  → 13.59.20.157             │
                └─────────────┬───────────────┘
                              ▼
                ┌─────────────────────────────┐
                │  AWS EC2 (Apache + PHP 8.2) │
                │  SSL: Let's Encrypt cert    │
                │  Auto-renew via Certbot     │
                └─────────────┬───────────────┘
                              │
              ┌───────────────┼───────────────┐
              ▼               ▼               ▼
    ┌─────────────────┐  ┌─────────┐  ┌──────────────┐
    │ AWS RDS (MySQL) │  │ Claude  │  │ O*NET Web    │
    │ talentsync_db   │  │ API     │  │ Services     │
    │ (us-east-2)     │  │ (Anthr.)│  │ (gov data)   │
    └─────────────────┘  └─────────┘  └──────────────┘
```

---

## 🛠️ Tech Stack

### Backend
- **PHP 8.2** — vanilla, no framework (deliberate choice for AI prompt control + faster iteration)
- **MySQL 8** on AWS RDS — `users`, `jobs`, `job_skills`, `applications`, `stage_history`, `meeting_notes`, `occupation_data`, `technology_skills` tables
- **PDO prepared statements** — zero raw SQL queries (SQL-injection-proof)

### AI / ML
- **Anthropic Claude API** — Sonnet 4.6 for deep reasoning, Haiku 4.5 for high-volume calls
- **Hybrid match scoring** — Jaccard set similarity + Weighted skill coverage + Claude qualitative analysis
- **Prompt-injection defense** — delimited prompts (`"""..."""`), strip_tags input sanitization, system + user message separation
- **Multi-model fallback** — Sonnet 4.6 → 4.5 fallback for the Feedback Summarizer

### Frontend
- **Vanilla HTML/CSS/JavaScript** — no React/Vue (lightweight, fast to ship)
- **Web Speech API** — on-device speech-to-text for the AI Interview Notes feature
- **Animated wizard UI** — Typeform-style 6-step questionnaire with auto-advance, progress bar, multi-select checkbox cards
- **Fully responsive** — tested at iPhone 14 Pro Max (430px) through 1920px desktop

### Cloud + DevOps
- **AWS EC2** (Amazon Linux 2023) — application server with Apache 2.4
- **AWS RDS** (MySQL, us-east-2) — managed database
- **DuckDNS** — free dynamic DNS providing the `talentsync.duckdns.org` subdomain
- **Let's Encrypt + Certbot** — free SSL certificate, auto-renewing every 90 days via systemd timer
- **GitHub Actions** — CI/CD pipeline auto-deploys to EC2 on every push to `main`

### Security
- **CSRF tokens** on every state-changing form
- **bcrypt** password hashing (`password_hash()` + `password_verify()`)
- **Session security**: HttpOnly + Secure + SameSite=Lax cookies
- **htmlspecialchars** output escaping (XSS prevention)
- **Server-side role enforcement** (HR vs Applicant — never trust the UI)
- **API keys in environment variables** — never committed to git
- **Rate limiting** on signup + login endpoints (5 attempts per 15 min)
- **27 dedicated security tests** — all passing

---

## 📊 Testing

**49 test cases · 100% pass rate · 4 browsers** (Chrome 124, Safari 17, Firefox 125, Edge 124)

| Category | Count | Result |
|----------|-------|--------|
| Functional (auth, jobs, applications, AI features) | 16 | 16/16 ✓ |
| Authorization boundaries | 3 | 3/3 ✓ |
| Edge cases (empty inputs, large inputs, non-English) | 3 | 3/3 ✓ |
| Security (CSRF, SQLi, XSS, prompt injection, secrets) | 27 | 27/27 ✓ |
| **Total** | **49** | **49/49 ✓** |

---

## 💰 Unit Economics

| Metric | Value |
|--------|-------|
| Cost-to-serve per user / month | **$5.70** (AWS + Claude API + Domain) |
| Average revenue per user / month | **$59.00** |
| Gross margin | **90.3%** |
| Customer acquisition cost (CAC) | **$96** |
| Lifetime value (LTV) | **$826** (14-month avg retention) |
| LTV : CAC ratio | **8.6×** (well above 3× SaaS healthy threshold) |
| Break-even | **Month 6** at customer #5 |

---

## 📁 Repository Structure

```
talentsync/
├── api/                       # Server-side PHP endpoints
│   ├── auth_login.php         # Login API
│   ├── auth_signup.php        # Signup with company verification
│   ├── chatbox.php            # AI Job Posting Assistant
│   ├── job_recommendations.php # AI Job Recommendations
│   ├── hr_insights.php        # AI HR Insights Dashboard
│   ├── ai_score.php           # AI Candidate Scoring (Jaccard + Weighted + Claude)
│   ├── ai_summarize_notes.php # AI Interview Notes summarization
│   ├── save_job.php           # Job CRUD (location, experience, salary)
│   ├── submit_application.php # Application submission
│   ├── update_application_status.php
│   ├── demo_login.php         # One-click HR demo
│   └── ...
├── assets/                    # CSS + images
├── config/
│   ├── db.php                 # DB credentials (gitignored)
│   ├── ai.php                 # Claude API config + helper
│   └── ai.example.php         # Template for new clones
├── includes/                  # Reusable navbar, footer, csrf, helpers
├── .github/workflows/         # CI/CD: auto-deploy to EC2 on push
├── index.php                  # Homepage (animated, polished marketing site)
├── login.php / signup.php     # Auth pages
├── browse_jobs.php            # Public job board
├── job_detail.php             # Single job page (role-aware: HR vs Applicant)
├── apply_job.php              # Application form
├── dashboard_hr.php           # HR command center with AI Insights
├── dashboard_applicant.php    # Applicant view + AI recommendations
├── create_job.php             # AI Chatbox + manual job builder
├── job_applications.php       # HR view of candidates per job
├── notepad.php                # Live transcript + AI summarization
├── questionnaire.php          # 6-step wizard for AI job recs
├── pricing.php                # Public pricing page (3 tiers)
├── billing.php                # HR account / current plan view
└── README.md
```

---

## 🚀 Local Development Setup

### Prerequisites
- **MAMP** (or XAMPP, LAMP) with PHP 8.2+
- Access to the team's AWS RDS credentials (or set up your own MySQL instance)
- Anthropic Claude API key

### Setup Steps

```bash
# 1. Clone the repo
git clone https://github.com/Krats05/talentsync.git
cd talentsync

# 2. Symlink into your MAMP htdocs
ln -s "$(pwd)" /Applications/MAMP/htdocs/talentsync

# 3. Copy AI config template and add your Claude API key
cp config/ai.example.php config/ai.php
# Edit config/ai.php → set CLAUDE_API_KEY

# 4. Create config/db.php with DB credentials (ask team for AWS RDS access)
cat > config/db.php << 'EOF'
<?php
$connection_mode = 'cloud';   // or 'local'
if ($connection_mode == 'cloud') {
    $host = 'talentsync-db.xxxx.us-east-2.rds.amazonaws.com';
    $user = 'admin';
    $pass = 'your-password';
    $dbname = 'talentsync_db';
    $port = 3306;
}
$conn = new mysqli($host, $user, $pass, $dbname, $port);
$conn->set_charset('utf8mb4');
EOF

# 5. Start MAMP, then visit:
# http://localhost:8888/talentsync/
```

### Test Login
After local setup, log in with the seeded test credentials:
- HR: `test.hr@gmail.com / Test@123`
- Applicant: `test.applicant@gmail.com / Test@123`

---

## ☁️ Deployment

The app is deployed on AWS EC2 with HTTPS via DuckDNS + Let's Encrypt:

1. **DuckDNS subdomain** — `talentsync.duckdns.org` points to EC2 IP `13.59.20.157`
2. **Apache vhost** — `/etc/httpd/conf.d/talentsync.conf` listens on port 80 + 443
3. **SSL cert** — issued by Let's Encrypt via `certbot --apache`, auto-renews every 90 days via systemd timer
4. **CI/CD** — every push to `main` triggers GitHub Actions → SSHs to EC2 → `git pull` → restart Apache
5. **Security group** — ports 22 (SSH), 80 (HTTP → 301 to HTTPS), 443 (HTTPS) open

Full HTTPS setup walkthrough: see `HTTPS_SETUP.md` (created during Sprint 4).

---

## 👥 Team — Group 3

| Member | Sprint Ownership |
|--------|------------------|
| **Kratika Patidar** | AI HR Insights Dashboard, security testing (49 cases), HTTPS deployment, demo data seeding, homepage redesign, AI matching alignment (Location/Experience/Salary), questionnaire wizard, final document coordination |
| **Yutong Jiang** | Product design, ER diagram, DFD/activity diagrams, AI feature specs |
| **Ozge Arslan** | Burndown chart (whole project), sprint reviews, application status API |
| **Vaishnavi Samani** | Marketing plan, cost analysis, Year 1 budget, pricing tiers, applicant dashboard |
| **Lee Li** | Sprint backlog, methodology + apps documentation, login/signup UI |
| **Qiushi Zhao** | System overview, browse jobs page, retrospective + recommendations sections |

---

## 🛣️ Roadmap

| Quarter | Improvement |
|---------|-------------|
| **Q3 2026** | Multi-tenant data isolation + native iOS/Android mobile apps |
| **Q4 2026** | ATS integrations (Indeed, ZipRecruiter, LinkedIn) + WCAG 2.1 AA + Section 508 + EAA accessibility compliance |
| **Q1 2027** | Video interview transcription, real-time AI coaching, AI-powered company verification at signup (replaces static blocklist) |
| **Q2 2027** | International launch — UK, Australia, Canada with multi-language AI |
| **Long-term** | Custom domain (replace `*.duckdns.org`), AWS Application Load Balancer + ACM SSL, CloudFront CDN |

---

## 📖 Capstone Documentation

This is a senior capstone project for **The George Washington University**:

- **Deliverable 1** — Project proposal + requirements
- **Deliverable 2** — Sprint 1 (foundation: auth, DB, basic CRUD)
- **Deliverable 3** — Sprint 2 (core: job posting, applications, AWS deployment)
- **Deliverable 4** — Sprint 3 (AI integration: 6 AI features + HTTPS + security testing)
- **Deliverable 5** — Sprint 4 / Final document (this submission)

---

## 📝 License

This project was built as part of an academic capstone. Code is provided for educational reference. For commercial use or licensing inquiries, please contact the team.

---

## 🙏 Acknowledgments

- **Anthropic** — for Claude API access
- **U.S. Department of Labor** — for the O\*NET occupation database (free public data)
- **Let's Encrypt** + **DuckDNS** — for free HTTPS infrastructure
- **GWU Capstone Faculty** — for guidance, sprint feedback, and project mentorship
