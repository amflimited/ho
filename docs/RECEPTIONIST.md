# AI Receptionist — the second offer ("hear it before you buy it")

## What this is

A recurring-revenue product on the same machine. The pipeline already finds and
researches a business; this track turns that research into an **audio demo of an
AI receptionist answering as their business**, pitches it, and converts to a
$149/mo Stripe subscription. Fulfillment is manual-by-design: Adam configures
Sona (Quo's AI agent) per customer in his own Quo workspace — labor that only
happens after money arrives.

## Verified economics (June 2026)

- Quo numbers: $5/mo each, unlimited per workspace.
- Sona: configured per call-flow/number with its own greeting + knowledge base
  (one workspace can answer as many different businesses).
- Sona usage: ~10 calls/mo included; $25/mo ≈ 50 calls workspace-wide; <15s calls free.
- Cost per customer ≈ $5–10/mo → ~95% margin at $149/mo.

## The flow

```
research record (exists, verified)
→ voice worker: LLM writes 3 call-scenario scripts   prompts/callscripts.md
→ TTS renders two-voice audio                        src/Llm/Tts.php (Gemini multi-speaker)
→ demo page /listen/{slug}                           public/listen.php
→ pitch email links the demo                         (next: receptionist pitch track)
→ heat tracking on visits                            existing previews/Heat
→ Stripe SUBSCRIPTION checkout $149/mo               checkout.php?offer=receptionist
→ webhook order (package=receptionist) → Notify      existing
→ Adam: 20-min manual Quo/Sona setup                 runbook below
→ welcome email: carrier forwarding code             runbook below
```

## Honesty rules (same Truth Gate ethos)

- Demo page says **"hear what your receptionist will sound like"** — a preview,
  never presented as a recording of a real call.
- Scripts use ONLY verified research facts. The receptionist NEVER invents
  prices, availability, or services — it takes a message and captures contact info.
- Demo only what Sona can actually do: answer questions from a knowledge base,
  take messages, capture callbacks. No calendar-booking promises.

## Fulfillment runbook (manual, per paying customer, ~20 min)

1. Quo: add a number (Indiana area code) to the workspace ($5/mo).
2. Configure Sona on that number: greeting + knowledge base pasted from the
   business's research record (services, hours, service area). Test-call it.
3. Welcome email: their conditional-forwarding dial code —
   Verizon `*71<sona-number>` · AT&T/T-Mobile `**004*<sona-number>#`
   (undo: `*73` / `##004#`). Their number never changes; only missed calls forward.
4. Week one: personally forward caught leads ("your receptionist caught this 8pm
   call"). That email is the retention engine. Automate via Quo webhooks later.

## Built in this milestone

- `migrations/004_receptionist.sql` — `call_demos` table + settings
- `prompts/callscripts.md` — scenario script generation (3 scenarios, JSON out)
- `src/Llm/Tts.php` — Gemini multi-speaker TTS → WAV (key: `tts_api_key` setting)
- `src/Workers/Voice.php` — scripts for verified leads, then audio; idempotent
- `public/listen.php` + `/listen/{slug}` rewrite — phone-screen demo page
- `checkout.php?offer=receptionist&biz={business_slug}` — subscription mode
- `StripeWebhook` accepts `package=receptionist`
- Runner job `voice`; cockpit button + settings (`tts_api_key`, `rcpt_price_cents`,
  `ap_voice_per_run`)

## Not built yet (in order)

1. Receptionist pitch track — a touch-1 email variant that leads with the demo
   link instead of the website preview (Pitch.php + a prompt).
2. Quo webhook → auto weekly "leads we caught" digest to customers.
3. Choice point: does the receptionist replace or ride beside the website offer
   per lead? (Score::route could pick per gap profile.)
