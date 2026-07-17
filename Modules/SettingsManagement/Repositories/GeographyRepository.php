<?php

namespace Modules\SettingsManagement\Repositories;

use App\Repositories\Repository;
use Illuminate\Support\Facades\Cache;
use Modules\SettingsManagement\Models\District;
use Modules\SettingsManagement\Models\LocalLevel;
use Modules\SettingsManagement\Models\Province;

class GeographyRepository extends Repository
{
    public function __construct(Province $model)
    {
        parent::__construct($model);
    }

    /**
     * Nepal's federal structure for the cascading address dropdowns,
     * cached for a day.
     */
    public function flat(): array
    {
        return Cache::remember('geography.flat', now()->addDay(), fn () => [
            'provinces' => Province::query()->orderBy('id')->get(['id', 'name_en'])->toArray(),
            'districts' => District::query()->orderBy('name_en')->get(['id', 'province_id', 'name_en'])->toArray(),
            'localLevels' => LocalLevel::query()->orderBy('name_en')->get(['id', 'district_id', 'name_en', 'type'])->toArray(),
        ]);
    }
}
