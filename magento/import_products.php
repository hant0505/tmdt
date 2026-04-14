<?php
require 'app/bootstrap.php';

$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

$state = $objectManager->get('Magento\Framework\App\State');
$state->setAreaCode('adminhtml');

// Magento import header
$magentoHeader = [
    'sku', 'store_view_code', 'attribute_set_code', 'product_type', 'categories', 'product_websites',
    'name', 'description', 'short_description', 'weight', 'product_online', 'tax_class_name',
    'visibility', 'price', 'special_price', 'special_price_from_date', 'special_price_to_date',
    'url_key', 'meta_title', 'meta_keywords', 'meta_description', 'base_image', 'base_image_label',
    'small_image', 'small_image_label', 'thumbnail_image', 'thumbnail_image_label', 'swatch_image',
    'swatch_image_label', 'created_at', 'updated_at', 'new_from_date', 'new_to_date',
    'display_product_options_in', 'map_price', 'msrp_price', 'map_enabled', 'gift_message_available',
    'custom_design', 'custom_design_from', 'custom_design_to', 'custom_layout_update', 'page_layout',
    'product_options_container', 'msrp_display_actual_price_type', 'country_of_manufacture',
    'additional_attributes', 'qty', 'out_of_stock_qty', 'use_config_min_qty', 'is_qty_decimal',
    'allow_backorders', 'use_config_backorders', 'min_cart_qty', 'use_config_min_sale_qty',
    'max_cart_qty', 'use_config_max_sale_qty', 'is_in_stock', 'notify_on_stock_below',
    'use_config_notify_stock_qty', 'manage_stock', 'use_config_manage_stock', 'use_config_qty_increments',
    'qty_increments', 'use_config_enable_qty_inc', 'enable_qty_increments', 'is_decimal_divided',
    'website_id', 'related_skus', 'related_position', 'crosssell_skus', 'crosssell_position',
    'upsell_skus', 'upsell_position', 'additional_images', 'additional_image_labels',
    'hide_from_product_page', 'custom_options', 'bundle_price_type', 'bundle_sku_type',
    'bundle_price_view', 'bundle_weight_type', 'bundle_values', 'bundle_shipment_type',
    'associated_skus', 'downloadable_links', 'downloadable_samples', 'configurable_variations',
    'configurable_variation_labels'
];

// Read input CSV
$inputFile = 'var/import/magento_amazon_full_gallery_fixed.csv';
$imageDir = 'var/import/images/';

if (!file_exists($inputFile)) {
    die("Input file not found: $inputFile\n");
}

$handle = fopen($inputFile, 'r');
if (!$handle) {
    die("Cannot open input file\n");
}

// Skip header
fgetcsv($handle, 0, ',');

$products = [];
while (($row = fgetcsv($handle, 0, ',')) !== false) {
    $products[] = $row;
}
fclose($handle);

echo "Total products: " . count($products) . "\n";

// Process and split into chunks of 100
$chunkSize = 100;
$chunks = array_chunk($products, $chunkSize);

foreach ($chunks as $chunkIndex => $chunk) {
    $outputFile = "var/import/magento_import_" . str_pad($chunkIndex + 1, 3, '0', STR_PAD_LEFT) . ".csv";
    $outputHandle = fopen($outputFile, 'w');
    if (!$outputHandle) {
        die("Cannot create output file: $outputFile\n");
    }

    // Write header
    fputcsv($outputHandle, $magentoHeader);

    foreach ($chunk as $row) {
        $product = convertToMagentoFormat($row, $imageDir);
        fputcsv($outputHandle, $product);
    }

    fclose($outputHandle);
    echo "Created file: $outputFile with " . count($chunk) . " products\n";
}

echo "Processing complete. Generated CSV files in var/import/.\n";
echo "Import with Magento admin or a stable import pipeline.\n";

function convertToMagentoFormat($row, $imageDir) {
    // Input columns: sku,name,price,brand,categories,description,short_description,product_type,attribute_set_code,base_image,small_image,thumbnail_image,additional_images,qty,is_in_stock

    $sku = $row[0];
    $name = $row[1];
    $price = $row[2];
    $brand = $row[3];
    $categories = $row[4];
    $description = $row[5];
    $short_description = $row[6];
    $product_type = $row[7];
    $attribute_set_code = $row[8];
    $base_image = $row[9];
    $small_image = $row[10];
    $thumbnail_image = $row[11];
    $additional_images = $row[12];
    $qty = $row[13];
    $is_in_stock = $row[14];

    // Fix curly quotes
    $description = str_replace(['"', "'"], ['"', "'"], $description);
    $short_description = str_replace(['"', "'"], ['"', "'"], $short_description);

    // Check and fix images
    $base_image = checkImage($base_image, $imageDir);
    $small_image = checkImage($small_image, $imageDir);
    $thumbnail_image = checkImage($thumbnail_image, $imageDir);
    $additional_images = checkAdditionalImages($additional_images, $imageDir);

    // URL key: use sku for uniqueness
    $url_key = strtolower(str_replace(' ', '-', $sku));

    // Meta
    $meta_title = $name;
    $meta_description = $short_description;

    // Dates
    $now = date('Y-m-d H:i:s');

    // Additional attributes
    $additional_attributes = "brand=$brand";

    // Build product array
    $product = [
        $sku, // sku
        '', // store_view_code
        $attribute_set_code ?: 'Default', // attribute_set_code
        $product_type ?: 'simple', // product_type
        $categories ?: 'Default Category', // categories
        'base', // product_websites
        $name, // name
        $description, // description
        $short_description, // short_description
        '0', // weight
        '1', // product_online
        'Taxable Goods', // tax_class_name
        'Catalog, Search', // visibility
        $price, // price
        '', // special_price
        '', // special_price_from_date
        '', // special_price_to_date
        $url_key, // url_key
        $meta_title, // meta_title
        '', // meta_keywords
        $meta_description, // meta_description
        $base_image, // base_image
        '', // base_image_label
        $small_image, // small_image
        '', // small_image_label
        $thumbnail_image, // thumbnail_image
        '', // thumbnail_image_label
        '', // swatch_image
        '', // swatch_image_label
        $now, // created_at
        $now, // updated_at
        '', // new_from_date
        '', // new_to_date
        'Block after Info Column', // display_product_options_in
        '', // map_price
        '', // msrp_price
        'Use config', // map_enabled
        '', // gift_message_available
        '', // custom_design
        '', // custom_design_from
        '', // custom_design_to
        '', // custom_layout_update
        'Product -- Full Width', // page_layout
        'Use config', // product_options_container
        'Use config', // msrp_display_actual_price_type
        '', // country_of_manufacture
        $additional_attributes, // additional_attributes
        $qty, // qty
        '0', // out_of_stock_qty
        '1', // use_config_min_qty
        '0', // is_qty_decimal
        '0', // allow_backorders
        '1', // use_config_backorders
        '1', // min_cart_qty
        '1', // use_config_min_sale_qty
        '10000', // max_cart_qty
        '1', // use_config_max_sale_qty
        $is_in_stock, // is_in_stock
        '1', // notify_on_stock_below
        '1', // use_config_notify_stock_qty
        '1', // manage_stock
        '1', // use_config_manage_stock
        '1', // use_config_qty_increments
        '1', // qty_increments
        '1', // use_config_enable_qty_inc
        '0', // enable_qty_increments
        '0', // is_decimal_divided
        '1', // website_id
        '', // related_skus
        '', // related_position
        '', // crosssell_skus
        '', // crosssell_position
        '', // upsell_skus
        '', // upsell_position
        $additional_images, // additional_images
        '', // additional_image_labels
        '', // hide_from_product_page
        '', // custom_options
        '', // bundle_price_type
        '', // bundle_sku_type
        '', // bundle_price_view
        '', // bundle_weight_type
        '', // bundle_values
        '', // bundle_shipment_type
        '', // associated_skus
        '', // downloadable_links
        '', // downloadable_samples
        '', // configurable_variations
        '', // configurable_variation_labels
    ];

    return $product;
}

function checkImage($imagePath, $imageDir) {
    if (empty($imagePath)) return '';
    // Remove leading slash
    $imagePath = ltrim($imagePath, '/');
    if (file_exists($imageDir . $imagePath)) {
        return $imagePath;
    }
    return '';
}

function checkAdditionalImages($images, $imageDir) {
    if (empty($images)) return '';
    $imageList = explode(',', $images);
    $validImages = [];
    foreach ($imageList as $img) {
        $img = trim($img);
        $img = ltrim($img, '/');
        if (file_exists($imageDir . $img)) {
            $validImages[] = $img;
        }
    }
    return implode(',', $validImages);
}
?>