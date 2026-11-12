# FixPoint UI implementation

This directory contains production-intended PHP view templates. They are not static demo pages.

Before changing templates, read:
- docs/10-INTEGRATION-CONTRACT.md
- docs/11-UI-SPECIFICATION.md
- docs/16-VIEW-TEMPLATE-CONTRACT.md
- docs/17-UI-01-QA-CHECKLIST.md

All views rely on the backend's HTML escaping helper e(). The backend ViewRenderer must resolve only fixed logical view names, pass the documented view-model variables, and buffer output. Templates do not contain SQL/PDO or authorization decisions.

The reusable shell is in layouts/, presentational components in components/ include flash messages, status badges, service cards, accessible form fields and empty states, and route-specific templates are in pages/.
