<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\UserRequest;
use App\Models\Role;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class UserCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class UserCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     * 
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\User::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/user');
        CRUD::setEntityNameStrings('user', 'users');
    }

    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::column('id')->label('ID');
        CRUD::column('name')->label('Name');
        CRUD::column('email')->label('Email');
        CRUD::column('roles')->label('Roles')
            ->type('closure')
            ->function(function($entry) {
                return $entry->roles->pluck('name')->join(', ');
            });
        CRUD::column('google_id')->label('Google ID')->visible(false);
        CRUD::column('avatar')->label('Avatar')->type('image');
        CRUD::column('email_verified_at')->label('Email Verified');
        CRUD::column('created_at')->label('Created At');
        CRUD::column('updated_at')->label('Updated At');
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(UserRequest::class);
        
        CRUD::field('name')->label('Name')->type('text');
        CRUD::field('email')->label('Email')->type('email');
        CRUD::field('password')->label('Password')->type('password');
        CRUD::field('roles')->label('Roles')
            ->type('select2_multiple')
            ->entity('roles')
            ->attribute('name')
            ->model(Role::class)
            ->pivot(true);
        CRUD::field('google_id')->label('Google ID')->type('text');
        CRUD::field('avatar')->label('Avatar URL')->type('url');
        CRUD::field('email_verified_at')->label('Email Verified At')->type('datetime');
    }

    /**
     * Define what happens when the Update operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        CRUD::setValidation(UserRequest::class);
        
        CRUD::field('name')->label('Name')->type('text');
        CRUD::field('email')->label('Email')->type('email');
        CRUD::field('password')->label('Password')->type('password')->hint('Leave empty to keep current password');
        CRUD::field('roles')->label('Roles')
            ->type('select2_multiple')
            ->entity('roles')
            ->attribute('name')
            ->model(Role::class)
            ->pivot(true);
        CRUD::field('google_id')->label('Google ID')->type('text');
        CRUD::field('avatar')->label('Avatar URL')->type('url');
        CRUD::field('email_verified_at')->label('Email Verified At')->type('datetime');
    }
}
