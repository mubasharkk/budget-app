<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class CategoryCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class CategoryCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Category::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/category');
        CRUD::setEntityNameStrings('category', 'categories');
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
        CRUD::column('slug')->label('Slug');
        CRUD::column('description')->label('Description');
        CRUD::column('color')->label('Color')->type('color');
        CRUD::column('icon')->label('Icon');
        CRUD::column('parent')->label('Parent Category')
            ->type('closure')
            ->function(function($entry) {
                return $entry->parent?->name ?? '-';
            });
        CRUD::column('is_active')->label('Active')->type('boolean');
        CRUD::column('sort_order')->label('Sort Order');
        CRUD::column('created_at')->label('Created At');
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(CategoryRequest::class);

        CRUD::field('name')->label('Name')->type('text');
        CRUD::field('slug')->label('Slug')->type('text')->hint('Auto-generated from name if empty');
        CRUD::field('description')->label('Description')->type('textarea');
        CRUD::field('color')->label('Color')->type('color');
        CRUD::field('icon')->label('Icon')->type('text')->hint('Icon name (e.g., fas fa-home)');
        CRUD::field('parent_id')->label('Parent Category')
            ->type('select')
            ->entity('parent')
            ->attribute('name');
        CRUD::field('is_active')->label('Active')->type('boolean')->default(true);
        CRUD::field('sort_order')->label('Sort Order')->type('number')->default(0);
    }

    /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        CRUD::setValidation(CategoryRequest::class);

        CRUD::field('name')->label('Name')->type('text');
        CRUD::field('slug')->label('Slug')->type('text')->hint('Auto-generated from name if empty');
        CRUD::field('description')->label('Description')->type('textarea');
        CRUD::field('color')->label('Color')->type('color');
        CRUD::field('icon')->label('Icon')->type('text')->hint('Icon name (e.g., fas fa-home)');
        CRUD::field('parent_id')->label('Parent Category')
            ->type('select')
            ->entity('parent')
            ->attribute('name');
        CRUD::field('is_active')->label('Active')->type('boolean');
        CRUD::field('sort_order')->label('Sort Order')->type('number');
    }

    protected function setupShowOperation()
    {
        CRUD::column('id')->label('ID');
        CRUD::column('name')->label('Name');
        CRUD::column('slug')->label('Slug');
        CRUD::column('description')->label('Description');
        CRUD::column('color')->label('Color')->type('color');
        CRUD::column('icon')->label('Icon');
        CRUD::column('parent')->label('Parent Category')
            ->type('closure')
            ->function(function($entry) {
                return $entry->parent?->name ?? '-';
            });
        CRUD::column('is_active')->label('Active')->type('boolean');
        CRUD::column('sort_order')->label('Sort Order');
        CRUD::column('created_at')->label('Created At');
    }
}
