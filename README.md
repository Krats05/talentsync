# TalentSync - Intelligent Recruitment Information System

TalentSync is a centralized, web-based recruitment platform designed to streamline the hiring process for IT organizations. By integrating the U.S. Department of Labor’s **O*NET database**, TalentSync allows HR managers to create standardized, skill-accurate job profiles in seconds.

## 🚀 Project Status: Sprint 1 Complete
Our team has successfully established the core architecture, including a secure authentication system, an HR dashboard, and the primary O*NET-powered Job Profile Builder.

---

## 🛠️ Technical Architecture

### **Cloud Infrastructure**
- **Database:** Hosted on **AWS RDS (MySQL)**. This ensures a centralized, high-availability data environment for all team members and provides a production-ready backend.
- **Environment:** Developed locally using PHP/Apache and collaborated via Git/GitHub.

### **Tech Stack**
- **Backend:** PHP 8.x
- **Frontend:** HTML5, CSS3, JavaScript (ES6)
- **Framework:** Bootstrap 5 (for responsive UI)
- **Database Logic:** Complex SQL JOINS for O*NET skill mapping and AJAX for real-time occupation searching.

---

## ✨ Key Features (Sprint 1)

### 1. O*NET Job Profile Builder
The standout feature of TalentSync. Instead of manual entry, HR managers search for standardized SOC (Standard Occupational Classification) codes.
- **Dynamic Skill Fetching:** Selecting a job title triggers an asynchronous request to fetch industry-standard technical skills directly from our O*NET relational tables.
- **Customization:** Managers can append custom skills and set job statuses (Draft, Open, Closed).

### 2. HR Command Center (Dashboard)
A centralized view for managing all job listings, tracking recruitment progress, and viewing real-time database statistics.

### 3. Secure Authentication
A robust Login/Signup system with encrypted passwords and session management to protect sensitive recruitment data.

---

## 📂 Repository Structure

- `/api`: Contains server-side PHP logic for O*NET searching, skill fetching, and job saving.
- `/assets`: CSS stylesheets and UI images.
- `/includes`: Reusable components like navigation bars and footers.
- `/config`: Configuration templates (Note: `db.php` is git-ignored for security).

---

- ## 👥 Group 3 - Team Responsibilities

Our team followed an Agile/Scrum methodology during Sprint 1, with specific roles assigned to ensure the successful integration of the cloud database and O*NET functionality.

### **Development Team**
* **Kratika Patidar (Project Lead):** * Cloud Infrastructure (AWS RDS Setup & Migration).
    * O*NET Integration (Search logic & Skill Tag generation).
    * Backend Database Architecture and `save_job.php` logic.
* **Ozge Arslan:** * Secure Authentication System (Login/Signup/Logout API logic).
    * Session Management and User Security.
* **Qiushi Zhang:** * Dashboard Statistics & Analytics (SQL queries for Job Status counts).
    * GitHub Repository Management and Version Control setup.
* **Yutong Jiang:** * Frontend Lead (Global Stylesheet, Homepage Design, UI Assets).
    * Main Website Layout and Branding.
* **Lee & Vaishnavi Samani:** * HR Dashboard Module and UI Components.
    * Reusable UI Templates (Navbar, Footer).
    * Job Search and Filtering logic.

---

## 📅 Sprint 1 Deliverables
As per our project roadmap and tracker:
- **Requirement Specifications:** Level-0 & Level-1 DFDs, Relational Database Models.
- **Scrum Management:** Product Backlog (20+ User Stories), Burndown Chart tracking.
- **Core Product:** Functional HR Portal with Cloud-connected O*NET data.
---

## 📝 How to Run Locally
1. Clone the repository: `git clone https://github.com/Krats05/talentsync.git`
2. Create a `db.php` in the root directory using the following parameters (Ask team for AWS credentials):
   ```php
   $host = 'talentsync-db.c9ckuwi4arn2.us-east-2.rds.amazonaws.com';
   $user = 'admin';
   $pass = '********';
   $dbname = 'talentsync_db';
