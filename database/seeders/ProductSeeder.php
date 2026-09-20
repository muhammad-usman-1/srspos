<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // [name, description, barcode, purchase price, sale price, quantity]
        $products = [
            ['Coca-Cola 500ml', 'Carbonated soft drink', '1000000000001', 0.60, 1.00, 120],
            ['Pepsi 500ml', 'Carbonated soft drink', '1000000000002', 0.55, 1.00, 100],
            ['Mineral Water 1.5L', 'Bottled drinking water', '1000000000003', 0.35, 0.70, 200],
            ['Orange Juice 1L', '100% orange juice', '1000000000004', 1.20, 2.20, 60],
            ['Milk 1L', 'Full cream fresh milk', '1000000000005', 0.90, 1.50, 80],
            ['White Bread', 'Sliced white loaf', '1000000000006', 0.80, 1.40, 50],
            ['Eggs (12 pack)', 'Farm fresh eggs', '1000000000007', 1.80, 2.80, 70],
            ['Basmati Rice 5kg', 'Long grain basmati rice', '1000000000008', 6.50, 9.00, 40],
            ['Sugar 1kg', 'Refined white sugar', '1000000000009', 0.80, 1.30, 90],
            ['Cooking Oil 1L', 'Sunflower cooking oil', '1000000000010', 2.20, 3.20, 55],
            ['Tea Bags (100)', 'Black tea bags', '1000000000011', 2.00, 3.30, 45],
            ['Instant Coffee 200g', 'Instant coffee granules', '1000000000012', 3.50, 5.50, 35],
            ['Potato Chips 150g', 'Salted potato chips', '1000000000013', 0.90, 1.60, 110],
            ['Chocolate Bar', 'Milk chocolate bar', '1000000000014', 0.60, 1.10, 150],
            ['Biscuits Pack', 'Assorted cream biscuits', '1000000000015', 0.50, 0.90, 130],
            ['Instant Noodles', 'Chicken flavour noodles', '1000000000016', 0.25, 0.50, 250],
            ['Toothpaste 100ml', 'Fluoride toothpaste', '1000000000017', 1.20, 2.00, 60],
            ['Shampoo 400ml', 'Anti-dandruff shampoo', '1000000000018', 2.80, 4.50, 40],
            ['Bath Soap', 'Moisturizing bath soap', '1000000000019', 0.50, 0.90, 140],
            ['Laundry Detergent 1kg', 'Washing powder', '1000000000020', 2.50, 4.00, 50],
            ['Dishwash Liquid 500ml', 'Lemon dishwashing liquid', '1000000000021', 1.10, 1.90, 65],
            ['Tissue Box', '2-ply facial tissues', '1000000000022', 0.70, 1.20, 90],
            ['Notebook A5', '100-page ruled notebook', '1000000000023', 0.60, 1.20, 8],
            ['Ballpoint Pen', 'Blue ink pen', '1000000000024', 0.10, 0.30, 5],
        ];

        $storeId = \App\Models\Store::value('id');

        foreach ($products as [$name, $description, $barcode, $purchasePrice, $price, $quantity]) {
            $product = Product::withoutGlobalScopes()->firstOrNew(['barcode' => $barcode, 'store_id' => $storeId]);
            $product->forceFill([
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'purchase_price' => $purchasePrice,
                'status' => true,
            ]);

            // Stock only changes through the ledger, so re-seeding never resets live stock.
            $product->quantity ??= 0;
            $product->save();

            if ($product->stockMovements()->withoutGlobalScopes()->doesntExist()) {
                $product->adjustStock($quantity, 'opening', null, 'Seeded opening stock');
            }
        }
    }
}
