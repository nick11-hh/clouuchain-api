<?php

namespace App\Observers;

use App\Models\Company;
use App\Models\CompanyGroup;

class CompanyObserver
{
    /**
     * Handle the company "created" event.
     *
     * @param  \App\Models\Company  $company
     * @return void
     */
    public function created(Company $company)
    {
        self::updateCountWhenCompanyUpdated($company);
    }

    /**
     * Handle the company "updated" event.
     *
     * @param  \App\Models\Company  $company
     * @return void
     */
    public function updated(Company $company)
    {
        self::updateCountWhenCompanyUpdated($company);
    }

    /**
     * Handle the company "deleted" event.
     *
     * @param  \App\Models\Company  $company
     * @return void
     */
    public function deleted(Company $company)
    {
        self::updateCountWhenCompanyUpdated($company);
    }

    /**
     * Handle the company "restored" event.
     *
     * @param  \App\Models\Company  $company
     * @return void
     */
    public function restored(Company $company)
    {
        //
    }

    /**
     * Handle the company "force deleted" event.
     *
     * @param  \App\Models\Company  $company
     * @return void
     */
    public function forceDeleted(Company $company)
    {
        //
    }

    protected static function updateCountWhenCompanyUpdated(Company $company)
    {
        if ($company->wasChanged('company_group_id')) {
            CompanyGroup::updateCompanyCount();
        }
    }
}
