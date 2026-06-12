# The Canonical Business Record — The Spine

> The single source of truth for "a business." Side B writes it; Side A reads it.
> This document is the contract. See `../SCOPE.md` for why the spine exists.
>
> **Law:** All code reads and writes a business **only** through the
> `ho_salesportal_*` access layer in `prospect-model.php`. No file defines its
> own field access, its own "get business," or its own storage. If a field isn't
> reachable through this layer, the layer is extended — a new parallel
> representation is never created. Every parallel representation is what caused
> the function collisions and the 130-version churn.

_Last set: 2026-06-12_

---

## 1. The record already exists (this is consolidation, not a rebuild)

The canonical record is the `businesses` row plus its satellite tables, defined in
`db/schema.sql`, accessed through `prospect-model.php`. The access layer is real
and already written:

| Purpose | Canonical function (prospect-model.php) |
|---|---|
| Load by slug | `ho_salesportal_get_business_by_slug()` |
| Load by id | `ho_salesportal_get_business_by_id()` |
| Create / update | `ho_salesportal_upsert_business()` |
| Evidence for a business | `ho_salesportal_business_evidence()` |
| Claims (the EAV facts) | `ho_salesportal_business_claims()` |
| Scores | `ho_salesportal_business_scores()` |
| Read one claim field | `ho_salesportal_claim_value_for_field()` / `_best_claim_by_field()` |

**The key is `business_slug`.** One business = one slug = one record.

## 2. The shape

```
businesses                         ← core identity + lifecycle status (the anchor)
  ├─ evidence_sources              ← where facts came from (Side B raw input)
  ├─ business_claims  (EAV)        ← every researched fact, by field_key (the richness)
  ├─ business_requirement_scores   ← scored against me_requirements
  ├─ business_me_scores            ← scored against me_categories
  ├─ prospect_previews             ← the /go preview record (Side A output)
  ├─ preview_choices               ← what the customer picked on the /go page
  ├─ outreach_events               ← every outreach attempt + outcome
  └─ build_handoffs                ← post-sale build spec
```

### How richness is stored: EAV via `business_claims`
The `businesses` table holds only stable identity + lifecycle enums. **Everything a
researcher learns is a `business_claims` row** — `field_key`, `claim_value`,
`confidence_level`, and a link to the `evidence_source` it came from. This is the
right design: it carries provenance and confidence per fact, which is exactly what
Side A needs to pitch credibly. Keep it.

## 3. The contract: Side B → record → Side A

- **Side B's job:** populate `evidence_sources` and `business_claims` with deep,
  confident, sourced facts. Side B is measured by the richness and trustworthiness
  of the claims it writes.
- **Side A's job:** read the business + its claims through the access layer and
  render the personalized outreach message and `/go` page. Side A writes
  `prospect_previews` (and reads `preview_choices` / `outreach_events`). Side A
  never reads raw tables directly and never invents fields.

## 4. ⚠️ The unresolved divergence (resolve before any code cut)

These fields are referenced by ~20 churn-era PHP files but appear in **no committed
SQL** and do **not** flow through the `ho_salesportal_*` layer:

```
diagnosis_status            strength_keys_json        weakness_keys_json
recommendation_keys_json    primary_offer_path        preview_direction_keys_json
front_door_preview_status   go_slug / go_path         outreach_asset_url
outreach_to / _subject / _body                        marketing_desk_status
```

Where they actually persist today is unconfirmed — likely some mix of dynamic
`business_claims` keys, JSON blobs, and/or columns added directly to the live DB
that were never captured in a schema file. **The committed schema and the live
database have silently diverged**, and that is the single biggest risk to the
spine.

**First concrete step before consolidating any code:** dump the live
`businesses` table structure (`SHOW CREATE TABLE businesses;`) and the distinct
`business_claims.field_key` values, and reconcile them against this document.
Then assign each field above exactly one canonical home:

- a real, schema-committed **column on `businesses`** (for one-per-business lifecycle state), or
- a **`business_claims` field_key** (for a researched, sourced fact), or
- a column on the relevant **satellite table** (e.g. `prospect_previews.go_slug`).

No field gets two homes. That reconciliation table, once filled, completes the spine.

## 5. The test for any spine change

1. Does it route through `ho_salesportal_*`? If not, it doesn't ship.
2. Does every field have exactly one canonical home? If not, fix that first.
3. Does it preserve provenance + confidence on researched facts? If not, it weakens Side A.
