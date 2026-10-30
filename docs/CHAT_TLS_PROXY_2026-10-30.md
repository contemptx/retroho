# Restore RetroHo chat transport without sharing the TLS key

## Confirmed failure

The Supervisor process `unit3d-chat-server:unit3d-chat-server_00` was FATAL. Its stdout reported EACCES reading `/etc/letsencrypt/live/retroho.com/privkey.pem`. Echo ran as www-data and attempted its own HTTPS listener on port 8443. The private key is mode 0600; neither 8443 nor 6001 was listening. The existing site CSP permits the HTTPS/WSS socket endpoint on port 8443.

This chat-service problem is separate from torrent tracker announcements.

## Repair design

Keep the browser endpoint `https://retroho.com:8443/socket.io/` unchanged. Let the existing Nginx service terminate TLS using the same certificate as the website. Run Echo with `host=127.0.0.1`, `port=6001`, `protocol=http`; clear its now-unused SSL path settings. Preserve all other Echo JSON properties, Redis connection and authentication settings. Do not commit the real `laravel-echo-server.json`, which may contain credentials.

Install the reviewed `ops/nginx/retroho-echo-8443.conf` at `/etc/nginx/conf.d/retroho-echo-8443.conf`. It passes explicit WebSocket upgrade headers and proxies only `/socket.io/`; other paths return 404. The backend binds only to loopback. The TLS private key retains its original permissions, ownership and contents.

## Controlled deployment and verification

Before changing runtime settings, verify both ports are available and preserve private backups of the original Echo configuration and metadata. Run `nginx -t` before reload. Start only the chat Supervisor process, verify the local Engine.IO polling handshake, then reload Nginx and verify a certificate-validated HTTPS polling handshake and WebSocket 101 upgrade through port 8443. Check that port 6001 is loopback-only and the website/login and tracker remain healthy. No chat message, customer grant or user-session change is needed for these probes.

On failure, restore the original Echo JSON, remove only this added Nginx include, validate/reload Nginx and stop the newly started chat process. Keep private deployment receipts under `/var/backups/arcadesystems-retroho/20261030-chat-tls-proxy`.

This changes transport configuration, not channel authorisation. Transport acceptance does not by itself demonstrate a two-user authenticated chat conversation. The PR deployment record should distinguish those results.

Reference: https://nginx.org/en/docs/http/websocket.html
