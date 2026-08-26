<?php declare(strict_types=1);

namespace Module\Trust\Contract;

/**
 * The whole outbound surface of the Trust module. Scores are derived on read, never stored,
 * so every answer here reflects the current configuration and the current graph.
 */
interface TrustInterface
{
    public function getScore(string $context, int $userId): int;

    /**
     * The full map for a context, keyed by user id. Narrowed to the viewer's own entry
     * unless the viewer may administer the context.
     *
     * @return array<int, int>
     */
    public function getScores(string $context): array;

    public function getBand(string $context, int $userId): TrustBand;

    /** The only edge fact anyone other than the voucher may see. */
    public function getVouchCount(string $context, int $userId): int;

    public function meetsMinimum(string $context, int $userId): bool;

    /**
     * The context's effective settings, so a root provider can hand out the configured
     * anchor points and a caller can name the minimum it just refused someone against.
     */
    public function getConfig(string $context): TrustConfig;

    public function grant(string $context, int $fromUserId, int $toUserId, TrustLevel $level): void;

    public function revoke(string $context, int $fromUserId, int $toUserId): void;

    /**
     * The subject's own outgoing vouches. There is deliberately no way to read anybody else's.
     *
     * @return array<int, TrustLevel>
     */
    public function getOutgoing(string $context, int $fromUserId): array;
}
