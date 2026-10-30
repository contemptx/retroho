# Routine announce acknowledgement — 30 October 2026

Follow-up to tracker reliability PR #4. Production remained on 919a64f with a clean tracked tree when this work began.

## Observed behaviour

At 00:10 UTC on 30 October, the current-hour aggregate counters showed 32 successful announcements, 23 minimum-interval rejections and two duplicate rejections. There were 143 active visible peers, current peer timestamps, no peers older than the two-hour cutoff and no pending peer/history batches. These are an observation, not proof that every reported disconnection shares this cause.

The existing tracker returns bencoded failure responses for an otherwise valid routine announce arriving before the timer allows another accounting update. This can report a tracker error even while that peer remains active.

## Narrow change

Authenticate and validate the request, account, group and torrent exactly as before. When the existing timer raises code 162, acknowledge only an ordinary event with the same user/peer identity already active, visible and updated within two hours. Unknown, inactive, invisible, expired and foreign peers retain the failure response. Started, stopped, completed and paused events do not use this shortcut.

Return the existing bounded peer-list response and current visible counts with a retry interval based on the remaining original Redis timer (minimum 30 seconds). Do not extend that timer or enqueue another ProcessAnnounce job. No uploaded/downloaded totals, history, slot counts, peer timestamps or entitlements are written by the acknowledgement. The next normally admitted announce resumes accounting from cumulative client counters.

Separate fixed aggregate outcomes, deferred_162_duplicate and deferred_162_interval, retain the existing 48-hour expiry and no-identifier policy.

## Validation and release

Five additional controller regression groups cover early and duplicate acknowledgements, unchanged timers/peer state/dispatch count, resumption at the original deadline, rejected peer states, lifecycle handling and denied account permissions. Existing tests remain in place. PHP syntax passed on the live server's CLI; execution of the isolated controller suite there was unavailable because its CLI lacks PDO SQLite. Dedicated CI, including real Redis and SQLite/MySQL cleanup suites, is the release test gate. No extension was installed into production to run tests.

Deploy only the three reviewed files after CI and exact-tree verification, retaining private rollback copies and all unrelated server files. Reload PHP-FPM and verify real traffic counters, fresh SQL updates and empty batches. No customer test grants, fabricated sales, peer-table reset, database migration, queue reconfiguration or Patreon activation is involved.

This prevents routine repeats from being reported as hard failures; it is not a guarantee against client network disconnects or an instruction to ignore genuine authentication and lifecycle errors.
