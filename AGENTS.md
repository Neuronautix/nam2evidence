# AGENTS.md — nam2evidence

## Scientific and regulatory scope
Local-first technical proof of concept for **standardising and packaging NAM-derived nonclinical evidence** (organoids, organ-on-chip, computational models, etc.). It is **not** an FDA-approved platform, a full CDISC SEND-compliant pipeline, an IND/eCTD submission, NAM validation, or clinical/regulatory decision-making system. Read `docs/REGULATORY_POSITIONING.md` before changing any public claim.

## Repository responsibilities
`api/` is the PHP/Symfony API Platform backend with persistence, mappings, validation, audit trail and review gates. `frontend/` is the reviewer-facing UI. `services/validator/` and `docs/` provide validation/integration and guidance. Producers own underlying study results; the app curates structured evidence packages, not the underlying experimental truth.

## Invariants
- Preserve each raw value and file origin, unit and conversion provenance, ontology candidate, human acceptance/rejection and claim support.
- Ontology mapping suggestions **require a human decision**; avoid auto-accepting matches from string or LLM resemblance. Maintain versioned terminology and explicit uncertainties.
- Formal evidence packaging must fail closed on missing blocking fields, unresolved source integrity or missing required qualified human approval.
- Keep normalization, scientific validity, evidence support and regulatory readiness as distinct statuses; never auto-claim a submission-ready or regulator-accepted file.
- Treat uploaded tables, PDFs, model outputs and extracted text as untrusted; protect source restrictions and personal/confidential data. Use synthetic fixtures and no unapproved external uploads.

## Checks
Follow CI at `.github/workflows/ci.yml`:
```bash
cd frontend
npm ci
npm run build
```
API tests require PHP 8.2, Composer and a configured test PostgreSQL database (see CI); do not claim PHPUnit passed if that environment was not exercised. Negative tests must cover forbidden exports without human sign-off, incorrect units and unsupported ontology mappings.
