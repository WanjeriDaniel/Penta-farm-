<?php
/*
|--------------------------------------------------------------------------
| PENTA FARM — PROCESS STOCK RECEIPT
|--------------------------------------------------------------------------
| Receives JSON from the Receive Stock page and:
|
| 1. Validates the request
| 2. Creates a stock receipt
| 3. Creates receipt items
| 4. Updates product stock
| 5. Creates stock movement records
| 6. Commits everything as one transaction
| 7. Returns JSON only
|--------------------------------------------------------------------------
*/

declare(strict_types=1);

session_start();

/*
|--------------------------------------------------------------------------
| ALWAYS RETURN JSON
|--------------------------------------------------------------------------
*/
header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| ERROR HANDLING
|--------------------------------------------------------------------------
| Never allow PHP warnings/notices/HTML errors to corrupt the JSON response.
|--------------------------------------------------------------------------
*/
ini_set('display_errors', '0');
error_reporting(E_ALL);

function jsonResponse(bool $success, string $message, array $extra = [], int $httpCode = 200): never
{
    http_response_code($httpCode);

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message
            ],
            $extra
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| GLOBAL EXCEPTION HANDLER
|--------------------------------------------------------------------------
*/
set_exception_handler(function (Throwable $e): never {

    error_log(
        "PENTA FARM process_stock.php Exception: " .
        $e->getMessage() .
        " in " .
        $e->getFile() .
        ":" .
        $e->getLine()
    );

    jsonResponse(
        false,
        'A server error occurred while processing the stock receipt.',
        [
            'error_type' => get_class($e)
        ],
        500
    );
});

/*
|--------------------------------------------------------------------------
| ONLY ACCEPT POST
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(
        false,
        'Invalid request method. Please submit the stock receipt using the Receive Stock form.',
        [],
        405
    );
}

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| READ RAW JSON
|--------------------------------------------------------------------------
*/
$rawInput = file_get_contents('php://input');

if ($rawInput === false || trim($rawInput) === '') {
    jsonResponse(
        false,
        'No stock receipt data was received.',
        [],
        400
    );
}

/*
|--------------------------------------------------------------------------
| DECODE JSON
|--------------------------------------------------------------------------
*/
$data = json_decode($rawInput, true);

if (!is_array($data)) {

    jsonResponse(
        false,
        'The stock receipt data is not valid JSON.',
        [
            'json_error' => json_last_error_msg()
        ],
        400
    );
}

/*
|--------------------------------------------------------------------------
| BASIC INPUTS
|--------------------------------------------------------------------------
*/
$supplierName = isset($data['supplier_name'])
    ? trim((string)$data['supplier_name'])
    : '';

$receiptDate = isset($data['receipt_date'])
    ? trim((string)$data['receipt_date'])
    : '';

$notes = isset($data['notes'])
    ? trim((string)$data['notes'])
    : '';

$items = $data['items'] ?? null;

/*
|--------------------------------------------------------------------------
| VALIDATE ITEMS
|--------------------------------------------------------------------------
*/
if (!is_array($items) || count($items) === 0) {
    jsonResponse(
        false,
        'Please add at least one product to the stock receipt.',
        [],
        400
    );
}

/*
|--------------------------------------------------------------------------
| LIMIT TEXT LENGTHS
|--------------------------------------------------------------------------
*/
if (mb_strlen($supplierName) > 255) {
    jsonResponse(
        false,
        'Supplier name is too long.',
        [],
        400
    );
}

if (mb_strlen($notes) > 500) {
    jsonResponse(
        false,
        'Notes are too long.',
        [],
        400
    );
}

/*
|--------------------------------------------------------------------------
| RECEIPT DATE
|--------------------------------------------------------------------------
| The database column is a timestamp.
| If the frontend sends an empty date, use the current database time.
|--------------------------------------------------------------------------
*/
if ($receiptDate === '') {
    $receiptDate = date('Y-m-d H:i:s');
}

/*
|--------------------------------------------------------------------------
| VALIDATE DATE FORMAT
|--------------------------------------------------------------------------
*/
$dateObject = DateTime::createFromFormat('Y-m-d', $receiptDate);

if ($dateObject !== false && $dateObject->format('Y-m-d') === $receiptDate) {

    $receiptDate = $receiptDate . ' 00:00:00';

} else {

    $dateObject = DateTime::createFromFormat('Y-m-d H:i:s', $receiptDate);

    if (
        $dateObject === false ||
        $dateObject->format('Y-m-d H:i:s') !== $receiptDate
    ) {

        /*
        |--------------------------------------------------------------
        | Some browsers may submit another valid date representation.
        | Try a general DateTime conversion before rejecting it.
        |--------------------------------------------------------------
        */

        try {
            $dateObject = new DateTime($receiptDate);
            $receiptDate = $dateObject->format('Y-m-d H:i:s');
        } catch (Throwable $e) {

            jsonResponse(
                false,
                'The receipt date is invalid.',
                [],
                400
            );
        }
    }
}

/*
|--------------------------------------------------------------------------
| START DATABASE TRANSACTION
|--------------------------------------------------------------------------
*/
try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | GENERATE UNIQUE RECEIPT NUMBER
    |--------------------------------------------------------------------------
    |
    | Example:
    | RCV-20261005-194530
    |
    | We include microseconds so two receipts created very quickly
    | are less likely to receive the same number.
    |--------------------------------------------------------------------------
    */
    $microtime = microtime(true);

    $milliseconds = sprintf(
        '%03d',
        (int)(($microtime - floor($microtime)) * 1000)
    );

    $receiptNumber =
        'RCV-' .
        date('Ymd-His') .
        '-' .
        $milliseconds;

    /*
    |--------------------------------------------------------------------------
    | CREATE RECEIPT
    |--------------------------------------------------------------------------
    |
    | total_cost starts at zero.
    | We calculate it while processing the items.
    |--------------------------------------------------------------------------
    */
    $receiptSql = "
        INSERT INTO stock_receipts
        (
            receipt_number,
            supplier_name,
            receipt_date,
            total_cost,
            notes,
            status
        )
        VALUES
        (
            :receipt_number,
            :supplier_name,
            :receipt_date,
            0.00,
            :notes,
            'received'
        )
    ";

    $receiptStmt = $pdo->prepare($receiptSql);

    $receiptStmt->execute([
        ':receipt_number' => $receiptNumber,
        ':supplier_name' => $supplierName !== '' ? $supplierName : null,
        ':receipt_date' => $receiptDate,
        ':notes' => $notes !== '' ? $notes : null
    ]);

    $receiptId = (int)$pdo->lastInsertId();

    if ($receiptId <= 0) {
        throw new RuntimeException(
            'The stock receipt could not be created.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PREPARE PRODUCT QUERY
    |--------------------------------------------------------------------------
    |
    | We read the actual product from the database instead of trusting
    | product names/prices sent by JavaScript.
    |--------------------------------------------------------------------------
    */
    $productStmt = $pdo->prepare("
        SELECT
            id,
            product_name,
            unit,
            purchase_unit,
            purchase_conversion,
            buying_price,
            stock_quantity,
            is_active
        FROM products
        WHERE id = :product_id
        LIMIT 1
        FOR UPDATE
    ");

    /*
    |--------------------------------------------------------------------------
    | PREPARE RECEIPT ITEM INSERT
    |--------------------------------------------------------------------------
    */
    $itemStmt = $pdo->prepare("
        INSERT INTO stock_receipt_items
        (
            stock_receipt_id,
            product_id,
            quantity,
            buying_price,
            subtotal
        )
        VALUES
        (
            :stock_receipt_id,
            :product_id,
            :quantity,
            :buying_price,
            :subtotal
        )
    ");

    /*
    |--------------------------------------------------------------------------
    | PREPARE STOCK UPDATE
    |--------------------------------------------------------------------------
    */
    $stockUpdateStmt = $pdo->prepare("
        UPDATE products
        SET stock_quantity = stock_quantity + :quantity
        WHERE id = :product_id
    ");

    /*
    |--------------------------------------------------------------------------
    | PREPARE STOCK MOVEMENT INSERT
    |--------------------------------------------------------------------------
    */
    $movementStmt = $pdo->prepare("
        INSERT INTO stock_movements
        (
            product_id,
            movement_type,
            quantity,
            buying_price,
            reference_id,
            notes
        )
        VALUES
        (
            :product_id,
            'stock_in',
            :quantity,
            :buying_price,
            :reference_id,
            :notes
        )
    ");

    /*
    |--------------------------------------------------------------------------
    | TOTAL RECEIPT COST
    |--------------------------------------------------------------------------
    */
    $totalCost = 0.00;

    /*
    |--------------------------------------------------------------------------
    | PROCESS EACH PRODUCT
    |--------------------------------------------------------------------------
    */
    foreach ($items as $index => $item) {

        if (!is_array($item)) {
            throw new RuntimeException(
                'Invalid product data at item #' . ($index + 1) . '.'
            );
        }

        /*
        |----------------------------------------------------------------------
        | PRODUCT ID
        |----------------------------------------------------------------------
        */
        $productId = isset($item['product_id'])
            ? (int)$item['product_id']
            : 0;

        if ($productId <= 0) {
            throw new RuntimeException(
                'Invalid product selected at item #' . ($index + 1) . '.'
            );
        }

        /*
        |----------------------------------------------------------------------
        | PURCHASE QUANTITY
        |----------------------------------------------------------------------
        */
        $purchaseQuantity = isset($item['purchase_quantity'])
            ? (float)$item['purchase_quantity']
            : 0;

        if ($purchaseQuantity <= 0) {
            throw new RuntimeException(
                'Invalid purchase quantity for item #' . ($index + 1) . '.'
            );
        }

        /*
        |----------------------------------------------------------------------
        | BUYING PRICE
        |----------------------------------------------------------------------
        |
        | The Receive Stock page sends buying_price.
        | We validate it here, but the processor will use the submitted
        | receiving price because this is the actual purchase cost.
        |----------------------------------------------------------------------
        */
        $submittedBuyingPrice = isset($item['buying_price'])
            ? (float)$item['buying_price']
            : 0;

        if ($submittedBuyingPrice < 0) {
            throw new RuntimeException(
                'Invalid buying price for item #' . ($index + 1) . '.'
            );
        }

        /*
        |----------------------------------------------------------------------
        | GET REAL PRODUCT FROM DATABASE
        |----------------------------------------------------------------------
        */
        $productStmt->execute([
            ':product_id' => $productId
        ]);

        $product = $productStmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new RuntimeException(
                'Product ID ' . $productId . ' could not be found.'
            );
        }

        if ((int)$product['is_active'] !== 1) {
            throw new RuntimeException(
                'Product "' .
                $product['product_name'] .
                '" is inactive and cannot receive stock.'
            );
        }

        /*
        |----------------------------------------------------------------------
        | CONVERSION FACTOR
        |----------------------------------------------------------------------
        |
        | Example:
        |
        | Purchase unit = Box
        | Selling/stock unit = Piece
        | Conversion = 10
        |
        | Receive 5 boxes:
        |
        | 5 × 10 = 50 pieces added to stock.
        |----------------------------------------------------------------------
        */
        $conversion = isset($product['purchase_conversion'])
            ? (float)$product['purchase_conversion']
            : 1.0;

        if ($conversion <= 0) {
            $conversion = 1.0;
        }

        /*
        |----------------------------------------------------------------------
        | CALCULATE ACTUAL STOCK QUANTITY
        |----------------------------------------------------------------------
        */
        $stockQuantity = $purchaseQuantity * $conversion;

        if ($stockQuantity <= 0) {
            throw new RuntimeException(
                'Calculated stock quantity is invalid for "' .
                $product['product_name'] .
                '".'
            );
        }

        /*
        |----------------------------------------------------------------------
        | BUYING PRICE
        |----------------------------------------------------------------------
        |
        | The frontend sends the price entered during receiving.
        | That is the actual cost of this receipt.
        |
        | IMPORTANT:
        | subtotal is based on PURCHASE quantity, not converted stock quantity.
        |
        | Example:
        | 10 bags × KSh 2,000 = KSh 20,000
        |
        | Not:
        | 500 pieces × KSh 2,000.
        |----------------------------------------------------------------------
        */
        $buyingPrice = round($submittedBuyingPrice, 2);

        /*
        |----------------------------------------------------------------------
        | SUBTOTAL
        |----------------------------------------------------------------------
        */
        $subtotal = round(
            $purchaseQuantity * $buyingPrice,
            2
        );

        /*
        |----------------------------------------------------------------------
        | ADD TO RECEIPT TOTAL
        |----------------------------------------------------------------------
        */
        $totalCost += $subtotal;

        /*
        |----------------------------------------------------------------------
        | INSERT RECEIPT ITEM
        |----------------------------------------------------------------------
        |
        | IMPORTANT:
        | stock_receipt_items.quantity represents the actual inventory
        | quantity because this is what the table stores.
        |----------------------------------------------------------------------
        */
        $itemStmt->execute([
            ':stock_receipt_id' => $receiptId,
            ':product_id' => $productId,
            ':quantity' => $stockQuantity,
            ':buying_price' => $buyingPrice,
            ':subtotal' => $subtotal
        ]);

        /*
        |----------------------------------------------------------------------
        | UPDATE PRODUCT STOCK
        |----------------------------------------------------------------------
        */
        $stockUpdateStmt->execute([
            ':quantity' => $stockQuantity,
            ':product_id' => $productId
        ]);

        /*
        |----------------------------------------------------------------------
        | CREATE STOCK MOVEMENT
        |----------------------------------------------------------------------
        |
        | Positive quantity = stock coming IN.
        |--------------------------------------------------------------------------
        */
        $movementNotes =
            'Stock received - Receipt ' .
            $receiptNumber;

        $movementStmt->execute([
            ':product_id' => $productId,
            ':quantity' => $stockQuantity,
            ':buying_price' => $buyingPrice,
            ':reference_id' => $receiptId,
            ':notes' => $movementNotes
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE RECEIPT TOTAL
    |--------------------------------------------------------------------------
    */
    $totalCost = round($totalCost, 2);

    $updateReceiptStmt = $pdo->prepare("
        UPDATE stock_receipts
        SET total_cost = :total_cost
        WHERE id = :receipt_id
    ");

    $updateReceiptStmt->execute([
        ':total_cost' => $totalCost,
        ':receipt_id' => $receiptId
    ]);

    /*
    |--------------------------------------------------------------------------
    | COMMIT EVERYTHING
    |--------------------------------------------------------------------------
    */
    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */
    jsonResponse(
        true,
        'Stock received successfully.',
        [
            'receipt_id' => $receiptId,
            'receipt_number' => $receiptNumber,
            'total_cost' => number_format($totalCost, 2, '.', '')
        ],
        200
    );

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | ROLLBACK
    |--------------------------------------------------------------------------
    */
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    /*
    |--------------------------------------------------------------------------
    | LOG REAL ERROR
    |--------------------------------------------------------------------------
    */
    error_log(
        "PENTA FARM process_stock.php Error: " .
        $e->getMessage() .
        " in " .
        $e->getFile() .
        ":" .
        $e->getLine()
    );

    /*
    |--------------------------------------------------------------------------
    | RETURN SAFE JSON ERROR
    |--------------------------------------------------------------------------
    */
    jsonResponse(
        false,
        $e->getMessage(),
        [],
        500
    );
}