<?php

namespace Modules\ApplicationManagement\Repositories;

use App\Enums\WorkflowStage;
use App\Models\User;
use App\Repositories\Repository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
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

    public function countByStatus(ApplicationStatus|string|array $status): int
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
            ->count();
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
