<?php

namespace Modules\ReportManagement\Reports;

use App\Services\NepaliNumeralService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\ApplicantManagement\Models\Profile;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\CompanyManagement\Models\Company;
use Modules\CompanyManagement\Models\ShareOffering;
use Modules\SettingsManagement\Models\District;
use Modules\SettingsManagement\Models\LocalLevel;

/**
 * शेयर लगत विवरण — the share register, one row per shareholder.
 *
 * Compiled per shareholder rather than per application, because a holder who
 * was approved twice in the same issue (2,000 then 5,000) holds 7,000 and must
 * appear once. The money columns follow the format's own definitions:
 *
 *   जम्मा चुक्ता भएको रकम  = the sum of every declared bank voucher's amount
 *   चुक्ता भएको रकम        = shares × 100 (par)
 *   प्रिमियम               = deposits − par
 *
 * so प्रिमियम falls out of the two figures either side of it rather than being
 * derived from the offering rate. The whole document is filed in Nepali, so
 * every number on it — serials, shares, amounts, the citizenship number — is
 * rendered in Devanagari numerals, and addresses print the geography's Nepali
 * names rather than the English ones the KYC form stores them under.
 */
class ShareLagatReport extends BaseReport
{
    /** "छैन।" — what the prescribed format prints for an empty value. */
    private const NONE_NP = 'छैन।';

    /**
     * Separator between the parts of a composite cell.
     *
     * A newline rather than ", " on purpose. mPDF's Devanagari shaper fuses a
     * comma into the following cluster when the run before it ends in
     * Devanagari digits and the cluster carries a candrabindu — so
     * "२७०९१२३४, काठमाडौँ" loses its comma, while the same string with Latin
     * digits, or without the ँ, is fine. Breaking the line ends the adjacency
     * the bug needs, and reads better in a narrow column besides.
     */
    private const LINE = "\n";

    /** Districts and local levels keyed by their English name, Nepali value. */
    private ?array $districtsNp = null;

    private ?array $localLevelsNp = null;

    public function __construct(
        private NepaliNumeralService $numerals,
    ) {}

    public function key(): string
    {
        return 'share-lagat';
    }

    public function title(): string
    {
        return 'Share Lagat (Share Register)';
    }

    public function titleNp(): ?string
    {
        return 'शेयर लगत विवरण';
    }

    public function description(): string
    {
        return 'One row per shareholder, compiling every approved application they hold in the '
            .'selected issue. Paid, premium and outstanding amounts follow the prescribed format.';
    }

    /**
     * Company narrows the offering list; the offering itself may be left
     * blank (every offering), set once, or several picked at once — a
     * shareholder register spanning more than one issue of the same company.
     */
    public function filters(): array
    {
        return [
            [
                'key' => 'company_id',
                'label' => 'Company',
                'type' => 'select',
                'placeholder' => 'All companies',
                'options' => Company::query()->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Company $company) => ['value' => $company->id, 'label' => $company->name])
                    ->all(),
            ],
            [
                'key' => 'share_offering_ids',
                'label' => 'Offering',
                'type' => 'multiselect',
                'placeholder' => 'All offerings',
                'dependsOn' => 'company_id',
                'options' => $this->offerings()
                    ->map(fn (ShareOffering $offering) => [
                        'value' => $offering->id,
                        'label' => $this->offeringLabel($offering),
                        'parent' => $offering->company_id,
                    ])
                    ->all(),
            ],
        ];
    }

    /**
     * Offerings in filter scope — overrides BaseReport's single-offering
     * version to also recognise the multi-select share_offering_ids this
     * report actually filters by.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, ShareOffering>
     */
    protected function offerings(array $filters = []): Collection
    {
        return ShareOffering::query()
            ->with('company:id,name,code')
            ->when($filters['company_id'] ?? null, fn ($q, $companyId) => $q->where('company_id', $companyId))
            ->when(filled($filters['share_offering_ids'] ?? null), fn ($q) => $q->whereIn('id', (array) $filters['share_offering_ids']))
            ->orderBy('id')
            ->get();
    }

    public function totalsLabel(): string
    {
        return 'जम्मा';
    }

    public function usesNepaliDates(): bool
    {
        return true;
    }

    /**
     * The register is the issuing company's own document, so it is filed under
     * that company's Nepali name — as the prescribed format shows — rather than
     * under the operator's. Only possible once the filters name one company;
     * a register spanning several falls back to the configured organisation.
     */
    public function heading(array $filters = []): ?array
    {
        $companyIds = $this->offerings($filters)->pluck('company_id')->filter()->unique();

        if ($companyIds->count() !== 1) {
            return null;
        }

        // Loaded in full rather than read off the offerings' relation, which is
        // eager loaded with only id/name/code and would silently fall back to
        // the English name.
        $company = Company::find($companyIds->first());

        if (! $company) {
            return null;
        }

        return [
            'name' => $company->name_np ?: $company->name,
            'address' => $company->address_np ?: ($company->address ?: ''),
        ];
    }

    public function columns(array $filters = []): array
    {
        return [
            ['key' => 'sn', 'label' => 'S.N.', 'labelNp' => 'सि.नं', 'align' => 'right'],
            ['key' => 'name_address', 'label' => 'Shareholder name and address', 'labelNp' => 'शेयरधनिको नाम र ठेगाना'],
            ['key' => 'father_or_spouse', 'label' => "Father's or husband's name", 'labelNp' => 'बाबु वा पतिको नाम'],
            ['key' => 'citizenship', 'label' => 'Citizenship no. and district', 'labelNp' => 'नागरिकता नं., र जिल्ला'],
            ['key' => 'shares', 'label' => 'Shares', 'labelNp' => 'शेयर संख्या', 'align' => 'right'],
            ['key' => 'total_paid', 'label' => 'Total paid amount', 'labelNp' => 'जम्मा चुक्ता भएको रकम रू.', 'align' => 'right'],
            ['key' => 'paid', 'label' => 'Paid amount', 'labelNp' => 'चुक्ता भएको रकम रू.', 'align' => 'right'],
            ['key' => 'premium', 'label' => 'Premium', 'labelNp' => 'प्रिमियम रू.', 'align' => 'right'],
            ['key' => 'outstanding', 'label' => 'Amount outstanding', 'labelNp' => 'चुक्ता हुन बाँकी रकम', 'align' => 'right'],
            ['key' => 'promoter', 'label' => 'Promoter', 'labelNp' => 'संस्थापक'],
            ['key' => 'registered_on', 'label' => 'Shareholder registration date', 'labelNp' => 'शेयरधनिको नाम दर्ता मिति'],
            ['key' => 'nominee', 'label' => 'Nominee', 'labelNp' => 'इच्छ्याएको व्यक्ति'],
        ];
    }

    public function rows(array $filters = []): array
    {
        $offeringIds = array_filter((array) ($filters['share_offering_ids'] ?? []));

        $applications = ShareApplication::query()
            ->whereIn('status', $this->holdingStatuses())
            ->when($offeringIds !== [], fn ($q) => $q->whereIn('share_offering_id', $offeringIds))
            ->when($filters['company_id'] ?? null, fn ($q, $companyId) => $q->whereHas(
                'offering', fn ($offering) => $offering->where('company_id', $companyId)
            ))
            ->orderBy('applicant_id')
            ->get([
                'id', 'applicant_id', 'share_offering_id', 'shares_applied',
                'total_amount_declared', 'approved_at', 'reviewed_at', 'submitted_at',
            ]);

        if ($applications->isEmpty()) {
            return [];
        }

        $deposits = $this->voucherAmountsByApplication($applications->pluck('id')->all());
        $promoters = $this->promoterByOffering($applications->pluck('share_offering_id')->filter()->unique()->all());

        $profiles = Profile::query()
            ->with(['permanentAddress', 'nominees'])
            ->whereIn('id', $applications->pluck('applicant_id')->unique())
            ->get()
            ->keyBy('id');

        $rows = [];
        $serial = 0;

        foreach ($applications->groupBy('applicant_id') as $applicantId => $holdings) {
            $profile = $profiles->get($applicantId);

            if (! $profile) {
                continue;
            }

            $shares = (int) $holdings->sum('shares_applied');
            $declared = (float) $holdings->sum(fn ($holding) => (float) $holding->total_amount_declared);
            $totalPaid = array_sum(array_map(
                fn ($id) => (float) ($deposits[$id] ?? 0),
                $holdings->pluck('id')->all(),
            ));

            $par = $shares * self::PAR_VALUE;
            $outstanding = $declared - $totalPaid;

            $rows[] = [
                'sn' => $this->digits(++$serial),
                'name_address' => $this->nameAndAddress($profile),
                'father_or_spouse' => $profile->father_name_np ?: ($profile->spouse_name_np ?: self::NONE_NP),
                'citizenship' => $this->citizenship($profile),
                'shares' => $this->digits(number_format($shares)),
                'total_paid' => $this->amount($totalPaid),
                'paid' => $this->amount($par),
                'premium' => $this->amount(max(0, $totalPaid - $par)),
                // The format prints छैन। rather than 0.00 when nothing is owed.
                'outstanding' => $outstanding > 0.004 ? $this->amount($outstanding) : self::NONE_NP,
                'promoter' => $this->promoter($holdings, $promoters),
                // Left blank for the system admin to fill in by hand later.
                'registered_on' => '',
                'nominee' => $profile->nominees->first()?->full_name ?: self::NONE_NP,
            ];
        }

        return $rows;
    }

    public function totals(array $rows): array
    {
        return [
            'shares' => $this->digits(number_format($this->sumColumn($rows, 'shares'))),
            'total_paid' => $this->amount($this->sumColumn($rows, 'total_paid')),
            'paid' => $this->amount($this->sumColumn($rows, 'paid')),
            'premium' => $this->amount($this->sumColumn($rows, 'premium')),
        ];
    }

    /**
     * BaseReport's version only ever recognises ASCII digits when it strips a
     * formatted cell back down to a number — but every cell here is already
     * rendered in Devanagari, so summing the footer would silently come out
     * as zero without converting back to ASCII first.
     */
    protected function sumColumn(array $rows, string $key): float
    {
        return array_sum(array_map(
            fn (array $row) => (float) preg_replace('/[^0-9.\-]/', '', $this->numerals->toAscii((string) ($row[$key] ?? 0))),
            $rows,
        ));
    }

    /**
     * Name in Nepali where the KYC captured one, since this register is filed
     * in Nepali, with the permanent address beneath it as the format shows.
     * District and local level print the geography's own Nepali name rather
     * than the English one the address is stored under.
     *
     * The address goes on its own line rather than after a comma — see
     * self::LINE for why that is not merely cosmetic.
     */
    private function nameAndAddress(Profile $profile): string
    {
        $address = $profile->permanentAddress;

        $parts = array_filter([
            $address?->local_level ? $this->localLevelNp($address->local_level) : null,
            $address?->ward_no ? 'वडा नं. '.$this->digits($address->ward_no) : null,
            $address?->district ? $this->districtNp($address->district) : null,
        ]);

        $name = $profile->full_name_np ?: $profile->full_name_en;

        return $parts === [] ? $name : $name.self::LINE.implode(', ', $parts);
    }

    /**
     * The citizenship number as printed on the certificate: the Nepali value
     * captured on the KYC form when there is one, otherwise the English
     * number's digits converted — same numbers, same order, just Devanagari.
     */
    private function citizenship(Profile $profile): string
    {
        $number = $profile->citizenship_number_np
            ?: ($profile->citizenship_number ? $this->digits($profile->citizenship_number) : null)
            ?: self::NONE_NP;

        $district = $profile->citizenship_issued_district
            ? $this->districtNp($profile->citizenship_issued_district)
            : null;

        return $district ? $number.','.self::LINE.$district : $number;
    }

    /**
     * The promoter shown against a holding — currently the issuing company
     * itself, in Nepali where the company has a Nepali name.
     *
     * A holder with holdings in more than one company gets each on its own
     * line, so the column stays readable and no comma sits between two
     * Devanagari runs (see self::LINE).
     *
     * @param  Collection<int, ShareApplication>  $holdings
     * @param  array<int, string>  $promoters  offering id => company name
     */
    private function promoter($holdings, array $promoters): string
    {
        $names = $holdings
            ->map(fn (ShareApplication $holding) => $promoters[$holding->share_offering_id] ?? null)
            ->filter()
            ->unique()
            ->values();

        return $names->isEmpty() ? self::NONE_NP : $names->implode(self::LINE);
    }

    /**
     * Company name per offering, Nepali preferred.
     *
     * @param  array<int, int>  $offeringIds
     * @return array<int, string>
     */
    private function promoterByOffering(array $offeringIds): array
    {
        if ($offeringIds === []) {
            return [];
        }

        return ShareOffering::query()
            ->with('company:id,name,name_np')
            ->whereIn('id', $offeringIds)
            ->get(['id', 'company_id'])
            ->mapWithKeys(fn (ShareOffering $offering) => [
                $offering->id => $offering->company?->name_np ?: ($offering->company?->name ?: ''),
            ])
            ->filter()
            ->all();
    }

    /**
     * जम्मा चुक्ता भएको रकम — the total actually paid in, summed straight from
     * every bank voucher declared against the application rather than from
     * the payment transaction's own amount, since that figure is only ever
     * set once at submission and does not necessarily track a voucher added
     * or corrected afterwards.
     *
     * @param  array<int, int>  $applicationIds
     * @return array<int, string>
     */
    private function voucherAmountsByApplication(array $applicationIds): array
    {
        if ($applicationIds === []) {
            return [];
        }

        return DB::table('share_application_vouchers')
            ->selectRaw('share_application_id, COALESCE(SUM(amount), 0) as total')
            ->whereIn('share_application_id', $applicationIds)
            ->groupBy('share_application_id')
            ->pluck('total', 'share_application_id')
            ->map(fn ($total) => (string) $total)
            ->all();
    }

    private function amount(float $value): string
    {
        return $this->digits(number_format($value, 2, '.', ','));
    }

    private function digits(string|int|float $value): string
    {
        return $this->numerals->toDevanagari($value);
    }

    /**
     * The Nepali name for a district stored under its English one, e.g.
     * "Kathmandu" → "काठमाडौं". Loaded once per report render — the whole
     * table is 77 rows — rather than a query per shareholder.
     */
    private function districtNp(string $nameEn): string
    {
        $this->districtsNp ??= District::query()->pluck('name_np', 'name_en')->all();

        return $this->districtsNp[$nameEn] ?? $nameEn;
    }

    /** The Nepali name for a local level stored under its English one. */
    private function localLevelNp(string $nameEn): string
    {
        $this->localLevelsNp ??= LocalLevel::query()->pluck('name_np', 'name_en')->all();

        return $this->localLevelsNp[$nameEn] ?? $nameEn;
    }
}
