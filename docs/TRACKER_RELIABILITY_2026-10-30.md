# RetroHo tracker reliability review — 30 October 2026

## Scope and production evidence

This review follows reports of torrent clients repeatedly losing tracker connectivity and the website occasionally displaying no peers. The production baseline was `6d8ca87c5869372eacee114624ce731ca4160334`, with a clean tracked working tree. The earlier Patreon contribution and registration fixes are preserved.

Read-only production observations at 19:23 UTC on 29 October (03:23 on 30 October in the Philippines):

- The built-in PHP tracker is active. Announce jobs execute synchronously; the four background queue workers are not the announce execution path.
- The minute scheduler ran 15 times during the preceding 15 minutes. Peer/history batches were empty, and current peer rows were updating.
- There were 142 active visible seed peers and three active visible leech peers. No active peer was older than two hours. There were no user/torrent pairs over the configured three-location limit at that instant.
- All 43 non-deleted torrents' copied peer totals agreed with the active, visible SQL peer counts. Two torrents had no visible peers. This does not establish that every historical user report was merely a display error.
- There were no recent failed queue jobs, SQL errors or announce-related Nginx upstream errors in the inspected logs. The last stored failed job was from December 2025.
- CPU, memory and storage were well within capacity. Increasing the server size is not supported by these observations.
- About 25,000 announce requests across the inspected access logs returned HTTP 200. Tracker failures are also bencoded HTTP 200 responses, so HTTP status alone cannot establish tracker success. Gzip and variable response lengths make historical response-byte classification unreliable.

The main torrent detail and listing pages count SQL peers with `active=1` and `visible=1`. The five-minute task also copies these counts into torrent rows. A client can retain existing peer connections while its tracker announcements fail, so a client-side peer count is not sufficient evidence that the tracker received a recent update.

## Reproduced defects and changes

### Announce admission and reconnects

The previous location check allowed a fourth location because it rejected only counts greater than three. Once the stored count exceeded three, it also rejected refreshes and stop events from existing peers. The repair applies the configured limit to new peer identities and permits existing peers to refresh or stop.

A previous regular announce timer also blocked legitimate starts after a pause or restart. Stop events now release that timer and the preceding start-event guard; starts do not inherit the regular interval restriction. The separate 30-second duplicate-event guard and the normal regular-announce interval remain in force.

### Correct swarm counts

The previous response counted peers while filling a sample of at most 25 endpoints. This truncated complete/incomplete totals and included invisible peers in some counts. The repair calculates totals across all active, visible peers before sampling endpoints. It honours `numwant=0`, retains compact IPv4/IPv6 encoding, and samples eligible peers without depending on database row order.

### Peer cleanup race

The hourly cleanup previously selected old peers and later saved them inactive without checking whether an announce had refreshed them in the meantime. It also cleared a user's torrent history when another location was still active. The repair rechecks age and activity in the actual SQL update, and only clears stale history when no active peer remains for that user/torrent. The two-hour expiry and two-day retention policy are unchanged.

### Bounded diagnostic counters

The tracker records hourly aggregate outcomes in Redis with a 48-hour expiry. These distinguish successful announces, warnings and rejected announcements, including early regular announces and duplicate events. Counters contain no passkeys, usernames, IP addresses, peer IDs, info hashes, request URLs or raw exceptions. A failure to record a counter cannot change the tracker response.

## Verification and deployment boundaries

`tests/tracker/run.php` exercises the real controller with isolated fixtures. Its optional Redis checks use a random test-only prefix and actual Redis commands. `tests/tracker/cleanup.php` executes cleanup against isolated SQLite or the guarded `retroho_tracker_test` MySQL database, including a peer refreshed after the candidate scan but before expiry.

The dedicated GitHub workflow runs PHP 8.3, Laravel components matching production 11.19, MySQL 8 and Redis 7. It does not bootstrap the live application or use customer credentials, purchases or entitlements.

Deployment must verify the reviewed base and changed file manifest, retain a private rollback copy, install only the reviewed files and reload PHP-FPM. No database migration, peer-table reset, queue reconfiguration, server resize or customer action is required. Post-deployment acceptance uses normal live traffic and read-only peer/counter observations. The pull request records the final CI, deployed commit and acceptance results.

## Limits and follow-up evidence

The defects above are reproducible, but the available historical logs do not prove that they caused every disconnection report. The new aggregate counters provide evidence for further diagnosis without full announce logging. If reports persist, investigate the specific tracker message, client version and approximate time against these counters.

The Redis-to-SQL batch commands remove records before their database transaction commits. A database failure could therefore lose a batch; no such failure or backlog was observed in this review. This is a separate reliability hardening task, not an established explanation for the reported incident.

Protocol and scheduling references:

- [BitTorrent protocol specification, BEP 3](https://www.bittorrent.org/beps/bep_0003.html)
- [Laravel 11 task scheduling](https://laravel.com/docs/11.x/scheduling)
