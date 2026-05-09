<?php

return [
    'catalog' => 'Catalog',
    'product' => 'Product',
    'products' => 'Products',
    'general_information' => 'General Information',
    'organization' => 'Organization',
    'name_english' => 'Name (English)',
    'name_arabic' => 'Name (Arabic)',
    'description_en' => 'Description (English)',
    'description_ar' => 'Description (Arabic)',

    'variants' => 'Variants',
    'variant' => 'Variant',
    'variant_details' => 'Variant Details',
    'variant_name_en' => 'Variant Name (English)',
    'variant_name_ar' => 'Variant Name (Arabic)',
    'variant_name_helper' => 'Enter a descriptive name for this variant.',
    'default_variant' => 'Default',
    'add_variant' => 'Add Variant',
    'attribute_values' => 'Attribute Values',
    'attribute_values_helper' => 'Select values from the product\'s assigned attributes.',

    'pricing' => 'Pricing',

    // Retail Pricing
    'retail_price' => 'Retail Price',
    'retail_is_price_negotiable' => 'Retail Price is Negotiable',
    'retail_is_price_negotiable_helper' => 'Allow cashiers to override the retail price within the defined minimum.',
    'min_retail_price' => 'Min Retail Price',
    'min_retail_price_helper' => 'The lowest retail price allowed when negotiation is enabled.',
    'negotiable' => 'Negotiable',

    // Purchase / Cost
    'purchase_price' => 'Latest Purchase Cost',
    'purchase_price_helper' => 'The most recent price paid to the supplier. This is automatically updated when a purchase invoice is finalized.',

    // Wholesale Pricing
    'wholesale_enabled' => 'Enable Wholesale Pricing',
    'wholesale_enabled_helper' => 'Enable a separate wholesale price for this variant.',
    'wholesale_pricing' => 'Wholesale Pricing',
    'wholesale_price' => 'Wholesale Price',
    'wholesale_price_helper' => 'The price offered to wholesale customers.',
    'wholesale_is_price_negotiable' => 'Wholesale Price is Negotiable',
    'wholesale_is_price_negotiable_helper' => 'Allow cashiers to override the wholesale price within the defined minimum.',
    'min_wholesale_price' => 'Min Wholesale Price',
    'min_wholesale_price_helper' => 'The lowest wholesale price allowed when negotiation is enabled.',
    'wholesale_qty_threshold' => 'Wholesale Qty Threshold',
    'wholesale_qty_threshold_helper' => 'Minimum quantity to apply wholesale pricing. Set to 0 to let the cashier decide.',

    'inventory' => 'Inventory',
    'quantity' => 'Quantity',
    'stock' => 'Stock',
    'low_stock_threshold' => 'Low Stock Threshold',
    'low_stock_threshold_helper' => 'Alert when stock falls below this quantity. Leave blank to disable.',
    'low_stock' => 'Low Stock',

    'barcodes' => 'Barcodes',
    'barcode' => 'Barcode',
    'add_barcode' => 'Add Barcode',

    'name_en_helper' => 'The name of the product in English as it will appear on receipts and the store.',
    'name_ar_helper' => 'The name of the product in Arabic as it will appear on receipts and the store.',
    'description_en_helper' => 'Detailed information about the product in English.',
    'description_ar_helper' => 'Detailed information about the product in Arabic.',
    'retail_price_helper' => 'The standard retail selling price per unit.',
    'quantity_helper' => 'The total number of units currently available in stock.',
    'is_active_helper' => 'If disabled, this product will not be visible in the POS system.',
    'barcode_input_helper' => 'Scan the product barcode or type it manually.',

    // Filters
    'out_of_stock' => 'Out of Stock',
    'no_barcode' => 'No Barcode',
    'retail_price_range' => 'Retail Price Range',
    'price_from' => 'Price From',
    'price_to' => 'Price To',
    'retail_price_from' => 'Retail Price From',
    'retail_price_to' => 'Retail Price To',
    'wholesale_price_range' => 'Wholesale Price Range',
    'wholesale_price_from' => 'Wholesale Price From',
    'wholesale_price_to' => 'Wholesale Price To',
    'purchase_price_range' => 'Latest Purchase Cost Range',
    'purchase_price_from' => 'Latest Purchase Cost From',
    'purchase_price_to' => 'Latest Purchase Cost To',
    'retail_margin' => 'Retail Margin',
    'wholesale_margin' => 'Wholesale Margin',
];
