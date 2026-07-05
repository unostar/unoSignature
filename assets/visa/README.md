# Visa Firma integration — approved spec (Jul 2026)

**Status:** **Visa checkout wired in plugin** (Jul 2026). Parser + text builders + `fields[]` on SR create for `visa_services`. Consultation checkout unchanged. **Test on site with test API key before prod.**

**Product (tourist visa form):** WooCommerce product `27028` (verify on site).

**Template:** `8956775a-6869-4b8d-916b-f2129b9acf92` — **Visa Service Agreement** (12 pages, **1 Signer**). Template **ID** and field **UUIDs** are stable. **Layout (position, size, page order) is configured only in Firma UI** — never send layout in API payloads.

**Verified reference TEST SR:** `0ed66013-d5f4-4650-910f-bbfe4bd04de9` — sign URL used for final layout check (04 Jul 2026).

---

## Core idea

1. **Site (EPO):** Element class name → `cssclass` in `tmcartepo`.
2. **Firma:** `template_field_id` UUIDs in plugin settings.
3. **Backend:** parse cart → format + translate to **English** → build `recipients[]` + `fields[]` → create/send SR.

**Do not:** merge/dedup fields, label/`post_name` maps, `visibility_conditions`, per-field rep/sponsor overrides, layout in payload.

---

## Signer

**One signer** — WooCommerce **billing** (payer). Signs only.

Rep, sponsor, primary EPO contact, additional adults/children → **text in textareas**, not separate signers.

Future business contracts: possible **second signer** via a **separate template** — out of scope.

---

## Firma template — page 12

### Signer 1 — billing (`recipients[0]`)

Prefill via recipient: `title`, `first_name`, `last_name`, `email`, `phone_number`, `street_address`, `city`, `state_province`, `postal_code`, `country`.

DOB: `recipients[0].custom_fields.birthdate` — `d-M-Y` (e.g. `15-Mar-1985`).

SR name: `LAST-FIRST-Title-DOB-dd-Mon-yyyy` (hyphens, no spaces in name parts).

### Signature

One signature field — signer completes in iframe.

### Three read-only textareas (`fields[]` overrides — **data only**)

| variable_name | template_field_id | When to send override |
|---------------|-------------------|------------------------|
| `additional_applicants` | `fcea8758-10ee-4c17-b5b6-869160e01086` | When any applicant lines exist (primary + AA + children) |
| `representative` | `f8d8842b-35f2-44fd-945d-f0e527d19a46` | When `firma_representative_*` contact fields present |
| `sponsor` | `f9cb9592-fd28-4375-9f08-3fee0845ee3b` | When `firma_sponsor_*` contact fields present |

**Empty block:** omit override entirely (textarea visible but empty on PDF).

**Override shape:** only `template_field_id`, `read_only: true`, `read_only_value`. **No** `position`, `page_number`, `width`, `height`, `type`.

---

## Textarea formatting (canonical — verified Jul 2026)

- **Language:** English only in contract text.
- **Dates in text:** `d-M-Y` (e.g. `11-Jul-2023`). **Never** write the word `DOB` in contract text.
- **Separators:** comma between parts — **no** `|`.
- **One line per person** (plus block headers / boilerplate as below).

### `additional_applicants`

Block header, then person lines:

```
Additional Applicant's:

Primary Applicant: {contact line}
Spouse/Partner: {contact line}
Additional Applicant (18+): {contact line}
Adult child (18+): {contact line}
Minor child: {first_name} {last_name}, {d-M-Y}
```

**Line labels** (prefix before contact data — map from EPO relationship/role in plugin):

| Label | EPO source |
|-------|------------|
| `Primary Applicant:` | `firma_primary_*` |
| `Spouse/Partner:` | TBD — relationship field or repeater role |
| `Additional Applicant (18+):` | `firma_additional_applicant_*` (repeater) |
| `Adult child (18+):` | 18+ child repeater if used |
| `Minor child:` | `firma_additional_applicant_child_*` (repeater) |

**Adult contact line** (8 EPO fields, no birthdate in form):

`{first_name} {last_name}, {address}, {city_region}, {postcode} {state if any}, {country}, {email}, {messenger}`

EPO classes: `firma_*_first_name`, `_last_name`, `_address`, `_city_region`, `_postcode`, `_country`, `_email`, `_messenger` (prefix `primary`, `additional_applicant`, `representative`, or `sponsor`).

**Minor child line** (3 fields only):

`{first_name} {last_name}, {d-M-Y}`

EPO: `firma_additional_applicant_child_first_name`, `_child_last_name`, `_child_birthdate` + `repeater` index.

Only include lines for people present in the order; omit unused label types.

### `representative`

```
Annexure 5: "Client(s) Representative"

We hereby authorize the set out below person to represent our interests as Client(s) Representative in dealing with the Migration Agent(s) set out in Annexure 1. In consideration of their acting as our Client(s) Representative, We hereby indemnify Migration Agent(s) set out in Annexure 1 against any claims or demands made against her/him arising from any declarations that the Client(s) Representative makes on my/our behalf:

{contact line}
```

**Required:** blank line after the Annexure 5 title line, before boilerplate.

Contact line: same 8-field adult format from `firma_representative_*`.

### `sponsor`

```
Applicant's Sponsor:

{contact line}
```

Blank line after header. Contact line: same 8-field adult format from `firma_sponsor_*`.

---

## EPO cart parsing

### Anchor: `cssclass`

Stable across RU/EN. Set in EPO → CSS Settings → Element class name.

**Ignored** (questionnaire / UI toggles, not contract contact data): `firma_visa_type`, any `*_added` checkbox class.

**Contact prefixes** (blocks are included when these fields have values):

```
firma_primary_*
firma_representative_*
firma_sponsor_*
firma_additional_applicant_*
firma_additional_applicant_child_*
```

### Repeater blocks

For multiple AA / children: **`cssclass` + `repeater`** index (`0`, `1`, …). Also `sections_repeater` groups adults vs children.

### Not used as anchors

`post_name` (`tmcp_textfield_N`), localized `name`/`section_label`, `section` hash alone.

### Questionnaire

Ignore for contract text (location, GTE, visa history, marketing, etc.).

---

## API payload

```json
{
  "template_id": "8956775a-6869-4b8d-916b-f2129b9acf92",
  "name": "PETROV-Ivan-Mr-DOB-15-Mar-1985",
  "recipients": [{
    "designation": "Signer",
    "order": 1,
    "first_name": "Ivan",
    "last_name": "Petrov",
    "title": "Mr",
    "email": "...",
    "phone_number": "...",
    "street_address": "...",
    "city": "...",
    "state_province": "...",
    "postal_code": "...",
    "country": "...",
    "custom_fields": { "birthdate": "15-Mar-1985" }
  }],
  "fields": [
    {
      "template_field_id": "fcea8758-10ee-4c17-b5b6-869160e01086",
      "read_only": true,
      "read_only_value": "Additional Applicant's:\n\nPrimary Applicant: …"
    }
  ],
  "settings": { "send_signing_email": false }
}
```

Then `POST …/signing-requests/{id}/send` with `{ "send_signing_email": false }`.

Signing UI: `https://app.firma.dev/signing/{recipient_id}`

---

## Testing

| Method | Key | Visa experiments |
|--------|-----|------------------|
| `scripts/dev/bootstrap.php` + `local.env` (`FIRMA_USE_TEST_KEY=1`) | **Test** | **Always** |
| Firma MCP | **Live (OAuth)** | **Read-only** — templates/docs. **Never create/send SR** |

Test mode = `Authorization` header only (Firma docs). TEST badge + watermark = test key.

`templates_get` — read UUIDs / verify template exists. **Do not copy layout into payloads.**

---

## Plugin work (next)

1. ~~**Settings**~~ — visa textarea field UUIDs per signing rule (done Jul 2026).
2. ~~**Cart parser**~~ — `VisaEpoParser` (`cssclass` + `repeater`).
3. ~~**Text builders**~~ — `VisaTextBuilder` (canonical EN format; values pass-through until WPML/EPO EN complete).
4. ~~**Checkout hook**~~ — `uno_build_firma_create_payload()` for `visa_services`: billing → `recipients[0]` + `custom_fields.birthdate`; non-empty blocks → `fields[]`.

**Next:** end-to-end test on staging (test key), verify PDF page 12, EN translation if RU values appear.

**Local artifact map:** [`../INDEX.md`](../INDEX.md) — what is spec vs archive vs unrelated site snippets.

**Reference EPO dump:** site `.debug.log` scope `visa_epo_dump` (Jun–Jul 2026).

---

## Superseded (do not reintroduce)

- 2-signer visa template / billing vs primary as separate signers
- Per-field rep/sponsor Firma text overrides
- Single `annexure_details` blob
- `visibility_conditions` / hiding empty columns
- `DOB` label in contract text
- `|` as field separator
- Layout fields in `fields[]` overrides
- Creating test SRs via MCP
- `post_name` / label-based EPO maps
