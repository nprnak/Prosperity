<?php

namespace Modules\ReportManagement\Reports;

use Illuminate\Contracts\Container\Container;
use Modules\ReportManagement\Reports\Contracts\Report;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The prescribed report formats, in the order they appear in the format
 * workbook. The keys are URL slugs and are constrained on the route, so an
 * unknown slug 404s at routing rather than here.
 */
class ReportRegistry
{
    /** @var array<string, class-string<Report>> */
    public const REPORTS = [
        'share-lagat' => ShareLagatReport::class,
        'shareholders' => ShareholderInfoReport::class,
        'focal-persons' => FocalPersonReport::class,
        'application-status' => ApplicationStatusReport::class,
        'kyc-status' => KycStatusReport::class,
    ];

    public function __construct(private Container $container) {}

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys(self::REPORTS);
    }

    public function find(string $key): Report
    {
        $class = self::REPORTS[$key] ?? throw new NotFoundHttpException("Unknown report [{$key}].");

        return $this->container->make($class);
    }

    /**
     * Every report, for the tab strip. Instantiated but not run — building a
     * report's rows is the expensive part and only the open one does that.
     *
     * @return array<int, array<string, mixed>>
     */
    public function summaries(): array
    {
        return array_map(function (string $key) {
            $report = $this->find($key);

            return [
                'key' => $key,
                'title' => $report->title(),
                'titleNp' => $report->titleNp(),
                'description' => $report->description(),
            ];
        }, $this->keys());
    }
}
