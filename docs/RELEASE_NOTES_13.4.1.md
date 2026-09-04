# Release Notes — 13.4.1

**YooY AI Studio** — Phase 10 clarification: unified Profile + Plan/Billing

## Capability findings (pre-implement)

1. Profile fields: `display_name`, `first_name`, `last_name`, `user_email`, `user_login`, `user_registered`
2. Avatar: Gravatar via `get_avatar_url`; custom upload added via Media Library + `yoy_avatar_attachment_id`
3. Email edit: **not supported** (no verification flow) — read-only
4. Payment gateway: WooCommerce product checkout mapping (Stripe/PayPal flags are config only)
5. Saved cards: **not supported** in YooY code (`WC_Payment_Tokens` unused)
6. WC payment-methods: manage URL linked when WooCommerce My Account exists
7. Subscriptions: **no** WooCommerce Subscriptions — YooY plan status only
8. Billing history: `yoy_billing_orders` snapshots from completed WC plan orders
9. Unsupported: add/remove/default card UI, fake renewal invoices, email change

## Changes

- MY → **내 프로필**: single page (photo + name + email + first/last + login/joined)
- Local avatar upload/remove with broken-image fallback
- MY → **플랜 및 결제**: plan, status, payment methods (truthful empty/manage link), order history, plan change CTA
- Dropdown labels updated

## Version

13.4.1
