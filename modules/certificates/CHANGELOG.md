# Certificates Changelog

## 1.5.2 - 2026-11-19

- Full localization: every UI string now comes from `lang/{en,ar}/core.txt` under the `certificates::` namespace; no hardcoded English or Arabic text remains in views or controllers.
- The public search eyebrow label is rendered from the `public.eyebrow` key instead of a fixed string in the default view template.
- Flash messages, confirmations, table headers, form labels and aria labels are translatable.

## 1.5.1 - 2026-10-15

- The account Certificates section now shows quick sub-actions (All certificates, Add new certificate, Deleted certificates) that appear only while the Certificates tab is active.

## 1.5.0 - 2026-10-15

- Every certificate workspace action button (Move to trash, Restore selected and Empty trash) stays hidden until at least one certificate is selected, keeping the toolbar clean.

## 1.4.9 - 2026-10-15

- Remove the duplicated permanent delete action from the trash bulk bar.
- Selection actions (Move to trash / Restore selected) stay hidden until at least one certificate is selected and show the selected count. Selected certificates can still be restored, each row keeps its own Delete permanently action, and Empty trash clears everything with an explicit confirmation.

## 1.4.8 - 2026-10-15

- Fix the Issue Certificate action in the certificate workspace so it keeps the theme accent with readable white text instead of inheriting the account link colours.

## 1.4.7 - 2026-10-15

- The certificate workspace sidebar lists quick sub-actions under Certificates: All certificates, Add new certificate and Deleted certificates.

## 1.4.6 - 2026-10-15

- The certificate management workspace keeps the account navigation sidebar, so issuers can move between account sections without losing their place. Sidebar links open the matching account tab.

## 1.4.5 - 2026-10-15

- Certificates issued from the account Certificates tab can now be deleted (moved to trash) directly from that list, next to Edit. Deleted certificates stay restorable from All Certificates.

## 1.4.4 - 2026-10-15

- Public certificate pages, the issuer workspace and the supplied default templates now take their colours from the active platform theme instead of a fixed palette, so the plugin looks native on any site.
- Brand accents, tints and neutrals read the theme colour variables with the previous values kept as fallbacks.
- Admin screens read the administration theme accent, text and muted colours.
- Refresh the untouched system templates so existing installations pick up the theme-aware colours; administrator edits are preserved.
