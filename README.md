# GOVYX — AI Governance Brain

## Overview

GOVYX is the **AI governance brain** of the ARWE ecosystem — a centralized,
decision-support platform for government organizations. It provides a single
dashboard for tracking work, monitoring performance, detecting risk and
supporting decisions across institutions, backed by a lightweight stack of C,
PHP and MySQL.

GOVYX is a **decision-support and governance intelligence platform** — it never
acts as an autonomous authority making unreviewed decisions.

## Problem

Government organizations operate across silos: tasks, KPIs and reports live in
spreadsheets and paper; institutional knowledge is fragmented; risk signals are
noticed late; and AI tooling is either heavyweight, cloud-dependent, or behaves
like an autonomous authority instead of a decision-support layer.

## Solution

GOVYX centralizes governance operations into one platform where every
organization's work — tasks, KPIs, workflows, reports and risk — is visible,
comparable and auditable. AI is used only as an **assistant layer**: it
summarizes, proposes, flags and explains (institutional intelligence), while a
human authority always makes and records the final decision.

- C-based intelligence engines ("Rankor"), background services and security
  utilities
- PHP APIs and workflows for tasks, KPIs, accountability and reporting
- Risk detection and institutional intelligence feeds presented for human
  review

## Features

- Task management
- KPI monitoring
- Accountability tracking
- Government workflow monitoring
- Performance analysis
- Administrative reporting
- Decision support
- Risk detection
- Institutional intelligence
- AI-assisted government operations (assistant, never autonomous)

## Architecture

```
                         GOVYX
                           │
          ┌────────────────┼────────────────┐
          │                │                │
          ▼                ▼                ▼
         C               PHP          HTML/CSS/JS
          │                │                │
          │                ▼                ▼
          │           MySQL/MariaDB      Browser
          │
          ▼
   Rankor / Intelligence
   Background Services
   Analytics
   Security
```

```
├── api/        REST API (v1)
├── app/        PHP application (controllers, services, models, security)
├── assets/     Static assets
├── c/          C components (intelligence & system layer)
├── config/     Configuration
├── database/   Schema (install.sql) and seed data (seed.sql)
├── public/     Web root
├── storage/    Storage (logs, uploads, cache)
└── tests/      Test suites
```

## Technology

| Technology | Responsibility |
|---|---|
| C | Systems, intelligence engines ("Rankor"), background services, high-performance processing, security utilities |
| PHP | Backend, APIs, workflows, authentication, authorization, business logic |
| HTML / CSS / Pure JavaScript | Interface structure, design and client-side communication |
| MySQL/MariaDB | Persistent data |

The core application intentionally avoids major frameworks (React, Laravel,
Django, Node.js, etc.) to stay lightweight and fully controlled.

## Installation

Requirements: PHP ≥ 8, a C compiler (`make`, `gcc`) and MySQL/MariaDB.

```bash
# 1. Create the database (default credentials live in config/config.php)
mysql -u root -p -e "CREATE DATABASE govyx CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p govyx < database/install.sql
mysql -u root -p govyx < database/seed.sql

# 2. Build the C layer
make -C c

# 3. Start the dev server with the web root at public/
php -S 127.0.0.1:8081 -t public public/index.php
```

Adjust DB credentials in `config/config.php` to match your server.

## Usage

Open the served URL in a browser and sign in with a seed account (see
`database/seed.sql` for seeded users and passwords). From the dashboard:

- Create and assign **tasks**, monitor **KPIs** and review **reports**
- Track government workflows end-to-end and enforce accountability
- Review **risk detection** and **institutional intelligence** — any
  AI-generated proposal is presented for human approval, never executed
  automatically

Run the automated suites under `tests/` after installation to verify the
deployment (API + C self-tests).

## Security

- Role-based access control over workflows, reports and administrative actions
- C-based security utilities for cryptographic and integrity operations
- Audit-friendly design: every decision flows through a human authority and
  leaves a trace
- No external runtime dependencies — self-contained stack reduces supply-chain
  and vendor risk

## Screenshots

Screenshots will be added here as the interface is finalized.

## Roadmap

- Institutional intelligence modules: automated risk summaries and anomaly
  detection surfaced for human review
- Rankor engine expansion: background analytics and scheduled intelligence
  jobs
- Expanded KPI and reporting templates per ministry workflow
- Arabic/Amharic localization pass
- Integration with sibling ARWE platforms (TerraChain, Edunex, Locify) for
  cross-institution operations

## License

Apache License, Version 2.0 — see [LICENSE](LICENSE) and [NOTICE](NOTICE).

Copyright 2026 henokakriso. "Govyx" and "ARWE" are trademarks of the ARWE project; trademark use is governed by Section 6 of the Apache License, Version 2.0.