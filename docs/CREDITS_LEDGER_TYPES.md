# Credits Ledger Types (Enum — documentation only)

Storage remains `user_meta` key `yoy_credits_ledger`.
Runtime today writes: `deduct` | `grant` | `purchase`.

**Do not enforce a closed enum in code yet.** Future writers should prefer these type strings.

## Allowed type vocabulary (reserved)

| type | Meaning |
|------|---------|
| `deduct` | Generation / Language Asset charge (current) |
| `grant` | Admin grant, welcome, refund restore (current) |
| `purchase` | Billing / WooCommerce (current) |
| `refund` | Explicit refund of a prior deduct |
| `bonus` | Promotional bonus |
| `promotion` | Campaign credit |
| `subscription` | Plan renewal grant |
| `marketplace` | Marketplace sale / purchase credit movement |
| `system` | System correction |
| `admin` | Manual admin adjustment (prefer over opaque grant) |

## Entry shape (unchanged)

```
id, type, amount, label, module, studio, provider, status, balance_after, created_at
(+ optional meta)
```

Constants mirror: `YooY_Credits_Ledger_Types` (stub class, not enforced).
