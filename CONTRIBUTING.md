# Contributing

Thank you for your interest in nam2evidence. This is a public OSS proof-of-concept
for standardizing NAM-derived nonclinical evidence.

## Development Setup

Use the Docker Compose flow from the README:

```bash
castor start
```

If Castor is not installed, run the equivalent `docker compose` commands in the
README.

## Pull Requests

- Keep changes focused and explain the user-facing or technical impact.
- Include tests for behavioral changes where practical.
- Do not commit secrets, private datasets, regulatory-confidential material, or
  generated dependency directories.
- Preserve the regulatory disclaimer boundaries: the tool standardizes and
  packages evidence; it does not provide regulatory advice or claim acceptance.
- By contributing, you agree that your contribution is licensed under the GNU
  General Public License v3.0 or later.

## Reporting Issues

Please include:

- The exact command or workflow used.
- Expected and observed behavior.
- Relevant logs, screenshots, or API responses.
- Whether the issue affects demo mode, API mode, Docker, or local development.
