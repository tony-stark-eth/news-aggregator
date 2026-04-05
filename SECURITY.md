# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability, please report it responsibly:

1. **Do not** open a public issue
2. Email the maintainer at kevin.mauel2+github@gmail.com
3. Include a description of the vulnerability and steps to reproduce

We will acknowledge receipt within 48 hours and provide a timeline for a fix.

## Supported Versions

| Version | Supported |
|---------|-----------|
| latest  | Yes       |

## Security Measures

- All dependencies monitored via Dependabot
- Weekly automated security scans (`composer audit`, `symfony security:check`)
- Docker image vulnerability scanning via `docker scout`
- Session-based authentication with CSRF protection
- No secrets in repository (all via environment variables)
