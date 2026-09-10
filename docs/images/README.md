# Screenshots

Images used by the root `README.md` and the Sphinx documentation. They are
captured from the running application with the standard demo dataset, so they
show real data rather than mockups.

## What each image shows

| File | Screen | Must show |
| --- | --- | --- |
| `overview.png` | Project Overview | Readiness score, before/after panel, blocker counts |
| `endpoint-data.png` | Endpoint Data | Canonical measurement rows with explicit units |
| `ontology-mapping.png` | Ontology | Suggested terms with real CURIEs and Approve/Reject actions |
| `semantic-validation.png` | Semantic Validation | Per-record errors with the recommended-fix column |
| `readiness.png` | Readiness | The 10-dimension FAIR/AI-readiness breakdown |
| `provenance.png` | Provenance | Endpoint values traced to raw files / analysis scripts |
| `context-of-use.png` | Context of Use | Regulatory question, intended use, support level |
| `claim-graph.png` | Claim Graph | Claims with supporting evidence, limitations, and the review gate |
| `export-center.png` | Export Center | Export formats plus the blocking human-review banner |
| `project-list.png` | Project list | The landing screen before a project is opened |

## Capture conventions

- **Viewport** 1440×900, captured at `deviceScaleFactor: 2`, then downscaled to
  1800px wide. Keeps text crisp without committing multi-megabyte files.
- **Above the fold only** (`fullPage: false`). Long pages should show their
  header and first meaningful block, not an entire scrolled document.
- **Default "before" demo state.** Capture with `app:load-namcore-demo` *without*
  `--corrected`, so the blockers and review gates are visible — those are the
  point of the screens.
- Keep the filenames stable; the README links to them directly.

## Recapturing

Start the stack and load the demo data as described in the root README, then
drive a headless browser over the workspace routes:

```
/                                    → project-list
/projects/<id>                       → overview
/projects/<id>/endpoints             → endpoint-data
/projects/<id>/ontology              → ontology-mapping
/projects/<id>/semantic-validation   → semantic-validation
/projects/<id>/readiness             → readiness
/projects/<id>/provenance            → provenance
/projects/<id>/cou                   → context-of-use
/projects/<id>/claims                → claim-graph
/projects/<id>/export                → export-center
```

Get `<id>` from `curl http://localhost:8080/api/v1/projects`. Wait for
`networkidle` plus a short settle delay before capturing — several workspaces
fetch their data client-side after first paint.

Re-capture whenever a workspace's layout changes materially. A stale screenshot
is worse than none: it teaches a newcomer the wrong thing about a tool whose
whole premise is traceability.
