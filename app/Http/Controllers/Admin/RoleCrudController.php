<?php

namespace App\Http\Controllers\Admin;

use App\Models\Role;
use App\Models\Permission;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class RoleCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class RoleCrudController extends CrudController
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
        CRUD::setModel(Role::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/role');
        CRUD::setEntityNameStrings('role', 'roles');
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
        CRUD::column('name')->label('Role Name');
        CRUD::column('guard_name')->label('Guard Name');
        CRUD::column('permissions_count')->label('Permissions Count')
            ->type('closure')
            ->function(function($entry) {
                return $entry->permissions()->count();
            });
        CRUD::column('users_count')->label('Users Count')
            ->type('closure')
            ->function(function($entry) {
                return $entry->users()->count();
            });
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
        CRUD::field('name')->label('Role Name')
            ->type('text')
            ->validation('required|string|max:255|unique:roles,name');
        
        CRUD::field('guard_name')->label('Guard Name')
            ->type('text')
            ->default('web')
            ->validation('required|string|max:255');
        
        CRUD::field('permissions')->label('Permissions')
            ->type('select_multiple')
            ->entity('permissions')
            ->attribute('name')
            ->model(Permission::class)
            ->pivot(true);
    }

    /**
     * Define what happens when the Update operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        CRUD::field('name')->label('Role Name')
            ->type('text')
            ->validation('required|string|max:255|unique:roles,name,' . request()->route('id'));
        
        CRUD::field('guard_name')->label('Guard Name')
            ->type('text')
            ->validation('required|string|max:255');
        
        CRUD::field('permissions')->label('Permissions')
            ->type('select_multiple')
            ->entity('permissions')
            ->attribute('name')
            ->model(Permission::class)
            ->pivot(true);
    }

    /**
     * Define what happens when the Show operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-show
     * @return void
     */
    protected function setupShowOperation()
    {
        CRUD::column('id')->label('ID');
        CRUD::column('name')->label('Role Name');
        CRUD::column('guard_name')->label('Guard Name');
        CRUD::column('permissions')->label('Permissions')
            ->type('closure')
            ->function(function($entry) {
                return $entry->permissions->pluck('name')->join(', ');
            });
        CRUD::column('users')->label('Users')
            ->type('closure')
            ->function(function($entry) {
                return $entry->users->pluck('name')->join(', ');
            });
        CRUD::column('created_at')->label('Created At');
        CRUD::column('updated_at')->label('Updated At');
    }
}