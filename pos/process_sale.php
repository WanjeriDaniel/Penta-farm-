<?php

require_once "../config/database.php";

header("Content-Type: application/json");

try {

    /*
    |--------------------------------------------------------------------------
    | Read JSON request
    |--------------------------------------------------------------------------
    */

    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!is_array($input)) {
        throw new Exception("Invalid sale data.");
    }


    /*
    |--------------------------------------------------------------------------
    | Basic information
    |--------------------------------------------------------------------------
    */

    $discount = (float)($input["discount"] ?? 0);

    /*
    |--------------------------------------------------------------------------
    | Sale type
    |--------------------------------------------------------------------------
    */

    $saleType = strtolower(
        trim($input["sale_type"] ?? "cash")
    );

    $allowedSaleTypes = [
        "cash",
        "invoice",
        "paylater",
        "retail"
    ];

    if (!in_array($saleType, $allowedSaleTypes, true)) {
        throw new Exception("Invalid sale type.");
    }

    /*
|--------------------------------------------------------------------------
| Fuel / Delivery Charge
|--------------------------------------------------------------------------
*/

$fuelCharge =
    (float)(
        $input["fuel_charge"]
        ?? 0
    );

if ($fuelCharge < 0) {

    throw new Exception(
        "Fuel / delivery charge cannot be negative."
    );

}

    $paymentMethod = trim(
        $input["payment_method"] ?? "Cash"
    );

    $amountPaid = (float)($input["amount_paid"] ?? 0);

    $items = $input["items"] ?? [];

    /*
    |--------------------------------------------------------------------------
    | Normalize Invoice / Pay Later payment details
    |--------------------------------------------------------------------------
    */

    if ($saleType === "paylater") {
        $paymentMethod = "Pay Later";
        $amountPaid = 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Validate payment method
    |--------------------------------------------------------------------------
    */

    $allowedPaymentMethods = [
        "Cash",
        "M-Pesa",
        "Bank",
        "Pay Later",
        "Invoice"
    ];

    if (!in_array(
        $paymentMethod,
        $allowedPaymentMethods,
        true
    )) {
        throw new Exception(
            "Invalid payment method."
        );
    }

    if ($amountPaid < 0) {
        throw new Exception(
            "Amount paid cannot be negative."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validate items
    |--------------------------------------------------------------------------
    */

    if (empty($items)) {

        throw new Exception(
            "The sale has no products."
        );

    }


    if ($discount < 0) {

        throw new Exception(
            "Discount cannot be negative."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Start transaction
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Calculate everything from the database
    |--------------------------------------------------------------------------
    */

    $subtotal = 0;

    $processedItems = [];


    foreach ($items as $item) {

        $productId = (int)(
            $item["product_id"] ?? 0
        );

        $quantity = (float)(
            $item["quantity"] ?? 0
        );


        if ($productId <= 0) {

            throw new Exception(
                "Invalid product selected."
            );

        }


        if ($quantity <= 0) {

            throw new Exception(
                "Invalid quantity."
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Lock product row
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                product_name,
                unit,
                buying_price,
                selling_price,
                stock_quantity,
                minimum_sale_quantity,
                quantity_step,
                is_active
            FROM products
            WHERE id = :id
            FOR UPDATE
        ");

        $stmt->execute([
            ":id" => $productId
        ]);


        $product = $stmt->fetch(
            PDO::FETCH_ASSOC
        );


        if (!$product) {

            throw new Exception(
                "Product not found."
            );

        }


        if ((int)$product["is_active"] !== 1) {

            throw new Exception(
                $product["product_name"] .
                " is inactive."
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Stock check
        |--------------------------------------------------------------------------
        */

        $availableStock = (float)(
            $product["stock_quantity"] ?? 0
        );


        if ($quantity > $availableStock) {

            throw new Exception(

                "Not enough stock for " .
                $product["product_name"] .
                ". Available: " .
                number_format(
                    $availableStock,
                    3
                ) .
                " " .
                ($product["unit"] ?? "")

            );

        }


        /*
        |--------------------------------------------------------------------------
        | Minimum quantity
        |--------------------------------------------------------------------------
        */

        $minimumQuantity = (float)(
            $product["minimum_sale_quantity"] ?? 1
        );


        if ($quantity < $minimumQuantity) {

            throw new Exception(

                $product["product_name"] .
                " minimum sale quantity is " .
                number_format(
                    $minimumQuantity,
                    3
                ) .
                " " .
                ($product["unit"] ?? "")

            );

        }


        /*
        |--------------------------------------------------------------------------
        | Quantity step
        |--------------------------------------------------------------------------
        */

        $quantityStep = (float)(
            $product["quantity_step"] ?? 1
        );


        if ($quantityStep <= 0) {
            $quantityStep = 1;
        }


        $difference =
            $quantity - $minimumQuantity;


        $steps =
            $difference / $quantityStep;


        $nearestStep =
            round($steps);


        if (
            abs($steps - $nearestStep) >
            0.000001
        ) {

            throw new Exception(

                $product["product_name"] .
                " must be sold in steps of " .
                number_format(
                    $quantityStep,
                    3
                ) .
                " " .
                ($product["unit"] ?? "")

            );

        }


        /*
        |--------------------------------------------------------------------------
        | Read current prices from database
        |--------------------------------------------------------------------------
        */

        $sellingPrice = (float)(
            $product["selling_price"] ?? 0
        );

        $buyingPrice = (float)(
            $product["buying_price"] ?? 0
        );


        if ($sellingPrice < 0) {

            throw new Exception(
                "Invalid selling price for " .
                $product["product_name"]
            );

        }


        if ($buyingPrice < 0) {

            throw new Exception(
                "Invalid buying price for " .
                $product["product_name"]
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Calculate line subtotal
        |--------------------------------------------------------------------------
        */

        $lineSubtotal =
            $quantity *
            $sellingPrice;


        $lineSubtotal =
            round(
                $lineSubtotal,
                2
            );


        $subtotal += $lineSubtotal;


        /*
        |--------------------------------------------------------------------------
        | Store verified item
        |--------------------------------------------------------------------------
        */

        $processedItems[] = [

            "product_id" =>
                $productId,

            "product_name" =>
                $product["product_name"],

            "quantity" =>
                $quantity,

            "unit" =>
                $product["unit"] ?? "",

            "unit_price" =>
                $sellingPrice,

            "buying_price" =>
                $buyingPrice,

            "subtotal" =>
                $lineSubtotal

        ];

    }


    /*
    |--------------------------------------------------------------------------
    | Round subtotal
    |--------------------------------------------------------------------------
    */

    $subtotal =
        round(
            $subtotal,
            2
        );


    /*
    |--------------------------------------------------------------------------
    | Validate discount
    |--------------------------------------------------------------------------
    */

    if ($discount > $subtotal) {

        throw new Exception(
            "Discount cannot be greater than the subtotal."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Calculate total
    |--------------------------------------------------------------------------
    */

    $totalAmount =
        $subtotal -
        $discount;


    /*
|--------------------------------------------------------------------------
| Total Including Delivery / Fuel
|--------------------------------------------------------------------------
*/

$totalAmount =
    round(
        $subtotal -
        $discount +
        $fuelCharge,
        2
    );

    /*
    |--------------------------------------------------------------------------
    | Validate payment
    |--------------------------------------------------------------------------
    */

    /*
    | Cash / normal retail sales must be fully paid.
    | Invoice and Pay Later sales may have an outstanding balance.
    */
    if (
        ($saleType === "cash" || $saleType === "retail")
        && $amountPaid < $totalAmount
    ) {

        throw new Exception(
            "Amount paid is less than the total. " .
            "Required: KSh " .
            number_format(
                $totalAmount,
                2
            )
        );

    }

    if ($amountPaid > $totalAmount) {
        throw new Exception(
            "Amount paid cannot be greater than the total."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Credit sale details
    |--------------------------------------------------------------------------
    */

    $customerName = trim(
        $input["customer_name"] ?? ""
    );

    $customerPhone = trim(
        $input["customer_phone"] ?? ""
    );

    $customerAddress = trim(
        $input["customer_address"] ?? ""
    );

    $dueDate = trim(
        $input["due_date"] ?? ""
    );

    if (
        $saleType === "invoice"
        || $saleType === "paylater"
    ) {

        if ($customerName === "") {
            throw new Exception(
                "Customer name is required for credit sales."
            );
        }

        if ($dueDate === "") {
            throw new Exception(
                "Due date is required for credit sales."
            );
        }

    }

    /*
    |--------------------------------------------------------------------------
    | Calculate change
    |--------------------------------------------------------------------------
    */

    $changeAmount =
        $amountPaid -
        $totalAmount;


    $changeAmount =
        round(
            max(
                0,
                $changeAmount
            ),
            2
        );


    /*
    |--------------------------------------------------------------------------
    | Create sale
    |--------------------------------------------------------------------------
    |
    | receipt_number is temporarily NULL.
    | We get the sale ID first, then generate the receipt number.
    |
    */
$stmt = $pdo->prepare("

    INSERT INTO sales

    (
        receipt_number,
        sale_type,
        customer_name,
        customer_phone,
        customer_address,
        due_date,
        sale_date,
        subtotal,
        discount,
        fuel_charge,
        total_amount,
        payment_method,
        amount_paid,
        change_amount,
        payment_status,
        status
    )

    VALUES

    (
        NULL,
        :sale_type,
        :customer_name,
        :customer_phone,
        :customer_address,
        :due_date,
        CURRENT_TIMESTAMP,
        :subtotal,
        :discount,
        :fuel_charge,
        :total_amount,
        :payment_method,
        :amount_paid,
        :change_amount,
        :payment_status,
        'completed'
    )

");
$stmt->execute([

    ":sale_type" =>
        $saleType,

    ":customer_name" =>
        $customerName !== "" ? $customerName : null,

    ":customer_phone" =>
        $customerPhone !== "" ? $customerPhone : null,

    ":customer_address" =>
        $customerAddress !== "" ? $customerAddress : null,

    ":due_date" =>
        $dueDate !== "" ? $dueDate : null,

    ":subtotal" =>
        $subtotal,

    ":discount" =>
        $discount,

    ":fuel_charge" =>
        $fuelCharge,

    ":total_amount" =>
        $totalAmount,

    ":payment_method" =>
        $paymentMethod,

    ":amount_paid" =>
        $amountPaid,

    ":change_amount" =>
        $changeAmount,

    ":payment_status" =>
        $amountPaid >= $totalAmount
            ? "paid"
            : "pending"

]);

    $saleId =
        (int)$pdo->lastInsertId();


    if ($saleId <= 0) {

        throw new Exception(
            "Unable to create sale."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Generate receipt number
    |--------------------------------------------------------------------------
    */

    $receiptNumber =
        "PF-" .
        date("Ymd") .
        "-" .
        str_pad(
            (string)$saleId,
            6,
            "0",
            STR_PAD_LEFT
        );


    /*
    |--------------------------------------------------------------------------
    | Update receipt number
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE sales
        SET receipt_number = :receipt_number
        WHERE id = :sale_id
    ");

    $stmt->execute([

        ":receipt_number" =>
            $receiptNumber,

        ":sale_id" =>
            $saleId

    ]);


    /*
    |--------------------------------------------------------------------------
    | Prepare sale item statement
    |--------------------------------------------------------------------------
    */

    $insertSaleItem = $pdo->prepare("
        INSERT INTO sale_items
        (
            sale_id,
            product_id,
            quantity,
            unit_price,
            buying_price,
            subtotal
        )
        VALUES
        (
            :sale_id,
            :product_id,
            :quantity,
            :unit_price,
            :buying_price,
            :subtotal
        )
    ");


    /*
    |--------------------------------------------------------------------------
    | Prepare stock update
    |--------------------------------------------------------------------------
    */

    $updateStock = $pdo->prepare("
        UPDATE products
        SET
            stock_quantity =
                stock_quantity - :quantity,

            updated_at =
                CURRENT_TIMESTAMP

        WHERE id = :product_id
          AND stock_quantity >= :quantity
          AND is_active = 1
    ");


    /*
    |--------------------------------------------------------------------------
    | Prepare stock movement
    |--------------------------------------------------------------------------
    */

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
            'sale',
            :quantity,
            :buying_price,
            :reference_id,
            :notes
        )
    ");


    /*
    |--------------------------------------------------------------------------
    | Save each item
    |--------------------------------------------------------------------------
    */

    foreach ($processedItems as $item) {


        /*
        | Save sale item
        */

        $insertSaleItem->execute([

            ":sale_id" =>
                $saleId,

            ":product_id" =>
                $item["product_id"],

            ":quantity" =>
                $item["quantity"],

            ":unit_price" =>
                $item["unit_price"],

            ":buying_price" =>
                $item["buying_price"],

            ":subtotal" =>
                $item["subtotal"]

        ]);


        /*
        | Deduct inventory
        */

        $updateStock->execute([

            ":quantity" =>
                $item["quantity"],

            ":product_id" =>
                $item["product_id"]

        ]);


        if ($updateStock->rowCount() !== 1) {

            throw new Exception(

                "Unable to deduct stock for " .
                $item["product_name"]

            );

        }


        /*
        | Record movement as negative quantity
        */

        $insertMovement->execute([

            ":product_id" =>
                $item["product_id"],

            ":quantity" =>
                -$item["quantity"],

            ":buying_price" =>
                $item["buying_price"],

            ":reference_id" =>
                $saleId,

            ":notes" =>
                "Sale - " .
                $receiptNumber

        ]);

    }


    /*
    |--------------------------------------------------------------------------
    | Commit transaction
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Success response
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        "success" =>
            true,

        "message" =>
            $saleType === "invoice"
                ? "Invoice created successfully."
                : (
                    $saleType === "paylater"
                        ? "Pay Later sale created successfully."
                        : "Sale completed successfully."
                ),

        "sale_id" =>
            $saleId,

        "receipt_number" =>
            $receiptNumber,

        "subtotal" =>
            number_format(
                $subtotal,
                2,
                ".",
                ""
            ),

        "discount" =>
            number_format(
                $discount,
                2,
                ".",
                ""
            ), "fuel_charge" =>
    number_format(
        $fuelCharge,
        2,
        ".",
        ""
    ),



        "total" =>
            number_format(
                $totalAmount,
                2,
                ".",
                ""
            ),

        "amount_paid" =>
            number_format(
                $amountPaid,
                2,
                ".",
                ""
            ),

        "change" =>
            number_format(
                $changeAmount,
                2,
                ".",
                ""
            )

    ]);


} catch (Throwable $e) {


    /*
    |--------------------------------------------------------------------------
    | Roll back everything on failure
    |--------------------------------------------------------------------------
    */

    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }


    http_response_code(400);


    echo json_encode([

        "success" =>
            false,

        "message" =>
            $e->getMessage()

    ]);

}