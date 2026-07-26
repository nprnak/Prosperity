<?php

namespace Modules\ReportManagement\Reports;

use App\Services\NepaliDateService;
use Illuminate\Support\Collection;
use Modules\ApplicantManagement\Models\Profile;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\CompanyManagement\Models\Company;
use Modules\CompanyManagement\Models\ShareOffering;

/**
 * शेयर लगत विवरण — the share register, one row per shareholder.
 *
 * Compiled per shareholder rather than per application, because a holder who
 * was approved twice in the same issue (2,000 then 5,000) holds 7,000 and must
 * appear once. The money columns follow the format's own definitions:
 *
 *   जम्मा चुक्ता भएको रकम  = verified deposits
 *   चुक्ता भएको रकम        = shares × 100 (par)
 *   प्रिमियम               = deposits − par
 *
 * so प्रिमियम falls out of the two figures either side of it rather than being
 * derived from the offering rate.
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

    public function __construct(private NepaliDateService $nepaliDates) {}

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

    public function filters(): array
    {
        return $this->issueFilters();
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
        $applications = ShareApplication::query()
            ->whereIn('status', $this->holdingStatuses())
            ->when($filters['share_offering_id'] ?? null, fn ($q, $offeringId) => $q->where('share_offering_id', $offeringId))
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

        $deposits = $this->verifiedDepositsByApplication($applications->pluck('id')->all());
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
                'sn' => ++$serial,
                'name_address' => $this->nameAndAddress($profile),
                'father_or_spouse' => $profile->father_name ?: ($profile->spouse_name ?: self::NONE_NP),
                'citizenship' => $this->citizenship($profile),
                'shares' => $shares,
                'total_paid' => $this->amount($totalPaid),
                'paid' => $this->amount($par),
                'premium' => $this->amount(max(0, $totalPaid - $par)),
                // The format prints छैन। rather than 0.00 when nothing is owed.
                'outstanding' => $outstanding > 0.004 ? $this->amount($outstanding) : self::NONE_NP,
                'promoter' => $this->promoter($holdings, $promoters),
                'registered_on' => $this->registeredOn($holdings),
                'nominee' => $profile->nominees->first()?->full_name ?: self::NONE_NP,
            ];
        }

        return $rows;
    }

    public function totals(array $rows): array
    {
        return [
            'shares' => number_format($this->sumColumn($rows, 'shares')),
            'total_paid' => $this->amount($this->sumColumn($rows, 'total_paid')),
            'paid' => $this->amount($this->sumColumn($rows, 'paid')),
            'premium' => $this->amount($this->sumColumn($rows, 'premium')),
        ];
    }

    /**
     * Name in Nepali where the KYC captured one, since this register is filed
     * in Nepali, with the permanent address beneath it as the format shows.
     *
     * The address goes on its own line rather than after a comma — see
     * self::LINE for why that is not merely cosmetic.
     */
    private function nameAndAddress(Profile $profile): string
    {
        $address = $profile->permanentAddress;

        $parts = array_filter([
            $address?->local_level,
            $address?->ward_no ? 'वडा नं. '.$address->ward_no : null,
            $address?->district,
        ]);

        $name = $profile->full_name_np ?: $profile->full_name_en;

        return $parts === [] ? $name : $name.self::LINE.implode(', ', $parts);
    }

    private function citizenship(Profile $profile): string
    {
        $number = $profile->citizenship_number ?: self::NONE_NP;

        return $profile->citizenship_issued_district
            ? $number.','.self::LINE.$profile->citizenship_issued_district
            : $number;
    }

    /**
     * When the holding was entered in the register: the date final approval was
     * given on the earliest of the holder's approved applications, in Bikram
     * Sambat with Devanagari numerals, as a Nepali register is dated.
     *
     * @param  Collection<int, ShareApplication>  $holdings
     */
    private function registeredOn($holdings): string
    {
        $earliest = $holdings
            ->map(fn (ShareApplication $holding) => $holding->approved_at ?? $holding->reviewed_at ?? $holding->submitted_at)
            ->filter()
            ->sort()
            ->first();

        return $this->nepaliDates->toBikramSambat($earliest) ?: self::NONE_NP;
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

    private function amount(float $value): string
    {
        return number_format($value, 2, '.', ',');
    }
}
