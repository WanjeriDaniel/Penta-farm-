<?php

require_once "../config/database.php";

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $product_name = trim($_POST["product_name"]);
    $unit = trim($_POST["unit"]);
    $buying_price = (float) $_POST["buying_price"];
    $selling_price = (float) $_POST["selling_price"];

    if ($product_name === "" || $unit === "") {
        $message = "Please fill in all required fields.";
        $message_type = "error";

    } elseif ($buying_price < 0 || $selling_price < 0) {
        $message = "Prices cannot be negative.";
        $message_type = "error";

    } else {

        try {

            $sql = "INSERT INTO products 
                    (product_name, unit, buying_price, selling_price)
                    VALUES (:product_name, :unit, :buying_price, :selling_price)";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ":product_name" => $product_name,
                ":unit" => $unit,
                ":buying_price" => $buying_price,
                ":selling_price" => $selling_price
            ]);

            $message = "Product added successfully!";
            $message_type = "success";

        } catch (PDOException $e) {

            if ($e->getCode() == 23000) {
                $message = "This product already exists.";
                $message_type = "error";
            } else {
                $message = "Something went wrong. Please try again.";
                $message_type = "error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Penta Farm | Add Product</title>

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

        /* Main container */

        .container {
            width: 100%;
            max-width: 650px;
            margin: 50px auto;
            padding: 0 20px;
        }

        /* Card */

        .card {
            background: white;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .card h2 {
            font-size: 24px;
            color: #176b3a;
            margin-bottom: 8px;
        }

        .description {
            color: #777;
            font-size: 14px;
            margin-bottom: 30px;
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
            box-shadow: 0 0 0 3px rgba(36,150,83,0.12);
        }

        input::placeholder {
            color: #aaa;
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

        /* Messages */

        .message {
            padding: 12px 15px;
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

        /* Back link */

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

            <h2>Add Product</h2>

            <p class="description">
                Add a new product to your Penta Farm inventory.
            </p>

            <?php if ($message !== ""): ?>

                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>

            <?php endif; ?>


            <form method="POST">

                <div class="form-group">

                    <label for="product_name">
                        Product Name
                    </label>

                    <input
                        type="text"
                        id="product_name"
                        name="product_name"
                        placeholder="Enter product name as shown on invoice"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="unit">
                        Unit
                    </label>

                    <input
                        type="text"
                        id="unit"
                        name="unit"
                        placeholder="e.g. Piece, Bag, Metre, Kg"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="buying_price">
                        Buying Price (KSh)
                    </label>

                    <input
                        type="number"
                        id="buying_price"
                        name="buying_price"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        required
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
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        required
                    >

                </div>


                <button type="submit" class="button">
                    Save Product
                </button>

            </form>


            <a href="#" class="back-link">
                ← Back to Dashboard
            </a>

        </div>

    </main>

</body>

</html>