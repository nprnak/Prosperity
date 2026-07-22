<?php

namespace Modules\ApplicationManagement\Repositories;

use App\Repositories\Repository;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ApplicationEvent;
use Modules\ApplicationManagement\Models\ShareApplication;

class ApplicationEventRepository extends Repository
{
    public function __construct(ApplicationEvent $model)
    {
        parent::__construct($model);
    }

    /**
     * Append a status-transition entry to an application's audit trail.
     *
     * Statuses arrive as enums now that ShareApplication casts them, but the
     * trail stores their string values, so both forms are accepted here.
     */
    public function record(
        ShareApplication $application,
        ?int $actorId,
        ApplicationStatus|string|null $fromStatus,
        ApplicationStatus|string $toStatus,
        string $remarks,
        array $meta = [],
    ): ApplicationEvent {
        return $this->create([
            'share_application_id' => $application->id,
            'actor_id' => $actorId,
            'from_status' => $fromStatus instanceof ApplicationStatus ? $fromStatus->value : $fromStatus,
            'to_status' => $toStatus instanceof ApplicationStatus ? $toStatus->value : $toStatus,
            'remarks' => $remarks,
            ...($meta !== [] ? ['meta' => $meta] : []),
        ]);
    }
}
