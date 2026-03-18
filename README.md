# TalentSync - O*NET Integrated Recruitment System

TalentSync is a centralized, web-based recruitment platform designed to streamline the hiring process for IT organizations. By integrating the U.S. Department of Labor's **O*NET database**, TalentSync allows HR managers to create standardized, skill-accurate job profiles and manage the full recruitment lifecycle from job posting to candidate selection.

## Live Demo: [http://13.59.20.157/talentsync/](http://13.59.20.157/talentsync/)

## Project Status: Sprint 2 Complete
Our team has successfully delivered the complete hiring workflow — from job creation with O*NET integration to applicant management with real-time status tracking — deployed on AWS EC2 with CI/CD via GitHub Actions.

---

## Technical Architecture

### **Cloud Infrastructure**
- **Application Server:** AWS EC2 (Amazon Linux) with Apache/PHP
- **Database:** AWS RDS (MySQL) — centralized, high-availability data environment
- **CI/CD:** GitHub Actions — auto-deploys to EC2 on every push to main
- **Version Control:** Git/GitHub with branch protection

### **Tech Stack**
- **Backend:** PHP 8.x
- **Frontend:** HTML5, CSS3, JavaScript (ES6)
- **Styling:** Custom CSS with Flexbox/Grid, responsive media queries (mobile, tablet, desktop)
- **Database Logic:** Complex SQL JOINs for O*NET skill mapping and AJAX for real-time occupation searching
- **Deployment:** AWS EC2 + GitHub Actions CI/CD pipeline

---

## Key Features

### Sprint 1 Features
1. **O*NET Job Profile Builder** — HR managers search standardized SOC codes to auto-populate skills, responsibilities, and salary data from O*NET
2. **HR Command Center (Dashboard)** — Centralized view for managing job listings and viewing real-time database statistics
3. **Secure Authentication** — Login/Signup system with encrypted passwords, session management, and role-based access (HR vs Applicant)

### Sprint 2 Features
4. **Job Application System** — Applicants can browse open jobs, view detailed job descriptions (skills, responsibilities, salary), and submit applications
5. **HR Application Management** — HR managers can view all applications per job and update applicant status (Interviewing / Offered / Rejected)
6. **Applicant Dashboard** — Displays application stats (Interviewing, Offered, Rejected, Total) with a searchable table of submitted applications
7. **Stage History Tracking** — Every status change is logged in a stage_history table for audit trail
8. **AWS EC2 Deployment** — Production deployment with CI/CD pipeline via GitHub Actions
9. **Duplicate Application Prevention** — One application per user per job enforced at database level

---

## Repository Structure

- `/api` — Server-side PHP logic for O*NET searching, skill fetching, job saving, and application management
- `/assets` — CSS stylesheets and UI images
- `/includes` — Reusable components (navigation bars, footers)
- `/config` — Configuration templates (Note: `db.php` is git-ignored for security)
- `/.github/workflows` — CI/CD pipeline (deploy.yml for automated EC2 deployment)

---

## Database Schema

### Core Tables
- **users** — User accounts with role-based access (HR / Applicant)
- **jobs** — Job postings with O*NET occupation codes, skills, and salary data
- **applications** — Job applications (job_id, user_id, full_name, email, phone, cover_letter, status)
- **stage_history** — Audit trail tracking all application status changes with timestamps
- **O*NET Tables** — Imported relational tables for occupations, skills, and SOC mappings

---

## Group 3 - Team Responsibilities

Our team followed an Agile/Scrum methodology across both sprints, with specific roles assigned to ensure successful delivery.

### **Sprint 1**
* **Kratika Patidar:** Cloud Infrastructure (AWS RDS Setup & Migration), O*NET Integration (Search logic & Skill Tag generation), Backend Database Architecture
* **Ozge Arslan:** Secure Authentication System (Login/Signup/Logout API logic), Session Management and User Security
* **Qiushi Zhang:** Dashboard Statistics & Analytics, GitHub Repository Management and Version Control
* **Yutong Jiang:** Frontend Lead (Global Stylesheet, Homepage Design, UI Assets, Navbar & Footer Components)
* **Lee:** Login/Signup UI Pages
* **Vaishnavi Samani:** HR Dashboard Module, Create Job Frontend Page, UI Components

### **Sprint 2**
* **Kratika Patidar:** AWS EC2 Deployment, CI/CD Pipeline (GitHub Actions), Submit Application Backend, Application Error Fixes, Dashboard Routing
* **Ozge Arslan:** Update Application Status API
* **Qiushi Zhang:** Browse Jobs Page, Job Applications Page
* **Yutong Jiang:** CSS Styling, Navbar & Footer Reusable Templates, Mobile Responsive Design
* **Lee:** Apply Job Page, Job Detail Page
* **Vaishnavi Samani:** Applicant Dashboard

---

## Sprint Deliverables

### Sprint 1
- Requirement Specifications: Level-0 & Level-1 DFDs, Relational Database Models
- Scrum Management: Product Backlog (20+ User Stories), Burndown Chart tracking
- Core Product: Functional HR Portal with Cloud-connected O*NET data

### Sprint 2
- Complete hiring workflow: Job Posting to Application Management
- Applicant-facing features: Browse Jobs, Apply, Track Applications
- HR management: View Applications, Update Status (Interviewing/Offered/Rejected)
- Cloud deployment on AWS EC2 with automated CI/CD
- Stage history audit trail for compliance tracking

---

## How to Run Locally
1. Clone the repository: `git clone https://github.com/Krats05/talentsync.git`
2. Set up MAMP/XAMPP with Apache and PHP 8.x
3. Create `config/db.php` with the following parameters (Ask team for AWS credentials):
   ```php
   $host = 'your-rds-endpoint';
   $user = 'admin';
   $pass = '********';
   $dbname = 'talentsync_db';
   ```
4. Place the project in your web root (e.g., `htdocs/talentsync`)
5. Access via `http://localhost/talentsync/`

---

## Deployment
The application is deployed on **AWS EC2** with automated CI/CD:
- Every push to `main` triggers a GitHub Actions workflow
- The workflow SSHs into EC2 and deploys the latest code
- Database config (`db.php`) is preserved across deployments
- Live at: [http://13.59.20.157/talentsync/](http://13.59.20.157/talentsync/)
