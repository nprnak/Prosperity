<?php

namespace Modules\ApplicationManagement\Repositories;

use App\Enums\WorkflowStage;
use App\Models\User;
use App\Repositories\Repository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\DB;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;

class ShareApplicationRepository extends Repository
{
    /**
     * Statuses that count as "in progress" on admin dashboards.
     */
    public const PENDING_STATUSES = [
        ApplicationStatus::Submitted,
        ApplicationStatus::SentToBank,
        ApplicationStatus::BankAccepted,
        ApplicationStatus::Blocked,
        ApplicationStatus::PaymentPending,
    ];

    public function __construct(ShareApplication $model)
    {
        parent::__construct($model);
    }

    /**
     * The voucher rides along so the list can show the receipt number and link
     * to it — an application with a receipt is one whose review is finished,
     * so the column doubles as a progress signal.
     */
    public function listForAdmin(): Collection
    {
        return $this->query()
            ->with(['applicant', 'paymentTransactions:id,share_application_id,receipt_number', 'paymentTransactions.voucher:id,payment_transaction_id'])
            ->latest()
            ->get();
    }

    public function loadDetail(ShareApplication $application): ShareApplication
    {
        return $application->load([
            'applicant.documents',
            'applicant.nominees',
            'applicant.sourcesOfFunds',
            'reviewer:id,name,email',
            'allotment',
            'vouchers',
            'paymentTransactions' => fn ($query) => $query
                ->with(['deposits', 'voucher', 'checker:id,name', 'verifier:id,name', 'approver:id,name'])
                ->latest(),
            // Two distinct trails: workflow_events is the review chain's
            // sign-offs, application_events the payment/lifecycle transitions
            // that finance drives outside the chain.
            'events' => fn ($query) => $query->with('actor:id,name')->latest(),
            'workflowEvents' => fn ($query) => $query->with('actor:id,name'),
        ]);
    }

    /**
     * All applications belonging to a user's applicant profile, newest first.
     */
    public function listForUser(int $userId): Collection
    {
        return $this->forUser($userId)
            ->with(['paymentTransactions:id,share_application_id,receipt_number', 'paymentTransactions.voucher:id,payment_transaction_id,voucher_number'])
            ->latest()
            ->get();
    }

    /**
     * The queue for one stage and one member of staff: applications sitting at
     * that stage, minus any the act-once rule bars them from.
     *
     * The act-once filter runs in SQL, not over the fetched collection, so
     * pages come out a consistent size. It mirrors
     * WorkflowService::assertMayAct: barred only if this user already acted at
     * a *different* stage of the current cycle.
     */
    public function pendingForStage(
        WorkflowStage $stage,
        User $user,
        array $with = [],
        int $perPage = 15,
        ?string $search = null,
    ): LengthAwarePaginator {
        $statuses = array_filter(
            ApplicationStatus::cases(),
            fn (ApplicationStatus $status) => $status->pendingStage() === $stage,
        );

        return $this->query()
            ->whereIn('status', $statuses)
            ->whereDoesntHave('workflowEvents', fn ($event) => $event
                ->where('actor_id', $user->id)
                ->whereColumn('workflow_events.cycle', 'share_applications.workflow_cycle')
                ->where('stage', '!=', $stage->value))
            ->when($search, fn ($query) => $this->applySearch($query, $search))
            ->with($with)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * The queue for whichever stage(s) a member of staff holds, in one list —
     * a user with several stage permissions (an admin, say) sees every one of
     * them at once rather than needing a separate queue per stage. Mirrors
     * ProfileRepository::pendingForUser(), which the KYC review queue already
     * merges the three stages into one page around.
     */
    public function pendingForUser(
        User $user,
        array $with = [],
        int $perPage = 15,
        ?string $search = null,
    ): LengthAwarePaginator {
        $actionable = array_filter(
            ApplicationStatus::cases(),
            fn (ApplicationStatus $status) => $status->pendingStage() !== null
                && $user->can($status->pendingStage()->permission('application')),
        );

        if ($actionable === []) {
            return new Paginator([], 0, $perPage, 1, ['path' => request()->url()]);
        }

        return $this->query()
            ->where(function ($outer) use ($actionable, $user) {
                foreach ($actionable as $status) {
                    $outer->orWhere(fn ($q) => $q
                        ->where('status', $status)
                        ->whereDoesntHave('workflowEvents', fn ($event) => $event
                            ->where('actor_id', $user->id)
                            ->whereColumn('workflow_events.cycle', 'share_applications.workflow_cycle')
                            ->where('stage', '!=', $status->pendingStage()->value)));
                }
            })
            ->when($search, fn ($query) => $this->applySearch($query, $search))
            ->with($with)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Applications this staff member has signed off, at whichever stage(s)
     * they hold — the "decided" tab of the merged review queue.
     */
    public function decidedByUser(
        User $user,
        array $with = [],
        int $perPage = 10,
        ?string $search = null,
    ): LengthAwarePaginator {
        return $this->query()
            ->whereHas('workflowEvents', fn ($event) => $event
                ->where('actor_id', $user->id)
                ->where('action', 'approve'))
            ->when($search, fn ($query) => $this->applySearch($query, $search))
            ->with($with)
            ->latest('updated_at')
            ->paginate($perPage, ['*'], 'decided')
            ->withQueryString();
    }

    /**
     * Applications this verifier has signed off at stage 1.
     */
    public function verifiedByUser(
        User $user,
        array $with = [],
        int $perPage = 15,
        ?string $search = null,
    ): LengthAwarePaginator {
        return $this->query()
            ->whereHas('workflowEvents', fn ($event) => $event
                ->where('actor_id', $user->id)
                ->where('stage', WorkflowStage::Verifier->value)
                ->where('action', 'approve'))
            ->when($search, fn ($query) => $this->applySearch($query, $search))
            ->with($with)
            ->latest()
            ->paginate($perPage, ['*'], 'verified_page')
            ->withQueryString();
    }

    /**
     * Applications this reviewer has signed off at stage 2.
     */
    public function reviewedByUser(
        User $user,
        array $with = [],
        int $perPage = 15,
        ?string $search = null,
    ): LengthAwarePaginator {
        return $this->query()
            ->whereHas('workflowEvents', fn ($event) => $event
                ->where('actor_id', $user->id)
                ->where('stage', WorkflowStage::Reviewer->value)
                ->where('action', 'approve'))
            ->when($search, fn ($query) => $this->applySearch($query, $search))
            ->with($with)
            ->latest()
            ->paginate($perPage, ['*'], 'reviewed_page')
            ->withQueryString();
    }

    /**
     * Applications this approver has signed off at stage 3.
     */
    public function approvedByUser(
        User $user,
        array $with = [],
        int $perPage = 15,
        ?string $search = null,
    ): LengthAwarePaginator {
        return $this->query()
            ->whereHas('workflowEvents', fn ($event) => $event
                ->where('actor_id', $user->id)
                ->where('stage', WorkflowStage::Approver->value)
                ->where('action', 'approve'))
            ->when($search, fn ($query) => $this->applySearch($query, $search))
            ->with($with)
            ->latest()
            ->paginate($perPage, ['*'], 'approved_page')
            ->withQueryString();
    }

    /**
     * Applications this Application Verifier filed themselves from a paper
     * form — a different list from pendingForStage(), which is everything
     * waiting on whichever stage they hold, self-submitted included.
     */
    public function enteredBy(
        int $verifierId,
        array $with = [],
        int $perPage = 15,
        ?string $search = null,
    ): LengthAwarePaginator {
        return $this->query()
            ->where('entered_by', $verifierId)
            ->when($search, fn ($query) => $this->applySearch($query, $search))
            ->with($with)
            ->latest()
            ->paginate($perPage, ['*'], 'entered_page')
            ->withQueryString();
    }

    /**
     * Matches the application number or the applicant's name, so the same box
     * finds a record whether staff have the number or just a name to go on.
     */
    private function applySearch($query, string $search)
    {
        return $query->where(function ($outer) use ($search) {
            $outer->where('application_number', 'like', "%{$search}%")
                ->orWhereHas('applicant', fn ($applicant) => $applicant
                    ->where('full_name_en', 'like', "%{$search}%"));
        });
    }

    public function listByStatus(ApplicationStatus|string|array $status, array $with = []): Collection
    {
        $statuses = is_array($status) ? $status : [$status];

        $statuses = array_map(
            fn ($status) => $status instanceof ApplicationStatus
                ? $status->value
                : $status,
            $statuses
        );

        return $this->query()
            ->whereIn('status', $statuses)
            ->with($with)
            ->latest()
            ->get();
    }

    /**
     * @param  array{company_id?: int|null, share_offering_id?: int|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    public function countByStatus(ApplicationStatus|string|array $status, array $filters = []): int
    {
        $statuses = is_array($status) ? $status : [$status];

        $statuses = array_map(
            fn ($status) => $status instanceof ApplicationStatus
                ? $status->value
                : $status,
            $statuses
        );

        return $this->scopeToFilters($this->query()->whereIn('status', $statuses), $filters)->count();
    }

    /**
     * How many distinct applicants hold at least one application in the given
     * statuses — the shareholder count a portfolio dashboard wants, as
     * opposed to the (larger) application count, since one holder can have
     * been approved more than once.
     *
     * @param  array{company_id?: int|null, share_offering_id?: int|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    public function distinctShareholderCount(array $statuses, array $filters = []): int
    {
        return $this->scopeToFilters($this->query()->whereIn('status', $statuses), $filters)
            ->distinct('applicant_id')
            ->count('applicant_id');
    }

    /**
     * Every non-draft status with at least one application, newest-count
     * first — the funnel the dashboard charts so staff can see where
     * applications are piling up.
     *
     * @param  array{company_id?: int|null, share_offering_id?: int|null, date_from?: string|null, date_to?: string|null}  $filters
     * @return array<int, array{status: string, label: string, count: int}>
     */
    public function statusBreakdown(array $filters = []): array
    {
        $counts = DB::table('share_applications')
            ->where('status', '!=', ApplicationStatus::Draft->value)
            ->when($filters['company_id'] ?? null, fn ($query, $companyId) => $query->whereIn('share_offering_id', function ($sub) use ($companyId) {
                $sub->select('id')->from('share_offerings')->where('company_id', $companyId);
            }))
            ->when($filters['share_offering_id'] ?? null, fn ($query, $offeringId) => $query->where('share_offering_id', $offeringId))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('submitted_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('submitted_at', '<=', $date))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(ApplicationStatus::cases())
            ->reject(fn (ApplicationStatus $status) => $status === ApplicationStatus::Draft)
            ->map(fn (ApplicationStatus $status) => [
                'status' => $status->value,
                'label' => $status->labelEn(),
                'count' => (int) ($counts[$status->value] ?? 0),
            ])
            ->filter(fn (array $row) => $row['count'] > 0)
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * The latest applications across every offering, for the dashboard's
     * activity feed.
     *
     * @param  array{company_id?: int|null, share_offering_id?: int|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    public function recent(int $limit = 6, array $filters = []): Collection
    {
        return $this->scopeToFilters($this->query(), $filters)
            ->with(['applicant:id,full_name_en', 'offering:id,title'])
            ->latest('submitted_at')
            ->limit($limit)
            ->get(['id', 'applicant_id', 'share_offering_id', 'application_number', 'status', 'shares_applied', 'total_amount_declared', 'submitted_at']);
    }

    /**
     * Company/offering/date-range narrowing shared by the dashboard's
     * queries. All optional — an empty $filters array matches everything.
     *
     * @param  array{company_id?: int|null, share_offering_id?: int|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    private function scopeToFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['company_id'] ?? null, fn ($q, $companyId) => $q->whereHas(
                'offering', fn ($offering) => $offering->where('company_id', $companyId)
            ))
            ->when($filters['share_offering_id'] ?? null, fn ($q, $offeringId) => $q->where('share_offering_id', $offeringId))
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('submitted_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('submitted_at', '<=', $date));
    }

    /**
     * Non-draft applications for an applicant profile. Returned ones stay
     * visible — the applicant needs to see what to correct.
     */
    public function activeCountForApplicant(int $applicantId): int
    {
        return $this->query()
            ->where('applicant_id', $applicantId)
            ->whereNotIn('status', [ApplicationStatus::Draft])
            ->count();
    }

    /**
     * Statuses in which an application is still the applicant's to edit.
     *
     * @var array<int, ApplicationStatus>
     */
    private const EDITABLE_STATUSES = [
        ApplicationStatus::Draft,
        ApplicationStatus::Returned,
    ];

    /**
     * The application the applicant is currently working on.
     *
     * A returned application is edited in place rather than replaced by a new
     * draft, so it keeps its number, its vouchers and its history — the
     * applicant was asked to correct that application, not to file another.
     */
    public function firstOrNewEditable(int $applicantId): ShareApplication
    {
        return ShareApplication::query()
            ->where('applicant_id', $applicantId)
            ->whereIn('status', self::EDITABLE_STATUSES)
            ->latest()
            ->first()
            ?? ShareApplication::make([
                'applicant_id' => $applicantId,
                'status' => ApplicationStatus::Draft,
            ]);
    }

    /** The draft or returned application the wizard should open on. */
    public function latestEditableForUser(int $userId): ?ShareApplication
    {
        return $this->forUser($userId)
            ->whereIn('status', self::EDITABLE_STATUSES)
            ->with(['applicant.nominees', 'applicant.sourcesOfFunds', 'vouchers'])
            ->latest()
            ->first();
    }

    /**
     * Applications sitting with staff. Only these block the applicant from
     * starting another for the same offering — a settled one (approved,
     * allotted, refunded) is finished business and must not lock the wizard
     * for every future offering as well.
     */
    public function inFlightForUser(int $userId): Collection
    {
        return $this->forUser($userId)
            ->whereIn('status', [
                ApplicationStatus::Submitted,
                ApplicationStatus::SentToBank,
                ApplicationStatus::BankAccepted,
                ApplicationStatus::Blocked,
                ApplicationStatus::PaymentPending,
                ApplicationStatus::PaymentVerified,
                ApplicationStatus::Verified,
                ApplicationStatus::Reviewed,
            ])
            ->latest()
            ->get();
    }

    private function forUser(int $userId)
    {
        return $this->query()->whereHas('applicant', fn ($q) => $q->where('user_id', $userId));
    }
}
