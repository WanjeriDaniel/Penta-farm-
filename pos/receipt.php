<?php

require_once "../config/database.php";

$saleId = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

$sale = null;
$items = [];
$error = "";

try {

    if ($saleId <= 0) {
        throw new Exception("Invalid sale.");
    }

    /*
    |--------------------------------------------------------------------------
    | Get Sale
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            receipt_number,
            sale_date,
            subtotal,
            discount,
            fuel_charge,
            total_amount,
            payment_method,
            amount_paid,
            change_amount,
            status
        FROM sales
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ":id" => $saleId
    ]);

    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        throw new Exception("Sale not found.");
    }

    /*
    |--------------------------------------------------------------------------
    | Get Sale Items
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            si.product_id,
            si.quantity,
            si.unit_price,
            si.buying_price,
            si.subtotal,
            p.product_name,
            p.unit
        FROM sale_items si
        INNER JOIN products p
            ON si.product_id = p.id
        WHERE si.sale_id = :sale_id
        ORDER BY si.id ASC
    ");

    $stmt->execute([
        ":sale_id" => $saleId
    ]);

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $error = $e->getMessage();

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    <?= $sale
        ? "Receipt " . htmlspecialchars($sale["receipt_number"])
        : "Receipt"
    ?>
</title>

<style>

/* =========================================================
   GENERAL
   ========================================================= */

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f1f3f5;
    font-family: Arial, Helvetica, sans-serif;
    color: #000;
}

/* =========================================================
   PAGE
   ========================================================= */

.page {
    width: 100%;
    padding: 25px;
}

/* =========================================================
   BUTTONS
   ========================================================= */

.actions {
    max-width: 420px;
    margin: 0 auto 15px;
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
}

.button {
    border: none;
    text-decoration: none;
    padding: 10px 15px;
    border-radius: 7px;
    font-weight: bold;
    cursor: pointer;
}

.back {
    background: #e7ebef;
    color: #333;
}

.print {
    background: #1e3c72;
    color: white;
}

/* =========================================================
   RECEIPT
   ========================================================= */

.receipt {
    width: 100%;
    max-width: 420px;
    margin: auto;
    background: #fff;
    padding: 25px;
    box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
    color: #000;
}

/* =========================================================
   COMPANY HEADER
   ========================================================= */

.shop-header {
    text-align: center;
    border-bottom: 1px dashed #000;
    padding-bottom: 18px;
    margin-bottom: 15px;
}

.shop-name {
    font-size: 21px;
    font-weight: bold;
    color: #000;
    white-space: nowrap;
}

.shop-business {
    margin-top: 5px;
    font-size: 13px;
    font-weight: bold;
    color: #000;
}

.shop-products {
    margin-top: 3px;
    font-size: 12px;
    font-weight: bold;
    color: #000;
}

.shop-address {
    margin-top: 8px;
    font-size: 11px;
    font-weight: bold;
    color: #000;
}

.shop-phone {
    margin-top: 3px;
    font-size: 11px;
    font-weight: bold;
    color: #000;
}

.receipt-title {
    margin-top: 9px;
    font-size: 13px;
    font-weight: bold;
    color: #000;
}

/* =========================================================
   RECEIPT INFORMATION
   ========================================================= */

.receipt-meta {
    font-size: 13px;
    margin-bottom: 15px;
    color: #000;
    font-weight: bold;
}

.meta-row {
    display: flex;
    justify-content: space-between;
    margin: 6px 0;
    gap: 15px;
    color: #000;
}

/* =========================================================
   ITEMS
   ========================================================= */

.items {
    border-top: 1px dashed #000;
    border-bottom: 1px dashed #000;
    padding: 12px 0;
}

.item {
    margin-bottom: 13px;
}

.item:last-child {
    margin-bottom: 0;
}

.item-name {
    font-weight: bold;
    margin-bottom: 4px;
    color: #000;
}

.item-details {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    font-size: 13px;
    color: #000;
    font-weight: bold;
}

/* =========================================================
   SUMMARY
   ========================================================= */

.summary {
    padding-top: 12px;
    color: #000;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin: 7px 0;
    gap: 15px;
    color: #000;
    font-weight: bold;
}

.total-row {
    border-top: 2px solid #000;
    margin-top: 12px;
    padding-top: 12px;
    font-size: 19px;
    font-weight: bold;
    color: #000;
}

/* =========================================================
   PAYMENT
   ========================================================= */

.payment {
    border-top: 1px dashed #000;
    margin-top: 15px;
    padding-top: 12px;
    color: #000;
}

.payment-method {
    font-weight: bold;
    color: #000;
}

/* =========================================================
   FOOTER
   ========================================================= */

.thank-you {
    text-align: center;
    border-top: 1px dashed #000;
    margin-top: 20px;
    padding-top: 18px;
    font-weight: bold;
    color: #000;
}

.receipt-status {
    text-align: center;
    margin-top: 8px;
    font-size: 12px;
    color: #000;
    font-weight: bold;
}

/* =========================================================
   ERROR
   ========================================================= */

.error {
    max-width: 600px;
    margin: 40px auto;
    padding: 18px;
    background: #ffe5e5;
    color: #a00000;
    border-radius: 8px;
    text-align: center;
}

/* =========================================================
   PRINT VERSION - 80MM POS PRINTER
   ========================================================= */

@media print {

    @page {
        size: 80mm auto;
        margin: 0;
    }

    html,
    body {
        width: 80mm;
        margin: 0;
        padding: 0;
        background: #fff;
        color: #000;
    }

    .page {
        width: 80mm;
        margin: 0;
        padding: 0;
    }

    .actions {
        display: none !important;
    }

    .receipt {
        width: 80mm;
        max-width: 80mm;
        margin: 0;
        padding: 5mm;
        background: #fff;
        box-shadow: none;
        border: none;
        color: #000;
    }

    /* ---------------------------------------------------------
       COMPANY HEADER
       --------------------------------------------------------- */

    .shop-header {
        padding-bottom: 4mm;
        margin-bottom: 4mm;
        border-color: #000;
    }

    .shop-name {
        font-size: 16px;
        color: #000;
        font-weight: bold;
        white-space: nowrap;
    }

    .shop-business {
        font-size: 10px;
        margin-top: 1.5mm;
        color: #000;
        font-weight: bold;
    }

    .shop-products {
        font-size: 10px;
        margin-top: 1mm;
        color: #000;
        font-weight: bold;
    }

    .shop-address {
        font-size: 9px;
        margin-top: 2.5mm;
        color: #000;
        font-weight: bold;
    }

    .shop-phone {
        font-size: 9px;
        margin-top: 1mm;
        color: #000;
        font-weight: bold;
    }

    .receipt-title {
        font-size: 11px;
        margin-top: 2mm;
        color: #000;
        font-weight: bold;
    }

    /* ---------------------------------------------------------
       RECEIPT INFORMATION
       --------------------------------------------------------- */

    .receipt-meta {
        font-size: 12px;
        margin-bottom: 4mm;
        color: #000;
        font-weight: bold;
    }

    .meta-row {
        margin: 2mm 0;
        color: #000;
        font-weight: bold;
    }

    /* ---------------------------------------------------------
       ITEMS
       --------------------------------------------------------- */

    .items {
        padding: 3mm 0;
        border-color: #000;
    }

    .item {
        margin-bottom: 3mm;
    }

    .item-name {
        font-size: 12px;
        color: #000;
        font-weight: bold;
    }

    .item-details {
        font-size: 12px;
        color: #000;
        font-weight: bold;
    }

    /* ---------------------------------------------------------
       SUMMARY
       --------------------------------------------------------- */

    .summary {
        padding-top: 3mm;
        color: #000;
    }

    .summary-row {
        font-size: 12px;
        margin: 2mm 0;
        color: #000;
        font-weight: bold;
    }

    .total-row {
        font-size: 14px;
        padding-top: 3mm;
        margin-top: 3mm;
        border-color: #000;
        color: #000;
        font-weight: bold;
    }

    /* ---------------------------------------------------------
       PAYMENT
       --------------------------------------------------------- */

    .payment {
        margin-top: 4mm;
        padding-top: 3mm;
        border-color: #000;
        color: #000;
    }

    .payment-method {
        color: #000;
        font-weight: bold;
    }

    /* ---------------------------------------------------------
       FOOTER
       --------------------------------------------------------- */

    .thank-you {
        margin-top: 5mm;
        padding-top: 4mm;
        font-size: 12px;
        color: #000;
        font-weight: bold;
        border-color: #000;
    }

    .receipt-status {
        font-size: 11px;
        color: #000;
        font-weight: bold;
    }

}

</style>

</head>

<body>

<div class="page">

<?php if ($error): ?>

    <div class="error">
        <?= htmlspecialchars($error) ?>
    </div>

<?php else: ?>

    <!-- =====================================================
         ACTION BUTTONS
         ===================================================== -->

    <div class="actions">

        <a
            href="index.php"
            class="button back"
        >
            ← New Sale
        </a>

        <button
            type="button"
            class="button print"
            onclick="window.print()"
        >
            🖨️ Print Receipt
        </button>

    </div>


    <!-- =====================================================
         RECEIPT
         ===================================================== -->

    <div class="receipt">


        <!-- =================================================
             COMPANY HEADER
             ================================================= -->

        <div class="shop-header">

            <div class="shop-name">
                ⬟ PENTA FARM TRADING CO. LTD.
            </div>

            <div class="shop-business">
                Dealers in Construction Materials
            </div>

            <div class="shop-products">
                Cement • Pipes • Paints
            </div>

            <div class="shop-address">
                P.O. BOX 33102–00600, NGARA, NAIROBI
            </div>

            <div class="shop-phone">
                Cell: 0720 368 957 | 0720 941 299
            </div>

            <div class="shop-phone">
                0720 376 019
            </div>

            <div class="receipt-title">
                SALES RECEIPT
            </div>

        </div>


        <!-- =================================================
             RECEIPT INFORMATION
             ================================================= -->

        <div class="receipt-meta">

            <div class="meta-row">

                <span>
                    Receipt
                </span>

                <strong>
                    <?= htmlspecialchars(
                        $sale["receipt_number"]
                    ) ?>
                </strong>

            </div>


            <div class="meta-row">

                <span>
                    Date
                </span>

                <span>
                    <?= date(
                        "d M Y",
                        strtotime(
                            $sale["sale_date"]
                        )
                    ) ?>
                </span>

            </div>


            <div class="meta-row">

                <span>
                    Time
                </span>

                <span>
                    <?= date(
                        "H:i",
                        strtotime(
                            $sale["sale_date"]
                        )
                    ) ?>
                </span>

            </div>

        </div>


        <!-- =================================================
             ITEMS
             ================================================= -->

        <div class="items">

            <?php foreach ($items as $item): ?>

                <div class="item">

                    <div class="item-name">

                        <?= htmlspecialchars(
                            $item["product_name"]
                        ) ?>

                    </div>


                    <div class="item-details">

                        <span>

                            <?= rtrim(
                                rtrim(
                                    number_format(
                                        (float)$item["quantity"],
                                        3,
                                        ".",
                                        ""
                                    ),
                                    "0"
                                ),
                                "."
                            ) ?>

                            <?= htmlspecialchars(
                                $item["unit"] ?? ""
                            ) ?>

                        </span>


                        <strong>

                            KSh

                            <?= number_format(
                                (float)$item["subtotal"],
                                2
                            ) ?>

                        </strong>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>


        <!-- =================================================
             SUMMARY
             ================================================= -->

        <div class="summary">

            <div class="summary-row">

                <span>
                    Subtotal
                </span>

                <span>

                    KSh

                    <?= number_format(
                        (float)$sale["subtotal"],
                        2
                    ) ?>

                </span>

            </div>


            <?php if ((float)$sale["discount"] > 0): ?>

                <div class="summary-row">

                    <span>
                        Discount
                    </span>

                    <span>

                        - KSh

                        <?= number_format(
                            (float)$sale["discount"],
                            2
                        ) ?>

                    </span>

                </div>

            <?php endif; ?>
            <?php if ((float)$sale["fuel_charge"] > 0): ?>

    <div class="summary-row">

        <span>
            Delivery / Fuel
        </span>

        <span>

            KSh

            <?= number_format(
                (float)$sale["fuel_charge"],
                2
            ) ?>

        </span>

    </div>

<?php endif; ?>


            <div class="summary-row total-row">

                <span>
                    TOTAL
                </span>

                <span>

                    KSh

                    <?= number_format(
                        (float)$sale["total_amount"],
                        2
                    ) ?>

                </span>

            </div>

        </div>


        <!-- =================================================
             PAYMENT
             ================================================= -->

        <div class="payment">

            <div class="summary-row">

                <span>
                    Payment
                </span>

                <span class="payment-method">

                    <?= htmlspecialchars(
                        $sale["payment_method"]
                    ) ?>

                </span>

            </div>


            <div class="summary-row">

                <span>
                    Amount Paid
                </span>

                <span>

                    KSh

                    <?= number_format(
                        (float)$sale["amount_paid"],
                        2
                    ) ?>

                </span>

            </div>


            <?php if ((float)$sale["change_amount"] > 0): ?>

                <div class="summary-row">

                    <span>
                        Change
                    </span>

                    <span>

                        KSh

                        <?= number_format(
                            (float)$sale["change_amount"],
                            2
                        ) ?>

                    </span>

                </div>

            <?php endif; ?>

        </div>


        <!-- =================================================
             FOOTER
             ================================================= -->

        <div class="thank-you">

            Thank you for shopping with Penta Farm! 🙏

        </div>


        <div class="receipt-status">

            <?= ucfirst(
                htmlspecialchars(
                    $sale["status"]
                )
            ) ?>

        </div>


    </div>

<?php endif; ?>

</div>

</body>

</html>

