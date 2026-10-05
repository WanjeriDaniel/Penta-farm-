<?php

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| Get Sale ID
|--------------------------------------------------------------------------
*/

$saleId = (int)($_GET["id"] ?? 0);

$sale = null;

$success = trim($_GET["success"] ?? "");

$error = trim($_GET["error"] ?? "");


if ($saleId <= 0) {

    $error = "Invalid sale ID.";

}


/*
|--------------------------------------------------------------------------
| Load Sale
|--------------------------------------------------------------------------
*/

try {

    if (!$error) {

        $sql = "
            SELECT
                s.id,
                s.receipt_number,
                s.sale_type,
                s.customer_name,
                s.customer_phone,
                s.sale_date,
                s.due_date,
                s.total_amount,
                s.amount_paid AS current_amount_paid,
                s.payment_status

            FROM sales s

            WHERE
                s.id = :sale_id
                AND s.sale_type IN ('invoice', 'paylater')
                AND s.status = 'completed'

            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ":sale_id" => $saleId
        ]);

        $sale = $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$sale) {

            $error = "Credit sale not found.";

        }

    }

} catch (PDOException $e) {

    $error = $e->getMessage();

}


/*
|--------------------------------------------------------------------------
| Calculate Current Balance
|--------------------------------------------------------------------------
*/

if ($sale) {

    $total =
        (float)$sale["total_amount"];

    /*
    |--------------------------------------------------------------------------
    | amount_paid is the cumulative amount paid.
    |--------------------------------------------------------------------------
    */

    $paid =
        (float)$sale["current_amount_paid"];

    $balance =
        max(
            0,
            round(
                $total - $paid,
                2
            )
        );

} else {

    $total = 0;

    $paid = 0;

    $balance = 0;

}


/*
|--------------------------------------------------------------------------
| Load Payment History
|--------------------------------------------------------------------------
*/

$payments = [];


if ($sale) {

    try {

        $stmt = $pdo->prepare("
            SELECT
                id,
                amount,
                payment_method,
                payment_date,
                notes
            FROM sale_payments
            WHERE sale_id = :sale_id
            ORDER BY
                payment_date DESC,
                id DESC
        ");

        $stmt->execute([
            ":sale_id" => $saleId
        ]);

        $payments =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        $error = $e->getMessage();

    }

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

<title>Record Payment - Penta Farm</title>


<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    background: #f4f6f9;

    font-family: Arial, sans-serif;

    color: #222;
}

.container {

    width: 96%;

    max-width: 1100px;

    margin: 25px auto;
}


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

.header {

    background: linear-gradient(
        135deg,
        #1e3c72,
        #2a5298
    );

    color: white;

    padding: 25px;

    border-radius: 14px;

    margin-bottom: 20px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;
}

.header h1 {

    margin: 0 0 6px;
}

.header p {

    margin: 0;

    opacity: .9;
}

.header-actions {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}

.button {

    display: inline-block;

    text-decoration: none;

    padding: 10px 15px;

    border-radius: 8px;

    font-weight: bold;
}

.btn-light {

    background: white;

    color: #1e3c72;
}

.btn-dark {

    background: rgba(
        255,
        255,
        255,
        .15
    );

    color: white;

    border: 1px solid
        rgba(
            255,
            255,
            255,
            .3
        );
}


/*
|--------------------------------------------------------------------------
| Cards
|--------------------------------------------------------------------------
*/

.card {

    background: white;

    padding: 22px;

    border-radius: 14px;

    box-shadow:
        0 4px 15px
        rgba(0,0,0,.06);

    margin-bottom: 20px;
}

.card-title {

    font-size: 18px;

    font-weight: bold;

    color: #1e3c72;

    margin-bottom: 18px;
}


/*
|--------------------------------------------------------------------------
| Sale Information
|--------------------------------------------------------------------------
*/

.sale-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;
}

.info-box {

    background: #f7f9fc;

    padding: 15px;

    border-radius: 10px;
}

.info-label {

    font-size: 12px;

    color: #777;

    margin-bottom: 5px;
}

.info-value {

    font-size: 16px;

    font-weight: bold;
}

.balance-box {

    background: #fff2f2;

    border: 1px solid #ffd4d4;

    color: #a00000;
}

.paid-box {

    background: #eaf7ee;

    border: 1px solid #ccebd5;

    color: #16702b;
}


/*
|--------------------------------------------------------------------------
| Payment Form
|--------------------------------------------------------------------------
*/

.form-grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 15px;
}

.form-group {

    margin-bottom: 5px;
}

.form-group.full {

    grid-column: 1 / -1;
}

.form-group label {

    display: block;

    font-size: 13px;

    font-weight: bold;

    margin-bottom: 7px;
}

.form-group input,
.form-group select,
.form-group textarea {

    width: 100%;

    padding: 12px;

    border: 1px solid #ccc;

    border-radius: 8px;

    font-size: 14px;

    font-family: inherit;
}

.form-group textarea {

    min-height: 90px;

    resize: vertical;
}

.submit-btn {

    border: none;

    background: #1e3c72;

    color: white;

    padding: 13px 22px;

    border-radius: 8px;

    font-weight: bold;

    cursor: pointer;

    font-size: 14px;
}

.submit-btn:hover {

    background: #16305c;
}


/*
|--------------------------------------------------------------------------
| Payment History
|--------------------------------------------------------------------------
*/

.table-wrapper {

    overflow-x: auto;
}

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 600px;
}

th {

    text-align: left;

    background: #f5f6f8;

    color: #555;

    padding: 12px;

    font-size: 12px;

    text-transform: uppercase;
}

td {

    padding: 12px;

    border-bottom: 1px solid #eee;

    font-size: 14px;
}

.amount {

    font-weight: bold;

    color: #16702b;
}

.empty {

    padding: 35px;

    text-align: center;

    color: #777;
}


/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

.error {

    background: #ffe5e5;

    color: #a00000;

    padding: 15px;

    border-radius: 8px;

    margin-bottom: 20px;
}

.notice {

    background: #eaf7ee;

    color: #16702b;

    padding: 15px;

    border-radius: 8px;

    margin-bottom: 20px;
}


/*
|--------------------------------------------------------------------------
| Mobile
|--------------------------------------------------------------------------
*/

@media (max-width: 800px) {

    .sale-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }

}

@media (max-width: 600px) {

    .header {

        flex-direction: column;

        align-items: flex-start;
    }

    .sale-grid,
    .form-grid {

        grid-template-columns: 1fr;
    }

    .form-group.full {

        grid-column: auto;
    }

}

</style>

</head>


<body>


<div class="container">


    <!-- HEADER -->

    <div class="header">

        <div>

            <h1>
                💰 Record Payment
            </h1>

            <p>
                Record a payment against an outstanding customer balance.
            </p>

        </div>


        <div class="header-actions">

            <a
                href="outstanding.php"
                class="button btn-light"
            >
                💳 Outstanding
            </a>


            <a
                href="history.php"
                class="button btn-dark"
            >
                🧾 Sales History
            </a>

        </div>

    </div>


    <!-- SUCCESS MESSAGE -->

    <?php if ($success): ?>

        <div class="notice">

            <?= htmlspecialchars($success) ?>

        </div>

    <?php endif; ?>


    <!-- ERROR MESSAGE -->

    <?php if ($error): ?>

        <div class="error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <?php if ($sale): ?>


        <!-- SALE INFORMATION -->

        <div class="card">

            <div class="card-title">
                Sale Information
            </div>


            <div class="sale-grid">


                <div class="info-box">

                    <div class="info-label">
                        Receipt
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $sale["receipt_number"]
                            ?: "—"
                        ) ?>

                    </div>

                </div>


                <div class="info-box">

                    <div class="info-label">
                        Customer
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $sale["customer_name"]
                            ?: "Unknown Customer"
                        ) ?>

                    </div>

                </div>


                <div class="info-box">

                    <div class="info-label">
                        Sale Type
                    </div>

                    <div class="info-value">

                        <?= $sale["sale_type"]
                            === "paylater"
                            ? "Pay Later"
                            : "Invoice"
                        ?>

                    </div>

                </div>


                <div class="info-box paid-box">

                    <div class="info-label">
                        Amount Paid
                    </div>

                    <div class="info-value">

                        KSh
                        <?= number_format(
                            $paid,
                            2
                        ) ?>

                    </div>

                </div>


                <div class="info-box">

                    <div class="info-label">
                        Total Amount
                    </div>

                    <div class="info-value">

                        KSh
                        <?= number_format(
                            $total,
                            2
                        ) ?>

                    </div>

                </div>


                <div class="info-box balance-box">

                    <div class="info-label">
                        Outstanding Balance
                    </div>

                    <div class="info-value">

                        KSh
                        <?= number_format(
                            $balance,
                            2
                        ) ?>

                    </div>

                </div>


                <div class="info-box">

                    <div class="info-label">
                        Sale Date
                    </div>

                    <div class="info-value">

                        <?= date(
                            "d M Y",
                            strtotime(
                                $sale["sale_date"]
                            )
                        ) ?>

                    </div>

                </div>


                <div class="info-box">

                    <div class="info-label">
                        Due Date
                    </div>

                    <div class="info-value">

                        <?= $sale["due_date"]
                            ? date(
                                "d M Y",
                                strtotime(
                                    $sale["due_date"]
                                )
                            )
                            : "—"
                        ?>

                    </div>

                </div>


            </div>

        </div>


        <?php if ($balance > 0): ?>


            <!-- PAYMENT FORM -->

            <div class="card">

                <div class="card-title">
                    New Payment
                </div>


                <form
                    method="POST"
                    action="save_payment.php"
                    onsubmit="return validatePayment();"
                >


                    <input
                        type="hidden"
                        name="sale_id"
                        value="<?= $saleId ?>"
                    >


                    <div class="form-grid">


                        <div class="form-group">

                            <label>
                                Payment Amount
                            </label>

                            <input
                                type="number"
                                name="amount"
                                id="paymentAmount"
                                min="0.01"
                                max="<?= htmlspecialchars(
                                    number_format(
                                        $balance,
                                        2,
                                        ".",
                                        ""
                                    )
                                ) ?>"
                                step="0.01"
                                required
                                placeholder="Enter amount"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Payment Method
                            </label>

                            <select
                                name="payment_method"
                                required
                            >

                                <option value="">
                                    Select payment method
                                </option>

                                <option value="Cash">
                                    Cash
                                </option>

                                <option value="M-Pesa">
                                    M-Pesa
                                </option>

                                <option value="Bank">
                                    Bank
                                </option>

                            </select>

                        </div>


                        <div class="form-group">

                            <label>
                                Payment Date
                            </label>

                            <input
                                type="date"
                                name="payment_date"
                                value="<?= date(
                                    "Y-m-d"
                                ) ?>"
                                required
                            >

                        </div>


                        <div class="form-group full">

                            <label>
                                Notes
                            </label>

                            <textarea
                                name="notes"
                                maxlength="255"
                                placeholder="Optional payment notes, M-Pesa code, bank reference, etc."
                            ></textarea>

                        </div>


                        <div class="form-group full">

                            <button
                                type="submit"
                                class="submit-btn"
                            >
                                💰 Save Payment
                            </button>

                        </div>


                    </div>

                </form>

            </div>


        <?php else: ?>


            <!-- FULLY PAID -->

            <div class="card">

                <div class="notice">

                    ✅ This sale is fully paid.
                    There is no outstanding balance.

                </div>

            </div>


        <?php endif; ?>


        <!-- PAYMENT HISTORY -->

        <div class="card">

            <div class="card-title">
                Payment History
            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Date
                            </th>

                            <th>
                                Amount
                            </th>

                            <th>
                                Method
                            </th>

                            <th>
                                Notes
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (empty($payments)): ?>


                        <tr>

                            <td
                                colspan="4"
                                class="empty"
                            >

                                No additional payments recorded yet.

                            </td>

                        </tr>


                    <?php else: ?>


                        <?php foreach (
                            $payments
                            as $payment
                        ): ?>


                            <tr>


                                <td>

                                    <?= date(
                                        "d M Y",
                                        strtotime(
                                            $payment[
                                                "payment_date"
                                            ]
                                        )
                                    ) ?>

                                </td>


                                <td class="amount">

                                    KSh
                                    <?= number_format(
                                        (float)
                                        $payment[
                                            "amount"
                                        ],
                                        2
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $payment[
                                            "payment_method"
                                        ]
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $payment[
                                            "notes"
                                        ]
                                        ?: "—"
                                    ) ?>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php endif; ?>


                    </tbody>

                </table>

            </div>

        </div>


    <?php endif; ?>


</div>


<script>

function validatePayment() {

    const amount =
        parseFloat(
            document.getElementById(
                "paymentAmount"
            )?.value || 0
        );


    const balance =
        <?= json_encode(
            $balance
        ) ?>;


    if (amount <= 0) {

        alert(
            "Please enter a valid payment amount."
        );

        return false;

    }


    if (
        amount >
        balance
    ) {

        alert(
            "Payment cannot be greater than the outstanding balance."
        );

        return false;

    }


    return confirm(
        "Record this payment of KSh "
        + amount.toFixed(2)
        + "?"
    );

}

</script>


</body>

</html>