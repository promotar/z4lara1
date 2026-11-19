#!/usr/bin/env bash
set -euo pipefail

failed=0

while IFS= read -r tracked_path; do
    case "$tracked_path" in
        verification/README.md|verification/*.example.*|reports/10-local-environment-configuration.example.md)
            continue
            ;;
    esac

    case "$tracked_path" in
        .env|.env.*|*/.env|*/.env.*|docker/secrets/*|secrets/*|*/secrets/*|auth.json|*/auth.json|.ssh/*|*/.ssh/*|id_rsa*|*/id_rsa*|id_ed25519*|*/id_ed25519*|*.pem|*.key|*.p12|*.pfx|*.sql|*.bak|*.backup|*.old|*.orig|*.save|*.tmp|*.codex-*|*/.backups/*|storage/app/*|storage/framework/views/*|verification/*|reports/10-local-environment-configuration-completion.md|art-inpa-main.zip)
            if [[ "$tracked_path" != ".env.example" ]]; then
        printf 'Sensitive or runtime path is tracked: %s\n' "$tracked_path" >&2
                failed=1
            fi
            ;;
    esac
done < <(git ls-files)

content_matches="$({
    git grep -IlE '^APP_KEY=base64:[A-Za-z0-9+/=]{20,}|^(DB|MYSQL|MARIADB|REDIS|MAIL)_PASSWORD=(")?[^"$<{[:space:]][^[:space:]]*|(^|[^A-Za-z0-9])sk-(proj-)?[A-Za-z0-9_-]{20,}|github_pat_[A-Za-z0-9_]{20,}|gh[pousr]_[A-Za-z0-9]{30,}|AKIA[0-9A-Z]{16}|ASIA[0-9A-Z]{16}|xox[baprs]-[0-9A-Za-z-]{20,}|-----BEGIN ([A-Z0-9 ]+ )?PRIVATE KEY-----' -- ':!.env.example' || true
} | sort -u)"

if [[ -n "$content_matches" ]]; then
    while IFS= read -r matched_path; do
        printf 'Potential credential content is tracked in: %s\n' "$matched_path" >&2
    done <<< "$content_matches"
    failed=1
fi

private_ip_matches="$({
    git grep -IlE '(^|[^0-9])(10\.([0-9]{1,3}\.){2}[0-9]{1,3}|192\.168\.[0-9]{1,3}\.[0-9]{1,3}|172\.(1[6-9]|2[0-9]|3[01])\.[0-9]{1,3}\.[0-9]{1,3})([^0-9]|$)' -- \
        app bootstrap config database ops reports scripts tests verification .env.example || true
} | sort -u)"

if [[ -n "$private_ip_matches" ]]; then
    while IFS= read -r matched_path; do
        printf 'Private network address is tracked in: %s\n' "$matched_path" >&2
    done <<< "$private_ip_matches"
    failed=1
fi

if [[ "$failed" -ne 0 ]]; then
    printf 'Refusing to package or publish tracked credentials.\n' >&2
    exit 1
fi

printf 'Tracked-file credential check passed.\n'
