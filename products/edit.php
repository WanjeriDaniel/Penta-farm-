<?php

require_once "../config/database.php";

$message = "";
$message_type = "";
$product = null;


/*
|--------------------------------------------------------------------------
| CHECK PRODUCT ID
|--------------------------------------------------------------------------
*/

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {

    die("Invalid product.");

}

$product_id = (int) $_GET["id"];


/*
|--------------------------------------------------------------------------
| FETCH PRODUCT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM products
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ":id" => $product_id
]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$product) {

    die("Product not found.");

}


/*
|--------------------------------------------------------------------------
| UPDATE PRODUCT
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /*
    | Get submitted values
    */

    $product_name = trim($_POST["product_name"] ?? "");

    $unit = trim($_POST["unit"] ?? "");

    $purchase_unit = trim($_POST["purchase_unit"] ?? "");

    $purchase_conversion = (float) ($_POST["purchase_conversion"] ?? 1);

    $buying_price = ($_POST["buying_price"] !== "")
        ? (float) $_POST["buying_price"]
        : null;

    $selling_price = ($_POST["selling_price"] !== "")
        ? (float) $_POST["selling_price"]
        : null;

    $stock_quantity = (float) ($_POST["stock_quantity"] ?? 0);

    $low_stock_level = (float) ($_POST["low_stock_level"] ?? 5);

    $minimum_sale_quantity = (float) ($_POST["minimum_sale_quantity"] ?? 1);

    $quantity_step = (float) ($_POST["quantity_step"] ?? 1);

    $is_active = isset($_POST["is_active"]) ? 1 : 0;


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($product_name === "") {

        $message = "Product name cannot be empty.";
        $message_type = "error";

    } elseif ($purchase_conversion <= 0) {

        $message = "Purchase conversion must be greater than zero.";
        $message_type = "error";

    } elseif ($buying_price !== null && $buying_price < 0) {

        $message = "Buying price cannot be negative.";
        $message_type = "error";

    } elseif ($selling_price !== null && $selling_price < 0) {

        $message = "Selling price cannot be negative.";
        $message_type = "error";

    } elseif ($stock_quantity < 0) {

        $message = "Stock quantity cannot be negative.";
        $message_type = "error";

    } elseif ($low_stock_level < 0) {

        $message = "Low stock level cannot be negative.";
        $message_type = "error";

    } elseif ($minimum_sale_quantity <= 0) {

        $message = "Minimum sale quantity must be greater than zero.";
        $message_type = "error";

    } elseif ($quantity_step <= 0) {

        $message = "Quantity step must be greater than zero.";
        $message_type = "error";

    } else {


        try {

            /*
            |--------------------------------------------------------------------------
            | Check duplicate product name
            |--------------------------------------------------------------------------
            */

            $duplicate_stmt = $pdo->prepare("
                SELECT id
                FROM products
                WHERE product_name = :product_name
                AND id != :id
                LIMIT 1
            ");

            $duplicate_stmt->execute([
                ":product_name" => $product_name,
                ":id" => $product_id
            ]);

            $duplicate = $duplicate_stmt->fetch(PDO::FETCH_ASSOC);


            if ($duplicate) {

                $message = "Another product with this name already exists.";
                $message_type = "error";

            } else {


                /*
                |--------------------------------------------------------------------------
                | START TRANSACTION
                |--------------------------------------------------------------------------
                */

                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | Check whether prices changed
                |--------------------------------------------------------------------------
                */

                $old_buying_price = $product["buying_price"];
                $old_selling_price = $product["selling_price"];


                $buying_changed =
                    (float) ($old_buying_price ?? 0)
                    !==
                    (float) ($buying_price ?? 0);


                $selling_changed =
                    (float) ($old_selling_price ?? 0)
                    !==
                    (float) ($selling_price ?? 0);


                /*
                |--------------------------------------------------------------------------
                | Save Price History
                |--------------------------------------------------------------------------
                */

                if ($buying_changed || $selling_changed) {

                    /*
                    | price_history does not allow NULL prices,
                    | so use 0 when the current product price is NULL.
                    */

                    $history_stmt = $pdo->prepare("
                        INSERT INTO price_history
                        (
                            product_id,
                            old_buying_price,
                            new_buying_price,
                            old_selling_price,
                            new_selling_price
                        )
                        VALUES
                        (
                            :product_id,
                            :old_buying,
                            :new_buying,
                            :old_selling,
                            :new_selling
                        )
                    ");

                    $history_stmt->execute([
                        ":product_id" => $product_id,

                        ":old_buying" =>
                            (float) ($old_buying_price ?? 0),

                        ":new_buying" =>
                            (float) ($buying_price ?? 0),

                        ":old_selling" =>
                            (float) ($old_selling_price ?? 0),

                        ":new_selling" =>
                            (float) ($selling_price ?? 0)
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | UPDATE PRODUCT
                |--------------------------------------------------------------------------
                */

                $update_stmt = $pdo->prepare("
                    UPDATE products
                    SET
                        product_name = :product_name,
                        unit = :unit,
                        purchase_unit = :purchase_unit,
                        purchase_conversion = :purchase_conversion,
                        buying_price = :buying_price,
                        selling_price = :selling_price,
                        stock_quantity = :stock_quantity,
                        low_stock_level = :low_stock_level,
                        minimum_sale_quantity = :minimum_sale_quantity,
                        quantity_step = :quantity_step,
                        is_active = :is_active
                    WHERE id = :id
                ");


                $update_stmt->execute([

                    ":product_name" => $product_name,

                    ":unit" =>
                        ($unit !== "") ? $unit : null,

                    ":purchase_unit" =>
                        ($purchase_unit !== "")
                            ? $purchase_unit
                            : null,

                    ":purchase_conversion" =>
                        $purchase_conversion,

                    ":buying_price" =>
                        $buying_price,

                    ":selling_price" =>
                        $selling_price,

                    ":stock_quantity" =>
                        $stock_quantity,

                    ":low_stock_level" =>
                        $low_stock_level,

                    ":minimum_sale_quantity" =>
                        $minimum_sale_quantity,

                    ":quantity_step" =>
                        $quantity_step,

                    ":is_active" =>
                        $is_active,

                    ":id" =>
                        $product_id
                ]);


                /*
                |--------------------------------------------------------------------------
                | COMMIT
                |--------------------------------------------------------------------------
                */

                $pdo->commit();


                /*
                |--------------------------------------------------------------------------
                | SUCCESS
                |--------------------------------------------------------------------------
                */

                $message =
                    "Product updated successfully!";

                $message_type =
                    "success";


                /*
                |--------------------------------------------------------------------------
                | REFRESH PRODUCT
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT *
                    FROM products
                    WHERE id = :id
                    LIMIT 1
                ");

                $stmt->execute([
                    ":id" => $product_id
                ]);

                $product =
                    $stmt->fetch(PDO::FETCH_ASSOC);

            }

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {

                $pdo->rollBack();

            }

            $message =
                "Update failed. Please try again.";

            $message_type =
                "error";
        }
    }
}


/*
|--------------------------------------------------------------------------
| DELETE PRODUCT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    isset($_POST["delete_product"])
) {

    try {

        /*
        |--------------------------------------------------------------------------
        | START TRANSACTION
        |--------------------------------------------------------------------------
        */

        $pdo->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | Make sure product exists
        |--------------------------------------------------------------------------
        */

        $check_stmt = $pdo->prepare("
            SELECT id, product_name
            FROM products
            WHERE id = :id
            LIMIT 1
        ");

        $check_stmt->execute([
            ":id" => $product_id
        ]);

        $delete_product =
            $check_stmt->fetch(PDO::FETCH_ASSOC);


        if (!$delete_product) {

            $pdo->rollBack();

            die("Product not found.");

        }


        /*
        |--------------------------------------------------------------------------
        | DELETE PRICE HISTORY
        |--------------------------------------------------------------------------
        */

        $history_delete_stmt = $pdo->prepare("
            DELETE FROM price_history
            WHERE product_id = :product_id
        ");

        $history_delete_stmt->execute([
            ":product_id" => $product_id
        ]);


        /*
        |--------------------------------------------------------------------------
        | DELETE PRODUCT
        |--------------------------------------------------------------------------
        */

        $product_delete_stmt = $pdo->prepare("
            DELETE FROM products
            WHERE id = :id
        ");

        $product_delete_stmt->execute([
            ":id" => $product_id
        ]);


        /*
        |--------------------------------------------------------------------------
        | COMMIT
        |--------------------------------------------------------------------------
        */

        $pdo->commit();


        /*
        |--------------------------------------------------------------------------
        | RETURN TO SEARCH
        |--------------------------------------------------------------------------
        */

        header("Location: search.php");

        exit;


    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {

            $pdo->rollBack();

        }

        $message =
            "Product could not be deleted. "
            . "Please check whether it is being used elsewhere.";

        $message_type =
            "error";
    }
}

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Penta Farm | Manage Product</title>


<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}


body {

    font-family: Arial, Helvetica, sans-serif;

    background: #f4f7f6;

    color: #333;

    min-height: 100vh;
}


/* HEADER */

.header {

    background: linear-gradient(135deg, #176b3a, #249653);

    color: white;

    padding: 20px 40px;

    box-shadow: 0 3px 10px rgba(0,0,0,0.12);
}


.header h1 {

    font-size: 26px;

    margin-bottom: 4px;
}


.header p {

    font-size: 14px;

    opacity: 0.9;
}


/* CONTAINER */

.container {

    width: 100%;

    max-width: 850px;

    margin: 40px auto;

    padding: 0 20px;
}


/* CARD */

.card {

    background: white;

    padding: 35px;

    border-radius: 12px;

    box-shadow: 0 5px 20px rgba(0,0,0,0.08);

    margin-bottom: 25px;
}


.card h2 {

    color: #176b3a;

    font-size: 24px;

    margin-bottom: 8px;
}


.description {

    color: #777;

    font-size: 14px;

    margin-bottom: 25px;
}


/* MESSAGE */

.message {

    padding: 13px 15px;

    border-radius: 7px;

    margin-bottom: 20px;

    font-size: 14px;
}


.success {

    background: #e8f7ee;

    color: #176b3a;

    border: 1px solid #b8e5c9;
}


.error {

    background: #fff0f0;

    color: #c62828;

    border: 1px solid #f0bcbc;
}


/* PRODUCT ID */

.product-id {

    background: #f0f5f2;

    padding: 12px 15px;

    border-radius: 7px;

    margin-bottom: 25px;

    font-size: 13px;

    color: #666;
}


/* FORM */

.form-group {

    margin-bottom: 20px;
}


.form-row {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 20px;
}


label {

    display: block;

    font-weight: bold;

    margin-bottom: 8px;

    color: #444;

    font-size: 14px;
}


input,
select {

    width: 100%;

    padding: 13px 14px;

    border: 1px solid #d5d5d5;

    border-radius: 7px;

    font-size: 15px;

    outline: none;
}


input:focus,
select:focus {

    border-color: #249653;

    box-shadow: 0 0 0 3px rgba(36,150,83,0.12);
}


/* UPDATE BUTTON */

.update-button {

    width: 100%;

    padding: 14px;

    border: none;

    border-radius: 7px;

    background: #176b3a;

    color: white;

    font-size: 16px;

    font-weight: bold;

    cursor: pointer;

    transition: 0.2s;
}


.update-button:hover {

    background: #12552e;

    transform: translateY(-1px);
}


/* DANGER ZONE */

.danger-zone {

    background: #fff7f7;

    border: 1px solid #f1c4c4;

    border-radius: 12px;

    padding: 25px;

    margin-top: 30px;
}


.danger-zone h3 {

    color: #c62828;

    margin-bottom: 8px;
}


.danger-zone p {

    color: #777;

    font-size: 14px;

    margin-bottom: 18px;
}


.delete-button {

    padding: 12px 20px;

    border: none;

    border-radius: 7px;

    background: #c62828;

    color: white;

    font-size: 14px;

    font-weight: bold;

    cursor: pointer;
}


.delete-button:hover {

    background: #a91f1f;
}


/* BACK */

.back-link {

    display: inline-block;

    margin-top: 5px;

    color: #176b3a;

    text-decoration: none;

    font-size: 14px;

    font-weight: bold;
}


.back-link:hover {

    text-decoration: underline;
}


/* MOBILE */

@media (max-width: 650px) {

    .header {

        padding: 18px 20px;
    }


    .container {

        margin: 30px auto;

        padding: 0 15px;
    }


    .card {

        padding: 25px 20px;
    }


    .form-row {

        grid-template-columns: 1fr;

        gap: 0;
    }

}

</style>

</head>


<body>


<header class="header">

    <h1>🌱 Penta Farm</h1>

    <p>Inventory Management System</p>

</header>



<main class="container">


<div class="card">


    <h2>Manage Product</h2>

    <p class="description">

        Update product information, prices, stock settings, or remove the product.

    </p>


    <?php if ($message !== ""): ?>

        <div class="message <?php echo $message_type; ?>">

            <?php echo htmlspecialchars($message); ?>

        </div>

    <?php endif; ?>


    <div class="product-id">

        Product ID:

        <strong>

            <?php echo htmlspecialchars($product["id"]); ?>

        </strong>

    </div>


    <form method="POST">


        <!-- PRODUCT NAME -->

        <div class="form-group">

            <label for="product_name">

                Product Name

            </label>

            <input

                type="text"

                id="product_name"

                name="product_name"

                value="<?php echo htmlspecialchars($product["product_name"]); ?>"

                required

            >

        </div>



        <!-- UNIT -->

        <div class="form-row">


            <div class="form-group">

                <label for="unit">

                    Selling Unit

                </label>

                <input

                    type="text"

                    id="unit"

                    name="unit"

                    value="<?php echo htmlspecialchars($product["unit"] ?? ""); ?>"

                    placeholder="e.g. pc, kg, box"

                >

            </div>



            <!-- PURCHASE UNIT -->

            <div class="form-group">

                <label for="purchase_unit">

                    Purchase Unit

                </label>

                <input

                    type="text"

                    id="purchase_unit"

                    name="purchase_unit"

                    value="<?php echo htmlspecialchars($product["purchase_unit"] ?? ""); ?>"

                    placeholder="e.g. carton, box, piece"

                >

            </div>


        </div>



        <!-- PURCHASE CONVERSION -->

        <div class="form-group">

            <label for="purchase_conversion">

                Purchase Conversion

            </label>

            <input

                type="number"

                id="purchase_conversion"

                name="purchase_conversion"

                value="<?php echo htmlspecialchars($product["purchase_conversion"]); ?>"

                step="0.001"

                min="0.001"

                required

            >

        </div>



        <!-- PRICES -->

        <div class="form-row">


            <div class="form-group">

                <label for="buying_price">

                    Buying Price (KSh)

                </label>

                <input

                    type="number"

                    id="buying_price"

                    name="buying_price"

                    value="<?php echo htmlspecialchars($product["buying_price"] ?? ""); ?>"

                    step="0.01"

                    min="0"

                >

            </div>



            <div class="form-group">

                <label for="selling_price">

                    Selling Price (KSh)

                </label>

                <input

                    type="number"

                    id="selling_price"

                    name="selling_price"

                    value="<?php echo htmlspecialchars($product["selling_price"] ?? ""); ?>"

                    step="0.01"

                    min="0"

                >

            </div>


        </div>



        <!-- STOCK -->

        <div class="form-row">


            <div class="form-group">

                <label for="stock_quantity">

                    Stock Quantity

                </label>

                <input

                    type="number"

                    id="stock_quantity"

                    name="stock_quantity"

                    value="<?php echo htmlspecialchars($product["stock_quantity"]); ?>"

                    step="0.001"

                    min="0"

                >

            </div>



            <div class="form-group">

                <label for="low_stock_level">

                    Low Stock Level

                </label>

                <input

                    type="number"

                    id="low_stock_level"

                    name="low_stock_level"

                    value="<?php echo htmlspecialchars($product["low_stock_level"]); ?>"

                    step="0.01"

                    min="0"

                >

            </div>


        </div>



        <!-- SALE SETTINGS -->

        <div class="form-row">


            <div class="form-group">

                <label for="minimum_sale_quantity">

                    Minimum Sale Quantity

                </label>

                <input

                    type="number"

                    id="minimum_sale_quantity"

                    name="minimum_sale_quantity"

                    value="<?php echo htmlspecialchars($product["minimum_sale_quantity"]); ?>"

                    step="0.001"

                    min="0.001"

                >

            </div>



            <div class="form-group">

                <label for="quantity_step">

                    Quantity Step

                </label>

                <input

                    type="number"

                    id="quantity_step"

                    name="quantity_step"

                    value="<?php echo htmlspecialchars($product["quantity_step"]); ?>"

                    step="0.001"

                    min="0.001"

                >

            </div>


        </div>



        <!-- ACTIVE -->

        <div class="form-group">

            <label for="is_active">

                Product Status

            </label>

            <select

                id="is_active"

                name="is_active"

            >

                <option

                    value="1"

                    <?php echo $product["is_active"] ? "selected" : ""; ?>

                >

                    Active

                </option>

                <option

                    value="0"

                    <?php echo !$product["is_active"] ? "selected" : ""; ?>

                >

                    Inactive

                </option>

            </select>

        </div>



        <!-- UPDATE -->

        <button

            type="submit"

            class="update-button"

        >

            💾 Update Product

        </button>


    </form>



    <!-- DELETE -->

    <div class="danger-zone">

        <h3>⚠️ Danger Zone</h3>

        <p>

            Deleting this product will also remove its price history.

            This action cannot be undone.

        </p>


        <form

            method="POST"

            onsubmit="return confirm('Are you sure you want to permanently delete this product? This action cannot be undone.');"

        >

            <input

                type="hidden"

                name="delete_product"

                value="1"

            >


            <button

                type="submit"

                class="delete-button"

            >

                🗑️ Delete Product

            </button>

        </form>

    </div>


</div>



<a

    href="search.php"

    class="back-link"

>

    ← Back to Product Search

</a>


</main>


</body>

</html>

