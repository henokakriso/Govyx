# GOVYX — AI Governance Brain

GOVYX is the **AI governance brain** of the ARWE ecosystem — a centralized,
decision-support platform for government organizations.

## Purpose

GOVYX provides government organizations with a centralized platform for:

- Task management
- KPI monitoring
- Accountability
- Government workflow monitoring
- Performance analysis
- Administrative reporting
- Decision support
- Risk detection
- Institutional intelligence
- AI-assisted government operations

GOVYX is a **decision-support and governance intelligence platform** — it never
acts as an autonomous authority making unreviewed decisions.

## Technology Stack

| Technology | Responsibility |
|---|---|
| C | Systems, intelligence engines ("Rankor"), background services, high-performance processing, security utilities |
| PHP | Backend, APIs, workflows, authentication, authorization, business logic |
| HTML / CSS / Pure JavaScript | Interface structure, design and client-side communication |
| MySQL/MariaDB | Persistent data |

The core application intentionally avoids major frameworks (React, Laravel,
Django, Node.js, etc.) to stay lightweight and fully controlled.

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

## Repository Layout

```
api/        REST API (v1)
app/        PHP application (controllers, services, models, security)
c/          C components (intelligence & system layer)
assets/     Static assets
config/     Configuration
database/   Schema and migrations
public/     Web root
storage/    Storage (logs, uploads, cache)
tests/      Test suites
```

## License

MIT — see [LICENSE](LICENSE).