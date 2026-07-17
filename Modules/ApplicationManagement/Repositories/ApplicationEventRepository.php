<?php

namespace Modules\ApplicationManagement\Repositories;

use App\Repositories\Repository;
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
     */
    public function record(
        ShareApplication $application,
        ?int $actorId,
        ?string $fromStatus,
        string $toStatus,
        string $remarks,
        array $meta = [],
    ): ApplicationEvent {
        return $this->create([
            'share_application_id' => $application->id,
            'actor_id' => $actorId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'remarks' => $remarks,
            ...($meta !== [] ? ['meta' => $meta] : []),
        ]);
    }
}
