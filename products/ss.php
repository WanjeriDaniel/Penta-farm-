<?php

require_once "../config/database.php";

$search = "";
$products = [];


/*
|--------------------------------------------------------------------------
| NORMAL SEARCH
|--------------------------------------------------------------------------
| When a product is selected, its full name is sent here.
|--------------------------------------------------------------------------
*/

if (isset($_GET["search"])) {

    $search = trim($_GET["search"]);

    if ($search !== "") {

        $sql = "SELECT
                    id,
                    product_name,
                    unit,
                    buying_price,
                    selling_price,
                    (selling_price - buying_price) AS profit,
                    updated_at
                FROM products
                WHERE product_name = :search
                ORDER BY product_name ASC";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ":search" => $search
        ]);

        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}


/*
|--------------------------------------------------------------------------
| AUTOCOMPLETE SEARCH
|--------------------------------------------------------------------------
*/

if (isset($_GET["ajax"]) && $_GET["ajax"] === "1") {

    $search = trim($_GET["search"] ?? "");

    header("Content-Type: application/json");


    if ($search === "") {

        echo json_encode([]);

        exit;
    }


    /*
    | Find products beginning with what the user typed.
    */

    $sql = "SELECT
                id,
                product_name
            FROM products
            WHERE product_name LIKE :search
            ORDER BY product_name ASC
            LIMIT 10";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ":search" => $search . "%"
    ]);

    $suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($suggestions);

    exit;
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Penta Farm | Price Search</title>


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


/* MAIN */

.container {

    width: 100%;

    max-width: 1100px;

    margin: 45px auto;

    padding: 0 20px;
}


/* SEARCH CARD */

.search-card {

    background: white;

    padding: 30px;

    border-radius: 12px;

    box-shadow: 0 5px 20px rgba(0,0,0,0.08);

    margin-bottom: 25px;
}


.search-card h2 {

    color: #176b3a;

    font-size: 24px;

    margin-bottom: 8px;
}


.description {

    color: #777;

    font-size: 14px;

    margin-bottom: 25px;
}


/* SEARCH AREA */

.search-wrapper {

    position: relative;

    width: 100%;
}


.search-form {

    display: flex;

    gap: 10px;
}


.search-input {

    flex: 1;

    padding: 13px 15px;

    border: 1px solid #d5d5d5;

    border-radius: 7px;

    font-size: 15px;

    outline: none;
}


.search-input:focus {

    border-color: #249653;

    box-shadow: 0 0 0 3px rgba(36,150,83,0.12);
}


.search-button {

    padding: 13px 25px;

    border: none;

    border-radius: 7px;

    background: #176b3a;

    color: white;

    font-size: 15px;

    font-weight: bold;

    cursor: pointer;
}


.search-button:hover {

    background: #12552e;
}


/* PRODUCT SUGGESTIONS */

.suggestions {

    position: absolute;

    top: 52px;

    left: 0;

    right: 105px;

    background: white;

    border: 1px solid #ddd;

    border-radius: 8px;

    box-shadow: 0 8px 20px rgba(0,0,0,0.12);

    z-index: 1000;

    display: none;

    max-height: 300px;

    overflow-y: auto;
}


.suggestion-item {

    padding: 14px 16px;

    border-bottom: 1px solid #eee;

    cursor: pointer;

    font-size: 15px;

    font-weight: 600;

    color: #176b3a;

    background: white;
}


.suggestion-item:last-child {

    border-bottom: none;
}


.suggestion-item:hover {

    background: #f0f8f3;

}


/* RESULTS */

.results-card {

    background: white;

    border-radius: 12px;

    box-shadow: 0 5px 20px rgba(0,0,0,0.08);

    overflow: hidden;
}


.results-header {

    padding: 20px 25px;

    border-bottom: 1px solid #eee;
}


.results-header h3 {

    color: #176b3a;

    font-size: 18px;
}


.results-header p {

    color: #777;

    font-size: 13px;

    margin-top: 5px;
}


/* TABLE */

.table-container {

    overflow-x: auto;
}


table {

    width: 100%;

    border-collapse: collapse;

    min-width: 800px;
}


th {

    background: #f0f5f2;

    color: #444;

    text-align: left;

    padding: 14px 18px;

    font-size: 13px;

    text-transform: uppercase;
}


td {

    padding: 15px 18px;

    border-bottom: 1px solid #eee;

    font-size: 14px;
}


.product-name {

    font-weight: bold;

    color: #176b3a;
}


.price {

    font-weight: 600;
}


.profit {

    color: #176b3a;

    font-weight: bold;
}


/* WELCOME */

.welcome {

    background: white;

    padding: 40px;

    text-align: center;

    border-radius: 12px;

    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
}


.welcome h3 {

    color: #176b3a;

    margin-bottom: 10px;
}


.welcome p {

    color: #777;

    font-size: 14px;
}


/* NO RESULTS */

.no-results {

    background: white;

    padding: 35px;

    text-align: center;

    border-radius: 12px;

    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
}


.no-results h3 {

    color: #555;

    margin-bottom: 8px;
}


.no-results p {

    color: #888;

    font-size: 14px;
}


/* BACK */

.back-link {

    display: inline-block;

    margin-top: 20px;

    color: #176b3a;

    text-decoration: none;

    font-size: 14px;

    font-weight: bold;
}


/* MOBILE */

@media (max-width:600px) {

    .header {

        padding: 18px 20px;
    }


    .container {

        margin: 30px auto;

        padding: 0 15px;
    }


    .search-card {

        padding: 25px 20px;
    }


    .search-form {

        flex-direction: column;
    }


    .search-button {

        width: 100%;
    }


    .suggestions {

        right: 0;

        top: 105px;
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


<section class="search-card">

    <h2>Product Price Search</h2>

    <p class="description">

        Type the first few letters of a product and select it from the list.

    </p>


    <div class="search-wrapper">


        <form method="GET" class="search-form">

            <input

                type="text"

                name="search"

                id="searchInput"

                class="search-input"

                value="<?php echo htmlspecialchars($search); ?>"

                placeholder="Start typing product name..."

                autocomplete="off"

            >


            <button type="submit" class="search-button">

                🔍 Search

            </button>

        </form>


        <div id="suggestions" class="suggestions"></div>


    </div>

</section>



<?php if ($search !== ""): ?>


    <?php if (count($products) > 0): ?>


        <section class="results-card">


            <div class="results-header">

                <h3>Product Details</h3>

                <p>

                    Showing information for

                    <strong>

                        <?php echo htmlspecialchars($search); ?>

                    </strong>

                </p>

            </div>


            <div class="table-container">


                <table>


                    <thead>

                        <tr>

                            <th>Product</th>

                            <th>Unit</th>

                            <th>Buying Price</th>

                            <th>Selling Price</th>

                            <th>Profit</th>

                            <th>Last Updated</th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach ($products as $product): ?>


                        <tr>


                            <td class="product-name">

                                <?php echo htmlspecialchars($product["product_name"]); ?>

                            </td>


                            <td>

                                <?php echo htmlspecialchars($product["unit"]); ?>

                            </td>


                            <td class="price">

                                KSh

                                <?php

                                echo number_format(

                                    $product["buying_price"],

                                    2

                                );

                                ?>

                            </td>


                            <td class="price">

                                KSh

                                <?php

                                echo number_format(

                                    $product["selling_price"],

                                    2

                                );

                                ?>

                            </td>


                            <td class="profit">

                                KSh

                                <?php

                                echo number_format(

                                    $product["profit"],

                                    2

                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(

                                    $product["updated_at"]

                                );

                                ?>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>


            </div>

        </section>


    <?php else: ?>


        <section class="no-results">

            <h3>Product Not Found</h3>

            <p>

                No exact product was found for

                <strong>

                    <?php echo htmlspecialchars($search); ?>

                </strong>

            </p>

        </section>


    <?php endif; ?>


<?php else: ?>


    <section class="welcome">

        <h3>🔎 Search Your Inventory</h3>

        <p>

            Type the first letter of a product name to begin.

        </p>

    </section>


<?php endif; ?>



<a href="#" class="back-link">

    ← Back to Dashboard

</a>


</main>



<script>


const searchInput = document.getElementById("searchInput");

const suggestionsBox = document.getElementById("suggestions");


/*
|--------------------------------------------------------------------------
| LIVE PRODUCT SUGGESTIONS
|--------------------------------------------------------------------------
*/

searchInput.addEventListener("input", function () {


    const search = this.value.trim();


    /*
    | Nothing typed
    */

    if (search.length === 0) {

        suggestionsBox.innerHTML = "";

        suggestionsBox.style.display = "none";

        return;
    }


    /*
    | Ask PHP for matching product names
    */

    fetch(

        "search.php?ajax=1&search="

        + encodeURIComponent(search)

    )


    .then(response => response.json())


    .then(products => {


        suggestionsBox.innerHTML = "";


        /*
        | No products found
        */

        if (products.length === 0) {

            suggestionsBox.style.display = "none";

            return;
        }


        /*
        | Display product names
        */

        products.forEach(product => {


            const item = document.createElement("div");


            item.className = "suggestion-item";


            item.textContent = product.product_name;


            /*
            | User chooses product
            */

            item.addEventListener("click", function () {


                /*
                | Put selected product name
                | into search box
                */

                searchInput.value = product.product_name;


                /*
                | Hide suggestions
                */

                suggestionsBox.style.display = "none";


                /*
                | Submit search
                */

                searchInput.form.submit();

            });


            suggestionsBox.appendChild(item);

        });


        suggestionsBox.style.display = "block";

    })


    .catch(error => {

        console.error(error);

    });

});



/*
|--------------------------------------------------------------------------
| CLOSE DROPDOWN WHEN CLICKING OUTSIDE
|--------------------------------------------------------------------------
*/

document.addEventListener("click", function(event) {


    if (!event.target.closest(".search-wrapper")) {

        suggestionsBox.style.display = "none";

    }

});

</script>


</body>

</html>
```
