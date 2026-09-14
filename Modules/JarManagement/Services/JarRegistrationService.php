<?php

namespace Modules\JarManagement\Services;

use App\Models\User;
use App\Services\NumberGeneratorService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\JarManagement\Enums\JarMovementEvent;
use Modules\JarManagement\Enums\JarStatus;
use Modules\JarManagement\Models\Jar;

/**
 * The single entry point that mints a new jar identity — whether that's a
 * brand-new jar at the factory or an uncoded jar a field/factory worker
 * finds and registers on the spot. Both cases are the same operation: give
 * the physical jar a system code and start its ledger.
 */
class JarRegistrationService
{
    public function __construct(
        private readonly NumberGeneratorService $numbers,
        private readonly JarMovementRecorder $movements,
    ) {}

    public function register(User $by, ?string $notes = null): Jar
    {
        return DB::transaction(function () use ($by, $notes) {
            $jar = Jar::create([
                'jar_code' => $this->numbers->generateJarCode(),
                'status' => JarStatus::Registered,
                'condition' => 'good',
                'registered_by' => $by->id,
                'registered_at' => now(),
            ]);

            $this->movements->record($jar, JarMovementEvent::Registered, null, JarStatus::Registered, $by, notes: $notes);

            return $jar;
        });
    }

    /** @return Collection<int, Jar> */
    public function registerMany(User $by, int $count): Collection
    {
        return collect(range(1, $count))->map(fn () => $this->register($by));
    }
}
