# Source-scoped torrent contributions

This additive API lets a trusted owner API account record one purchase's
torrent contribution and later withdraw that contribution without removing
independent or separately purchased access. It does not create users, change
passwords, issue software licences or infer ownership from `newly_attached`.

## Endpoints and scope

All endpoints use the existing `auth:api`, `banned` middleware and the existing
owner-group permission. The caller is the authenticated numeric user ID; it
cannot be supplied in a body or replaced by a target account.

- `GET /api/user/torrent-contributions/capabilities` returns
  `{success:true,data:{version:"source-contributions-v1",caller_id,
  ledger_instance_id,generationFencing:true,selectiveWithdrawal:true,
  completeReadBack:true}}`.
- `PUT /api/user/torrent-contributions/{contributionId}` accepts only
  `{generation,desired,username,email,torrent_ids,idempotency_key}`.
- `GET /api/user/torrent-contributions/{contributionId}` reads that caller's
  exact current contribution. Another caller receives the same 404 as an
  unknown contribution.

PUT and GET require `X-Torrent-Caller` and `X-Torrent-Ledger-Instance` matching
the authenticated caller and current capabilities. Store the provider API URL,
caller ID and ledger instance with the original contribution. A changed scope
requires review; an empty withdrawal on another server cannot prove removal
from the original server. Rotating a token for the same API user preserves the
caller scope. The ledger instance is generated once by the migration.

Generation is a positive JSON integer up to JavaScript's safe integer maximum;
desired state is `active` or `withdrawn`. The recipient username and canonical
email must identify the same existing account. The recipient and sorted set of
1–1000 positive torrent IDs are immutable for a contribution. Idempotency keys
bind the exact caller, source, recipient, resources, generation and desired
state. A higher-generation withdrawal may be recorded before the original
grant; an older arriving grant cannot override that tombstone.

Successful responses have `{success:true,data:{contribution_id,caller_id,
ledger_instance_id,generation,desired,username,email,torrent_ids,complete,
resources:[{torrent_id,contribution_active,effective_access,
independent_support}]}}`. `complete` is true only after checking the complete
recorded resource set and current attachment state. It is not a download speed,
ratio or software-licence promise. An exact current retry can reconcile a lost
pivot; an older successful retry only reads the newer state. Fixed errors use
`TORRENT_CONTRIBUTION_*` codes and never return provider credentials or SQL.

## Existing attachments and concurrency

The first source operation snapshots existing pivots as independent support
under the recipient row lock. Existing API and web attach actions explicitly
record independent support even if a source already supplies the pivot. Their
remove actions clear independent support and preserve every active source.
The broad remove response reports retained source-supported torrents.

All these paths lock the same recipient row. MySQL uses fresh statement
snapshots under `READ COMMITTED`; SQLite tests use `BEGIN IMMEDIATE`. The
contribution, operation receipt, support evidence and effective pivots commit
together. Existing duplicate pivot rows are supported: they are all removed
only when no support remains. The migration does not rewrite existing pivots.
Staff web attachment management now requires the same owner-group permission
as the API. Direct database edits bypassing these entry points are not proof of
independent support and should be reconciled through the supported API.

## Release and verification

Apply `2026_10_29_000001_create_torrent_source_contributions.php` before serving
the changed attachment handlers. The API fails closed when its ledger schema is
unavailable. Rolling back the ledger while active contributions exist is
refused; do not discard source ownership records as an access-removal method.

The focused `Source torrent contribution checks` workflow runs the actual new
migration and service on SQLite and MySQL 8. Its synthetic fixtures cover
baseline/late independent support, overlapping sources and callers, scoped
reads, broad removal, idempotency, immutable bindings, generation conflicts,
revoke-before-grant, rollback, drift and real concurrent processes. It also
exercises the actual controller's owner and scope boundaries. No fixture calls
production, sends email, creates a real customer or changes a live purchase.

This draft must be reviewed and deployed in coordination with the admin
contribution adapter. A real seller sale/refund acceptance check remains a
separate production gate.
