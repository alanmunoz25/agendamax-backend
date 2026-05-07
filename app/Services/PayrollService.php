<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Payroll\InvalidPayrollTransitionException;
use App\Exceptions\Payroll\MissingTransitionMetadataException;
use App\Exceptions\Payroll\NegativeCommissionDetectedException;
use App\Exceptions\Payroll\NoOpenPeriodForCompensationException;
use App\Exceptions\Payroll\PeriodNotOpenException;
use App\Exceptions\Payroll\PeriodOverlapException;
use App\Exceptions\Payroll\RecordAlreadyFinalizedException;
use App\Exceptions\Payroll\RecordsAlreadyGeneratedException;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\CommissionRecord;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollAuditLog;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\Tip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AgendaMax Payroll Phase 3 — Core payroll orchestration service.
 *
 * Responsibilities:
 * - Create and manage payroll periods (no-overlap guarantee via Cache::lock).
 * - Generate one PayrollRecord per active employee with activity in a period.
 * - Drive the state machine: draft → approved → paid → voided.
 * - Auto-close periods when all records reach a terminal state (paid|voided).
 * - Attach manual credit/debit adjustments and recalculate draft records.
 */
class PayrollService
{
    /**
     * Allowed payment methods for markPaid. Closed list — coordinate with frontend before adding new options.
     *
     * @var array<int, string>
     */
    public const PAYMENT_METHODS = ['cash', 'bank_transfer', 'check', 'digital_wallet', 'other'];

    /**
     * Create a new open payroll period for a business.
     * Rejects date ranges that overlap with any existing period for the same business.
     *
     * Concurrency: protected by `Cache::lock` keyed on `business_id`. Requires an atomic
     * cache driver (Redis, database, memcached). The default `array` driver is per-process
     * and only safe in single-process tests — cross-process concurrency is validated in
     * T-3.1.8 against MariaDB with a shared cache driver.
     *
     * Defense in depth (DN-02): validates that $createdBy belongs to the same business
     * before creating the period. Super admins bypass this check.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException when user belongs to a different business
     * @throws \InvalidArgumentException when end <= start
     * @throws PeriodOverlapException when the range overlaps an existing period
     * @throws \Illuminate\Contracts\Cache\LockTimeoutException if another request is
     *                                                          creating a period for the same business and does not release within 3 seconds
     */
    public function createPeriod(Business $business, Carbon $start, Carbon $end, User $createdBy): PayrollPeriod
    {
        $this->assertSameBusiness($createdBy, $business->id);

        if ($end->lessThanOrEqualTo($start)) {
            throw new \InvalidArgumentException('End date must be after start date.');
        }

        $lockKey = "payroll:create-period:business:{$business->id}";
        $lock = Cache::lock($lockKey, 5);

        try {
            $lock->block(3);

            return DB::transaction(function () use ($business, $start, $end, $createdBy): PayrollPeriod {
                // withoutGlobalScopes: overlap check runs inside a service-level Cache::lock,
                // no auth context is guaranteed at this point — business_id filter applied manually.
                $overlap = PayrollPeriod::withoutGlobalScopes()
                    ->where('business_id', $business->id)
                    ->where('starts_on', '<=', $end->toDateString())
                    ->where('ends_on', '>=', $start->toDateString())
                    ->exists();

                if ($overlap) {
                    throw new PeriodOverlapException(
                        "Period overlaps with existing period for business {$business->id}"
                    );
                }

                $period = PayrollPeriod::create([
                    'business_id' => $business->id,
                    'starts_on' => $start->toDateString(),
                    'ends_on' => $end->toDateString(),
                    'status' => 'open',
                ]);

                PayrollAuditLog::create([
                    'business_id' => $business->id,
                    'payroll_record_id' => null,
                    'payroll_period_id' => $period->id,
                    'user_id' => $createdBy->id,
                    'action' => 'period_create',
                    'previous_status' => null,
                    'new_status' => 'open',
                    'payload' => [
                        'starts_on' => $start->toDateString(),
                        'ends_on' => $end->toDateString(),
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => substr((string) request()->userAgent(), 0, 500),
                ]);

                return $period;
            });
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Generate one PayrollRecord per active employee with payable activity in the period.
     *
     * Decision #2: employees with base_salary == 0 AND no commissions/tips/adjustments are silently skipped.
     * Decision on retroactive commissions: uses commission_records.created_at (not appointment.completed_at).
     * Locking strategy: triple lockForUpdate — on payroll_periods (anti-double-generation),
     * on commission_records (anti-parallel-job), on tips.
     *
     * Defense in depth (DN-02): validates that $generatedBy belongs to the same business as
     * the period before operating. Super admins bypass this check.
     *
     * @return Collection<int, PayrollRecord>
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException when user belongs to a different business
     * @throws PeriodNotOpenException when the period is not open
     * @throws RecordsAlreadyGeneratedException when records already exist for the period
     */
    public function generateRecords(PayrollPeriod $period, User $generatedBy): Collection
    {
        $this->assertSameBusiness($generatedBy, $period->business_id);

        return DB::transaction(function () use ($period, $generatedBy): Collection {
            // Lock the period row to prevent concurrent generation (anti-double-generation).
            $locked = PayrollPeriod::withoutGlobalScopes()
                ->whereKey($period->id)
                ->lockForUpdate()
                ->first();

            if ($locked->status !== 'open') {
                throw new PeriodNotOpenException("Period {$locked->id} is not open");
            }

            if ($locked->payrollRecords()->exists()) {
                throw new RecordsAlreadyGeneratedException("Records already generated for period {$locked->id}");
            }

            // Use startOfDay / addDay for inclusive date range on created_at (which is a timestamp).
            $start = Carbon::parse($locked->starts_on)->startOfDay();
            $endExclusive = Carbon::parse($locked->ends_on)->addDay()->startOfDay();

            // withoutGlobalScopes: BelongsToBusinessScope relies on auth()->check() which is not
            // guaranteed inside a DB::transaction (no HTTP context). Business ownership is already
            // enforced by assertSameBusiness() at the start of generateRecords(); explicit
            // business_id filter applied below.
            $employees = Employee::withoutGlobalScopes()
                ->where('business_id', $locked->business_id)
                ->where('is_active', true)
                ->get();

            $records = collect();

            foreach ($employees as $employee) {
                // Lock pending commission records to prevent concurrent payroll jobs from double-assigning.
                // withoutGlobalScopes: no auth context guaranteed — business_id filter applied manually.
                $commissions = CommissionRecord::withoutGlobalScopes()
                    ->where('business_id', $locked->business_id)
                    ->where('employee_id', $employee->id)
                    ->where('status', 'pending')
                    ->whereNull('payroll_period_id')
                    ->whereBetween('created_at', [$start, $endExclusive])
                    ->lockForUpdate()
                    ->get();

                // Lock tips to prevent concurrent payroll jobs from double-assigning.
                $tips = Tip::withoutGlobalScopes()
                    ->where('business_id', $locked->business_id)
                    ->where('employee_id', $employee->id)
                    ->whereNull('payroll_period_id')
                    ->whereBetween('received_at', [$start, $endExclusive])
                    ->lockForUpdate()
                    ->get();

                // Adjustments already belong to this period — no lock needed (they are already in this period's scope).
                $adjustments = PayrollAdjustment::withoutGlobalScopes()
                    ->where('payroll_period_id', $locked->id)
                    ->where('employee_id', $employee->id)
                    ->get();

                // DN-06: Guard against negative commission amounts before summing.
                // Double defense: DB CHECK constraint (MySQL/MariaDB) + this service-layer check (all drivers).
                $negativeCommission = $commissions->first(
                    fn (CommissionRecord $c) => bccomp((string) $c->commission_amount, '0', 2) < 0
                );

                if ($negativeCommission !== null) {
                    throw new NegativeCommissionDetectedException($negativeCommission);
                }

                $cTotal = $commissions->reduce(
                    fn (string $carry, CommissionRecord $r) => bcadd($carry, (string) $r->commission_amount, 2),
                    '0.00'
                );
                $tTotal = $tips->reduce(
                    fn (string $carry, Tip $t) => bcadd($carry, (string) $t->amount, 2),
                    '0.00'
                );
                $aTotal = $adjustments->reduce(
                    fn (string $carry, PayrollAdjustment $a) => bcadd($carry, $a->signedAmount(), 2),
                    '0.00'
                );
                // Normalize base to scale 2 in case it comes as integer or has more decimals.
                $base = bcadd((string) ($employee->base_salary ?? '0'), '0', 2);

                // Decision #2: skip employees with nothing to pay.
                if ($cTotal === '0.00' && $tTotal === '0.00' && $aTotal === '0.00' && $base === '0.00') {
                    Log::info('PayrollService: skipping employee with no payable activity', [
                        'period_id' => $locked->id,
                        'employee_id' => $employee->id,
                    ]);

                    continue;
                }

                $gross = $this->bcAdd($base, $cTotal, $tTotal, $aTotal);

                // Bypass fillable: status is a state machine field set only at creation here.
                $record = (new PayrollRecord)->forceFill([
                    'business_id' => $locked->business_id,
                    'payroll_period_id' => $locked->id,
                    'employee_id' => $employee->id,
                    'base_salary_snapshot' => $base,
                    'commissions_total' => $cTotal,
                    'tips_total' => $tTotal,
                    'adjustments_total' => $aTotal,
                    'gross_total' => $gross,
                    'status' => 'draft',
                    'snapshot_payload' => [
                        'commission_record_ids' => $commissions->pluck('id')->all(),
                        'tip_ids' => $tips->pluck('id')->all(),
                        'adjustment_ids' => $adjustments->pluck('id')->all(),
                        'generated_at' => now()->toIso8601String(),
                    ],
                ]);
                $record->save();

                // Assign commissions and tips to this period (mark as claimed).
                if ($commissions->isNotEmpty()) {
                    CommissionRecord::withoutGlobalScopes()
                        ->whereIn('id', $commissions->pluck('id'))
                        ->update(['payroll_period_id' => $locked->id]);
                }

                if ($tips->isNotEmpty()) {
                    Tip::withoutGlobalScopes()
                        ->whereIn('id', $tips->pluck('id'))
                        ->update(['payroll_period_id' => $locked->id]);
                }

                $records->push($record);

                PayrollAuditLog::create([
                    'business_id' => $locked->business_id,
                    'payroll_record_id' => $record->id,
                    'payroll_period_id' => $locked->id,
                    'user_id' => $generatedBy->id,
                    'action' => 'period_generate',
                    'previous_status' => null,
                    'new_status' => 'draft',
                    'payload' => [
                        'employee_id' => $employee->id,
                        'gross_total' => $gross,
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => substr((string) request()->userAgent(), 0, 500),
                ]);
            }

            return $records;
        });
    }

    /**
     * Approve all draft records in the period.
     * Simultaneously locks all commission records assigned to the period (pending → locked).
     * Does NOT close the period — auto-close only happens when all records reach paid|voided.
     *
     * Defense in depth (DN-02): validates that $approvedBy belongs to the same business as
     * the period before operating. Super admins bypass this check.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException when user belongs to a different business
     * @throws InvalidPayrollTransitionException when any record is not in draft status
     */
    public function approve(PayrollPeriod $period, User $approvedBy): void
    {
        $this->assertSameBusiness($approvedBy, $period->business_id);

        DB::transaction(function () use ($period, $approvedBy): void {
            // withoutGlobalScopes: locked lookup inside DB::transaction; BelongsToBusinessScope
            // requires auth context which is not guaranteed here. business_id already enforced
            // via assertSameBusiness() above before entering the transaction.
            $records = PayrollRecord::withoutGlobalScopes()
                ->where('payroll_period_id', $period->id)
                ->lockForUpdate()
                ->get();

            $invalid = $records->where('status', '!=', 'draft');

            if ($invalid->isNotEmpty()) {
                throw new InvalidPayrollTransitionException(
                    "Cannot approve period: {$invalid->count()} records not in draft state"
                );
            }

            // withoutGlobalScopes: mass update by IDs already locked and validated above.
            PayrollRecord::withoutGlobalScopes()
                ->whereIn('id', $records->pluck('id'))
                ->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => $approvedBy->id,
                ]);

            foreach ($records as $record) {
                PayrollAuditLog::create([
                    'business_id' => $period->business_id,
                    'payroll_record_id' => $record->id,
                    'payroll_period_id' => $period->id,
                    'user_id' => $approvedBy->id,
                    'action' => 'approve',
                    'previous_status' => 'draft',
                    'new_status' => 'approved',
                    'payload' => [
                        'gross_total' => (string) $record->gross_total,
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => substr((string) request()->userAgent(), 0, 500),
                ]);
            }

            // Lock all commission records for this period (pending → locked).
            // withoutGlobalScopes: mass update scoped to period_id (validated via period entity above).
            CommissionRecord::withoutGlobalScopes()
                ->where('payroll_period_id', $period->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'locked',
                    'locked_at' => now(),
                ]);
        });
    }

    /**
     * Mark a single PayrollRecord as paid.
     * Requires the record to be in approved status.
     * After marking paid, checks if all records in the period are terminal (paid|voided) and auto-closes.
     *
     * Defense in depth (DN-02): validates that $paidBy belongs to the same business as
     * the record before operating. Super admins bypass this check.
     *
     * @param  array<string, mixed>  $paymentMeta  expects: payment_method (required), payment_reference (optional)
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException when user belongs to a different business
     * @throws InvalidPayrollTransitionException when record is not in approved status
     * @throws MissingTransitionMetadataException when payment_method is missing
     */
    public function markPaid(PayrollRecord $record, User $paidBy, array $paymentMeta): void
    {
        $this->assertSameBusiness($paidBy, $record->business_id);

        if (empty($paymentMeta['payment_method'])) {
            throw new MissingTransitionMetadataException('payment_method is required to mark a record as paid.');
        }

        $method = $paymentMeta['payment_method'];

        if (! in_array($method, self::PAYMENT_METHODS, true)) {
            throw new \InvalidArgumentException(
                "Invalid payment method '{$method}'. Must be one of: ".implode(', ', self::PAYMENT_METHODS)
            );
        }

        DB::transaction(function () use ($record, $paidBy, $paymentMeta): void {
            // withoutGlobalScopes: locked lookup inside DB::transaction; business_id enforced
            // via assertSameBusiness() before entering the transaction.
            $locked = PayrollRecord::withoutGlobalScopes()
                ->whereKey($record->id)
                ->lockForUpdate()
                ->first();

            if ($locked->status !== 'approved') {
                throw new InvalidPayrollTransitionException(
                    "Cannot mark record {$locked->id} as paid: current status is {$locked->status}, expected approved"
                );
            }

            // Bypass fillable: state machine transition controlled by PayrollService.
            $locked->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
                'paid_by' => $paidBy->id,
                'payment_method' => $paymentMeta['payment_method'],
                'payment_reference' => $paymentMeta['payment_reference'] ?? null,
            ])->save();

            PayrollAuditLog::create([
                'business_id' => $locked->business_id,
                'payroll_record_id' => $locked->id,
                'payroll_period_id' => $locked->payroll_period_id,
                'user_id' => $paidBy->id,
                'action' => 'mark_paid',
                'previous_status' => 'approved',
                'new_status' => 'paid',
                'payload' => [
                    'payment_method' => $paymentMeta['payment_method'],
                    'payment_reference' => $paymentMeta['payment_reference'] ?? null,
                    'gross_total' => (string) $locked->gross_total,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
            ]);

            // Mark commission records for this employee/period as paid.
            // withoutGlobalScopes: mass update scoped to employee_id + period_id from the locked record above.
            CommissionRecord::withoutGlobalScopes()
                ->where('payroll_period_id', $locked->payroll_period_id)
                ->where('employee_id', $locked->employee_id)
                ->where('status', 'locked')
                ->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

            $this->autoClosePeriodIfComplete($locked->payroll_period_id, $paidBy);
        });
    }

    /**
     * Void a PayrollRecord (from draft, approved, or paid status).
     * Returns commission records to pending status if they were locked (approved).
     * Requires a non-empty void reason.
     * After voiding, checks if all records in the period are terminal (paid|voided) and auto-closes.
     *
     * Decision #6 — Compensation on void from paid:
     * - draft → voided: no compensation (no disbursement was made).
     * - approved → voided: no compensation; locked commissions return to pending.
     * - paid → voided: creates a PayrollAdjustment(type='debit', amount=gross_total) in the next
     *   open period for the same (business_id, employee_id). If a draft record exists in that period,
     *   adjustments_total and gross_total are recalculated using BCMath. Commission records belonging
     *   to the voided record remain status='paid' — the adjustment compensates without destroying history.
     *   If no open period exists after the voided record's period ends_on, the entire transaction is
     *   rolled back and NoOpenPeriodForCompensationException is thrown. The admin must open a period first.
     *
     * @throws MissingTransitionMetadataException when reason is empty
     * @throws InvalidPayrollTransitionException when record is already voided
     * @throws NoOpenPeriodForCompensationException when voiding a paid record and no open period exists after it
     */
    public function void(PayrollRecord $record, User $voidedBy, string $reason): void
    {
        $this->assertSameBusiness($voidedBy, $record->business_id);

        if (trim($reason) === '') {
            throw new MissingTransitionMetadataException('A reason is required to void a payroll record.');
        }

        DB::transaction(function () use ($record, $voidedBy, $reason): void {
            // withoutGlobalScopes: locked lookup inside DB::transaction; business_id enforced
            // via assertSameBusiness() before entering the transaction.
            $locked = PayrollRecord::withoutGlobalScopes()
                ->whereKey($record->id)
                ->lockForUpdate()
                ->first();

            if ($locked->status === 'voided') {
                throw new InvalidPayrollTransitionException(
                    "Record {$locked->id} is already voided."
                );
            }

            $previousStatus = $locked->status;

            // Bypass fillable: state machine transition controlled by PayrollService.
            $locked->forceFill([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => $voidedBy->id,
                'void_reason' => $reason,
            ])->save();

            PayrollAuditLog::create([
                'business_id' => $locked->business_id,
                'payroll_record_id' => $locked->id,
                'payroll_period_id' => $locked->payroll_period_id,
                'user_id' => $voidedBy->id,
                'action' => 'void',
                'previous_status' => $previousStatus,
                'new_status' => 'voided',
                'payload' => [
                    'void_reason' => $reason,
                    'gross_total' => (string) $locked->gross_total,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
            ]);

            // Return commission records to pending if they were locked (approved state).
            // If they were paid, they remain paid (only the payroll record is voided).
            // withoutGlobalScopes: mass update scoped to employee_id + period_id from the locked record.
            if ($previousStatus === 'approved') {
                CommissionRecord::withoutGlobalScopes()
                    ->where('payroll_period_id', $locked->payroll_period_id)
                    ->where('employee_id', $locked->employee_id)
                    ->where('status', 'locked')
                    ->update([
                        'status' => 'pending',
                        'locked_at' => null,
                        'payroll_period_id' => null,
                    ]);
            }

            // Decision #6: void from paid requires a compensation debit in the next open period.
            // This reflects the accounting liability — money already left the bank account.
            if ($previousStatus === 'paid') {
                // Resolve the voided record's period end date to anchor the next-period search.
                // withoutGlobalScopes: no auth context guaranteed inside a transaction.
                $voidedPeriod = PayrollPeriod::withoutGlobalScopes()
                    ->whereKey($locked->payroll_period_id)
                    ->first();

                $periodEndsOn = $voidedPeriod->ends_on->toDateString();

                // Find the nearest open period that starts after the voided record's period ends.
                $nextPeriod = PayrollPeriod::withoutGlobalScopes()
                    ->where('business_id', $locked->business_id)
                    ->where('status', 'open')
                    ->where('starts_on', '>', $periodEndsOn)
                    ->orderBy('starts_on')
                    ->first();

                if ($nextPeriod === null) {
                    // Throws inside the transaction — the entire void is rolled back.
                    throw new NoOpenPeriodForCompensationException($locked, $periodEndsOn);
                }

                // Create the compensation debit adjustment in the next open period.
                // Bypass fillable: state machine and audit fields set directly via forceFill.
                $compensation = new PayrollAdjustment;
                $compensation->forceFill([
                    'business_id' => $locked->business_id,
                    'payroll_period_id' => $nextPeriod->id,
                    'employee_id' => $locked->employee_id,
                    'related_commission_record_id' => null,
                    'related_appointment_id' => null,
                    'type' => 'debit',
                    'amount' => $locked->gross_total,
                    'reason' => "Void compensation: payroll_record #{$locked->id}",
                    'description' => $reason,
                    'created_by' => $voidedBy->id,
                ]);
                $compensation->save();

                PayrollAuditLog::create([
                    'business_id' => $locked->business_id,
                    'payroll_record_id' => null,
                    'payroll_period_id' => $nextPeriod->id,
                    'user_id' => $voidedBy->id,
                    'action' => 'add_adjustment',
                    'previous_status' => null,
                    'new_status' => null,
                    'payload' => [
                        'type' => 'debit',
                        'amount' => (string) $locked->gross_total,
                        'reason' => "Void compensation: payroll_record #{$locked->id}",
                        'source_record_id' => $locked->id,
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => substr((string) request()->userAgent(), 0, 500),
                ]);

                // If a draft record already exists in the next period for this employee, recalculate
                // its totals immediately (same pattern as T-3.1.3 / addAdjustment).
                // withoutGlobalScopes: locked lookup inside transaction; business already validated above.
                $nextDraftRecord = PayrollRecord::withoutGlobalScopes()
                    ->where('payroll_period_id', $nextPeriod->id)
                    ->where('employee_id', $locked->employee_id)
                    ->where('status', 'draft')
                    ->lockForUpdate()
                    ->first();

                if ($nextDraftRecord !== null) {
                    $nextDraftRecord->update([
                        'adjustments_total' => bcadd((string) $nextDraftRecord->adjustments_total, $compensation->signedAmount(), 2),
                        'gross_total' => bcadd((string) $nextDraftRecord->gross_total, $compensation->signedAmount(), 2),
                    ]);
                }
            }

            $this->autoClosePeriodIfComplete($locked->payroll_period_id, $voidedBy);
        });
    }

    /**
     * Add a manual credit or debit adjustment to an open payroll period for an employee.
     *
     * Behavior by PayrollRecord status:
     * - null (no record yet): creates the adjustment; no recalculation. The adjustment will be
     *   included when generateRecords() runs for this period.
     * - draft: creates the adjustment and immediately recalculates adjustments_total and gross_total
     *   using BCMath at scale 2.
     * - approved | paid | voided: rejects with RecordAlreadyFinalizedException. The transaction
     *   is rolled back and no adjustment is persisted. Use void + compensation flow instead.
     *
     * The period status is re-validated inside the transaction with lockForUpdate to defend
     * against concurrent period closure between the pre-check and the actual write.
     *
     * Defense in depth (DN-02): validates that $createdBy belongs to the same business as
     * the period before operating. Super admins bypass this check.
     * Note: $createdBy is always required for admin-initiated adjustments. The void() compensation
     * flow creates PayrollAdjustment::create() directly (bypassing this method) because the void's
     * business check is already performed in void() itself.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException when user belongs to a different business
     * @throws PeriodNotOpenException when the period is not open (pre-check or concurrent closure)
     * @throws RecordAlreadyFinalizedException when the employee's record is in a terminal state
     */
    public function addAdjustment(
        PayrollPeriod $period,
        Employee $employee,
        string $type,
        float $amount,
        string $reason,
        User $createdBy,
        ?CommissionRecord $relatedCommission = null,
        ?Appointment $relatedAppointment = null
    ): PayrollAdjustment {
        $this->assertSameBusiness($createdBy, $period->business_id);

        if ($employee->business_id !== $period->business_id) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                "Cross-tenant adjustment rejected: employee (business={$employee->business_id}) "
                ."cannot be assigned to period (business={$period->business_id})."
            );
        }

        if (! in_array($type, ['credit', 'debit'], true)) {
            throw new \InvalidArgumentException(
                "Invalid adjustment type '{$type}'. Must be 'credit' or 'debit'."
            );
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException(
                'Adjustment amount must be positive; use type="debit" for deductions.'
            );
        }

        if ($period->status !== 'open') {
            throw new PeriodNotOpenException("Cannot add adjustment to non-open period {$period->id}");
        }

        return DB::transaction(function () use ($period, $employee, $type, $amount, $reason, $createdBy, $relatedCommission, $relatedAppointment): PayrollAdjustment {
            // 1. Reload period with lock and revalidate status to defend against concurrent closure.
            // withoutGlobalScopes: locked lookup inside DB::transaction; business_id enforced
            // via assertSameBusiness() before entering the transaction.
            $lockedPeriod = PayrollPeriod::withoutGlobalScopes()
                ->whereKey($period->id)
                ->lockForUpdate()
                ->first();

            if ($lockedPeriod === null || $lockedPeriod->status !== 'open') {
                throw new PeriodNotOpenException("Cannot add adjustment to non-open period {$period->id}");
            }

            // 2. Find the employee's record in this period regardless of status.
            // withoutGlobalScopes: locked lookup inside transaction; business validated above.
            $record = PayrollRecord::withoutGlobalScopes()
                ->where('payroll_period_id', $lockedPeriod->id)
                ->where('employee_id', $employee->id)
                ->lockForUpdate()
                ->first();

            // 3. Reject if the record is in a terminal state — adjustment cannot be applied.
            if ($record !== null && in_array($record->status, ['approved', 'paid', 'voided'], true)) {
                throw new RecordAlreadyFinalizedException($record->id, $record->status);
            }

            // 4. Create the adjustment.
            $adj = PayrollAdjustment::create([
                'business_id' => $lockedPeriod->business_id,
                'payroll_period_id' => $lockedPeriod->id,
                'employee_id' => $employee->id,
                'related_commission_record_id' => $relatedCommission?->id,
                'related_appointment_id' => $relatedAppointment?->id,
                'type' => $type,
                'amount' => round($amount, 2),
                'reason' => $reason,
                'created_by' => $createdBy->id,
            ]);

            PayrollAuditLog::create([
                'business_id' => $lockedPeriod->business_id,
                'payroll_record_id' => $record?->id,
                'payroll_period_id' => $lockedPeriod->id,
                'user_id' => $createdBy->id,
                'action' => 'add_adjustment',
                'previous_status' => $record?->status,
                'new_status' => $record?->status,
                'payload' => [
                    'adjustment_id' => $adj->id,
                    'type' => $type,
                    'amount' => round($amount, 2),
                    'reason' => $reason,
                    'employee_id' => $employee->id,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
            ]);

            // 5. Recalculate totals only when a draft record exists.
            if ($record !== null && $record->status === 'draft') {
                $record->update([
                    'adjustments_total' => bcadd((string) $record->adjustments_total, $adj->signedAmount(), 2),
                    'gross_total' => bcadd((string) $record->gross_total, $adj->signedAmount(), 2),
                ]);
            }

            return $adj;
        });
    }

    /**
     * Ensure an open PayrollPeriod exists for the given business covering today.
     * If no open period includes today, creates a calendar-month period for the current month.
     * Protected by a Cache::lock to avoid duplicate period creation under concurrent requests.
     *
     * This method is intentionally NOT protected by assertSameBusiness() — it is called from
     * queue jobs and PosService where no authenticated User context is available.
     *
     * @throws PeriodOverlapException when an auto-created period would overlap an existing period
     *                                (should not happen in practice because we check first)
     */
    public function ensureOpenPeriodForToday(Business $business): PayrollPeriod
    {
        $lockKey = "payroll:ensure-period:business:{$business->id}";
        $lock = Cache::lock($lockKey, 5);

        try {
            $lock->block(3);

            return DB::transaction(function () use ($business): PayrollPeriod {
                $today = now()->toDateString();

                // Find an open period that already covers today.
                $existing = PayrollPeriod::withoutGlobalScopes()
                    ->where('business_id', $business->id)
                    ->where('status', 'open')
                    ->where('starts_on', '<=', $today)
                    ->where('ends_on', '>=', $today)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }

                // Auto-create a calendar-month period for the current month.
                $start = now()->startOfMonth();
                $end = now()->endOfMonth();

                // Safety check: reject if the auto period overlaps any existing period.
                $overlap = PayrollPeriod::withoutGlobalScopes()
                    ->where('business_id', $business->id)
                    ->where('starts_on', '<=', $end->toDateString())
                    ->where('ends_on', '>=', $start->toDateString())
                    ->exists();

                if ($overlap) {
                    throw new PeriodOverlapException(
                        "Cannot auto-create period: overlaps with an existing period for business {$business->id}"
                    );
                }

                $period = PayrollPeriod::create([
                    'business_id' => $business->id,
                    'starts_on' => $start->toDateString(),
                    'ends_on' => $end->toDateString(),
                    'status' => 'open',
                ]);

                Log::info('PayrollService: auto-created open period for business', [
                    'business_id' => $business->id,
                    'period_id' => $period->id,
                    'starts_on' => $period->starts_on->toDateString(),
                    'ends_on' => $period->ends_on->toDateString(),
                ]);

                return $period;
            });
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Assign commission records to the given period and create/update the aggregated PayrollRecord
     * for the employee in that period. Safe to call multiple times (idempotent).
     *
     * This method operates without a User context — it is designed to be called from queue jobs
     * and PosService where no authenticated admin is present. All business-level authorization
     * is guaranteed by the caller (commission records are already scoped to the correct business).
     *
     * Concurrency: uses DB::transaction + lockForUpdate on the PayrollRecord row to prevent
     * race conditions when two tickets for the same employee are processed simultaneously.
     *
     * @param  \Illuminate\Support\Collection<int, CommissionRecord>  $commissionRecords
     */
    public function upsertEmployeeRecord(
        int $employeeId,
        PayrollPeriod $period,
        \Illuminate\Support\Collection $commissionRecords
    ): PayrollRecord {
        return DB::transaction(function () use ($employeeId, $period, $commissionRecords): PayrollRecord {
            // Re-query from DB to get the canonical status/payroll_period_id values.
            // In-memory models from firstOrCreate may have null for non-fillable fields
            // (status, payroll_period_id) even when the DB has the correct values.
            $candidateIds = $commissionRecords->pluck('id')->filter()->all();

            if (! empty($candidateIds)) {
                // Assign all unassigned commission records (pending + no period) to this period.
                CommissionRecord::withoutGlobalScopes()
                    ->whereIn('id', $candidateIds)
                    ->whereNull('payroll_period_id')
                    ->where('status', 'pending')
                    ->update(['payroll_period_id' => $period->id]);
            }

            // Recalculate total commissions for this employee in this period
            // (includes records assigned in previous runs + this batch).
            $periodCommissionsTotal = CommissionRecord::withoutGlobalScopes()
                ->where('business_id', $period->business_id)
                ->where('employee_id', $employeeId)
                ->where('payroll_period_id', $period->id)
                ->where('status', 'pending')
                ->sum('commission_amount');

            $commissionsTotalStr = bcadd((string) $periodCommissionsTotal, '0', 2);

            // Load employee for base_salary.
            $employee = Employee::withoutGlobalScopes()->find($employeeId);
            $baseSalary = bcadd((string) ($employee?->base_salary ?? '0'), '0', 2);

            // Lock the PayrollRecord row to prevent concurrent upserts for the same employee+period.
            $record = PayrollRecord::withoutGlobalScopes()
                ->where('payroll_period_id', $period->id)
                ->where('employee_id', $employeeId)
                ->lockForUpdate()
                ->first();

            if ($record === null) {
                // First commission for this employee in this period — create the record.
                $tipsTotal = Tip::withoutGlobalScopes()
                    ->where('business_id', $period->business_id)
                    ->where('employee_id', $employeeId)
                    ->where('payroll_period_id', $period->id)
                    ->sum('amount');

                $adjustmentsTotal = PayrollAdjustment::withoutGlobalScopes()
                    ->where('payroll_period_id', $period->id)
                    ->where('employee_id', $employeeId)
                    ->get()
                    ->reduce(
                        fn (string $carry, PayrollAdjustment $a) => bcadd($carry, $a->signedAmount(), 2),
                        '0.00'
                    );

                $gross = $this->bcAdd($baseSalary, $commissionsTotalStr, (string) $tipsTotal, $adjustmentsTotal);

                $record = new PayrollRecord;
                $record->forceFill([
                    'business_id' => $period->business_id,
                    'payroll_period_id' => $period->id,
                    'employee_id' => $employeeId,
                    'base_salary_snapshot' => $baseSalary,
                    'commissions_total' => $commissionsTotalStr,
                    'tips_total' => bcadd((string) $tipsTotal, '0', 2),
                    'adjustments_total' => $adjustmentsTotal,
                    'gross_total' => $gross,
                    'status' => 'draft',
                    'snapshot_payload' => [
                        'auto_generated' => true,
                        'generated_at' => now()->toIso8601String(),
                    ],
                ]);
                $record->save();

                Log::info('PayrollService: auto-created PayrollRecord for employee', [
                    'period_id' => $period->id,
                    'employee_id' => $employeeId,
                    'commissions_total' => $commissionsTotalStr,
                    'gross_total' => $gross,
                ]);
            } elseif ($record->status === 'draft') {
                // Record already exists in draft — recalculate commissions_total and gross_total.
                // Tips and adjustments are not recalculated here (they are not affected by the new commission).
                $newGross = $this->bcAdd(
                    (string) $record->base_salary_snapshot,
                    $commissionsTotalStr,
                    (string) $record->tips_total,
                    (string) $record->adjustments_total
                );

                $record->update([
                    'commissions_total' => $commissionsTotalStr,
                    'gross_total' => $newGross,
                ]);

                Log::info('PayrollService: updated existing draft PayrollRecord for employee', [
                    'period_id' => $period->id,
                    'employee_id' => $employeeId,
                    'commissions_total' => $commissionsTotalStr,
                    'gross_total' => $newGross,
                ]);
            } else {
                Log::warning('PayrollService: PayrollRecord already in non-draft state, skipping upsert', [
                    'period_id' => $period->id,
                    'employee_id' => $employeeId,
                    'record_status' => $record->status,
                ]);
            }

            return $record->refresh();
        });
    }

    /**
     * Defense in depth (DN-02): ensure the user belongs to the same business as the resource.
     * Super admins bypass this check and may operate across any business.
     *
     * Called at the start of every public method that receives a User and mutates a payroll entity.
     * This is a second layer of defense before Fase 4 Policies are in place. Controllers that
     * pass a mismatched User are caught here rather than silently corrupting cross-tenant data.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException when the user's business_id does not
     *                                                        match the resource's business_id
     */
    private function assertSameBusiness(User $user, int $businessId): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if ($user->primary_business_id !== $businessId) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                "Cross-business operation rejected: user (business_id={$user->primary_business_id}) cannot operate on resource (business_id={$businessId})."
            );
        }
    }

    /**
     * Add two or more monetary values using BCMath at scale 2.
     * Accepts strings, ints, or floats; returns a string with exactly 2 decimal places.
     * Never use (float) casts on monetary columns — use this helper instead.
     */
    private function bcAdd(string|int|float ...$values): string
    {
        $sum = '0';
        foreach ($values as $v) {
            $sum = bcadd($sum, (string) $v, 2);
        }

        return $sum;
    }

    /**
     * Auto-close the period if all PayrollRecords have reached a terminal state (paid or voided).
     * Decision #1: approve() does NOT close the period — only full terminal state triggers closure.
     */
    private function autoClosePeriodIfComplete(int $periodId, User $closedBy): void
    {
        // withoutGlobalScopes: private helper called from inside existing DB::transactions;
        // auth context is not guaranteed. periodId is derived from a locked record, ensuring
        // business scope integrity has already been validated by the calling public method.
        $period = PayrollPeriod::withoutGlobalScopes()
            ->whereKey($periodId)
            ->lockForUpdate()
            ->first();

        if ($period === null || $period->status !== 'open') {
            return;
        }

        // withoutGlobalScopes: same transaction context — period ID already validated above.
        $hasRecords = PayrollRecord::withoutGlobalScopes()
            ->where('payroll_period_id', $periodId)
            ->exists();

        if (! $hasRecords) {
            return;
        }

        // withoutGlobalScopes: same transaction context — checking terminal state for period records.
        $allDone = ! PayrollRecord::withoutGlobalScopes()
            ->where('payroll_period_id', $periodId)
            ->whereNotIn('status', ['paid', 'voided'])
            ->exists();

        if ($allDone) {
            // Bypass fillable: state machine transition controlled by PayrollService.
            $period->forceFill([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => $closedBy->id,
            ])->save();

            PayrollAuditLog::create([
                'business_id' => $period->business_id,
                'payroll_record_id' => null,
                'payroll_period_id' => $periodId,
                'user_id' => $closedBy->id,
                'action' => 'period_close',
                'previous_status' => 'open',
                'new_status' => 'closed',
                'payload' => [
                    'auto_closed' => true,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
            ]);

            Log::info('PayrollService: period auto-closed — all records in terminal state', [
                'period_id' => $periodId,
                'closed_by' => $closedBy->id,
            ]);
        }
    }
}
