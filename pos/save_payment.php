<?php

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| Only accept POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: outstanding.php");

    exit;

}


/*
|--------------------------------------------------------------------------
| Input
|--------------------------------------------------------------------------
*/

$saleId = (int)($_POST["sale_id"] ?? 0);

$amount = (float)($_POST["amount"] ?? 0);

$paymentMethod =
    trim($_POST["payment_method"] ?? "");

$reference =
    trim($_POST["reference"] ?? "");

$notes =
    trim($_POST["notes"] ?? "");

$paymentDate =
    trim($_POST["payment_date"] ?? "");


/*
|--------------------------------------------------------------------------
| Allowed Payment Methods
|--------------------------------------------------------------------------
*/

$allowedPaymentMethods = [
    "Cash",
    "M-Pesa",
    "Bank"
];


/*
|--------------------------------------------------------------------------
| Basic Validation
|--------------------------------------------------------------------------
*/

if ($saleId <= 0) {

    die("Invalid sale.");

}


if ($amount <= 0) {

    die("Payment amount must be greater than zero.");

}


if (!in_array(
    $paymentMethod,
    $allowedPaymentMethods,
    true
)) {

    die("Invalid payment method.");

}


/*
|--------------------------------------------------------------------------
| Validate Payment Date
|--------------------------------------------------------------------------
*/

if ($paymentDate === "") {

    $paymentDate = date("Y-m-d");

}

$dateObject =
    DateTime::createFromFormat(
        "Y-m-d",
        $paymentDate
    );

if (
    !$dateObject ||
    $dateObject->format("Y-m-d") !== $paymentDate
) {

    die("Invalid payment date.");

}


/*
|--------------------------------------------------------------------------
| Validate Reference Length
|--------------------------------------------------------------------------
*/

if (strlen($reference) > 100) {

    die("Payment reference is too long.");

}


/*
|--------------------------------------------------------------------------
| Validate Notes Length
|--------------------------------------------------------------------------
*/

if (strlen($notes) > 2000) {

    die("Payment notes are too long.");

}


/*
|--------------------------------------------------------------------------
| Database Transaction
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Lock Sale
    |--------------------------------------------------------------------------
    |
    | FOR UPDATE prevents another payment from changing the same sale
    | while we are calculating and recording this payment.
    |
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            receipt_number,
            sale_type,
            customer_name,
            total_amount,
            amount_paid,
            status
        FROM sales
        WHERE id = :sale_id
        FOR UPDATE
    ");

    $stmt->execute([
        ":sale_id" => $saleId
    ]);

    $sale =
        $stmt->fetch(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | Sale Validation
    |--------------------------------------------------------------------------
    */

    if (!$sale) {

        throw new Exception(
            "Sale not found."
        );

    }


    if (
        !in_array(
            $sale["sale_type"],
            ["invoice", "paylater"],
            true
        )
    ) {

        throw new Exception(
            "This sale does not accept credit payments."
        );

    }


    if ($sale["status"] !== "completed") {

        throw new Exception(
            "This sale is not available for payment."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Original Amount Paid
    |--------------------------------------------------------------------------
    */

    $originalPaid =
        (float)$sale["amount_paid"];


    /*
    |--------------------------------------------------------------------------
    | Additional Payments
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(
                SUM(amount),
                0
            ) AS additional_paid
        FROM sale_payments
        WHERE sale_id = :sale_id
    ");

    $stmt->execute([
        ":sale_id" => $saleId
    ]);

    $additionalPaid =
        (float)$stmt
            ->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Current Paid
    |--------------------------------------------------------------------------
    */

    $currentPaid =
        $originalPaid +
        $additionalPaid;


    /*
    |--------------------------------------------------------------------------
    | Calculate Balance
    |--------------------------------------------------------------------------
    */

    $totalAmount =
        (float)$sale["total_amount"];

    $balance =
        $totalAmount -
        $currentPaid;


    /*
    |--------------------------------------------------------------------------
    | Prevent Floating Point Tiny Errors
    |--------------------------------------------------------------------------
    */

    $balance =
        round(
            $balance,
            2
        );


    /*
    |--------------------------------------------------------------------------
    | Already Paid
    |--------------------------------------------------------------------------
    */

    if ($balance <= 0) {

        throw new Exception(
            "This sale is already fully paid."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Prevent Overpayment
    |--------------------------------------------------------------------------
    */

    if (
        round($amount, 2) >
        $balance
    ) {

        throw new Exception(
            "Payment cannot be greater than the outstanding balance of KSh "
            . number_format(
                $balance,
                2
            )
            . "."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | New Paid Amount
    |--------------------------------------------------------------------------
    */

    $newPaid =
        round(
            $currentPaid + $amount,
            2
        );


    /*
    |--------------------------------------------------------------------------
    | New Balance
    |--------------------------------------------------------------------------
    */

    $newBalance =
        round(
            $totalAmount - $newPaid,
            2
        );


    /*
    |--------------------------------------------------------------------------
    | Determine Payment Status
    |--------------------------------------------------------------------------
    */

    if ($newPaid <= 0) {

        $paymentStatus =
            "unpaid";

    } elseif (
        $newBalance <= 0
    ) {

        $paymentStatus =
            "paid";

    } else {

        $paymentStatus =
            "partial";

    }


    /*
    |--------------------------------------------------------------------------
    | Insert Payment
    |--------------------------------------------------------------------------
    */

    $paymentDateTime =
        $paymentDate . " " . date("H:i:s");


    $stmt = $pdo->prepare("
        INSERT INTO sale_payments (
            sale_id,
            amount,
            payment_method,
            payment_date,
            reference,
            notes
        )
        VALUES (
            :sale_id,
            :amount,
            :payment_method,
            :payment_date,
            :reference,
            :notes
        )
    ");


    $stmt->execute([

        ":sale_id" =>
            $saleId,

        ":amount" =>
            number_format(
                $amount,
                2,
                ".",
                ""
            ),

        ":payment_method" =>
            $paymentMethod,

        ":payment_date" =>
            $paymentDateTime,

        ":reference" =>
            $reference !== ""
                ? $reference
                : null,

        ":notes" =>
            $notes !== ""
                ? $notes
                : null

    ]);


    /*
    |--------------------------------------------------------------------------
    | Update Sale
    |--------------------------------------------------------------------------
    |
    | Keep sales.amount_paid synchronized with the complete payment total.
    |
    */

    $stmt = $pdo->prepare("
        UPDATE sales
        SET
            amount_paid = :amount_paid,
            payment_status = :payment_status
        WHERE id = :sale_id
    ");


    $stmt->execute([

        ":amount_paid" =>
            number_format(
                $newPaid,
                2,
                ".",
                ""
            ),

        ":payment_status" =>
            $paymentStatus,

        ":sale_id" =>
            $saleId

    ]);


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Success Message
    |--------------------------------------------------------------------------
    */

    $message =
        "Payment of KSh "
        . number_format(
            $amount,
            2
        )
        . " recorded successfully.";


    if (
        $paymentStatus === "paid"
    ) {

        $message .=
            " The sale is now fully paid.";

    } else {

        $message .=
            " Remaining balance: KSh "
            . number_format(
                $newBalance,
                2
            )
            . ".";

    }


    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    header(
        "Location: record_payment.php?id="
        . $saleId
        . "&success="
        . urlencode($message)
    );

    exit;


} catch (Throwable $e) {


    /*
    |--------------------------------------------------------------------------
    | Rollback
    |--------------------------------------------------------------------------
    */

    if (
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();

    }


    /*
    |--------------------------------------------------------------------------
    | Return User Safely
    |--------------------------------------------------------------------------
    */

    header(
        "Location: record_payment.php?id="
        . $saleId
        . "&error="
        . urlencode(
            $e->getMessage()
        )
    );

    exit;

}