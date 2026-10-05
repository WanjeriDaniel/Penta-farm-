<?php

require_once "../config/database.php";

header("Content-Type: application/json");

try {

    /*
    |--------------------------------------------------------------------------
    | Read Request
    |--------------------------------------------------------------------------
    */

    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!is_array($input)) {
        throw new Exception("Invalid request data.");
    }


    $supplierName = trim(
        $input["supplier_name"] ?? ""
    );

    $receiptDate = trim(
        $input["receipt_date"] ?? date("Y-m-d")
    );

    $notes = trim(
        $input["notes"] ?? ""
    );

    $items = $input["items"] ?? [];


    if (empty($items)) {
        throw new Exception(
            "Please add at least one product."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Date
    |--------------------------------------------------------------------------
    */

    $dateObject = DateTime::createFromFormat(
        "Y-m-d",
        $receiptDate
    );

    if (
        !$dateObject ||
        $dateObject->format("Y-m-d") !== $receiptDate
    ) {

        throw new Exception(
            "Invalid receipt date."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Calculate Total
    |--------------------------------------------------------------------------
    */

    $totalCost = 0;


    foreach ($items as $item) {

        $quantity = (float)(
            $item["purchase_quantity"] ?? 0
        );

        $buyingPrice = (float)(
            $item["buying_price"] ?? 0
        );


        if ($quantity <= 0) {

            throw new Exception(
                "Invalid purchase quantity."
            );

        }


        if ($buyingPrice < 0) {

            throw new Exception(
                "Buying price cannot be negative."
            );

        }


        $totalCost +=
            $quantity * $buyingPrice;

    }


    /*
    |--------------------------------------------------------------------------
    | Start Transaction
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Generate Receipt Number
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT id
        FROM stock_receipts
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ");

    $lastReceipt =
        $stmt->fetch(PDO::FETCH_ASSOC);


    $nextNumber =
        $lastReceipt
        ? ((int)$lastReceipt["id"] + 1)
        : 1;


    $receiptNumber =
        "SR-" .
        str_pad(
            $nextNumber,
            6,
            "0",
            STR_PAD_LEFT
        );


    /*
    |--------------------------------------------------------------------------
    | Create Stock Receipt
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
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
            :total_cost,
            :notes,
            'received'
        )
    ");


    $stmt->execute([

        ":receipt_number" =>
            $receiptNumber,

        ":supplier_name" =>
            $supplierName !== ""
            ? $supplierName
            : null,

        ":receipt_date" =>
            $receiptDate . " 00:00:00",

        ":total_cost" =>
            $totalCost,

        ":notes" =>
            $notes !== ""
            ? $notes
            : null

    ]);


    $stockReceiptId =
        $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | Prepare Statements
    |--------------------------------------------------------------------------
    */

    $insertItem = $pdo->prepare("
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


    $updateProduct = $pdo->prepare("
        UPDATE products
        SET
            stock_quantity =
                stock_quantity + :stock_quantity,

            buying_price =
                :buying_price,

            updated_at =
                CURRENT_TIMESTAMP

        WHERE id = :product_id
          AND is_active = 1
    ");


    $insertMovement = $pdo->prepare("
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
    | Process Products
    |--------------------------------------------------------------------------
    */

    foreach ($items as $item) {

        $productId =
            (int)($item["product_id"] ?? 0);


        $purchaseQuantity =
            (float)(
                $item["purchase_quantity"] ?? 0
            );


        $buyingPrice =
            (float)(
                $item["buying_price"] ?? 0
            );


        if ($productId <= 0) {

            throw new Exception(
                "Invalid product selected."
            );

        }


        if ($purchaseQuantity <= 0) {

            throw new Exception(
                "Invalid quantity."
            );

        }


        if ($buyingPrice < 0) {

            throw new Exception(
                "Invalid buying price."
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Get Product From Database
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                product_name,
                unit,
                purchase_unit,
                purchase_conversion,
                is_active

            FROM products

            WHERE id = :id

            FOR UPDATE
        ");


        $stmt->execute([
            ":id" => $productId
        ]);


        $product =
            $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$product) {

            throw new Exception(
                "Product not found."
            );

        }


        if ((int)$product["is_active"] !== 1) {

            throw new Exception(
                "Product '" .
                $product["product_name"] .
                "' is inactive."
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Conversion
        |--------------------------------------------------------------------------
        */

        $conversion =
            (float)$product["purchase_conversion"];


        if ($conversion <= 0) {
            $conversion = 1;
        }


        /*
        |--------------------------------------------------------------------------
        | Calculate Stock Quantity
        |--------------------------------------------------------------------------
        */

        $stockQuantity =
            $purchaseQuantity *
            $conversion;


        /*
        |--------------------------------------------------------------------------
        | Calculate Item Subtotal
        |--------------------------------------------------------------------------
        */

        $subtotal =
            $purchaseQuantity *
            $buyingPrice;


        /*
        |--------------------------------------------------------------------------
        | Save Receipt Item
        |--------------------------------------------------------------------------
        */

        $insertItem->execute([

            ":stock_receipt_id" =>
                $stockReceiptId,

            ":product_id" =>
                $productId,

            ":quantity" =>
                $purchaseQuantity,

            ":buying_price" =>
                $buyingPrice,

            ":subtotal" =>
                $subtotal

        ]);


        /*
        |--------------------------------------------------------------------------
        | Increase Product Stock
        |--------------------------------------------------------------------------
        */

        $updateProduct->execute([

            ":stock_quantity" =>
                $stockQuantity,

            ":buying_price" =>
                $buyingPrice,

            ":product_id" =>
                $productId

        ]);


        if ($updateProduct->rowCount() === 0) {

            throw new Exception(
                "Unable to update stock for " .
                $product["product_name"]
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Record Stock Movement
        |--------------------------------------------------------------------------
        */

        $insertMovement->execute([

            ":product_id" =>
                $productId,

            ":quantity" =>
                $stockQuantity,

            ":buying_price" =>
                $buyingPrice,

            ":reference_id" =>
                $stockReceiptId,

            ":notes" =>
                "Stock received - " .
                $receiptNumber

        ]);

    }


    /*
    |--------------------------------------------------------------------------
    | Commit Transaction
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        "success" => true,

        "message" =>
            "Stock received successfully.",

        "receipt_number" =>
            $receiptNumber,

        "receipt_id" =>
            $stockReceiptId,

        "total_cost" =>
            number_format(
                $totalCost,
                2,
                ".",
                ""
            )

    ]);


} catch (Throwable $e) {


    /*
    |--------------------------------------------------------------------------
    | Rollback
    |--------------------------------------------------------------------------
    */

    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }


    echo json_encode([

        "success" => false,

        "message" =>
            $e->getMessage()

    ]);

}