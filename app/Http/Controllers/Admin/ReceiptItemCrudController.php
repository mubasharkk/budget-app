<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ReceiptItemRequest;
use App\Models\ReceiptItem;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class ReceiptItemCrudController
 *
 * @property-read CrudPanel $crud
 */
class ReceiptItemCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use ShowOperation;
    use UpdateOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(ReceiptItem::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/receipt-item');
        CRUD::setEntityNameStrings('receipt item', 'receipt items');
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     *
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::column('id')->label('ID');
        CRUD::column('receipt_id')->label('Receipt')->type('relationship');
        CRUD::column('name')->label('Item Name');
        CRUD::column('line_type')->label('Line type')->type('enum');
        CRUD::column('quantity')->label('Quantity')->type('number');
        CRUD::column('unit_price')->label('Unit Price')->type('number')->suffix(' EUR');
        CRUD::column('total')->label('Total')->type('number')->suffix(' EUR');
        CRUD::column('category_id')->label('Category')->type('relationship');
        CRUD::column('subcategory_id')->label('Subcategory')->type('relationship');
        CRUD::column('created_at')->label('Created At');
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     *
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(ReceiptItemRequest::class);

        CRUD::field('receipt_id')->label('Receipt')->type('select')->entity('receipt')->attribute('original_filename');
        CRUD::field('name')->label('Item Name')->type('text');
        CRUD::field('quantity')->label('Quantity')->type('number')->attributes(['step' => '0.001']);
        CRUD::field('unit_price')->label('Unit Price')->type('number')->attributes(['step' => '0.0001']);
        CRUD::field('total')->label('Total')->type('number')->attributes(['step' => '0.01']);
        CRUD::field('category_id')->label('Category')->type('select')->entity('category')->attribute('name');
        CRUD::field('subcategory_id')->label('Subcategory')->type('select')->entity('subcategory')->attribute('name');
    }

    /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     *
     * @return void
     */
    protected function setupUpdateOperation()
    {
        CRUD::setValidation(ReceiptItemRequest::class);

        CRUD::field('receipt_id')->label('Receipt')->type('select')->entity('receipt')->attribute('original_filename');
        CRUD::field('name')->label('Item Name')->type('text');
        CRUD::field('quantity')->label('Quantity')->type('number')->attributes(['step' => '0.001']);
        CRUD::field('unit_price')->label('Unit Price')->type('number')->attributes(['step' => '0.0001']);
        CRUD::field('total')->label('Total')->type('number')->attributes(['step' => '0.01']);
        CRUD::field('category_id')->label('Category')->type('select')->entity('category')->attribute('name');
        CRUD::field('subcategory_id')->label('Subcategory')->type('select')->entity('subcategory')->attribute('name');
    }

    /**
     * Backpack's automatic show columns, with the line type rendered as an enum.
     */
    protected function setupShowOperation()
    {
        $this->autoSetupShowOperation();

        CRUD::column('line_type')->type('enum');
    }
}
