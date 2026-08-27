<?php declare(strict_types=1);

namespace App\Enum;

enum CirculationLedgerEntryType: string
{
    case Donated = 'donated';
    case Requested = 'requested';
    case RequestCancelled = 'request_cancelled';
    case RequestExpired = 'request_expired';
    case HandoverOpened = 'handover_opened';
    case HandoverConfirmed = 'handover_confirmed';
    case HandoverCompleted = 'handover_completed';
    case HandoverCancelled = 'handover_cancelled';
    case MarkedFinished = 'marked_finished';
    case Retired = 'retired';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Donated => 'circulation.ledger_donated',
            self::Requested => 'circulation.ledger_requested',
            self::RequestCancelled => 'circulation.ledger_request_cancelled',
            self::RequestExpired => 'circulation.ledger_request_expired',
            self::HandoverOpened => 'circulation.ledger_handover_opened',
            self::HandoverConfirmed => 'circulation.ledger_handover_confirmed',
            self::HandoverCompleted => 'circulation.ledger_handover_completed',
            self::HandoverCancelled => 'circulation.ledger_handover_cancelled',
            self::MarkedFinished => 'circulation.ledger_marked_finished',
            self::Retired => 'circulation.ledger_retired',
            self::Lost => 'circulation.ledger_lost',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Donated => 'fa-hand-holding-heart',
            self::Requested => 'fa-hand',
            self::RequestCancelled, self::RequestExpired => 'fa-xmark',
            self::HandoverOpened => 'fa-arrows-turn-to-dots',
            self::HandoverConfirmed => 'fa-check',
            self::HandoverCompleted => 'fa-handshake',
            self::HandoverCancelled => 'fa-ban',
            self::MarkedFinished => 'fa-flag-checkered',
            self::Retired => 'fa-box-archive',
            self::Lost => 'fa-triangle-exclamation',
        };
    }
}
