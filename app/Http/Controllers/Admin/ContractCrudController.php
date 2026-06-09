<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Http\Requests\ContractRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ContractCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;

    public function setup()
    {
        CRUD::setModel(\App\Models\Contract::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/contract');
        CRUD::setEntityNameStrings('contract', 'contracts');
    }

    protected function setupListOperation()
    {
        CRUD::column('id')->label('ID');
        CRUD::column('user_id')->label('User')->type('relationship')->entity('user')->attribute('name');
        CRUD::column('name')->label('Name');
        CRUD::column('provider')->label('Provider')->type('relationship')->attribute('name');
        CRUD::column('amount')->label('Amount')->type('number')->decimals(2);
        CRUD::column('currency')->label('Currency');
        CRUD::column('billing_cycle')->label('Cycle');
        CRUD::column('status')->label('Status');
        CRUD::column('next_billing_date')->label('Next Billing')->type('date');
    }

    protected function setupCreateOperation()
    {
        CRUD::setValidation(ContractRequest::class);

        CRUD::field('user_id')->label('User')->type('relationship')->entity('user')->attribute('name');
        CRUD::field('provider_id')->label('Provider')->type('relationship')->entity('provider')->attribute('name');
        CRUD::field('category_id')->label('Category')->type('relationship')->entity('category')->attribute('name');
        CRUD::field('name')->label('Name')->type('text');
        CRUD::field('description')->label('Description')->type('textarea');
        CRUD::field('amount')->label('Amount')->type('number')->attributes(['step' => '0.01']);
        CRUD::field('currency')->label('Currency')->type('select_from_array')
            ->options(array_combine(['EUR', 'USD', 'INR', 'PKR', 'TRY', 'GBP'], ['EUR', 'USD', 'INR', 'PKR', 'TRY', 'GBP']));
        CRUD::field('billing_cycle')->label('Billing Cycle')->type('select_from_array')
            ->options($this->enumOptions(BillingCycle::class));
        CRUD::field('billing_day')->label('Billing Day (1-31)')->type('number');
        CRUD::field('start_date')->label('Start Date')->type('date');
        CRUD::field('end_date')->label('End Date')->type('date');
        CRUD::field('next_billing_date')->label('Next Billing Date')->type('date');
        CRUD::field('status')->label('Status')->type('select_from_array')
            ->options($this->enumOptions(ContractStatus::class));
        CRUD::field('auto_renew')->label('Auto Renew')->type('boolean')->default(true);
        CRUD::field('notes')->label('Notes')->type('textarea');
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    /**
     * @param  class-string  $enum
     * @return array<string, string>
     */
    private function enumOptions(string $enum): array
    {
        $options = [];
        foreach ($enum::options() as $option) {
            $options[$option['value']] = $option['label'];
        }

        return $options;
    }
}
