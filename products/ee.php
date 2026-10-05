<?php

require_once "../config/database.php";

$message = "";
$message_type = "";
$product = null;

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    die("Invalid product.");
}

$product_id = (int) $_GET["id"];


/*
|--------------------------------------------------------------------------
| Fetch Product
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM products
    WHERE id = :id
");

$stmt->execute([
    ":id" => $product_id
]);

$product = $stmt->fetch();

if (!$product) {
    die("Product not found.");
}


/*
|--------------------------------------------------------------------------
| Update Product
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $new_buying_price = (float) $_POST["buying_price"];
    $new_selling_price = (float) $_POST["selling_price"];

    if ($new_buying_price < 0 || $new_selling_price < 0) {

        $message = "Prices cannot be negative.";
        $message_type = "error";

    } else {

        try {

            $pdo->beginTransaction();


            /*
            | Save price history
            */

            $history_sql = "
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
            ";

            $history_stmt = $pdo->prepare($history_sql);

            $history_stmt->execute([
                ":product_id" => $product_id,
                ":old_buying" => $product["buying_price"],
                ":new_buying" => $new_buying_price,
                ":old_selling" => $product["selling_price"],
                ":new_selling" => $new_selling_price
            ]);


            /*
            | Update current product price
            */

            $update_sql = "
                UPDATE products
                SET
                    buying_price = :buying_price,
                    selling_price = :selling_price
                WHERE id = :id
            ";

            $update_stmt = $pdo->prepare($update_sql);

            $update_stmt->execute([
                ":buying_price" => $new_buying_price,
                ":selling_price" => $new_selling_price,
                ":id" => $product_id
            ]);


            /*
            | Complete both operations
            */

            $pdo->commit();

            $message = "Product price updated successfully!";
            $message_type = "success";


            /*
            | Refresh product information
            */

            $stmt = $pdo->prepare("
                SELECT *
                FROM products
                WHERE id = :id
            ");

            $stmt->execute([
                ":id" => $product_id
            ]);

            $product = $stmt->fetch();

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = "Update failed. Please try again.";
            $message_type = "error";
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Penta Farm | Update Price</title>


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


        /* Header */

        .header {
            background: linear-gradient(135deg, #176b3a, #249653);
            color: white;
            padding: 20px 40px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.12);
        }

        .header h1 {
            font-size: 26px;
            margin-bottom: 4px;
        }

        .header p {
            font-size: 14px;
            opacity: 0.9;
        }


        /* Main */

        .container {
            width: 100%;
            max-width: 650px;
            margin: 45px auto;
            padding: 0 20px;
        }


        /* Card */

        .card {
            background: white;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
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


        /* Product Information */

        .product-info {
            background: #f0f5f2;
            border-left: 4px solid #176b3a;
            padding: 16px;
            border-radius: 7px;
            margin-bottom: 25px;
        }

        .product-info h3 {
            color: #176b3a;
            font-size: 18px;
            margin-bottom: 5px;
        }

        .product-info p {
            color: #666;
            font-size: 14px;
        }


        /* Message */

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


        /* Form */

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: #444;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #d5d5d5;
            border-radius: 7px;
            font-size: 15px;
            outline: none;
            transition: 0.2s;
        }

        input:focus {
            border-color: #249653;
            box-shadow: 0 0 0 3px rgba(36, 150, 83, 0.12);
        }


        /* Current Price */

        .current-price {
            color: #888;
            font-size: 12px;
            margin-top: 6px;
        }


        /* Button */

        .button {
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

        .button:hover {
            background: #12552e;
            transform: translateY(-1px);
        }


        /* Back */

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #176b3a;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
        }

        .back-link:hover {
            text-decoration: underline;
        }


        /* Mobile */

        @media (max-width: 600px) {

            .header {
                padding: 18px 20px;
            }

            .header h1 {
                font-size: 22px;
            }

            .container {
                margin: 30px auto;
                padding: 0 15px;
            }

            .card {
                padding: 25px 20px;
            }

            .card h2 {
                font-size: 21px;
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


        <h2>Update Product Price</h2>

        <p class="description">
            Update the current buying and selling prices for this product.
        </p>


        <?php if ($message !== ""): ?>

            <div class="message <?php echo $message_type; ?>">

                <?php echo htmlspecialchars($message); ?>

            </div>

        <?php endif; ?>


        <div class="product-info">

            <h3>
                <?php echo htmlspecialchars($product["product_name"]); ?>
            </h3>

            <p>
                Unit:
                <strong>
                    <?php echo htmlspecialchars($product["unit"]); ?>
                </strong>
            </p>

        </div>


        <form method="POST">


            <div class="form-group">

                <label for="buying_price">
                    Buying Price (KSh)
                </label>

                <input
                    type="number"
                    id="buying_price"
                    name="buying_price"
                    value="<?php echo htmlspecialchars($product["buying_price"]); ?>"
                    step="0.01"
                    min="0"
                    required
                >

                <p class="current-price">
                    Current price:
                    KSh <?php echo number_format($product["buying_price"], 2); ?>
                </p>

            </div>


            <div class="form-group">

                <label for="selling_price">
                    Selling Price (KSh)
                </label>

                <input
                    type="number"
                    id="selling_price"
                    name="selling_price"
                    value="<?php echo htmlspecialchars($product["selling_price"]); ?>"
                    step="0.01"
                    min="0"
                    required
                >

                <p class="current-price">
                    Current price:
                    KSh <?php echo number_format($product["selling_price"], 2); ?>
                </p>

            </div>


            <button type="submit" class="button">
                💾 Update Price
            </button>


        </form>


        <a href="#" class="back-link">
            ← Back to Products
        </a>


    </div>


</main>


</body>

</html>