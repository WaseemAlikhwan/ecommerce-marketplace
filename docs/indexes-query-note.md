# Indexes / query note (Phase 11)

Phase 11 **does not** add speculative MySQL index migrations.

Storefront Catalog **S8C** already collected disposable EXPLAIN evidence on a bounded demo dataset and concluded that existing PK/unique/FK indexes bound correlated access; small-table scans were cost-based. No S8C index migration was justified without production cardinality or slow-query evidence (see ADR-040 / `docs/decisions.md` S8C hardening; `docs/architecture.md`).

Re-evaluate indexes only when a concrete demo-breaking or production slow path is identified with EXPLAIN — not as part of generic handoff polish.
