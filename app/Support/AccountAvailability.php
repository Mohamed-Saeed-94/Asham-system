<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounts\Entities\BankAccount;
use Modules\Accounts\Entities\Safe;
use Modules\Ledger\Entities\LedgerEntry;

class AccountAvailability
{
    /**
     * نتائج فحص الـschema محفوظة طوال عمر العملية بدل الاستعلام عنها مع كل حساب.
     *
     * @var array<string, bool>
     */
    protected static array $schemaCache = [];

    public static function compute(string $type, int $id, ?string $from = null, ?string $to = null): ?array
    {
        $type = $type === 'safe' ? 'safe' : 'bank';

        $account = $type === 'bank'
            ? BankAccount::query()->find($id)
            : Safe::query()->find($id);

        if (! $account) {
            return null;
        }

        $opening = (float) ($account->opening_balance ?? 0);

        $base = LedgerEntry::query()
            ->when($type === 'bank', fn (Builder $query) => $query->where('bank_account_id', $id)->whereNull('safe_id'))
            ->when($type === 'safe', fn (Builder $query) => $query->where('safe_id', $id)->whereNull('bank_account_id'))
            ->when($from, fn (Builder $query) => $query->whereDate('entry_date', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('entry_date', '<=', $to));

        [$sumIn, $sumOut] = self::sumInOut($base);

        $sumIn = round($sumIn, 2);
        $sumOut = round($sumOut, 2);
        $available = round($opening + ($sumIn - $sumOut), 2);

        return [
            'id' => (int) $account->id,
            'name' => (string) ($account->name ?? ('#'.$account->id)),
            'type' => $type,
            'opening' => round($opening, 2),
            'in' => $sumIn,
            'out' => $sumOut,
            'available' => $available,
        ];
    }

    public static function availableBalance(string $type, int $id, ?string $from = null, ?string $to = null): ?float
    {
        $result = self::compute($type, $id, $from, $to);

        return $result['available'] ?? null;
    }

    protected static function sumInOut(Builder $base): array
    {
        if (self::hasColumn('ledger_entries', 'direction')) {
            $in = (clone $base)->where('direction', 'in')->sum('amount');
            $out = (clone $base)->where('direction', 'out')->sum('amount');

            return [(float) $in, (float) $out];
        }

        $typeIn = null;
        $typeOut = null;

        if (self::hasTable('transaction_types')) {
            $typeIn = DB::table('transaction_types')->whereIn('name', ['إيداع', 'ايداع', 'Deposit', 'Incoming', 'In'])->value('id');
            $typeOut = DB::table('transaction_types')->whereIn('name', ['سحب', 'Withdrawal', 'Outgoing', 'Out'])->value('id');
        }

        $inQuery = (clone $base);
        $outQuery = (clone $base);

        $hasTypeColumn = self::hasColumn('ledger_entries', 'transaction_type_id');
        $hasStatusColumn = self::hasColumn('ledger_entries', 'transaction_status_id');
        $hasStatusesTable = self::hasTable('transaction_statuses');

        if ($hasTypeColumn || ($hasStatusColumn && $hasStatusesTable)) {
            $inQuery->where(function ($query) use ($typeIn, $hasTypeColumn, $hasStatusColumn) {
                if (! is_null($typeIn) && $hasTypeColumn) {
                    $query->where('transaction_type_id', $typeIn);
                }

                if ($hasStatusColumn) {
                    $query->orWhereIn('transaction_status_id', function ($sub) use ($typeIn) {
                        $sub->select('id')->from('transaction_statuses');

                        if (! is_null($typeIn) && self::hasColumn('transaction_statuses', 'transaction_type_id')) {
                            $sub->where('transaction_type_id', $typeIn);
                        } else {
                            $sub->where('name', 'like', '%إيداع%')
                                ->orWhere('name', 'like', '%Deposit%');
                        }
                    });
                }
            });

            $outQuery->where(function ($query) use ($typeOut, $hasTypeColumn, $hasStatusColumn) {
                if (! is_null($typeOut) && $hasTypeColumn) {
                    $query->where('transaction_type_id', $typeOut);
                }

                if ($hasStatusColumn) {
                    $query->orWhereIn('transaction_status_id', function ($sub) use ($typeOut) {
                        $sub->select('id')->from('transaction_statuses');

                        if (! is_null($typeOut) && self::hasColumn('transaction_statuses', 'transaction_type_id')) {
                            $sub->where('transaction_type_id', $typeOut);
                        } else {
                            $sub->where('name', 'like', '%سحب%')
                                ->orWhere('name', 'like', '%Withdrawal%');
                        }
                    });
                }
            });

            $in = $inQuery->sum('amount');
            $out = $outQuery->sum('amount');

            return [(float) $in, (float) $out];
        }

        $in = (clone $base)->where('amount', '>', 0)->sum('amount');
        $out = (clone $base)->where('amount', '<', 0)->sum(DB::raw('ABS(amount)'));

        return [(float) $in, (float) $out];
    }

    protected static function hasColumn(string $table, string $column): bool
    {
        return self::$schemaCache["column:{$table}.{$column}"] ??= Schema::hasColumn($table, $column);
    }

    protected static function hasTable(string $table): bool
    {
        return self::$schemaCache["table:{$table}"] ??= Schema::hasTable($table);
    }
}
