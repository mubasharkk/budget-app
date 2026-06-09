<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ProviderRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ProviderCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;

    public function setup()
    {
        CRUD::setModel(\App\Models\Provider::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/provider');
        CRUD::setEntityNameStrings('provider', 'providers');
    }

    protected function setupListOperation()
    {
        CRUD::column('id')->label('ID');
        CRUD::column('user_id')->label('User')->type('relationship')->entity('user')->attribute('name');
        CRUD::column('name')->label('Name');
        CRUD::column('website')->label('Website');
        CRUD::column('contact_email')->label('Email');
        CRUD::column('contact_phone')->label('Phone');
        CRUD::column('created_at')->label('Created At');
    }

    protected function setupCreateOperation()
    {
        CRUD::setValidation(ProviderRequest::class);

        CRUD::field('user_id')->label('User')->type('relationship')->entity('user')->attribute('name');
        CRUD::field('name')->label('Name')->type('text');
        CRUD::field('website')->label('Website')->type('url');
        CRUD::field('contact_email')->label('Contact Email')->type('email');
        CRUD::field('contact_phone')->label('Contact Phone')->type('text');
        CRUD::field('notes')->label('Notes')->type('textarea');
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}
