<?php

use App\Http\Controllers\DashboardController\DashboardController;
use App\Http\Controllers\Emi\ApplyEmi;
use App\Http\Controllers\Emi\ApproveEmi;
use App\Http\Controllers\Emi\EmiController;
use App\Http\Controllers\Inventory\ExtensionRequisitionController;
use App\Http\Controllers\Master\MasterWarrantyController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\ReportController\CashReceiptController;
use App\Http\Controllers\ReportController\OnHandItemsController;
use App\Http\Controllers\ReportController\OnlineReceiptController;
use App\Http\Controllers\ReportController\PostedSalesInvoiceController;
use App\Http\Controllers\ReportController\ReportController;
use App\Http\Controllers\ReportController\SalesAndStockController;
use App\Http\Controllers\ReportController\SalesOrderListController;
use App\Http\Controllers\ReportController\StaffEmiController;
use App\Http\Controllers\SalesAndOrder\Warranty;
use App\Http\Controllers\SalesAndOrder\WarrantyController;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SystemSetting\AuditsController;
use App\Http\Controllers\SystemSetting\PermissionController;
use App\Http\Controllers\SystemSetting\RoleController;
use App\Http\Controllers\SystemSetting\UserController;
use App\Http\Controllers\Master\CategoryController;
use App\Http\Controllers\Master\BrandController;
use App\Http\Controllers\Master\SaleTypeController;
use App\Http\Controllers\Master\DzongkhagController;
// use App\Http\Controllers\Master\ExtensionController;
use App\Http\Controllers\Master\GewogController;
use App\Http\Controllers\Master\RegionController;
use App\Http\Controllers\Master\VillageController;
use App\Http\Controllers\Master\BankController;
use App\Http\Controllers\Master\UnitController;
// use App\Http\Controllers\Master\StoreController;
use App\Http\Controllers\Master\AssigningController;
use App\Http\Controllers\Master\ColorController;
// use App\Http\Controllers\DealerManagement\PromotionTypeController;
use App\Http\Controllers\DealerManagement\DiscountTypeController;
use App\Http\Controllers\DealerManagement\CustomerTypeController;
use App\Http\Controllers\DealerManagement\CustomerController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\ProductMovementController;
use App\Http\Controllers\Inventory\ProductRequisitionController;
use App\Http\Controllers\MainStore\MainStoreTransferController;
use App\Http\Controllers\MainStore\MainStoreSaleController;
use App\Http\Controllers\MainStore\MainProductTransferController;
use App\Http\Controllers\MainStore\MainProductReceiveController;
use App\Http\Controllers\MainStore\PhoneEmiController;
use App\Http\Controllers\RegionalStore\RegionalStoreTransferController;
use App\Http\Controllers\RegionalStore\RegionStoreSaleController;
use App\Http\Controllers\RegionalStore\RegionProductTransferController;
use App\Http\Controllers\RegionalStore\RegionProductReceiveController;
use App\Http\Controllers\ExtensionStore\ExtensionStoreTransferController;
use App\Http\Controllers\ExtensionStore\ExtensionStoreSaleController;
use App\Http\Controllers\ExtensionStore\ExtensionProductTransferController;
use App\Http\Controllers\ExtensionStore\ExtensionProductReceiveController;
use App\Http\Controllers\Master\EmployeeController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::post('login', [AuthController::Class, 'Login']);

Route::group(['middleware' => ['jwt.auth']], function () {

    //route under system setting
    Route::get('activity-logs', [AuditsController::class, 'index']);
    Route::get('permission', [PermissionController::class, 'index']);
    Route::post('permission', [PermissionController::class, 'refresh']);
    Route::resource('roles', RoleController::class);
    Route::get('edit-roles/{id}', [RoleController::Class, 'editRole']);
    Route::get('roles-base/{id}', [RoleController::Class, 'getRoleBase']);
    Route::resource('users', UserController::class);
    Route::get('edit-users/{id}', [UserController::Class, 'editUser']);
    Route::put('reset-password/{id}', [UserController::Class, 'password']);

    

    //route under master 
    Route::resource('dzongkhags', DzongkhagController::class);
    Route::get('edit-dzongkhags/{id}', [DzongkhagController::Class, 'editDzongkhag']);
    Route::resource('gewogs', GewogController::class);
    Route::get('edit-gewogs/{id}', [GewogController::Class, 'editGewog']);
    Route::resource('villages', VillageController::class);
    Route::get('edit-villages/{id}', [VillageController::Class, 'editVillage']);
    Route::resource('sale-types', SaleTypeController::class);
    Route::get('edit-sale-types/{id}', [SaleTypeController::Class, 'editSaleType']);
    Route::resource('regions', RegionController::class);
    Route::get('edit-regions/{id}', [RegionController::Class, 'editRegion']);
    // Route::resource('extensions', ExtensionController::class);  
    // Route::get('edit-extensions/{id}', [ExtensionController::Class, 'editExtension']);    
    Route::resource('categories', CategoryController::class);
    Route::get('edit-categories/{id}', [CategoryController::Class, 'editCategory']);
    // Route::resource('brands', BrandController::class);  
    // Route::get('edit-brands/{id}', [BrandController::Class, 'editBrand']);  
    Route::resource('banks', BankController::class);
    Route::get('edit-banks/{id}', [BankController::Class, 'editBank']);
    Route::get('get-banks', [BankController::Class, 'getBanks']);
    // Route::resource('units', UnitController::class);  
    // Route::get('edit-units/{id}', [UnitController::Class, 'editUnit']);  
    // Route::resource('stores', StoreController::class);  
    // Route::get('edit-stores/{id}', [StoreController::Class, 'editStore']);  
    Route::resource('assignings', AssigningController::class);
    Route::get('edit-assignings/{id}', [AssigningController::Class, 'editAssigning']);
    Route::post('edit-assignings/{id}', [AssigningController::Class, 'changeAssignRegion']);
    Route::resource('colors', ColorController::class);
    Route::get('edit-colors/{id}', [ColorController::Class, 'editColor']);

    // Route::resource('employees', EmployeeController::class);
    // Route::get('edit-employees/{id}', [EmployeeController::class, 'editEmployee']);

    Route::resource('master-warranties', MasterWarrantyController::class);
    Route::get('edit-master-warranties/{id}', [MasterWarrantyController::class, 'editWarranty']);

    //route under dealer management 
    // Route::resource('promotion-types', PromotionTypeController::class);  
    // Route::get('edit-promotions/{id}', [PromotionTypeController::Class, 'editPromotion']);  
    Route::resource('discount-types', DiscountTypeController::class);
    Route::get('edit-discounts/{id}', [DiscountTypeController::Class, 'editDiscountType']);
    // Route::get('get-products/{id}', [DiscountTypeController::class, 'getProduct']);

    Route::resource('customer-types', CustomerTypeController::class);
    Route::get('edit-customer-types/{id}', [CustomerTypeController::Class, 'editCustomerType']);
    Route::resource('customers', CustomerController::class);
    Route::get('edit-customers/{id}', [CustomerController::Class, 'editCustomer']);
    Route::get('get-customers/{id}', [CustomerController::Class, 'getCustomers']);

    //route under inventory 
    Route::resource('products', ProductController::class);
    Route::get('edit-products/{id}', [ProductController::Class, 'editProduct']);


    Route::post('uploads', [ProductController::Class, 'importProduct']);
    Route::resource('product-movements', ProductMovementController::class);
    Route::get('edit-product-movements/{id}', [ProductMovementController::Class, 'editBank']);
    // Route::resource('product-requisitions', ProductRequisitionController::class);  
    // Route::get('edit-product-requisitions/{id}', [ProductRequisitionController::Class, 'editBank']); 

    //route for main store

    Route::get('main-transfers/{id}', [MainStoreTransferController::Class, 'mainStoreTransfer']);
    Route::get('request-transfers/{id}', [MainStoreTransferController::Class, 'requestedTransfer']);
    // Route::post('verify-products', [MainStoreTransferController::Class, 'physicalVerification']);
    Route::resource('main-stores', MainStoreTransferController::class);

    //route for sale voucher
    Route::resource('main-store-sales', MainStoreSaleController::class);
    Route::post('make-payments', [MainStoreSaleController::class, 'makePayment']);
    // Route::resource('phone-emis', PhoneEmiController::class);
 
    
    //route for regional office
    Route::resource('regional-stores', RegionalStoreTransferController::class);
    Route::get('regional-transfer/{id}', [RegionalStoreTransferController::Class, 'requestedRegionalTransfer']);
    //for transfer route in regional
    Route::resource('regional-transfers', RegionProductTransferController::Class);
    Route::get('regional-requisitions/{id}', [RegionProductTransferController::Class, 'requestedRegionalTransfer']);

    // Route::get('get-transfer-regional/{id}', [RegionProductTransferController::Class, 'transferRegionalProduct']);  
    // Route::put('regional-transfer/{id}', [RegionProductTransferController::Class, 'regionalTransfer']); 

    //route for sale voucher RegionProductTransferController
    Route::resource('region-store-sales', RegionStoreSaleController::class);
    Route::get('product-details/{id}', [RegionStoreSaleController::class, 'ProductDetails']);
    Route::post('regional-payments', [RegionStoreSaleController::class, 'regionalPayment']);

    //route for extension office
    Route::resource('extension-stores', ExtensionStoreTransferController::class);

    //for transfer route in extension
    Route::resource('extension-transfers', ExtensionProductTransferController::class);
    Route::get('view-extension-requisitions/{id}', [ExtensionStoreTransferController::Class, 'viewExtensionRequisition']);
    Route::get('get-extension-requisitions', [ExtensionStoreTransferController::Class, 'getExtensionRequisition']);
    Route::post('extension-to-extension-transfers', [ExtensionStoreTransferController::class, 'extensionTransfer']);


    //for transfer route in extension
    // Route::get('get-extension-transfer', [ExtensionStoreTransferController::Class, 'getExtensionTransfer']);
    // Route::get('get-transfer-extension/{id}', [ExtensionStoreTransferController::Class, 'transferExtensionProduct']);
    Route::put('extension-transfer/{id}', [ExtensionStoreTransferController::Class, 'extensionTransfer']);
    //route for sale voucher
    Route::resource('extension-store-sales', ExtensionStoreSaleController::class);
    Route::post('extension-payments', [ExtensionStoreSaleController::class, 'extensionPayment']);
    Route::get('extension-product-details/{id}', [ExtensionStoreSaleController::class, 'ExtensionProductDetails']);


    //route for requisition
    Route::resource('requisitions', ProductRequisitionController::class);
    Route::resource('extension-requisitions', ExtensionRequisitionController::class);
    Route::get('requisition-lists', [ExtensionRequisitionController::class, 'requisitionList']);
    Route::get('edit-requisitions/{id}', [ProductRequisitionController::Class, 'editRequisition']);

    //dashboardController
    Route::resource('dashboards', DashboardController::class);
    Route::get('invoices', [DashboardController::class, 'invoice']);
    Route::get('payments', [DashboardController::class, 'payment']);
    Route::get('sales', [DashboardController::class, 'sale']);
    Route::get('product-list', [DashboardController::class, 'productList']);
    Route::get('repair-list', [DashboardController::class, 'repair']);
    Route::get('replace-list', [DashboardController::class, 'replace']);

    //ReportController
    Route::resource('postedsalesinvoice', PostedSalesInvoiceController::class);
    Route::resource('onhanditems', OnHandItemsController::class);
    Route::resource('salesandstocks',SalesAndStockController::class);
    Route::resource('salesorderlist',SalesOrderListController::class);
    Route::resource('cashreceipt',CashReceiptController::class);
    Route::resource('onlinereceipt',OnlineReceiptController::class);
    Route::resource('staff-emi',StaffEmiController::class);

    //warrantyCOntroller
    Route::resource('warranties', WarrantyController::class);
    Route::get('search-warranties', [WarrantyController::class, 'searchForWarranty']);
    Route::post('replace', [WarrantyController::class, 'Replace']);
    Route::post('repair', [WarrantyController::class, 'Repair']);


    //Notification
    Route::resource('notifications',NotificationController::class);
    Route::get('get-notifications/{id}', [NotificationController::class, 'getNotificationsforUser']);

    //EMI

    // Route::resource('emi', EmiController::class);
    Route::resource('apply-emi', ApplyEmi::class);
    Route::get('phone-details', [ApplyEmi::class, 'productDetails']);

    Route::resource('approve-emi', ApproveEmi::class);
    Route::put('update-product/{id}', [ApplyEmi::class, 'updateProduct']);



});

Route::get('check-products', [ProductController::class, 'checkStock']);