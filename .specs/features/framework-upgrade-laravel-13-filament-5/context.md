# Framework Upgrade — Captured Decisions

Gray areas surfaced during Specify and resolved by the user on 2026-07-18. These are decisions, not assumptions — do not re-open them during Design or Execute without saying so explicitly.

## D1 — Sequencing: three staged phases

**Chosen**: Phase 1 Filament 3→4 (+ fabricator 2.6→3.1) on Laravel 12; Phase 2 Filament 4→5 (+ fabricator 3.1→4.1); Phase 3 Laravel 12→13 (+ PHP/Sail, PHPUnit).

**Rejected**: two-phase (mixes framework and admin-panel breakage in one gate); single big-bang (fastest when it works, unbisectable when it doesn't).

**Why**: The v3→v4 hop carries essentially all the breaking changes; v5 is functionally identical to v4 apart from Livewire 4, and Laravel 13 shipped with zero breaking changes. Staging isolates the one genuinely risky hop behind its own green test gate, so a regression is attributable to a single phase and revertable on its own.

## D2 — `PageResource`: re-derive from vendor v4.1.0

**Chosen**: Take `filament-fabricator` 4.1.0's own `PageResource` as the new base and re-apply only this app's additions (Generation section, status/batch/provider/generated_at columns, status + layout filters, `visit` action, `publish` action).

**Rejected**: mechanically porting the existing fork (carries two-major-old vendor code forward, drifting silently from upstream); dropping the fork for `ResourceSchemaSlot` injection (smallest long-term surface, but the largest behavior-change risk to take during an upgrade).

**Why**: `app/Filament/Resources/PageResource.php` is a copy of the vendor resource with local edits layered on. Across fabricator 2→4 that vendor class was rewritten for Filament v4 Schemas, so a mechanical rewrite of our stale copy would preserve v3-era structure the upgrade script was never designed to reach. Re-deriving keeps the diff against upstream to just our intentional additions.

**Note**: D2 is a re-derivation, not a redesign. The admin-visible behavior asserted by `tests/Feature/Filament/PageResourceTest.php` must be preserved exactly; that test is the gate.

## D3 — PHP target: Sail runtime 8.5

**Chosen**: Bump Sail's runtime from 8.3 to 8.5 in Phase 3, alongside Laravel 13. Composer's `php` constraint moves `^8.2` → `^8.3` in Phase 2 (forced by fabricator 4.1.0).

**Rejected**: PHP 8.4 (proven, but leaves a local/container gap); staying on 8.3 (smallest change, keeps the mismatch).

**Why**: The local CLI is already PHP 8.5.8 while the container runs 8.3 — a version gap that can hide bugs in either direction. Laravel 13 requires ^8.3, so the floor rises regardless; matching the container to the local runtime closes the gap in the same phase.

**Risk accepted**: PHP 8.5 is recent enough that a transitive dependency may not yet be certified on it. Phase 3 verifies with a clean `composer update` on the 8.5 image before the phase is considered green; if a dependency blocks, fall back to 8.4 and record it here.
