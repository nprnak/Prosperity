<?php

namespace Modules\CompanyManagement\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Modules\CompanyManagement\Models\Company;
use Modules\CompanyManagement\Models\ShareOffering;
use Modules\CompanyManagement\Requests\StoreCompanyRequest;
use Modules\CompanyManagement\Requests\StoreShareOfferingRequest;

class AdminCompaniesController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Companies', [
            'companies' => Company::query()
                ->with(['offerings' => fn ($q) => $q->withCount('applications')->latest()])
                ->orderBy('name')
                ->get(),
            'offeringStatuses' => ShareOffering::STATUSES,
        ]);
    }

    public function store(StoreCompanyRequest $request)
    {
        $company = Company::create($request->validated());

        return back()->with('success', 'Company created: '.$company->name);
    }

    public function update(StoreCompanyRequest $request, Company $company)
    {
        $company->update($request->validated());

        return back()->with('success', 'Company updated: '.$company->name);
    }

    public function destroy(Company $company)
    {
        abort_if($company->offerings()->withCount('applications')->get()->sum('applications_count') > 0, 422,
            'Cannot delete a company that has applications.');

        $company->delete();

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
}
