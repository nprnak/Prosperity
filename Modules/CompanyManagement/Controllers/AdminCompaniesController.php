<?php

namespace Modules\CompanyManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Modules\CompanyManagement\Models\Company;
use Modules\CompanyManagement\Models\ShareOffering;
use Modules\CompanyManagement\Repositories\CompanyRepository;
use Modules\CompanyManagement\Requests\StoreCompanyRequest;
use Modules\CompanyManagement\Requests\StoreShareOfferingRequest;

class AdminCompaniesController extends Controller
{
    public function __construct(private CompanyRepository $companies)
    {
    }

    public function index()
    {
        return Inertia::render('Admin/Companies', [
            'companies' => $this->companies->listWithOfferings(),
            'offeringStatuses' => ShareOffering::STATUSES,
        ]);
    }

    public function store(StoreCompanyRequest $request)
    {
        $company = $this->companies->create($this->payload($request));

        return back()->with('success', 'Company created: '.$company->name);
    }

    public function update(StoreCompanyRequest $request, Company $company)
    {
        $this->companies->update($company, $this->payload($request, $company));

        return back()->with('success', 'Company updated: '.$company->name);
    }

    public function logo(Company $company)
    {
        abort_unless($company->logo_path && Storage::disk('private')->exists($company->logo_path), 404);

        return Storage::disk('private')->response($company->logo_path);
    }

    public function destroy(Company $company)
    {
        abort_if($this->companies->hasApplications($company), 422,
            'Cannot delete a company that has applications.');

        $this->companies->destroy($company);

        return back()->with('success', 'Company deleted.');
    }

    public function storeOffering(StoreShareOfferingRequest $request, Company $company)
    {
        $offering = $company->offerings()->create($request->validated());

        return back()->with('success', 'Offering created: '.$offering->title);
    }

    public function updateOffering(StoreShareOfferingRequest $request, ShareOffering $offering)
    {
        $offering->update($request->validated());

        return back()->with('success', 'Offering updated: '.$offering->title);
    }

    public function destroyOffering(ShareOffering $offering)
    {
        abort_if($offering->applications()->exists(), 422, 'Cannot delete an offering that has applications.');

        $offering->delete();

        return back()->with('success', 'Offering deleted.');
    }

    protected function payload(StoreCompanyRequest $request, ?Company $company = null): array
    {
        $data = $request->safe()->except('logo');

        if ($request->hasFile('logo')) {
            if ($company?->logo_path) {
                Storage::disk('private')->delete($company->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('companies', 'private');
        }

        return $data;
    }
}
