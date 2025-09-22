<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ReceiptRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class ReceiptCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ReceiptCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Receipt::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/receipt');
        CRUD::setEntityNameStrings('receipt', 'receipts');
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
        CRUD::column('user_id')->label('User')->type('relationship');
        CRUD::column('original_filename')->label('Filename');
        CRUD::column('vendor')->label('Vendor');
        CRUD::column('total_amount')->label('Amount')->type('number')->suffix(' EUR');
        CRUD::column('status')->label('Status')->type('enum');
        CRUD::column('receipt_date')->label('Receipt Date');
        CRUD::column('created_at')->label('Uploaded At');
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(ReceiptRequest::class);
        
        CRUD::field('user_id')->label('User')->type('select2_from_ajax');
        CRUD::field('original_filename')->label('Original Filename')->type('text');
        CRUD::field('original_path')->label('Original Path')->type('text');
        CRUD::field('stored_path')->label('Stored Path')->type('text');
        CRUD::field('file_type')->label('File Type')->type('text');
        CRUD::field('mime')->label('MIME Type')->type('text');
        CRUD::field('file_size')->label('File Size')->type('number');
        CRUD::field('vendor')->label('Vendor')->type('text');
        CRUD::field('currency')->label('Currency')->type('text')->default('EUR');
        CRUD::field('total_amount')->label('Total Amount')->type('number')->attributes(['step' => '0.01']);
        CRUD::field('status')->label('Status')->type('select_from_array')->options(['pending' => 'Pending', 'processed' => 'Processed', 'failed' => 'Failed']);
        CRUD::field('receipt_date')->label('Receipt Date')->type('datetime');
        CRUD::field('receipt_timezone')->label('Receipt Timezone')->type('text');
    }

    /**
     * Define what happens when the Update operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        CRUD::setValidation(ReceiptRequest::class);
        
        CRUD::field('user_id')->label('User')->type('select2_from_ajax');
        CRUD::field('original_filename')->label('Original Filename')->type('text');
        CRUD::field('original_path')->label('Original Path')->type('text');
        CRUD::field('stored_path')->label('Stored Path')->type('text');
        CRUD::field('file_type')->label('File Type')->type('text');
        CRUD::field('mime')->label('MIME Type')->type('text');
        CRUD::field('file_size')->label('File Size')->type('number');
        CRUD::field('vendor')->label('Vendor')->type('text');
        CRUD::field('currency')->label('Currency')->type('text');
        CRUD::field('total_amount')->label('Total Amount')->type('number')->attributes(['step' => '0.01']);
        CRUD::field('status')->label('Status')->type('select_from_array')->options(['pending' => 'Pending', 'processed' => 'Processed', 'failed' => 'Failed']);
        CRUD::field('receipt_date')->label('Receipt Date')->type('datetime');
        CRUD::field('receipt_timezone')->label('Receipt Timezone')->type('text');
        CRUD::field('error_message')->label('Error Message')->type('textarea');
    }
}
