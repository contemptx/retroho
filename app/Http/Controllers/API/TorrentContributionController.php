<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Exceptions\TorrentContributionException;
use App\Services\TorrentContributionLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use JsonException;
use Throwable;

final class TorrentContributionController extends BaseController
{
    public function capabilities(Request $request): JsonResponse
    {
        return $this->respond($request, static fn (TorrentContributionLedger $ledger, int $caller): array => $ledger->capabilities($caller));
    }

    public function show(Request $request, string $contributionId): JsonResponse
    {
        return $this->respond($request, fn (TorrentContributionLedger $ledger, int $caller): array => $ledger->read($caller, $contributionId, $this->scope($request, $caller)));
    }

    public function update(Request $request, string $contributionId): JsonResponse
    {
        return $this->respond($request, function (TorrentContributionLedger $ledger, int $caller) use ($request, $contributionId): array {
            if (!$request->isJson() || \strlen($request->getContent()) > 65536 ||
                !\in_array(strtolower((string) $request->header('Content-Encoding', 'identity')), ['', 'identity'], true)) {
                throw new TorrentContributionException('INVALID_REQUEST', 422);
            }

            // Authenticate middleware merges api_token into the decoded input.
            // Validate the original client JSON so its strict field whitelist
            // cannot confuse injected credentials with contribution data.
            try {
                $input = json_decode($request->getContent(), true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new TorrentContributionException('INVALID_REQUEST', 422);
            }

            if (!\is_array($input) || array_is_list($input)) {
                throw new TorrentContributionException('INVALID_REQUEST', 422);
            }

            return $ledger->apply($caller, $contributionId, $input, $this->scope($request, $caller));
        });
    }

    private function scope(Request $request, int $caller): string
    {
        $instance = $request->header('X-Torrent-Ledger-Instance');

        if ($request->header('X-Torrent-Caller') !== (string) $caller || !\is_string($instance) || !preg_match('/^[a-f0-9]{64}$/D', $instance)) {
            throw new TorrentContributionException('SCOPE_CONFLICT');
        }

        return $instance;
    }

    private function respond(Request $request, callable $operation): JsonResponse
    {
        try {
            $caller = $request->user();

            if (!$caller || !$caller->group?->is_owner || !is_numeric($caller->id) || (int) $caller->id < 1) {
                throw new TorrentContributionException('UNAUTHORIZED', 403);
            }

            // Read the original URL query string; Authenticate middleware may
            // have merged its Bearer token into the mutable query input bag.
            if ($request->getQueryString() !== null) {
                throw new TorrentContributionException('INVALID_REQUEST', 422);
            }
            $result = $operation(new TorrentContributionLedger(DB::connection()->getPdo()), (int) $caller->id);

            return response()->json(['success' => true, 'data' => $result])->header('Cache-Control', 'no-store');
        } catch (TorrentContributionException $error) {
            return response()->json(['success' => false, 'code' => 'TORRENT_CONTRIBUTION_'.$error->reason,
                'error'                        => 'The torrent contribution could not be confirmed.'], $error->status)->header('Cache-Control', 'no-store');
        } catch (Throwable) {
            return response()->json(['success' => false, 'code' => 'TORRENT_CONTRIBUTION_UNAVAILABLE',
                'error'                        => 'The torrent contribution is temporarily unavailable.'], 503)->header('Cache-Control', 'no-store');
        }
    }
}
