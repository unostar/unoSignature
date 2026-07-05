# 💬 Conversation History

> **Purpose:** Track discussions, decisions, and context across sessions to maintain continuity and shared understanding.
> 
> **How it works:** After each work session, add a new entry with what was discussed, decided, and why.

---

## Session 1 - 27 Mar 2026

### What we discussed
- **Memory System Setup:** How to preserve conversation history and project context across VS Code sessions
- **Problem:** Every time VS Code reopens, the context (what we talked about, why we made decisions) is lost
- **Solution:** Create persistent files in the project to maintain understanding

### Key Decisions & Why
1. **Created `.agent.md`** 
   - Reason: Physical file in project for roadmap, architecture, and TODOs
   - Benefit: Transfers with project if moved/cloned

2. **Created `.conversation-history.md`** (this file)
   - Reason: Preserve the "why" behind decisions, not just the code
   - Benefit: Next session I read this and understand context immediately

### Context & Assumptions
- Project is WooCommerce + Firma.dev integration (electronic signatures at checkout)
- Core workflow is working, but refinements and edge cases need attention
- We want to avoid explaining the same things repeatedly across sessions

### Next Session Notes
- Start by reading `.agent.md` for technical overview
- Read `.conversation-history.md` for decision context
- Check if any new issues/decisions need to be logged

---

## Session 2+ Configuration (Previous Sessions)

### API & Workspace Setup ✅
- **API Key:** `firma_817ebb0ba0ccfc8beb68536ab328f79d8388a7af86f2d330`
- **Workspace ID:** `7771959d-c6de-4e53-a683-e994df84803a`
- **MCP Integration:** Firma.dev API + Documentation connected in VS Code
- **Note:** These are stored here to avoid re-entering them each session

---

## Session 3 - 28 Mar 2026 (COMPLETE)

### What we discussed
- Tested signing flow — discovered button state issue
- Button stays "Signing..." when user closes modal window
- Explored completion email notifications (not receiving auto-emails)
- Investigated users list (both Firma dashboard and API have issues)
- Added i18n (internationalization) support for all button text

### Key Decisions & Why
1. **Fixed modal close behavior**
   - Reset button to "Sign Agreement" when overlay clicked
   - Reason: Allows retry without page refresh
   - Implementation: Check button state before resetting

2. **Added wp_localize_script for translations**
   - Created `firmaL10n` object with all user-facing strings
   - Reason: Enable multi-language support (WPML, locale switching)
   - All hardcoded strings replaced with `__()` function

3. **Dismissed completion email notification issue**
   - Not implementing custom webhook email
   - Firma.dev should have this built-in

### Technical Changes Made
- **Button reset on modal close:** When clicking overlay, button returns to initial state
- **Localization setup:** 
  - `wp_localize_script('jquery', 'firmaL10n', [...])`
  - Updated all: 'Sign Agreement', 'Signing...', 'Signing in progress...', '✔ Agreement signed', etc.
- **No breaking changes** to workflow

### Code Quality Status
- ✅ Modal handling improved (UX fix)
- ✅ Full i18n support added
- ✅ Webhook verification working
- ⚠️ Completion emails still not auto-sending (Firma feature gap, not our code)

### Next Session Notes
- Core workflow is solid and tested
- Code is now production-ready with i18n
- Small UX fixes complete
- Check Firma.dev dashboard for any creator notification settings if needed

---

## Session 4 - 30 Mar 2026 (COMPLETE)

### What we discussed
- Switched from staging to production Firma account and verified production endpoints.
- Confirmed current production company/workspace/template IDs via API.
- Investigated why signing could be bypassed and why signing link became invalid.
- Diagnosed `state_province` validation errors during send.
- Fixed `firmaL10n` availability by inlining localized object before checkout JS.

### Key Decisions & Why
1. **Contract fields must block checkout with explicit errors**
   - Replaced silent early-exit approach with `$errors->add` + missing-fields list.
   - Prevents unnoticed bypass when Woo country rules omit fields.

2. **Treat optional address-related recipient fields with safe fallback values**
   - Woo can submit empty strings (`''`) instead of null.
   - `?? 'N/A'` was insufficient; fallback must handle empty string.

3. **Do not rely on `wp_localize_script('jquery', ...)` inside checkout markup hook**
   - In this context localization was not printed in source reliably.
   - Inline `window.firmaL10n = ...` before main inline script is stable.

4. **Handle Firma send API validation errors as real failures**
   - API can return payload-level errors with HTTP success semantics in some flows.
   - Backend now checks response body for `error` / `validation_errors` and returns failure.

### Confirmed Findings
- Production company: `Reality Maker PTY. LTD. ATF RM.`
- Production workspace ID: `7771959d-c6de-4e53-a683-e994df84803a`
- Active production template ID: `6f31d364-1e42-4fb0-b17b-c24c0e7dda58`
- Old template ID from previous environment caused mismatch/confusion.
- `state_province` may be enforced through prefilled mapping logic even when template field is not marked required.

### Current Stable State
- Checkout blocks correctly when contract-required data is missing.
- Signing flow opens correctly.
- `firmaL10n` object is available at runtime.
- API validation failures are surfaced properly instead of false success.

### Next Session Notes
- If `state_province` error reappears, inspect newest signing request payload first (not previous request IDs).
- Keep using production template ID `6f31d364-1e42-4fb0-b17b-c24c0e7dda58`.

---

## Session [N] - [Date]

### What we discussed
- [ Update here ]

### Key Decisions & Why
- [ Update here ]

### Context & Assumptions
- [ Update here ]

### Next Session Notes
- [ Update here ]

---

## Session 5 - 31 Mar 2026 (CHECKPOINT)

### What we discussed
- Frontend complexity was the primary pain point: too many transient states and brittle intermediate AJAX interactions.
- Goal was explicitly simplified workflow: backend-first processing, minimal frontend, and WooCommerce-native flow continuation.
- Validated target sequence: submit checkout -> backend creates/sends request -> modal opens -> signing completes -> backend confirms signed state -> checkout resumes to payment.

### Key Decisions & Why
1. **Backend-first send flow kept, frontend send removed**
   - Reason: avoid race conditions and session mismatch noise from client-triggered send calls.

2. **Minimal frontend marker instead of a clickable signing button**
   - Replaced button semantics with a plain payload marker in checkout error output.
   - Reason: reduce UI branching and avoid customer confusion.

3. **Removed explicit custom network timeouts in API calls**
   - Reason: reduce false negatives/timeouts caused by aggressive overrides.

4. **Modal workflow simplified**
   - Frontend now focuses on only three actions: detect sign URL marker, open clean iframe modal, and re-trigger checkout once on `signing.completed`.

5. **Checkpoint freeze requested**
   - User confirmed latest test passed and requested full freeze/documentation point for continuation tomorrow.

### Current Working State (End of Session)
- Checkout correctly blocks with signature-required notice when needed.
- Sign request creation/send handled by backend.
- Frontend opens clean modal from payload marker and resumes checkout on completion.
- Webhook completion path still updates signed option flag and keeps signed users from being prompted again.

### Artifacts Saved
- Working code snapshot file created:
  - `esignature-firma.dev-api-rest-webhooks-and-checkout-process-validation.checkpoint-2026-03-31.php`

### Next Session Notes
- Keep this checkpoint as baseline.
- If new issues appear, compare against the checkpoint snapshot first before introducing new logic.
- Prefer incremental edits with explicit rollback anchors.

---

## Session 6 - 02 Apr 2026 (UX STABILIZATION)

### What we discussed
- Confirmed the integration works but UX felt inconsistent because checkout sometimes required a second click after signing.
- Reviewed code paths where race/timing can happen between `signing.completed`, API status visibility, and webhook delivery.
- Chose a simple and robust UX direction: explicit manual continue instead of automatic re-submit.

### Key Decisions & Why
1. **Keep backend-first flow, simplify frontend resume step**
   - Reason: preserve stability while removing hidden client-side timing behavior.

2. **Disable automatic checkout submit on `signing.completed`**
   - Reason: auto-submit can fire before backend status is finalized, creating confusing double-submit UX.

3. **Add explicit continue action in the same checkout notice area**
   - Added button: `I signed, continue checkout` in payload block below signature notice.
   - Reason: clear user intent and predictable retry path without extra complexity.

4. **Localize all customer-facing button/progress text**
   - Reason: keep translation-safe output consistent with project i18n standards.

### Technical Changes Made
- `get_firma_sign_payload()` now renders:
  - `#firma-sign-data` marker
  - hidden `#firma-continue-checkout` button (shown after signing completed)
- JS updates in checkout form hook:
  - `signing.completed` now closes modal and reveals continue button
  - removed auto `#place_order` trigger from completion event
  - clicking continue button triggers checkout submit and sets progress text
- Added localization keys in `$firma_l10n`:
  - `continue_checkout`
  - `checking_signature`

### Outcomes
- Flow is now explicit and deterministic for users.
- Reduced reliance on fragile timing between frontend message and backend state propagation.
- Customer-facing strings are fully translation-ready in the updated UX path.

### Remarks / Caveats
- A second click can still be needed in real delayed webhook/status cases, but now this is intentional and clearly communicated.
- This is a deliberate UX tradeoff for reliability and simplicity.

### Next Session Notes
- Keep this as current baseline unless a real regression appears in production logs.
- If needed later, improve button styling only (logic should stay unchanged).

---

## Session 7 - 05 Apr 2026 (ROLLBACK TO STABLE CHECKPOINT)

### What we discussed
- During additional experiments around webhook-driven owner-copy delivery, core checkout/signing UX started regressing.
- User explicitly requested to stop speculative changes and return to the last known-good workflow.
- Confirmed intent: preserve stable checkout behavior first; do not keep risky webhook experiments in active code path.

### Key Decisions & Why
1. **Rollback to last working checkpoint**
   - Reason: protect production behavior and remove unstable deltas.

2. **Freeze current working state**
   - Reason: avoid churn and reintroducing regressions while testing pressure is high.

3. **Document-only update, no new feature rollout**
   - Reason: keep code stable and maintain clear handoff context for next session.

### Current State (After Rollback)
- Checkout/signing flow is back to user-confirmed working baseline.
- Experimental owner-copy webhook variations were removed from active iteration path.
- Workspace git state is clean at the moment of this checkpoint record.

### Next Session Notes
- Any owner-copy implementation must be reintroduced only as an isolated, minimal, testable patch.
- Before changing webhook logic again, validate payload shape from real events and lock parsing contract first.

---

## Decision Log (Cross-Session Reference)

> **Format:** "Decision: X" | "Why: Y" | "Session: Z"

*Add important decisions here for quick lookup*

---

## Open Questions & Assumptions

> **List things we're assuming are true or questions left unanswered**

*Example format:*
- **Q:** Should webhook handle retries?
  - **A:** [To be decided]
  - **Session:** [N]

---

## Learnings & Patterns

> **Reusable insights gained from this project**

*Example format:*
- **WooCommerce Signature Verification:** HMAC-SHA256 with 5-minute replay window works well
- **Session:** [N]

---

## Session 8 - 03 Jun 2026 (HISTORY AUTOPILOT)

### What we discussed
- Chat session `api.firma.dev` disappeared from VS Code Sessions UI.
- Raw transcript was recovered from local Copilot storage.
- User requested persistent, non-manual history handling so next agents always start with full context.

### Key Decisions & Why
1. **Keep history inside project repo under assets**
    - Reason: survives VS Code/session cache issues and is visible to any future agent.
2. **Make logging automatic by agent policy**
    - Reason: remove dependence on manual updates after each session.

### Changes made
- Moved history files into `assets/chat-history/`:
   - `api.firma.dev-chat-history.md`
   - `conversation-history.md`
- Rewrote `.agent.md` with startup rule to always read both history files first.
- Added strict auto-log protocol in `.agent.md`:
   - append a session entry automatically after meaningful work,
   - skip noise-only sessions,
   - use a fixed session template.

### Current stable state
- Project now has centralized history folder in repo: `assets/chat-history/`.
- Agent instructions enforce read-first and auto-append behavior for session history.
- The recovered `api.firma.dev` conversation is preserved in markdown inside project files.

### Next session starting point
- Start with `assets/chat-history/conversation-history.md` and `assets/chat-history/api.firma.dev-chat-history.md`.
- Continue feature work in `esignature.php` only after history is read.

### Artifacts
- `assets/chat-history/api.firma.dev-chat-history.md`
- `assets/chat-history/conversation-history.md`
- `.agent.md`

---

## Session 9 - 04 Jun 2026 (STANDALONE SHORTCODE)

### What we discussed
- Need a standalone signing entry point for unknown clients outside Woo checkout.
- Target UX: place a shortcode on any WP page, customer fills data manually, signs agreement, then receives completion copy while owner also receives copy.

### Key decisions & why
1. **Use a separate standalone script with shortcode**
   - Reason: minimal cost and no disruption to checkout workflow.
2. **Shortcode contract: `[firma_esignature document="template_id"]`**
   - Reason: explicit template selection per page without admin pre-creating requests.
3. **Reuse existing webhook confirmation endpoint**
   - Reason: keeps post-sign confirmation logic consistent with existing integration.

### Changes made
- Added new file `firma-standalone-shortcode.php` with:
  - shortcode renderer and client form,
  - AJAX handler to create/send signing request from template,
  - iframe modal + `postMessage` handling,
  - webhook confirmation polling via `/wp-json/firma/v1/signing-completed-status`.
- Added module include in `esignature.php` to auto-load standalone shortcode script.
- Request creation for standalone path sets settings:
  - `send_finish_email: true`
  - `attach_pdf_on_finish: true`
  - `allow_download: true`

### Current stable state
- Existing checkout flow remains untouched.
- New standalone flow can be embedded on any page via shortcode.
- Owner-copy webhook flow remains in `esignature.php`.

### Next session starting point
- Verify end-to-end on a public page with real template ID.
- If needed, adjust field list in shortcode form to exact template requirements.

### Artifacts
- `firma-standalone-shortcode.php`

## Session 11 - 04 Jun 2026 (TEMPLATE-BASED STANDALONE)

### What we discussed
- User selected variant: password-protected page creates a new signing request on site.
- Target template is Visa Service Agreement with 3 signers; client is signer #3.

### Key decisions & why
1. **Use shortcode with template_id + client data form**
   - Reason: signer link cannot be known before request creation.
2. **Assign client data to recipient order #3 after template-based create**
   - Reason: template has three signer slots and client belongs to the third slot.
3. **Keep owner-copy logic in main webhook unchanged**
   - Reason: existing `certificate.generated` webhook flow already sends owner copy.

### Changes made
- Reworked `firma-standalone-shortcode.php` to:
  - accept `[firma_esignature document="TEMPLATE_ID"]`,
  - collect client profile fields,
  - create signing request from template,
  - patch recipient #3 with client data,
  - send request,
  - open modal with `https://app.firma.dev/signing/{recipient_id}`,
  - poll `/firma/v1/signing-completed-status` after signing.completed.

### Current stable state
- Standalone flow is independent from checkout file wiring.
- Main webhook owner-copy logic remains in `esignature.php` and applies to requests from standalone as well.

### Artifacts
- `firma-standalone-shortcode.php`
- `esignature.php`

---

*Last updated: 04 Jun 2026*

## Session 10 - 04 Jun 2026 (STANDALONE SIMPLIFIED)

### What we discussed
- User clarified standalone page must not create or send signing requests.
- Required behavior: shortcode only opens Firma modal and routes signer to Firma page.

### Key decisions & why
1. **Remove all backend request creation logic from standalone file**
    - Reason: keep standalone strictly independent and minimal.
2. **Use direct signing URL in shortcode attribute**
    - Reason: no local data collection or API orchestration.

### Changes made
- `firma-standalone-shortcode.php` simplified to:
   - shortcode rendering only,
   - open iframe modal with provided Firma URL,
   - optional status text updates from postMessage events.
- Removed from standalone file:
   - form fields,
   - AJAX handlers,
   - create/send API calls,
   - nonce and request processing code.

### Current stable state
- Main checkout script remains untouched and decoupled.
- Standalone shortcode now acts as a pure modal launcher for Firma URL.

### Next session starting point
- If needed, tune button text/style only.
- If Firma provides a dedicated public template URL format, use it directly in shortcode.

### Artifacts
- `firma-standalone-shortcode.php`

---

## Session 11 - 07 Jun 2026 (MCP REVIEW)

### What we discussed
- Review `.agent.md` AI instructions for the project
- Verify whether Firma MCP configured for VS Code actually works
- Check MCP availability in Cursor vs VS Code

### Key decisions & why
1. **VS Code config is correct but Cursor needs separate setup**
   - Reason: `.vscode/mcp.json` uses VS Code schema (`servers`); Cursor reads `.cursor/mcp.json` with `mcpServers` key
2. **Firma Docs MCP is live without auth; Firma API MCP requires OAuth**
   - Reason: live HTTP checks confirmed Docs returns tools/search; API returns 401 without Bearer token

### Changes made
- No code changes; diagnostic review only
- Verified endpoints: `https://docs.firma.dev/mcp` (OK), `https://mcp.firma.dev/mcp` (401 without OAuth)

### Current stable state
- `.agent.md` instructions are coherent and reference MCP tools `firma-api/*`, `firma-docs/*`
- `.vscode/mcp.json` correctly defines both Firma servers for VS Code / GitHub Copilot
- Cursor currently has no Firma MCP connected (only Vercel plugin in session MCP folder)
- Firma Docs MCP works from network; Firma API MCP needs OAuth sign-in on first use

### Next session starting point
- Restart Cursor after adding `.cursor/mcp.json`, complete OAuth for `firma-api` on first use

### Artifacts
- `.agent.md`, `.vscode/mcp.json`, `.cursor/mcp.json`, https://docs.firma.dev/guides/mcp

---

## Session 12 - 07 Jun 2026 (CURSOR MCP CONFIG)

### What we discussed
- Add Cursor MCP config mirroring existing VS Code setup

### Key decisions & why
1. **No separate AI instructions for Cursor vs VS Code**
   - Reason: user considers it self-evident which config applies per IDE

### Changes made
- Added `.cursor/mcp.json` with `firma-api` and `firma-docs` URL servers

### Current stable state
- Both IDEs have project-level MCP config: `.vscode/mcp.json` (VS Code), `.cursor/mcp.json` (Cursor)
- OAuth required on first use for `firma-api` in either client

### Next session starting point
- Verify green MCP status in Cursor Settings after restart

### Artifacts
- `.cursor/mcp.json`

---

## Session 13 - 07 Jun 2026 (CHECKPOINT)

### What we discussed
- MCP setup for Cursor, how agent uses MCP tools
- Live verification: last 3 signing requests via `firma-api`
- User request to lock current stable state

### Key decisions & why
1. **MCP in both IDEs, no extra AI instructions**
   - Reason: `.vscode/mcp.json` for VS Code, `.cursor/mcp.json` for Cursor; agent calls tools as needed after OAuth
2. **Standalone shortcode remains separate file**
   - Reason: not wired into `esignature.php`; load manually on WP site when needed

### Changes made
- `.cursor/mcp.json` — Firma API + Docs MCP for Cursor
- `conversation-history.md` — sessions 9–13 logged
- `firma-standalone-shortcode.php` — present in repo (template-based flow with form, AJAX create/send, iframe modal)

### Current stable state
- **Checkout integration:** `esignature.php` — unchanged, production-stable
- **Standalone:** `firma-standalone-shortcode.php` — `[firma_esignature document="TEMPLATE_ID"]`, creates request from template, patches recipient #3, opens signing iframe, polls webhook status; `send_signing_email: false`
- **MCP:** both servers work in Cursor after restart + OAuth; verified live list of signing requests
- **Naming pattern:** `LASTNAME-Firstname-Title-DOB-dd-Mon-yyyy` (e.g. `KHOTOVA-Ekaterina-Mrs-DOB-18-Jun-1984`)

### Next session starting point
- Wire standalone into WP if not yet included (`require` in theme/plugin bootstrap)
- End-to-end test standalone page with Visa template if needed

### Artifacts
- `.cursor/mcp.json`, `.vscode/mcp.json`, `firma-standalone-shortcode.php`, `esignature.php`

---

---

## Session 14 - 08 Jun 2026

### What we discussed
- Unify visa service agreement + consultation under `esignature.php`
- Firma template `8956775a-6869-4b8d-916b-f2129b9acf92`: single Signer, hybrid prefill
- Deprecate `firma-standalone-shortcode.php`

### Key decisions & why
1. **Single Signer model** — client fills optional annexures in Firma iframe; no multi-recipient data-only slots
2. **`agreement_group: visa_services`** — separate signed-state cache from `paid_consultation`
3. **Shared builders** — `firma_build_recipients_for_template`, `firma_build_field_overrides_for_template`, `firma_create_and_send_signing_request`
4. **Cart match** — WooCommerce category `visa-services` and/or `VISA_SERVICE_PRODUCT_IDS` constant
5. **Template API limits** — `visibility_conditions` for rep/sponsor blocks must be set in Firma UI; API added `has_representative` / `has_sponsor` checkboxes + `variable_name` on rep/sponsor fields

### Changes made
- `esignature.php` — visa map entry, signing context/builders, checkout refactor, `[firma_esignature]` shortcode + `firma_create_signing_request` AJAX (legacy action alias kept)
- `firma-standalone-shortcode.php` — deprecated stub requiring `esignature.php`
- Firma template — `description: Visa Service Agreement`, rep/sponsor `variable_name`s, trigger checkboxes
- `.agent.md` — visa template ID, constants, shortcode docs

### Current stable state
- Checkout: consultation (product IDs 20203/20047, 19741/20202) + visa (`visa-services` category)
- Standalone: `[firma_esignature document="visa_services"]` or template UUID
- API test create: recipient prefill for name/email/phone/address OK; birthdate via `fields[]` override + `custom_fields.birthdate`
- **Still manual in Firma UI:** conditional visibility on rep/sponsor/AA1–7 field slots

### Next session starting point
- Finish conditional visibility + AA1–7 annexure slots in Firma template editor
- Add `VISA_SERVICE_PRODUCT_IDS` to wp-config when product IDs are confirmed
- Live E2E on WP: visa checkout + standalone page + consultation regression

### Artifacts
- `esignature.php`, `firma-standalone-shortcode.php`, `.agent.md`

---

## Session 15 - 08 Jun 2026

### What we discussed
- Pivot away from Firma conditional/hidden fields (UI unreliable)
- Delete `firma-standalone-shortcode.php`; everything in `esignature.php`
- Copy consultation EN client fields into visa template page 12
- Next: generate annexure read-only/textarea fields from WP form data at signing-request create

### Key decisions & why
1. **No conditional visibility in Firma template** — E-Signature hide/show pattern abandoned; dynamic annexures will be built in WP and sent as `fields[]` on create
2. **No `firma_visa_template_id()` helper** — visa template ID inline in map like other entries (`VISA_SERVICE_AGREEMENT` constant or default UUID)
3. **`firma_build_field_overrides_for_template`** — returns `$context['fields']` when present (future visa annexure package)
4. **Visa template base fields** — copied from Paid Consultation EN: title, full_name, email, phone_number, street_address, city, postal_code, state_province, country (page 12, same coordinates as consultation page 8)

### Changes made
- Deleted `firma-standalone-shortcode.php`
- `esignature.php` — removed `firma_visa_template_id()`, `firma_get_visa_template_field_ids()`; field overrides from context
- Firma template `8956775a-...` — 9 client fields added via API (from consultation `6f31d364-...`)

### Next session starting point
- User will refine visa template layout in Firma UI and share annexure generation idea
- Implement WP builder: form data → recipients + read-only text/textarea fields per annexure block

### Artifacts
- `esignature.php`, `.agent.md`

---

## Session 16 - 08 Jun 2026

### What we discussed
- Implement dynamic Annexure 2 blocks for Visa Service Agreement from WP checkout + EPO
- Firma API smoke test: new fields without `template_field_id` are ignored on template-based create

### Key decisions & why
1. **Template field overrides** — use `annexure_left`, `annexure_right`, `client_signature` placeholders with `template_field_id` at SR create (API drops unmatched new fields)
2. **Legacy fields off-page** — 11 old per-line prefilled fields moved to y=99 on template + hidden again at SR create as safety net
3. **EPO mapping** — label-based section/field matching; tunable via `FIRMA_VISA_EPO_FIELD_MAP`
4. **Offline clients** — same checkout path via `visa-services` category or `VISA_SERVICE_PRODUCT_IDS` + EPO (no separate shortcode required)

### Changes made
- `esignature.php` — parties collection, layout engine, `firma_enrich_visa_signing_context()`, template field ID overrides
- Firma template `8956775a-...` — added 3 placeholder fields; moved 11 legacy fields off-page
- `.agent.md` — documented new constants and template field approach

### Next session starting point
- Staging E2E with real EPO cart; tune `FIRMA_VISA_EPO_FIELD_MAP` if labels differ
- Review page 12 PDF formatting (line heights) on test SR
- Add offline product ID to `VISA_SERVICE_PRODUCT_IDS` when confirmed

### Artifacts
- `esignature.php`, `.agent.md`

---

## Session 17 - 09 Jun 2026

### What we discussed
- Visa EPO simplification plan: remove party parsing, left/right layout, standalone shortcode
- Page 12 rebuilt in Firma: consultation-style primary block + single annexure_details textarea

### Key decisions & why
1. **No standalone flow** — checkout-only; removed shortcode, AJAX, resolve-helpers
2. **EPO as cart blob** — `firma_contract_cart_epo_text()` linear scan; no field maps or party arrays
3. **Contract rules** — English-only agreement text; dates `d-M-Y` via `firma_contract_format_date()`
4. **Single override** — only `annexure_details` text_area at SR create; primary via recipients

### Changes made
- `esignature.php` — removed ~1058 lines (visa micro-layer, standalone); added contract helpers + simplified visa builders (~2124 lines)
- `.agent.md` — contract rules, page 12 layout; removed standalone/annexure_left/right docs
- `assets/epo/page12-layout.md` — field UUID table

### Current stable state
- Visa checkout: `billing_*` → recipients; EPO blob → `annexure_details` override
- Consultation unchanged; shared `firma_contract_format_date()` for SR name + birthdate

### Next session starting point
- Staging E2E: RU checkout → EN annexure blob, page 12 PDF layout, consultation regression

### Artifacts
- `esignature.php`, `.agent.md`, `assets/epo/page12-layout.md`

---

## Session 18 - 09 Jun 2026

### What we discussed
- Fatal on visa checkout: `WPML_Package_Helper::translate_string()` ArgumentCountError

### Key decisions & why
1. **Never call `wpml_translate_string` with 2 args** — WPML package helper requires exactly 3: `(string, context, name)`
2. **Removed blind `wcml_translate_string` call** — same risk; keep only guarded 3-arg WPML filters

### Changes made
- `esignature.php` — `firma_contract_translate_line_to_english()` helper; fixed filter arity

### Current stable state
- Checkout should no longer fatal on WPML string-translation hook

### Next session starting point
- Re-test visa checkout; if EPO stays RU, register strings in WPML or add explicit label map

### Artifacts
- `esignature.php`

---

## Session 19 - 09 Jun 2026

### What we discussed
- Visa SR signed but page 12 `annexure_details` empty — no additional applicant EPO blob

### Key decisions & why
1. **Dropped dashed-header gate** — EPO headers often lack dashes in `tmcartepo`; collect after skipping service radios only
2. **Static label map instead of WPML** — WPML filters caused fatals; RU→EN map for known EPO labels
3. **Field override needs position** — `annexure_details` override now includes template coordinates from Firma

### Changes made
- `esignature.php` — rewrote `firma_contract_cart_epo_text()`, added `firma_contract_epo_label_map()`, position on override, debug `visa_epo_prepared`
- `.agent.md`, `assets/epo/page12-layout.md` — translation note

### Next session starting point
- Re-test visa checkout; check debug.log for `visa_cart_epo_text` char_count > 0 and annexure textarea on page 12

### Artifacts
- `esignature.php`

---

## Session 20 - 09 Jun 2026

### What we discussed
- debug.log shows 1772-char EPO blob but user sees empty annexure textarea on page 12

### Key findings
- Firma SR `97220b85-...` **does** contain full `read_only_value` on `annexure_details` — PHP/API path works; issue is PDF/iframe rendering or layout overlap
- First checkout attempt hit `already_signed_short_circuit` (no new SR)

### Changes made
- Template field enlarged via MCP (y=41, w=86, h=52)
- PHP override synced + `background_color: #FFFDE7`
- EPO filter: skip visa questionnaire rows; party block only; improved tail detection
- Debug: log `field_overrides` on SR create

### Next session starting point
- Fresh checkout (new email or clear `firma_signed_*` option); verify yellow textarea on page 12

### Artifacts
- `esignature.php`, Firma template `fcea8758-...`

---

## Session 21 - 09 Jun 2026

### What we discussed
- Visa EPO blob approach rejected; user will use multiple Firma templates per scenario
- Reset codebase to stable consultation baseline (option B)

### Key decisions & why
1. **Revert `esignature.php` to commit `9d1b2ef`** — consultation-only, no visa bloat
2. **No visa code in PHP until templates + field map exist** — document plan in `assets/visa/README.md`
3. **Lessons preserved** — `post_name` for EPO, `template_field_id` overrides work, Firma field visibility/Signer assignment matters, WPML 3-arg filters only

### Changes made
- `esignature.php` restored from HEAD (1581 lines); `firma-standalone-shortcode.php` restored
- Removed experimental visa PHP (~680 lines uncommitted)
- Added `assets/visa/README.md`, `assets/visa/page12-experiment.md`
- Updated `.agent.md` for clean-slate visa planning

### Current stable state
- Production path: paid consultation products only via `firma_get_template_map()`
- Visa: planning docs only; Firma templates in cloud are experimental reference

### Next session starting point
- Read `assets/visa/README.md` → define scenarios → create Firma templates → wp-config field map → minimal PHP

### Artifacts
- `assets/visa/README.md`, git baseline `9d1b2ef`

---

## Session 22 - 09 Jun 2026

### What we discussed
- User rejected mistaken `git checkout HEAD` revert that discarded useful checkout refactor (~680 lines)
- Correct Option B: consultation stable + shared builders kept; visa bloat removed; clean-slate docs for multi-template visa

### Key decisions & why
1. **Do not wholesale-revert to HEAD** — refactor helpers are salvage and visa starting point
2. **No visa checkout branch** — no EPO blob, label maps, hardcoded template UUIDs, `firma_is_visa_template`, standalone in main file
3. **Template map** — consultation entries + `signer_role`/`prefill_source`; visa block commented until Firma templates exist

### Changes made
- `esignature.php` — restored shared builders (~189 lines), checkout uses `firma_create_and_send_signing_request()`; no visa runtime code
- `firma-standalone-shortcode.php` — restored from git HEAD (optional separate snippet)
- `.agent.md`, `assets/visa/README.md` — document builders + planned visa path

### Current stable state
- Consultation checkout: same behavior, cleaner create path via shared builders
- Visa: planning docs only; uncomment map entry + implement scenario builder when templates ready

### Next session starting point
- Read `assets/visa/README.md` → define scenarios → Firma templates → wp-config → `$context['fields']` overrides

### Artifacts
- `esignature.php` (~1770 lines), `assets/visa/README.md`

---

## Session 23 - 15 Jun 2026

### What we discussed
- Visa clean-slate: payload shape for Firma API (recipients + per-field `template_field_id` overrides)
- First live test with test API key via `scripts/dev/local.env` (not MCP / not prod)

### Key decisions & why
1. **Test via `FIRMA_TEST_API_KEY`** — watermarked SR, no prod checkout
2. **Steps 1–4 loop** — template fields → build payload → create → send → open signing UI

### Changes made
- No code changes; one-off PHP via `scripts/dev/bootstrap.php`

### Current stable state
- Test SR `88b52202-6139-48a7-8b7b-810c6030e7be` on template `8956775a-...`: recipients prefilled; `annexure_details` text_area has 188-char `read_only_value` in API (GET `/fields`)

### Next session starting point
- User verifies page 12 in Firma signing UI; then design per-field slots (clean-slate) or iterate layout

### Artifacts
- Sign URL: `https://app.firma.dev/signing/d6971452-d2eb-4887-b78d-9ab1c0a9b18e`

---

## Session 24 - 16 Jun 2026

### What we discussed
- EPO debug dumps (RU/EN, full party scenario); `cssclass` as stable site anchor
- Visa contract architecture: declarative site id ↔ Firma field map in plugin settings
- Signers, gates, textarea vs per-field blocks; EN-only contract text

### Key decisions & why
1. **No merge, no first-wins** — each contact field is independent; billing and EPO primary are never merged
2. **Signers:** 1-signer template when billing = primary; 2-signer when different (Signer 1 = billing, Signer 2 = `firma_primary_*`)
3. **Contract content:** rep + sponsor = Firma text fields; AA 18+ and minors = textareas; questionnaire ignored except indicators (`firma_visa_type`, `*_added` gates)
4. **EPO:** Element class name → `cssclass` in cart; repeater instances via `cssclass` + `repeater` index
5. **Template selection:** gate-checkboxes + signer count + visa type → `template_id` from plugin settings (wp-config overrides)
6. **Next step:** user builds Firma 1-signer + 2-signer templates before any plugin code

### Changes made
- Rewrote `assets/visa/README.md` with approved spec

### Current stable state
- Consultation checkout in unoSignature unchanged; visa planning doc is source of truth
- Firma reference template `8956775a-...`; test-key SR path verified

### Next session starting point
- User completes Firma templates + UUID/logical-name table; then plugin settings map + parser

### Artifacts
- `assets/visa/README.md`, EPO dumps in `.debug.log`

---

## Session 25 - 03 Jul 2026

### What we discussed
- Pivot back to **1 signer + 3 textareas** (additional_applicants, representative, sponsor); payer = billing only
- Test SRs for updated template layout; MCP vs test API key confusion

### Key decisions & why
1. **1 signer** — billing/payer signs; rep/sponsor/AA/children are EN text in textareas only
2. **additional_applicants** — adults + minors in one textarea, separate sub-blocks inside text
3. **Empty blocks** — omit `fields[]` override (column visible, empty); **no `visibility_conditions`**
4. **Backend** — collect EPO, format, translate EN, send ready strings to textareas
5. **Template ID** `8956775a-...` unchanged; field UUIDs unchanged; **positions/sizes** change when user edits Firma layout — always `templates_get` before overrides
6. **Test SRs** — **only** `FIRMA_TEST_API_KEY` via `scripts/dev/bootstrap.php`; **MCP OAuth = live key**, read templates only, do not create/send test SR via MCP

### Changes made
- Test SRs (test key): full `98502958-...`, partial `b410ae67-...`
- Rewrote `assets/visa/README.md`; updated `.agent.md`, `scripts/dev/README.md`

### Current stable state
- Textarea positions (Jul 2026): 29×60 at y=35 (x: 7 / 37 / 67)
- Live SRs created by mistake via MCP (no TEST badge) — do not repeat

### Next session starting point
- User verifies new test sign URLs; then plugin settings + EPO → textarea builders

### Addendum (same session)
- **Field overrides = data only.** Never include `position`, `page_number`, width/height in `fields[]` — template layout is configured in Firma UI, not via API.

### Artifacts
- Full TEST sign URL: `https://app.firma.dev/signing/8cb039a9-bbb9-4794-9bef-acc0c70bb1d8`
- Partial TEST sign URL: `https://app.firma.dev/signing/155a6dfc-b70f-4312-a345-a2f143daad78`

---

## Session 26 - 04 Jul 2026

### What we discussed
- Horizontal Firma layout; compact one-line-per-person textarea format
- Iterative test SRs until PDF layout approved
- Full EPO field set for adults (8 contact fields); children name + date only

### Key decisions & why
1. **Template verified** — user approved final page 12 layout (04 Jul 2026)
2. **Text format locked** — block headers, line labels, boilerplate text, blank lines (see `assets/visa/README.md`)
3. **additional_applicants** — starts with `Additional Applicant's:` + blank line; labels: Primary Applicant, Spouse/Partner, Additional Applicant (18+), Adult child (18+), Minor child
4. **No `DOB` word** in contract; dates as `d-M-Y` only
5. **Payload = data only** — never position/size/page in `fields[]`
6. **Test SRs** — test key via `scripts/dev` only; MCP read-only

### Changes made
- Multiple TEST SRs; final reference `0ed66013-d5f4-4650-910f-bbfe4bd04de9`
- Full rewrite `assets/visa/README.md`; updated `.agent.md`, `scripts/dev/README.md`

### Current stable state
- Visa Firma spec complete; ready for plugin implementation (parser + text builders + checkout)
- Verified sign URL: `https://app.firma.dev/signing/eaffbe62-761b-4f83-9657-a67ee54e1abb`

### Next session starting point
- Implement visa layer in unoSignature per README (settings, EPO parser, textarea builders, checkout hook)

### Artifacts
- `assets/visa/README.md` (canonical spec)
- `assets/INDEX.md` (local artifact inventory — gitignored with `/assets/`)
- Reference TEST SR: `0ed66013-d5f4-4650-910f-bbfe4bd04de9`

---

## Session 27 - 04 Jul 2026 (documentation audit)

### What we discussed
- Pipeline explanation (settings → parser → text builders → checkout)
- User asked to fix all documentation before Settings implementation
- Inventory of `assets/` files not obviously in history

### Key decisions & why
1. **`assets/` + `.agent.md` are gitignored** — spec lives locally; plugin code in `unosignature/` is what gets committed
2. **Canonical spec** — only `assets/visa/README.md`; everything else is archive, site snippet, or reference
3. **`wp-e-signature-additional-applicants-selector.code-snippets.php`** — WP E-Signature form UI on site, **not** Firma/unoSignature; documented in INDEX, not part of visa plugin work
4. **Next implementation step** — Settings in `unosignature/includes/class-settings.php` + `class-config.php`

### Changes made
- Added `assets/INDEX.md`; updated `assets/visa/page12-experiment.md`, README link to INDEX; `.agent.md` pointer

### Current stable state
- Documentation complete for pre-Settings handoff
- Unrelated uncommitted git change: `unosignature/includes/class-updater.php` (not visa session work)

### Next session starting point
- **Settings step** — visa template UUIDs, product mapping, agreement_group in plugin admin

### Artifacts
- [`assets/INDEX.md`](../INDEX.md)

---

## Session 28 - 04 Jul 2026 (Settings)

### What we discussed
- Start visa implementation with Settings step; keep existing Signing agreement rules UI

### Key decisions & why
1. **Template/product mapping unchanged** — visa uses same `template_map` rows (product + `agreement_group` e.g. `visa_services` + Firma template ID)
2. **Three new admin fields** — Firma textarea UUIDs: additional_applicants, representative, sponsor
3. **`Config::get_visa_firma_fields()`** — effective UUIDs with built-in defaults + wp-config overrides
4. **`Config::is_visa_agreement_group()`** — detects `visa_services` for checkout/parser later

### Changes made
- `unosignature/includes/class-config.php` — visa field keys, getters, defaults
- `unosignature/includes/class-settings.php` — Visa service agreement section, sanitize UUID
- `unosignature/readme.md` — document visa settings + wp-config constants

### Current stable state
- Settings step complete; parser/text builders/checkout not started

### Next session starting point
- Cart parser (EPO `cssclass` + repeater)

### Artifacts
- Admin: Settings → unoSignature → Visa service agreement

---

*Last updated: 04 Jul 2026*

