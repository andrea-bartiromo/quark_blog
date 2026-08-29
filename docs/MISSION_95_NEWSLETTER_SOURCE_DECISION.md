# Mission 95 — Newsletter source context decision

## Decision

Choose **A: placement only**, with the bounded taxonomy:

- `popup`
- `homepage`
- `article`
- `unknown` for invalid/missing input at write time
- existing rows remain SQL `NULL` and report as `unknown/legacy`

Percorsi subscriptions remain a separate consent/subscription domain and are not folded into this newsletter field.

## Rationale

The real use case is aggregate signup volume by acquisition surface. Category would be unstable and ambiguous on reusable components; article ID/slug creates high-cardinality behavioral history without a demonstrated reporting need. Placement is privacy-minimal, bounded, operationally explainable, and sufficient to compare the three existing CTAs.

This field describes a public UI surface, not a person, consent purpose, campaign or attribution claim. It must not accept arbitrary client strings. No conversion rate may be shown without a real impression denominator.

## Implementation authorization

A nullable, indexed, backward-compatible column is approved. Server-side allowlisting is mandatory. This decision precedes and authorizes Mission 96; it does not itself change schema.
