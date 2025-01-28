<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Purchase Order</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }

        .container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 0 auto;
        }

        h1 {
            margin-bottom: 20px;
            font-size: 2rem;
            color: #333;
            text-align: center;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-control {
            margin-bottom: 1rem;
        }

        .btn-save {
            width: 100%;
            padding: 0.5rem;
            font-size: 1.1rem;
        }

        .btn-back {
            margin-bottom: 1rem;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Create Purchase Order</h1>
        <a href="purchase_order_index.php" class="btn btn-secondary btn-back">Back to PO List</a>

        <form method="POST">
            <div class="mb-3">
                <label for="purchase_order_no" class="form-label">PO Number</label>
                <input type="text" class="form-control" id="purchase_order_no" name="purchase_order_no" required>
            </div>

            <div class="mb-3">
                <label for="units" class="form-label">Units</label>
                <input type="number" class="form-control" id="units" name="units" min="0">
            </div>

            <div class="mb-3">
                <label for="order_date" class="form-label">Order Date</label>
                <input type="date" class="form-control" id="order_date" name="order_date">
            </div>

            <button type="submit" class="btn btn-primary btn-save">Save</button>
        </form>
    </div>

    <!-- Bootstrap 5 JS (optional, for certain components) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>